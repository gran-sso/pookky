<?php
// includes/razorpay.php
// Official Razorpay Standard Web Checkout Integration Helper

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

/**
 * Get an initialized Razorpay API instance
 *
 * @return Api
 * @throws Exception
 */
function get_razorpay_api() {
    $key_id = defined('RAZORPAY_KEY_ID') ? RAZORPAY_KEY_ID : env('RAZORPAY_KEY_ID', '');
    $key_secret = defined('RAZORPAY_KEY_SECRET') ? RAZORPAY_KEY_SECRET : env('RAZORPAY_KEY_SECRET', '');

    if (empty($key_id) || empty($key_secret)) {
        throw new Exception('Razorpay credentials (RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET) are missing or not configured.');
    }

    if (!class_exists('Razorpay\Api\Api')) {
        throw new Exception('Razorpay PHP SDK is not installed.');
    }

    return new Api($key_id, $key_secret);
}

/**
 * Create a Razorpay Order via REST API / SDK
 *
 * @param int $amount_in_paise Amount in paise (1 INR = 100 paise). Minimum: 100 paise.
 * @param string $currency Currency code (e.g. INR)
 * @param string $receipt Unique receipt/order identifier
 * @param array $notes Optional associative array of metadata
 * @return array ['order_id' => string, 'amount' => int, 'currency' => string, 'key_id' => string]
 * @throws InvalidArgumentException
 * @throws Exception
 */
function create_razorpay_order($amount_in_paise, $currency = 'INR', $receipt = '', $notes = []) {
    $amount = (int)$amount_in_paise;
    if ($amount < 100) {
        throw new InvalidArgumentException('Amount must be at least 100 paise (₹1.00).');
    }

    $key_id = defined('RAZORPAY_KEY_ID') ? RAZORPAY_KEY_ID : env('RAZORPAY_KEY_ID', '');
    $key_secret = defined('RAZORPAY_KEY_SECRET') ? RAZORPAY_KEY_SECRET : env('RAZORPAY_KEY_SECRET', '');

    if (empty($key_id) || empty($key_secret)) {
        throw new Exception('Unauthorized: Razorpay credentials are not configured.', 401);
    }

    $currency = strtoupper(trim($currency ?: 'INR'));
    $receipt = (string)($receipt ?: ('rcpt_' . time() . '_' . bin2hex(random_bytes(3))));

    $orderData = [
        'receipt'         => substr($receipt, 0, 40),
        'amount'          => $amount,
        'currency'        => $currency,
        'payment_capture' => 1
    ];

    if (!empty($notes) && is_array($notes)) {
        $orderData['notes'] = array_slice($notes, 0, 15);
    }

    // Try via Razorpay PHP SDK if available
    if (class_exists('Razorpay\Api\Api')) {
        try {
            $api = new Api($key_id, $key_secret);
            $order = $api->order->create($orderData);

            return [
                'order_id' => $order['id'],
                'amount'   => (int)$order['amount'],
                'currency' => $order['currency'],
                'receipt'  => $order['receipt'] ?? $receipt,
                'key_id'   => $key_id
            ];
        } catch (\Exception $e) {
            // If SDK throws, attempt direct cURL fallback or re-throw
            $sdkError = $e->getMessage();
        }
    }

    // Direct cURL fallback to ensure zero single point of failure
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_USERPWD, "{$key_id}:{$key_secret}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $raw_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception("Razorpay API connection error: {$curl_error}", 500);
    }

    $data = json_decode($raw_response, true);
    if ($http_code >= 400 || empty($data['id'])) {
        $msg = $data['error']['description'] ?? ($sdkError ?? 'Unknown error occurred while creating order on Razorpay.');
        throw new Exception("Razorpay API error ({$http_code}): {$msg}", $http_code >= 500 ? 500 : 400);
    }

    return [
        'order_id' => $data['id'],
        'amount'   => (int)$data['amount'],
        'currency' => $data['currency'],
        'receipt'  => $data['receipt'] ?? $receipt,
        'key_id'   => $key_id
    ];
}

/**
 * Verify Razorpay payment signature
 * HMAC-SHA256(order_id + "|" + payment_id, KEY_SECRET)
 *
 * @param string $order_id Razorpay order_id (e.g. order_To3wtmchX...)
 * @param string $payment_id Razorpay payment_id (e.g. pay_To3wtmchX...)
 * @param string $signature Razorpay signature sent in checkout handler
 * @return bool True if valid, false otherwise
 */
function verify_razorpay_signature($order_id, $payment_id, $signature) {
    $order_id = trim((string)$order_id);
    $payment_id = trim((string)$payment_id);
    $signature = trim((string)$signature);

    if (empty($order_id) || empty($payment_id) || empty($signature)) {
        return false;
    }

    $key_secret = defined('RAZORPAY_KEY_SECRET') ? RAZORPAY_KEY_SECRET : env('RAZORPAY_KEY_SECRET', '');
    if (empty($key_secret)) {
        return false;
    }

    // Primary: Native cryptographic HMAC-SHA256 comparison
    $expected_signature = hash_hmac('sha256', $order_id . '|' . $payment_id, $key_secret);
    return hash_equals($expected_signature, $signature);
}

/**
 * Finalize an order upon successful payment verification.
 * Creates order, order_items, deducts stock, records payment, clears cart, and sends confirmation email.
 *
 * @param array $data Checkout payload (from pending_checkouts or session)
 * @param string $payment_id Razorpay payment ID
 * @return array ['success' => bool, 'order_id' => int, 'order_number' => string]
 * @throws Exception
 */
