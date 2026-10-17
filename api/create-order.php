<?php
// api/create-order.php
// Razorpay Standard Checkout - Backend Endpoint to Create Orders
// Specification: POST /api/create-order
// Request: { amount (paise), currency, receipt }
// Response: { order_id, amount, currency, key_id }

header('Content-Type: application/json; charset=UTF-8');

// Ensure HTTP POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => 'Method Not Allowed. Please use POST.'
    ]);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/razorpay.php';

// Check authentication credentials
$key_id = defined('RAZORPAY_KEY_ID') ? RAZORPAY_KEY_ID : env('RAZORPAY_KEY_ID', '');
$key_secret = defined('RAZORPAY_KEY_SECRET') ? RAZORPAY_KEY_SECRET : env('RAZORPAY_KEY_SECRET', '');

if (empty($key_id) || empty($key_secret)) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized: Razorpay credentials are not configured.'
    ]);
    exit;
}

// Read raw JSON body or POST form data
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true);
$data = is_array($json_data) ? $json_data : $_POST;

$raw_amount = $data['amount'] ?? null;
if ($raw_amount === null || !is_numeric($raw_amount)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Amount is required and must be a valid number of paise.'
    ]);
    exit;
}

$amount = (int)round((float)$raw_amount);

// Validation: Minimum amount must be at least 100 paise (₹1.00)
if ($amount < 100) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Validation error: Minimum amount must be at least 100 paise (₹1.00).'
    ]);
    exit;
}

$currency = !empty($data['currency']) ? strtoupper(trim($data['currency'])) : 'INR';
$receipt = !empty($data['receipt']) ? trim($data['receipt']) : ('rcpt_' . time() . '_' . substr(md5(uniqid(rand(), true)), 0, 6));
$notes = isset($data['notes']) && is_array($data['notes']) ? $data['notes'] : [];

try {
    $order = create_razorpay_order($amount, $currency, $receipt, $notes);

    http_response_code(200);
    echo json_encode([
        'order_id' => $order['order_id'],
        'amount'   => $order['amount'],
        'currency' => $order['currency'],
        'receipt'  => $order['receipt'],
        'key_id'   => $order['key_id']
    ]);
    exit;

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
    exit;

} catch (Exception $e) {
    $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($statusCode);
    echo json_encode([
        'error' => 'Razorpay API error: ' . $e->getMessage()
    ]);
    exit;
}
