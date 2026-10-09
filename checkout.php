<?php
// checkout.php
// Modern State-of-the-Art Customer & Guest Checkout Flow

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';

$meta_title = 'Secure Checkout | Pookky';

$is_logged_in = is_customer_logged_in();
$customer_id = $is_logged_in ? (int)$_SESSION['customer_id'] : null;
$session_id = session_id();
$logged_in_customer = $is_logged_in ? get_logged_in_customer() : null;

$error = '';
$success = '';

// 1. Fetch Cart items (Logged in customer_id OR guest session_id)
try {
    if ($customer_id) {
        $cart_sql = "
            SELECT c.id as cart_id, c.quantity, c.variant_id, c.combo_product_id, c.combo_config,
                   v.sku as variant_sku, v.regular_price as variant_regular_price, v.offer_price as variant_offer_price, v.price as variant_price, v.stock as variant_stock,
                   p.id as product_id, p.product_type, p.name as product_name, p.slug as product_slug, 
                   p.sku as base_sku, p.regular_price, p.offer_price, p.gst_percentage,
                   (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_featured DESC LIMIT 1) as main_image,
                   sz.size_code, cl.color_name, cl.color_code
            FROM cart c 
            LEFT JOIN product_variants v ON c.variant_id = v.id 
            LEFT JOIN products p ON COALESCE(c.combo_product_id, v.product_id) = p.id 
            LEFT JOIN sizes sz ON v.size_id = sz.id 
            LEFT JOIN colors cl ON v.color_id = cl.id 
            WHERE c.customer_id = ?
        ";
        $cart_stmt = $pdo->prepare($cart_sql);
        $cart_stmt->execute([$customer_id]);
    } else {
        $cart_sql = "
            SELECT c.id as cart_id, c.quantity, c.variant_id, c.combo_product_id, c.combo_config,
                   v.sku as variant_sku, v.regular_price as variant_regular_price, v.offer_price as variant_offer_price, v.price as variant_price, v.stock as variant_stock,
                   p.id as product_id, p.product_type, p.name as product_name, p.slug as product_slug, 
                   p.sku as base_sku, p.regular_price, p.offer_price, p.gst_percentage,
                   (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_featured DESC LIMIT 1) as main_image,
                   sz.size_code, cl.color_name, cl.color_code
            FROM cart c 
            LEFT JOIN product_variants v ON c.variant_id = v.id 
            LEFT JOIN products p ON COALESCE(c.combo_product_id, v.product_id) = p.id 
            LEFT JOIN sizes sz ON v.size_id = sz.id 
            LEFT JOIN colors cl ON v.color_id = cl.id 
            WHERE c.session_id = ? AND c.customer_id IS NULL
        ";
        $cart_stmt = $pdo->prepare($cart_sql);
        $cart_stmt->execute([$session_id]);
    }
    $cart_items = $cart_stmt->fetchAll();

    if (empty($cart_items)) {
        set_flash_message('error', 'Your shopping cart is empty.');
        header('Location: cart.php');
        exit;
    }

    // Process unit pricing and parsed combo configs
    foreach ($cart_items as &$c_item) {
        if (!empty($c_item['combo_product_id']) && !empty($c_item['combo_config'])) {
            $c_item['parsed_combo_config'] = json_decode($c_item['combo_config'], true) ?? [];
            $c_item['unit_price'] = $c_item['offer_price'] ? (float)$c_item['offer_price'] : (float)$c_item['regular_price'];
        } else {
            $c_item['parsed_combo_config'] = null;
            
            // Dual-tier price resolution
            $v_off = ($c_item['variant_offer_price'] !== null && $c_item['variant_offer_price'] > 0) ? (float)$c_item['variant_offer_price'] : null;
            $v_reg = ($c_item['variant_regular_price'] !== null && $c_item['variant_regular_price'] > 0) ? (float)$c_item['variant_regular_price'] : (float)$c_item['regular_price'];
            $v_legacy = ($c_item['variant_price'] !== null && $c_item['variant_price'] > 0 && !$c_item['variant_regular_price']) ? (float)$c_item['variant_price'] : null;
            
            if ($v_off !== null) {
                $c_item['unit_price'] = $v_off;
            } elseif ($v_legacy !== null) {
                $c_item['unit_price'] = $v_legacy;
            } elseif (!empty($c_item['offer_price']) && (float)$c_item['offer_price'] > 0) {
                $c_item['unit_price'] = (float)$c_item['offer_price'];
            } else {
                $c_item['unit_price'] = $v_reg;
            }
        }
    }
    unset($c_item);

} catch (PDOException $e) {
    die("Error processing cart: " . $e->getMessage());
}

// 2. Fetch Customer saved addresses (if logged in)
$addresses = [];
if ($customer_id) {
    $addr_stmt = $pdo->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC");
    $addr_stmt->execute([$customer_id]);
    $addresses = $addr_stmt->fetchAll();
}

// 3. Process Subtotal & Taxes
$subtotal = 0.00;
$tax_amount = 0.00;

foreach ($cart_items as $item) {
    $price = $item['unit_price'];
    $item_total = $price * $item['quantity'];
    
    $gst_rate = (float)$item['gst_percentage'];
    $tax_included = $item_total - ($item_total / (1 + ($gst_rate / 100)));

    $subtotal += $item_total;
    $tax_amount += $tax_included;
}

