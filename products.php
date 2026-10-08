<?php
// products.php
// Public Catalog Listing with Filters

$meta_title = 'Shop Ladies\' Lingerie & Innerwear | Pookky';
$meta_desc = 'Explore our premium innerwear collection. Filter by size, color, fabric, and price range to find your perfect fit.';
require_once __DIR__ . '/includes/header.php';

// Retrieve filter inputs
$search = trim($_GET['search'] ?? '');
$category_slug = trim($_GET['category'] ?? '');
$selected_sizes = $_GET['sizes'] ?? [];
$selected_colors = $_GET['colors'] ?? [];
$selected_fabrics = $_GET['fabrics'] ?? [];
$price_min = isset($_GET['price_min']) ? (float)$_GET['price_min'] : 0;
$price_max = isset($_GET['price_max']) ? (float)$_GET['price_max'] : 5000;
$sort_by = $_GET['sort'] ?? 'newest';
$product_type = trim($_GET['type'] ?? '');

try {
    // 1. Build Query dynamically
    $query_str = "
        SELECT DISTINCT p.*, c.name as category_name,
        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_featured DESC LIMIT 1) as main_image 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        LEFT JOIN product_variants pv ON p.id = pv.product_id 
        WHERE p.status = 1
    ";
    $params = [];

    // Filter by Product Type (e.g. combo or standard)
    if (!empty($product_type)) {
        $query_str .= " AND p.product_type = ? ";
        $params[] = $product_type;
    }

    // Filter by Category
    if (!empty($category_slug)) {
        $query_str .= " AND c.slug = ? ";
        $params[] = $category_slug;
    }

    // Filter by Search Query
    if (!empty($search)) {
        $query_str .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?) ";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // Filter by Sizes
    if (!empty($selected_sizes)) {
        $in_clause = implode(',', array_fill(0, count($selected_sizes), '?'));
        $query_str .= " AND pv.size_id IN ($in_clause) ";
        foreach ($selected_sizes as $s_id) {
            $params[] = (int)$s_id;
        }
    }

    // Filter by Colors
    if (!empty($selected_colors)) {
        $in_clause = implode(',', array_fill(0, count($selected_colors), '?'));
        $query_str .= " AND pv.color_id IN ($in_clause) ";
        foreach ($selected_colors as $c_id) {
            $params[] = (int)$c_id;
        }
    }

    // Filter by Fabric (approximate substring checks or mapped product_fabrics)
    if (!empty($selected_fabrics)) {
        $fabric_queries = [];
        foreach ($selected_fabrics as $fab) {
            $fabric_queries[] = "(p.fabric_details LIKE ? OR EXISTS (SELECT 1 FROM product_fabrics pf JOIN fabrics f ON pf.fabric_id = f.id WHERE pf.product_id = p.id AND (f.name = ? OR f.id = ?)))";
            $params[] = "%$fab%";
            $params[] = $fab;
            $params[] = is_numeric($fab) ? (int)$fab : 0;
        }
        $query_str .= " AND (" . implode(" OR ", $fabric_queries) . ") ";
    }

    // Filter by Price range
    $query_str .= " AND (COALESCE(p.offer_price, p.regular_price) BETWEEN ? AND ?) ";
    $params[] = $price_min;
    $params[] = $price_max;

    // Sorting
    if ($sort_by === 'price_asc') {
        $query_str .= " ORDER BY COALESCE(p.offer_price, p.regular_price) ASC ";
    } elseif ($sort_by === 'price_desc') {
        $query_str .= " ORDER BY COALESCE(p.offer_price, p.regular_price) DESC ";
    } elseif ($sort_by === 'best') {
        $query_str .= " ORDER BY p.is_best_seller DESC, p.id DESC ";
    } else {
        $query_str .= " ORDER BY p.id DESC "; // newest
    }

    $stmt = $pdo->prepare($query_str);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Fetch options to populate filters
    $filter_categories = $pdo->query("SELECT * FROM categories WHERE status = 1 ORDER BY name ASC")->fetchAll();
    $filter_sizes = $pdo->query("SELECT * FROM sizes ORDER BY category_type ASC, id ASC")->fetchAll();
    sort_sizes($filter_sizes, true);
    $filter_colors = $pdo->query("SELECT * FROM colors ORDER BY color_name ASC")->fetchAll();

    // Dynamic fabric filters from database with mapped product counts
    try {
        $filter_fabrics = $pdo->query("
            SELECT f.*, 
            (
                SELECT COUNT(DISTINCT p.id) 
                FROM products p 
                LEFT JOIN product_fabrics pf ON p.id = pf.product_id 
                WHERE p.status = 1 AND (pf.fabric_id = f.id OR p.fabric_details LIKE CONCAT('%', f.name, '%'))
            ) as prod_count 
            FROM fabrics f 
            WHERE f.status = 1 
            ORDER BY f.name ASC
        ")->fetchAll();
    } catch (PDOException $e) {
        $filter_fabrics = [];
    }

    if (empty($filter_fabrics)) {
        // Fallback options
        $fallback_fabrics = ['Cotton', 'Lace', 'Satin', 'Nylon', 'Elastane', 'Spandex', 'Polyester'];
        $filter_fabrics = array_map(function($name) {
            return ['id' => $name, 'name' => $name, 'prod_count' => 0];
        }, $fallback_fabrics);
    }

} catch (PDOException $e) {
    $products = [];
    set_flash_message('error', 'Failed to retrieve products.');
}
?>

