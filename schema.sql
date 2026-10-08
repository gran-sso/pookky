-- Database schema and seeds for Pookky Ladies' Innerwear E-commerce Platform
-- Compatible with cPanel / Hostinger MySQL (select your target database before importing)

-- 1. Admin Roles & Permissions
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    permissions TEXT, -- JSON structure of allowed actions
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Administrators
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    status TINYINT DEFAULT 1 COMMENT '1=Active, 0=Inactive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Admin Action Logging
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Product Categories
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image_path VARCHAR(255),
    sort_order INT DEFAULT 0,
    status TINYINT DEFAULT 1 COMMENT '1=Active, 0=Inactive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Products Base
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    product_type ENUM('single', 'combo') NOT NULL DEFAULT 'single',
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    sku VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    fabric_details TEXT,
    fit_type VARCHAR(100),
    care_instructions TEXT,
    regular_price DECIMAL(10,2) NOT NULL,
    offer_price DECIMAL(10,2) DEFAULT NULL,
    gst_percentage DECIMAL(5,2) DEFAULT 18.00,
    is_featured TINYINT DEFAULT 0,
    is_new_arrival TINYINT DEFAULT 0,
    is_best_seller TINYINT DEFAULT 0,
    status TINYINT DEFAULT 1 COMMENT '1=Active, 0=Inactive',
    seo_title VARCHAR(150),
    seo_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Variant Sizes
CREATE TABLE IF NOT EXISTS sizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    size_name VARCHAR(50) NOT NULL,
    size_code VARCHAR(10) NOT NULL,
    category_type VARCHAR(50) NOT NULL, -- 'Bras', 'Panties', 'Sleepwear', etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Variant Colors
