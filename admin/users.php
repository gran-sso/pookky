<?php
// admin/users.php
// Administrator Users & Staff Roles Permissions

$page_title = 'Staff & Role Management';
$active_menu = 'users';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
require_admin_permission('all');

$error = '';
$success = '';

// Handle additions / status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        // CREATE NEW ADMIN USER
        if (isset($_POST['add_admin'])) {
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $role_id = (int)$_POST['role_id'];
            $status = isset($_POST['status']) ? 1 : 0;

            if (empty($name) || empty($username) || empty($email) || empty($password) || empty($role_id)) {
                $error = 'Please fill in all required fields.';
            } else {
                try {
                    // Check duplicate username or email
                    $check = $pdo->prepare("SELECT id FROM admins WHERE username = ? OR email = ? LIMIT 1");
                    $check->execute([$username, $email]);
                    if ($check->fetch()) {
                        $error = 'Username or Email is already registered.';
                    } else {
                        // Hash password
                        $hashed = password_hash($password, PASSWORD_BCRYPT);
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO admins (role_id, username, email, password, name, status) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$role_id, $username, $email, $hashed, $name, $status]);
                        log_admin_action($current_admin_id, "Created staff user: $username");
                        set_flash_message('success', 'Staff user created successfully!');
                        header('Location: users.php');
                        exit;
                    }
                } catch (PDOException $e) {
                    $error = 'Insertion failed: ' . $e->getMessage();
                }
            }
        }

        // TOGGLE SUSPEND / ACTIVATE ADMIN
        if (isset($_POST['toggle_admin_status'])) {
            $id = (int)$_POST['admin_id'];
            $status = (int)$_POST['status'];

            // Prevent self suspension
            if ($id === $current_admin_id) {
                $error = 'Cannot change your own active status.';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE admins SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $id]);
                    log_admin_action($current_admin_id, "Toggled status of admin ID $id to $status");
                    set_flash_message('success', 'Staff account status updated!');
                    header('Location: users.php');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Status update failed: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch admin users with role details
$admins = $pdo->query("
    SELECT a.*, r.name as role_name 
    FROM admins a 
    JOIN roles r ON a.role_id = r.id 
    ORDER BY a.id ASC
")->fetchAll();

// Fetch roles for configuration
$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
?>

<div class="row g-4">
    <!-- Form to Create Staff Accounts -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3">Create Staff Account</h5>
            
            <?php if (!empty($error) && isset($_POST['add_admin'])): ?>
                <div class="alert alert-danger p-2 small"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form action="users.php" method="POST">
                <?php echo csrf_field(); ?>
                
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="name" name="name" required placeholder="Full Name *">
                    <label for="name">Full Name <span class="text-danger">*</span></label>
                </div>

                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" required placeholder="Username *">
                    <label for="username">Username <span class="text-danger">*</span></label>
                </div>

                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email" name="email" required placeholder="Email Address *">
                    <label for="email">Email Address <span class="text-danger">*</span></label>
                </div>

                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password" name="password" required placeholder="Secret Password *">
                    <label for="password">Secret Password <span class="text-danger">*</span></label>
                </div>

                <div class="form-floating mb-3">
                    <select class="form-select" id="role_id" name="role_id" required>
                        <option value="">-- Select Role --</option>
                        <?php foreach ($roles as $rl): ?>
                            <option value="<?php echo $rl['id']; ?>"><?php echo e($rl['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="role_id">System Role <span class="text-danger">*</span></label>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="status" name="status" checked>
                        <label class="form-check-label small fw-semibold" for="status">Active Profile</label>
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" name="add_admin" class="btn btn-brand rounded-pill">Create Administrator</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Staff Listings Grid -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3">System Staff Administrators</h5>
            
            <?php if (!empty($error) && isset($_POST['toggle_admin_status'])): ?>
                <div class="alert alert-danger p-2 small"><?php echo e($error); ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Name / Email</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created On</th>
                            <th class="text-end" style="width: 100px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $adm): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-dark d-block"><?php echo e($adm['name']); ?></span>
                                    <span class="text-muted small"><?php echo e($adm['email']); ?></span>
                                </td>
                                <td><code><?php echo e($adm['username']); ?></code></td>
                                <td><span class="badge bg-secondary"><?php echo e($adm['role_name']); ?></span></td>
                                <td>
                                    <span class="badge <?php echo $adm['status'] == 1 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $adm['status'] == 1 ? 'Active' : 'Suspended'; ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?php echo date('d-M-Y', strtotime($adm['created_at'])); ?></td>
                                <td class="text-end">
                                    <?php if ($adm['id'] !== $current_admin_id): ?>
                                        <form action="users.php" method="POST" class="d-inline">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="admin_id" value="<?php echo $adm['id']; ?>">
                                            <input type="hidden" name="toggle_admin_status" value="1">
                                            <?php if ($adm['status'] == 1): ?>
                                                <input type="hidden" name="status" value="0">
                                                <button type="submit" class="btn btn-sm btn-outline-danger p-1 px-2" title="Suspend staff login">
                                                    Suspend
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="status" value="1">
                                                <button type="submit" class="btn btn-sm btn-outline-success p-1 px-2" title="Restore login">
                                                    Activate
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">You (Active)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
