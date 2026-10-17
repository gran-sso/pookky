<?php
// admin/faqs.php
// Frequently Asked Questions Management (CRUD)

$page_title = 'FAQs Management';
$active_menu = 'faqs';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
require_admin_permission('all');

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        // ADD FAQ
        if (isset($_POST['add_faq'])) {
            $question = trim($_POST['question'] ?? '');
            $answer = trim($_POST['answer'] ?? '');
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = isset($_POST['status']) ? 1 : 0;

            if (empty($question) || empty($answer)) {
                $error = 'Question and Answer are required.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO faqs (question, answer, sort_order, status) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$question, $answer, $sort_order, $status]);
                    log_admin_action($current_admin_id, "Added FAQ: $question");
                    set_flash_message('success', 'FAQ question added successfully!');
                    header('Location: faqs.php');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Failed to create FAQ: ' . $e->getMessage();
                }
            }
        }

        // EDIT FAQ
        if (isset($_POST['edit_faq'])) {
            $id = (int)$_POST['faq_id'];
            $question = trim($_POST['question'] ?? '');
            $answer = trim($_POST['answer'] ?? '');
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = isset($_POST['status']) ? 1 : 0;

            if (empty($question) || empty($answer)) {
                $error = 'Question and Answer are required.';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE faqs SET question = ?, answer = ?, sort_order = ?, status = ? WHERE id = ?");
                    $stmt->execute([$question, $answer, $sort_order, $status, $id]);
                    log_admin_action($current_admin_id, "Updated FAQ ID: $id");
                    set_flash_message('success', 'FAQ updated successfully!');
                    header('Location: faqs.php');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Failed to update FAQ: ' . $e->getMessage();
                }
            }
        }

        // DELETE FAQ
        if (isset($_POST['delete_faq'])) {
            $id = (int)$_POST['faq_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM faqs WHERE id = ?");
                $stmt->execute([$id]);
                log_admin_action($current_admin_id, "Deleted FAQ ID: $id");
                set_flash_message('success', 'FAQ deleted successfully.');
                header('Location: faqs.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Failed to delete FAQ.';
            }
        }
    }
}

// Fetch single FAQ if editing
$faq = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM faqs WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$_GET['id']]);
    $faq = $stmt->fetch();
    if (!$faq) {
        header('Location: faqs.php');
        exit;
    }
}
?>

<div class="row g-4 justify-content-center" style="max-width: 1000px; margin: 0 auto;">
    <div class="col-12">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-2">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-question-circle me-2 text-brand"></i> FAQs Management</h4>
                <p class="text-muted small mb-0">Add, reorder, and manage customer help questions displayed on the storefront FAQ page.</p>
            </div>
            <?php if ($action === 'list'): ?>
                <a href="faqs.php?action=add" class="btn btn-brand btn-sm rounded-pill px-3">
                    <i class="bi bi-plus-lg me-1"></i> Add New FAQ
                </a>
            <?php else: ?>
                <a href="faqs.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Back to FAQ List
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="col-12">
            <div class="alert alert-danger shadow-sm"><?php echo e($error); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                <h5 class="fw-bold mb-3 text-dark pb-2 border-bottom">
                    <?php echo $action === 'add' ? '<i class="bi bi-plus-circle text-primary me-2"></i> Add New Question' : '<i class="bi bi-pencil-square text-primary me-2"></i> Edit FAQ Question'; ?>
                </h5>
                <form action="faqs.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $faq['id'] : ''; ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="faq_id" value="<?php echo $faq['id']; ?>">
                    <?php endif; ?>

                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="question" name="question" value="<?php echo e($faq['question'] ?? ''); ?>" required placeholder="FAQ Question *">
                        <label for="question">FAQ Question <span class="text-danger">*</span></label>
                    </div>

                    <div class="form-floating mb-2">
                        <textarea class="form-control" id="answer" name="answer" required placeholder="FAQ Answer / Response (HTML allowed) *" style="height: 140px;"><?php echo e($faq['answer'] ?? ''); ?></textarea>
                        <label for="answer">FAQ Answer / Response (HTML allowed) <span class="text-danger">*</span></label>
                    </div>
                    <div class="form-text small text-muted mb-3">
                        <strong>Dynamic Tokens Available:</strong> <code>{SHIPPING_FLAT_RATE}</code>, <code>{FREE_SHIPPING_THRESHOLD}</code>, <code>{RETURN_WINDOW_DAYS}</code>, <code>{EST_DELIVERY_DAYS}</code>, <code>{SITE_NAME}</code>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?php echo e($faq['sort_order'] ?? '0'); ?>" placeholder="Display Sort Order">
                                <label for="sort_order">Display Sort Order</label>
                            </div>
                            <div class="form-text small text-muted">Lower numbers appear first.</div>
                        </div>
                        <div class="col-12 col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch pt-2">
                                <input class="form-check-input" type="checkbox" id="status" name="status" <?php echo (!isset($faq['status']) || $faq['status'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label small fw-bold ms-1" for="status">Published & Active on FAQ Page</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" name="<?php echo $action === 'add' ? 'add_faq' : 'edit_faq'; ?>" class="btn btn-brand rounded-pill px-4">Save FAQ</button>
                        <a href="faqs.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- FAQ Listing Table -->
        <div class="col-12">
            <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Question</th>
                                <th>Answer Preview</th>
                                <th class="text-center" style="width: 80px;">Order</th>
                                <th class="text-center" style="width: 100px;">Status</th>
                                <th class="text-end" style="width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $all_faqs = $pdo->query("SELECT * FROM faqs ORDER BY sort_order ASC, id ASC")->fetchAll();
                            if (empty($all_faqs)):
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No FAQs added yet. Click "Add New FAQ" to create one.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_faqs as $item): ?>
                                    <tr>
                                        <td class="text-muted small"><?php echo $item['id']; ?></td>
                                        <td class="fw-semibold text-dark"><?php echo e($item['question']); ?></td>
                                        <td class="text-muted small" style="max-width: 320px;">
                                            <div class="text-truncate"><?php echo strip_tags($item['answer']); ?></div>
                                        </td>
                                        <td class="text-center small"><?php echo (int)$item['sort_order']; ?></td>
                                        <td class="text-center">
                                            <?php if ($item['status'] == 1): ?>
                                                <span class="badge bg-success-subtle text-success small">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary small">Hidden</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="faqs.php?action=edit&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="faqs.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this FAQ?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="faq_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" name="delete_faq" class="btn btn-sm btn-outline-danger" title="Delete">
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
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
