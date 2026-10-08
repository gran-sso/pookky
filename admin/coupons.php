<?php
// admin/coupons.php
// Coupon and Offers Management CRUD

$page_title = 'Coupon Management';
$active_menu = 'coupons';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
require_admin_permission('all');

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle additions & updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        // ADD COUPON
        if (isset($_POST['add_coupon'])) {
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $discount_type = $_POST['discount_type'];
            $discount_value = (float)$_POST['discount_value'];
            $min_order_value = (float)($_POST['min_order_value'] ?? 0.00);
            $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
            $max_uses = (int)($_POST['max_uses'] ?? 0);
            $status = isset($_POST['status']) ? 1 : 0;

            if (empty($code) || $discount_value <= 0) {
                $error = 'Please enter a valid coupon code and discount value.';
            } else {
                try {
                    $check = $pdo->prepare("SELECT id FROM coupons WHERE code = ? LIMIT 1");
                    $check->execute([$code]);
                    if ($check->fetch()) {
                        $error = 'Coupon code already exists.';
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO coupons (code, discount_type, discount_value, min_order_value, expiry_date, max_uses, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$code, $discount_type, $discount_value, $min_order_value, $expiry_date, $max_uses, $status]);
                        log_admin_action($current_admin_id, "Created coupon: $code");
                        set_flash_message('success', 'Coupon created successfully!');
                        header('Location: coupons.php');
                        exit;
                    }
                } catch (PDOException $e) {
                    $error = 'Insert failed: ' . $e->getMessage();
                }
            }
        }

        // EDIT COUPON
        if (isset($_POST['edit_coupon'])) {
            $id = (int)$_POST['coupon_id'];
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $discount_type = $_POST['discount_type'];
            $discount_value = (float)$_POST['discount_value'];
            $min_order_value = (float)($_POST['min_order_value'] ?? 0.00);
            $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
            $max_uses = (int)($_POST['max_uses'] ?? 0);
            $status = isset($_POST['status']) ? 1 : 0;

            if (empty($code) || $discount_value <= 0) {
                $error = 'Please fill in all fields correctly.';
            } else {
                try {
                    $check = $pdo->prepare("SELECT id FROM coupons WHERE code = ? AND id != ? LIMIT 1");
                    $check->execute([$code, $id]);
                    if ($check->fetch()) {
                        $error = 'Coupon code already exists.';
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE coupons SET 
                            code = ?, discount_type = ?, discount_value = ?, min_order_value = ?, expiry_date = ?, max_uses = ?, status = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$code, $discount_type, $discount_value, $min_order_value, $expiry_date, $max_uses, $status, $id]);
                        log_admin_action($current_admin_id, "Updated coupon: $code");
                        set_flash_message('success', 'Coupon updated successfully!');
                        header('Location: coupons.php');
                        exit;
                    }
                } catch (PDOException $e) {
                    $error = 'Update failed: ' . $e->getMessage();
                }
            }
        }

        // DELETE COUPON
        if (isset($_POST['delete_coupon'])) {
            $id = (int)$_POST['coupon_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
                $stmt->execute([$id]);
                log_admin_action($current_admin_id, "Deleted coupon id: $id");
                set_flash_message('success', 'Coupon deleted successfully!');
                header('Location: coupons.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Failed to delete coupon.';
            }
        }
    }
}

// Render views based on action
if ($action === 'add' || $action === 'edit'):
    $coupon = null;
    if ($action === 'edit') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $coupon = $stmt->fetch();
        if (!$coupon) {
            set_flash_message('error', 'Coupon not found.');
            header('Location: coupons.php');
            exit;
        }
    }
