<?php
// config/constants.php
// Load dynamic site settings from database or set defaults

if (defined('POOKKY_CONSTANTS_LOADED')) {
    return;
}
define('POOKKY_CONSTANTS_LOADED', true);

require_once __DIR__ . '/database.php';

// Define base app path constants
if (!defined('BASE_URL')) define('BASE_URL', '/'); // Standard web root, adjust to project path if deployed in subdirectory
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', __DIR__ . '/../uploads/');
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', BASE_URL . 'uploads/');

// Fetch setting helper
if (!function_exists('get_setting')) {
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
}

// Define configuration constants with fallbacks from settings table
if (!defined('SITE_NAME')) define('SITE_NAME', get_setting('site_name', 'Pookky'));
if (!defined('APP_NAME')) define('APP_NAME', SITE_NAME);
if (!defined('SITE_EMAIL')) define('SITE_EMAIL', get_setting('site_email', 'info@pookky.com'));
if (!defined('SITE_PHONE')) define('SITE_PHONE', get_setting('site_phone', '+91 98765 43210'));
if (!defined('SITE_LOGO')) define('SITE_LOGO', BASE_URL . 'assets/images/logo.png');
if (!defined('SITE_LOGO_WHITE')) define('SITE_LOGO_WHITE', BASE_URL . 'assets/images/pookky-logo-white.png');

if (!defined('CURRENCY_SYMBOL')) {
    $db_currency_symbol = get_setting('currency_symbol', '₹');
    if ($db_currency_symbol === '262145' || is_numeric($db_currency_symbol) || empty(trim((string)$db_currency_symbol)) || strpos((string)$db_currency_symbol, '262145') !== false) {
        $db_currency_symbol = '₹';
    }
    define('CURRENCY_SYMBOL', $db_currency_symbol);
}

if (!defined('CURRENCY_CODE')) define('CURRENCY_CODE', get_setting('currency_code', 'INR'));
if (!defined('SHIPPING_FLAT_RATE')) define('SHIPPING_FLAT_RATE', (float)get_setting('shipping_flat_rate', 99.00));
if (!defined('FREE_SHIPPING_THRESHOLD')) define('FREE_SHIPPING_THRESHOLD', (float)get_setting('free_shipping_threshold', 999.00));
if (!defined('DEFAULT_GST_RATE')) define('DEFAULT_GST_RATE', (float)get_setting('default_gst_rate', 18.00));
if (!defined('COMPANY_ADDRESS')) define('COMPANY_ADDRESS', get_setting('address_company', 'St. Joseph Garments, Phase 2, Industrial Area, Bangalore, Karnataka - 560001'));
if (!defined('COMPANY_LEGAL_NAME')) define('COMPANY_LEGAL_NAME', get_setting('company_legal_name', 'St. Joseph Garments'));
if (!defined('COMPANY_GSTIN')) define('COMPANY_GSTIN', get_setting('company_gstin', ''));
if (!defined('COMPANY_REG_NUMBER')) define('COMPANY_REG_NUMBER', get_setting('company_reg_number', ''));
if (!defined('COMPANY_PAN')) define('COMPANY_PAN', get_setting('company_pan', ''));
if (!defined('INVOICE_AUTHORIZED_SIGNATORY')) define('INVOICE_AUTHORIZED_SIGNATORY', get_setting('invoice_authorized_signatory', 'St. Joseph Garments'));
if (!defined('INVOICE_TERMS_NOTES')) define('INVOICE_TERMS_NOTES', get_setting('invoice_terms_notes', "1. Standard exchange policies apply within 7 days of delivery.\n2. Lingerie items must remain unworn with tags attached.\n3. All disputes subject to local jurisdiction."));
if (!defined('FOOTER_ABOUT_TEXT')) define('FOOTER_ABOUT_TEXT', get_setting('footer_about_text', "Pookky is a leading ladies' innerwear brand dedicated to delivering luxury, comfort, and perfect fit. We design and manufacture premium lingerie using the finest fabrics for the modern woman."));
if (!defined('SUPPORT_HOURS')) define('SUPPORT_HOURS', get_setting('support_hours', 'Monday to Saturday, 9:00 AM to 6:00 PM'));
if (!defined('RETURN_WINDOW_DAYS')) define('RETURN_WINDOW_DAYS', (int)get_setting('return_window_days', 7));

