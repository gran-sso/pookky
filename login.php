<?php
// login.php
// Customer Login Page

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';

// Capture redirect target if specified
$redirect_target = $_GET['redirect'] ?? ($_POST['redirect'] ?? ($_SESSION['redirect_url'] ?? ''));

// Redirect if already logged in
if (is_customer_logged_in()) {
    $destination = !empty($redirect_target) ? $redirect_target : 'dashboard.php';
    header('Location: ' . $destination);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';
    $redirect_target = trim($_POST['redirect'] ?? $redirect_target);

    // Verify CSRF
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed. Try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $customer = $stmt->fetch();

            if ($customer && password_verify($password, $customer['password'])) {
                if ($customer['status'] == 1) {
                    // 1. Capture old guest session ID BEFORE regeneration
                    $old_guest_session = session_id();

                    // 2. Set Customer Sessions
                    $_SESSION['customer_id'] = $customer['id'];
                    $_SESSION['customer_name'] = $customer['name'];

                    // 3. Seamlessly Merge Guest Cart to Customer Profile
                    $total_cart_items = merge_guest_cart_to_customer($customer['id'], $old_guest_session);

                    // 4. Safely Regenerate Session ID for Security
                    session_regenerate_id(true);

                    // 5. Smart Redirection:
                    // If a specific return URL was requested, use it.
                    // Otherwise, if the customer has items in cart, take them directly to cart.php.
                    $redirect = $redirect_target;
                    unset($_SESSION['redirect_url']);

                    if (empty($redirect) || $redirect === 'dashboard.php') {
                        if ($total_cart_items > 0) {
                            $redirect = 'cart.php';
                        } else {
                            $redirect = 'dashboard.php';
                        }
                    }

                    set_flash_message('success', 'Logged in successfully! Welcome back, ' . $customer['name']);
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $error = 'Your customer account is currently suspended.';
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'A database error occurred. Please try again.';
        }
    }
}

$meta_title = 'Customer Login | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-5">
            <div class="card glass-card p-4 p-md-5 border-0 bg-white" style="border-radius: 16px;">
                <h1 class="page-title text-center mb-2" style="font-size: 1.85rem;">Welcome Back</h1>
                <p class="text-center text-muted small mb-4">Login to view orders, manage addresses, and checkout</p>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger p-2 small" role="alert">
                        <?php echo e($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Google OAuth Sign-In Button -->
                <a href="auth-google.php<?php echo !empty($redirect_target) ? '?redirect=' . urlencode($redirect_target) : ''; ?>" class="btn btn-outline-dark d-flex align-items-center justify-content-center gap-2 py-2 mb-3 bg-white text-dark shadow-sm border w-100" style="border-radius: 8px; font-weight: 500; font-size: 0.95rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    <span>Continue with Google</span>
                </a>

                <div class="d-flex align-items-center my-3">
                    <hr class="flex-grow-1 my-0 text-muted opacity-25">
                    <span class="px-3 text-muted small text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">or sign in with email</span>
                    <hr class="flex-grow-1 my-0 text-muted opacity-25">
                </div>

                <form action="login.php" method="POST">
                    <?php echo csrf_field(); ?>
                    <?php if (!empty($redirect_target)): ?>
                        <input type="hidden" name="redirect" value="<?php echo e($redirect_target); ?>">
                    <?php endif; ?>
                    
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email" required placeholder="Email Address" autocomplete="email">
                        <label for="email">Email Address</label>
                    </div>

                    <div class="form-floating mb-4">
                        <input type="password" class="form-control" id="password" name="password" required placeholder="Password" autocomplete="current-password">
                        <label for="password">Password</label>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-brand">Sign In</button>
                    </div>

                    <div class="text-center small text-muted">
                        Don't have an account? <a href="register.php<?php echo !empty($redirect_target) ? '?redirect=' . urlencode($redirect_target) : ''; ?>" class="fw-bold text-rose">Register here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
