<?php
// register.php
// Customer Registration Page with OTP Email Verification

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp.php';
require_once __DIR__ . '/includes/mail.php';

$redirect_target = $_GET['redirect'] ?? ($_POST['redirect'] ?? ($_SESSION['redirect_url'] ?? ''));

// Redirect if already logged in
if (is_customer_logged_in()) {
    $destination = !empty($redirect_target) ? $redirect_target : 'dashboard.php';
    header('Location: ' . $destination);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';
    $redirect_target = trim($_POST['redirect'] ?? $redirect_target);

    // Validations
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed. Please refresh and try again.';
    } elseif (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields (Name, Email, Password).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Check if email already registered
            $check = $pdo->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = 'An account with this email address already exists. Please sign in or use another email.';
            } else {
                if (!empty($redirect_target)) {
                    $_SESSION['redirect_url'] = $redirect_target;
                }

                // Store pending registration data securely in session
                $_SESSION['pending_registration'] = [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    'timestamp' => time()
                ];

                // Generate OTP and send email
                $otp = create_otp($email, 'registration', 10);
                $dispatched = send_otp_email($email, $name, $otp, 'Registration');

                set_flash_message('success', 'A 6-digit verification code has been sent to ' . e($email) . '. Please enter it below to complete registration.');
                header('Location: verify-otp.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Registration service unavailable. Please try again later.';
        }
    }
}

$meta_title = 'Create Customer Account | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6">
            <div class="card glass-card p-4 p-md-5 border-0 bg-white" style="border-radius: 16px;">
                <h1 class="page-title text-center mb-2" style="font-size: 1.85rem;">Create Account</h1>
                <p class="text-center text-muted small mb-4">Register for faster checkout, wishlist access, and order tracking</p>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger p-2 small" role="alert">
                        <?php echo e($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Google OAuth Sign-Up Button -->
                <a href="auth-google.php<?php echo !empty($redirect_target) ? '?redirect=' . urlencode($redirect_target) : ''; ?>" class="btn btn-outline-dark d-flex align-items-center justify-content-center gap-2 py-2 mb-3 bg-white text-dark shadow-sm border w-100" style="border-radius: 8px; font-weight: 500; font-size: 0.95rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    <span>Sign up with Google</span>
                </a>

                <div class="d-flex align-items-center my-3">
                    <hr class="flex-grow-1 my-0 text-muted opacity-25">
                    <span class="px-3 text-muted small text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">or register with email</span>
                    <hr class="flex-grow-1 my-0 text-muted opacity-25">
                </div>

                <form action="register.php" method="POST">
                    <?php echo csrf_field(); ?>
                    <?php if (!empty($redirect_target)): ?>
                        <input type="hidden" name="redirect" value="<?php echo e($redirect_target); ?>">
                    <?php endif; ?>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="name" name="name" required placeholder="Full Name" value="<?php echo e($name ?? ''); ?>" autocomplete="name">
                                <label for="name">Full Name <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="form-floating">
                                <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone Number (Optional)" value="<?php echo e($phone ?? ''); ?>" autocomplete="tel">
                                <label for="phone">Phone Number (Optional)</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-floating mb-1">
                            <input type="email" class="form-control" id="email" name="email" required placeholder="Email Address" value="<?php echo e($email ?? ''); ?>" autocomplete="email">
                            <label for="email">Email Address <span class="text-danger">*</span></label>
                        </div>
                        <div class="form-text small text-muted"><i class="bi bi-shield-check text-success"></i> We will send an OTP to verify this email address.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-sm-6">
                            <div class="form-floating">
                                <input type="password" class="form-control" id="password" name="password" required placeholder="Secret Password" autocomplete="new-password">
                                <label for="password">Secret Password <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="form-floating">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Confirm Password" autocomplete="new-password">
                                <label for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-brand">Continue to Email Verification &rarr;</button>
                    </div>

                    <div class="text-center small text-muted">
                        Already have an account? <a href="login.php<?php echo !empty($redirect_target) ? '?redirect=' . urlencode($redirect_target) : ''; ?>" class="fw-bold text-rose">Sign In here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
