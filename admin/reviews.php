<?php
// admin/reviews.php
// Product Reviews & Ratings Moderation

$page_title = 'Product Reviews Moderation';
$active_menu = 'reviews';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
require_admin_permission('all');

$error = '';
$success = '';

// Handle Moderation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['moderate_review'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        $review_id = (int)$_POST['review_id'];
        $status = (int)$_POST['status']; // 1=Approved, 2=Rejected

        try {
            $stmt = $pdo->prepare("UPDATE reviews SET status = ? WHERE id = ?");
            $stmt->execute([$status, $review_id]);
            log_admin_action($current_admin_id, "Moderated review ID $review_id to status $status");
            set_flash_message('success', 'Review moderation status updated successfully!');
            header('Location: reviews.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to update: ' . $e->getMessage();
        }
    }
}

// Fetch all reviews
try {
    $reviews = $pdo->query("
        SELECT r.*, p.name as product_name, p.slug as product_slug, c.name as customer_name, c.email as customer_email,
        EXISTS (
            SELECT 1 FROM order_items oi 
            JOIN orders o ON oi.order_id = o.id 
            WHERE o.customer_id = r.customer_id 
              AND (oi.product_id = r.product_id OR oi.combo_product_id = r.product_id)
              AND o.order_status != 'cancelled'
        ) as is_verified_buyer
        FROM reviews r 
        JOIN products p ON r.product_id = p.id 
        JOIN customers c ON r.customer_id = c.id 
        ORDER BY r.id DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $reviews = [];
    $error = 'Error fetching reviews: ' . $e->getMessage();
}
?>

<div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
    <h5 class="fw-bold mb-4"><i class="bi bi-star text-warning me-2"></i> Product Ratings & Reviews Moderation</h5>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Customer</th>
                    <th>Rating</th>
                    <th>Comment</th>
                    <th>Moderation Status</th>
                    <th class="text-end" style="width: 180px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviews)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No product reviews submitted yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reviews as $rev): ?>
                        <tr class="<?php echo $rev['status'] == 0 ? 'table-warning-subtle fw-semibold' : ''; ?>">
                            <td class="small"><?php echo date('d-M-Y', strtotime($rev['created_at'])); ?></td>
                            <td>
                                <a href="../product-detail.php?slug=<?php echo e($rev['product_slug']); ?>" target="_blank" class="fw-bold text-decoration-none text-dark">
                                    <?php echo e($rev['product_name']); ?> <i class="bi bi-box-arrow-up-right text-muted" style="font-size: 11px;"></i>
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo e($rev['customer_name']); ?></div>
                                <div class="small text-muted" style="font-size: 11px;"><?php echo e($rev['customer_email']); ?></div>
                                <?php if ($rev['is_verified_buyer']): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle mt-1" style="font-size: 10px;">
                                        <i class="bi bi-patch-check-fill me-1"></i>Verified Purchase
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border mt-1" style="font-size: 10px;">
                                        <i class="bi bi-exclamation-circle me-1"></i>Unverified
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-warning">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="bi <?php echo $i <= $rev['rating'] ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </td>
                            <td>
                                <div class="small text-secondary" style="max-width: 250px; overflow-wrap: break-word;">
                                    <?php echo e($rev['comment']); ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($rev['status'] == 0): ?>
                                    <span class="badge bg-warning text-dark">Pending Approval</span>
                                <?php elseif ($rev['status'] == 1): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    <form action="reviews.php" method="POST">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                                        <input type="hidden" name="moderate_review" value="1">
                                        
                                        <?php if ($rev['status'] != 1): ?>
                                            <button type="submit" name="status" value="1" class="btn btn-sm btn-success px-2 py-1">Approve</button>
                                        <?php endif; ?>
                                        
                                        <?php if ($rev['status'] != 2): ?>
                                            <button type="submit" name="status" value="2" class="btn btn-sm btn-danger px-2 py-1">Reject</button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
