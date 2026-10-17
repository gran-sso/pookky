<?php
// orders.php
// Customer Order History

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Requires customer login
require_customer_login();

$meta_title = 'My Order History | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';

$customer_id = $_SESSION['customer_id'];
$customer = get_logged_in_customer();

try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC");
    $stmt->execute([$customer_id]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = [];
    set_flash_message('error', 'Failed to retrieve order history.');
}
?>

<div class="container py-5">
    <div class="row g-4">
        <!-- Dashboard Sidebar Nav -->
        <div class="col-12 col-md-3">
            <div class="card glass-card p-3 border-0 bg-white" style="border-radius: 12px;">
                <div class="text-center py-3 border-bottom mb-3">
                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white mx-auto mb-2 fw-bold" style="width: 60px; height: 60px; font-size:24px;">
                        <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                    </div>
                    <h6 class="fw-bold text-dark mb-0"><?php echo e($customer['name']); ?></h6>
                    <small class="text-muted"><?php echo e($customer['email']); ?></small>
                </div>
                <div class="list-group list-group-flush small">
                    <a href="dashboard.php" class="list-group-item list-group-item-action border-0 rounded p-2 mb-1"><i class="bi bi-person me-2"></i> Profile Dashboard</a>
                    <a href="addresses.php" class="list-group-item list-group-item-action border-0 rounded p-2 mb-1"><i class="bi bi-geo-alt me-2"></i> Address Book</a>
                    <a href="orders.php" class="list-group-item list-group-item-action border-0 active bg-brand-blush text-rose fw-bold rounded p-2 mb-1"><i class="bi bi-box me-2"></i> Order History</a>
                    <a href="wishlist.php" class="list-group-item list-group-item-action border-0 rounded p-2 mb-1"><i class="bi bi-heart me-2"></i> My Wishlist</a>
                    <a href="logout.php" class="list-group-item list-group-item-action border-0 text-danger rounded p-2"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
                </div>
            </div>
        </div>

        <!-- Main content block -->
        <div class="col-12 col-md-9">
            <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius:12px;">
                <h5 class="fw-bold mb-4"><i class="bi bi-box-seam me-2 text-primary"></i> Order History</h5>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Date Placed</th>
                                <th>Recipient</th>
                                <th>Amount</th>
                                <th>Order Status</th>
                                <th>Payment</th>
                                <th class="text-end" style="width: 120px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4 small">No orders recorded in your history. <a href="products.php" class="text-rose">Browse products</a></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $ord): ?>
                                    <tr>
                                        <td><span class="fw-bold text-dark"><?php echo e($ord['order_number']); ?></span></td>
                                        <td class="small text-muted"><?php echo date('d-M-Y', strtotime($ord['created_at'])); ?></td>
                                        <td><?php echo e($ord['receiver_name']); ?></td>
                                        <td class="fw-bold text-rose"><?php echo format_price($ord['grand_total']); ?></td>
                                        <td>
                                            <?php 
                                            $badge = 'bg-secondary';
                                            if($ord['order_status'] === 'pending') $badge = 'bg-warning text-dark';
                                            else if($ord['order_status'] === 'processing') $badge = 'bg-primary';
                                            else if($ord['order_status'] === 'shipped') $badge = 'bg-info text-dark';
                                            else if($ord['order_status'] === 'delivered') $badge = 'bg-success';
                                            else if($ord['order_status'] === 'cancelled') $badge = 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $badge; ?>" style="font-size:10px;"><?php echo strtoupper($ord['order_status']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $ord['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark'; ?>" style="font-size: 10px;">
                                                <?php echo strtoupper($ord['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="order-detail.php?id=<?php echo $ord['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-3" style="font-size:12px;">
                                                View
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
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
