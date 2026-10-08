<?php
// admin/ajax_search_products.php
// Asynchronous search endpoint for products in the Admin Dashboard

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

// Guarantee admin session
if (!is_admin_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

header('Content-Type: application/json');

$query = trim($_GET['q'] ?? $_GET['query'] ?? '');
$category_id = (int)($_GET['category_id'] ?? 0);
$stock_status = trim($_GET['stock_status'] ?? 'all');
$product_type = trim($_GET['product_type'] ?? 'all');
$status = trim($_GET['status'] ?? 'all');
$limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));

try {
    $where_clauses = ["1=1"];
    $params = [];

    // Search query matching multiple product attributes
    if ($query !== '') {
        $search_param = '%' . $query . '%';
        $where_clauses[] = "(
            p.name LIKE ? 
            OR p.sku LIKE ? 
            OR c.name LIKE ? 
            OR p.description LIKE ? 
            OR p.fabric_details LIKE ? 
            OR EXISTS (SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id AND pv.sku LIKE ?)
            OR EXISTS (SELECT 1 FROM product_fabrics pf JOIN fabrics fb ON pf.fabric_id = fb.id WHERE pf.product_id = p.id AND fb.name LIKE ?)
        )";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    // Category filter
    if ($category_id > 0) {
        $where_clauses[] = "p.category_id = ?";
        $params[] = $category_id;
    }

    // Product Type filter
    if (in_array($product_type, ['single', 'combo'])) {
        $where_clauses[] = "p.product_type = ?";
        $params[] = $product_type;
    }

    // Published Status filter
    if ($status === 'active') {
        $where_clauses[] = "p.status = 1";
    } elseif ($status === 'inactive') {
        $where_clauses[] = "p.status = 0";
    }

    $where_sql = implode(' AND ', $where_clauses);

    // Stock status filter in WHERE clause
    if ($stock_status === 'out_of_stock') {
        $where_clauses[] = "(p.product_type = 'single' AND COALESCE((SELECT SUM(stock) FROM product_variants WHERE product_id = p.id), 0) <= 0)";
    } elseif ($stock_status === 'low_stock') {
        $where_clauses[] = "(p.product_type = 'single' AND (
            EXISTS (SELECT 1 FROM product_variants pv_ls WHERE pv_ls.product_id = p.id AND pv_ls.stock <= 5) 
            OR COALESCE((SELECT SUM(stock) FROM product_variants WHERE product_id = p.id), 0) <= 5
        ))";
    } elseif ($stock_status === 'in_stock') {
        $where_clauses[] = "(p.product_type = 'combo' OR COALESCE((SELECT SUM(stock) FROM product_variants WHERE product_id = p.id), 0) > 5)";
    }

    $where_sql = implode(' AND ', $where_clauses);

    // Count total matches
    $count_sql = "
        SELECT COUNT(p.id) as cnt
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE {$where_sql}
    ";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = (int)$count_stmt->fetchColumn();

    // Fetch matching products with stock, primary image, and category
    $sql = "
        SELECT 
            p.id, 
            p.name, 
            p.slug, 
            p.sku, 
            p.product_type, 
            p.regular_price, 
            p.offer_price, 
            p.status, 
            p.is_featured, 
            p.is_new_arrival, 
            p.is_best_seller,
            c.name as category_name,
            (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_featured DESC, id ASC LIMIT 1) as main_image,
            (SELECT SUM(stock) FROM product_variants WHERE product_id = p.id) as total_stock,
            (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) as variant_count,
            (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id AND stock <= 5) as low_stock_variants
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE {$where_sql}
        ORDER BY p.id DESC 
        LIMIT {$limit}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $results = [];
    foreach ($rows as $row) {
        $type = $row['product_type'] ?? 'single';
        $stock = $row['total_stock'] !== null ? (int)$row['total_stock'] : 0;
        
        $stock_label = '';
        $stock_badge_class = '';
        if ($type === 'combo') {
            $stock_label = 'Dynamic Set';
            $stock_badge_class = 'bg-purple-subtle text-purple border';
        } elseif ($stock <= 0) {
            $stock_label = 'Out of Stock (0)';
            $stock_badge_class = 'bg-danger text-white';
        } elseif ($stock <= 5) {
            $stock_label = 'Low Stock (' . $stock . ')';
            $stock_badge_class = 'bg-warning text-dark';
        } else {
            $stock_label = $stock . ' in stock';
            $stock_badge_class = 'bg-success-subtle text-success border border-success-subtle';
        }

        $image_url = '';
        if (!empty($row['main_image'])) {
            $image_url = '../uploads/' . $row['main_image'];
        }

        $results[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'sku' => $row['sku'],
            'product_type' => $type,
            'category_name' => $row['category_name'],
            'regular_price' => (float)$row['regular_price'],
            'offer_price' => $row['offer_price'] !== null ? (float)$row['offer_price'] : null,
            'formatted_regular_price' => format_price($row['regular_price']),
            'formatted_offer_price' => $row['offer_price'] !== null ? format_price($row['offer_price']) : null,
            'main_image' => $row['main_image'],
            'image_url' => $image_url,
            'total_stock' => $stock,
            'stock_label' => $stock_label,
            'stock_badge_class' => $stock_badge_class,
            'status' => (int)$row['status'],
            'is_featured' => (int)$row['is_featured'],
            'is_new_arrival' => (int)$row['is_new_arrival'],
            'is_best_seller' => (int)$row['is_best_seller'],
            'variant_count' => (int)$row['variant_count'],
            'edit_url' => 'products.php?action=edit&id=' . (int)$row['id'],
            'matrix_url' => $type === 'combo' ? ('combo_components.php?combo_id=' . (int)$row['id']) : ('variants.php?product_id=' . (int)$row['id']),
            'view_url' => '../product-detail.php?slug=' . urlencode($row['slug'])
        ];
    }

    echo json_encode([
        'status' => 'success',
        'query' => $query,
        'total_count' => $total_count,
        'results_count' => count($results),
        'results' => $results
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database search error: ' . $e->getMessage()
    ]);
}
