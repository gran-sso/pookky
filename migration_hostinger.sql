-- ==============================================================================
-- POOKKY / VEESTA E-COMMERCE DATABASE MIGRATION SCRIPT FOR HOSTINGER
-- Safe, non-destructive, and 100% idempotent (safe to run multiple times)
-- Target Server: Hostinger cPanel / hPanel MySQL 8.0+ or MariaDB 10.4+
-- ==============================================================================
-- INSTRUCTIONS FOR HOSTINGER PHPMYADMIN:
-- 1. Log into your Hostinger control panel (hPanel / cPanel).
-- 2. Open phpMyAdmin and click your database name on the left sidebar.
-- 3. Click the "SQL" tab at the top.
-- 4. Paste the entire content of this file and click "Go".
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- STEP 1: CREATE NEW TABLES (IF NOT ALREADY EXISTING)
-- ------------------------------------------------------------------------------

-- 1. FAQs (Frequently Asked Questions)
CREATE TABLE IF NOT EXISTS `faqs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `question` VARCHAR(255) NOT NULL,
  `answer` TEXT NOT NULL,
  `sort_order` INT DEFAULT 0,
  `status` TINYINT DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Email OTPs (for Customer Registration Verification)
CREATE TABLE IF NOT EXISTS `email_otps` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(100) NOT NULL,
  `otp_code` VARCHAR(10) NOT NULL,
  `purpose` VARCHAR(50) NOT NULL DEFAULT 'registration',
  `expires_at` DATETIME NOT NULL,
  `is_used` TINYINT DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_email_purpose` (`email`,`purpose`,`is_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Pending Checkouts (for Razorpay Gateway & Webhook Reconciliation)
CREATE TABLE IF NOT EXISTS `pending_checkouts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `razorpay_order_id` VARCHAR(100) NOT NULL UNIQUE,
  `order_number` VARCHAR(50) NOT NULL,
  `customer_id` INT DEFAULT NULL,
  `checkout_data` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_order_number` (`order_number`),
  KEY `idx_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Fabrics Catalog (for Material Filtering)
CREATE TABLE IF NOT EXISTS `fabrics` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `status` TINYINT DEFAULT 1 COMMENT '1=Active, 0=Inactive',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Product Fabrics Mapping
CREATE TABLE IF NOT EXISTS `product_fabrics` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `fabric_id` INT NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_product_fabric` (`product_id`,`fabric_id`),
  KEY `idx_fabric_id` (`fabric_id`),
  CONSTRAINT `fk_pf_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pf_fabric` FOREIGN KEY (`fabric_id`) REFERENCES `fabrics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Combo Components (for Combo Sets & Bundles)
CREATE TABLE IF NOT EXISTS `combo_components` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ------------------------------------------------------------------------------
-- STEP 2: SAFELY ADD NEW COLUMNS TO EXISTING TABLES (IF NOT ALREADY PRESENT)
-- ------------------------------------------------------------------------------

DROP PROCEDURE IF EXISTS `AddColumnIfNotExists`;

DELIMITER $$
CREATE PROCEDURE `AddColumnIfNotExists`(
    IN tableName VARCHAR(64),
    IN colName VARCHAR(64),
    IN colDef TEXT
)
BEGIN
    DECLARE colCount INT;
    SELECT COUNT(*) INTO colCount
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = tableName
      AND COLUMN_NAME = colName;
    IF colCount = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` ADD COLUMN `', colName, '` ', colDef);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$
DELIMITER ;

-- A. Update `customers` table for Google OAuth & OTP Verification
CALL AddColumnIfNotExists('customers', 'google_id', 'VARCHAR(100) DEFAULT NULL UNIQUE');
CALL AddColumnIfNotExists('customers', 'auth_provider', 'VARCHAR(20) DEFAULT "local"');
CALL AddColumnIfNotExists('customers', 'avatar_url', 'VARCHAR(255) DEFAULT NULL');
CALL AddColumnIfNotExists('customers', 'email_verified', 'TINYINT DEFAULT 1 COMMENT "1=Verified, 0=Unverified"');
CALL AddColumnIfNotExists('customers', 'email_verified_at', 'TIMESTAMP NULL DEFAULT NULL');

-- B. Update `banners` table for Mobile Responsive Banner Slider & Custom CTA
CALL AddColumnIfNotExists('banners', 'tagline', 'VARCHAR(100) DEFAULT "Featured Collection"');
CALL AddColumnIfNotExists('banners', 'subtitle', 'VARCHAR(255) DEFAULT "Elegant designs, soft fabrics, and customizable sizing matrices."');
CALL AddColumnIfNotExists('banners', 'mobile_image', 'VARCHAR(255) DEFAULT NULL');
CALL AddColumnIfNotExists('banners', 'btn_text', 'VARCHAR(100) DEFAULT "Shop Collection"');

-- C. Update `cart` table for Combo Sets
CALL AddColumnIfNotExists('cart', 'combo_product_id', 'INT DEFAULT NULL');
CALL AddColumnIfNotExists('cart', 'combo_config', 'TEXT DEFAULT NULL');

-- D. Update `order_items` table for Combo Sets
CALL AddColumnIfNotExists('order_items', 'combo_product_id', 'INT DEFAULT NULL');
CALL AddColumnIfNotExists('order_items', 'combo_config', 'TEXT DEFAULT NULL');

-- E. Update `products` table for Combo Sets
CALL AddColumnIfNotExists('products', 'product_type', 'ENUM("single", "combo") NOT NULL DEFAULT "single"');

-- F. Update `orders` table for Online Gateway Transaction Reference
CALL AddColumnIfNotExists('orders', 'transaction_id', 'VARCHAR(100) DEFAULT NULL');

-- Cleanup temporary stored procedure
DROP PROCEDURE IF EXISTS `AddColumnIfNotExists`;


-- ------------------------------------------------------------------------------
-- STEP 3: MODIFY COLUMN CONSTRAINTS (NULLABILITY FOR OAUTH & COMBOS)
-- ------------------------------------------------------------------------------

-- Allow password to be NULL for Google OAuth social logins
ALTER TABLE `customers` MODIFY `password` VARCHAR(255) DEFAULT NULL;

-- Allow variant_id to be NULL for combo product sets
ALTER TABLE `cart` MODIFY `variant_id` INT DEFAULT NULL;
ALTER TABLE `order_items` MODIFY `variant_id` INT DEFAULT NULL;


-- ------------------------------------------------------------------------------
-- STEP 4: INSERT DEFAULT SETTINGS KEYS (DO NOT OVERWRITE EXISTING VALUES)
-- ------------------------------------------------------------------------------

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES 
('site_name', 'Pookky'),
('site_email', 'info@pookky.com'),
('site_phone', '+91 98765 43210'),
('currency_symbol', '₹'),
('currency_code', 'INR'),
('shipping_flat_rate', '99.00'),
('free_shipping_threshold', '999.00'),
('default_gst_rate', '18.00'),
('address_company', 'St. Joseph Garments, Phase 2, Industrial Area, Bangalore, Karnataka - 560001'),
('company_legal_name', 'St. Joseph Garments'),
('company_gstin', ''),
('company_reg_number', ''),
('company_pan', ''),
('invoice_authorized_signatory', 'St. Joseph Garments'),
('invoice_terms_notes', '1. Standard exchange policies apply within 7 days of delivery.\n2. Lingerie items must remain unworn with tags attached.\n3. All disputes subject to local jurisdiction.'),
('google_client_id', ''),
('google_client_secret', ''),
('google_auth_enabled', '0'),
('site_notice_enabled', '0'),
('site_notice_badge', 'SPECIAL OFFER'),
('site_notice_text', 'Welcome to Pookky! Enjoy free shipping on all orders above ₹999.'),
('site_notice_type', 'rose'),
('site_notice_dismissible', '1'),
('cod_enabled', '1'),
('cod_shipping_charge', '0'),
('courier_partner_name', 'BlueDart, Delhivery, DTDC, Xpressbees'),
('discreet_packaging_enabled', '1'),
('est_delivery_min_days', '3'),
('est_delivery_max_days', '5'),
('footer_about_text', 'Pookky is a leading ladies\' innerwear brand dedicated to delivering luxury, comfort, and perfect fit. We design and manufacture premium lingerie using the finest fabrics for the modern woman.'),
('home_tagline_categories', 'Selected Collection Blocks'),
('home_tagline_featured', 'Exclusive Picks of the Season'),
('home_tagline_new_arrivals', 'Fresh off the Looms'),
('home_tagline_best_sellers', 'Customer Favorites'),
('nav_menu_categories', ''),
('razorpay_enabled', '0'),
('razorpay_key_id', ''),
('razorpay_key_secret', ''),
('razorpay_mode', 'test'),
('razorpay_webhook_secret', ''),
('return_window_days', '7'),
('social_facebook', 'https://facebook.com'),
('social_instagram', 'https://instagram.com'),
('social_pinterest', 'https://pinterest.com'),
('social_youtube', 'https://youtube.com'),
('support_hours', 'Monday to Saturday, 9:00 AM to 6:00 PM');


-- ------------------------------------------------------------------------------
-- STEP 5: POPULATE DEFAULT FAQS (IF EMPTY)
-- ------------------------------------------------------------------------------

INSERT IGNORE INTO `faqs` (`id`, `question`, `answer`, `sort_order`, `status`) VALUES
(1, 'How do I find my perfect bra size?', 'Refer to our detailed <a href="size-guide.php" class="text-rose fw-bold">Size Guide page</a>. You will need a soft tape measure to record your underbust (band size) and overbust (cup size). Compare measurements with our tables to select the perfect code.', 1, 1),
(2, 'What are the shipping charges and delivery timelines?', 'We charge a flat delivery rate of {SHIPPING_FLAT_RATE}. For orders above {FREE_SHIPPING_THRESHOLD}, shipping is completely FREE. Shipments are processed via major courier partners and usually arrive within {EST_DELIVERY_DAYS} business days.', 2, 1),
(3, 'What is your return and exchange policy?', 'Due to hygiene reasons, we only allow returns or exchanges for allowed cases: damaged product received, wrong product, wrong size received, or missing item in shipment. Requests must be submitted within {RETURN_WINDOW_DAYS} days of delivery.', 3, 1),
(4, 'Can I cancel my order?', 'Yes, you can cancel your order directly from your Customer Dashboard only while the order status is still marked as "Pending". Once processing or shipping starts, cancellations are disabled.', 4, 1),
(5, 'What payment methods do you support?', 'We currently support Cash on Delivery (COD) for all orders, as well as online credit card, debit card, UPI, and net banking payments via Razorpay.', 5, 1);


-- ------------------------------------------------------------------------------
-- STEP 6: POPULATE DEFAULT FABRICS (IF EMPTY)
-- ------------------------------------------------------------------------------

INSERT IGNORE INTO `fabrics` (`id`, `name`, `description`, `status`) VALUES
(1, 'Cotton', 'Natural, breathable, and soft cotton fibers ideal for everyday comfort', 1),
(2, 'Lace', 'Intricate openwork fabric providing romantic, delicate detailing', 1),
(3, 'Satin', 'Smooth, glossy fabric with a luxurious sheen and graceful drape', 1),
(4, 'Nylon', 'High-strength, smooth synthetic fabric resistant to shrinking and abrasion', 1),
(5, 'Elastane', 'Highly elastic polyurethane fiber offering flexible stretch and shape retention', 1),
(6, 'Spandex', 'Synthetic fiber known for exceptional elasticity and comfort fit', 1),
(7, 'Polyester', 'Durable, wrinkle-resistant lightweight synthetic fabric', 1),
(8, 'Silk', 'Premium natural protein fiber with unparalleled soft luster and thermoregulation', 1),
(9, 'Microfiber', 'Ultra-fine synthetic fiber delivering silky smooth, seamless coverage', 1),
(10, 'Modal', 'Eco-friendly semi-synthetic cellulose fiber known for silky softness', 1),
(11, 'Viscose', 'Lightweight, breathable rayon fabric with elegant drape', 1),
(12, 'Mesh', 'Breathable open-structure netting offering sheer elegance and ventilation', 1);

-- ==============================================================================
-- END OF MIGRATION SCRIPT
-- ==============================================================================
