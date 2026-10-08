<?php
// admin/enquiries.php
// Contact us enquiries inbox

$page_title = 'Contact Enquiries Inbox';
$active_menu = 'enquiries';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
require_admin_permission('all');

$error = '';
$success = '';

// Handle Status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        $enquiry_id = (int)$_POST['enquiry_id'];
        $status = $_POST['status']; // pending or responded

        try {
            $stmt = $pdo->prepare("UPDATE enquiries SET status = ? WHERE id = ?");
            $stmt->execute([$status, $enquiry_id]);
            log_admin_action($current_admin_id, "Toggled enquiry status ID $enquiry_id to $status");
            set_flash_message('success', 'Enquiry responder status updated!');
            header('Location: enquiries.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to update: ' . $e->getMessage();
        }
    }
}

// Fetch enquiries
$enquiries = $pdo->query("SELECT * FROM enquiries ORDER BY id DESC")->fetchAll();
?>

<div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
    <h5 class="fw-bold mb-4">Customer Contact Inbox</h5>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Date / Time</th>
                    <th>Sender Profile</th>
                    <th>Subject</th>
                    <th>Message Details</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 120px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($enquiries)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No contact inquiries found. Inbox is clear!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($enquiries as $enq): ?>
                        <tr class="<?php echo $enq['status'] === 'pending' ? 'table-warning-subtle fw-semibold' : ''; ?>">
                            <td class="small"><?php echo date('d-M-Y H:i', strtotime($enq['created_at'])); ?></td>
                            <td>
                                <span class="d-block text-dark fw-bold"><?php echo e($enq['name']); ?></span>
                                <span class="d-block small text-muted"><?php echo e($enq['email']); ?></span>
                                <?php if($enq['phone']): ?><span class="d-block small text-muted"><?php echo e($enq['phone']); ?></span><?php endif; ?>
                            </td>
                            <td class="text-dark"><?php echo e($enq['subject']); ?></td>
                            <td>
                                <div style="max-width: 320px; max-height: 120px; overflow-y: auto;" class="small text-secondary">
                                    <?php echo nl2br(e($enq['message'])); ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo $enq['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-success'; ?>">
                                    <?php echo strtoupper($enq['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <form action="enquiries.php" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="enquiry_id" value="<?php echo $enq['id']; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <?php if ($enq['status'] === 'pending'): ?>
                                        <input type="hidden" name="status" value="responded">
                                        <button type="submit" class="btn btn-sm btn-success px-2 py-1">
                                            Mark Solved
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="status" value="pending">
                                        <button type="submit" class="btn btn-sm btn-outline-warning text-dark px-2 py-1">
                                            Mark Pending
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
