<?php
// includes/google-auth.php
// Google OAuth 2.0 Engine for Veesta E-Commerce

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

/**
 * Ensure database table contains required columns for Google OAuth
 */
function ensure_google_oauth_schema_exists() {
    global $pdo;
    static $checked = false;
    if ($checked) return;

    try {
        // Check google_id column
        $col1 = $pdo->query("SHOW COLUMNS FROM customers LIKE 'google_id'")->fetch();
        if (!$col1) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN google_id VARCHAR(100) NULL UNIQUE AFTER password");
        }

        // Check auth_provider column
        $col2 = $pdo->query("SHOW COLUMNS FROM customers LIKE 'auth_provider'")->fetch();
        if (!$col2) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN auth_provider VARCHAR(20) DEFAULT 'local' AFTER google_id");
        }

        // Check avatar_url column
        $col3 = $pdo->query("SHOW COLUMNS FROM customers LIKE 'avatar_url'")->fetch();
        if (!$col3) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN avatar_url VARCHAR(255) NULL AFTER auth_provider");
        }

        // Modify password column to allow NULL for OAuth users
        $pwd_col = $pdo->query("SHOW COLUMNS FROM customers LIKE 'password'")->fetch();
        if ($pwd_col && $pwd_col['Null'] === 'NO') {
            $pdo->exec("ALTER TABLE customers MODIFY COLUMN password VARCHAR(255) NULL DEFAULT NULL");
        }

        $checked = true;
    } catch (PDOException $e) {
        // Ignore to prevent breaking
    }
}

// Ensure schema on load
ensure_google_oauth_schema_exists();

/**
 * Get dynamic OAuth redirect callback URI
 *
 * @return string Full URL to auth-google-callback.php
 */
function get_google_redirect_uri() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Determine base folder path
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    // Clean up admin or subdirectories
    $clean_dir = preg_replace('#/(admin|includes)$#i', '', $script_dir);
    $clean_dir = rtrim($clean_dir, '/');

    return $protocol . $host . ($clean_dir ? $clean_dir : '') . '/auth-google-callback.php';
}

/**
 * Generate Google OAuth 2.0 Authorization URL
 *
 * @param string $return_to URL path to redirect customer after login
 * @return string Google consent screen URL
 */
function get_google_auth_url($return_to = 'dashboard.php') {
    if (!defined('GOOGLE_CLIENT_ID') || empty(GOOGLE_CLIENT_ID)) {
        return '#';
    }

    // Generate CSRF state token
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;
    $_SESSION['google_oauth_return_to'] = $return_to;

    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => get_google_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'openid profile email',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account'
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * Exchange Authorization Code for Access Token via Google OAuth Token endpoint
 *
 * @param string $code OAuth authorization code
 * @return array Decoded response with access_token or error
 */
function exchange_google_code_for_token($code) {
    $token_url = 'https://oauth2.googleapis.com/token';

    $post_data = [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => get_google_redirect_uri(),
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['error' => 'Network connection error: ' . $curl_error];
    }

    $data = json_decode($response, true);
    if ($http_code !== 200 || !empty($data['error'])) {
        return [
            'error' => $data['error_description'] ?? ($data['error'] ?? 'Failed to exchange authorization code with Google.')
        ];
    }

    return $data;
}

/**
 * Fetch verified user profile from Google UserInfo API
 *
 * @param string $access_token Google Access Token
 * @return array User profile details
 */
function fetch_google_user_profile($access_token) {
    $userinfo_url = 'https://www.googleapis.com/oauth2/v3/userinfo';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $userinfo_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['error' => 'Failed to reach Google UserInfo: ' . $curl_error];
    }

    $data = json_decode($response, true);
    if ($http_code !== 200 || empty($data['sub'])) {
        return ['error' => 'Invalid profile returned from Google.'];
    }

    return $data;
}

/**
 * Authenticate, link, or register customer using verified Google profile
 *
 * @param array $profile Profile returned from Google (sub, email, name, picture)
 * @return array ['success' => bool, 'message' => string, 'customer' => array|null]
 */
function process_google_customer_auth($profile) {
    global $pdo;
    ensure_google_oauth_schema_exists();

    $google_id = trim($profile['sub'] ?? '');
    $email = strtolower(trim($profile['email'] ?? ''));
    $name = trim($profile['name'] ?? 'Customer');
    $avatar = trim($profile['picture'] ?? '');

    if (empty($google_id) || empty($email)) {
        return [
            'success' => false,
            'message' => 'Incomplete profile data received from Google.',
            'customer' => null
        ];
    }

    try {
        // 1. Check if customer exists by google_id
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE google_id = ? LIMIT 1");
        $stmt->execute([$google_id]);
        $customer = $stmt->fetch();

        if ($customer) {
            // Customer exists with this Google account
            if ((int)$customer['status'] !== 1) {
                return [
                    'success' => false,
                    'message' => 'Your customer account is currently suspended. Please contact support.',
                    'customer' => null
                ];
            }

            // Keep name / avatar and verified status updated
            $up = $pdo->prepare("
                UPDATE customers 
                SET name = ?, avatar_url = ?, email_verified = 1, email_verified_at = COALESCE(email_verified_at, NOW()) 
                WHERE id = ?
            ");
            $up->execute([$name, $avatar, $customer['id']]);

            $customer_id = (int)$customer['id'];
        } else {
            // 2. Check if customer exists with matching email (Account Linking)
            $stmt2 = $pdo->prepare("SELECT * FROM customers WHERE email = ? LIMIT 1");
            $stmt2->execute([$email]);
            $existing_email_user = $stmt2->fetch();

            if ($existing_email_user) {
                if ((int)$existing_email_user['status'] !== 1) {
                    return [
                        'success' => false,
                        'message' => 'Your account is suspended. Please contact customer support.',
                        'customer' => null
                    ];
                }

                // Link Google account to this existing customer profile
                $link_stmt = $pdo->prepare("
                    UPDATE customers 
                    SET google_id = ?, auth_provider = 'google', avatar_url = ?, email_verified = 1, email_verified_at = COALESCE(email_verified_at, NOW()) 
                    WHERE id = ?
                ");
                $link_stmt->execute([$google_id, $avatar, $existing_email_user['id']]);

                $customer_id = (int)$existing_email_user['id'];
                $customer = $existing_email_user;
            } else {
                // 3. New Customer Registration via Google
                $ins_stmt = $pdo->prepare("
                    INSERT INTO customers (name, email, google_id, auth_provider, avatar_url, status, email_verified, email_verified_at) 
                    VALUES (?, ?, ?, 'google', ?, 1, 1, NOW())
                ");
                $ins_stmt->execute([$name, $email, $google_id, $avatar]);

                $customer_id = (int)$pdo->lastInsertId();
                $customer = [
                    'id' => $customer_id,
                    'name' => $name,
                    'email' => $email,
                    'avatar_url' => $avatar
                ];
            }
        }

        // Establish session
        $old_guest_session = session_id();
        $_SESSION['customer_id'] = $customer_id;
        $_SESSION['customer_name'] = $name;
        $_SESSION['customer_avatar'] = $avatar;

        // Seamlessly merge guest cart before session_regenerate_id
        $total_cart_items = merge_guest_cart_to_customer($customer_id, $old_guest_session);

        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        return [
            'success' => true,
            'message' => 'Signed in successfully with Google!',
            'customer' => $customer,
            'total_cart_items' => $total_cart_items
        ];

    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database error during Google authentication: ' . $e->getMessage(),
            'customer' => null
        ];
    }
}
?>
