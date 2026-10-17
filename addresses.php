<?php
// addresses.php
// Customer Addresses Book Manager

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';

// Requires customer login
require_customer_login();

$customer_id = $_SESSION['customer_id'];
$error = '';
$success = '';
$return_to = $_GET['return_to'] ?? $_POST['return_to'] ?? '';
// Only allow safe relative redirect paths
if (!empty($return_to) && (strpos($return_to, 'http') === 0 || strpos($return_to, '//') === 0)) {
    $return_to = '';
}
$action = $_GET['action'] ?? '';

// Handle Add / Edit / Delete Address Post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed. Please refresh page.';
    } else {
        // 1. ADD / EDIT ADDRESS
        if (isset($_POST['save_address'])) {
            $address_id = (int)($_POST['address_id'] ?? 0);
            $address_type = trim($_POST['address_type'] ?? 'Home');
            $receiver_name = trim($_POST['receiver_name'] ?? '');
            $receiver_phone = trim($_POST['receiver_phone'] ?? '');
            $address_line1 = trim($_POST['address_line1'] ?? '');
            $address_line2 = trim($_POST['address_line2'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $is_default = isset($_POST['is_default']) ? 1 : 0;

            if (empty($receiver_name) || empty($receiver_phone) || empty($address_line1) || empty($city) || empty($state) || empty($pincode)) {
                $error = 'Please fill in all required fields.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // If set as default, reset other addresses
                    if ($is_default == 1) {
                        $reset = $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE customer_id = ?");
                        $reset->execute([$customer_id]);
                    }

                    if ($address_id > 0) {
                        // UPDATE
                        $stmt = $pdo->prepare("
                            UPDATE addresses SET 
                            address_type = ?, receiver_name = ?, receiver_phone = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, pincode = ?, is_default = ? 
                            WHERE id = ? AND customer_id = ?
                        ");
                        $stmt->execute([$address_type, $receiver_name, $receiver_phone, $address_line1, $address_line2, $city, $state, $pincode, $is_default, $address_id, $customer_id]);
                        set_flash_message('success', 'Address details updated successfully!');
                    } else {
                        // INSERT
                        $stmt = $pdo->prepare("
                            INSERT INTO addresses (customer_id, address_type, receiver_name, receiver_phone, address_line1, address_line2, city, state, pincode, is_default) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$customer_id, $address_type, $receiver_name, $receiver_phone, $address_line1, $address_line2, $city, $state, $pincode, $is_default]);
                        set_flash_message('success', 'New address added successfully!');
                    }

                    $pdo->commit();
                    $redirect_url = !empty($return_to) ? $return_to : 'addresses.php';
                    header('Location: ' . $redirect_url);
                    exit;

                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = 'Save failed: ' . $e->getMessage();
                }
            }
        }

        // 2. DELETE ADDRESS
        if (isset($_POST['delete_address'])) {
            $address_id = (int)$_POST['address_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM addresses WHERE id = ? AND customer_id = ?");
                $stmt->execute([$address_id, $customer_id]);
                set_flash_message('success', 'Address deleted successfully!');
                $redirect_url = !empty($return_to) ? $return_to : 'addresses.php';
                header('Location: ' . $redirect_url);
                exit;
            } catch (PDOException $e) {
                $error = 'Delete failed.';
            }
        }
    }
}

$meta_title = 'Manage Shipping Addresses | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';

// Fetch Customer profile
$customer = get_logged_in_customer();

// Fetch addresses
$addresses = $pdo->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC");
$addresses->execute([$customer_id]);
$addresses = $addresses->fetchAll();

// Edit form loader
$edit_addr = null;
if ($action === 'edit') {
    $edit_id = (int)($_GET['id'] ?? 0);
    $edit_stmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND customer_id = ? LIMIT 1");
    $edit_stmt->execute([$edit_id, $customer_id]);
    $edit_addr = $edit_stmt->fetch();
    if (!$edit_addr) {
        header('Location: addresses.php');
        exit;
    }
}
?>