// 4. Coupon Processing
$coupon_code = $_SESSION['checkout_coupon']['code'] ?? '';
$discount = 0.00;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed.';
    } else {
        $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
        if (empty($code)) {
            $error = 'Please enter a coupon code.';
            unset($_SESSION['checkout_coupon']);
        } else {
            try {
                $c_stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND status = 1 LIMIT 1");
                $c_stmt->execute([$code]);
                $coupon = $c_stmt->fetch();

                if (!$coupon) {
                    $error = 'Invalid coupon code.';
                    unset($_SESSION['checkout_coupon']);
                } else {
                    $today = date('Y-m-d');
                    if ($coupon['expiry_date'] && $coupon['expiry_date'] < $today) {
                        $error = 'This coupon code has expired.';
                        unset($_SESSION['checkout_coupon']);
                    } elseif ($coupon['max_uses'] > 0 && $coupon['used_count'] >= $coupon['max_uses']) {
                        $error = 'This coupon usage limit has been reached.';
                        unset($_SESSION['checkout_coupon']);
                    } elseif ($subtotal < $coupon['min_order_value']) {
                        $error = 'Minimum order subtotal of ' . format_price($coupon['min_order_value']) . ' required for this coupon.';
                        unset($_SESSION['checkout_coupon']);
                    } else {
                        $_SESSION['checkout_coupon'] = [
                            'id' => $coupon['id'],
                            'code' => $coupon['code'],
                            'discount_type' => $coupon['discount_type'],
                            'discount_value' => (float)$coupon['discount_value']
                        ];
                        $success = 'Coupon code "' . $coupon['code'] . '" applied successfully!';
                        $coupon_code = $coupon['code'];
                    }
                }
            } catch (PDOException $e) {
                $error = 'Coupon verification failed.';
            }
        }
    }
}

// Remove applied coupon
if (isset($_GET['remove_coupon'])) {
    unset($_SESSION['checkout_coupon']);
    header('Location: checkout.php');
    exit;
}

// Calculate discount
if (isset($_SESSION['checkout_coupon'])) {
    $cp = $_SESSION['checkout_coupon'];
    if ($cp['discount_type'] === 'percentage') {
        $discount = round(($subtotal * $cp['discount_value']) / 100, 2);
    } else {
        $discount = min($subtotal, $cp['discount_value']);
    }
}

// Shipping & Grand total
$shipping = $subtotal >= FREE_SHIPPING_THRESHOLD ? 0.00 : SHIPPING_FLAT_RATE;
$grand_total = max(0.00, $subtotal - $discount + $shipping);

