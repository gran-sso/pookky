<?php
// admin/migrate.php
// Web-based database migration utility for Hostinger / production deployments

$page_title = 'Database Migrations';
$active_menu = 'settings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Super Admin permission required
require_admin_permission('all');

$messages = [];
$status_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $messages[] = ['type' => 'danger', 'text' => 'Invalid CSRF security token.'];
    } else {
        try {
            // 1. New Tables
            $tables = [
                'faqs' => "CREATE TABLE IF NOT EXISTS `faqs` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `question` VARCHAR(255) NOT NULL,
                    `answer` TEXT NOT NULL,
                    `sort_order` INT DEFAULT 0,
                    `status` TINYINT DEFAULT 1,
                    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                'email_otps' => "CREATE TABLE IF NOT EXISTS `email_otps` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `email` VARCHAR(100) NOT NULL,
                    `otp_code` VARCHAR(10) NOT NULL,
                    `purpose` VARCHAR(50) NOT NULL DEFAULT 'registration',
                    `expires_at` DATETIME NOT NULL,
                    `is_used` TINYINT DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_email_purpose` (`email`,`purpose`,`is_used`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                'pending_checkouts' => "CREATE TABLE IF NOT EXISTS `pending_checkouts` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `razorpay_order_id` VARCHAR(100) NOT NULL UNIQUE,
                    `order_number` VARCHAR(50) NOT NULL,
                    `customer_id` INT DEFAULT NULL,
                    `checkout_data` LONGTEXT NOT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_order_number` (`order_number`),
                    KEY `idx_customer_id` (`customer_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                'fabrics' => "CREATE TABLE IF NOT EXISTS `fabrics` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(100) NOT NULL UNIQUE,
                    `description` TEXT,
                    `status` TINYINT DEFAULT 1 COMMENT '1=Active, 0=Inactive',
                    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                'product_fabrics' => "CREATE TABLE IF NOT EXISTS `product_fabrics` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `product_id` INT NOT NULL,
                    `fabric_id` INT NOT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `unique_product_fabric` (`product_id`,`fabric_id`),
                    KEY `idx_fabric_id` (`fabric_id`),
                    CONSTRAINT `fk_pf_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_pf_fabric` FOREIGN KEY (`fabric_id`) REFERENCES `fabrics` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                'combo_components' => "CREATE TABLE IF NOT EXISTS `combo_components` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `combo_product_id` INT NOT NULL,
                    `component_title` VARCHAR(150) NOT NULL,
                    `allowed_category_id` INT DEFAULT NULL,
                    `allowed_product_id` INT DEFAULT NULL,
                    `default_quantity` INT DEFAULT 1,
                    `sort_order` INT DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_combo_product` (`combo_product_id`),
                    KEY `idx_allowed_category` (`allowed_category_id`),
                    KEY `idx_allowed_product` (`allowed_product_id`),
                    CONSTRAINT `fk_cc_combo` FOREIGN KEY (`combo_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_cc_category` FOREIGN KEY (`allowed_category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
                    CONSTRAINT `fk_cc_product` FOREIGN KEY (`allowed_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            ];

            foreach ($tables as $tName => $tSql) {
                $pdo->exec($tSql);
                $messages[] = ['type' => 'success', 'text' => "Table checked / verified: `{$tName}`"];
            }

            // 2. Add columns if missing
            $columns = [
                ['customers', 'google_id', 'VARCHAR(100) DEFAULT NULL UNIQUE'],
                ['customers', 'auth_provider', 'VARCHAR(20) DEFAULT "local"'],
                ['customers', 'avatar_url', 'VARCHAR(255) DEFAULT NULL'],
                ['customers', 'email_verified', 'TINYINT DEFAULT 1 COMMENT "1=Verified, 0=Unverified"'],
                ['customers', 'email_verified_at', 'TIMESTAMP NULL DEFAULT NULL'],
                ['banners', 'tagline', 'VARCHAR(100) DEFAULT "Featured Collection"'],
                ['banners', 'subtitle', 'VARCHAR(255) DEFAULT "Elegant designs, soft fabrics, and customizable sizing matrices."'],
                ['banners', 'mobile_image', 'VARCHAR(255) DEFAULT NULL'],
                ['banners', 'btn_text', 'VARCHAR(100) DEFAULT "Shop Collection"'],
                ['cart', 'combo_product_id', 'INT DEFAULT NULL'],
                ['cart', 'combo_config', 'TEXT DEFAULT NULL'],
                ['order_items', 'combo_product_id', 'INT DEFAULT NULL'],
                ['order_items', 'combo_config', 'TEXT DEFAULT NULL'],
                ['products', 'product_type', 'ENUM("single", "combo") NOT NULL DEFAULT "single"'],
                ['orders', 'transaction_id', 'VARCHAR(100) DEFAULT NULL']
            ];

            foreach ($columns as [$tbl, $col, $def]) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                $chk->execute([$tbl, $col]);
                if ((int)$chk->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `{$col}` {$def}");
                    $messages[] = ['type' => 'success', 'text' => "Added column: `{$tbl}`.`{$col}`"];
                } else {
                    $messages[] = ['type' => 'info', 'text' => "Column already exists: `{$tbl}`.`{$col}`"];
                }
            }

            // 3. Nullability adjustments
            $pdo->exec("ALTER TABLE `customers` MODIFY `password` VARCHAR(255) DEFAULT NULL");
            $pdo->exec("ALTER TABLE `cart` MODIFY `variant_id` INT DEFAULT NULL");
            $pdo->exec("ALTER TABLE `order_items` MODIFY `variant_id` INT DEFAULT NULL");
            $messages[] = ['type' => 'success', 'text' => "Adjusted column nullability for OAuth & Combos"];

            // 4. Default settings keys
            $default_settings = [
                'site_name' => 'Pookky',
                'site_email' => 'info@pookky.com',
                'site_phone' => '+91 98765 43210',
                'currency_symbol' => '₹',
                'currency_code' => 'INR',
                'shipping_flat_rate' => '99.00',
                'free_shipping_threshold' => '999.00',
                'default_gst_rate' => '18.00',
                'address_company' => 'St. Joseph Garments, Phase 2, Industrial Area, Bangalore, Karnataka - 560001',
                'company_legal_name' => 'St. Joseph Garments',
                'company_gstin' => '',
                'company_reg_number' => '',
                'company_pan' => '',
                'invoice_authorized_signatory' => 'St. Joseph Garments',
                'invoice_terms_notes' => "1. Standard exchange policies apply within 7 days of delivery.\n2. Lingerie items must remain unworn with tags attached.\n3. All disputes subject to local jurisdiction.",
                'google_client_id' => '',
                'google_client_secret' => '',
                'google_auth_enabled' => '0',
                'site_notice_enabled' => '0',
                'site_notice_badge' => 'SPECIAL OFFER',
                'site_notice_text' => 'Welcome to Pookky! Enjoy free shipping on all orders above ₹999.',
                'site_notice_type' => 'rose',
                'site_notice_dismissible' => '1',
                'cod_enabled' => '1',
                'cod_shipping_charge' => '0',
                'courier_partner_name' => 'BlueDart, Delhivery, DTDC, Xpressbees',
                'discreet_packaging_enabled' => '1',
                'est_delivery_min_days' => '3',
                'est_delivery_max_days' => '5',
                'footer_about_text' => 'Pookky is a leading ladies\' innerwear brand dedicated to delivering luxury, comfort, and perfect fit. We design and manufacture premium lingerie using the finest fabrics for the modern woman.',
                'home_tagline_categories' => 'Selected Collection Blocks',
                'home_tagline_featured' => 'Exclusive Picks of the Season',
                'home_tagline_new_arrivals' => 'Fresh off the Looms',
                'home_tagline_best_sellers' => 'Customer Favorites',
                'nav_menu_categories' => '',
                'razorpay_enabled' => '0',
                'razorpay_key_id' => '',
                'razorpay_key_secret' => '',
                'razorpay_mode' => 'test',
                'razorpay_webhook_secret' => '',
                'return_window_days' => '7',
                'social_facebook' => 'https://facebook.com',
                'social_instagram' => 'https://instagram.com',
                'social_pinterest' => 'https://pinterest.com',
                'social_youtube' => 'https://youtube.com',
                'support_hours' => 'Monday to Saturday, 9:00 AM to 6:00 PM'
            ];

            $stmt_set = $pdo->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
            foreach ($default_settings as $k => $v) {
                $stmt_set->execute([$k, $v]);
            }
            $messages[] = ['type' => 'success', 'text' => "Verified all system configuration keys in settings"];

            // 5. Default FAQs seed
            $faqs = [
                [1, 'How do I find my perfect bra size?', 'Refer to our detailed <a href="size-guide.php" class="text-rose fw-bold">Size Guide page</a>. You will need a soft tape measure to record your underbust (band size) and overbust (cup size). Compare measurements with our tables to select the perfect code.', 1, 1],
                [2, 'What are the shipping charges and delivery timelines?', 'We charge a flat delivery rate of {SHIPPING_FLAT_RATE}. For orders above {FREE_SHIPPING_THRESHOLD}, shipping is completely FREE. Shipments are processed via major courier partners and usually arrive within {EST_DELIVERY_DAYS} business days.', 2, 1],
                [3, 'What is your return and exchange policy?', 'Due to hygiene reasons, we only allow returns or exchanges for allowed cases: damaged product received, wrong product, wrong size received, or missing item in shipment. Requests must be submitted within {RETURN_WINDOW_DAYS} days of delivery.', 3, 1],
                [4, 'Can I cancel my order?', 'Yes, you can cancel your order directly from your Customer Dashboard only while the order status is still marked as "Pending". Once processing or shipping starts, cancellations are disabled.', 4, 1],
                [5, 'What payment methods do you support?', 'We currently support Cash on Delivery (COD) for all orders, as well as online credit card, debit card, UPI, and net banking payments via Razorpay.', 5, 1]
            ];
            $stmt_faq = $pdo->prepare("INSERT IGNORE INTO `faqs` (`id`, `question`, `answer`, `sort_order`, `status`) VALUES (?, ?, ?, ?, ?)");
            foreach ($faqs as $f) {
                $stmt_faq->execute($f);
            }
            $messages[] = ['type' => 'success', 'text' => "Default FAQs verified & preserved"];

            // 6. Default Fabrics seed
            $fabrics = [
                [1, 'Cotton', 'Natural, breathable, and soft cotton fibers ideal for everyday comfort', 1],
                [2, 'Lace', 'Intricate openwork fabric providing romantic, delicate detailing', 1],
                [3, 'Satin', 'Smooth, glossy fabric with a luxurious sheen and graceful drape', 1],
                [4, 'Nylon', 'High-strength, smooth synthetic fabric resistant to shrinking and abrasion', 1],
                [5, 'Elastane', 'Highly elastic polyurethane fiber offering flexible stretch and shape retention', 1],
                [6, 'Spandex', 'Synthetic fiber known for exceptional elasticity and comfort fit', 1],
                [7, 'Polyester', 'Durable, wrinkle-resistant lightweight synthetic fabric', 1],
                [8, 'Silk', 'Premium natural protein fiber with unparalleled soft luster and thermoregulation', 1],
                [9, 'Microfiber', 'Ultra-fine synthetic fiber delivering silky smooth, seamless coverage', 1],
                [10, 'Modal', 'Eco-friendly semi-synthetic cellulose fiber known for silky softness', 1],
                [11, 'Viscose', 'Lightweight, breathable rayon fabric with elegant drape', 1],
                [12, 'Mesh', 'Breathable open-structure netting offering sheer elegance and ventilation', 1]
            ];
            $stmt_fab = $pdo->prepare("INSERT IGNORE INTO `fabrics` (`id`, `name`, `description`, `status`) VALUES (?, ?, ?, ?)");
            foreach ($fabrics as $fb) {
                $stmt_fab->execute($fb);
            }
            $messages[] = ['type' => 'success', 'text' => "Default fabrics catalog verified"];

            $status_type = 'complete';

        } catch (Exception $e) {
            $messages[] = ['type' => 'danger', 'text' => 'Migration error: ' . $e->getMessage()];
            $status_type = 'error';
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center mb-4">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 me-3">
                            <i class="bi bi-database-gear fs-3"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-1">Server Database Migration Utility</h4>
                            <p class="text-muted mb-0">Safely check and sync your MySQL/MariaDB database with all latest application requirements.</p>
                        </div>
                    </div>

                    <?php if ($status_type === 'complete'): ?>
                        <div class="alert alert-success border-0 rounded-4 p-4 mb-4 shadow-sm">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-check-circle-fill fs-4 text-success me-2"></i>
                                <h5 class="fw-bold mb-0 text-success">Database Migration Finished Successfully!</h5>
                            </div>
                            <p class="mb-0 text-muted">All database tables, columns, constraints, and configuration keys are up to date and 100% synchronized.</p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($messages)): ?>
                        <div class="card border bg-light rounded-4 mb-4">
                            <div class="card-header bg-white border-bottom fw-bold py-3">
                                <i class="bi bi-terminal me-2"></i> Migration Execution Log
                            </div>
                            <div class="card-body p-3" style="max-height: 350px; overflow-y: auto; font-family: monospace; font-size: 0.9rem;">
                                <?php foreach ($messages as $msg): ?>
                                    <div class="mb-1 text-<?php echo $msg['type'] === 'success' ? 'success' : ($msg['type'] === 'danger' ? 'danger' : 'secondary'); ?>">
                                        <?php if ($msg['type'] === 'success'): ?>
                                            <i class="bi bi-check2 me-1"></i>
                                        <?php elseif ($msg['type'] === 'danger'): ?>
                                            <i class="bi bi-x-circle me-1"></i>
                                        <?php else: ?>
                                            <i class="bi bi-info-circle me-1"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($msg['text']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="bg-light rounded-4 p-4 border mb-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-shield-check text-success me-2"></i>What this migration checks and applies:</h6>
                        <ul class="mb-0 text-secondary ps-3">
                            <li class="mb-2"><strong>New Tables:</strong> <code>faqs</code>, <code>email_otps</code>, <code>pending_checkouts</code>, <code>combo_components</code>, <code>fabrics</code>, and <code>product_fabrics</code>.</li>
                            <li class="mb-2"><strong>Customers:</strong> Adds <code>google_id</code>, <code>auth_provider</code>, <code>avatar_url</code>, <code>email_verified</code>, and allows nullable password for Google logins.</li>
                            <li class="mb-2"><strong>Banners:</strong> Adds <code>tagline</code>, <code>subtitle</code>, <code>mobile_image</code>, and <code>btn_text</code> for mobile banner slider.</li>
                            <li class="mb-2"><strong>Orders:</strong> Adds <code>transaction_id</code> for Razorpay payment reference tracking.</li>
                            <li class="mb-2"><strong>Cart & Order Items:</strong> Adds <code>combo_product_id</code> and <code>combo_config</code> for multi-item combo sets.</li>
                            <li><strong>Settings:</strong> Ensures all invoicing, Razorpay credentials, and logistics keys are present without overwriting your existing values.</li>
                        </ul>
                    </div>

                    <form method="POST" onsubmit="return confirm('Execute safe database migration now?');">
                        <?php echo csrf_field(); ?>
                        <div class="d-flex align-items-center gap-3">
                            <button type="submit" name="run_migration" value="1" class="btn btn-brand rounded-pill px-4 py-2 fw-semibold">
                                <i class="bi bi-play-fill me-1"></i> Run Migration Now
                            </button>
                            <a href="settings.php" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                                Go to Settings
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
