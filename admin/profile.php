<?php
// admin/profile.php
// Administrator Profile & Security Management

$page_title = 'My Profile & Security';
$active_menu = 'profile';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

$error = '';
$success = '';

// Re-fetch latest admin profile data
$stmt = $pdo->prepare("
    SELECT a.*, r.name as role_name 
    FROM admins a 
    JOIN roles r ON a.role_id = r.id 
    WHERE a.id = ? LIMIT 1
");
$stmt->execute([$current_admin_id]);
$current_admin = $stmt->fetch();

if (!$current_admin) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        // ACTION 1: CHANGE PASSWORD
        if (isset($_POST['change_password'])) {
            $current_pass = trim($_POST['current_password'] ?? '');
            $new_pass = trim($_POST['new_password'] ?? '');
            $confirm_pass = trim($_POST['confirm_password'] ?? '');

            if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
                $error = 'Please fill in all password fields.';
            } elseif (!password_verify($current_pass, $current_admin['password'])) {
                $error = 'Your current password does not match our records.';
            } elseif (strlen($new_pass) < 8) {
                $error = 'New password must be at least 8 characters long.';
            } elseif ($new_pass !== $confirm_pass) {
                $error = 'New password and confirmation do not match.';
            } else {
                try {
                    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                    $up_stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
                    $up_stmt->execute([$hashed, $current_admin_id]);

                    log_admin_action($current_admin_id, 'Changed login password via Profile');
                    set_flash_message('success', 'Your password has been changed successfully!');
                    header('Location: profile.php');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Database error updating password: ' . $e->getMessage();
                }
            }
        }

        // ACTION 2: UPDATE PROFILE INFO (Name & Email)
        if (isset($_POST['update_profile'])) {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($name) || empty($email)) {
                $error = 'Name and email address are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please provide a valid email address.';
            } else {
                try {
                    // Check if email already taken by another admin
                    $chk = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND id != ? LIMIT 1");
                    $chk->execute([$email, $current_admin_id]);
                    if ($chk->fetch()) {
                        $error = 'That email address is already in use by another staff member.';
                    } else {
                        $up_prof = $pdo->prepare("UPDATE admins SET name = ?, email = ? WHERE id = ?");
                        $up_prof->execute([$name, $email, $current_admin_id]);

                        log_admin_action($current_admin_id, 'Updated profile information');
                        set_flash_message('success', 'Profile details updated successfully!');
                        header('Location: profile.php');
                        exit;
                    }
                } catch (PDOException $e) {
                    $error = 'Database error updating profile: ' . $e->getMessage();
                }
            }
        }
    }
}
?>

