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
                'company_gstin',
                'company_reg_number',
                'company_pan',
                'invoice_authorized_signatory',
                'invoice_terms_notes',
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

$company_legal_name_val = get_setting('company_legal_name', 'St. Joseph Garments');
$company_gstin_val = get_setting('company_gstin', '');
$company_reg_number_val = get_setting('company_reg_number', '');
$company_pan_val = get_setting('company_pan', '');
$company_address_val = get_setting('address_company', 'St. Joseph Garments, Phase 2, Industrial Area, Bangalore, Karnataka - 560001');
$invoice_signatory_val = get_setting('invoice_authorized_signatory', 'St. Joseph Garments');
$invoice_notes_val = get_setting('invoice_terms_notes', "1. Standard exchange policies apply within 7 days of delivery.\n2. Lingerie items must remain unworn with tags attached.\n3. All disputes subject to local jurisdiction.");

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
            <div class="d-flex gap-2">
                <a href="migrate.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                    <i class="bi bi-database-gear me-1"></i> DB Migrations
                </a>
                <a href="pages.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-file-earmark-richtext me-1"></i> Manage CMS Pages
                </a>
            </div>
        </div>
    </div>
    <div class="col-12">
        <form action="settings.php" method="POST" id="settingsForm">
            <?php echo csrf_field(); ?>

            <!-- Interactive Settings Tabs Navigation -->
            <ul class="nav nav-pills gap-2 mb-4 bg-white p-2 rounded-4 shadow-sm border flex-nowrap overflow-x-auto" id="settingsTabs" role="tablist" style="scrollbar-width: thin; -webkit-overflow-scrolling: touch;">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active rounded-pill px-3 py-2 fw-semibold text-nowrap" id="tab-general-btn" data-bs-toggle="pill" data-bs-target="#tab-general" type="button" role="tab" aria-controls="tab-general" aria-selected="true">
                        <i class="bi bi-shop me-1"></i> General & Tax
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold text-primary text-nowrap" id="tab-company-btn" data-bs-toggle="pill" data-bs-target="#tab-company" type="button" role="tab" aria-controls="tab-company" aria-selected="false">
                        <i class="bi bi-building me-1"></i> Company & Invoicing
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold text-warning text-nowrap" id="tab-notice-btn" data-bs-toggle="pill" data-bs-target="#tab-notice" type="button" role="tab" aria-controls="tab-notice" aria-selected="false">
                        <i class="bi bi-megaphone-fill me-1"></i> Pre-Launch Banner
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold text-nowrap" id="tab-social-btn" data-bs-toggle="pill" data-bs-target="#tab-social" type="button" role="tab" aria-controls="tab-social" aria-selected="false">
                        <i class="bi bi-share me-1"></i> Social Profiles
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold text-nowrap" id="tab-navbar-btn" data-bs-toggle="pill" data-bs-target="#tab-navbar" type="button" role="tab" aria-controls="tab-navbar" aria-selected="false">
                        <i class="bi bi-menu-button-wide me-1"></i> Navigation Menu
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold text-nowrap" id="tab-shipping-btn" data-bs-toggle="pill" data-bs-target="#tab-shipping" type="button" role="tab" aria-controls="tab-shipping" aria-selected="false">
                        <i class="bi bi-truck me-1"></i> Shipping & Logistics
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold text-primary text-nowrap" id="tab-razorpay-btn" data-bs-toggle="pill" data-bs-target="#tab-razorpay" type="button" role="tab" aria-controls="tab-razorpay" aria-selected="false">
                        <i class="bi bi-credit-card-2-front-fill me-1"></i> Razorpay Payments
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-3 py-2 fw-semibold text-danger text-nowrap" id="tab-google-btn" data-bs-toggle="pill" data-bs-target="#tab-google" type="button" role="tab" aria-controls="tab-google" aria-selected="false">
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
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo e(SITE_NAME); ?>" required placeholder="Website Platform Name *">
                                    <label for="site_name">Website Platform Name <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="site_email" name="site_email" value="<?php echo e(SITE_EMAIL); ?>" required placeholder="Support Email Address *">
                                    <label for="site_email">Support Email Address <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="site_phone" name="site_phone" value="<?php echo e(SITE_PHONE); ?>" required placeholder="Contact / Helpline Phone *">
                                    <label for="site_phone">Contact / Helpline Phone <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-12 col-md-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" value="<?php echo e(CURRENCY_SYMBOL === '262145' || is_numeric(CURRENCY_SYMBOL) ? '₹' : CURRENCY_SYMBOL); ?>" required placeholder="Currency Symbol *">
                                    <label for="currency_symbol">Currency Symbol <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-12 col-md-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="currency_code" name="currency_code" value="<?php echo e(CURRENCY_CODE); ?>" required placeholder="Currency Code *">
                                    <label for="currency_code">Currency Code <span class="text-danger">*</span></label>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="form-floating">
                                    <input type="number" step="0.01" class="form-control" id="default_gst_rate" name="default_gst_rate" value="<?php echo e(DEFAULT_GST_RATE); ?>" required placeholder="Default GST Rate (%)">
                                    <label for="default_gst_rate">Default GST Rate (%)</label>
                                </div>
                                <div class="form-text small text-muted">Applied on order checkout & invoices.</div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="return_window_days" name="return_window_days" value="<?php echo e(RETURN_WINDOW_DAYS); ?>" required min="1" max="30" placeholder="Return Window (Days)">
                                    <label for="return_window_days">Return Window (Days)</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="support_hours" name="support_hours" value="<?php echo e(SUPPORT_HOURS); ?>" required placeholder="Customer Support Hours">
                                    <label for="support_hours">Customer Support Hours</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <div class="fw-bold small text-dark"><i class="bi bi-building me-1 text-primary"></i> Company Legal Entity & Invoicing Configuration</div>
                                        <div class="text-muted small">Owner company on invoices: <strong><?php echo e($company_legal_name_val); ?></strong> (Parent entity owning <?php echo e(SITE_NAME); ?>).</div>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="document.getElementById('tab-company-btn').click();">
                                        Configure Company & Invoicing &rarr;
                                    </button>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" id="footer_about_text" name="footer_about_text" placeholder="Storefront Footer Brand Bio / About Summary" style="height: 100px;" required><?php echo e(FOOTER_ABOUT_TEXT); ?></textarea>
                                    <label for="footer_about_text">Storefront Footer Brand Bio / About Summary</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. COMPANY LEGAL ENTITY & INVOICING TAB -->
                <div class="tab-pane fade" id="tab-company" role="tabpanel" aria-labelledby="tab-company-btn">
                    <div class="card border-0 shadow-sm p-4 bg-white mb-4" style="border-radius: 12px; border-left: 4px solid #0d6efd !important;">
                        <div class="d-flex align-items-center justify-content-between pb-2 border-bottom mb-3 flex-wrap gap-2">
                            <div>
                                <h5 class="fw-bold mb-1 text-dark">
                                    <i class="bi bi-building text-primary me-2"></i> Company Legal Entity & Invoice Details
                                </h5>
                                <p class="text-muted small mb-0">Configure the registered legal entity (e.g. <strong>St. Joseph Garments</strong>) that owns the <strong><?php echo e(SITE_NAME); ?></strong> brand and issues customer tax invoices.</p>
                            </div>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill">
                                <i class="bi bi-receipt me-1"></i> Tax Invoice Settings
                            </span>
                        </div>

                        <!-- Brand vs Company Notice -->
                        <div class="alert alert-info d-flex align-items-start gap-2 py-2 px-3 small rounded-3 mb-4">
                            <i class="bi bi-info-circle-fill fs-5 mt-1 text-primary"></i>
                            <div>
                                <strong>Brand Identity vs. Legal Owner:</strong> 
                                <strong><?php echo e(SITE_NAME); ?></strong> is your consumer brand, website, and storefront identity. 
                                <strong>St. Joseph Garments</strong> is the legal company/proprietorship that owns Pookky and appears as the registered seller on all official Tax Invoices, payment receipts, and statutory filings.
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="company_legal_name" name="company_legal_name" value="<?php echo e($company_legal_name_val); ?>" required placeholder="Company Legal Entity Name *">
                                    <label for="company_legal_name">Company Legal Entity Name (Owner Entity) <span class="text-danger">*</span></label>
                                </div>
                                <div class="form-text small text-muted">Printed on customer tax invoices instead of the storefront brand name.</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="company_gstin" name="company_gstin" value="<?php echo e($company_gstin_val); ?>" placeholder="GSTIN / Tax ID Number">
                                    <label for="company_gstin">GSTIN / Tax Identification Number</label>
                                </div>
                                <div class="form-text small text-muted">Printed on the tax invoice header for GST compliance.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="company_pan" name="company_pan" value="<?php echo e($company_pan_val); ?>" placeholder="Company PAN Number">
                                    <label for="company_pan">Company PAN Number</label>
                                </div>
                                <div class="form-text small text-muted">Optional: printed on invoices for high-value orders.</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="company_reg_number" name="company_reg_number" value="<?php echo e($company_reg_number_val); ?>" placeholder="Company Registration / CIN / MSME No.">
                                    <label for="company_reg_number">Company Registration / CIN / MSME No.</label>
                                </div>
                                <div class="form-text small text-muted">Optional registered business registration number.</div>
                            </div>

                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" id="address_company" name="address_company" placeholder="Company Headquarters / Registered Billing Address *" style="height: 95px;" required><?php echo e($company_address_val); ?></textarea>
                                    <label for="address_company">Company Headquarters / Registered Billing Address <span class="text-danger">*</span></label>
                                </div>
                                <div class="form-text small text-muted">Official registered business address printed on all customer invoice sheets.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="invoice_authorized_signatory" name="invoice_authorized_signatory" value="<?php echo e($invoice_signatory_val); ?>" placeholder="Invoice Authorized Signatory Text">
                                    <label for="invoice_authorized_signatory">Invoice Authorized Signatory Text</label>
                                </div>
                                <div class="form-text small text-muted">Printed under "Authorized Signatory" at the bottom right of the invoice.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control bg-light" id="storefront_brand_ref" value="<?php echo e(SITE_NAME); ?>" readonly placeholder="Storefront Brand Reference">
                                    <label for="storefront_brand_ref">Storefront Brand Reference</label>
                                </div>
                                <div class="form-text small text-muted">The consumer brand name (configured in the General & Tax tab).</div>
                            </div>

                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" id="invoice_terms_notes" name="invoice_terms_notes" placeholder="Invoice Footer Terms & Conditions" style="height: 100px;"><?php echo e($invoice_notes_val); ?></textarea>
                                    <label for="invoice_terms_notes">Invoice Footer Terms & Conditions / Important Information</label>
                                </div>
                                <div class="form-text small text-muted">Standard exchange and jurisdiction terms printed on customer invoices.</div>
                            </div>
                        </div>

                        <!-- Live Invoice Preview Box -->
                        <div class="mt-4 pt-3 border-top">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold text-muted small text-uppercase mb-0"><i class="bi bi-eye me-1"></i> Live Invoice Header Preview</h6>
                                <span class="badge bg-secondary-subtle text-secondary small">Real-time Preview</span>
                            </div>
                            <div class="p-3 rounded-3 bg-light border d-flex justify-content-between align-items-start flex-wrap gap-3">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <img src="../assets/images/logo.png" alt="Logo" height="24">
                                        <span class="badge bg-white text-dark border">Brand: <?php echo e(SITE_NAME); ?></span>
                                    </div>
                                    <h5 class="fw-bold text-dark mb-1" id="previewLegalName"><?php echo e($company_legal_name_val); ?></h5>
                                    <div class="small text-muted" id="previewAddress"><?php echo nl2br(e($company_address_val)); ?></div>
                                    <div class="small text-muted mt-1" id="previewTaxMeta">
                                        <?php if (!empty($company_gstin_val)): ?><span class="me-2" id="previewGstinTag"><strong>GSTIN:</strong> <?php echo e($company_gstin_val); ?></span><?php endif; ?>
                                        <?php if (!empty($company_pan_val)): ?><span id="previewPanTag"><strong>PAN:</strong> <?php echo e($company_pan_val); ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-white text-secondary border px-3 py-2 fw-bold">TAX INVOICE</span>
                                    <div class="small text-muted mt-1">Invoice: <strong>PKY-SAMPLE-01</strong></div>
                                    <div class="small text-muted">Date: <?php echo date('d-M-Y'); ?></div>
                                </div>
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
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="site_notice_badge" name="site_notice_badge" value="<?php echo e($notice_badge); ?>" placeholder="e.g. PRE-LAUNCH TESTING">
                                    <label for="site_notice_badge">Pill Badge Text</label>
                                </div>
                                <div class="form-text small text-muted">Highlighted badge label displayed at the front.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" id="site_notice_type" name="site_notice_type">
                                        <option value="amber" <?php echo $notice_type === 'amber' ? 'selected' : ''; ?>>Amber Gold (Testing & Beta - Recommended)</option>
                                        <option value="brand" <?php echo $notice_type === 'brand' ? 'selected' : ''; ?>>Rose Brand (Soft Pink & Berry)</option>
                                        <option value="dark" <?php echo $notice_type === 'dark' ? 'selected' : ''; ?>>Charcoal Dark (High Contrast)</option>
                                        <option value="info" <?php echo $notice_type === 'info' ? 'selected' : ''; ?>>Azure Blue (Notice & Info)</option>
                                    </select>
                                    <label for="site_notice_type">Visual Color Theme</label>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="site_notice_dismissible" name="site_notice_dismissible" value="1" <?php echo $notice_dismissible ? 'checked' : ''; ?>>
                                        <label class="form-check-label small fw-semibold" for="site_notice_dismissible">Allow visitors to dismiss banner (via X button)</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" id="site_notice_text" name="site_notice_text" placeholder="Enter message to display..." style="height: 100px;"><?php echo e($notice_text); ?></textarea>
                                    <label for="site_notice_text">Banner Message Text <span class="text-danger">*</span></label>
                                </div>
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
                                <div class="form-floating">
                                    <input type="url" class="form-control" id="social_facebook" name="social_facebook" value="<?php echo e(SOCIAL_FACEBOOK); ?>" placeholder="https://facebook.com/yourbrand">
                                    <label for="social_facebook"><i class="bi bi-facebook text-primary me-1"></i> Facebook URL</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="url" class="form-control" id="social_instagram" name="social_instagram" value="<?php echo e(SOCIAL_INSTAGRAM); ?>" placeholder="https://instagram.com/yourbrand">
                                    <label for="social_instagram"><i class="bi bi-instagram text-danger me-1"></i> Instagram URL</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="url" class="form-control" id="social_pinterest" name="social_pinterest" value="<?php echo e(SOCIAL_PINTEREST); ?>" placeholder="https://pinterest.com/yourbrand">
                                    <label for="social_pinterest"><i class="bi bi-pinterest text-danger me-1"></i> Pinterest URL</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="url" class="form-control" id="social_youtube" name="social_youtube" value="<?php echo e(SOCIAL_YOUTUBE); ?>" placeholder="https://youtube.com/@yourbrand">
                                    <label for="social_youtube"><i class="bi bi-youtube text-danger me-1"></i> YouTube URL</label>
                                </div>
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
                                <div class="form-floating">
                                    <input type="number" step="0.01" class="form-control" id="shipping_flat_rate" name="shipping_flat_rate" value="<?php echo e(SHIPPING_FLAT_RATE); ?>" placeholder="0.00" required>
                                    <label for="shipping_flat_rate">Flat Shipping Charges (<?php echo e(CURRENCY_SYMBOL === '262145' || is_numeric(CURRENCY_SYMBOL) ? '₹' : CURRENCY_SYMBOL); ?>) <span class="text-danger">*</span></label>
                                </div>
                                <div class="form-text small text-muted">Applied to shopping cart subtotal when below the free shipping threshold.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="number" step="0.01" class="form-control" id="free_shipping_threshold" name="free_shipping_threshold" value="<?php echo e(FREE_SHIPPING_THRESHOLD); ?>" placeholder="0.00" required>
                                    <label for="free_shipping_threshold">Free Shipping Order Threshold (<?php echo e(CURRENCY_SYMBOL === '262145' || is_numeric(CURRENCY_SYMBOL) ? '₹' : CURRENCY_SYMBOL); ?>) <span class="text-danger">*</span></label>
                                </div>
                                <div class="form-text small text-muted">Orders with subtotal equal to or above this value qualify for free delivery automatically.</div>
                            </div>

                            <!-- Delivery Window Estimate -->
                            <div class="col-12 col-md-3">
                                <div class="form-floating">
                                    <input type="number" min="1" max="30" class="form-control" id="est_delivery_min_days" name="est_delivery_min_days" value="<?php echo e($est_min); ?>" placeholder="3" required>
                                    <label for="est_delivery_min_days">Min Delivery Days</label>
                                </div>
                                <div class="form-text small text-muted">e.g. 3 days</div>
                            </div>

                            <div class="col-12 col-md-3">
                                <div class="form-floating">
                                    <input type="number" min="1" max="60" class="form-control" id="est_delivery_max_days" name="est_delivery_max_days" value="<?php echo e($est_max); ?>" placeholder="5" required>
                                    <label for="est_delivery_max_days">Max Delivery Days</label>
                                </div>
                                <div class="form-text small text-muted">e.g. 5 days</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="courier_partner_name" name="courier_partner_name" value="<?php echo e($courier_names); ?>" placeholder="e.g. BlueDart, Delhivery, DTDC, Xpressbees">
                                    <label for="courier_partner_name">Logistics & Courier Partners</label>
                                </div>
                                <div class="form-text small text-muted">Displayed in policy pages, checkout information, and order tracking.</div>
                            </div>

                            <!-- Discreet Packaging Switch -->
                            <div class="col-12 col-md-6 pt-2">
                                <div class="p-3 bg-light rounded-3 border h-100 d-flex align-items-center justify-content-between">
                                    <div>
                                        <h6 class="fw-bold mb-1 text-dark"><i class="bi bi-box-seam me-1 text-brand"></i> Discreet Packaging Guarantee</h6>
                                        <div class="small text-muted">Show 100% unmarked discreet delivery assurance across checkout.</div>
                                    </div>
                                    <div class="form-check form-switch ms-3">
                                        <input class="form-check-input" type="checkbox" role="switch" id="discreet_packaging_enabled" name="discreet_packaging_enabled" value="1" <?php echo $discreet_enabled ? 'checked' : ''; ?> style="width: 2.5em; height: 1.3em;">
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
                                        <div class="form-check form-switch ms-3">
                                            <input class="form-check-input" type="checkbox" role="switch" id="cod_enabled" name="cod_enabled" value="1" <?php echo $cod_active ? 'checked' : ''; ?> style="width: 2.5em; height: 1.3em;">
                                        </div>
                                    </div>
                                    <div class="mt-2 pt-2 border-top">
                                        <div class="form-floating">
                                            <input type="number" step="0.01" class="form-control" id="cod_shipping_charge" name="cod_shipping_charge" value="<?php echo e($cod_charge); ?>" placeholder="0 for Free COD">
                                            <label for="cod_shipping_charge">COD Extra Fee (<?php echo e(CURRENCY_SYMBOL === '262145' || is_numeric(CURRENCY_SYMBOL) ? '₹' : CURRENCY_SYMBOL); ?>)</label>
                                        </div>
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
                                <div class="form-floating">
                                    <select class="form-select" id="razorpay_mode" name="razorpay_mode">
                                        <option value="test" <?php echo $razorpay_mode_val === 'test' ? 'selected' : ''; ?>>Test / Sandbox Mode (rzp_test_...)</option>
                                        <option value="live" <?php echo $razorpay_mode_val === 'live' ? 'selected' : ''; ?>>Live / Production Mode (rzp_live_...)</option>
                                    </select>
                                    <label for="razorpay_mode">Gateway Mode <span class="text-danger">*</span></label>
                                </div>
                                <div class="form-text small text-muted">Use Test mode during development & testing.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control font-monospace" id="razorpay_key_id" name="razorpay_key_id" value="<?php echo e($razorpay_kid); ?>" placeholder="rzp_test_xxxxxxxxxx">
                                    <label for="razorpay_key_id">Razorpay Key ID <span class="text-danger">*</span></label>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="input-group">
                                    <div class="form-floating flex-grow-1">
                                        <input type="password" class="form-control font-monospace" id="razorpay_key_secret" name="razorpay_key_secret" value="<?php echo e($razorpay_ksec); ?>" placeholder="••••••••••••••••">
                                        <label for="razorpay_key_secret">Razorpay Key Secret <span class="text-danger">*</span></label>
                                    </div>
                                    <button class="btn btn-outline-secondary px-3" type="button" onclick="const p = document.getElementById('razorpay_key_secret'); p.type = p.type === 'password' ? 'text' : 'password'; this.querySelector('i').classList.toggle('bi-eye'); this.querySelector('i').classList.toggle('bi-eye-slash');" aria-label="Toggle password visibility">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="password" class="form-control font-monospace" id="razorpay_webhook_secret" name="razorpay_webhook_secret" value="<?php echo e($razorpay_wsec); ?>" placeholder="Webhook secret">
                                    <label for="razorpay_webhook_secret">Webhook Secret (Optional)</label>
                                </div>
                                <div class="form-text small text-muted">Optional webhook secret for instant capture callbacks.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="input-group">
                                    <div class="form-floating flex-grow-1">
                                        <input type="text" class="form-control font-monospace bg-light" value="<?php echo e($webhook_url); ?>" readonly id="razorpayWebhookUrl" placeholder="Webhook URL">
                                        <label for="razorpayWebhookUrl">Webhook Callback Endpoint URL</label>
                                    </div>
                                    <button class="btn btn-outline-secondary px-3" type="button" onclick="navigator.clipboard.writeText(document.getElementById('razorpayWebhookUrl').value); alert('Webhook URL copied!');">
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
                            <div class="input-group">
                                <div class="form-floating flex-grow-1">
                                    <input type="text" class="form-control font-monospace bg-light" value="<?php echo e($redirect_uri); ?>" readonly id="oauthRedirectUri" placeholder="OAuth Redirect URI">
                                    <label for="oauthRedirectUri">Authorized Redirect URI</label>
                                </div>
                                <button class="btn btn-outline-secondary px-3" type="button" onclick="navigator.clipboard.writeText(document.getElementById('oauthRedirectUri').value); alert('Redirect URI copied to clipboard!');">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control font-monospace" id="google_client_id" name="google_client_id" value="<?php echo e($google_cid); ?>" placeholder="e.g. 1234567890-xxx.apps.googleusercontent.com">
                                    <label for="google_client_id">Google Client ID</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input type="password" class="form-control font-monospace" id="google_client_secret" name="google_client_secret" value="<?php echo e($google_csecret); ?>" placeholder="••••••••••••••••">
                                    <label for="google_client_secret">Google Client Secret</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div> <!-- /tab-content -->

            <!-- Unified Save Button -->
            <div class="d-flex flex-wrap gap-2 mt-4 pt-2">
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

    // 3. Company & Invoice Live Preview Reactive Synchronization
    const inputLegalName = document.getElementById('company_legal_name');
    const inputAddress = document.getElementById('address_company');
    const inputGstin = document.getElementById('company_gstin');
    const inputPan = document.getElementById('company_pan');
    const previewLegalName = document.getElementById('previewLegalName');
    const previewAddress = document.getElementById('previewAddress');
    const previewTaxMeta = document.getElementById('previewTaxMeta');

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function updateCompanyPreview() {
        if (inputLegalName && previewLegalName) {
            previewLegalName.textContent = inputLegalName.value.trim() || 'St. Joseph Garments';
        }
        if (inputAddress && previewAddress) {
            previewAddress.innerHTML = escapeHtml(inputAddress.value.trim()).replace(/\n/g, '<br>');
        }
        if (previewTaxMeta) {
            const gstin = inputGstin ? inputGstin.value.trim() : '';
            const pan = inputPan ? inputPan.value.trim() : '';
            let html = '';
            if (gstin) {
                html += `<span class="me-2" id="previewGstinTag"><strong>GSTIN:</strong> ${escapeHtml(gstin)}</span>`;
            }
            if (pan) {
                html += `<span id="previewPanTag"><strong>PAN:</strong> ${escapeHtml(pan)}</span>`;
            }
            previewTaxMeta.innerHTML = html;
        }
    }

    [inputLegalName, inputAddress, inputGstin, inputPan].forEach(el => {
        if (el) el.addEventListener('input', updateCompanyPreview);
    });

    // 4. Handle deep-linking / URL hashes on page load (e.g., settings.php#company or settings.php#razorpay)
    const currentHash = window.location.hash;
    if (currentHash) {
        const cleaned = currentHash.replace('#sec-', '').replace('#tab-', '').replace('#', '');
        const directTarget = `#tab-${cleaned}`;
        activateTab(directTarget);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