// 5. PLACE ORDER (Logged-in & Guest checkout handling)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed.';
    } else {
        $chosen_payment = trim($_POST['payment_method'] ?? 'COD');
        $payment_method = ($chosen_payment === 'Razorpay' && RAZORPAY_ENABLED) ? 'Razorpay' : 'COD';
        $order_customer_id = $customer_id;
        $delivery_name = '';
        $delivery_phone = '';
        $delivery_line1 = '';
        $delivery_line2 = '';
        $delivery_city = '';
        $delivery_state = '';
        $delivery_pincode = '';

        if ($is_logged_in) {
            // Logged-in Customer Flow
            $selected_address_id = (int)($_POST['address_id'] ?? 0);
            $new_receiver_name = trim($_POST['new_receiver_name'] ?? '');
            $new_receiver_phone = trim($_POST['new_receiver_phone'] ?? '');
            $new_address_line1 = trim($_POST['new_address_line1'] ?? '');
            $new_address_line2 = trim($_POST['new_address_line2'] ?? '');
            $new_city = trim($_POST['new_city'] ?? '');
            $new_state = trim($_POST['new_state'] ?? '');
            $new_pincode = trim($_POST['new_pincode'] ?? '');
            $new_address_type = trim($_POST['new_address_type'] ?? 'Home');
            $new_is_default = isset($_POST['new_is_default']) ? 1 : 0;

            if ($selected_address_id <= 0 && !empty($new_receiver_name)) {
                if (empty($new_receiver_phone) || empty($new_address_line1) || empty($new_city) || empty($new_state) || empty($new_pincode)) {
                    $error = 'Please fill in all required address fields.';
                } else {
                    try {
                        $ins_addr = $pdo->prepare("
                            INSERT INTO addresses (customer_id, address_type, receiver_name, receiver_phone, address_line1, address_line2, city, state, pincode, is_default) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins_addr->execute([$customer_id, $new_address_type, $new_receiver_name, $new_receiver_phone, $new_address_line1, $new_address_line2, $new_city, $new_state, $new_pincode, $new_is_default]);
                        $selected_address_id = (int)$pdo->lastInsertId();
                    } catch (PDOException $e) {
                        $error = 'Failed to save address: ' . $e->getMessage();
                    }
                }
            }

            if ($selected_address_id <= 0 && empty($error)) {
                $error = 'Please select or provide a delivery address.';
            } elseif (empty($error)) {
                $addr_stmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND customer_id = ? LIMIT 1");
                $addr_stmt->execute([$selected_address_id, $customer_id]);
                $address = $addr_stmt->fetch();

                if (!$address) {
                    $error = 'Selected shipping address is invalid.';
                } else {
                    $delivery_name = $address['receiver_name'];
                    $delivery_phone = $address['receiver_phone'];
                    $delivery_line1 = $address['address_line1'];
                    $delivery_line2 = $address['address_line2'];
                    $delivery_city = $address['city'];
                    $delivery_state = $address['state'];
                    $delivery_pincode = $address['pincode'];
                }
            }
        } else {
            // Guest Checkout Flow
            $guest_name = trim($_POST['guest_name'] ?? '');
            $guest_email = trim($_POST['guest_email'] ?? '');
            $guest_phone = trim($_POST['guest_phone'] ?? '');
            $guest_line1 = trim($_POST['guest_address_line1'] ?? '');
            $guest_line2 = trim($_POST['guest_address_line2'] ?? '');
            $guest_city = trim($_POST['guest_city'] ?? '');
            $guest_state = trim($_POST['guest_state'] ?? '');
            $guest_pincode = trim($_POST['guest_pincode'] ?? '');
            $create_account = isset($_POST['create_account']) ? 1 : 0;
            $guest_password = trim($_POST['guest_password'] ?? '');

            if (empty($guest_name) || empty($guest_email) || empty($guest_phone) || empty($guest_line1) || empty($guest_city) || empty($guest_state) || empty($guest_pincode)) {
                $error = 'Please fill in all required contact and shipping details.';
            } elseif (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please provide a valid email address.';
            } else {
                try {
                    // Check if customer with this email already exists
                    $c_chk = $pdo->prepare("SELECT id, name, phone FROM customers WHERE email = ? LIMIT 1");
                    $c_chk->execute([$guest_email]);
                    $existing_cust = $c_chk->fetch();

                    if ($existing_cust) {
                        $order_customer_id = (int)$existing_cust['id'];
                    } else {
                        // Create customer account
                        $raw_pwd = !empty($guest_password) ? $guest_password : bin2hex(random_bytes(6));
                        $pwd_hash = password_hash($raw_pwd, PASSWORD_DEFAULT);
                        $ins_c = $pdo->prepare("INSERT INTO customers (name, email, phone, password, status) VALUES (?, ?, ?, ?, 1)");
                        $ins_c->execute([$guest_name, $guest_email, $guest_phone, $pwd_hash]);
                        $order_customer_id = (int)$pdo->lastInsertId();
                    }

                    // Save address for this customer
                    $ins_addr = $pdo->prepare("
                        INSERT INTO addresses (customer_id, address_type, receiver_name, receiver_phone, address_line1, address_line2, city, state, pincode, is_default) 
                        VALUES (?, 'Home', ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $ins_addr->execute([$order_customer_id, $guest_name, $guest_phone, $guest_line1, $guest_line2, $guest_city, $guest_state, $guest_pincode]);

                    // Automatically authenticate customer session
                    $_SESSION['customer_id'] = $order_customer_id;
                    $_SESSION['customer_name'] = $guest_name;
                    $_SESSION['customer_email'] = $guest_email;

                    $delivery_name = $guest_name;
                    $delivery_phone = $guest_phone;
                    $delivery_line1 = $guest_line1;
                    $delivery_line2 = $guest_line2;
                    $delivery_city = $guest_city;
                    $delivery_state = $guest_state;
                    $delivery_pincode = $guest_pincode;

                } catch (PDOException $e) {
                    $error = 'Account preparation failed: ' . $e->getMessage();
                }
            }
        }

        // Execute Order Placement Transaction
        if (empty($error) && !empty($order_customer_id) && !empty($delivery_name)) {
            try {
                $pdo->beginTransaction();

                // Verify stock levels under transaction row lock (FOR UPDATE)
                foreach ($cart_items as $item) {
                    if (!empty($item['combo_product_id']) && !empty($item['parsed_combo_config'])) {
                        foreach ($item['parsed_combo_config'] as $sub_var) {
                            $stock_stmt = $pdo->prepare("SELECT stock, sku FROM product_variants WHERE id = ? FOR UPDATE");
                            $stock_stmt->execute([$sub_var['variant_id']]);
                            $var = $stock_stmt->fetch();

                            if (!$var || (int)$var['stock'] < $item['quantity']) {
                                throw new Exception("Combo set component SKU " . ($var['sku'] ?? $sub_var['sku']) . " is out of stock.");
                            }
                        }
                    } else {
                        $stock_stmt = $pdo->prepare("SELECT stock, sku FROM product_variants WHERE id = ? FOR UPDATE");
                        $stock_stmt->execute([$item['variant_id']]);
                        $var = $stock_stmt->fetch();

                        if (!$var || (int)$var['stock'] < $item['quantity']) {
                            throw new Exception("Product SKU " . ($var['sku'] ?? $item['variant_sku']) . " is currently out of stock.");
                        }
                    }
                }

                // Generate Unique Order ID
                $order_number = generate_order_number();

                // Insert Order
                $ins_order = $pdo->prepare("
                    INSERT INTO orders 
                    (order_number, customer_id, receiver_name, receiver_phone, address_line1, address_line2, city, state, pincode, subtotal, coupon_code, discount, shipping_charge, tax_amount, grand_total, payment_method, payment_status, order_status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
                ");
                $ins_order->execute([
                    $order_number,
                    $order_customer_id,
                    $delivery_name,
                    $delivery_phone,
                    $delivery_line1,
                    $delivery_line2,
                    $delivery_city,
                    $delivery_state,
                    $delivery_pincode,
                    $subtotal,
                    !empty($coupon_code) ? $coupon_code : null,
                    $discount,
                    $shipping,
                    $tax_amount,
                    $grand_total,
                    $payment_method
                ]);

                $order_id = $pdo->lastInsertId();

                // Insert Order Items & decrement variant stocks
                foreach ($cart_items as $item) {
                    $unit_price = $item['unit_price'];
                    $line_total = $unit_price * $item['quantity'];

                    $gst_rate = (float)$item['gst_percentage'];
                    $line_tax = $line_total - ($line_total / (1 + ($gst_rate / 100)));

                    if (!empty($item['combo_product_id'])) {
                        $ins_item = $pdo->prepare("
                            INSERT INTO order_items 
                            (order_id, product_id, variant_id, combo_product_id, combo_config, sku, product_name, size_name, color_name, quantity, price, tax_percentage, tax_amount, total) 
                            VALUES (?, ?, NULL, ?, ?, ?, ?, 'Custom Combo Set', 'Multi / Matching', ?, ?, ?, ?, ?)
                        ");
                        $ins_item->execute([
                            $order_id,
                            $item['product_id'],
                            $item['combo_product_id'],
                            $item['combo_config'],
                            $item['base_sku'],
                            $item['product_name'],
                            $item['quantity'],
                            $unit_price,
                            $gst_rate,
                            $line_tax,
                            $line_total
                        ]);

                        foreach ($item['parsed_combo_config'] as $sub_var) {
                            $dec_stock = $pdo->prepare("UPDATE product_variants SET stock = stock - ? WHERE id = ?");
                            $dec_stock->execute([$item['quantity'], $sub_var['variant_id']]);

                            $log_inv = $pdo->prepare("
                                INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) 
                                VALUES (?, ?, 'sale', ?)
                            ");
                            $log_inv->execute([$sub_var['variant_id'], -$item['quantity'], "Sale deduction for order: " . $order_number]);
                        }
                    } else {
                        $attr_stmt = $pdo->prepare("
                            SELECT s.size_code, c.color_name 
                            FROM product_variants pv 
                            JOIN sizes s ON pv.size_id = s.id 
                            JOIN colors c ON pv.color_id = c.id 
                            WHERE pv.id = ? LIMIT 1
                        ");
                        $attr_stmt->execute([$item['variant_id']]);
                        $attr = $attr_stmt->fetch();
                        $sz_code = $attr ? $attr['size_code'] : ($item['size_code'] ?? 'Standard');
                        $cl_name = $attr ? $attr['color_name'] : ($item['color_name'] ?? 'Classic');

                        $ins_item = $pdo->prepare("
                            INSERT INTO order_items 
                            (order_id, product_id, variant_id, combo_product_id, combo_config, sku, product_name, size_name, color_name, quantity, price, tax_percentage, tax_amount, total) 
                            VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins_item->execute([
                            $order_id,
                            $item['product_id'],
                            $item['variant_id'],
                            $item['variant_sku'],
                            $item['product_name'],
                            $sz_code,
                            $cl_name,
                            $item['quantity'],
                            $unit_price,
                            $gst_rate,
                            $line_tax,
                            $line_total
                        ]);

                        $dec_stock = $pdo->prepare("UPDATE product_variants SET stock = stock - ? WHERE id = ?");
                        $dec_stock->execute([$item['quantity'], $item['variant_id']]);

                        $log_inv = $pdo->prepare("
                            INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) 
                            VALUES (?, ?, 'sale', ?)
                        ");
                        $log_inv->execute([$item['variant_id'], -$item['quantity'], "Sale deduction for order: " . $order_number]);
                    }
                }

                // Increment coupon usage
                if (isset($_SESSION['checkout_coupon'])) {
                    $up_coupon = $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
                    $up_coupon->execute([$_SESSION['checkout_coupon']['id']]);
                }

                if ($payment_method === 'Razorpay') {
                    // Log pending Razorpay payment
                    $ins_pay = $pdo->prepare("
                        INSERT INTO payments (order_id, payment_method, amount, status) 
                        VALUES (?, 'Razorpay', ?, 'pending')
                    ");
                    $ins_pay->execute([$order_id, $grand_total]);

                    $pdo->commit();

                    $customer_email_address = $is_logged_in ? ($customer['email'] ?? '') : ($guest_email ?? '');

                    // Prepare Razorpay payload for frontend invocation
                    $razorpay_order_payload = [
                        'key' => RAZORPAY_KEY_ID,
                        'amount' => (int)round($grand_total * 100), // In paise
                        'currency' => CURRENCY_CODE,
                        'name' => SITE_NAME,
                        'description' => "Order #" . $order_number,
                        'order_number' => $order_number,
                        'order_id' => $order_id,
                        'prefill' => [
                            'name' => $delivery_name,
                            'email' => $customer_email_address,
                            'contact' => $delivery_phone
                        ],
                        'theme' => [
                            'color' => '#E60066'
                        ]
                    ];
                } else {
                    // Log initial COD payment info
                    $ins_pay = $pdo->prepare("
                        INSERT INTO payments (order_id, payment_method, amount, status) 
                        VALUES (?, 'COD', ?, 'pending')
                    ");
                    $ins_pay->execute([$order_id, $grand_total]);

                    // Clear Cart (by customer_id or session_id)
                    $clear_cart = $pdo->prepare("DELETE FROM cart WHERE customer_id = ? OR session_id = ?");
                    $clear_cart->execute([$order_customer_id, $session_id]);

                    $pdo->commit();
                    
                    // Clear applied coupon session
                    unset($_SESSION['checkout_coupon']);

                    // Send confirmation email for COD
                    try {
                        $cust_email_for_mail = $is_logged_in ? ($customer['email'] ?? '') : ($guest_email ?? '');
                        if (!empty($cust_email_for_mail)) {
                            send_order_confirmation_email($cust_email_for_mail, $order_number, $grand_total, $delivery_name);
                        }
                    } catch (Exception $e) {}
                    
                    header('Location: order-success.php?order_num=' . urlencode($order_number));
                    exit;
                }

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Checkout transaction failed: ' . $e->getMessage();
            }
        }
    }
}

// Now include header layout for HTML rendering
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    
    <!-- Checkout Header & Step Progress -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-2 border-bottom">
        <div>
            <h1 class="page-title h3 mb-1">Express Checkout</h1>
            <div class="text-muted small">
                <i class="bi bi-shield-check text-success me-1"></i> 256-bit SSL Encrypted &bull; 100% Privacy Guaranteed
            </div>
        </div>
        <a href="cart.php" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-left me-1"></i> Return to Bag
        </a>
    </div>
    
    <?php if(!empty($error)): ?>
        <div class="alert alert-danger p-3 small mb-4 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo e($error); ?>
        </div>
    <?php endif; ?>
    <?php if(!empty($success)): ?>
        <div class="alert alert-success p-3 small mb-4 shadow-sm">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo e($success); ?>
        </div>
    <?php endif; ?>

    <!-- Guest Login Prompt Banner (if not logged in) -->
    <?php if (!$is_logged_in): ?>
        <div class="card border-0 shadow-sm p-3 mb-4 bg-brand-blush border-rose rounded-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center">
                    <i class="bi bi-person-circle fs-4 text-rose me-3"></i>
                    <div>
                        <strong class="text-dark">Already have a Pookky account?</strong>
                        <div class="text-muted small">Log in to use saved addresses and track orders easily.</div>
                    </div>
                </div>
                <a href="login.php?redirect=checkout.php" class="btn btn-sm btn-brand rounded-pill px-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Log In
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        
        <!-- Left Column: Checkout Details Form -->
        <div class="col-12 col-lg-7">
            <form action="checkout.php" method="POST" id="checkout-form">
                <?php echo csrf_field(); ?>
                
                <?php if (!$is_logged_in): ?>
                    <!-- Returning Guest 1-Tap Auto-fill Banner (Dynamically shown if saved address exists) -->
                    <div id="guest-autofill-banner" class="alert alert-rose-subtle border border-rose-subtle rounded-4 p-3 mb-4 d-none">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center">
                                <div class="bg-rose text-white rounded-circle p-2 me-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                                    <i class="bi bi-magic fs-6"></i>
                                </div>
                                <div>
                                    <strong class="text-dark d-block">Welcome back!</strong>
                                    <span class="small text-muted" id="guest-autofill-preview">We found your previously used delivery details.</span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-brand rounded-pill px-3" id="btn-apply-guest-autofill">
                                    <i class="bi bi-check2-circle me-1"></i> Auto-fill Details
                                </button>
                                <button type="button" class="btn btn-sm btn-link text-muted p-1 text-decoration-none" id="btn-dismiss-guest-autofill" title="Dismiss">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- GUEST STEP 1: Contact Information -->
                    <div class="card border-0 shadow-sm p-4 bg-white mb-4 rounded-4">
                        <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">
                            <i class="bi bi-person-vcard text-rose me-2"></i> 1. Contact Information
                        </h5>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="guest_name" class="form-label small fw-bold text-muted">Your Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="guest_name" name="guest_name" value="<?php echo e($_POST['guest_name'] ?? ''); ?>" placeholder="e.g. Ananya Sharma" autocomplete="shipping name" autocapitalize="words" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="guest_phone" class="form-label small fw-bold text-muted">Mobile Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="guest_phone" name="guest_phone" value="<?php echo e($_POST['guest_phone'] ?? ''); ?>" placeholder="e.g. 9876543210" autocomplete="shipping tel" inputmode="tel" required>
                            </div>
                            <div class="col-12">
                                <label for="guest_email" class="form-label small fw-bold text-muted">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="guest_email" name="guest_email" value="<?php echo e($_POST['guest_email'] ?? ''); ?>" placeholder="e.g. ananya@example.com" autocomplete="email" inputmode="email" required>
                                <div class="form-text small text-muted">Your order confirmation and tracking details will be sent here.</div>
                            </div>
                        </div>
                    </div>

                    <!-- GUEST STEP 2: Delivery Address -->
                    <div class="card border-0 shadow-sm p-4 bg-white mb-4 rounded-4">
                        <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">
                            <i class="bi bi-geo-alt text-rose me-2"></i> 2. Delivery Address
                        </h5>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="guest_address_line1" class="form-label small fw-bold text-muted">Flat / House No., Building Name, Street <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="guest_address_line1" name="guest_address_line1" value="<?php echo e($_POST['guest_address_line1'] ?? ''); ?>" placeholder="e.g. Flat 402, Rosewood Heights, MG Road" autocomplete="shipping address-line1" required>
                            </div>
                            <div class="col-12">
                                <label for="guest_address_line2" class="form-label small fw-bold text-muted">Area / Locality / Landmark (Optional)</label>
                                <input type="text" class="form-control" id="guest_address_line2" name="guest_address_line2" value="<?php echo e($_POST['guest_address_line2'] ?? ''); ?>" placeholder="e.g. Near City Center Mall" autocomplete="shipping address-line2">
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="guest_pincode" class="form-label small fw-bold text-muted">PIN Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="guest_pincode" name="guest_pincode" value="<?php echo e($_POST['guest_pincode'] ?? ''); ?>" placeholder="e.g. 400001" autocomplete="shipping postal-code" inputmode="numeric" maxlength="6" required>
                                <div id="guest-pincode-status" class="form-text small"></div>
                            </div>
                            <div class="col-6 col-md-4">
                                <label for="guest_city" class="form-label small fw-bold text-muted">City <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="guest_city" name="guest_city" value="<?php echo e($_POST['guest_city'] ?? ''); ?>" placeholder="e.g. Mumbai" autocomplete="shipping address-level2" required>
                            </div>
                            <div class="col-6 col-md-4">
                                <label for="guest_state" class="form-label small fw-bold text-muted">State <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="guest_state" name="guest_state" value="<?php echo e($_POST['guest_state'] ?? ''); ?>" placeholder="e.g. Maharashtra" autocomplete="shipping address-level1" required>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- LOGGED-IN: Saved Address Selector Card -->
                    <div class="card border-0 shadow-sm p-4 bg-white mb-4 rounded-4">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-geo-alt text-rose me-2"></i> 1. Delivery Address
                            </h5>
                            <button type="button" class="btn btn-sm btn-brand-outline rounded-pill btn-add-checkout-address" data-bs-toggle="modal" data-bs-target="#modalCheckoutAddress">
                                <i class="bi bi-plus-circle me-1"></i> Add New Address
                            </button>
                        </div>
                        
                        <div id="checkout-addresses-list">
                            <?php if (empty($addresses)): ?>
                                <div class="alert alert-light border p-3 mb-3" id="no-address-alert">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-info-circle text-rose fs-5 me-2"></i>
                                        <strong>No saved address found.</strong>
                                    </div>
                                    <p class="small text-muted mb-3">Please provide your delivery address below to complete your order.</p>
                                    
                                    <div class="row g-2">
                                        <div class="col-12 col-md-6 mb-2">
                                            <label class="form-label small text-muted">Receiver's Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" name="new_receiver_name" value="<?php echo e($logged_in_customer['name'] ?? ''); ?>" placeholder="e.g. Jane Smith">
                                        </div>
                                        <div class="col-12 col-md-6 mb-2">
                                            <label class="form-label small text-muted">Contact Phone <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" name="new_receiver_phone" value="<?php echo e($logged_in_customer['phone'] ?? ''); ?>" placeholder="e.g. +91 98765 43210">
                                        </div>
                                        <div class="col-12 mb-2">
                                            <label class="form-label small text-muted">Address Line 1 <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" name="new_address_line1" placeholder="House/Flat No., Building Name, Street">
                                        </div>
                                        <div class="col-12 mb-2">
                                            <label class="form-label small text-muted">Address Line 2 (Optional)</label>
                                            <input type="text" class="form-control form-control-sm" name="new_address_line2" placeholder="Area, Landmark">
                                        </div>
                                        <div class="col-6 col-md-4 mb-2">
                                            <label class="form-label small text-muted">City <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" name="new_city" placeholder="City">
                                        </div>
                                        <div class="col-6 col-md-4 mb-2">
                                            <label class="form-label small text-muted">State <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" name="new_state" placeholder="State">
                                        </div>
                                        <div class="col-12 col-md-4 mb-2">
                                            <label class="form-label small text-muted">Pincode <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" name="new_pincode" placeholder="Pincode">
                                        </div>
                                        <div class="col-12 mt-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="new_is_default" value="1" id="inline_is_default" checked>
                                                <label class="form-check-label small text-muted" for="inline_is_default">
                                                    Save this address for future orders
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="row g-3" id="address-cards-row">
                                    <?php foreach ($addresses as $index => $addr): ?>
                                        <?php $is_checked = ($index === 0 && $addr['is_default'] == 1) || ($index === 0 && !array_filter($addresses, fn($a) => $a['is_default'] == 1)) || ($addr['is_default'] == 1); ?>
                                        <div class="col-12 address-card-wrapper" id="addr-card-item-<?php echo $addr['id']; ?>">
                                            <div class="border rounded p-3 address-selection-card <?php echo $is_checked ? 'border-rose bg-brand-blush' : 'bg-light-subtle'; ?>" style="cursor: pointer; transition: all 0.2s ease;">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="form-check flex-grow-1">
                                                        <input class="form-check-input address-radio" type="radio" name="address_id" id="addr_radio_<?php echo $addr['id']; ?>" value="<?php echo $addr['id']; ?>" <?php echo $is_checked ? 'checked' : ''; ?>>
                                                        <label class="form-check-label d-block text-dark fw-bold mb-1" for="addr_radio_<?php echo $addr['id']; ?>">
                                                            <?php echo e($addr['receiver_name']); ?> 
                                                            <span class="badge bg-secondary small ms-1"><?php echo e($addr['address_type']); ?></span>
                                                            <?php if($addr['is_default'] == 1): ?>
                                                                <span class="badge bg-success small ms-1">Default</span>
                                                            <?php endif; ?>
                                                        </label>
                                                        <div class="small text-muted mb-1"><i class="bi bi-phone me-1"></i> <?php echo e($addr['receiver_phone']); ?></div>
                                                        <div class="small text-secondary">
                                                            <?php echo e($addr['address_line1']); ?><?php if($addr['address_line2']) echo ', ' . e($addr['address_line2']); ?>, <?php echo e($addr['city']); ?>, <?php echo e($addr['state']); ?> - <strong><?php echo e($addr['pincode']); ?></strong>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 btn-edit-checkout-address" 
                                                                data-id="<?php echo $addr['id']; ?>"
                                                                data-type="<?php echo e($addr['address_type']); ?>"
                                                                data-name="<?php echo e($addr['receiver_name']); ?>"
                                                                data-phone="<?php echo e($addr['receiver_phone']); ?>"
                                                                data-line1="<?php echo e($addr['address_line1']); ?>"
                                                                data-line2="<?php echo e($addr['address_line2']); ?>"
                                                                data-city="<?php echo e($addr['city']); ?>"
                                                                data-state="<?php echo e($addr['state']); ?>"
                                                                data-pincode="<?php echo e($addr['pincode']); ?>"
                                                                data-default="<?php echo $addr['is_default']; ?>"
                                                                style="font-size:11px;" title="Edit Address">
                                                            <i class="bi bi-pencil me-1"></i> Edit
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- STEP: Payment Selection Card -->
                <div class="card border-0 shadow-sm p-4 bg-white mb-4 rounded-4">
                    <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">
                        <i class="bi bi-credit-card text-rose me-2"></i> <?php echo $is_logged_in ? '2.' : '3.'; ?> Payment Method
                    </h5>

                    <?php if (defined('SITE_NOTICE_ENABLED') && SITE_NOTICE_ENABLED): ?>
                        <div class="alert alert-warning border d-flex align-items-center gap-2 mb-3 py-2 px-3 small rounded-3">
                            <i class="bi bi-info-circle-fill fs-5 text-warning flex-shrink-0"></i>
                            <div>
                                <strong>Preview & Testing Notice:</strong> <?php echo e(SITE_NOTICE_TEXT); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (RAZORPAY_ENABLED): ?>
                        <div class="border rounded-3 p-3 bg-light-subtle mb-3 payment-option-card">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="pay_razorpay" value="Razorpay" checked>
                                <label class="form-check-label text-dark fw-bold d-flex align-items-center justify-content-between flex-wrap gap-2" for="pay_razorpay">
                                    <span><i class="bi bi-credit-card-2-front-fill me-1 text-primary"></i> Online Payment (Razorpay)</span>
                                    <span class="d-flex gap-1 fs-6">
                                        <span class="badge bg-white text-dark border"><i class="bi bi-qr-code me-1 text-primary"></i>UPI / QR</span>
                                        <span class="badge bg-white text-dark border"><i class="bi bi-credit-card me-1 text-success"></i>Cards</span>
                                        <span class="badge bg-white text-dark border"><i class="bi bi-bank me-1 text-info"></i>NetBanking</span>
                                    </span>
                                </label>
                                <span class="text-muted small d-block mt-1">Instant, encrypted checkout via Google Pay, PhonePe, Paytm, Cards, Net Banking, or Wallets.</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (COD_ENABLED || !RAZORPAY_ENABLED): ?>
                        <div class="border rounded-3 p-3 bg-light-subtle mb-3 payment-option-card">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="pay_cod" value="COD" <?php echo !RAZORPAY_ENABLED ? 'checked' : ''; ?>>
                                <label class="form-check-label text-dark fw-bold" for="pay_cod">
                                    <i class="bi bi-cash-stack me-1 text-success"></i> Cash on Delivery (COD) / Pay on Delivery
                                    <?php if (COD_SHIPPING_CHARGE > 0): ?>
                                        <span class="badge bg-warning-subtle text-dark border ms-1">+<?php echo format_price(COD_SHIPPING_CHARGE); ?> fee</span>
                                    <?php endif; ?>
                                </label>
                                <span class="text-muted small d-block mt-1">Pay with Cash, UPI, or Card at your doorstep when the delivery partner arrives.</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="p-3 bg-light rounded-3 border small text-muted d-flex align-items-center gap-2">
                        <i class="bi bi-shield-check text-success fs-5"></i>
                        <span>100% Safe & Secure Payment Guarantee. All items delivered in tamper-proof, discreet packaging.</span>
                    </div>
                </div>

                <!-- Submit Button in Form -->
                <div class="d-none d-lg-block">
                    <button type="submit" name="place_order" class="btn btn-brand w-100 py-3 rounded-pill fw-bold shadow-sm fs-5">
                        <i class="bi bi-lock-fill me-2"></i> Complete Order &bull; <?php echo format_price($grand_total); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Right Column: Order Review & Financial Summary -->
        <div class="col-12 col-lg-5">
            <!-- Items summary card -->
            <div class="card border-0 shadow-sm p-4 bg-white mb-4 rounded-4">
                <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">
                    <i class="bi bi-bag-check me-2 text-rose"></i> Order Summary (<?php echo count($cart_items); ?> items)
                </h5>

                <div class="checkout-items-list mb-3" style="max-height: 280px; overflow-y: auto;">
                    <?php foreach ($cart_items as $item): ?>
                        <?php 
                        $price = $item['unit_price'];
                        $is_combo = !empty($item['combo_product_id']);
                        $img_src = !empty($item['main_image']) ? 'uploads/' . $item['main_image'] : 'assets/images/logo.png';
                        ?>
                        <div class="d-flex gap-3 py-2 border-bottom align-items-center">
                            <div class="flex-shrink-0 position-relative">
                                <img src="<?php echo e($img_src); ?>" alt="<?php echo e($item['product_name']); ?>" class="rounded-3 object-fit-cover" style="width: 52px; height: 64px; background-color: #f8f6f5;" onerror="this.src='assets/images/logo.png';">
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark text-white" style="font-size: 10px;">
                                    <?php echo $item['quantity']; ?>
                                </span>
                            </div>
                            <div class="flex-grow-1 small">
                                <span class="fw-bold text-dark d-block text-truncate" style="max-width: 180px;"><?php echo e($item['product_name']); ?></span>
                                <?php if ($is_combo): ?>
                                    <span class="badge bg-purple-subtle text-purple border" style="font-size: 10px;"><i class="bi bi-stars"></i> Curated Set</span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 11px;">
                                        Size: <strong><?php echo e($item['size_code']); ?></strong> &bull; Color: <strong><?php echo e($item['color_name']); ?></strong>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-end fw-bold small text-dark flex-shrink-0">
                                <?php echo format_price($price * $item['quantity']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Coupon Form -->
                <div class="border-top pt-3 mb-3">
                    <?php if (isset($_SESSION['checkout_coupon'])): ?>
                        <div class="alert alert-success d-flex justify-content-between align-items-center p-2 small mb-0">
                            <div>
                                <i class="bi bi-tag-fill me-1"></i> Promo <strong><?php echo e($_SESSION['checkout_coupon']['code']); ?></strong> applied!
                            </div>
                            <a href="checkout.php?remove_coupon=1" class="btn btn-sm btn-link text-danger p-0 fw-bold" style="text-decoration:none;">Remove</a>
                        </div>
                    <?php else: ?>
                        <form action="checkout.php" method="POST" class="d-flex gap-2">
                            <?php echo csrf_field(); ?>
                            <input type="text" name="coupon_code" class="form-control form-control-sm text-uppercase font-monospace" placeholder="Enter Coupon Code" required>
                            <button type="submit" name="apply_coupon" class="btn btn-sm btn-dark rounded-pill px-3">Apply</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Price Breakdown -->
                <div class="border-top pt-3 small">
                    <div class="d-flex justify-content-between text-muted mb-2">
                        <span>Subtotal:</span>
                        <span class="fw-semibold text-dark"><?php echo format_price($subtotal); ?></span>
                    </div>

                    <?php if ($discount > 0): ?>
                        <div class="d-flex justify-content-between text-success mb-2">
                            <span>Promo Discount:</span>
                            <span class="fw-semibold">-<?php echo format_price($discount); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between text-muted mb-2">
                        <span>Shipping & Packaging:</span>
                        <?php if ($shipping == 0): ?>
                            <span class="badge bg-success-subtle text-success">FREE Shipping</span>
                        <?php else: ?>
                            <span class="fw-semibold text-dark"><?php echo format_price($shipping); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-between text-muted mb-3">
                        <span>Taxes & GST (Included):</span>
                        <span><?php echo format_price($tax_amount); ?></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-top pt-3">
                        <span class="fw-bold text-dark fs-5">Grand Total:</span>
                        <span class="fw-bold text-rose fs-4 font-display"><?php echo format_price($grand_total); ?></span>
                    </div>
                </div>

                <!-- Mobile place order button -->
                <div class="d-block d-lg-none mt-4">
                    <button type="button" class="btn btn-brand w-100 py-3 rounded-pill fw-bold shadow-sm fs-5" onclick="document.getElementById('checkout-form').submit();">
                        <i class="bi bi-lock-fill me-2"></i> Complete Order &bull; <?php echo format_price($grand_total); ?>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal: Add / Edit Address on Checkout -->
<div class="modal fade" id="modalCheckoutAddress" tabindex="-1" aria-labelledby="modalCheckoutAddressLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="modalCheckoutAddressLabel">
                    <i class="bi bi-geo-alt-fill text-rose me-2"></i> <span id="modalAddressTitleText">Add New Address</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCheckoutAddressModal">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="address_id" id="modal_address_id" value="">
                
                <div class="modal-body p-4">
                    <div class="alert alert-danger d-none p-2 small mb-3" id="modalAddressError"></div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="modal_receiver_name" class="form-label small fw-bold">Receiver's Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="receiver_name" id="modal_receiver_name" autocomplete="shipping name" autocapitalize="words" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="modal_receiver_phone" class="form-label small fw-bold">Contact Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="receiver_phone" id="modal_receiver_phone" autocomplete="shipping tel" inputmode="tel" required>
                        </div>
                        <div class="col-12">
                            <label for="modal_address_line1" class="form-label small fw-bold">Address Line 1 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="address_line1" id="modal_address_line1" placeholder="Flat, Building, Street" autocomplete="shipping address-line1" required>
                        </div>
                        <div class="col-12">
                            <label for="modal_address_line2" class="form-label small fw-bold">Address Line 2 (Optional)</label>
                            <input type="text" class="form-control" name="address_line2" id="modal_address_line2" placeholder="Area, Landmark" autocomplete="shipping address-line2">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="modal_pincode" class="form-label small fw-bold">Pincode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="pincode" id="modal_pincode" autocomplete="shipping postal-code" inputmode="numeric" maxlength="6" required>
                            <div id="modal-pincode-status" class="form-text small"></div>
                        </div>
                        <div class="col-6 col-md-4">
                            <label for="modal_city" class="form-label small fw-bold">City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="city" id="modal_city" autocomplete="shipping address-level2" required>
                        </div>
                        <div class="col-6 col-md-4">
                            <label for="modal_state" class="form-label small fw-bold">State <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="state" id="modal_state" autocomplete="shipping address-level1" required>
                        </div>
                        <div class="col-12">
                            <label for="modal_address_type" class="form-label small fw-bold">Address Type</label>
                            <select class="form-select" name="address_type" id="modal_address_type">
                                <option value="Home">Home</option>
                                <option value="Office">Office</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="modal_is_default" checked>
                                <label class="form-check-label small" for="modal_is_default">
                                    Set as default shipping address
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top bg-light p-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand rounded-pill px-4" id="btnSaveCheckoutAddressModal">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="spinnerAddressModal" role="status"></span>
                        <span id="btnSaveAddressText">Save & Select Address</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if (isset($razorpay_order_payload)): ?>
    <!-- Razorpay Checkout Integration Script -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const rzpOptions = <?php echo json_encode($razorpay_order_payload); ?>;
        
        rzpOptions.handler = function(response) {
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.92);z-index:999999;display:flex;align-items:center;justify-content:center;flex-direction:column;backdrop-filter:blur(5px);';
            overlay.innerHTML = '<div class="spinner-border text-danger mb-3" style="width:3rem;height:3rem;" role="status"></div><h4 class="fw-bold text-dark">Confirming Payment...</h4><p class="text-muted small">Please do not refresh or close this window.</p>';
            document.body.appendChild(overlay);

            fetch('process-razorpay.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    csrf_token: '<?php echo generate_csrf_token(); ?>',
                    order_number: rzpOptions.order_number,
                    razorpay_payment_id: response.razorpay_payment_id || '',
                    razorpay_order_id: response.razorpay_order_id || '',
                    razorpay_signature: response.razorpay_signature || ''
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    alert('Payment processed. Redirecting to confirmation...');
                    window.location.href = 'order-success.php?order_num=' + encodeURIComponent(rzpOptions.order_number);
                }
            })
            .catch(err => {
                window.location.href = 'order-success.php?order_num=' + encodeURIComponent(rzpOptions.order_number);
            });
        };

        rzpOptions.modal = {
            ondismiss: function() {
                alert('Payment was not completed. Your order #' + rzpOptions.order_number + ' is saved as Pending. You can retry payment from Order History.');
                window.location.href = 'orders.php';
            }
        };

        const rzp = new Razorpay(rzpOptions);
        rzp.open();
    });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
