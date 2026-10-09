<?php
// admin/settings.php
// Store Settings configuration management

$page_title = 'Global Settings';
$active_menu = 'settings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/google-auth.php';

// Check permissions
require_admin_permission('all');

$error = '';
$success = '';

// Handle configurations update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $pdo->beginTransaction();

            // Set keys to update
            $keys = [
                'site_name',
                'site_email',
                'site_phone',
                'currency_symbol',
                'currency_code',
                'default_gst_rate',
                'address_company',
                'company_legal_name',
                'footer_about_text',
                'support_hours',
                'return_window_days',
                'social_facebook',
                'social_instagram',
                'social_pinterest',
                'social_youtube',
                'shipping_flat_rate',
                'free_shipping_threshold',
                'est_delivery_min_days',
                'est_delivery_max_days',
                'courier_partner_name',
                'discreet_packaging_enabled',
                'cod_shipping_charge',
                'cod_enabled',
                'razorpay_enabled',
                'razorpay_mode',
                'razorpay_key_id',
                'razorpay_key_secret',
                'razorpay_webhook_secret',
                'google_client_id',
                'google_client_secret',
                'google_auth_enabled',
                'nav_menu_categories',
                'site_notice_enabled',
                'site_notice_badge',
                'site_notice_text',
                'site_notice_type',
                'site_notice_dismissible'
            ];

            foreach ($keys as $key) {
                if ($key === 'nav_menu_categories') {
                    $selected_cats = $_POST['nav_menu_categories'] ?? [];
                    if (is_array($selected_cats)) {
                        $val = implode(',', array_filter(array_map('intval', $selected_cats)));
                    } else {
                        $val = trim((string)$selected_cats);
                    }
                } else {
                    $val = isset($_POST[$key]) ? trim($_POST[$key]) : '';
                }

                if ($key === 'currency_symbol') {
                    if ($val === '262145' || is_numeric($val) || empty(trim((string)$val)) || strpos((string)$val, '262145') !== false) {
                        $val = '₹';
                    }
                }
                if ($key === 'google_auth_enabled') {
                    $val = isset($_POST['google_auth_enabled']) ? '1' : '0';
                }
                if ($key === 'discreet_packaging_enabled') {
                    $val = isset($_POST['discreet_packaging_enabled']) ? '1' : '0';
                }
                if ($key === 'cod_enabled') {
                    $val = isset($_POST['cod_enabled']) ? '1' : '0';
                }
                if ($key === 'razorpay_enabled') {
                    $val = isset($_POST['razorpay_enabled']) ? '1' : '0';
                }
                if ($key === 'site_notice_enabled') {
                    $val = isset($_POST['site_notice_enabled']) ? '1' : '0';
                }
                if ($key === 'site_notice_dismissible') {
                    $val = isset($_POST['site_notice_dismissible']) ? '1' : '0';
                }
                
                // Verify key exists, else insert
                $stmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ? LIMIT 1");
                $stmt->execute([$key]);
                if ($stmt->fetch()) {
                    $up = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $up->execute([$val, $key]);
                } else {
                    $in = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                    $in->execute([$key, $val]);
                }
            }

            $pdo->commit();
            log_admin_action($current_admin_id, "Updated global website, shipment & payment configurations");
            set_flash_message('success', 'All store configurations saved successfully!');
            header('Location: settings.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Failed to save settings: ' . $e->getMessage();
        }
    }
}

$google_cid = get_setting('google_client_id', '');
$google_csecret = get_setting('google_client_secret', '');
$google_enabled = get_setting('google_auth_enabled', '0') === '1';
$redirect_uri = get_google_redirect_uri();

$est_min = get_setting('est_delivery_min_days', '3');
$est_max = get_setting('est_delivery_max_days', '5');
$courier_names = get_setting('courier_partner_name', 'BlueDart, Delhivery, DTDC, Xpressbees');
$discreet_enabled = get_setting('discreet_packaging_enabled', '1') === '1';
$cod_charge = get_setting('cod_shipping_charge', '0');
$cod_active = get_setting('cod_enabled', '1') === '1';

