<?php
// api/verify-payment.php
// Razorpay Standard Checkout - Backend Endpoint to Verify Payment Signature
// Specification: POST /api/verify-payment
// Algorithm: HMAC-SHA256(order_id + "|" + payment_id, KEY_SECRET)
// Response: HTTP 200 on success; HTTP 400 on signature mismatch or missing fields

header('Content-Type: application/json; charset=UTF-8');

// Ensure HTTP POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error'   => 'Method Not Allowed. Please use POST.'
    ]);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/razorpay.php';
require_once __DIR__ . '/../includes/mail.php';

// Read raw JSON body or POST form data
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true);
$data = is_array($json_data) ? $json_data : $_POST;

// Extract fields with fallbacks for both naming conventions
$order_id = trim($data['razorpay_order_id'] ?? $data['order_id'] ?? '');
$payment_id = trim($data['razorpay_payment_id'] ?? $data['payment_id'] ?? '');
$signature = trim($data['razorpay_signature'] ?? $data['signature'] ?? '');
$order_number = trim($data['order_number'] ?? '');

// 1. Missing fields validation (Must return HTTP 400)
if (empty($order_id) || empty($payment_id) || empty($signature)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Missing required payment fields: razorpay_order_id, razorpay_payment_id, and razorpay_signature are required.'
    ]);
    exit;
}

$key_secret = defined('RAZORPAY_KEY_SECRET') ? RAZORPAY_KEY_SECRET : env('RAZORPAY_KEY_SECRET', '');
if (empty($key_secret)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Server configuration error: Razorpay secret key is not configured.'
    ]);
    exit;
}

// 2. Cryptographic signature verification using HMAC-SHA256
$expected_signature = hash_hmac('sha256', $order_id . '|' . $payment_id, $key_secret);
$is_valid = hash_equals($expected_signature, $signature);

// 3. Signature mismatch handling (Must return HTTP 400, do NOT mark as paid)
if (!$is_valid) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Payment signature verification failed. Generated signature does not match.'
    ]);
    exit;
}

// 4. Signature matched! Create/Update order
$redirect_url = null;
$confirmed_order_number = $order_number;

try {
    // Check if order already exists in orders table (idempotency check)
    $stmt = $pdo->prepare("SELECT id, order_number, payment_status FROM orders WHERE order_number = ? OR transaction_id = ? LIMIT 1");
    $stmt->execute([$order_number, $payment_id]);
    $existing_order = $stmt->fetch();

    if ($existing_order) {
        $confirmed_order_number = $existing_order['order_number'];
        if ($existing_order['payment_status'] !== 'paid') {
            $up = $pdo->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'processing', transaction_id = ? WHERE id = ?");
            $up->execute([$payment_id, $existing_order['id']]);

            $up_pay = $pdo->prepare("UPDATE payments SET status = 'paid', transaction_reference = ? WHERE order_id = ? AND payment_method = 'Razorpay'");
            $up_pay->execute([$payment_id, $existing_order['id']]);
        }
    } else {
        // Not in orders table yet: finalize from session or pending_checkouts
        $checkout_data = null;
        if (isset($_SESSION['pending_checkout']) && (($_SESSION['pending_checkout']['razorpay_order_id'] ?? '') === $order_id || ($_SESSION['pending_checkout']['order_number'] ?? '') === $order_number)) {
            $checkout_data = $_SESSION['pending_checkout'];
        } else {
            $pc_stmt = $pdo->prepare("SELECT checkout_data FROM pending_checkouts WHERE razorpay_order_id = ? OR order_number = ? LIMIT 1");
            $pc_stmt->execute([$order_id, $order_number]);
            $pc_row = $pc_stmt->fetch();
            if ($pc_row) {
                $checkout_data = json_decode($pc_row['checkout_data'], true);
            }
        }

        if (!$checkout_data) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error'   => 'Pending checkout session not found or expired. Please refresh and try again.'
            ]);
            exit;
        }

        // Finalize order atomically
        $res = finalize_razorpay_order($checkout_data, $payment_id);
        $confirmed_order_number = $res['order_number'];
    }

    $redirect_url = 'order-success.php?order_num=' . urlencode($confirmed_order_number);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to finalize order: ' . $e->getMessage()
    ]);
    exit;
}

// 5. Return success response
http_response_code(200);
echo json_encode([
    'success'      => true,
    'message'      => 'Payment verified successfully.',
    'payment_id'   => $payment_id,
    'order_id'     => $order_id,
    'order_number' => $confirmed_order_number,
    'redirect'     => $redirect_url
]);
exit;
