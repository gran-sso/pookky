<?php
// config/constants.php
// Load dynamic site settings from database or set defaults

require_once __DIR__ . '/database.php';

// Define base app path constants
define('BASE_URL', '/'); // Standard web root, adjust to project path if deployed in subdirectory
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . 'uploads/');

// Fetch setting helper
function get_setting($key, $default = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// Define configuration constants with fallbacks from settings table
define('SITE_NAME', get_setting('site_name', 'Pookky'));
define('SITE_EMAIL', get_setting('site_email', 'info@pookky.com'));
define('SITE_PHONE', get_setting('site_phone', '+91 98765 43210'));
define('SITE_LOGO', BASE_URL . 'assets/images/logo.png');
define('SITE_LOGO_WHITE', BASE_URL . 'assets/images/pookky-logo-white.png');
$db_currency_symbol = get_setting('currency_symbol', '₹');
if ($db_currency_symbol === '262145' || is_numeric($db_currency_symbol) || empty(trim((string)$db_currency_symbol)) || strpos((string)$db_currency_symbol, '262145') !== false) {
    $db_currency_symbol = '₹';
}
define('CURRENCY_SYMBOL', $db_currency_symbol);
define('CURRENCY_CODE', get_setting('currency_code', 'INR'));
define('SHIPPING_FLAT_RATE', (float)get_setting('shipping_flat_rate', 99.00));
define('FREE_SHIPPING_THRESHOLD', (float)get_setting('free_shipping_threshold', 999.00));
define('DEFAULT_GST_RATE', (float)get_setting('default_gst_rate', 18.00));
define('COMPANY_ADDRESS', get_setting('address_company', 'Pookky Innerwear Mfg Ltd.'));
define('COMPANY_LEGAL_NAME', get_setting('company_legal_name', 'Pookky Innerwear Mfg Ltd.'));
define('FOOTER_ABOUT_TEXT', get_setting('footer_about_text', "Pookky is a leading ladies' innerwear brand dedicated to delivering luxury, comfort, and perfect fit. We design and manufacture premium lingerie using the finest fabrics for the modern woman."));
define('SUPPORT_HOURS', get_setting('support_hours', 'Monday to Saturday, 9:00 AM to 6:00 PM'));
define('RETURN_WINDOW_DAYS', (int)get_setting('return_window_days', 7));

// Social Media Links
define('SOCIAL_FACEBOOK', get_setting('social_facebook', 'https://facebook.com'));
define('SOCIAL_INSTAGRAM', get_setting('social_instagram', 'https://instagram.com'));
define('SOCIAL_PINTEREST', get_setting('social_pinterest', 'https://pinterest.com'));
define('SOCIAL_YOUTUBE', get_setting('social_youtube', 'https://youtube.com'));

// Shipment & Delivery Configuration
define('EST_DELIVERY_MIN_DAYS', (int)get_setting('est_delivery_min_days', 3));
define('EST_DELIVERY_MAX_DAYS', (int)get_setting('est_delivery_max_days', 5));
define('COURIER_PARTNER_NAME', get_setting('courier_partner_name', 'BlueDart, Delhivery, DTDC, Xpressbees'));
define('DISCREET_PACKAGING_ENABLED', (bool)(get_setting('discreet_packaging_enabled', '1') === '1'));
define('COD_SHIPPING_CHARGE', (float)get_setting('cod_shipping_charge', 0.00));
define('COD_ENABLED', (bool)(get_setting('cod_enabled', '1') === '1'));

// Google OAuth 2.0 Configuration
define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID') ?: get_setting('google_client_id', ''));
define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET') ?: get_setting('google_client_secret', ''));
$google_enabled_setting = get_setting('google_auth_enabled', '0');
define('GOOGLE_AUTH_ENABLED', (bool)(env('GOOGLE_AUTH_ENABLED', false) || $google_enabled_setting === '1' || $google_enabled_setting === 1 || (!empty(GOOGLE_CLIENT_ID) && !empty(GOOGLE_CLIENT_SECRET))));

// Razorpay Payment Gateway Configuration
define('RAZORPAY_KEY_ID', env('RAZORPAY_KEY_ID') ?: get_setting('razorpay_key_id', ''));
define('RAZORPAY_KEY_SECRET', env('RAZORPAY_KEY_SECRET') ?: get_setting('razorpay_key_secret', ''));
define('RAZORPAY_WEBHOOK_SECRET', env('RAZORPAY_WEBHOOK_SECRET') ?: get_setting('razorpay_webhook_secret', ''));
define('RAZORPAY_MODE', get_setting('razorpay_mode', 'test'));
$razorpay_enabled_setting = get_setting('razorpay_enabled', '0');
define('RAZORPAY_ENABLED', (bool)($razorpay_enabled_setting === '1' && !empty(RAZORPAY_KEY_ID)));
?>
