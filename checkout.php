<?php
// checkout.php
// Modern State-of-the-Art Customer & Guest Checkout Flow
// Inspired by Shopify 1-Page Checkout with Distraction-Free Layout & Razorpay Integration

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';

$meta_title = 'Secure Checkout | ' . SITE_NAME;

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
        if ((isset($_GET['action']) && $_GET['action'] === 'init_razorpay') || isset($_POST['init_razorpay'])) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'error' => 'Your shopping cart is empty.']);
            exit;
        }
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
        $error = 'Security check failed. Please refresh.';
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
$is_ajax_init_rzp = (isset($_GET['action']) && $_GET['action'] === 'init_razorpay') || isset($_POST['init_razorpay']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['place_order']) || $is_ajax_init_rzp)) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security check failed. Please refresh the page.';
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

            // 1. Resolve receiver name across all possible inputs (first_name + last_name, guest_name, new_receiver_name, or customer profile)
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $combined_name = trim($first_name . ' ' . $last_name);
            $new_receiver_name = !empty($_POST['new_receiver_name']) 
                ? trim($_POST['new_receiver_name']) 
                : (!empty($_POST['guest_name']) 
                    ? trim($_POST['guest_name']) 
                    : (!empty($combined_name) ? $combined_name : ($logged_in_customer['name'] ?? '')));

            // 2. Resolve phone across inputs
            $new_receiver_phone = trim($_POST['new_receiver_phone'] ?? ($_POST['guest_phone'] ?? ($logged_in_customer['phone'] ?? '')));

            // 3. Resolve address lines (supports both new_* and guest_* naming conventions)
            $new_address_line1 = trim($_POST['new_address_line1'] ?? ($_POST['guest_address_line1'] ?? ''));
            $new_address_line2 = trim($_POST['new_address_line2'] ?? ($_POST['guest_address_line2'] ?? ''));
            $guest_company = trim($_POST['company'] ?? '');
            if (!empty($guest_company) && empty($new_address_line2)) {
                $new_address_line2 = 'Company: ' . $guest_company;
            }

            // 4. Resolve city, state, pincode
            $new_city = trim($_POST['new_city'] ?? ($_POST['guest_city'] ?? ''));
            $new_state = trim($_POST['new_state'] ?? ($_POST['guest_state'] ?? ''));
            $new_pincode = trim($_POST['new_pincode'] ?? ($_POST['guest_pincode'] ?? ''));
            $new_address_type = trim($_POST['new_address_type'] ?? 'Home');
            $new_is_default = (isset($_POST['new_is_default']) || isset($_POST['save_info']) || empty($addresses)) ? 1 : 0;

            // 5. If no saved address was selected, check if user typed in a new address
            if ($selected_address_id <= 0 && (!empty($new_address_line1) || !empty($new_receiver_name) || !empty($new_city) || !empty($new_pincode))) {
                if (empty($new_receiver_name)) {
                    $new_receiver_name = !empty($logged_in_customer['name']) ? $logged_in_customer['name'] : 'Customer';
                }

                if (empty($new_receiver_phone) || empty($new_address_line1) || empty($new_city) || empty($new_state) || empty($new_pincode)) {
                    $error = 'Please fill in all required shipping address fields.';
                } else {
                    try {
                        $ins_addr = $pdo->prepare("
                            INSERT INTO addresses (customer_id, address_type, receiver_name, receiver_phone, address_line1, address_line2, city, state, pincode, is_default) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins_addr->execute([$customer_id, $new_address_type, $new_receiver_name, $new_receiver_phone, $new_address_line1, $new_address_line2, $new_city, $new_state, $new_pincode, $new_is_default]);
                        $selected_address_id = (int)$pdo->lastInsertId();
                    } catch (PDOException $e) {
                        // In case address insert fails (e.g. temporary DB lock), do not block checkout
                    }

                    // Populate delivery details directly
                    $delivery_name = $new_receiver_name;
                    $delivery_phone = $new_receiver_phone;
                    $delivery_line1 = $new_address_line1;
                    $delivery_line2 = $new_address_line2;
                    $delivery_city = $new_city;
                    $delivery_state = $new_state;
                    $delivery_pincode = $new_pincode;

                    // Update customer account phone if empty
                    if (empty($logged_in_customer['phone']) && !empty($new_receiver_phone)) {
                        try {
                            $up_phone = $pdo->prepare("UPDATE customers SET phone = ? WHERE id = ? AND (phone IS NULL OR phone = '')");
                            $up_phone->execute([$new_receiver_phone, $customer_id]);
                        } catch (Exception $e) {}
                    }
                }
            }

            // 6. If an existing saved address was chosen
            if ($selected_address_id > 0 && empty($delivery_line1) && empty($error)) {
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
            } elseif ($selected_address_id <= 0 && empty($delivery_line1) && empty($error)) {
                $error = 'Please select or provide a delivery address.';
            }
        } else {
            // Guest Checkout Flow - Supports both single-name and split-name inputs seamlessly
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $combined_name = trim($first_name . ' ' . $last_name);
            $guest_name = !empty($_POST['guest_name']) ? trim($_POST['guest_name']) : $combined_name;

            // Supports single contact input (Email or Mobile) or dedicated inputs
            $contact_val = trim($_POST['contact_email_phone'] ?? '');
            $guest_email = trim($_POST['guest_email'] ?? '');
            $guest_phone = trim($_POST['guest_phone'] ?? '');

            if (empty($guest_email) && filter_var($contact_val, FILTER_VALIDATE_EMAIL)) {
                $guest_email = $contact_val;
            }
            if (empty($guest_phone) && preg_match('/^[0-9+\s\-]{7,15}$/', $contact_val)) {
                $guest_phone = $contact_val;
            }

            $guest_line1 = trim($_POST['guest_address_line1'] ?? '');
            $guest_line2 = trim($_POST['guest_address_line2'] ?? '');
            $guest_city = trim($_POST['guest_city'] ?? '');
            $guest_state = trim($_POST['guest_state'] ?? '');
            $guest_pincode = trim($_POST['guest_pincode'] ?? '');
            $guest_company = trim($_POST['company'] ?? '');
            if (!empty($guest_company) && empty($guest_line2)) {
                $guest_line2 = 'Company: ' . $guest_company;
            }

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
                        $raw_pwd = bin2hex(random_bytes(6));
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

        // If AJAX request encountered an error, respond with JSON immediately
        if ($is_ajax_init_rzp && !empty($error)) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }

        // Execute Order Handling
        if (empty($error) && !empty($order_customer_id) && !empty($delivery_name)) {
            try {
                if ($payment_method === 'Razorpay') {
                    // 1. Verify stock is available for each item (do NOT deduct yet)
                    foreach ($cart_items as $item) {
                        if (!empty($item['combo_product_id']) && !empty($item['parsed_combo_config'])) {
                            foreach ($item['parsed_combo_config'] as $sub_var) {
                                $stock_stmt = $pdo->prepare("SELECT stock, sku FROM product_variants WHERE id = ?");
                                $stock_stmt->execute([$sub_var['variant_id']]);
                                $var = $stock_stmt->fetch();
                                if (!$var || (int)$var['stock'] < $item['quantity']) {
                                    throw new Exception("Combo set component SKU " . ($var['sku'] ?? $sub_var['sku']) . " is out of stock.");
                                }
                            }
                        } else {
                            $stock_stmt = $pdo->prepare("SELECT stock, sku FROM product_variants WHERE id = ?");
                            $stock_stmt->execute([$item['variant_id']]);
                            $var = $stock_stmt->fetch();
                            if (!$var || (int)$var['stock'] < $item['quantity']) {
                                throw new Exception("Product SKU " . ($var['sku'] ?? $item['variant_sku']) . " is currently out of stock.");
                            }
                        }
                    }

                    $order_number = generate_order_number();
                    $customer_email_address = $is_logged_in ? ($logged_in_customer['email'] ?? '') : ($guest_email ?? '');

                    require_once __DIR__ . '/includes/razorpay.php';
                    $amount_in_paise = (int)round($grand_total * 100);

                    $rzp_order = create_razorpay_order(
                        $amount_in_paise,
                        CURRENCY_CODE,
                        $order_number,
                        [
                            'order_number' => $order_number,
                            'customer_email' => $customer_email_address
                        ]
                    );

                    $razorpay_order_id = $rzp_order['order_id'];

                    // Construct pending checkout dataset
                    $pending_data = [
                        'order_number' => $order_number,
                        'razorpay_order_id' => $razorpay_order_id,
                        'customer_id' => $order_customer_id,
                        'customer_email' => $customer_email_address,
                        'delivery_name' => $delivery_name,
                        'delivery_phone' => $delivery_phone,
                        'delivery_line1' => $delivery_line1,
                        'delivery_line2' => $delivery_line2,
                        'delivery_city' => $delivery_city,
                        'delivery_state' => $delivery_state,
                        'delivery_pincode' => $delivery_pincode,
                        'subtotal' => $subtotal,
                        'coupon_code' => !empty($coupon_code) ? $coupon_code : null,
                        'coupon_id' => $_SESSION['checkout_coupon']['id'] ?? null,
                        'discount' => $discount,
                        'shipping' => $shipping,
                        'tax_amount' => $tax_amount,
                        'grand_total' => $grand_total,
                        'cart_items' => $cart_items,
                        'session_id' => $session_id
                    ];

                    // Store in session
                    $_SESSION['pending_checkout'] = $pending_data;

                    // Persist in pending_checkouts table for webhook redundancy
                    $ins_pc = $pdo->prepare("
                        INSERT INTO pending_checkouts (razorpay_order_id, order_number, customer_id, checkout_data) 
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE checkout_data = VALUES(checkout_data), customer_id = VALUES(customer_id)
                    ");
                    $ins_pc->execute([$razorpay_order_id, $order_number, $order_customer_id, json_encode($pending_data)]);

                    // Prepare Razorpay payload for frontend invocation
                    $razorpay_order_payload = [
                        'key' => RAZORPAY_KEY_ID,
                        'amount' => $amount_in_paise,
                        'currency' => CURRENCY_CODE,
                        'name' => SITE_NAME,
                        'description' => "Order #" . $order_number,
                        'order_number' => $order_number,
                        'order_id' => $razorpay_order_id,
                        'prefill' => [
                            'name' => $delivery_name,
                            'email' => $customer_email_address,
                            'contact' => $delivery_phone
                        ],
                        'theme' => [
                            'color' => '#E12B56'
                        ]
                    ];

                    if ($is_ajax_init_rzp) {
                        header('Content-Type: application/json; charset=UTF-8');
                        echo json_encode([
                            'success' => true,
                            'razorpay_payload' => $razorpay_order_payload
                        ]);
                        exit;
                    }
                } else {
                    // COD Flow: Place order immediately
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
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'COD', 'pending', 'pending')
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
                        $grand_total
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
                        $cust_email_for_mail = $is_logged_in ? ($logged_in_customer['email'] ?? '') : ($guest_email ?? '');
                        if (!empty($cust_email_for_mail)) {
                            require_once __DIR__ . '/includes/mail.php';
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
                $error = 'Checkout failed: ' . $e->getMessage();
                if ($is_ajax_init_rzp) {
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode(['success' => false, 'error' => $error]);
                    exit;
                }
            }
        }
    }
}

