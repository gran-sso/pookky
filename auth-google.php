<?php
// auth-google.php
// Public route to initiate Google OAuth 2.0 Login / Registration

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/google-auth.php';

// If already logged in, redirect to dashboard
if (is_customer_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

// Check if Google OAuth is configured
if (!GOOGLE_AUTH_ENABLED || empty(GOOGLE_CLIENT_ID)) {
    set_flash_message('error', 'Google Sign-In is not currently enabled or configured. Please sign in with your email or contact support.');
    header('Location: login.php');
    exit;
}

// Determine destination after login
$return_to = $_GET['redirect'] ?? ($_SESSION['redirect_url'] ?? 'dashboard.php');
$auth_url = get_google_auth_url($return_to);

if ($auth_url === '#') {
    set_flash_message('error', 'Google OAuth credentials missing.');
    header('Location: login.php');
    exit;
}

// Redirect to Google Consent Screen
header('Location: ' . $auth_url);
exit;
?>
