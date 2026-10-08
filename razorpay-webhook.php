<?php
// razorpay-webhook.php
// Webhook endpoint to capture Razorpay async payment notifications

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (empty($payload)) {
    http_response_code(400);
    exit('No payload');
}

// Verify webhook signature if webhook secret is configured
if (!empty(RAZORPAY_WEBHOOK_SECRET) && !empty($signature)) {
    $expected_signature = hash_hmac('sha256', $payload, RAZORPAY_WEBHOOK_SECRET);
    if ($expected_signature !== $signature) {
        http_response_code(400);
        exit('Invalid signature');
    }
}

$data = json_decode($payload, true);
$event = $data['event'] ?? '';

if ($event === 'payment.captured' || $event === 'order.paid') {
    $payment = $data['payload']['payment']['entity'] ?? [];
    $payment_id = $payment['id'] ?? '';
    $notes = $payment['notes'] ?? [];
    $order_number = $notes['order_number'] ?? '';

    if (!empty($order_number)) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'processing', transaction_id = ? WHERE order_number = ? AND payment_status != 'paid'");
            $stmt->execute([$payment_id, $order_number]);
        } catch (PDOException $e) {
            // Log or ignore
        }
    }
}

http_response_code(200);
echo 'OK';