<div class="container pt-4 pb-2">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb mb-1 small">
            <li class="breadcrumb-item"><a href="index.php" class="text-muted text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">
                <?php echo !empty($category_slug) ? e(ucwords(str_replace('-', ' ', $category_slug))) : 'Shop Catalog'; ?>
            </li>
        </ol>
    </nav>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end border-bottom pb-3 mb-4">
        <div>
            <span class="brand-accent-tag d-block mb-1">Curated Collections</span>
            <h1 class="page-title mb-0">
                <?php 
                if (!empty($category_slug)) {
                    echo e(ucwords(str_replace('-', ' ', $category_slug)));
                } elseif (!empty($search)) {
                    echo 'Search: "' . e($search) . '"';
                } else {
                    echo 'Women\'s Collection';
                }
                ?>
            </h1>
        </div>
        <p class="text-muted small mb-0 mt-2 mt-md-0">
            Showing <strong class="text-dark fw-semibold"><?php echo count($products); ?></strong> styles crafted for refined comfort & elegance
        </p>
    </div>

    <!-- Mobile Horizontal Category Pills Bar (Smooth swipe on mobile) -->
    <div class="d-lg-none mb-3">
        <div class="category-scroll-chips d-flex gap-2 overflow-x-auto pb-2" style="-webkit-overflow-scrolling: touch; scrollbar-width: none;">
            <a href="products.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" 
               class="badge rounded-pill px-3 py-2 text-decoration-none text-nowrap <?php echo empty($category_slug) ? 'bg-brand text-white shadow-sm' : 'bg-white text-dark border'; ?>">
                All Collections
            </a>
            <?php foreach ($filter_categories as $f_cat): ?>
                <a href="products.php?category=<?php echo e($f_cat['slug']); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="badge rounded-pill px-3 py-2 text-decoration-none text-nowrap <?php echo $category_slug === $f_cat['slug'] ? 'bg-brand text-white shadow-sm' : 'bg-white text-dark border'; ?>">
                    <?php echo e($f_cat['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    // Calculate active filter metrics
    $active_filters_list = [];
    if (!empty($category_slug)) {
        $cat_match = array_filter($filter_categories, fn($c) => $c['slug'] === $category_slug);
        $cat_name = !empty($cat_match) ? reset($cat_match)['name'] : ucwords(str_replace('-', ' ', $category_slug));
        
        // Build remove url
        $q_copy = $_GET;
        unset($q_copy['category']);
        $remove_url = 'products.php' . (!empty($q_copy) ? '?' . http_build_query($q_copy) : '');
        $active_filters_list[] = ['label' => 'Category: ' . $cat_name, 'url' => $remove_url];
    }
    if (!empty($search)) {
        $q_copy = $_GET;
        unset($q_copy['search']);
        $remove_url = 'products.php' . (!empty($q_copy) ? '?' . http_build_query($q_copy) : '');
        $active_filters_list[] = ['label' => 'Search: "' . $search . '"', 'url' => $remove_url];
    }
    if ($price_min > 0 || $price_max < 5000) {
        $q_copy = $_GET;
        unset($q_copy['price_min'], $q_copy['price_max']);
        $remove_url = 'products.php' . (!empty($q_copy) ? '?' . http_build_query($q_copy) : '');
        $active_filters_list[] = ['label' => 'Price: ₹' . (int)$price_min . ' - ₹' . (int)$price_max, 'url' => $remove_url];
    }
    if (!empty($selected_sizes)) {
        foreach ($selected_sizes as $s_id) {
            $sz_match = array_filter($filter_sizes, fn($s) => $s['id'] == $s_id);
            $sz_code = !empty($sz_match) ? reset($sz_match)['size_code'] : 'Size ' . $s_id;
            $q_copy = $_GET;
            $q_copy['sizes'] = array_values(array_diff($q_copy['sizes'], [$s_id]));
            if (empty($q_copy['sizes'])) unset($q_copy['sizes']);
            $remove_url = 'products.php' . (!empty($q_copy) ? '?' . http_build_query($q_copy) : '');
            $active_filters_list[] = ['label' => 'Size: ' . $sz_code, 'url' => $remove_url];
        }
    }
    if (!empty($selected_colors)) {
        foreach ($selected_colors as $c_id) {
            $clr_match = array_filter($filter_colors, fn($c) => $c['id'] == $c_id);
            $clr_name = !empty($clr_match) ? reset($clr_match)['color_name'] : 'Color ' . $c_id;
            $q_copy = $_GET;
            $q_copy['colors'] = array_values(array_diff($q_copy['colors'], [$c_id]));
            if (empty($q_copy['colors'])) unset($q_copy['colors']);
            $remove_url = 'products.php' . (!empty($q_copy) ? '?' . http_build_query($q_copy) : '');
            $active_filters_list[] = ['label' => 'Color: ' . $clr_name, 'url' => $remove_url];
        }
    }
    if (!empty($selected_fabrics)) {
        foreach ($selected_fabrics as $fab_val) {
            $q_copy = $_GET;
            $q_copy['fabrics'] = array_values(array_diff($q_copy['fabrics'], [$fab_val]));
            if (empty($q_copy['fabrics'])) unset($q_copy['fabrics']);
            $remove_url = 'products.php' . (!empty($q_copy) ? '?' . http_build_query($q_copy) : '');
            $active_filters_list[] = ['label' => 'Fabric: ' . $fab_val, 'url' => $remove_url];
        }
    }
    ?>

    <!-- Mobile Top Filter & Sort Bar (Visible on mobile screens) -->
    <div class="d-lg-none bg-white p-2 p-sm-3 rounded-3 shadow-sm mb-3 border">
        <div class="d-flex justify-content-between align-items-center gap-2">
            <button type="button" class="btn btn-outline-dark btn-sm rounded-pill d-flex align-items-center gap-2 px-3 flex-grow-1 justify-content-center" data-bs-toggle="offcanvas" data-bs-target="#mobileFilterDrawer">
                <i class="bi bi-sliders2 text-rose"></i>
                <span class="fw-semibold">Filters</span>
                <?php if (count($active_filters_list) > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-1" style="font-size: 11px;"><?php echo count($active_filters_list); ?></span>
                <?php endif; ?>
            </button>

            <form action="products.php" method="GET" class="d-flex align-items-center flex-grow-1" id="mobile-sort-form">
                <?php if(!empty($category_slug)): ?><input type="hidden" name="category" value="<?php echo e($category_slug); ?>"><?php endif; ?>
                <?php if(!empty($search)): ?><input type="hidden" name="search" value="<?php echo e($search); ?>"><?php endif; ?>
                <?php foreach($selected_sizes as $s): ?><input type="hidden" name="sizes[]" value="<?php echo $s; ?>"><?php endforeach; ?>
                <?php foreach($selected_colors as $c): ?><input type="hidden" name="colors[]" value="<?php echo $c; ?>"><?php endforeach; ?>
                <?php foreach($selected_fabrics as $f): ?><input type="hidden" name="fabrics[]" value="<?php echo $f; ?>"><?php endforeach; ?>
                <input type="hidden" name="price_min" value="<?php echo $price_min; ?>">
                <input type="hidden" name="price_max" value="<?php echo $price_max; ?>">
                
                <select class="form-select form-select-sm rounded-pill text-truncate" name="sort" onchange="this.form.submit()" style="font-size: 13px;">
                    <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest</option>
                    <option value="best" <?php echo $sort_by === 'best' ? 'selected' : ''; ?>>Popular</option>
                    <option value="price_asc" <?php echo $sort_by === 'price_asc' ? 'selected' : ''; ?>>Price: Low-High</option>
                    <option value="price_desc" <?php echo $sort_by === 'price_desc' ? 'selected' : ''; ?>>Price: High-Low</option>
                </select>
            </form>
        </div>

        <!-- Active Filter Dismissible Pills -->
        <?php if (!empty($active_filters_list)): ?>
            <div class="d-flex flex-wrap gap-1 align-items-center pt-2 mt-2 border-top">
                <span class="text-muted small me-1" style="font-size: 11px;">Active:</span>
                <?php foreach ($active_filters_list as $af): ?>
                    <a href="<?php echo e($af['url']); ?>" class="badge bg-light text-dark border rounded-pill d-inline-flex align-items-center gap-1 text-decoration-none py-1 px-2" style="font-size: 11px;">
                        <span><?php echo e($af['label']); ?></span>
                        <i class="bi bi-x fs-6"></i>
                    </a>
                <?php endforeach; ?>
                <a href="products.php" class="small text-danger ms-auto text-decoration-none" style="font-size: 11px; font-weight: 500;">Clear All</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="container pb-5">
    <div class="row g-4">
        <!-- 1. Desktop Sidebar Filter Form (Hidden on mobile/tablet) -->
        <div class="col-12 col-lg-3 d-none d-lg-block">
            <div class="card glass-card p-4 border-0 mb-4 bg-white" style="border-radius: 12px; position: sticky; top: 90px;">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <h5 class="fw-semibold mb-0 text-dark" style="font-size: 1.05rem;">Filter Products</h5>
                    <?php if (count($active_filters_list) > 0): ?>
                        <a href="products.php" class="text-danger small text-decoration-none">Reset All</a>
                    <?php endif; ?>
                </div>

                <form action="products.php" method="GET">
                    <!-- Carry search key -->
                    <?php if(!empty($search)): ?>
                        <input type="hidden" name="search" value="<?php echo e($search); ?>">
                    <?php endif; ?>

                    <!-- Categories -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">Category</label>
                        <select class="form-select form-select-sm" name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($filter_categories as $f_cat): ?>
                                <option value="<?php echo $f_cat['slug']; ?>" <?php echo $category_slug === $f_cat['slug'] ? 'selected' : ''; ?>>
                                    <?php echo e($f_cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Price Range Slider values -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">Price Range (₹)</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="number" class="form-control form-control-sm" name="price_min" value="<?php echo $price_min; ?>" placeholder="Min" min="0">
                            </div>
                            <div class="col-6">
                                <input type="number" class="form-control form-control-sm" name="price_max" value="<?php echo $price_max; ?>" placeholder="Max" min="0">
                            </div>
                        </div>
                    </div>

                    <!-- Sizes checkboxes -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-2 d-block" style="letter-spacing: 0.5px;">Size Options</label>
                        <div class="overflow-y-auto pe-1" style="max-height: 150px;">
                            <?php foreach ($filter_sizes as $f_sz): ?>
                                <div class="form-check small mb-1">
                                    <input class="form-check-input" type="checkbox" name="sizes[]" value="<?php echo $f_sz['id']; ?>" id="size_<?php echo $f_sz['id']; ?>" <?php echo in_array($f_sz['id'], $selected_sizes) ? 'checked' : ''; ?>>
                                    <label class="form-check-label text-dark" for="size_<?php echo $f_sz['id']; ?>">
                                        <?php echo e($f_sz['size_code']); ?> <span class="text-muted" style="font-size: 10px;">(<?php echo e($f_sz['category_type']); ?>)</span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Colors checkboxes -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-2 d-block" style="letter-spacing: 0.5px;">Color Swatches</label>
                        <div class="overflow-y-auto pe-1" style="max-height: 150px;">
                            <?php foreach ($filter_colors as $f_cl): ?>
                                <div class="form-check small mb-1 d-flex align-items-center gap-2">
                                    <input class="form-check-input" type="checkbox" name="colors[]" value="<?php echo $f_cl['id']; ?>" id="color_<?php echo $f_cl['id']; ?>" <?php echo in_array($f_cl['id'], $selected_colors) ? 'checked' : ''; ?>>
                                    <span class="border rounded-circle d-inline-block flex-shrink-0" style="background-color: <?php echo e($f_cl['color_code']); ?>; width: 14px; height: 14px;"></span>
                                    <label class="form-check-label text-dark text-truncate" for="color_<?php echo $f_cl['id']; ?>">
                                        <?php echo e($f_cl['color_name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Fabric Checkboxes -->
                    <?php if (!empty($filter_fabrics)): ?>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-2 d-block" style="letter-spacing: 0.5px;">Fabric Material</label>
                        <div class="overflow-y-auto pe-1" style="max-height: 160px;">
                            <?php foreach ($filter_fabrics as $fab): 
                                $fab_val = $fab['name'];
                                $is_checked = in_array($fab_val, $selected_fabrics) || in_array((string)$fab['id'], $selected_fabrics);
                            ?>
                                <div class="form-check small mb-1 d-flex justify-content-between align-items-center">
                                    <div>
                                        <input class="form-check-input" type="checkbox" name="fabrics[]" value="<?php echo e($fab_val); ?>" id="fab_<?php echo $fab['id']; ?>" <?php echo $is_checked ? 'checked' : ''; ?>>
                                        <label class="form-check-label text-dark" for="fab_<?php echo $fab['id']; ?>">
                                            <?php echo e($fab['name']); ?>
                                        </label>
                                    </div>
                                    <?php if (($fab['prod_count'] ?? 0) > 0): ?>
                                        <span class="badge bg-light text-muted border rounded-pill" style="font-size: 10px;"><?php echo (int)$fab['prod_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-sm btn-brand">Apply Filters</button>
                        <a href="products.php" class="btn btn-sm btn-outline-secondary rounded-pill">Reset All</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2. Products List Grid -->
        <div class="col-12 col-lg-9">
            <!-- Desktop header sorting control (Hidden on mobile/tablet) -->
            <div class="d-none d-lg-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded-3">
                <div class="small text-muted">
                    Found <strong class="text-dark"><?php echo count($products); ?></strong> items in catalog.
                </div>
                <form action="products.php" method="GET" class="d-flex align-items-center gap-2">
                    <!-- Preserve existing filters -->
                    <?php if(!empty($category_slug)): ?><input type="hidden" name="category" value="<?php echo e($category_slug); ?>"><?php endif; ?>
                    <?php if(!empty($search)): ?><input type="hidden" name="search" value="<?php echo e($search); ?>"><?php endif; ?>
                    <?php foreach($selected_sizes as $s): ?><input type="hidden" name="sizes[]" value="<?php echo $s; ?>"><?php endforeach; ?>
                    <?php foreach($selected_colors as $c): ?><input type="hidden" name="colors[]" value="<?php echo $c; ?>"><?php endforeach; ?>
                    <?php foreach($selected_fabrics as $f): ?><input type="hidden" name="fabrics[]" value="<?php echo $f; ?>"><?php endforeach; ?>
                    <input type="hidden" name="price_min" value="<?php echo $price_min; ?>">
                    <input type="hidden" name="price_max" value="<?php echo $price_max; ?>">
                    
                    <label for="sort" class="small text-muted text-nowrap">Sort By</label>
                    <select class="form-select form-select-sm" name="sort" id="sort" onchange="this.form.submit()">
                        <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest arrivals</option>
                        <option value="best" <?php echo $sort_by === 'best' ? 'selected' : ''; ?>>Popularity</option>
                        <option value="price_asc" <?php echo $sort_by === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo $sort_by === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                    </select>
                </form>
            </div>

            <!-- Products catalog grid (Responsive 2 columns on mobile, 3 columns on tablet/desktop) -->
            <div class="row g-3 g-md-4">
                <?php if (empty($products)): ?>
                    <div class="col-12 text-center py-5 bg-white rounded-3 shadow-sm my-2">
                        <div class="fs-1 text-muted"><i class="bi bi-emoji-frown"></i></div>
                        <h4 class="fw-bold mt-3">No matching items found</h4>
                        <p class="text-muted small">Try broadening your filter criteria or adjusting tags.</p>
                        <a href="products.php" class="btn btn-sm btn-brand mt-2">Clear All Filters</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $prod): ?>
                        <div class="col-6 col-md-4">
                            <div class="card product-card h-100">
                                <div class="img-container">
                                    <div class="badge-overlay d-flex flex-column gap-1 align-items-start">
                                        <?php if (($prod['product_type'] ?? '') === 'combo'): ?>
                                            <span class="badge bg-purple rounded-pill px-2 py-1 shadow-sm" style="font-size:10px;"><i class="bi bi-stars"></i> COMBO</span>
                                        <?php endif; ?>
                                        <?php if ($prod['offer_price']): ?>
                                            <span class="badge bg-danger rounded-pill px-2 py-1 shadow-sm" style="font-size:10px;">OFFER</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="wishlist-overlay wishlist-toggle" data-product-id="<?php echo $prod['id']; ?>">
                                        <i class="bi bi-heart"></i>
                                    </div>
                                    <a href="product-detail.php?slug=<?php echo e($prod['slug']); ?>">
                                        <?php if ($prod['main_image']): ?>
                                            <img src="uploads/<?php echo e($prod['main_image']); ?>" alt="<?php echo e($prod['name']); ?>" loading="lazy">
                                        <?php else: ?>
                                            <div class="w-100 h-100 bg-secondary d-flex align-items-center justify-content-center text-white"><i class="bi bi-image fs-1"></i></div>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div class="product-details text-center">
                                    <span class="product-category"><?php echo e($prod['category_name']); ?></span>
                                    <a href="product-detail.php?slug=<?php echo e($prod['slug']); ?>">
                                        <h6 class="product-title text-truncate-2"><?php echo e($prod['name']); ?></h6>
                                    </a>
                                    <div class="price-container justify-content-center">
                                        <span class="price-regular"><?php echo format_price($prod['offer_price'] ?? $prod['regular_price']); ?></span>
                                        <?php if ($prod['offer_price']): ?>
                                            <span class="price-original"><?php echo format_price($prod['regular_price']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Filter Offcanvas Drawer -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileFilterDrawer" aria-labelledby="mobileFilterDrawerLabel">
    <div class="offcanvas-header border-bottom bg-light">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-sliders2 text-rose fs-5"></i>
            <h5 class="offcanvas-title fw-semibold text-dark" id="mobileFilterDrawerLabel">Filters</h5>
            <?php if (count($active_filters_list) > 0): ?>
                <span class="badge bg-danger rounded-pill"><?php echo count($active_filters_list); ?></span>
            <?php endif; ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-4">
        <form action="products.php" method="GET" id="mobile-filter-drawer-form">
            <?php if(!empty($search)): ?>
                <input type="hidden" name="search" value="<?php echo e($search); ?>">
            <?php endif; ?>
            <?php if(!empty($sort_by)): ?>
                <input type="hidden" name="sort" value="<?php echo e($sort_by); ?>">
            <?php endif; ?>

            <!-- Categories -->
            <div class="mb-4">
                <label class="form-label small fw-bold text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">Category</label>
                <select class="form-select" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($filter_categories as $f_cat): ?>
                        <option value="<?php echo $f_cat['slug']; ?>" <?php echo $category_slug === $f_cat['slug'] ? 'selected' : ''; ?>>
                            <?php echo e($f_cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Price Range -->
            <div class="mb-4">
                <label class="form-label small fw-bold text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">Price Range (₹)</label>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control" name="price_min" value="<?php echo $price_min; ?>" placeholder="Min" min="0">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control" name="price_max" value="<?php echo $price_max; ?>" placeholder="Max" min="0">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sizes -->
            <div class="mb-4">
                <label class="form-label small fw-bold text-muted text-uppercase mb-2 d-block" style="letter-spacing: 0.5px;">Size Options</label>
                <div class="row g-2">
                    <?php foreach ($filter_sizes as $f_sz): ?>
                        <div class="col-6">
                            <div class="form-check small p-2 border rounded bg-light">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="sizes[]" value="<?php echo $f_sz['id']; ?>" id="m_size_<?php echo $f_sz['id']; ?>" <?php echo in_array($f_sz['id'], $selected_sizes) ? 'checked' : ''; ?>>
                                <label class="form-check-label text-dark fw-medium" for="m_size_<?php echo $f_sz['id']; ?>">
                                    <?php echo e($f_sz['size_code']); ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Colors -->
            <div class="mb-4">
                <label class="form-label small fw-bold text-muted text-uppercase mb-2 d-block" style="letter-spacing: 0.5px;">Color Swatches</label>
                <div class="row g-2">
                    <?php foreach ($filter_colors as $f_cl): ?>
                        <div class="col-6">
                            <div class="form-check small p-2 border rounded bg-light d-flex align-items-center gap-2">
                                <input class="form-check-input ms-0 me-1" type="checkbox" name="colors[]" value="<?php echo $f_cl['id']; ?>" id="m_color_<?php echo $f_cl['id']; ?>" <?php echo in_array($f_cl['id'], $selected_colors) ? 'checked' : ''; ?>>
                                <span class="border rounded-circle d-inline-block flex-shrink-0" style="background-color: <?php echo e($f_cl['color_code']); ?>; width: 14px; height: 14px;"></span>
                                <label class="form-check-label text-dark text-truncate" for="m_color_<?php echo $f_cl['id']; ?>">
                                    <?php echo e($f_cl['color_name']); ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Fabrics -->
            <?php if (!empty($filter_fabrics)): ?>
            <div class="mb-4">
                <label class="form-label small fw-bold text-muted text-uppercase mb-2 d-block" style="letter-spacing: 0.5px;">Fabric Material</label>
                <div class="row g-2">
                    <?php foreach ($filter_fabrics as $fab): 
                        $fab_val = $fab['name'];
                        $is_checked = in_array($fab_val, $selected_fabrics) || in_array((string)$fab['id'], $selected_fabrics);
                    ?>
                        <div class="col-6">
                            <div class="form-check small p-2 border rounded bg-light d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="fabrics[]" value="<?php echo e($fab_val); ?>" id="m_fab_<?php echo $fab['id']; ?>" <?php echo $is_checked ? 'checked' : ''; ?>>
                                    <label class="form-check-label text-dark text-truncate" for="m_fab_<?php echo $fab['id']; ?>">
                                        <?php echo e($fab['name']); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <div class="offcanvas-footer border-top p-3 bg-white d-flex gap-2">
        <a href="products.php" class="btn btn-outline-secondary w-50 rounded-pill">Reset</a>
        <button type="submit" form="mobile-filter-drawer-form" class="btn btn-brand w-50 rounded-pill">Apply</button>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