// Razorpay settings
$razorpay_active = get_setting('razorpay_enabled', '0') === '1';
$razorpay_mode_val = get_setting('razorpay_mode', 'test');
$razorpay_kid = get_setting('razorpay_key_id', '');
$razorpay_ksec = get_setting('razorpay_key_secret', '');
$razorpay_wsec = get_setting('razorpay_webhook_secret', '');

// Compute Webhook / Callback URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$webhook_url = $protocol . $host . rtrim(dirname(dirname($_SERVER['REQUEST_URI'] ?? '/')), '/\\') . '/razorpay-webhook.php';

// Navigation categories configuration
$all_active_categories = $pdo->query("SELECT id, name, slug FROM categories WHERE status = 1 AND parent_id IS NULL ORDER BY sort_order ASC, id ASC")->fetchAll();
$saved_nav_categories_str = get_setting('nav_menu_categories', '1,2,3');
$saved_nav_ids = !empty($saved_nav_categories_str) ? array_map('intval', explode(',', $saved_nav_categories_str)) : [1, 2, 3];

// Site Notice / Testing Banner configuration
$notice_enabled = get_setting('site_notice_enabled', '1') === '1';
$notice_badge = get_setting('site_notice_badge', 'PRE-LAUNCH TESTING');
$notice_text = get_setting('site_notice_text', 'Welcome to Pookky! We are currently undergoing final testing & verification before our official launch. Orders placed during this period are for demonstration/testing and will not be charged or processed.');
$notice_type = get_setting('site_notice_type', 'amber');
$notice_dismissible = get_setting('site_notice_dismissible', '1') === '1';
?>

