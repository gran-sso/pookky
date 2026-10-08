<?php
// admin/customers.php
// Registered Customer Accounts Management

$page_title = 'Customers Management';
$active_menu = 'customers';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
require_admin_permission('customers');

$error = '';
$success = '';

// Handle customer status toggles if post direct
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        $customer_id = (int)$_POST['customer_id'];
        $status = (int)$_POST['status'];

        try {
            $stmt = $pdo->prepare("UPDATE customers SET status = ? WHERE id = ?");
            $stmt->execute([$status, $customer_id]);
            log_admin_action($current_admin_id, "Toggled customer ID: $customer_id to status $status");
            set_flash_message('success', 'Customer status updated successfully!');
            header('Location: customers.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
}

// Fetch all customers along with order summaries
try {
    $stmt = $pdo->query("
        SELECT c.*, 
        (SELECT COUNT(id) FROM orders WHERE customer_id = c.id) as order_count,
        (SELECT SUM(grand_total) FROM orders WHERE customer_id = c.id AND order_status='delivered') as total_spend 
        FROM customers c 
        ORDER BY c.id DESC
    ");
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    $customers = [];
    $error = 'Failed to fetch customer list: ' . $e->getMessage();
}
?>

<div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
    <h5 class="fw-bold mb-4">Customer Accounts Database</h5>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Email Address</th>
                    <th>Phone</th>
                    <th>Orders Count</th>
                    <th>Total Spend</th>
                    <th>Status</th>
                    <th>Registered Date</th>
                    <th class="text-end" style="width: 100px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No customers registered yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $cust): ?>
                        <tr>
                            <td><span class="fw-bold text-dark"><?php echo e($cust['name']); ?></span></td>
                            <td>
                                <code><?php echo e($cust['email']); ?></code>
                                <?php if (!empty($cust['email_verified']) && $cust['email_verified'] == 1): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.7rem;"><i class="bi bi-patch-check-fill"></i> Verified</span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1" style="font-size: 0.7rem;"><i class="bi bi-clock-history"></i> Unverified</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($cust['phone'] ?: 'Not provided'); ?></td>
                            <td><span class="badge bg-secondary"><?php echo (int)$cust['order_count']; ?> orders</span></td>
                            <td class="fw-bold text-success"><?php echo format_price($cust['total_spend'] ?? 0.00); ?></td>
                            <td>
                                <div class="form-check form-switch p-0 m-0">
                                    <input class="form-check-input status-toggle ms-0" type="checkbox" role="switch" 
                                           data-table="customers" data-id="<?php echo $cust['id']; ?>" <?php echo $cust['status'] == 1 ? 'checked' : ''; ?>>
                                </div>
                            </td>
                            <td class="small text-muted"><?php echo date('d-M-Y H:i', strtotime($cust['created_at'])); ?></td>
                            <td class="text-end">
                                <form action="customers.php" method="POST" class="d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="customer_id" value="<?php echo $cust['id']; ?>">
                                    <input type="hidden" name="toggle_status" value="1">
                                    <?php if ($cust['status'] == 1): ?>
                                        <input type="hidden" name="status" value="0">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Suspend customer">
                                            Suspend
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="status" value="1">
                                        <button type="submit" class="btn btn-sm btn-outline-success py-1 px-2" title="Activate customer">
                                            Activate
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
