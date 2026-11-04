<?php
// admin/index.php
// Administrator Main Dashboard Panel

$page_title = 'Dashboard';
$active_menu = 'dashboard';
require_once __DIR__ . '/includes/header.php';

// Fetch business KPIs
try {
    // 1. Total Sales (Delivered orders only)
    $sales_stmt = $pdo->query("SELECT SUM(grand_total) as total FROM orders WHERE order_status = 'delivered'");
    $sales_row = $sales_stmt->fetch();
    $total_sales = $sales_row['total'] ?? 0.00;

    // 2. Active Orders (Pending, Processing, Shipped)
    $orders_stmt = $pdo->query("SELECT COUNT(id) as count FROM orders WHERE order_status IN ('pending', 'processing', 'shipped')");
    $orders_row = $orders_stmt->fetch();
    $active_orders = $orders_row['count'] ?? 0;

    // 3. Registered Customers
    $cust_stmt = $pdo->query("SELECT COUNT(id) as count FROM customers WHERE status = 1");
    $cust_row = $cust_stmt->fetch();
    $total_customers = $cust_row['count'] ?? 0;

    // 4. Low stock variants (stock <= 5)
    $stock_stmt = $pdo->query("SELECT COUNT(id) as count FROM product_variants WHERE stock <= 5");
    $stock_row = $stock_stmt->fetch();
    $low_stock_count = $stock_row['count'] ?? 0;

    // 5. Recent 5 Orders
    $recent_orders_stmt = $pdo->query("
        SELECT o.*, c.name as customer_name 
        FROM orders o 
        JOIN customers c ON o.customer_id = c.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $recent_orders = $recent_orders_stmt->fetchAll();

    // 6. Recent Low Stock Products details
    $low_stock_details = $pdo->query("
        SELECT v.sku, v.stock, p.name as product_name, s.size_code, c.color_name 
        FROM product_variants v 
        JOIN products p ON v.product_id = p.id 
        JOIN sizes s ON v.size_id = s.id 
        JOIN colors c ON v.color_id = c.id 
        WHERE v.stock <= 5 
        ORDER BY v.stock ASC 
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    set_flash_message('error', 'Error loading dashboard metrics: ' . $e->getMessage());
}
?>

<!-- Metrics KPI Cards Grid -->
<div class="row g-4 mb-4">
    <!-- Sales Card -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card admin-card-stat bg-white p-3 border-0">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted small text-uppercase mb-2 font-weight-bold">Total Sales</h6>
                    <h3 class="fw-bold text-dark mb-0"><?php echo format_price($total_sales); ?></h3>
                </div>
                <div class="rounded-circle bg-success-subtle p-3 text-success">
                    <i class="bi bi-currency-rupee fs-3"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Active Orders Card -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card admin-card-stat bg-white p-3 border-0">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted small text-uppercase mb-2 font-weight-bold">Active Orders</h6>
                    <h3 class="fw-bold text-dark mb-0"><?php echo e($active_orders); ?></h3>
                </div>
                <div class="rounded-circle bg-primary-subtle p-3 text-primary">
                    <i class="bi bi-cart-check fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Customers Card -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card admin-card-stat bg-white p-3 border-0">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted small text-uppercase mb-2 font-weight-bold">Customers</h6>
                    <h3 class="fw-bold text-dark mb-0"><?php echo e($total_customers); ?></h3>
                </div>
                <div class="rounded-circle bg-info-subtle p-3 text-info">
                    <i class="bi bi-people fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock warning Card -->
    <div class="col-12 col-sm-6 col-xl-3">
        <a href="products.php?stock_filter=low_stock" class="text-decoration-none">
            <div class="card admin-card-stat bg-white p-3 border-0">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted small text-uppercase mb-2 font-weight-bold">Low Stock items</h6>
                        <h3 class="fw-bold text-dark mb-0"><?php echo e($low_stock_count); ?></h3>
                    </div>
                    <div class="rounded-circle bg-danger-subtle p-3 text-danger">
                        <i class="bi bi-exclamation-triangle fs-3"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Orders Table -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i> Recent Placed Orders</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Order No</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_orders)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No recent orders available.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $ord): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo e($ord['order_number']); ?></td>
                                    <td><?php echo e($ord['customer_name']); ?></td>
                                    <td><?php echo format_price($ord['grand_total']); ?></td>
                                    <td>
                                        <?php 
                                        $badge = 'bg-secondary';
                                        if($ord['order_status'] === 'pending') $badge = 'bg-warning';
                                        else if($ord['order_status'] === 'processing') $badge = 'bg-primary';
                                        else if($ord['order_status'] === 'shipped') $badge = 'bg-info';
                                        else if($ord['order_status'] === 'delivered') $badge = 'bg-success';
                                        else if($ord['order_status'] === 'cancelled') $badge = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($ord['order_status']); ?></span>
                                    </td>
                                    <td class="small"><?php echo date('d-M-Y H:i', strtotime($ord['created_at'])); ?></td>
                                    <td class="text-end">
                                        <a href="orders.php?view=<?php echo $ord['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Stock Ledger Warning list -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3"><i class="bi bi-exclamation-octagon me-2 text-danger"></i> Low Stock Alerts</h5>
            <div class="list-group list-group-flush">
                <?php if (empty($low_stock_details)): ?>
                    <div class="text-center text-muted py-4">All products are well stocked!</div>
                <?php else: ?>
                    <?php foreach ($low_stock_details as $item): ?>
                        <div class="list-group-item px-0 py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-dark text-truncate" style="max-width: 220px;"><?php echo e($item['product_name']); ?></div>
                                    <small class="text-muted">SKU: <?php echo e($item['sku']); ?> | Size: <?php echo e($item['size_code']); ?> | Color: <?php echo e($item['color_name']); ?></small>
                                </div>
                                <span class="badge bg-danger rounded-pill"><?php echo e($item['stock']); ?> left</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($low_stock_details)): ?>
                <div class="mt-3 text-center">
                    <a href="inventory.php" class="btn btn-sm btn-brand-outline w-100">Restock Inventory</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