<div class="row g-4 justify-content-center" style="max-width: 1000px; margin: 0 auto;">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-sliders me-2 text-brand"></i> Store & Platform Settings</h4>
                <p class="text-muted small mb-0">Configure store identity, testing banner, automated shipping calculation, Razorpay online payments, and 3rd-party integrations.</p>
            </div>
            <a href="pages.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-file-earmark-richtext me-1"></i> Manage CMS Pages
            </a>
        </div>
    </div>
    <div class="col-12">
        <form action="settings.php" method="POST" id="settingsForm">
            <?php echo csrf_field(); ?>

            <!-- Interactive Settings Tabs Navigation -->
            <ul class="nav nav-pills gap-2 mb-4 bg-white p-2 rounded-4 shadow-sm border" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active rounded-pill px-3 py-2 fw-semibold" id="tab-general-btn" data-bs-toggle="pill" data-bs-target="#tab-general" type="button" role="tab" aria-controls="tab-general" aria-selected="true">
                        <i class="bi bi-shop me-1"></i> General & Tax
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold text-warning" id="tab-notice-btn" data-bs-toggle="pill" data-bs-target="#tab-notice" type="button" role="tab" aria-controls="tab-notice" aria-selected="false">
                        <i class="bi bi-megaphone-fill me-1"></i> Pre-Launch Banner
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="tab-social-btn" data-bs-toggle="pill" data-bs-target="#tab-social" type="button" role="tab" aria-controls="tab-social" aria-selected="false">
                        <i class="bi bi-share me-1"></i> Social Profiles
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="tab-navbar-btn" data-bs-toggle="pill" data-bs-target="#tab-navbar" type="button" role="tab" aria-controls="tab-navbar" aria-selected="false">
                        <i class="bi bi-menu-button-wide me-1"></i> Navigation Menu
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="tab-shipping-btn" data-bs-toggle="pill" data-bs-target="#tab-shipping" type="button" role="tab" aria-controls="tab-shipping" aria-selected="false">
                        <i class="bi bi-truck me-1"></i> Shipping & Logistics
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold text-primary" id="tab-razorpay-btn" data-bs-toggle="pill" data-bs-target="#tab-razorpay" type="button" role="tab" aria-controls="tab-razorpay" aria-selected="false">
                        <i class="bi bi-credit-card-2-front-fill me-1"></i> Razorpay Payments
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold text-danger" id="tab-google-btn" data-bs-toggle="pill" data-bs-target="#tab-google" type="button" role="tab" aria-controls="tab-google" aria-selected="false">
                        <i class="bi bi-google me-1"></i> Google 1-Click Login
                    </button>
                </li>
            </ul>

            <!-- Tab Panels Content Container -->
            <div class="tab-content" id="settingsTabContent">

                <!-- 1. GENERAL & TAX TAB -->
                <div class="tab-pane fade show active" id="tab-general" role="tabpanel" aria-labelledby="tab-general-btn">
                    <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
                        <h5 class="fw-bold mb-3 text-dark pb-2 border-bottom">
                            <i class="bi bi-shop me-2 text-primary"></i> General Store Identity & Contact
                        </h5>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="site_name" class="form-label fw-bold small">Website Platform Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo e(SITE_NAME); ?>" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="site_email" class="form-label fw-bold small">Support Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="site_email" name="site_email" value="<?php echo e(SITE_EMAIL); ?>" required>
                            </div>
                            
                            <div class="col-12 col-md-6">
                                <label for="site_phone" class="form-label fw-bold small">Contact / Helpline Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="site_phone" name="site_phone" value="<?php echo e(SITE_PHONE); ?>" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <label for="currency_symbol" class="form-label fw-bold small">Currency Symbol <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" value="<?php echo e(CURRENCY_SYMBOL === '262145' || is_numeric(CURRENCY_SYMBOL) ? '₹' : CURRENCY_SYMBOL); ?>" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <label for="currency_code" class="form-label fw-bold small">Currency Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="currency_code" name="currency_code" value="<?php echo e(CURRENCY_CODE); ?>" required placeholder="e.g. INR">
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="default_gst_rate" class="form-label fw-bold small">Default GST Rate (%)</label>
                                <input type="number" step="0.01" class="form-control" id="default_gst_rate" name="default_gst_rate" value="<?php echo e(DEFAULT_GST_RATE); ?>" required>
                                <div class="form-text small text-muted">Applied on order checkout & invoices.</div>
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="company_legal_name" class="form-label fw-bold small">Company Legal Entity Name</label>
                                <input type="text" class="form-control" id="company_legal_name" name="company_legal_name" value="<?php echo e(COMPANY_LEGAL_NAME); ?>" required placeholder="e.g. Pookky Innerwear Mfg Ltd.">
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="return_window_days" class="form-label fw-bold small">Return Window (Days)</label>
                                <input type="number" class="form-control" id="return_window_days" name="return_window_days" value="<?php echo e(RETURN_WINDOW_DAYS); ?>" required min="1" max="30">
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="support_hours" class="form-label fw-bold small">Customer Support Working Hours</label>
                                <input type="text" class="form-control" id="support_hours" name="support_hours" value="<?php echo e(SUPPORT_HOURS); ?>" required placeholder="e.g. Mon-Sat, 9AM-6PM">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="address_company" class="form-label fw-bold small">Company Headquarters Address</label>
                                <textarea class="form-control" id="address_company" name="address_company" rows="2" required><?php echo e(COMPANY_ADDRESS); ?></textarea>
                            </div>

                            <div class="col-12">
                                <label for="footer_about_text" class="form-label fw-bold small">Storefront Footer Brand Bio / About Summary</label>
                                <textarea class="form-control" id="footer_about_text" name="footer_about_text" rows="2" required><?php echo e(FOOTER_ABOUT_TEXT); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. SITE NOTICE / PRE-LAUNCH TESTING BANNER TAB -->
                <div class="tab-pane fade" id="tab-notice" role="tabpanel" aria-labelledby="tab-notice-btn">
                    <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px; border-left: 4px solid #F59E0B !important;">
                        <div class="d-flex align-items-center justify-content-between pb-2 border-bottom mb-3">
                            <div>
                                <h5 class="fw-bold mb-1 text-dark">
                                    <i class="bi bi-megaphone-fill text-warning me-2"></i> Pre-Launch / Testing Announcement Banner
                                </h5>
                                <p class="text-muted small mb-0">Display a prominent banner across the top of all public pages informing visitors or payment gateway evaluators that the site is in pre-launch testing.</p>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="site_notice_enabled" name="site_notice_enabled" value="1" <?php echo $notice_enabled ? 'checked' : ''; ?> style="width: 2.5em; height: 1.3em;">
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label for="site_notice_badge" class="form-label fw-bold small">Pill Badge Text</label>
                                <input type="text" class="form-control" id="site_notice_badge" name="site_notice_badge" value="<?php echo e($notice_badge); ?>" placeholder="e.g. PRE-LAUNCH TESTING">
                                <div class="form-text small text-muted">Highlighted badge label displayed at the front.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="site_notice_type" class="form-label fw-bold small">Visual Color Theme</label>
                                <select class="form-select" id="site_notice_type" name="site_notice_type">
                                    <option value="amber" <?php echo $notice_type === 'amber' ? 'selected' : ''; ?>>Amber Gold (Testing & Beta - Recommended)</option>
                                    <option value="brand" <?php echo $notice_type === 'brand' ? 'selected' : ''; ?>>Rose Brand (Soft Pink & Berry)</option>
                                    <option value="dark" <?php echo $notice_type === 'dark' ? 'selected' : ''; ?>>Charcoal Dark (High Contrast)</option>
                                    <option value="info" <?php echo $notice_type === 'info' ? 'selected' : ''; ?>>Azure Blue (Notice & Info)</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold small">Visitor Dismiss Option</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="site_notice_dismissible" name="site_notice_dismissible" value="1" <?php echo $notice_dismissible ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="site_notice_dismissible">Allow visitors to temporarily dismiss banner (via X button)</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="site_notice_text" class="form-label fw-bold small">Banner Message Text <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="site_notice_text" name="site_notice_text" rows="3" placeholder="Enter message to display..."><?php echo e($notice_text); ?></textarea>
                                <div class="form-text small text-muted">Clear disclosure helps Razorpay verification officers understand the website's stage while reviewing your merchant account.</div>
                            </div>

                            <div class="col-12 mt-3">
                                <div class="alert alert-light border p-3 rounded-3 mb-0">
                                    <div class="fw-bold small text-muted text-uppercase mb-2"><i class="bi bi-eye me-1"></i> Live Appearance Preview:</div>
                                    <div class="p-2 rounded text-center small fw-medium" style="background: linear-gradient(90deg, #FFFBEB 0%, #FEF3C7 50%, #FFFBEB 100%); color: #92400E; border: 1px solid #FDE68A;">
                                        <span class="badge rounded-pill px-2 py-1 text-uppercase fw-bold me-1" style="background-color: #D97706; color: #fff; font-size: 10px;">
                                            <i class="bi bi-cone-striped me-1"></i>PRE-LAUNCH TESTING
                                        </span>
                                        <span>Welcome to Pookky! We are currently undergoing final testing & verification before our official launch. Orders placed during this period are for demonstration/testing and will not be charged or processed.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. SOCIAL PROFILES TAB -->
                <div class="tab-pane fade" id="tab-social" role="tabpanel" aria-labelledby="tab-social-btn">
                    <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px;">
                        <h5 class="fw-bold mb-3 text-dark pb-2 border-bottom">
                            <i class="bi bi-share me-2 text-danger"></i> Official Social Media Profiles
                        </h5>
                        <p class="text-muted small mb-3">Links will be displayed as interactive social icons in the customer storefront footer.</p>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="social_facebook" class="form-label fw-bold small"><i class="bi bi-facebook text-primary me-1"></i> Facebook URL</label>
                                <input type="url" class="form-control form-control-sm" id="social_facebook" name="social_facebook" value="<?php echo e(SOCIAL_FACEBOOK); ?>" placeholder="https://facebook.com/yourbrand">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="social_instagram" class="form-label fw-bold small"><i class="bi bi-instagram text-danger me-1"></i> Instagram URL</label>
                                <input type="url" class="form-control form-control-sm" id="social_instagram" name="social_instagram" value="<?php echo e(SOCIAL_INSTAGRAM); ?>" placeholder="https://instagram.com/yourbrand">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="social_pinterest" class="form-label fw-bold small"><i class="bi bi-pinterest text-danger me-1"></i> Pinterest URL</label>
                                <input type="url" class="form-control form-control-sm" id="social_pinterest" name="social_pinterest" value="<?php echo e(SOCIAL_PINTEREST); ?>" placeholder="https://pinterest.com/yourbrand">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="social_youtube" class="form-label fw-bold small"><i class="bi bi-youtube text-danger me-1"></i> YouTube URL</label>
                                <input type="url" class="form-control form-control-sm" id="social_youtube" name="social_youtube" value="<?php echo e(SOCIAL_YOUTUBE); ?>" placeholder="https://youtube.com/@yourbrand">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. NAVIGATION MENU TAB -->
                <div class="tab-pane fade" id="tab-navbar" role="tabpanel" aria-labelledby="tab-navbar-btn">
                    <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px; border-left: 4px solid var(--brand-rose-gold, #B76E79) !important;">
                        <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-3">
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-menu-button-wide me-2 text-brand"></i> Header Navigation Menu Categories
                            </h5>
                            <span class="badge bg-light text-dark border px-2 py-1 small">Storefront Navbar</span>
                        </div>
                        <p class="text-muted small mb-3">
                            Choose which product categories appear directly on the storefront top navigation bar. Selecting 2 to 4 key categories keeps the menu clean, perfectly spaced, and avoids word-wrapping on laptop & desktop screens.
                        </p>

                        <div class="row g-3">
                            <div class="col-12">
                                <div class="p-3 bg-light rounded-3 border">
                                    <label class="form-label fw-bold small text-dark mb-2 d-flex justify-content-between">
                                        <span>Categories to Display in Top Navigation:</span>
                                        <span class="badge bg-primary rounded-pill fw-normal" id="selectedCountBadge"><?php echo count($saved_nav_ids); ?> Selected</span>
                                    </label>
                                    <div class="row g-2">
                                        <?php foreach ($all_active_categories as $cat_item): ?>
                                            <div class="col-12 col-sm-6 col-md-4">
                                                <div class="form-check form-switch p-2 bg-white rounded border d-flex align-items-center justify-content-between">
                                                    <label class="form-check-label small fw-semibold text-dark ps-2" for="nav_cat_<?php echo $cat_item['id']; ?>" style="cursor: pointer;">
                                                        <?php echo e($cat_item['name']); ?>
                                                        <small class="text-muted d-block fw-normal" style="font-size: 11px;">/<?php echo e($cat_item['slug']); ?></small>
                                                    </label>
                                                    <input class="form-check-input me-1 nav-menu-checkbox" type="checkbox" role="switch" name="nav_menu_categories[]" id="nav_cat_<?php echo $cat_item['id']; ?>" value="<?php echo $cat_item['id']; ?>" <?php echo in_array((int)$cat_item['id'], $saved_nav_ids) ? 'checked' : ''; ?> style="width: 2.2em; height: 1.2em; cursor: pointer;">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="form-text small text-muted mt-2 d-flex align-items-center">
                                        <i class="bi bi-info-circle me-1 text-primary"></i> 
                                        <span>Selected categories will show on the top bar in the order chosen above alongside Home, About, and Contact.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. SHIPPING & LOGISTICS TAB -->
                <div class="tab-pane fade" id="tab-shipping" role="tabpanel" aria-labelledby="tab-shipping-btn">
                    <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px; border-left: 4px solid var(--brand-pink, #E60066) !important;">
                        <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-3">
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-truck me-2 text-brand"></i> Shipment & Logistics Configuration
                            </h5>
                            <span class="badge bg-light text-dark border px-2 py-1 small">Delivery Engine</span>
                        </div>
                        <p class="text-muted small mb-4">Set delivery fee rules, free shipping thresholds, delivery day estimates, courier partners, and packaging preferences.</p>

                        <div class="row g-3">
                            <!-- Flat Rate & Free Threshold -->
                            <div class="col-12 col-md-6">
                                <label for="shipping_flat_rate" class="form-label fw-bold small">Flat Shipping Charges (<?php echo e(CURRENCY_SYMBOL); ?>) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><?php echo e(CURRENCY_SYMBOL); ?></span>
                                    <input type="number" step="0.01" class="form-control" id="shipping_flat_rate" name="shipping_flat_rate" value="<?php echo e(SHIPPING_FLAT_RATE); ?>" required>
                                </div>
                                <div class="form-text small text-muted">Applied to shopping cart subtotal when below the free shipping threshold.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="free_shipping_threshold" class="form-label fw-bold small">Free Shipping Order Threshold (<?php echo e(CURRENCY_SYMBOL); ?>) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><?php echo e(CURRENCY_SYMBOL); ?></span>
                                    <input type="number" step="0.01" class="form-control" id="free_shipping_threshold" name="free_shipping_threshold" value="<?php echo e(FREE_SHIPPING_THRESHOLD); ?>" required>
                                </div>
                                <div class="form-text small text-muted">Orders with subtotal equal to or above this value qualify for free delivery automatically.</div>
                            </div>

                            <!-- Delivery Window Estimate -->
                            <div class="col-12 col-md-3">
                                <label for="est_delivery_min_days" class="form-label fw-bold small">Min Delivery Days</label>
                                <input type="number" min="1" max="30" class="form-control" id="est_delivery_min_days" name="est_delivery_min_days" value="<?php echo e($est_min); ?>" required>
                                <div class="form-text small text-muted">e.g. 3 days</div>
                            </div>

                            <div class="col-12 col-md-3">
                                <label for="est_delivery_max_days" class="form-label fw-bold small">Max Delivery Days</label>
                                <input type="number" min="1" max="60" class="form-control" id="est_delivery_max_days" name="est_delivery_max_days" value="<?php echo e($est_max); ?>" required>
                                <div class="form-text small text-muted">e.g. 5 days</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="courier_partner_name" class="form-label fw-bold small">Logistics & Courier Partners</label>
                                <input type="text" class="form-control" id="courier_partner_name" name="courier_partner_name" value="<?php echo e($courier_names); ?>" placeholder="e.g. BlueDart, Delhivery, DTDC, Xpressbees">
                                <div class="form-text small text-muted">Displayed in policy pages, checkout information, and order tracking.</div>
                            </div>

                            <!-- Discreet Packaging Switch -->
                            <div class="col-12 col-md-6 pt-2">
                                <div class="p-3 bg-light rounded-3 border">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <h6 class="fw-bold mb-1 text-dark"><i class="bi bi-box-seam me-1 text-brand"></i> Discreet Packaging Guarantee</h6>
                                            <div class="small text-muted">Show 100% unmarked discreet delivery assurance across checkout.</div>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="discreet_packaging_enabled" name="discreet_packaging_enabled" value="1" <?php echo $discreet_enabled ? 'checked' : ''; ?> style="width: 2.5em; height: 1.3em;">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cash on Delivery Switch & Fee -->
                            <div class="col-12 col-md-6 pt-2">
                                <div class="p-3 bg-light rounded-3 border">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div>
                                            <h6 class="fw-bold mb-1 text-dark"><i class="bi bi-cash-stack me-1 text-success"></i> Cash on Delivery (COD)</h6>
                                            <div class="small text-muted">Enable COD option on public checkout.</div>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="cod_enabled" name="cod_enabled" value="1" <?php echo $cod_active ? 'checked' : ''; ?> style="width: 2.5em; height: 1.3em;">
                                        </div>
                                    </div>
                                    <div class="mt-2 pt-2 border-top">
                                        <label for="cod_shipping_charge" class="form-label small fw-semibold mb-1">COD Extra Convenience Fee (<?php echo e(CURRENCY_SYMBOL); ?>)</label>
                                        <input type="number" step="0.01" class="form-control form-control-sm" id="cod_shipping_charge" name="cod_shipping_charge" value="<?php echo e($cod_charge); ?>" placeholder="0 for Free COD">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. RAZORPAY PAYMENT GATEWAY TAB -->
                <div class="tab-pane fade" id="tab-razorpay" role="tabpanel" aria-labelledby="tab-razorpay-btn">
                    <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px; border-left: 4px solid #0C2340 !important;">
                        <div class="d-flex align-items-center justify-content-between pb-2 border-bottom mb-3">
                            <div>
                                <h5 class="fw-bold mb-0 text-dark">
                                    <i class="bi bi-credit-card-2-front-fill me-2" style="color: #0C2340;"></i> Razorpay Payment Gateway Integration
                                </h5>
                                <div class="small text-muted">Accept online payments via UPI (GPay, PhonePe, Paytm), Cards (Visa, Master, RuPay), Net Banking & Wallets.</div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="razorpay_enabled" name="razorpay_enabled" value="1" <?php echo $razorpay_active ? 'checked' : ''; ?> style="width: 2.5em; height: 1.3em;">
                                <label class="form-check-label small fw-bold ms-1" for="razorpay_enabled">Enable Online Payments</label>
                            </div>
                        </div>

                        <!-- Razorpay Features / Info Bar -->
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <span class="badge bg-white text-dark border px-2 py-1"><i class="bi bi-qr-code text-primary me-1"></i> UPI & QR</span>
                                    <span class="badge bg-white text-dark border px-2 py-1"><i class="bi bi-credit-card text-success me-1"></i> Credit & Debit Cards</span>
                                    <span class="badge bg-white text-dark border px-2 py-1"><i class="bi bi-bank text-info me-1"></i> Net Banking</span>
                                    <span class="badge bg-white text-dark border px-2 py-1"><i class="bi bi-wallet2 text-warning me-1"></i> Wallets</span>
                                </div>
                                <a href="https://dashboard.razorpay.com/#/app/keys" target="_blank" rel="noopener noreferrer" class="small text-decoration-none fw-semibold">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> Get API Keys from Razorpay Dashboard
                                </a>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label for="razorpay_mode" class="form-label fw-bold small">Gateway Environment Mode <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="razorpay_mode" name="razorpay_mode">
                                    <option value="test" <?php echo $razorpay_mode_val === 'test' ? 'selected' : ''; ?>>Test / Sandbox Mode (rzp_test_...)</option>
                                    <option value="live" <?php echo $razorpay_mode_val === 'live' ? 'selected' : ''; ?>>Live / Production Mode (rzp_live_...)</option>
                                </select>
                                <div class="form-text small text-muted">Use Test mode during development & testing.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="razorpay_key_id" class="form-label fw-bold small">Razorpay Key ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm font-monospace" id="razorpay_key_id" name="razorpay_key_id" value="<?php echo e($razorpay_kid); ?>" placeholder="rzp_test_xxxxxxxxxx or rzp_live_xxxxxxxxxx">
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="razorpay_key_secret" class="form-label fw-bold small">Razorpay Key Secret <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <input type="password" class="form-control font-monospace" id="razorpay_key_secret" name="razorpay_key_secret" value="<?php echo e($razorpay_ksec); ?>" placeholder="••••••••••••••••">
                                    <button class="btn btn-outline-secondary" type="button" onclick="const p = document.getElementById('razorpay_key_secret'); p.type = p.type === 'password' ? 'text' : 'password'; this.querySelector('i').classList.toggle('bi-eye'); this.querySelector('i').classList.toggle('bi-eye-slash');">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="razorpay_webhook_secret" class="form-label fw-bold small">Webhook Secret (Optional)</label>
                                <input type="password" class="form-control form-control-sm font-monospace" id="razorpay_webhook_secret" name="razorpay_webhook_secret" value="<?php echo e($razorpay_wsec); ?>" placeholder="Optional webhook secret for instant capture callbacks">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-muted">Webhook Callback Endpoint URL</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control font-monospace bg-light" value="<?php echo e($webhook_url); ?>" readonly id="razorpayWebhookUrl">
                                    <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('razorpayWebhookUrl').value); alert('Webhook URL copied!');">
                                        <i class="bi bi-clipboard"></i> Copy
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 6. GOOGLE OAUTH 2.0 TAB -->
                <div class="tab-pane fade" id="tab-google" role="tabpanel" aria-labelledby="tab-google-btn">
                    <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px; border-left: 4px solid #EA4335 !important;">
                        <div class="d-flex align-items-center justify-content-between pb-2 border-bottom mb-3">
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-google text-danger me-2"></i> Google OAuth 2.0 (1-Click Customer Login)
                            </h5>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="google_auth_enabled" name="google_auth_enabled" value="1" <?php echo $google_enabled ? 'checked' : ''; ?> style="width: 2.5em; height: 1.3em;">
                            </div>
                        </div>

                        <div class="bg-light p-3 rounded mb-3">
                            <div class="small text-muted mb-2">
                                <strong>Authorized Redirect URI</strong> (Copy and paste into your Google Cloud Console OAuth 2.0 Client credentials):
                            </div>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace" value="<?php echo e($redirect_uri); ?>" readonly id="oauthRedirectUri">
                                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('oauthRedirectUri').value); alert('Redirect URI copied to clipboard!');">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="google_client_id" class="form-label fw-bold small">Google Client ID</label>
                                <input type="text" class="form-control font-monospace" id="google_client_id" name="google_client_id" value="<?php echo e($google_cid); ?>" placeholder="e.g. 1234567890-xxx.apps.googleusercontent.com">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="google_client_secret" class="form-label fw-bold small">Google Client Secret</label>
                                <input type="password" class="form-control font-monospace" id="google_client_secret" name="google_client_secret" value="<?php echo e($google_csecret); ?>" placeholder="••••••••••••••••">
                            </div>
                        </div>
                    </div>
                </div>

            </div> <!-- /tab-content -->

            <!-- Unified Save Button -->
            <div class="d-flex gap-3 mt-4 pt-2">
                <button type="submit" class="btn btn-brand px-4 py-2 fw-semibold">
                    <i class="bi bi-check2-circle me-1"></i> Save All Settings
                </button>
                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Navigation menu badge counter
    const checkboxes = document.querySelectorAll('.nav-menu-checkbox');
    const badge = document.getElementById('selectedCountBadge');

    function updateNavBadge() {
        const checkedCount = document.querySelectorAll('.nav-menu-checkbox:checked').length;
        if (badge) {
            badge.textContent = checkedCount + ' Selected';
            if (checkedCount > 4) {
                badge.className = 'badge bg-warning text-dark rounded-pill fw-normal';
            } else {
                badge.className = 'badge bg-primary rounded-pill fw-normal';
            }
        }
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateNavBadge);
    });

    // 2. Tab switcher helper (works with and without Bootstrap JS initialized)
    function activateTab(targetSelector) {
        if (!targetSelector) return;
        const targetBtn = document.querySelector(`#settingsTabs button[data-bs-target="${targetSelector}"]`);
        const targetPane = document.querySelector(targetSelector);
        
        if (targetBtn && targetPane) {
            // Remove active state from all tab buttons & panes
            document.querySelectorAll('#settingsTabs button').forEach(b => {
                b.classList.remove('active');
                b.setAttribute('aria-selected', 'false');
            });
            document.querySelectorAll('#settingsTabContent .tab-pane').forEach(p => {
                p.classList.remove('show', 'active');
            });

            // Activate chosen button & pane
            targetBtn.classList.add('active');
            targetBtn.setAttribute('aria-selected', 'true');
            targetPane.classList.add('show', 'active');

            // Try bootstrap API if available
            if (window.bootstrap && typeof bootstrap.Tab !== 'undefined') {
                try {
                    const bsTab = bootstrap.Tab.getOrCreateInstance(targetBtn);
                    bsTab.show();
                } catch(e) {}
            }
        }
    }

    // Attach click listeners to all tab buttons
    const tabButtons = document.querySelectorAll('#settingsTabs button[data-bs-toggle="pill"]');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-bs-target');
            activateTab(targetId);
            if (targetId) {
                const cleanHash = targetId.replace('#tab-', '#');
                history.replaceState(null, null, cleanHash);
            }
        });
    });

    // Handle deep-linking / URL hashes on page load (e.g., settings.php#razorpay)
    const currentHash = window.location.hash;
    if (currentHash) {
        const cleaned = currentHash.replace('#sec-', '').replace('#tab-', '').replace('#', '');
        const directTarget = `#tab-${cleaned}`;
        activateTab(directTarget);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