function finalize_razorpay_order($data, $payment_id) {
    global $pdo;
    
    $order_number = $data['order_number'] ?? '';
    if (empty($order_number)) {
        throw new Exception('Missing order number in checkout data.');
    }

    // Check if order already exists in orders table (idempotency check)
    $chk = $pdo->prepare("SELECT id, order_number, grand_total, receiver_name, customer_id FROM orders WHERE order_number = ? LIMIT 1");
    $chk->execute([$order_number]);
    $existing = $chk->fetch();

    if ($existing) {
        // Just ensure payment status is paid
        $up = $pdo->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'processing', transaction_id = ? WHERE id = ?");
        $up->execute([$payment_id, $existing['id']]);

        $up_pay = $pdo->prepare("UPDATE payments SET status = 'paid', transaction_reference = ? WHERE order_id = ? AND payment_method = 'Razorpay'");
        $up_pay->execute([$payment_id, $existing['id']]);

        return [
            'success' => true,
            'order_id' => (int)$existing['id'],
            'order_number' => $existing['order_number']
        ];
    }

    $pdo->beginTransaction();
    try {
        $cart_items = $data['cart_items'] ?? [];
        if (empty($cart_items)) {
            throw new Exception('Cart items missing from checkout data.');
        }

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
                    throw new Exception("Product SKU " . ($var['sku'] ?? ($item['variant_sku'] ?? '')) . " is currently out of stock.");
                }
            }
        }

        // Insert Order
        $ins_order = $pdo->prepare("
            INSERT INTO orders 
            (order_number, customer_id, receiver_name, receiver_phone, address_line1, address_line2, city, state, pincode, subtotal, coupon_code, discount, shipping_charge, tax_amount, grand_total, payment_method, payment_status, order_status, transaction_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Razorpay', 'paid', 'processing', ?)
        ");
        $ins_order->execute([
            $order_number,
            $data['customer_id'],
            $data['delivery_name'],
            $data['delivery_phone'],
            $data['delivery_line1'],
            $data['delivery_line2'],
            $data['delivery_city'],
            $data['delivery_state'],
            $data['delivery_pincode'],
            $data['subtotal'],
            !empty($data['coupon_code']) ? $data['coupon_code'] : null,
            $data['discount'],
            $data['shipping'],
            $data['tax_amount'],
            $data['grand_total'],
            $payment_id
        ]);
        $order_id = (int)$pdo->lastInsertId();

        // Insert Order Items & decrement variant stocks
        foreach ($cart_items as $item) {
            $unit_price = $item['unit_price'];
            $line_total = $unit_price * $item['quantity'];
            $gst_rate = (float)($item['gst_percentage'] ?? 0);
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
                    $item['combo_config'] ?? null,
                    $item['base_sku'] ?? ($item['sku'] ?? ''),
                    $item['product_name'],
                    $item['quantity'],
                    $unit_price,
                    $gst_rate,
                    $line_tax,
                    $line_total
                ]);

                if (!empty($item['parsed_combo_config'])) {
                    foreach ($item['parsed_combo_config'] as $sub_var) {
                        $dec_stock = $pdo->prepare("UPDATE product_variants SET stock = stock - ? WHERE id = ?");
                        $dec_stock->execute([$item['quantity'], $sub_var['variant_id']]);

                        $log_inv = $pdo->prepare("
                            INSERT INTO inventory_logs (variant_id, quantity_changed, change_type, notes) 
                            VALUES (?, ?, 'sale', ?)
                        ");
                        $log_inv->execute([$sub_var['variant_id'], -$item['quantity'], "Sale deduction for order: " . $order_number]);
                    }
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
                    $item['variant_sku'] ?? ($item['sku'] ?? ''),
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
        if (!empty($data['coupon_id'])) {
            $up_coupon = $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
            $up_coupon->execute([$data['coupon_id']]);
        }

        // Log payment in payments table
        $ins_pay = $pdo->prepare("
            INSERT INTO payments (order_id, payment_method, transaction_reference, amount, status) 
            VALUES (?, 'Razorpay', ?, ?, 'paid')
        ");
        $ins_pay->execute([$order_id, $payment_id, $data['grand_total']]);

        // Clear Cart
        $order_customer_id = $data['customer_id'];
        $session_id = $data['session_id'] ?? session_id();
        $clear_cart = $pdo->prepare("DELETE FROM cart WHERE customer_id = ? OR session_id = ?");
        $clear_cart->execute([$order_customer_id, $session_id]);

        // Clean up from pending_checkouts table
        if (!empty($data['razorpay_order_id'])) {
            $del_pc = $pdo->prepare("DELETE FROM pending_checkouts WHERE razorpay_order_id = ?");
            $del_pc->execute([$data['razorpay_order_id']]);
        }

        $pdo->commit();

        // Clear coupon from session
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['checkout_coupon']);
            unset($_SESSION['pending_checkout']);
        }

        // Send order confirmation email
        try {
            $cust_email = $data['customer_email'] ?? '';
            if (!empty($cust_email)) {
                require_once __DIR__ . '/mail.php';
                send_order_confirmation_email($cust_email, $order_number, $data['grand_total'], $data['delivery_name']);
            }
        } catch (Exception $e) {
            // Non-blocking
        }

        return [
            'success' => true,
            'order_id' => $order_id,
            'order_number' => $order_number
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
