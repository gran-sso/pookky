<?php
// admin/login.php
// Secure Administrator Login Page

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

// Redirect if already logged in
if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Verify CSRF
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid request security token.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            // Find administrator by username or email
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                if ($admin['status'] == 1) {
                    // Start secure session
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = $admin['id'];
                    
                    // Log success
                    log_admin_action($admin['id'], 'Login successful');

                    set_flash_message('success', 'Welcome back, ' . $admin['name'] . '!');
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Your admin account is suspended.';
                }
            } else {
                $error = 'Invalid credentials provided.';
            }
        } catch (PDOException $e) {
            $error = 'A database error occurred. Try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pookky Admin Login</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Bodoni Moda (Display) & Montserrat (UI & Body) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400;0,6..96,500;0,6..96,600;0,6..96,700;1,6..96,400;1,6..96,500&family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&subset=latin,latin-ext,devanagari&display=swap" rel="stylesheet">
    <!-- Core Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css?v=2.3">
    <style>
        body {
            background-color: var(--brand-blush);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            border: none;
            max-width: 420px;
            width: 100%;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(93, 58, 66, 0.08);
        }
        .login-logo {
            font-family: var(--font-heading);
            font-size: 32px;
            font-weight: 700;
            color: var(--brand-mauve);
            text-align: center;
            letter-spacing: 2px;
            margin-bottom: 25px;
        }
    </style>
</head>
<body>

    <div class="card glass-card p-4 p-md-5 login-card">
        <div class="text-center mb-3">
            <img src="../assets/images/logo.png" alt="Pookky" height="50" class="d-inline-block">
        </div>
        <h4 class="text-center mb-4 text-secondary" style="font-size: 14px; letter-spacing: 1.5px; text-transform: uppercase;">Admin Portal</h4>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger p-2 small" role="alert">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <?php echo csrf_field(); ?>
            <div class="mb-3">
                <label for="username" class="form-label small text-muted">Username or Email</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="e.g. admin" required autocomplete="username">
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label small text-muted">Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-brand w-100">Sign In</button>
            </div>
        </form>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
