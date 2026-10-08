<?php
// admin/reports.php
// Sales, Orders, and Stock reports

$page_title = 'Sales & Stock Reports';
$active_menu = 'reports';
require_once __DIR__ . '/includes/header.php';

// Check permissions
require_admin_permission('all');

try {
    // 1. Monthly sales summary
    $monthly_sales = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%M-%Y') as month_year, 
        COUNT(id) as total_orders, 
        SUM(subtotal) as total_subtotal, 
        SUM(discount) as total_discount, 
        SUM(shipping_charge) as total_shipping, 
        SUM(tax_amount) as total_tax, 
        SUM(grand_total) as total_revenue 
        FROM orders 
        WHERE order_status = 'delivered' 
        GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%M-%Y') 
        ORDER BY DATE_FORMAT(created_at, '%Y-%m') DESC
    ")->fetchAll();

    // 2. Revenue by Category
    $category_sales = $pdo->query("
        SELECT c.name as category_name, 
        SUM(oi.quantity) as items_sold, 
        SUM(oi.total) as total_sales 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        JOIN categories c ON p.category_id = c.id 
        JOIN orders o ON oi.order_id = o.id 
        WHERE o.order_status = 'delivered' 
        GROUP BY c.id 
        ORDER BY total_sales DESC
    ")->fetchAll();

    // 3. Top 10 Best Selling Products
    $best_sellers = $pdo->query("
        SELECT oi.product_name, oi.sku, 
        SUM(oi.quantity) as total_qty, 
        SUM(oi.total) as total_revenue 
        FROM order_items oi 
        JOIN orders o ON oi.order_id = o.id 
        WHERE o.order_status = 'delivered' 
        GROUP BY oi.variant_id 
        ORDER BY total_qty DESC 
        LIMIT 10
    ")->fetchAll();

    // 4. Low stock variants overview
    $low_stock = $pdo->query("
        SELECT v.sku, v.stock, p.name as product_name, s.size_code, c.color_name 
        FROM product_variants v 
        JOIN products p ON v.product_id = p.id 
        JOIN sizes s ON v.size_id = s.id 
        JOIN colors c ON v.color_id = c.id 
        WHERE v.stock <= 5 
        ORDER BY v.stock ASC
    ")->fetchAll();

} catch (PDOException $e) {
    set_flash_message('error', 'Error loading analytics reports: ' . $e->getMessage());
}
?>

<!-- Report Navigation Tabs -->
<div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
    <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pills-sales-tab" data-bs-toggle="pill" data-bs-target="#pills-sales" type="button" role="tab" aria-controls="pills-sales" aria-selected="true">
                <i class="bi bi-calendar-check me-1"></i> Monthly Sales
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pills-categories-tab" data-bs-toggle="pill" data-bs-target="#pills-categories" type="button" role="tab" aria-controls="pills-categories" aria-selected="false">
                <i class="bi bi-tags-fill me-1"></i> Category Breakdown
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pills-products-tab" data-bs-toggle="pill" data-bs-target="#pills-products" type="button" role="tab" aria-controls="pills-products" aria-selected="false">
                <i class="bi bi-box-seam me-1"></i> Top Products
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pills-stock-tab" data-bs-toggle="pill" data-bs-target="#pills-stock" type="button" role="tab" aria-controls="pills-stock" aria-selected="false">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> Stock Warns
            </button>
        </li>
    </ul>
    
    <div class="tab-content" id="pills-tabContent">
        <!-- Monthly Sales tab -->
        <div class="tab-pane fade show active" id="pills-sales" role="tabpanel" aria-labelledby="pills-sales-tab">
            <h6 class="fw-bold mb-3">Revenue Ledger (Delivered Orders Only)</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Month-Year</th>
                            <th>Delivered Orders</th>
                            <th>Subtotal</th>
                            <th>Discounts</th>
                            <th>Shipping</th>
                            <th>GST Tax Collected</th>
                            <th>Grand Net Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($monthly_sales)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No delivered orders registered yet in billing cycles.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($monthly_sales as $row): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo e($row['month_year']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo $row['total_orders']; ?> orders</span></td>
                                    <td><?php echo format_price($row['total_subtotal']); ?></td>
                                    <td class="text-danger">-<?php echo format_price($row['total_discount']); ?></td>
                                    <td><?php echo format_price($row['total_shipping']); ?></td>
                                    <td><?php echo format_price($row['total_tax']); ?></td>
                                    <td class="fw-bold text-success fs-6"><?php echo format_price($row['total_revenue']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Category Breakdown Tab -->
        <div class="tab-pane fade" id="pills-categories" role="tabpanel" aria-labelledby="pills-categories-tab">
            <h6 class="fw-bold mb-3">Revenues Grouped by Product Category</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Quantity Sold</th>
                            <th>Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($category_sales)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No categories data tracked yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($category_sales as $row): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo e($row['category_name']); ?></td>
                                    <td><?php echo $row['items_sold']; ?> pieces</td>
                                    <td class="fw-bold text-success"><?php echo format_price($row['total_sales']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Products Tab -->
        <div class="tab-pane fade" id="pills-products" role="tabpanel" aria-labelledby="pills-products-tab">
            <h6 class="fw-bold mb-3">Top 10 Best Sellers Matrix</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th>Units Sold</th>
                            <th>Revenue Contribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($best_sellers)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No purchase logs available.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($best_sellers as $row): ?>
                                <tr>
                                    <td><span class="fw-bold text-dark"><?php echo e($row['product_name']); ?></span></td>
                                    <td><code><?php echo e($row['sku']); ?></code></td>
                                    <td><?php echo $row['total_qty']; ?> items</td>
                                    <td class="fw-bold text-success"><?php echo format_price($row['total_revenue']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Stock Warns Tab -->
        <div class="tab-pane fade" id="pills-stock" role="tabpanel" aria-labelledby="pills-stock-tab">
            <h6 class="fw-bold mb-3">Active Low-Stock Alerts (Stock &le; 5)</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th>Attributes</th>
                            <th>Remaining Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($low_stock)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-success py-4">All products are well stocked above levels!</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($low_stock as $row): ?>
                                <tr>
                                    <td><code><?php echo e($row['sku']); ?></code></td>
                                    <td><span class="fw-bold text-dark"><?php echo e($row['product_name']); ?></span></td>
                                    <td>Size: <?php echo e($row['size_code']); ?> | Color: <?php echo e($row['color_name']); ?></td>
                                    <td><span class="badge bg-danger"><?php echo $row['stock']; ?> units left</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
