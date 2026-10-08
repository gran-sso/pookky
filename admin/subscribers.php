<?php
// admin/subscribers.php
// Newsletter subscriber database listing

$page_title = 'Newsletter Subscribers';
$active_menu = 'subscribers';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
require_admin_permission('all');

$error = '';
$success = '';

// Handle removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_subscriber'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        $subscriber_id = (int)$_POST['subscriber_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM newsletter_subscribers WHERE id = ?");
            $stmt->execute([$subscriber_id]);
            log_admin_action($current_admin_id, "Deleted newsletter subscriber ID: $subscriber_id");
            set_flash_message('success', 'Subscriber removed successfully!');
            header('Location: subscribers.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Removal failed: ' . $e->getMessage();
        }
    }
}

// Fetch list
$subscribers = $pdo->query("SELECT * FROM newsletter_subscribers ORDER BY id DESC")->fetchAll();
?>

<div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px; max-width: 800px;">
    <h5 class="fw-bold mb-4">Newsletter Registration Ledger</h5>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Subscriber Email</th>
                    <th>Status</th>
                    <th>Subscribed On</th>
                    <th class="text-end" style="width: 80px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subscribers)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No newsletter registrations found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subscribers as $sub): ?>
                        <tr>
                            <td><span class="fw-bold text-dark"><?php echo e($sub['email']); ?></span></td>
                            <td>
                                <div class="form-check form-switch p-0 m-0">
                                    <input class="form-check-input status-toggle ms-0" type="checkbox" role="switch" 
                                           data-table="newsletter_subscribers" data-id="<?php echo $sub['id']; ?>" <?php echo $sub['status'] == 1 ? 'checked' : ''; ?>>
                                </div>
                            </td>
                            <td class="small text-muted"><?php echo date('d-M-Y H:i', strtotime($sub['created_at'])); ?></td>
                            <td class="text-end">
                                <form action="subscribers.php" method="POST" onsubmit="return confirm('Remove subscriber from database?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="subscriber_id" value="<?php echo $sub['id']; ?>">
                                    <button type="submit" name="delete_subscriber" class="btn btn-sm btn-outline-danger p-1 px-2" title="Delete Email">
                                        <i class="bi bi-trash"></i>
                                    </button>
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
