<?php
// process-cart.php
// Public Front-end Customer AJAX controller

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';

$session_id = session_id();
$customer_id = $_SESSION['customer_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // 1. NEWSLETTER SUBSCRIPTION (Public action, skip csrf if form lacks, but verify if set)
    if ($action === 'subscribe') {
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response('error', 'Please enter a valid email address.');
        }

        try {
            // Check if already registered
            $check = $pdo->prepare("SELECT id FROM newsletter_subscribers WHERE email = ? LIMIT 1");
            $check->execute([$email]);
            if ($check->fetch()) {
                json_response('success', 'You are already subscribed to our newsletter!');
            } else {
                $stmt = $pdo->prepare("INSERT INTO newsletter_subscribers (email, status) VALUES (?, 1)");
                $stmt->execute([$email]);
                json_response('success', 'Thank you for subscribing to Pookky!');
            }
        } catch (PDOException $e) {
            json_response('error', 'Subscription failed.');
        }
    }

    // CSRF verification for other requests
    if (!verify_csrf_token($csrf_token)) {
        json_response('error', 'Security check failed. Please refresh page.');
    }

    // Helper: calculate total distinct items in cart
    function get_total_cart_items() {
        global $pdo, $customer_id, $session_id;
        if ($customer_id) {
            $stmt = $pdo->prepare("SELECT COUNT(id) as count FROM cart WHERE customer_id = ?");
            $stmt->execute([$customer_id]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(id) as count FROM cart WHERE session_id = ? AND customer_id IS NULL");
            $stmt->execute([$session_id]);
        }
        $row = $stmt->fetch();
        return (int)($row['count'] ?? 0);
    }

    // 2. ADD TO CART (SINGLE PRODUCT)
    if ($action === 'add_to_cart') {
        $variant_id = (int)($_POST['variant_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 1);

        if ($variant_id <= 0 || $qty <= 0) {
            json_response('error', 'Invalid product choice.');
        }

        try {
            // Check available stock
            $v_stmt = $pdo->prepare("SELECT stock, sku FROM product_variants WHERE id = ? LIMIT 1");
            $v_stmt->execute([$variant_id]);
            $variant = $v_stmt->fetch();

            if (!$variant) {
                json_response('error', 'Selected variant not found.');
            }

            $available_stock = (int)$variant['stock'];
            if ($available_stock <= 0) {
                json_response('error', 'This item is out of stock.');
            }

            // Check if item already in cart
            if ($customer_id) {
                $check = $pdo->prepare("SELECT id, quantity FROM cart WHERE customer_id = ? AND variant_id = ? LIMIT 1");
                $check->execute([$customer_id, $variant_id]);
            } else {
                $check = $pdo->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND variant_id = ? AND customer_id IS NULL LIMIT 1");
                $check->execute([$session_id, $variant_id]);
            }
            $existing_cart_item = $check->fetch();

            $new_qty = $qty;
            if ($existing_cart_item) {
                $new_qty += (int)$existing_cart_item['quantity'];
            }

            if ($new_qty > $available_stock) {
                json_response('error', "Only $available_stock units available. You already have " . ($existing_cart_item['quantity'] ?? 0) . " in your cart.");
            }

            if ($existing_cart_item) {
                // Update
                $up_stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                $up_stmt->execute([$new_qty, $existing_cart_item['id']]);
            } else {
                // Insert new
                $ins_stmt = $pdo->prepare("INSERT INTO cart (customer_id, session_id, variant_id, combo_product_id, combo_config, quantity) VALUES (?, ?, ?, NULL, NULL, ?)");
                $ins_stmt->execute([$customer_id, $session_id, $variant_id, $qty]);
            }

            json_response('success', 'Product added to cart successfully!', ['total_items' => get_total_cart_items()]);

        } catch (PDOException $e) {
            json_response('error', 'Failed to add item: ' . $e->getMessage());
        }
    }

    // 2.1 ADD COMBO / BUNDLE SET TO CART
    if ($action === 'add_combo_to_cart') {
        $combo_product_id = (int)($_POST['combo_product_id'] ?? 0);
        $components_json = $_POST['components'] ?? '[]';
        $components = json_decode($components_json, true);
        $qty = (int)($_POST['quantity'] ?? 1);

        if ($combo_product_id <= 0 || empty($components) || !is_array($components) || $qty <= 0) {
            json_response('error', 'Invalid combo configuration.');
        }

        try {
            // Fetch combo product
            $p_stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND product_type = 'combo' AND status = 1 LIMIT 1");
            $p_stmt->execute([$combo_product_id]);
            $combo_product = $p_stmt->fetch();

            if (!$combo_product) {
                json_response('error', 'Combo product not found or unavailable.');
            }

            // Fetch required combo components
            $req_stmt = $pdo->prepare("SELECT * FROM combo_components WHERE combo_product_id = ? ORDER BY sort_order ASC, id ASC");
            $req_stmt->execute([$combo_product_id]);
            $required_components = $req_stmt->fetchAll();

            if (empty($required_components)) {
                json_response('error', 'Combo components configuration is missing.');
            }

            // Map components submitted
            $submitted_map = [];
            foreach ($components as $c) {
                if (!empty($c['component_id']) && !empty($c['variant_id'])) {
                    $submitted_map[(int)$c['component_id']] = (int)$c['variant_id'];
                }
            }

            $combo_config = [];
            $min_available_stock = 999999;

            // Verify each required component
            foreach ($required_components as $req_comp) {
                $comp_id = (int)$req_comp['id'];
                if (!isset($submitted_map[$comp_id])) {
                    json_response('error', 'Missing selection for: ' . $req_comp['component_title']);
                }

                $variant_id = $submitted_map[$comp_id];

                // Verify variant details and stock
                $v_stmt = $pdo->prepare("
                    SELECT pv.id as variant_id, pv.sku, pv.stock, pv.product_id,
                           p.name as product_name,
                           s.size_code, s.size_name,
                           cl.color_name, cl.color_code
                    FROM product_variants pv
                    JOIN products p ON pv.product_id = p.id
                    JOIN sizes s ON pv.size_id = s.id
                    JOIN colors cl ON pv.color_id = cl.id
                    WHERE pv.id = ? AND p.status = 1
                    LIMIT 1
                ");
                $v_stmt->execute([$variant_id]);
                $var_info = $v_stmt->fetch();

                if (!$var_info) {
                    json_response('error', 'Selected piece in combo is invalid.');
                }

                $stock = (int)$var_info['stock'];
                if ($stock < $qty) {
                    json_response('error', "Piece '{$var_info['product_name']}' ({$var_info['size_code']} / {$var_info['color_name']}) has only $stock units in stock.");
                }

                if ($stock < $min_available_stock) {
                    $min_available_stock = $stock;
                }

                $combo_config[] = [
                    'component_id' => $comp_id,
                    'component_title' => $req_comp['component_title'],
                    'product_id' => $var_info['product_id'],
                    'product_name' => $var_info['product_name'],
                    'variant_id' => $var_info['variant_id'],
                    'sku' => $var_info['sku'],
                    'size_code' => $var_info['size_code'],
                    'color_name' => $var_info['color_name'],
                    'color_code' => $var_info['color_code']
                ];
            }

            $config_json = json_encode($combo_config);

            // Check if matching combo already in cart
            if ($customer_id) {
                $check = $pdo->prepare("SELECT id, quantity FROM cart WHERE customer_id = ? AND combo_product_id = ? AND combo_config = ? LIMIT 1");
                $check->execute([$customer_id, $combo_product_id, $config_json]);
            } else {
                $check = $pdo->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND combo_product_id = ? AND combo_config = ? AND customer_id IS NULL LIMIT 1");
                $check->execute([$session_id, $combo_product_id, $config_json]);
            }
            $existing = $check->fetch();

            $new_qty = $qty;
            if ($existing) {
                $new_qty += (int)$existing['quantity'];
            }

            if ($new_qty > $min_available_stock) {
                json_response('error', "Cannot add $qty more sets. Stock limit of $min_available_stock reached.");
            }

            if ($existing) {
                $up = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                $up->execute([$new_qty, $existing['id']]);
            } else {
                $ins = $pdo->prepare("INSERT INTO cart (customer_id, session_id, variant_id, combo_product_id, combo_config, quantity) VALUES (?, ?, NULL, ?, ?, ?)");
                $ins->execute([$customer_id, $session_id, $combo_product_id, $config_json, $qty]);
            }

            json_response('success', 'Combo Set added to your shopping bag!', ['total_items' => get_total_cart_items()]);

        } catch (PDOException $e) {
            json_response('error', 'Failed to add combo set: ' . $e->getMessage());
        }
    }

    // 3. UPDATE CART QUANTITY
    if ($action === 'update_cart') {
        $cart_id = (int)($_POST['cart_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);

        if ($cart_id <= 0 || $qty <= 0) {
            json_response('error', 'Invalid quantities.');
        }

        try {
            // Verify ownership
            if ($customer_id) {
                $c_stmt = $pdo->prepare("SELECT * FROM cart WHERE id = ? AND customer_id = ? LIMIT 1");
                $c_stmt->execute([$cart_id, $customer_id]);
            } else {
                $c_stmt = $pdo->prepare("SELECT * FROM cart WHERE id = ? AND session_id = ? AND customer_id IS NULL LIMIT 1");
                $c_stmt->execute([$cart_id, $session_id]);
            }
            $cart_item = $c_stmt->fetch();

            if (!$cart_item) {
                json_response('error', 'Item not found in cart.');
            }

            if (!empty($cart_item['variant_id'])) {
                $v_stmt = $pdo->prepare("SELECT stock FROM product_variants WHERE id = ?");
                $v_stmt->execute([$cart_item['variant_id']]);
                $v = $v_stmt->fetch();
                $max_stock = (int)($v['stock'] ?? 0);
            } elseif (!empty($cart_item['combo_config'])) {
                $cfg = json_decode($cart_item['combo_config'], true) ?? [];
                $max_stock = 999999;
                foreach ($cfg as $item) {
                    $v_stmt = $pdo->prepare("SELECT stock FROM product_variants WHERE id = ?");
                    $v_stmt->execute([$item['variant_id']]);
                    $v = $v_stmt->fetch();
                    $s = (int)($v['stock'] ?? 0);
                    if ($s < $max_stock) {
                        $max_stock = $s;
                    }
                }
            } else {
                $max_stock = 10;
            }

            if ($qty > $max_stock) {
                json_response('error', "Only $max_stock sets/items in stock.", ['available_stock' => $max_stock]);
            }

            // Update quantity
            $up_stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $up_stmt->execute([$qty, $cart_id]);

            json_response('success', 'Cart updated successfully!', ['total_items' => get_total_cart_items()]);

        } catch (PDOException $e) {
            json_response('error', 'Cart update failed: ' . $e->getMessage());
        }
    }

    // 3.1 REMOVE ITEM FROM CART
    if ($action === 'remove_from_cart') {
        $cart_id = (int)($_POST['cart_id'] ?? 0);

        if ($cart_id <= 0) {
            json_response('error', 'Invalid cart item.');
        }

        try {
            if ($customer_id) {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND customer_id = ?");
                $stmt->execute([$cart_id, $customer_id]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND session_id = ? AND customer_id IS NULL");
                $stmt->execute([$cart_id, $session_id]);
            }

            json_response('success', 'Item removed from shopping cart!', ['total_items' => get_total_cart_items()]);

        } catch (PDOException $e) {
            json_response('error', 'Failed to remove item: ' . $e->getMessage());
        }
    }

    // 4. TOGGLE WISHLIST
    if ($action === 'toggle_wishlist') {
        if (!$customer_id) {
            json_response('error', 'Please log in to manage your wishlist.');
        }

        $product_id = (int)($_POST['product_id'] ?? 0);
        if ($product_id <= 0) {
            json_response('error', 'Invalid product selection.');
        }

        try {
            // Check if already in wishlist
            $check = $pdo->prepare("SELECT id FROM wishlist WHERE customer_id = ? AND product_id = ? LIMIT 1");
            $check->execute([$customer_id, $product_id]);
            $wish_item = $check->fetch();

            if ($wish_item) {
                // Remove
                $del = $pdo->prepare("DELETE FROM wishlist WHERE id = ?");
                $del->execute([$wish_item['id']]);

                $cnt = $pdo->prepare("SELECT COUNT(id) as count FROM wishlist WHERE customer_id = ?");
                $cnt->execute([$customer_id]);
                $wish_count = (int)($cnt->fetch()['count'] ?? 0);

                json_response('success', 'Removed from wishlist!', ['is_added' => false, 'wishlist_count' => $wish_count]);
            } else {
                // Add
                $ins = $pdo->prepare("INSERT INTO wishlist (customer_id, product_id) VALUES (?, ?)");
                $ins->execute([$customer_id, $product_id]);

                $cnt = $pdo->prepare("SELECT COUNT(id) as count FROM wishlist WHERE customer_id = ?");
                $cnt->execute([$customer_id]);
                $wish_count = (int)($cnt->fetch()['count'] ?? 0);

                json_response('success', 'Added to wishlist!', ['is_added' => true, 'wishlist_count' => $wish_count]);
            }
        } catch (PDOException $e) {
            json_response('error', 'Wishlist update failed.');
        }
    }

    // 5. SAVE CHECKOUT ADDRESS (AJAX)
    if ($action === 'save_checkout_address') {
        if (!$customer_id) {
            json_response('error', 'Please log in to save addresses.');
        }

        $address_id = (int)($_POST['address_id'] ?? 0);
        $address_type = trim($_POST['address_type'] ?? 'Home');
        $receiver_name = trim($_POST['receiver_name'] ?? '');
        $receiver_phone = trim($_POST['receiver_phone'] ?? '');
        $address_line1 = trim($_POST['address_line1'] ?? '');
        $address_line2 = trim($_POST['address_line2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        $is_default = isset($_POST['is_default']) && ($_POST['is_default'] == '1' || $_POST['is_default'] === 'on') ? 1 : 0;

        if (empty($receiver_name) || empty($receiver_phone) || empty($address_line1) || empty($city) || empty($state) || empty($pincode)) {
            json_response('error', 'Please fill in all required fields (Name, Phone, Address Line 1, City, State, Pincode).');
        }

        try {
            $pdo->beginTransaction();

            // Check how many addresses exist for this customer
            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM addresses WHERE customer_id = ?");
            $count_stmt->execute([$customer_id]);
            $existing_count = (int)$count_stmt->fetchColumn();

            // If it's the first address, make it default automatically
            if ($existing_count === 0) {
                $is_default = 1;
            }

            // If set as default, reset other addresses
            if ($is_default == 1) {
                $reset = $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE customer_id = ?");
                $reset->execute([$customer_id]);
            }

            if ($address_id > 0) {
                // Verify ownership
                $chk = $pdo->prepare("SELECT id FROM addresses WHERE id = ? AND customer_id = ? LIMIT 1");
                $chk->execute([$address_id, $customer_id]);
                if (!$chk->fetch()) {
                    $pdo->rollBack();
                    json_response('error', 'Address not found or permission denied.');
                }

                $stmt = $pdo->prepare("
                    UPDATE addresses SET 
                    address_type = ?, receiver_name = ?, receiver_phone = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, pincode = ?, is_default = ? 
                    WHERE id = ? AND customer_id = ?
                ");
                $stmt->execute([$address_type, $receiver_name, $receiver_phone, $address_line1, $address_line2, $city, $state, $pincode, $is_default, $address_id, $customer_id]);
                $saved_id = $address_id;
                $msg = 'Address updated successfully!';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO addresses (customer_id, address_type, receiver_name, receiver_phone, address_line1, address_line2, city, state, pincode, is_default) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$customer_id, $address_type, $receiver_name, $receiver_phone, $address_line1, $address_line2, $city, $state, $pincode, $is_default]);
                $saved_id = (int)$pdo->lastInsertId();
                $msg = 'New address added successfully!';
            }

            $pdo->commit();

            // Fetch updated list of all addresses for this customer
            $all_stmt = $pdo->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC");
            $all_stmt->execute([$customer_id]);
            $all_addresses = $all_stmt->fetchAll();

            json_response('success', $msg, [
                'saved_address_id' => $saved_id,
                'addresses' => $all_addresses
            ]);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            json_response('error', 'Failed to save address: ' . $e->getMessage());
        }
    }

    // 6. GET CHECKOUT ADDRESS (AJAX)
    if ($action === 'get_checkout_address') {
        if (!$customer_id) {
            json_response('error', 'Please log in.');
        }

        $address_id = (int)($_POST['address_id'] ?? 0);
        if ($address_id <= 0) {
            json_response('error', 'Invalid address ID.');
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND customer_id = ? LIMIT 1");
            $stmt->execute([$address_id, $customer_id]);
            $address = $stmt->fetch();

            if (!$address) {
                json_response('error', 'Address not found.');
            }

            json_response('success', 'Address details loaded', ['address' => $address]);
        } catch (PDOException $e) {
            json_response('error', 'Failed to load address.');
        }
    }

    // 7. DELETE CHECKOUT ADDRESS (AJAX)
    if ($action === 'delete_checkout_address') {
        if (!$customer_id) {
            json_response('error', 'Please log in.');
        }

        $address_id = (int)($_POST['address_id'] ?? 0);
        if ($address_id <= 0) {
            json_response('error', 'Invalid address ID.');
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM addresses WHERE id = ? AND customer_id = ?");
            $stmt->execute([$address_id, $customer_id]);

            // Fetch remaining addresses
            $all_stmt = $pdo->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC");
            $all_stmt->execute([$customer_id]);
            $all_addresses = $all_stmt->fetchAll();

            json_response('success', 'Address removed successfully.', ['addresses' => $all_addresses]);
        } catch (PDOException $e) {
            json_response('error', 'Failed to delete address.');
        }
    }
}

json_response('error', 'Invalid request method.');
?>