?>

    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
        <h5 class="fw-bold mb-4"><?php echo $action === 'add' ? 'Create New Coupon' : 'Edit Coupon: ' . e($coupon['code']); ?></h5>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form action="coupons.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $coupon['id'] : ''; ?>" method="POST">
            <?php echo csrf_field(); ?>
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <label for="code" class="form-label fw-bold small">Promo Code (All Caps, e.g. FESTIVE20)</label>
                    <input type="text" class="form-control text-uppercase" id="code" name="code" value="<?php echo e($coupon['code'] ?? ''); ?>" required placeholder="e.g. POOKKY100">
                </div>
                <div class="col-12 col-md-3">
                    <label for="discount_type" class="form-label fw-bold small">Discount Model</label>
                    <select class="form-select" id="discount_type" name="discount_type" required>
                        <option value="fixed" <?php echo (isset($coupon['discount_type']) && $coupon['discount_type'] === 'fixed') ? 'selected' : ''; ?>>Fixed Amount (INR)</option>
                        <option value="percentage" <?php echo (isset($coupon['discount_type']) && $coupon['discount_type'] === 'percentage') ? 'selected' : ''; ?>>Percentage (%)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label for="discount_value" class="form-label fw-bold small">Discount Value</label>
                    <input type="number" step="0.01" class="form-control" id="discount_value" name="discount_value" value="<?php echo e($coupon['discount_value'] ?? ''); ?>" required placeholder="e.g. 100 or 15">
                </div>

                <div class="col-12 col-md-4">
                    <label for="min_order_value" class="form-label fw-bold small">Min Order Cart Subtotal</label>
                    <input type="number" step="0.01" class="form-control" id="min_order_value" name="min_order_value" value="<?php echo e($coupon['min_order_value'] ?? '0.00'); ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label for="expiry_date" class="form-label fw-bold small">Expiry Date</label>
                    <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?php echo e($coupon['expiry_date'] ?? ''); ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label for="max_uses" class="form-label fw-bold small">Max Usage Cap (0 for unlimited)</label>
                    <input type="number" class="form-control" id="max_uses" name="max_uses" value="<?php echo e($coupon['max_uses'] ?? '0'); ?>">
                </div>

                <div class="col-12">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="status" name="status" <?php echo (!isset($coupon['status']) || $coupon['status'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label small" for="status">Active & Apply-ready</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" name="<?php echo $action === 'add' ? 'add_coupon' : 'edit_coupon'; ?>" class="btn btn-brand">Save Promo Coupon</button>
                <a href="coupons.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
            </div>
        </form>
    </div>

<?php else:
    // List Coupons
    try {
        $coupons_list = $pdo->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll();
    } catch (PDOException $e) {
        $coupons_list = [];
        $error = 'Error listing coupons: ' . $e->getMessage();
    }
?>

    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0">Coupons & Offer Codes</h5>
            <a href="coupons.php?action=add" class="btn btn-brand">
                <i class="bi bi-plus-circle me-1"></i> Add Coupon
            </a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Value</th>
                        <th>Min Order Required</th>
                        <th>Usage Count</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th class="text-end" style="width: 140px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($coupons_list)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No coupons configured yet. Click "Add Coupon" to create promotional offers.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($coupons_list as $cp): ?>
                            <tr>
                                <td><span class="badge bg-secondary fs-6 text-uppercase"><?php echo e($cp['code']); ?></span></td>
                                <td><?php echo $cp['discount_type'] === 'fixed' ? 'Fixed (INR)' : 'Percentage (%)'; ?></td>
                                <td class="fw-bold"><?php echo $cp['discount_type'] === 'fixed' ? format_price($cp['discount_value']) : e($cp['discount_value']) . '%'; ?></td>
                                <td><?php echo format_price($cp['min_order_value']); ?></td>
                                <td>
                                    <?php echo $cp['used_count']; ?> / 
                                    <?php echo $cp['max_uses'] > 0 ? $cp['max_uses'] : '<span class="text-muted small">∞</span>'; ?>
                                </td>
                                <td class="small">
                                    <?php if ($cp['expiry_date']): ?>
                                        <?php $expired = strtotime($cp['expiry_date']) < time(); ?>
                                        <span class="<?php echo $expired ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                            <?php echo date('d-M-Y', strtotime($cp['expiry_date'])); ?>
                                            <?php if($expired) echo ' (Expired)'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">No Expiry</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="form-check form-switch p-0 m-0">
                                        <input class="form-check-input status-toggle ms-0" type="checkbox" role="switch" 
                                               data-table="coupons" data-id="<?php echo $cp['id']; ?>" <?php echo $cp['status'] == 1 ? 'checked' : ''; ?>>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="coupons.php?action=edit&id=<?php echo $cp['id']; ?>" class="btn btn-sm btn-outline-secondary p-1 px-2" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form action="coupons.php" method="POST" onsubmit="return confirm('Delete this coupon?');" style="display:inline;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="coupon_id" value="<?php echo $cp['id']; ?>">
                                            <button type="submit" name="delete_coupon" class="btn btn-sm btn-outline-danger p-1 px-2" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
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

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