// Indian States dictionary for dropdown
$indian_states = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 
    'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 
    'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 
    'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 
    'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Andaman and Nicobar Islands', 
    'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Jammu & Kashmir', 
    'Ladakh', 'Lakshadweep', 'Puducherry'
];

$preselected_state = $_POST['guest_state'] ?? 'Kerala';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($meta_title); ?></title>
    <link rel="shortcut icon" href="assets/images/logo.png" type="image/png">

    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Site Styles -->
    <link rel="stylesheet" href="assets/css/style.css?v=2.8">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-primary: #E12B56;
            --brand-hover: #C21845;
            --shopify-bg: #FFFFFF;
            --shopify-sidebar: #FAFAFA;
            --shopify-border: #D9D9D9;
            --shopify-border-focus: #111111;
            --shopify-text: #18181B;
            --shopify-muted: #71717A;
            --shopify-subtle-bg: #F4F4F5;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--shopify-text);
            background-color: var(--shopify-bg);
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* Distraction-Free Header */
        .checkout-topbar {
            border-bottom: 1px solid #EEEEEE;
            background: #FFFFFF;
            padding: 16px 0;
        }

        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Layout Grid */
        .checkout-split {
            display: flex;
            min-height: calc(100vh - 75px);
        }

        .checkout-main-col {
            flex: 1 1 58%;
            padding: 32px 48px 48px 16px;
            max-width: 650px;
            margin-left: auto;
            width: 100%;
        }

        .checkout-sidebar-col {
            flex: 1 1 42%;
            background-color: var(--shopify-sidebar);
            border-left: 1px solid #E5E7EB;
            padding: 32px 16px 48px 48px;
            position: sticky;
            top: 0;
            height: fit-content;
            min-height: calc(100vh - 75px);
            width: 100%;
        }

        /* Clean Inputs matching Reference */
        .shopify-input {
            border: 1px solid var(--shopify-border);
            border-radius: 6px;
            font-size: 14px;
            color: #111111;
            background-color: #FFFFFF;
            transition: all 0.15s ease-in-out;
        }

        .shopify-input:not(.form-floating > .shopify-input) {
            padding: 12px 14px;
        }

        .shopify-input:focus {
            border-color: var(--shopify-border-focus);
            box-shadow: 0 0 0 1px var(--shopify-border-focus);
            outline: none;
        }

        .shopify-input::placeholder {
            color: #8C8C8C;
        }

        .form-check-input:checked {
            background-color: #111111;
            border-color: #111111;
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 2px rgba(17,17,17,0.15);
        }

        /* Grouped Card Panels */
        .shopify-card-group {
            border: 1px solid var(--shopify-border);
            border-radius: 8px;
            overflow: hidden;
            background: #FFFFFF;
        }

        .shopify-card-item {
            padding: 14px 16px;
            border-bottom: 1px solid #EBEBEB;
            transition: background-color 0.15s ease;
        }

        .shopify-card-item:last-child {
            border-bottom: none;
        }

        .shopify-card-item.selected {
            background-color: #FBFBFC;
        }

        .shopify-expanded-panel {
            background-color: #F8F9FA;
            border-top: 1px solid #EBEBEB;
            padding: 16px;
            font-size: 13.5px;
            color: #4B5563;
        }

        /* Order Summary Line Items */
        .item-thumbnail-wrap {
            position: relative;
            width: 64px;
            height: 64px;
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            background: #FFFFFF;
            flex-shrink: 0;
        }

        .item-thumbnail-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 7px;
        }

        .item-qty-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: rgba(113, 113, 122, 0.95);
            color: #FFFFFF;
            font-size: 11px;
            font-weight: 600;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }

        /* Big Pay Now Button */
        .btn-pay-now {
            background-color: #111111;
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            padding: 16px 24px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: all 0.2s ease;
            letter-spacing: 0.3px;
        }

        .btn-pay-now:hover {
            background-color: #262626;
            color: #FFFFFF;
        }

        /* Policy Links Footer */
        .checkout-policy-links a {
            color: #71717A;
            text-decoration: underline;
            font-size: 12.5px;
            transition: color 0.15s ease;
        }

        .checkout-policy-links a:hover {
            color: #111111;
        }

        /* Mobile Summary Accordion */
        .mobile-summary-bar {
            background-color: #FAFAFA;
            border-bottom: 1px solid #E5E7EB;
            padding: 14px 16px;
            cursor: pointer;
            user-select: none;
        }

        /* Mobile Responsive */
        @media (max-width: 991.98px) {
            .checkout-split {
                flex-direction: column-reverse;
            }
            .checkout-main-col {
                max-width: 100%;
                padding: 24px 16px 48px;
            }
            .checkout-sidebar-col {
                display: none;
                border-left: none;
                border-bottom: 1px solid #E5E7EB;
                padding: 20px 16px;
                position: static;
                height: auto;
                min-height: auto;
            }
            .checkout-sidebar-col.show-mobile {
                display: block;
            }
        }
    </style>