<div class="row g-4">
    <!-- Left Column: Account Summary Card -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm p-4 bg-white text-center" style="border-radius: 12px;">
            <div class="mx-auto mb-3 rounded-circle bg-danger-subtle text-danger fw-bold d-flex align-items-center justify-content-center shadow-sm" style="width: 80px; height: 80px; font-size: 32px;">
                <?php echo strtoupper(substr($current_admin['name'], 0, 1)); ?>
            </div>
            <h4 class="fw-bold mb-1 text-dark"><?php echo e($current_admin['name']); ?></h4>
            <div class="text-muted small mb-2"><code>@<?php echo e($current_admin['username']); ?></code></div>
            <div>
                <span class="badge bg-secondary-subtle text-secondary border px-3 py-1 rounded-pill">
                    <i class="bi bi-shield-check me-1 text-primary"></i> <?php echo e($current_admin['role_name']); ?>
                </span>
                <span class="badge bg-success-subtle text-success border px-3 py-1 rounded-pill ms-1">
                    <i class="bi bi-check-circle-fill me-1"></i> Active
                </span>
            </div>

            <hr class="my-4">

            <div class="text-start small">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Email</span>
                    <span class="fw-semibold text-dark text-truncate ms-2"><?php echo e($current_admin['email']); ?></span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Account ID</span>
                    <span class="fw-semibold text-dark">#<?php echo e($current_admin['id']); ?></span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Member Since</span>
                    <span class="fw-semibold text-dark"><?php echo !empty($current_admin['created_at']) ? date('d M, Y', strtotime($current_admin['created_at'])) : 'Active'; ?></span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Portal Access</span>
                    <span class="badge bg-light text-dark border">Full Administrative</span>
                </div>
            </div>

            <div class="mt-4 pt-2">
                <a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill btn-sm py-2">
                    <i class="bi bi-box-arrow-right me-1"></i> Sign Out of Session
                </a>
            </div>
        </div>

        <!-- Quick Security Tips -->
        <div class="card border-0 shadow-sm p-4 bg-white mt-4" style="border-radius: 12px; border-left: 4px solid var(--brand-accent) !important;">
            <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-shield-lock-fill text-rose me-2"></i> Password Guidelines</h6>
            <ul class="text-muted small mb-0 ps-3" style="line-height: 1.7;">
                <li>Use at least 8 characters with a mix of letters, numbers, and symbols.</li>
                <li>Avoid reusing passwords from personal email or social accounts.</li>
                <li>Keep your credentials confidential and sign out on shared computers.</li>
            </ul>
        </div>
    </div>

    <!-- Right Column: Settings and Change Password Forms -->
    <div class="col-12 col-lg-8">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 d-flex align-items-center gap-2 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <div class="small fw-semibold"><?php echo e($error); ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- 1. Change Password Card -->
        <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
            <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-4">
                <div>
                    <h5 class="fw-bold mb-1 text-dark">
                        <i class="bi bi-key text-rose me-2"></i> Change Password
                    </h5>
                    <p class="text-muted small mb-0">Update your administrator login password securely.</p>
                </div>
                <span class="badge bg-light text-dark border px-2 py-1 small">
                    <i class="bi bi-lock-fill text-muted me-1"></i> Bcrypt Encrypted
                </span>
            </div>

            <form action="profile.php" method="POST" autocomplete="off">
                <?php echo csrf_field(); ?>
                
                <div class="row g-3">
                    <!-- Current Password -->
                    <div class="col-12">
                        <label for="current_password" class="form-label small fw-bold text-dark">Current Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-shield-lock"></i></span>
                            <input type="password" class="form-control border-start-0" id="current_password" name="current_password" required placeholder="Enter your current password">
                            <button class="btn btn-outline-secondary px-3" type="button" onclick="togglePassVisibility('current_password', this)" aria-label="Toggle password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- New Password -->
                    <div class="col-12 col-md-6">
                        <label for="new_password" class="form-label small fw-bold text-dark">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-key-fill"></i></span>
                            <input type="password" class="form-control border-start-0" id="new_password" name="new_password" minlength="8" required placeholder="Minimum 8 characters">
                            <button class="btn btn-outline-secondary px-3" type="button" onclick="togglePassVisibility('new_password', this)" aria-label="Toggle password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text small text-muted">Must be at least 8 characters.</div>
                    </div>

                    <!-- Confirm New Password -->
                    <div class="col-12 col-md-6">
                        <label for="confirm_password" class="form-label small fw-bold text-dark">Confirm New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-check2-circle"></i></span>
                            <input type="password" class="form-control border-start-0" id="confirm_password" name="confirm_password" minlength="8" required placeholder="Re-enter new password">
                            <button class="btn btn-outline-secondary px-3" type="button" onclick="togglePassVisibility('confirm_password', this)" aria-label="Toggle password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4 pt-2">
                    <button type="submit" name="change_password" class="btn btn-brand rounded-pill px-4 py-2">
                        <i class="bi bi-check-lg me-1"></i> Update Password
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. Edit Profile Details Card -->
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-4">
                <div>
                    <h5 class="fw-bold mb-1 text-dark">
                        <i class="bi bi-person-lines-fill text-primary me-2"></i> Profile Information
                    </h5>
                    <p class="text-muted small mb-0">Update your account name and contact email address.</p>
                </div>
            </div>

            <form action="profile.php" method="POST">
                <?php echo csrf_field(); ?>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="username_display" class="form-label small fw-bold text-dark">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">@</span>
                            <input type="text" class="form-control bg-light" id="username_display" value="<?php echo e($current_admin['username']); ?>" readonly>
                        </div>
                        <div class="form-text small text-muted">Usernames cannot be modified directly.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="role_display" class="form-label small fw-bold text-dark">Assigned System Role</label>
                        <input type="text" class="form-control bg-light" id="role_display" value="<?php echo e($current_admin['role_name']); ?>" readonly>
                        <div class="form-text small text-muted">Role permissions are managed under Staff Permissions.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="name" class="form-label small fw-bold text-dark">Full Display Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo e($current_admin['name']); ?>" required placeholder="Your Full Name">
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="email" class="form-label small fw-bold text-dark">Contact Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo e($current_admin['email']); ?>" required placeholder="your.email@example.com">
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4 pt-2">
                    <button type="submit" name="update_profile" class="btn btn-dark rounded-pill px-4 py-2">
                        <i class="bi bi-save me-1"></i> Save Profile Details
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePassVisibility(inputId, btn) {
    const el = document.getElementById(inputId);
    if (!el) return;
    const icon = btn.querySelector('i');
    if (el.type === 'password') {
        el.type = 'text';
        if (icon) {
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        }
    } else {
        el.type = 'password';
        if (icon) {
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