// Social Media Links
if (!defined('SOCIAL_FACEBOOK')) define('SOCIAL_FACEBOOK', get_setting('social_facebook', 'https://facebook.com'));
if (!defined('SOCIAL_INSTAGRAM')) define('SOCIAL_INSTAGRAM', get_setting('social_instagram', 'https://instagram.com'));
if (!defined('SOCIAL_PINTEREST')) define('SOCIAL_PINTEREST', get_setting('social_pinterest', 'https://pinterest.com'));
if (!defined('SOCIAL_YOUTUBE')) define('SOCIAL_YOUTUBE', get_setting('social_youtube', 'https://youtube.com'));

// Shipment & Delivery Configuration
if (!defined('EST_DELIVERY_MIN_DAYS')) define('EST_DELIVERY_MIN_DAYS', (int)get_setting('est_delivery_min_days', 3));
if (!defined('EST_DELIVERY_MAX_DAYS')) define('EST_DELIVERY_MAX_DAYS', (int)get_setting('est_delivery_max_days', 5));
if (!defined('COURIER_PARTNER_NAME')) define('COURIER_PARTNER_NAME', get_setting('courier_partner_name', 'BlueDart, Delhivery, DTDC, Xpressbees'));
if (!defined('DISCREET_PACKAGING_ENABLED')) define('DISCREET_PACKAGING_ENABLED', (bool)(get_setting('discreet_packaging_enabled', '1') === '1'));
if (!defined('COD_SHIPPING_CHARGE')) define('COD_SHIPPING_CHARGE', (float)get_setting('cod_shipping_charge', 0.00));
if (!defined('COD_ENABLED')) define('COD_ENABLED', (bool)(get_setting('cod_enabled', '1') === '1'));

// Google OAuth 2.0 Configuration
if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID') ?: get_setting('google_client_id', ''));
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET') ?: get_setting('google_client_secret', ''));
if (!defined('GOOGLE_AUTH_ENABLED')) {
    $google_enabled_setting = get_setting('google_auth_enabled', '0');
    define('GOOGLE_AUTH_ENABLED', (bool)(env('GOOGLE_AUTH_ENABLED', false) || $google_enabled_setting === '1' || $google_enabled_setting === 1 || (!empty(GOOGLE_CLIENT_ID) && !empty(GOOGLE_CLIENT_SECRET))));
}

// Razorpay Payment Gateway Configuration
if (!defined('RAZORPAY_KEY_ID')) define('RAZORPAY_KEY_ID', env('RAZORPAY_KEY_ID') ?: get_setting('razorpay_key_id', ''));
if (!defined('RAZORPAY_KEY_SECRET')) define('RAZORPAY_KEY_SECRET', env('RAZORPAY_KEY_SECRET') ?: get_setting('razorpay_key_secret', ''));
if (!defined('RAZORPAY_WEBHOOK_SECRET')) define('RAZORPAY_WEBHOOK_SECRET', env('RAZORPAY_WEBHOOK_SECRET') ?: get_setting('razorpay_webhook_secret', ''));
if (!defined('RAZORPAY_MODE')) define('RAZORPAY_MODE', get_setting('razorpay_mode', 'test'));
if (!defined('RAZORPAY_ENABLED')) {
    $razorpay_enabled_setting = get_setting('razorpay_enabled', '1');
    $env_razorpay_enabled = env('RAZORPAY_ENABLED');
    define('RAZORPAY_ENABLED', (bool)(!empty(RAZORPAY_KEY_ID) && ($env_razorpay_enabled !== null ? (bool)$env_razorpay_enabled : ($razorpay_enabled_setting === '1' || $razorpay_enabled_setting === 1))));
}

// Site Notice / Pre-Launch Testing Banner Configuration
if (!defined('SITE_NOTICE_ENABLED')) define('SITE_NOTICE_ENABLED', (bool)(get_setting('site_notice_enabled', '1') === '1'));
if (!defined('SITE_NOTICE_BADGE')) define('SITE_NOTICE_BADGE', get_setting('site_notice_badge', 'PRE-LAUNCH TESTING'));
if (!defined('SITE_NOTICE_TEXT')) define('SITE_NOTICE_TEXT', get_setting('site_notice_text', 'Welcome to Pookky! We are currently undergoing final testing & verification before our official launch. Orders placed during this period are for demonstration/testing and will not be charged or processed.'));
if (!defined('SITE_NOTICE_TYPE')) define('SITE_NOTICE_TYPE', get_setting('site_notice_type', 'amber')); // 'amber', 'brand', 'dark', 'info'
if (!defined('SITE_NOTICE_DISMISSIBLE')) define('SITE_NOTICE_DISMISSIBLE', (bool)(get_setting('site_notice_dismissible', '1') === '1'));
?>

