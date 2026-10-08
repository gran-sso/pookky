<?php
// auth-google-callback.php
// Handles OAuth 2.0 redirect callback from Google

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/google-auth.php';

// Handle user cancellation or error from Google
if (!empty($_GET['error'])) {
    $err = $_GET['error'];
    $msg = ($err === 'access_denied') ? 'Google sign-in was cancelled.' : 'Google sign-in error: ' . htmlspecialchars($err);
    set_flash_message('error', $msg);
    header('Location: login.php');
    exit;
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$stored_state = $_SESSION['google_oauth_state'] ?? '';

// Verify CSRF state token
if (empty($code) || empty($state) || empty($stored_state) || !hash_equals($stored_state, $state)) {
    set_flash_message('error', 'Google security authentication check failed. Please try signing in again.');
    header('Location: login.php');
    exit;
}

// Clear state to prevent replay
unset($_SESSION['google_oauth_state']);

// 1. Exchange code for access token
$token_data = exchange_google_code_for_token($code);
if (!empty($token_data['error']) || empty($token_data['access_token'])) {
    set_flash_message('error', 'Failed to authenticate with Google: ' . ($token_data['error'] ?? 'No access token received.'));
    header('Location: login.php');
    exit;
}

// 2. Fetch user profile from Google UserInfo
$profile = fetch_google_user_profile($token_data['access_token']);
if (!empty($profile['error']) || empty($profile['sub'])) {
    set_flash_message('error', 'Failed to retrieve your profile from Google: ' . ($profile['error'] ?? 'Profile missing.'));
    header('Location: login.php');
    exit;
}

// 3. Authenticate, link, or register customer
$auth_result = process_google_customer_auth($profile);

if ($auth_result['success']) {
    $redirect = $_SESSION['google_oauth_return_to'] ?? ($_SESSION['redirect_url'] ?? '');
    unset($_SESSION['google_oauth_return_to']);
    unset($_SESSION['redirect_url']);

    if (empty($redirect) || $redirect === 'dashboard.php') {
        if (!empty($auth_result['total_cart_items']) && $auth_result['total_cart_items'] > 0) {
            $redirect = 'cart.php';
        } else {
            $redirect = 'dashboard.php';
        }
    }

    set_flash_message('success', 'Signed in successfully with Google! Welcome, ' . e($profile['name'] ?? 'Customer') . '.');
    header('Location: ' . $redirect);
    exit;
} else {
    set_flash_message('error', $auth_result['message']);
    header('Location: login.php');
    exit;
}
?>
