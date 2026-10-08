<?php
// dashboard.php
// Customer Account Dashboard

$meta_title = 'My Account Dashboard | Pookky';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/security.php';

// Protect page
require_customer_login();

$customer_id = $_SESSION['customer_id'];
$error = '';
$success = '';

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($name)) {
            $error = 'Name cannot be empty.';
        } else {
            try {
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        $error = 'New password must be at least 6 characters.';
                    } else {
                        // Update with password
                        $hashed = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, password = ? WHERE id = ?");
                        $stmt->execute([$name, $phone, $hashed, $customer_id]);
                        $success = 'Profile and password updated successfully!';
                    }
                } else {
                    // Update profile details only
                    $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$name, $phone, $customer_id]);
                    $success = 'Profile details updated successfully!';
                }
                
                if (empty($error)) {
                    $_SESSION['customer_name'] = $name;
                }
            } catch (PDOException $e) {
                $error = 'Failed to update profile details.';
            }
        }
    }
}

// Fetch user profile and orders
$customer = get_logged_in_customer();

try {
    $ord_stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 5");
    $ord_stmt->execute([$customer_id]);
    $recent_orders = $ord_stmt->fetchAll();
} catch (PDOException $e) {
    $recent_orders = [];
}
?>

<div class="container py-5">
    <div class="row g-4">
        <!-- 1. Left Nav Sidebar wrapper -->
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
                    <a href="dashboard.php" class="list-group-item list-group-item-action border-0 active bg-brand-blush text-rose fw-bold rounded p-2 mb-1"><i class="bi bi-person me-2"></i> Profile Dashboard</a>
                    <a href="addresses.php" class="list-group-item list-group-item-action border-0 rounded p-2 mb-1"><i class="bi bi-geo-alt me-2"></i> Shipping Addresses</a>
                    <a href="orders.php" class="list-group-item list-group-item-action border-0 rounded p-2 mb-1"><i class="bi bi-box me-2"></i> Order History</a>
                    <a href="wishlist.php" class="list-group-item list-group-item-action border-0 rounded p-2 mb-1"><i class="bi bi-heart me-2"></i> My Wishlist</a>
                    <a href="logout.php" class="list-group-item list-group-item-action border-0 text-danger rounded p-2"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
                </div>
            </div>
        </div>

        <!-- 2. Main content block -->
        <div class="col-12 col-md-9">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger p-2 small"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if(!empty($success)): ?>
                <div class="alert alert-success p-2 small"><?php echo e($success); ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Profile Edit Form -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius:12px;">
                        <h5 class="fw-bold mb-4">Edit Profile details</h5>
                        <form action="dashboard.php" method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label for="name" class="form-label small text-muted">Full Name</label>
                                <input type="text" class="form-control form-control-sm" id="name" name="name" value="<?php echo e($customer['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label small text-muted">Email Address (Cannot change)</label>
                                <input type="email" class="form-control form-control-sm" id="email" value="<?php echo e($customer['email']); ?>" readonly disabled>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label small text-muted">Phone Number</label>
                                <input type="text" class="form-control form-control-sm" id="phone" name="phone" value="<?php echo e($customer['phone']); ?>">
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label small text-muted">Change Password (leave empty to keep current)</label>
                                <input type="password" class="form-control form-control-sm" id="password" name="password" placeholder="••••••••">
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-sm btn-brand w-100">Save Profile Settings</button>
                        </form>
                    </div>
                </div>

                <!-- Recent Orders list -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius:12px;">
                        <h5 class="fw-bold mb-3">Recent Orders</h5>
                        
                        <div class="list-group list-group-flush">
                            <?php if (empty($recent_orders)): ?>
                                <div class="text-center text-muted py-4 small">No orders placed yet. <a href="products.php" class="text-rose">Start shopping</a></div>
                            <?php else: ?>
                                <?php foreach ($recent_orders as $ord): ?>
                                    <div class="list-group-item px-0 py-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold text-dark small"><?php echo e($ord['order_number']); ?></span>
                                            <span class="small fw-bold text-rose"><?php echo format_price($ord['grand_total']); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center text-muted" style="font-size:12px;">
                                            <span>Date: <?php echo date('d-M-Y', strtotime($ord['created_at'])); ?></span>
                                            <?php 
                                            $badge = 'bg-secondary';
                                            if($ord['order_status'] === 'pending') $badge = 'bg-warning text-dark';
                                            else if($ord['order_status'] === 'processing') $badge = 'bg-primary';
                                            else if($ord['order_status'] === 'shipped') $badge = 'bg-info text-dark';
                                            else if($ord['order_status'] === 'delivered') $badge = 'bg-success';
                                            else if($ord['order_status'] === 'cancelled') $badge = 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $badge; ?>" style="font-size:10px;"><?php echo strtoupper($ord['order_status']); ?></span>
                                        </div>
                                        <div class="mt-2 text-end">
                                            <a href="order-detail.php?id=<?php echo $ord['id']; ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 11px;">Track Order</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