</head>
<body>

    <!-- Top Distraction-Free Header -->
    <header class="checkout-topbar">
        <div class="container checkout-container">
            <div class="d-flex align-items-center justify-content-between">
                <a href="index.php" title="Return to store" class="d-inline-flex align-items-center text-decoration-none">
                    <img src="assets/images/logo.png" alt="<?php echo e(SITE_NAME); ?>" height="36" onerror="this.src='assets/images/pookky-logo-white.png';">
                </a>
                <div class="d-flex align-items-center">
                    <a href="cart.php" class="text-dark small text-decoration-none fw-semibold">
                        <i class="bi bi-bag me-1"></i> Cart
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Collapsible Order Summary Bar -->
    <div class="d-lg-none mobile-summary-bar" id="mobileSummaryToggle">
        <div class="container checkout-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center text-dark small fw-semibold">
                <i class="bi bi-cart3 me-2 fs-6"></i>
                <span id="mobileSummaryText">Show order summary</span>
                <i class="bi bi-chevron-down ms-1" id="mobileSummaryIcon"></i>
            </div>
            <div class="fw-bold text-dark">
                INR <?php echo format_price($grand_total); ?>
            </div>
        </div>
    </div>

    <!-- Main Container Split -->
    <div class="container checkout-container">
        <div class="checkout-split">

            <!-- LEFT COLUMN: Checkout Form -->
            <div class="checkout-main-col">

                <div id="checkout-alert-box"></div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4 d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill fs-6 text-danger"></i>
                        <div><?php echo e($error); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success py-2 px-3 small rounded-3 mb-4 d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill fs-6 text-success"></i>
                        <div><?php echo e($success); ?></div>
                    </div>
                <?php endif; ?>

                <!-- Returning Guest Auto-fill Banner -->
                <div id="guest-autofill-banner" class="alert alert-light border border-secondary-subtle rounded-3 p-3 mb-4 d-none">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-lightning-charge-fill text-warning fs-5 me-2"></i>
                            <div>
                                <strong class="small d-block text-dark">Welcome back!</strong>
                                <span class="small text-muted" id="guest-autofill-preview">Previously used delivery details found.</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-dark rounded-pill px-3" id="btn-apply-guest-autofill">
                                Auto-fill Details
                            </button>
                            <button type="button" class="btn btn-sm btn-link text-muted p-0 text-decoration-none" id="btn-dismiss-guest-autofill" title="Dismiss">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <form action="checkout.php" method="POST" id="checkout-form" novalidate>
                    <?php echo csrf_field(); ?>

                    <!-- SECTION 1: Contact -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-baseline mb-2">
                            <h2 class="h5 fw-bold text-dark mb-0">Contact</h2>
                            <?php if (!$is_logged_in): ?>
                                <a href="login.php?redirect=checkout.php" class="small text-decoration-none text-dark fw-semibold">
                                    Sign in
                                </a>
                            <?php else: ?>
                                <span class="small text-muted">
                                    <?php echo e($logged_in_customer['email'] ?? ''); ?> &bull; 
                                    <a href="logout.php" class="text-danger text-decoration-none ms-1">Sign out</a>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <div class="form-floating mb-2">
                                <input type="text" 
                                       class="form-control shopify-input" 
                                       id="guest_email" 
                                       name="guest_email" 
                                       value="<?php echo e($is_logged_in ? ($logged_in_customer['email'] ?? '') : ($_POST['guest_email'] ?? '')); ?>" 
                                       placeholder="Email or mobile phone number" 
                                       autocomplete="email" 
                                       required>
                                <label for="guest_email">Email or mobile phone number</label>
                            </div>
                        </div>

                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="email_offers" name="email_offers" checked>
                            <label class="form-check-label small text-muted" for="email_offers">
                                Email me with news and offers
                            </label>
                        </div>
                    </div>

                    <!-- SECTION 2: Delivery -->
                    <div class="mb-4">
                        <h2 class="h5 fw-bold text-dark mb-3">Delivery</h2>

                        <?php 
                        $default_first_name = '';
                        $default_last_name = '';
                        if ($is_logged_in && !empty($logged_in_customer['name'])) {
                            $cust_name_parts = explode(' ', trim($logged_in_customer['name']), 2);
                            $default_first_name = $cust_name_parts[0] ?? '';
                            $default_last_name = $cust_name_parts[1] ?? '';
                        }
                        $need_delivery_required = (!$is_logged_in || empty($addresses));
                        ?>

                        <?php if ($is_logged_in && !empty($addresses)): ?>
                            <!-- Saved Addresses for Logged-in Customer -->
                            <div class="shopify-card-group mb-3" id="saved-address-group">
                                <?php foreach ($addresses as $idx => $addr): ?>
                                    <?php $is_checked = ($idx === 0 && $addr['is_default'] == 1) || ($idx === 0 && !array_filter($addresses, fn($a) => $a['is_default'] == 1)) || ($addr['is_default'] == 1); ?>
                                    <label class="shopify-card-item d-flex align-items-start gap-3 <?php echo $is_checked ? 'selected' : ''; ?>" style="cursor: pointer;">
                                        <input class="form-check-input mt-1" type="radio" name="address_id" value="<?php echo $addr['id']; ?>" <?php echo $is_checked ? 'checked' : ''; ?> onchange="toggleNewAddressBox(false)">
                                        <div class="flex-grow-1 small">
                                            <div class="fw-bold text-dark">
                                                <?php echo e($addr['receiver_name']); ?> 
                                                <span class="badge bg-secondary-subtle text-secondary ms-1"><?php echo e($addr['address_type']); ?></span>
                                                <?php if($addr['is_default'] == 1): ?>
                                                    <span class="badge bg-success-subtle text-success ms-1">Default</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted mt-1">
                                                <?php echo e($addr['address_line1']); ?><?php if($addr['address_line2']) echo ', ' . e($addr['address_line2']); ?>, <?php echo e($addr['city']); ?>, <?php echo e($addr['state']); ?> - <?php echo e($addr['pincode']); ?>
                                            </div>
                                            <div class="text-muted small mt-1">Phone: <?php echo e($addr['receiver_phone']); ?></div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                                <label class="shopify-card-item d-flex align-items-center gap-3" style="cursor: pointer;">
                                    <input class="form-check-input" type="radio" name="address_id" value="0" id="radio_use_new_addr" onchange="toggleNewAddressBox(true)">
                                    <span class="small fw-semibold text-dark"><i class="bi bi-plus-circle me-1"></i> Use a different delivery address</span>
                                </label>
                            </div>
                        <?php endif; ?>

                        <!-- Delivery Address Form Fields -->
                        <div id="delivery-fields-box" class="<?php echo ($is_logged_in && !empty($addresses)) ? 'd-none' : ''; ?>">
                            <!-- Country / Region -->
                            <div class="form-floating mb-2">
                                <select class="form-select shopify-input" id="guest_country" name="country">
                                    <option value="India" selected>India</option>
                                </select>
                                <label for="guest_country">Country/Region</label>
                            </div>

                            <!-- First Name & Last Name Split Grid -->
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <div class="form-floating">
                                        <input type="text" 
                                               class="form-control shopify-input" 
                                               id="first_name" 
                                               name="first_name" 
                                               placeholder="First name" 
                                               autocomplete="shipping given-name" 
                                               value="<?php echo e($_POST['first_name'] ?? $default_first_name); ?>" 
                                               <?php echo $need_delivery_required ? 'required' : 'data-required="true"'; ?>>
                                        <label for="first_name">First name</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating">
                                        <input type="text" 
                                               class="form-control shopify-input" 
                                               id="last_name" 
                                               name="last_name" 
                                               placeholder="Last name" 
                                               autocomplete="shipping family-name" 
                                               value="<?php echo e($_POST['last_name'] ?? $default_last_name); ?>">
                                        <label for="last_name">Last name</label>
                                    </div>
                                </div>
                            </div>
                            <!-- Hidden synced guest_name for legacy scripts/autofill compatibility -->
                            <input type="hidden" id="guest_name" name="guest_name" value="<?php echo e($_POST['guest_name'] ?? ($logged_in_customer['name'] ?? '')); ?>">

                            <!-- Company (optional) -->
                            <div class="form-floating mb-2">
                                <input type="text" 
                                       class="form-control shopify-input" 
                                       id="company" 
                                       name="company" 
                                       placeholder="Company (optional)" 
                                       autocomplete="organization" 
                                       value="<?php echo e($_POST['company'] ?? ''); ?>">
                                <label for="company">Company (optional)</label>
                            </div>

                            <!-- Street Address -->
                            <div class="form-floating mb-2">
                                <input type="text" 
                                       class="form-control shopify-input" 
                                       id="guest_address_line1" 
                                       name="guest_address_line1" 
                                       placeholder="Address (House/Flat No., Building, Street)" 
                                       autocomplete="shipping address-line1" 
                                       value="<?php echo e($_POST['guest_address_line1'] ?? ''); ?>" 
                                       <?php echo $need_delivery_required ? 'required' : 'data-required="true"'; ?>>
                                <label for="guest_address_line1">Address (House/Flat No., Street)</label>
                            </div>

                            <!-- Apartment, Suite, Landmark (optional) -->
                            <div class="form-floating mb-2">
                                <input type="text" 
                                       class="form-control shopify-input" 
                                       id="guest_address_line2" 
                                       name="guest_address_line2" 
                                       placeholder="Apartment, suite, landmark, etc. (optional)" 
                                       autocomplete="shipping address-line2" 
                                       value="<?php echo e($_POST['guest_address_line2'] ?? ''); ?>">
                                <label for="guest_address_line2">Apartment, suite, landmark, etc. (optional)</label>
                            </div>

                            <!-- 3-Way Row: City | State | PIN Code -->
                            <div class="row g-2 mb-2">
                                <div class="col-12 col-md-4">
                                    <div class="form-floating">
                                        <input type="text" 
                                               class="form-control shopify-input" 
                                               id="guest_city" 
                                               name="guest_city" 
                                               placeholder="City" 
                                               autocomplete="shipping address-level2" 
                                               value="<?php echo e($_POST['guest_city'] ?? ''); ?>" 
                                               <?php echo $need_delivery_required ? 'required' : 'data-required="true"'; ?>>
                                        <label for="guest_city">City</label>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select shopify-input" id="guest_state" name="guest_state" autocomplete="shipping address-level1" <?php echo $need_delivery_required ? 'required' : 'data-required="true"'; ?>>
                                            <?php foreach ($indian_states as $st): ?>
                                                <option value="<?php echo e($st); ?>" <?php echo ($preselected_state === $st) ? 'selected' : ''; ?>>
                                                    <?php echo e($st); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="guest_state">State</label>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="form-floating">
                                        <input type="text" 
                                               class="form-control shopify-input" 
                                               id="guest_pincode" 
                                               name="guest_pincode" 
                                               placeholder="PIN code" 
                                               autocomplete="shipping postal-code" 
                                               inputmode="numeric" 
                                               maxlength="6" 
                                               value="<?php echo e($_POST['guest_pincode'] ?? ''); ?>" 
                                               <?php echo $need_delivery_required ? 'required' : 'data-required="true"'; ?>>
                                        <label for="guest_pincode">PIN code</label>
                                    </div>
                                    <div id="guest-pincode-status" class="small mt-1"></div>
                                </div>
                            </div>

                            <!-- Phone -->
                            <div class="form-floating mb-2">
                                <input type="tel" 
                                       class="form-control shopify-input" 
                                       id="guest_phone" 
                                       name="guest_phone" 
                                       placeholder="Phone" 
                                       autocomplete="shipping tel" 
                                       inputmode="tel" 
                                       value="<?php echo e($is_logged_in ? ($logged_in_customer['phone'] ?? ($_POST['guest_phone'] ?? '')) : ($_POST['guest_phone'] ?? '')); ?>" 
                                       <?php echo $need_delivery_required ? 'required' : 'data-required="true"'; ?>>
                                <label for="guest_phone">Phone</label>
                            </div>
                            <div class="form-text small text-muted" style="font-size: 12px;">
                                In case we need to contact you regarding order delivery.
                            </div>
                        </div>

                            <!-- Checkboxes -->
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="save_info" name="save_info" checked>
                                <label class="form-check-label small text-muted" for="save_info">
                                    Save this information for next time
                                </label>
                            </div>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" id="text_offers" name="text_offers">
                                <label class="form-check-label small text-muted" for="text_offers">
                                    Text me with news and offers
                                </label>
                            </div>
                        </div>

                    <!-- SECTION 3: Shipping Method -->
                    <div class="mb-4">
                        <h2 class="h5 fw-bold text-dark mb-2">Shipping method</h2>
                        <div class="shopify-card-group">
                            <div class="shopify-card-item d-flex justify-content-between align-items-center bg-white">
                                <div class="d-flex align-items-center gap-2">
                                    <input class="form-check-input mt-0" type="radio" name="shipping_method_radio" checked disabled>
                                    <span class="small fw-semibold text-dark">Standard Insured Delivery (3–5 business days)</span>
                                </div>
                                <span class="small fw-bold text-dark">
                                    <?php echo $shipping == 0 ? '<span class="text-success">FREE</span>' : format_price($shipping); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 4: Payment -->
                    <div class="mb-4">
                        <h2 class="h5 fw-bold text-dark mb-1">Payment</h2>
                        <div class="small text-muted mb-3" style="font-size: 13px;">All transactions are secure and encrypted.</div>

                        <div class="shopify-card-group">
                            <?php if (RAZORPAY_ENABLED): ?>
                                <!-- Option 1: Razorpay / Online Payment -->
                                <div class="shopify-card-item <?php echo RAZORPAY_ENABLED ? 'selected' : ''; ?>" id="pay_option_rzp" onclick="selectPaymentMethod('Razorpay')">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-2">
                                            <input class="form-check-input mt-0" type="radio" name="payment_method" id="pay_razorpay" value="Razorpay" checked>
                                            <label class="form-check-label small fw-semibold text-dark mb-0" for="pay_razorpay" style="cursor: pointer;">
                                                Razorpay Secure (UPI, Cards, NetBanking, Wallets)
                                            </label>
                                        </div>
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="badge bg-white text-dark border p-1" style="font-size: 10px;">UPI</span>
                                            <span class="badge bg-white text-dark border p-1" style="font-size: 10px;">VISA</span>
                                            <span class="badge bg-white text-dark border p-1" style="font-size: 10px;">Mastercard</span>
                                            <span class="badge bg-white text-dark border p-1" style="font-size: 10px;">RuPay</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="shopify-expanded-panel" id="panel_rzp">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="bi bi-shield-check text-success fs-5"></i>
                                        <span>After clicking <strong>"Pay now"</strong>, you will be seamlessly directed to Razorpay's secure modal to complete your purchase safely.</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (COD_ENABLED || !RAZORPAY_ENABLED): ?>
                                <!-- Option 2: Cash on Delivery -->
                                <div class="shopify-card-item <?php echo !RAZORPAY_ENABLED ? 'selected' : ''; ?>" id="pay_option_cod" onclick="selectPaymentMethod('COD')">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-2">
                                            <input class="form-check-input mt-0" type="radio" name="payment_method" id="pay_cod" value="COD" <?php echo !RAZORPAY_ENABLED ? 'checked' : ''; ?>>
                                            <label class="form-check-label small fw-semibold text-dark mb-0" for="pay_cod" style="cursor: pointer;">
                                                Cash on Delivery (COD) / Pay on Delivery
                                            </label>
                                        </div>
                                        <span class="badge bg-light text-secondary border p-1" style="font-size: 11px;">Pay at Doorstep</span>
                                    </div>
                                </div>
                                <div class="shopify-expanded-panel <?php echo RAZORPAY_ENABLED ? 'd-none' : ''; ?>" id="panel_cod">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-cash-coin text-success fs-5"></i>
                                        <span>Pay with cash or UPI directly to our delivery courier when your order arrives.</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- SECTION 5: Billing Address -->
                    <div class="mb-4">
                        <h2 class="h5 fw-bold text-dark mb-3">Billing address</h2>
                        <div class="shopify-card-group">
                            <label class="shopify-card-item d-flex align-items-center gap-2 selected" style="cursor: pointer;">
                                <input class="form-check-input mt-0" type="radio" name="billing_address_choice" value="same" checked onclick="toggleBillingFields(false)">
                                <span class="small fw-semibold text-dark">Same as shipping address</span>
                            </label>
                            <label class="shopify-card-item d-flex align-items-center gap-2" style="cursor: pointer;">
                                <input class="form-check-input mt-0" type="radio" name="billing_address_choice" value="different" onclick="toggleBillingFields(true)">
                                <span class="small fw-semibold text-dark">Use a different billing address</span>
                            </label>
                        </div>
                        <div class="shopify-card-group mt-2 p-3 bg-light d-none" id="billing_fields_box">
                            <div class="small text-muted mb-2">Enter your legal/tax billing details:</div>
                            <div class="form-floating mb-2">
                                <input type="text" class="form-control shopify-input" id="billing_name" name="billing_name" placeholder="Full name / Company name">
                                <label for="billing_name">Full name / Company name</label>
                            </div>
                            <div class="form-floating mb-2">
                                <input type="text" class="form-control shopify-input" id="billing_address" name="billing_address" placeholder="Billing address">
                                <label for="billing_address">Billing address</label>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control shopify-input" id="billing_city" name="billing_city" placeholder="City">
                                        <label for="billing_city">City</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control shopify-input" id="billing_pincode" name="billing_pincode" placeholder="PIN code">
                                        <label for="billing_pincode">PIN code</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SUBMIT BUTTON -->
                    <button type="submit" name="place_order" id="btn-pay-now" class="btn-pay-now shadow-sm">
                        <i class="bi bi-lock-fill me-1"></i> Pay now
                    </button>
                </form>

                <!-- Distraction-Free Policy Links Footer -->
                <div class="checkout-policy-links mt-5 pt-3 border-top d-flex flex-wrap gap-3">
                    <a href="return-and-exchange.php" target="_blank">Refund policy</a>
                    <a href="shipping-policy.php" target="_blank">Shipping policy</a>
                    <a href="privacy-policy.php" target="_blank">Privacy policy</a>
                    <a href="terms-and-conditions.php" target="_blank">Terms of service</a>
                    <a href="contact.php" target="_blank">Contact</a>
                </div>

            </div>

            <!-- RIGHT COLUMN: Sticky Order Summary & Totals -->
            <div class="checkout-sidebar-col" id="checkoutSidebarCol">

                <!-- Line Items List -->
                <div class="checkout-items-wrapper mb-3" style="max-height: 380px; overflow-y: auto; padding-right: 4px;">
                    <?php foreach ($cart_items as $item): ?>
                        <?php 
                        $price = $item['unit_price'];
                        $is_combo = !empty($item['combo_product_id']);
                        $img_src = !empty($item['main_image']) ? 'uploads/' . $item['main_image'] : 'assets/images/logo.png';
                        ?>
                        <div class="d-flex align-items-center gap-3 py-2">
                            <div class="item-thumbnail-wrap">
                                <img src="<?php echo e($img_src); ?>" alt="<?php echo e($item['product_name']); ?>" class="item-thumbnail-img" onerror="this.src='assets/images/logo.png';">
                                <span class="item-qty-badge"><?php echo $item['quantity']; ?></span>
                            </div>
                            <div class="flex-grow-1 small lh-sm">
                                <div class="fw-semibold text-dark text-truncate" style="max-width: 220px;">
                                    <?php echo e($item['product_name']); ?>
                                </div>
                                <div class="text-muted mt-1" style="font-size: 12px;">
                                    <?php if ($is_combo): ?>
                                        <span class="badge bg-purple-subtle text-purple border p-1" style="font-size: 10px;">Curated Combo Set</span>
                                    <?php else: ?>
                                        <?php echo e($item['size_code']); ?> / <?php echo e($item['color_name']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="fw-semibold text-dark text-end small flex-shrink-0">
                                <?php echo format_price($price * $item['quantity']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Discount Code Input -->
                <div class="border-top pt-3 pb-3">
                    <?php if (isset($_SESSION['checkout_coupon'])): ?>
                        <div class="alert alert-success d-flex justify-content-between align-items-center p-2 small mb-0 rounded-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-tag-fill me-2 fs-6"></i>
                                <span>Promo <strong><?php echo e($_SESSION['checkout_coupon']['code']); ?></strong> applied!</span>
                            </div>
                            <a href="checkout.php?remove_coupon=1" class="text-danger fw-bold text-decoration-none small">
                                Remove
                            </a>
                        </div>
                    <?php else: ?>
                        <form action="checkout.php" method="POST" class="d-flex gap-2 align-items-center">
                            <?php echo csrf_field(); ?>
                            <div class="form-floating flex-grow-1">
                                <input type="text" 
                                       name="coupon_code" 
                                       id="coupon_code"
                                       class="form-control shopify-input text-uppercase font-monospace" 
                                       placeholder="Discount code or gift card" 
                                       required>
                                <label for="coupon_code">Discount code or gift card</label>
                            </div>
                            <button type="submit" name="apply_coupon" class="btn btn-secondary px-3 rounded-2 fw-semibold" style="font-size: 14px; height: 54px;">
                                Apply
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Totals Breakdown -->
                <div class="border-top pt-3 small">
                    <div class="d-flex justify-content-between text-muted mb-2">
                        <span>Subtotal</span>
                        <span class="text-dark fw-semibold"><?php echo format_price($subtotal); ?></span>
                    </div>

                    <?php if ($discount > 0): ?>
                        <div class="d-flex justify-content-between text-success mb-2">
                            <span>Discount</span>
                            <span class="fw-semibold">-<?php echo format_price($discount); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between text-muted mb-2">
                        <span>Shipping</span>
                        <span class="text-dark fw-semibold">
                            <?php echo $shipping == 0 ? '<span class="text-success">FREE</span>' : format_price($shipping); ?>
                        </span>
                    </div>

                    <div class="d-flex justify-content-between text-muted mb-3">
                        <span>Estimated taxes (GST included)</span>
                        <span><?php echo format_price($tax_amount); ?></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-baseline border-top pt-3">
                        <span class="fw-bold text-dark fs-5">Total</span>
                        <div class="text-end">
                            <span class="text-muted small text-uppercase me-1" style="font-size: 12px; letter-spacing: 0.5px;">INR</span>
                            <span class="fs-4 fw-bold text-dark"><?php echo format_price($grand_total); ?></span>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // 1. Mobile Summary Toggle
    const mobileToggle = document.getElementById('mobileSummaryToggle');
    const sidebarCol = document.getElementById('checkoutSidebarCol');
    const mobileSummaryText = document.getElementById('mobileSummaryText');
    const mobileSummaryIcon = document.getElementById('mobileSummaryIcon');

    if (mobileToggle && sidebarCol) {
        mobileToggle.addEventListener('click', function() {
            const isShown = sidebarCol.classList.toggle('show-mobile');
            if (isShown) {
                mobileSummaryText.textContent = 'Hide order summary';
                mobileSummaryIcon.className = 'bi bi-chevron-up ms-1';
            } else {
                mobileSummaryText.textContent = 'Show order summary';
                mobileSummaryIcon.className = 'bi bi-chevron-down ms-1';
            }
        });
    }

    // 2. Payment Method Switcher
    function selectPaymentMethod(method) {
        const rzpRadio = document.getElementById('pay_razorpay');
        const codRadio = document.getElementById('pay_cod');
        const optRzp = document.getElementById('pay_option_rzp');
        const optCod = document.getElementById('pay_option_cod');
        const panelRzp = document.getElementById('panel_rzp');
        const panelCod = document.getElementById('panel_cod');
        const payBtn = document.getElementById('btn-pay-now');

        if (method === 'Razorpay' && rzpRadio) {
            rzpRadio.checked = true;
            if (optRzp) optRzp.classList.add('selected');
            if (optCod) optCod.classList.remove('selected');
            if (panelRzp) panelRzp.classList.remove('d-none');
            if (panelCod) panelCod.classList.add('d-none');
            if (payBtn) payBtn.innerHTML = '<i class="bi bi-lock-fill me-1"></i> Pay now';
        } else if (method === 'COD' && codRadio) {
            codRadio.checked = true;
            if (optCod) optCod.classList.add('selected');
            if (optRzp) optRzp.classList.remove('selected');
            if (panelCod) panelCod.classList.remove('d-none');
            if (panelRzp) panelRzp.classList.add('d-none');
            if (payBtn) payBtn.innerHTML = '<i class="bi bi-box-seam me-1"></i> Complete order &bull; <?php echo format_price($grand_total); ?>';
        }
    }

    // 3. Billing Address Toggle
    function toggleBillingFields(show) {
        const box = document.getElementById('billing_fields_box');
        if (box) {
            if (show) box.classList.remove('d-none');
            else box.classList.add('d-none');
        }
    }

    // 4. Logged-in New Address Toggle
    function toggleNewAddressBox(show) {
        const fieldsBox = document.getElementById('delivery-fields-box');
        if (fieldsBox) {
            if (show) {
                fieldsBox.classList.remove('d-none');
                fieldsBox.querySelectorAll('[data-required="true"]').forEach(el => el.required = true);
            } else {
                fieldsBox.classList.add('d-none');
                fieldsBox.querySelectorAll('[required]').forEach(el => {
                    el.dataset.required = 'true';
                    el.required = false;
                });
            }
        }
    }

    // 5. Automatic Name & Contact Synchronization
    const firstNameEl = document.getElementById('first_name');
    const lastNameEl = document.getElementById('last_name');
    const guestNameEl = document.getElementById('guest_name');

    function syncFullName() {
        if (firstNameEl && guestNameEl) {
            const first = firstNameEl.value.trim();
            const last = lastNameEl ? lastNameEl.value.trim() : '';
            guestNameEl.value = [first, last].filter(Boolean).join(' ');
        }
    }
    if (firstNameEl) firstNameEl.addEventListener('input', syncFullName);
    if (lastNameEl) lastNameEl.addEventListener('input', syncFullName);

    // 6. PIN Code Auto-Lookup for City and State
    const pincodeInput = document.getElementById('guest_pincode');
    const cityInput = document.getElementById('guest_city');
    const stateSelect = document.getElementById('guest_state');
    const pincodeStatus = document.getElementById('guest-pincode-status');

    if (pincodeInput) {
        let debounceTimer;
        pincodeInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const pin = this.value.trim();
            if (pin.length === 6 && /^\d{6}$/.test(pin)) {
                if (pincodeStatus) pincodeStatus.innerHTML = '<span class="text-muted"><span class="spinner-border spinner-border-sm me-1" role="status"></span>Checking PIN...</span>';
                debounceTimer = setTimeout(() => {
                    fetch(`https://api.postalpincode.in/pincode/${pin}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data && data[0] && data[0].Status === 'Success' && data[0].PostOffice && data[0].PostOffice.length > 0) {
                                const po = data[0].PostOffice[0];
                                const city = po.District || po.Block || po.Circle;
                                const state = po.State;
                                if (city && cityInput && !cityInput.value) {
                                    cityInput.value = city;
                                }
                                if (state && stateSelect) {
                                    for (let opt of stateSelect.options) {
                                        if (opt.value.toLowerCase() === state.toLowerCase()) {
                                            stateSelect.value = opt.value;
                                            break;
                                        }
                                    }
                                }
                                if (pincodeStatus) pincodeStatus.innerHTML = `<span class="text-success"><i class="bi bi-check-circle me-1"></i>${city}, ${state}</span>`;
                            } else {
                                if (pincodeStatus) pincodeStatus.innerHTML = '';
                            }
                        })
                        .catch(() => {
                            if (pincodeStatus) pincodeStatus.innerHTML = '';
                        });
                }, 300);
            } else {
                if (pincodeStatus) pincodeStatus.innerHTML = '';
            }
        });
    }

    // 7. Returning Guest LocalStorage Auto-fill
    const GUEST_STORAGE_KEY = 'pookky_guest_shipping_address';
    const emailInput = document.getElementById('guest_email');
    const phoneInput = document.getElementById('guest_phone');
    const line1Input = document.getElementById('guest_address_line1');
    const line2Input = document.getElementById('guest_address_line2');
    const autofillBanner = document.getElementById('guest-autofill-banner');
    const applyAutofillBtn = document.getElementById('btn-apply-guest-autofill');
    const dismissAutofillBtn = document.getElementById('btn-dismiss-guest-autofill');
    const previewAutofill = document.getElementById('guest-autofill-preview');

    try {
        const savedGuest = localStorage.getItem(GUEST_STORAGE_KEY);
        if (savedGuest && autofillBanner) {
            const data = JSON.parse(savedGuest);
            const isFormBlank = (!firstNameEl.value && !line1Input.value);
            if (isFormBlank && (data.name || data.email)) {
                autofillBanner.classList.remove('d-none');
                if (previewAutofill) {
                    previewAutofill.textContent = `Found address for ${data.name || data.email} (${data.city || ''} ${data.pincode || ''})`;
                }
                if (applyAutofillBtn) {
                    applyAutofillBtn.onclick = function() {
                        if (data.first_name && firstNameEl) firstNameEl.value = data.first_name;
                        if (data.last_name && lastNameEl) lastNameEl.value = data.last_name;
                        if (data.email && emailInput) emailInput.value = data.email;
                        if (data.phone && phoneInput) phoneInput.value = data.phone;
                        if (data.line1 && line1Input) line1Input.value = data.line1;
                        if (data.line2 && line2Input) line2Input.value = data.line2;
                        if (data.city && cityInput) cityInput.value = data.city;
                        if (data.pincode && pincodeInput) pincodeInput.value = data.pincode;
                        if (data.state && stateSelect) stateSelect.value = data.state;
                        syncFullName();
                        autofillBanner.classList.add('d-none');
                    };
                }
                if (dismissAutofillBtn) {
                    dismissAutofillBtn.onclick = function() {
                        autofillBanner.classList.add('d-none');
                    };
                }
            }
        }
    } catch(e) {}

    // Helper function to render in-page notification
    function showCheckoutNotice(message, type = 'danger') {
        const alertBox = document.getElementById('checkout-alert-box');
        if (!alertBox) return;
        const icon = type === 'warning' ? 'bi-exclamation-circle-fill text-warning' : (type === 'success' ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-danger');
        alertBox.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show py-2 px-3 small rounded-3 mb-4 d-flex align-items-center gap-2" role="alert">
                <i class="bi ${icon} fs-5"></i>
                <div class="flex-grow-1">${message}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function showConfirmOverlay() {
        if (document.getElementById('checkout-confirm-overlay')) return;
        const overlay = document.createElement('div');
        overlay.id = 'checkout-confirm-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.96);z-index:999999;display:flex;align-items:center;justify-content:center;flex-direction:column;backdrop-filter:blur(5px);';
        overlay.innerHTML = '<div class="spinner-border text-danger mb-3" style="width:3rem;height:3rem;" role="status"></div><h4 class="fw-bold text-dark">Confirming Payment...</h4><p class="text-muted small">Please do not refresh or close this window.</p>';
        document.body.appendChild(overlay);
    }

    function hideConfirmOverlay() {
        const overlay = document.getElementById('checkout-confirm-overlay');
        if (overlay) overlay.remove();
    }

    function openRazorpayCheckout(rzpOptions) {
        const payBtn = document.getElementById('btn-pay-now');
        const origBtnHtml = payBtn ? payBtn.innerHTML : '<i class="bi bi-lock-fill me-1"></i> Pay now';

        rzpOptions.handler = function(response) {
            showConfirmOverlay();
            fetch('api/verify-payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: document.querySelector('input[name="csrf_token"]')?.value || '',
                    order_number: rzpOptions.order_number,
                    razorpay_payment_id: response.razorpay_payment_id || '',
                    razorpay_order_id: response.razorpay_order_id || '',
                    razorpay_signature: response.razorpay_signature || ''
                })
            })
            .then(res => res.json())
            .then(vData => {
                if (vData.success && vData.redirect) {
                    window.location.href = vData.redirect;
                } else {
                    hideConfirmOverlay();
                    showCheckoutNotice('Payment verification failed: ' + (vData.error || 'Please contact support with Payment Reference ' + (response.razorpay_payment_id || '')), 'danger');
                    if (payBtn) {
                        payBtn.disabled = false;
                        payBtn.innerHTML = origBtnHtml;
                    }
                }
            })
            .catch(err => {
                hideConfirmOverlay();
                showCheckoutNotice('Network error while confirming payment. If amount was debited, please contact customer support.', 'warning');
                if (payBtn) {
                    payBtn.disabled = false;
                    payBtn.innerHTML = origBtnHtml;
                }
            });
        };

        rzpOptions.modal = {
            ondismiss: function() {
                if (payBtn) {
                    payBtn.disabled = false;
                    payBtn.innerHTML = origBtnHtml;
                }
                showCheckoutNotice('Payment was cancelled. You have not been charged. Your items are still in your cart.', 'warning');
            }
        };

        const rzp = new Razorpay(rzpOptions);
        rzp.on('payment.failed', function(resp) {
            if (payBtn) {
                payBtn.disabled = false;
                payBtn.innerHTML = origBtnHtml;
            }
            showCheckoutNotice('Payment failed: ' + (resp.error ? resp.error.description : 'Transaction could not be completed.'), 'danger');
        });
        rzp.open();
    }

    // Save on form submit & intercept Razorpay payment
    const checkoutForm = document.getElementById('checkout-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            syncFullName();
            try {
                if (firstNameEl && line1Input) {
                    const toSave = {
                        first_name: firstNameEl.value.trim(),
                        last_name: lastNameEl ? lastNameEl.value.trim() : '',
                        name: guestNameEl ? guestNameEl.value.trim() : '',
                        email: emailInput ? emailInput.value.trim() : '',
                        phone: phoneInput ? phoneInput.value.trim() : '',
                        line1: line1Input.value.trim(),
                        line2: line2Input ? line2Input.value.trim() : '',
                        city: cityInput ? cityInput.value.trim() : '',
                        state: stateSelect ? stateSelect.value : '',
                        pincode: pincodeInput ? pincodeInput.value.trim() : ''
                    };
                    localStorage.setItem(GUEST_STORAGE_KEY, JSON.stringify(toSave));
                }
            } catch(err) {}

            const selectedPayRadio = document.querySelector('input[name="payment_method"]:checked');
            const paymentMethod = selectedPayRadio ? selectedPayRadio.value : 'COD';

            if (paymentMethod === 'Razorpay') {
                e.preventDefault();

                // Validate form inputs HTML5 validity
                if (!checkoutForm.checkValidity()) {
                    checkoutForm.reportValidity();
                    return false;
                }

                const payBtn = document.getElementById('btn-pay-now');
                const origBtnHtml = payBtn ? payBtn.innerHTML : '<i class="bi bi-lock-fill me-1"></i> Pay now';
                if (payBtn) {
                    payBtn.disabled = true;
                    payBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Preparing Secure Payment...';
                }

                // Clear previous alerts
                const alertBox = document.getElementById('checkout-alert-box');
                if (alertBox) alertBox.innerHTML = '';

                const formData = new FormData(checkoutForm);
                formData.append('init_razorpay', '1');

                fetch('checkout.php?action=init_razorpay', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.razorpay_payload) {
                        showCheckoutNotice(data.error || 'Failed to initialize payment gateway. Please try again.', 'danger');
                        if (payBtn) {
                            payBtn.disabled = false;
                            payBtn.innerHTML = origBtnHtml;
                        }
                        return;
                    }
                    openRazorpayCheckout(data.razorpay_payload);
                })
                .catch(err => {
                    showCheckoutNotice('Unable to connect to the payment server. Please check your internet connection and try again.', 'danger');
                    if (payBtn) {
                        payBtn.disabled = false;
                        payBtn.innerHTML = origBtnHtml;
                    }
                });
            }
        });
    }
    </script>

    <?php if (RAZORPAY_ENABLED): ?>
        <!-- Razorpay Standard Web Checkout Integration Script -->
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <?php if (isset($razorpay_order_payload)): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                openRazorpayCheckout(<?php echo json_encode($razorpay_order_payload); ?>);
            });
            </script>
        <?php endif; ?>
    <?php endif; ?>

</body>
</html>