<div class="container py-5">
    <div class="row g-4">
        <!-- Dashboard Sidebar nav -->
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
                    <a href="addresses.php" class="list-group-item list-group-item-action border-0 active bg-brand-blush text-rose fw-bold rounded p-2 mb-1"><i class="bi bi-geo-alt me-2"></i> Address Book</a>
                    <a href="orders.php" class="list-group-item list-group-item-action border-0 rounded p-2 mb-1"><i class="bi bi-box me-2"></i> Order History</a>
                    <a href="wishlist.php" class="list-group-item list-group-item-action border-0 rounded p-2 mb-1"><i class="bi bi-heart me-2"></i> My Wishlist</a>
                    <a href="logout.php" class="list-group-item list-group-item-action border-0 text-danger rounded p-2"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
                </div>
            </div>
        </div>

        <!-- Main content block -->
        <div class="col-12 col-md-9">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger p-2 small"><?php echo e($error); ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Add / Edit Address Form -->
                <div class="col-12 col-lg-5">
                    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius:12px;">
                        <h5 class="fw-bold mb-4"><?php echo $action === 'edit' ? 'Edit Address' : 'Add New Address'; ?></h5>
                        
                        <form action="addresses.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $edit_addr['id'] : ''; ?><?php echo !empty($return_to) ? '&return_to=' . urlencode($return_to) : ''; ?>" method="POST">
                            <?php echo csrf_field(); ?>
                            <?php if (!empty($return_to)): ?>
                                <input type="hidden" name="return_to" value="<?php echo e($return_to); ?>">
                            <?php endif; ?>
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="address_id" value="<?php echo $edit_addr['id']; ?>">
                            <?php endif; ?>

                            <div class="form-floating mb-3">
                                <select class="form-select" id="address_type" name="address_type" required>
                                    <option value="Home" <?php echo (isset($edit_addr['address_type']) && $edit_addr['address_type'] === 'Home') ? 'selected' : ''; ?>>Home (Default)</option>
                                    <option value="Work" <?php echo (isset($edit_addr['address_type']) && $edit_addr['address_type'] === 'Work') ? 'selected' : ''; ?>>Work (Office)</option>
                                    <option value="Other" <?php echo (isset($edit_addr['address_type']) && $edit_addr['address_type'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <label for="address_type">Address Type</label>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="receiver_name" name="receiver_name" value="<?php echo e($edit_addr['receiver_name'] ?? ''); ?>" required placeholder="Receiver's Name" autocomplete="name">
                                <label for="receiver_name">Receiver's Name <span class="text-danger">*</span></label>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="tel" class="form-control" id="receiver_phone" name="receiver_phone" value="<?php echo e($edit_addr['receiver_phone'] ?? ''); ?>" required placeholder="Receiver's Phone" autocomplete="tel">
                                <label for="receiver_phone">Receiver's Contact Phone <span class="text-danger">*</span></label>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="address_line1" name="address_line1" value="<?php echo e($edit_addr['address_line1'] ?? ''); ?>" required placeholder="Address Line 1" autocomplete="shipping address-line1">
                                <label for="address_line1">Address Line 1 (Flat, House No, Bldg) <span class="text-danger">*</span></label>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="address_line2" name="address_line2" value="<?php echo e($edit_addr['address_line2'] ?? ''); ?>" placeholder="Address Line 2 (Optional)" autocomplete="shipping address-line2">
                                <label for="address_line2">Address Line 2 (Street, Area - Optional)</label>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="city" name="city" value="<?php echo e($edit_addr['city'] ?? ''); ?>" required placeholder="City" autocomplete="shipping address-level2">
                                        <label for="city">City <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="state" name="state" value="<?php echo e($edit_addr['state'] ?? ''); ?>" required placeholder="State" autocomplete="shipping address-level1">
                                        <label for="state">State <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-12 mt-2">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="pincode" name="pincode" value="<?php echo e($edit_addr['pincode'] ?? ''); ?>" required placeholder="Pincode" autocomplete="shipping postal-code" inputmode="numeric" maxlength="6">
                                        <label for="pincode">Pincode <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_default" name="is_default" <?php echo (isset($edit_addr['is_default']) && $edit_addr['is_default'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="is_default">Set as default delivery address</label>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" name="save_address" class="btn btn-sm btn-brand flex-grow-1">Save Address</button>
                                <?php if($action === 'edit' || !empty($return_to)): ?>
                                    <a href="<?php echo !empty($return_to) ? e($return_to) : 'addresses.php'; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Addresses Listing book -->
                <div class="col-12 col-lg-7">
                    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius:12px;">
                        <h5 class="fw-bold mb-3">Address Directory</h5>
                        
                        <?php if (empty($addresses)): ?>
                            <div class="text-center text-muted py-4 small">Your address directory is empty. Add a delivery address on the left form.</div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($addresses as $addr): ?>
                                    <div class="col-12">
                                        <div class="border rounded p-3 bg-light-subtle <?php echo $addr['is_default'] == 1 ? 'border-rose bg-brand-blush' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge bg-secondary"><?php echo e($addr['address_type']); ?></span>
                                                <?php if($addr['is_default'] == 1): ?>
                                                    <span class="badge bg-success small">Default Address</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="fw-bold text-dark mb-1"><?php echo e($addr['receiver_name']); ?></div>
                                            <div class="small text-muted mb-2"><i class="bi bi-phone me-1"></i> <?php echo e($addr['receiver_phone']); ?></div>
                                            <p class="small text-secondary mb-3">
                                                <?php echo e($addr['address_line1']); ?><br>
                                                <?php if($addr['address_line2']) echo e($addr['address_line2']) . '<br>'; ?>
                                                <?php echo e($addr['city']); ?>, <?php echo e($addr['state']); ?> - <?php echo e($addr['pincode']); ?>
                                            </p>
                                            
                                            <div class="d-flex gap-2 justify-content-end border-top pt-2">
                                                <a href="addresses.php?action=edit&id=<?php echo $addr['id']; ?><?php echo !empty($return_to) ? '&return_to=' . urlencode($return_to) : ''; ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:11px;">Edit</a>
                                                
                                                <form action="addresses.php" method="POST" onsubmit="return confirm('Delete this address?');" style="display:inline;">
                                                    <?php echo csrf_field(); ?>
                                                    <?php if (!empty($return_to)): ?>
                                                        <input type="hidden" name="return_to" value="<?php echo e($return_to); ?>">
                                                    <?php endif; ?>
                                                    <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                                    <button type="submit" name="delete_address" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size:11px;">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
