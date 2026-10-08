<?php
// verify-otp.php
// Email OTP Verification Page for New Customer Accounts

$meta_title = 'Verify Email Address | Pookky';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/otp.php';
require_once __DIR__ . '/includes/mail.php';

// Redirect if already logged in
if (is_customer_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

// Ensure there is an active pending registration session
if (empty($_SESSION['pending_registration']) || empty($_SESSION['pending_registration']['email'])) {
    set_flash_message('error', 'Registration session expired or invalid. Please fill out the registration form again.');
    header('Location: register.php');
    exit;
}

$pending = $_SESSION['pending_registration'];
$email = $pending['email'];
$name = $pending['name'];
$error = '';

// Handle Cancel / Edit Email request
if (isset($_GET['action']) && $_GET['action'] === 'edit_email') {
    unset($_SESSION['pending_registration']);
    header('Location: register.php');
    exit;
}

// Calculate remaining cooldown for Resend OTP button
$resend_check = can_resend_otp($email, 'registration', 60);
$remaining_cooldown = $resend_check['remaining_seconds'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'verify_otp';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security validation failed. Please refresh and try again.';
    } elseif ($action === 'resend_otp') {
        // Handle OTP Resend
        if (!$resend_check['allowed']) {
            $error = "Please wait {$remaining_cooldown} seconds before requesting a new code.";
        } else {
            $new_otp = create_otp($email, 'registration', 10);
            send_otp_email($email, $name, $new_otp, 'Registration');
            set_flash_message('success', 'A fresh 6-digit verification code has been dispatched to your email.');
            header('Location: verify-otp.php');
            exit;
        }
    } elseif ($action === 'verify_otp') {
        // Collect OTP digits
        $otp_code = '';
        if (isset($_POST['otp']) && is_array($_POST['otp'])) {
            $otp_code = implode('', array_map('trim', $_POST['otp']));
        } else {
            $otp_code = trim($_POST['otp_code'] ?? '');
        }

        // Verify OTP
        $result = verify_otp($email, $otp_code, 'registration');

        if ($result['success']) {
            try {
                // Finalize customer account creation in database
                $stmt = $pdo->prepare("
                    INSERT INTO customers (name, email, phone, password, status, email_verified, email_verified_at) 
                    VALUES (?, ?, ?, ?, 1, 1, NOW())
                ");
                $stmt->execute([
                    $pending['name'],
                    $pending['email'],
                    !empty($pending['phone']) ? $pending['phone'] : null,
                    $pending['password_hash']
                ]);

                $new_customer_id = (int)$pdo->lastInsertId();

                // Automatically log in the customer
                $_SESSION['customer_id'] = $new_customer_id;
                $_SESSION['customer_name'] = $pending['name'];

                // Clean up registration session
                unset($_SESSION['pending_registration']);

                // Capture guest session and merge cart
                $old_guest_session = session_id();
                $total_cart_items = merge_guest_cart_to_customer($new_customer_id, $old_guest_session);

                if (!headers_sent()) {
                    session_regenerate_id(true);
                }

                $redirect = $_SESSION['redirect_url'] ?? '';
                unset($_SESSION['redirect_url']);

                if (empty($redirect) || $redirect === 'dashboard.php') {
                    if ($total_cart_items > 0) {
                        $redirect = 'cart.php';
                    } else {
                        $redirect = 'dashboard.php';
                    }
                }

                set_flash_message('success', 'Email verified successfully! Welcome to ' . SITE_NAME . ', ' . $pending['name'] . '.');
                header('Location: ' . $redirect);
                exit;

            } catch (PDOException $e) {
                $error = 'Failed to create account. Please contact customer support.';
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
            <div class="card glass-card p-4 p-md-5 border-0 bg-white shadow-sm" style="border-radius: 16px;">
                
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-brand-blush rounded-circle mb-3" style="width: 72px; height: 72px;">
                        <i class="bi bi-envelope-check text-rose fs-1"></i>
                    </div>
                    <h1 class="page-title mb-1" style="font-size: 1.75rem;">Verify Your Email</h1>
                    <p class="text-muted small mb-2">We sent a 6-digit verification code to:</p>
                    <div class="d-inline-flex align-items-center gap-2 bg-light px-3 py-1 rounded-pill">
                        <strong class="text-dark small"><?php echo e($email); ?></strong>
                        <a href="verify-otp.php?action=edit_email" class="text-rose text-decoration-none small" title="Change email">
                            <i class="bi bi-pencil-square"></i> Change
                        </a>
                    </div>
                </div>

                <?php display_flash_messages(); ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger p-2 small text-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo e($error); ?>
                    </div>
                <?php endif; ?>

                <!-- OTP Verification Form -->
                <form action="verify-otp.php" method="POST" id="otpForm" class="mb-4">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="verify_otp">
                    <input type="hidden" name="otp_code" id="combinedOtp" value="">

                    <label class="form-label small text-muted text-center d-block mb-3">Enter the 6-digit code</label>
                    
                    <div class="d-flex justify-content-between gap-2 mb-4 otp-inputs-container">
                        <input type="text" class="form-control text-center fs-4 fw-bold otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required autofocus autocomplete="off" name="otp[]">
                        <input type="text" class="form-control text-center fs-4 fw-bold otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required autocomplete="off" name="otp[]">
                        <input type="text" class="form-control text-center fs-4 fw-bold otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required autocomplete="off" name="otp[]">
                        <input type="text" class="form-control text-center fs-4 fw-bold otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required autocomplete="off" name="otp[]">
                        <input type="text" class="form-control text-center fs-4 fw-bold otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required autocomplete="off" name="otp[]">
                        <input type="text" class="form-control text-center fs-4 fw-bold otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required autocomplete="off" name="otp[]">
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-brand py-2" id="verifyBtn">
                            <i class="bi bi-shield-check me-1"></i> Verify & Complete Registration
                        </button>
                    </div>
                </form>

                <!-- Resend OTP Section -->
                <div class="text-center pt-3 border-top">
                    <p class="small text-muted mb-2">Didn't receive the email code?</p>
                    <form action="verify-otp.php" method="POST" class="d-inline" id="resendForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="resend_otp">
                        <button type="submit" class="btn btn-link text-rose p-0 small fw-bold text-decoration-none" id="resendBtn" <?php echo $remaining_cooldown > 0 ? 'disabled' : ''; ?>>
                            <span id="resendText">
                                <?php if ($remaining_cooldown > 0): ?>
                                    Resend code in <span id="cooldownTimer"><?php echo $remaining_cooldown; ?></span>s
                                <?php else: ?>
                                    Resend Verification Code
                                <?php endif; ?>
                            </span>
                        </button>
                    </form>
                </div>

                <div class="text-center mt-3">
                    <a href="verify-otp.php?action=edit_email" class="text-muted small text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Cancel and return to registration
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<style>
.otp-digit {
    height: 56px;
    border: 1.5px solid #e0d5d4;
    border-radius: 10px;
    font-family: 'Courier New', Courier, monospace;
    transition: all 0.2s ease;
}
.otp-digit:focus {
    border-color: var(--brand-rose-gold, #c48b81);
    box-shadow: 0 0 0 0.25rem rgba(196, 139, 129, 0.2);
    outline: none;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const digits = document.querySelectorAll('.otp-digit');
    const combinedInput = document.getElementById('combinedOtp');
    const form = document.getElementById('otpForm');

    // Auto-focus and backspace behavior for OTP inputs
    digits.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            // Remove non-numeric characters
            this.value = this.value.replace(/[^0-9]/g, '');

            if (this.value.length >= 1) {
                this.value = this.value.charAt(0);
                if (index < digits.length - 1) {
                    digits[index + 1].focus();
                }
            }
            updateCombined();
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !this.value && index > 0) {
                digits[index - 1].focus();
            }
        });

        // Handle Paste (e.g. pasting full 6 digits)
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text').trim();
            const numbersOnly = pasteData.replace(/[^0-9]/g, '');
            if (numbersOnly.length >= 6) {
                for (let i = 0; i < 6; i++) {
                    if (digits[i]) {
                        digits[i].value = numbersOnly.charAt(i);
                    }
                }
                digits[5].focus();
                updateCombined();
            }
        });
    });

    function updateCombined() {
        let code = '';
        digits.forEach(d => code += d.value);
        combinedInput.value = code;
    }

    form.addEventListener('submit', function() {
        updateCombined();
    });

    // Cooldown countdown timer logic for Resend button
    let cooldown = <?php echo (int)$remaining_cooldown; ?>;
    const resendBtn = document.getElementById('resendBtn');
    const resendText = document.getElementById('resendText');

    if (cooldown > 0) {
        const interval = setInterval(function() {
            cooldown--;
            if (cooldown <= 0) {
                clearInterval(interval);
                resendBtn.removeAttribute('disabled');
                resendText.innerHTML = 'Resend Verification Code';
            } else {
                resendText.innerHTML = 'Resend code in ' + cooldown + 's';
            }
        }, 1000);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