CREATE TABLE IF NOT EXISTS colors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    color_name VARCHAR(50) NOT NULL,
    color_code VARCHAR(7) NOT NULL, -- Hex code e.g. #FFC0CB
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Product Variants Matrix (Size/Color variations)
CREATE TABLE IF NOT EXISTS product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size_id INT NOT NULL,
    color_id INT NOT NULL,
    sku VARCHAR(100) NOT NULL UNIQUE,
    price DECIMAL(10,2) DEFAULT NULL COMMENT 'Override price if set',
    stock INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (size_id) REFERENCES sizes(id) ON DELETE RESTRICT,
    FOREIGN KEY (color_id) REFERENCES colors(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Product Images
CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    color_id INT DEFAULT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_featured TINYINT DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (color_id) REFERENCES colors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9.1 Combo Components Mapping
CREATE TABLE IF NOT EXISTS combo_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    combo_product_id INT NOT NULL,
    component_title VARCHAR(150) NOT NULL,
    allowed_category_id INT DEFAULT NULL,
    allowed_product_id INT DEFAULT NULL,
    default_quantity INT DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (combo_product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (allowed_category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (allowed_product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9.2 Fabric Materials
CREATE TABLE IF NOT EXISTS fabrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9.3 Product to Fabric Mapping
CREATE TABLE IF NOT EXISTS product_fabrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    fabric_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_product_fabric (product_id, fabric_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (fabric_id) REFERENCES fabrics(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Customer Accounts
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password VARCHAR(255) DEFAULT NULL,
    google_id VARCHAR(100) DEFAULT NULL UNIQUE,
    auth_provider VARCHAR(20) DEFAULT 'local',
    avatar_url VARCHAR(255) DEFAULT NULL,
    status TINYINT DEFAULT 1 COMMENT '1=Active, 0=Suspended',
    email_verified TINYINT DEFAULT 1 COMMENT '1=Verified, 0=Unverified',
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Customer Addresses Book
CREATE TABLE IF NOT EXISTS addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    address_type VARCHAR(20) DEFAULT 'Home' COMMENT 'Home, Work, Other',
    receiver_name VARCHAR(100) NOT NULL,
    receiver_phone VARCHAR(20) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    pincode VARCHAR(10) NOT NULL,
    is_default TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Cart Items
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT DEFAULT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    variant_id INT DEFAULT NULL,
    combo_product_id INT DEFAULT NULL,
    combo_config TEXT DEFAULT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    FOREIGN KEY (combo_product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Customer Wishlist
CREATE TABLE IF NOT EXISTS wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_wishlist (customer_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Promotional Coupons
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_order_value DECIMAL(10,2) DEFAULT 0.00,
    expiry_date DATE DEFAULT NULL,
    max_uses INT DEFAULT 0 COMMENT '0=Unlimited',
    used_count INT DEFAULT 0,
    status TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Customer Orders
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    receiver_name VARCHAR(100) NOT NULL,
    receiver_phone VARCHAR(20) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    pincode VARCHAR(10) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    coupon_code VARCHAR(20) DEFAULT NULL,
    discount DECIMAL(10,2) DEFAULT 0.00,
    shipping_charge DECIMAL(10,2) DEFAULT 0.00,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    grand_total DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'COD',
    payment_status ENUM('pending', 'paid', 'refunded', 'failed') DEFAULT 'pending',
    order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'returned') DEFAULT 'pending',
    courier_name VARCHAR(100) DEFAULT NULL,
    tracking_number VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. Order Line Items
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT DEFAULT NULL,
    combo_product_id INT DEFAULT NULL,
    combo_config TEXT DEFAULT NULL,
    sku VARCHAR(100) NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    size_name VARCHAR(50) NOT NULL,
    color_name VARCHAR(50) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    tax_percentage DECIMAL(5,2) NOT NULL,
    tax_amount DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. Manual Payments History
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    transaction_reference VARCHAR(100) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. Shipment Status Tracking
CREATE TABLE IF NOT EXISTS shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    courier_name VARCHAR(100) NOT NULL,
    tracking_number VARCHAR(100) NOT NULL,
    status VARCHAR(100) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. Returns and Exchanges
CREATE TABLE IF NOT EXISTS returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    order_item_id INT NOT NULL,
    customer_id INT NOT NULL,
    request_type ENUM('return', 'exchange') NOT NULL,
    reason ENUM('damaged', 'wrong_product', 'wrong_size', 'missing_item') NOT NULL,
    description TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20. Product Reviews
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    customer_id INT NOT NULL,
    rating INT NOT NULL CHECK(rating BETWEEN 1 AND 5),
    comment TEXT,
    status TINYINT DEFAULT 0 COMMENT '0=Pending, 1=Approved, 2=Rejected',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 21. Promo Banners
CREATE TABLE IF NOT EXISTS banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    mobile_image VARCHAR(255) DEFAULT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    status TINYINT DEFAULT 1 COMMENT '1=Active, 0=Inactive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 22. Site Content Pages
CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    content LONGTEXT NOT NULL,
    seo_title VARCHAR(150),
    seo_description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 23. Contacts Enquiries
CREATE TABLE IF NOT EXISTS enquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'responded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 24. Newsletter Subscribers
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    status TINYINT DEFAULT 1 COMMENT '1=Subscribed, 0=Unsubscribed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 25. Detailed Inventory Adjustments
CREATE TABLE IF NOT EXISTS inventory_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    variant_id INT NOT NULL,
    quantity_changed INT NOT NULL,
    change_type ENUM('restock', 'sale', 'adjustment', 'return') NOT NULL,
    notes VARCHAR(255),
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 26. Global Site Settings
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 27. Email OTP Verifications
CREATE TABLE IF NOT EXISTS email_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    purpose VARCHAR(50) NOT NULL DEFAULT 'registration',
    expires_at DATETIME NOT NULL,
    is_used TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_purpose (email, purpose, is_used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==========================================
-- SEED DATA
-- ==========================================

-- Roles
INSERT INTO roles (id, name, permissions) VALUES 
(1, 'Super Admin', '{"all": true}'),
(2, 'Store Manager', '{"products": "write", "categories": "write", "orders": "read"}'),
(3, 'Order Manager', '{"orders": "write", "returns": "write", "products": "read"}');

-- Admin User (password is 'admin123', hashed using standard PHP BCRYPT)
INSERT INTO admins (id, role_id, username, email, password, name, status) VALUES 
(1, 1, 'admin', 'admin@pookky.com', '$2y$10$paRyUgSJsjcOeCeWRBSSt.rUTBHi/zAqrmtDKmOgbFj5GOcWioEWG', 'Pookky Super Admin', 1);

-- Default Settings
INSERT INTO settings (setting_key, setting_value) VALUES 
('site_name', 'Pookky'),
('site_email', 'info@pookky.com'),
('site_phone', '+91 98765 43210'),
('currency_symbol', '₹'),
('currency_code', 'INR'),
('shipping_flat_rate', '99.00'),
('free_shipping_threshold', '999.00'),
('default_gst_rate', '18.00'),
('address_company', 'Pookky Innerwear Mfg Ltd, Phase 2, Industrial Area, Bangalore, Karnataka - 560001'),
('google_client_id', ''),
('google_client_secret', ''),
('google_auth_enabled', '0');

-- Default Content Pages
INSERT INTO pages (slug, title, content, seo_title, seo_description) VALUES
('about-us', 'About Pookky', '<h2>About Our Company</h2><p>Pookky is a leading ladies\' innerwear brand dedicated to delivering luxury, comfort, and perfect fit. We design and manufacture premium lingerie using the finest fabrics and state-of-the-art technology.</p>', 'About Us | Pookky Lingerie', 'Learn more about Pookky premium innerwear manufacturing and heritage.'),
('privacy-policy', 'Privacy Policy', '<h2>Privacy Policy</h2><p>Your privacy is important to us. We collect, store, and process your data in accordance with strict security standards.</p>', 'Privacy Policy | Pookky', 'Privacy policy and data protection terms for Pookky customers.'),
('terms-and-conditions', 'Terms & Conditions', '<h2>Terms & Conditions</h2><p>Welcome to Pookky. By accessing this website, you agree to comply with our terms of usage and conditions.</p>', 'Terms and Conditions | Pookky', 'Terms of service and usage regulations for Pookky website.'),
('return-and-exchange', 'Return & Exchange Policy', '<h2>Returns and Exchanges</h2><p>We allow returns or exchanges within 7 days of delivery only for Damaged items, Wrong items, Wrong size received, or Missing items. Product tags must remain attached and the item must be unworn.</p>', 'Return & Exchange Policy | Pookky', 'Easy return and exchange policies for our lingerie products.'),
('shipping-policy', 'Shipping Policy', '<h2>Shipping and Delivery</h2><p>We offer flat rate shipping of ₹99 across India. Shipping is FREE for orders above ₹999. Deliveries usually take 3-5 business days.</p>', 'Shipping Policy | Pookky', 'Fast and flat-rate shipping details across India.'),
('size-guide', 'Size Guide', '<h2>Size Guide & Measurement Tips</h2><p>Follow our guidelines to find your perfect fit. For Bras, measure your under-bust and over-bust. For Panties, measure your waist and hips.</p>', 'Size Guide | Pookky', 'Comprehensive size guide for bras, panties, and sleepwear.');

-- Size seeds
INSERT INTO sizes (size_name, size_code, category_type) VALUES
('32B', '32B', 'Bras'),
('34B', '34B', 'Bras'),
('36B', '36B', 'Bras'),
('38B', '38B', 'Bras'),
('32C', '32C', 'Bras'),
('34C', '34C', 'Bras'),
('36C', '36C', 'Bras'),
('38C', '38C', 'Bras'),
('Small', 'S', 'Panties'),
('Medium', 'M', 'Panties'),
('Large', 'L', 'Panties'),
('X-Large', 'XL', 'Panties'),
('XX-Large', 'XXL', 'Panties'),
('Small', 'S', 'Nighties'),
('Medium', 'M', 'Nighties'),
('Large', 'L', 'Nighties'),
('X-Large', 'XL', 'Nighties'),
('XX-Large', 'XXL', 'Nighties');

-- Color seeds
INSERT INTO colors (color_name, color_code) VALUES
('Blush Pink', '#FFF0F2'),
('Ruby Red', '#9B111E'),
('Midnight Black', '#0A0A0A'),
('Ivory White', '#FFFFF0'),
('Warm Nude', '#E3C1B5'),
('Mauve Mist', '#8A7380');

-- Categories
INSERT INTO categories (id, parent_id, name, slug, description, image_path, sort_order, status) VALUES
(1, NULL, 'Bras', 'bras', 'Premium, supportive, and elegant bras for all day comfort.', 'category_bras.jpg', 1, 1),
(2, NULL, 'Panties', 'panties', 'Soft, breathable panties in various cuts and fabrics.', 'category_panties.jpg', 2, 1),
(3, NULL, 'Nighties', 'nighties', 'Luxurious, soft, and comfortable nighties and sleep slips.', 'category_nighties.jpg', 3, 1),
(4, NULL, 'Shapewear', 'shapewear', 'Seamless compression shapewear.', 'category_shapewear.jpg', 6, 0),
(5, NULL, 'Combo Sets', 'combo-sets', 'Curated matching bra & panty sets and value bundles.', 'category_combos.jpg', 4, 1),
(6, NULL, 'Everyday Essentials', 'everyday-essentials', 'Ultra-comfortable daily cottons, seamless briefs, and essentials.', 'category_everyday.jpg', 5, 1);

-- Mock products
INSERT INTO products (id, category_id, name, slug, sku, description, fabric_details, fit_type, care_instructions, regular_price, offer_price, gst_percentage, is_featured, is_new_arrival, is_best_seller, status, seo_title, seo_description) VALUES
(1, 1, 'Elegance Lace Underwire Bra', 'elegance-lace-underwire-bra', 'BR-ELG-001', 'An elegant underwire bra decorated with French lace overlays. Offers unparalleled support and comfort.', '80% Nylon, 20% Elastane, French Lace trim', 'Underwired, full coverage, lightly padded', 'Hand wash cold. Do not bleach. Dry flat in shade. Do not iron.', 1299.00, 1099.00, 18.00, 1, 1, 1, 1, 'Elegance Lace Underwire Bra | Pookky', 'Buy elegant French lace underwire bra online. Best comfort and lift.'),
(2, 1, 'Everyday Cotton T-Shirt Bra', 'everyday-cotton-tshirt-bra', 'BR-COT-002', 'Ultra-soft everyday cotton bra with seamless padding, perfect under tight t-shirts.', '95% Cotton, 5% Spandex', 'Wire-free, medium coverage, seamless padded', 'Machine wash cold, delicate cycle in laundry bag. Dry low.', 899.00, 799.00, 18.00, 0, 1, 1, 1, 'Everyday Cotton T-Shirt Bra | Pookky', 'Everyday seamless wire-free cotton t-shirt bra by Pookky.'),
(3, 2, 'Luxury Lace Trim Hipster', 'luxury-lace-trim-hipster', 'PN-LAC-003', 'A pack of mid-rise hipster briefs adorned with delicate lace edges.', '90% Nylon, 10% Lycra, 100% Cotton Gusset', 'Mid-rise, hipster cut, medium coverage', 'Hand wash recommended. Machine wash cold inside mesh bag.', 499.00, 449.00, 18.00, 1, 0, 1, 1, 'Luxury Lace Trim Hipster Pack | Pookky', 'Buy premium lace trim hipsters pack. Soft cotton gusset.'),
(4, 3, 'Satin Nightgown & Robe Set', 'satin-nightgown-robe-set', 'SL-SAT-004', 'Duo satin slips and lightweight robes set. Luxurious texture and elegant drape.', '100% Polyester Satin', 'Relaxed fit, adjustable straps, matching waist tie robe', 'Hand wash cold or dry clean. Low steam iron.', 2499.00, 1999.00, 18.00, 1, 1, 0, 1, 'Satin Nightgown & Robe Set | Pookky', 'Premium satin slip and matching robe loungewear set.');

-- Mock Product Variants with stock
INSERT INTO product_variants (id, product_id, size_id, color_id, sku, price, stock) VALUES
-- Elegance Lace Bra (1): 32B, 34B, 36B in Blush Pink (1), Midnight Black (3)
(1, 1, 1, 1, 'BR-ELG-001-32B-BLUSH', NULL, 15),
(2, 1, 2, 1, 'BR-ELG-001-34B-BLUSH', NULL, 22),
(3, 1, 3, 1, 'BR-ELG-001-36B-BLUSH', NULL, 10),
(4, 1, 1, 3, 'BR-ELG-001-32B-BLACK', NULL, 8),
(5, 1, 2, 3, 'BR-ELG-001-34B-BLACK', NULL, 3), -- Low stock for testing notifications
(6, 1, 3, 3, 'BR-ELG-001-36B-BLACK', NULL, 14),

-- Cotton Bra (2): 34B, 36B, 34C in Warm Nude (5), Ivory White (4)
(7, 2, 2, 5, 'BR-COT-002-34B-NUDE', NULL, 25),
(8, 2, 3, 5, 'BR-COT-002-36B-NUDE', NULL, 30),
(9, 2, 6, 5, 'BR-COT-002-34C-NUDE', NULL, 18),
(10, 2, 2, 4, 'BR-COT-002-34B-WHITE', NULL, 12),
(11, 2, 3, 4, 'BR-COT-002-36B-WHITE', NULL, 2), -- Low stock

-- Hipster Briefs (3): S, M, L in Blush Pink (1), Midnight Black (3)
(12, 3, 9, 1, 'PN-LAC-003-S-BLUSH', NULL, 40),
(13, 3, 10, 1, 'PN-LAC-003-M-BLUSH', NULL, 50),
(14, 3, 11, 1, 'PN-LAC-003-L-BLUSH', NULL, 35),
(15, 3, 9, 3, 'PN-LAC-003-S-BLACK', NULL, 45),
(16, 3, 10, 3, 'PN-LAC-003-M-BLACK', NULL, 42),
(17, 3, 11, 3, 'PN-LAC-003-L-BLACK', NULL, 20),

-- Satin Sleepwear (4): S, M, L in Ruby Red (2), Mauve Mist (6)
(18, 4, 14, 2, 'SL-SAT-004-S-RUBY', NULL, 12),
(19, 4, 15, 2, 'SL-SAT-004-M-RUBY', NULL, 15),
(20, 4, 16, 2, 'SL-SAT-004-L-RUBY', NULL, 9),
(21, 4, 14, 6, 'SL-SAT-004-S-MAUVE', NULL, 11),
(22, 4, 15, 6, 'SL-SAT-004-M-MAUVE', NULL, 14),
(23, 4, 16, 6, 'SL-SAT-004-L-MAUVE', NULL, 0); -- Out of stock

-- Product Images mapping
INSERT INTO product_images (id, product_id, image_path, is_featured) VALUES
(1, 1, 'bra_elegance_pink.jpg', 1),
(2, 1, 'bra_elegance_pink_back.jpg', 0),
(3, 2, 'bra_tshirt_nude.jpg', 1),
(4, 3, 'panty_hipster_pink.jpg', 1),
(5, 4, 'sleepwear_satin_ruby.jpg', 1);

-- Default Fabrics Configuration
INSERT INTO fabrics (id, name, description, status) VALUES
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

-- Default Product Fabrics Mappings
INSERT INTO product_fabrics (product_id, fabric_id) VALUES
(1, 2), -- Bra 1: Lace
(1, 4), -- Bra 1: Nylon
(1, 5), -- Bra 1: Elastane
(2, 1), -- Bra 2: Cotton
(2, 6), -- Bra 2: Spandex
(3, 1), -- Panty 3: Cotton
(3, 2), -- Panty 3: Lace
(3, 4), -- Panty 3: Nylon
(4, 3), -- Satin Robe 4: Satin
(4, 7); -- Satin Robe 4: Polyester

-- Default Banner Promo
INSERT INTO banners (title, image_path, link_url, sort_order, status) VALUES
('Luxury Lingerie Collection - Fall 2026', 'banner_fall_2026.jpg', 'products.php?category=bras', 1, 1),
('Softest Everyday Comfies - wire free cottons', 'banner_everyday.jpg', 'products.php?category=bras', 2, 1);

-- Default Coupons
INSERT INTO coupons (code, discount_type, discount_value, min_order_value, expiry_date, max_uses, status) VALUES
('POOKKY100', 'fixed', 100.00, 499.00, '2027-12-31', 500, 1),
('FIRST15', 'percentage', 15.00, 999.00, '2027-12-31', 1000, 1);
