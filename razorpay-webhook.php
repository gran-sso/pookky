<?php
// razorpay-webhook.php
// Webhook endpoint to capture Razorpay async payment notifications

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/razorpay.php';

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (empty($payload)) {
    http_response_code(400);
    exit('No payload');
}

// Verify webhook signature strictly if webhook secret is configured
if (!empty(RAZORPAY_WEBHOOK_SECRET)) {
    if (empty($signature) || !hash_equals(hash_hmac('sha256', $payload, RAZORPAY_WEBHOOK_SECRET), $signature)) {
        http_response_code(400);
        exit('Invalid signature');
    }
}

$data = json_decode($payload, true);
$event = $data['event'] ?? '';

if ($event === 'payment.captured' || $event === 'order.paid') {
    $payment = $data['payload']['payment']['entity'] ?? [];
    $payment_id = $payment['id'] ?? '';
    $rzp_order_id = $payment['order_id'] ?? '';
    $notes = $payment['notes'] ?? [];
    $order_number = $notes['order_number'] ?? '';

    try {
        // 1. If order already in orders table, update it
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = ? OR transaction_id = ? LIMIT 1");
        $stmt->execute([$order_number, $payment_id]);
        $existing_id = $stmt->fetchColumn();

        if ($existing_id) {
            $stmt_up = $pdo->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'processing', transaction_id = ? WHERE id = ? AND payment_status != 'paid'");
            $stmt_up->execute([$payment_id, $existing_id]);

            $stmt_pay = $pdo->prepare("UPDATE payments SET status = 'paid', transaction_reference = ? WHERE order_id = ? AND payment_method = 'Razorpay'");
            $stmt_pay->execute([$payment_id, $existing_id]);
        } elseif (!empty($rzp_order_id)) {
            // 2. If not yet in orders table, look up from pending_checkouts
            $pc_stmt = $pdo->prepare("SELECT checkout_data FROM pending_checkouts WHERE razorpay_order_id = ? OR order_number = ? LIMIT 1");
            $pc_stmt->execute([$rzp_order_id, $order_number]);
            $pc_row = $pc_stmt->fetch();
            if ($pc_row) {
                $checkout_data = json_decode($pc_row['checkout_data'], true);
                if ($checkout_data) {
                    finalize_razorpay_order($checkout_data, $payment_id);
                }
            }
        }
    } catch (Exception $e) {
        error_log('Razorpay webhook processing error: ' . $e->getMessage());
    }
}

http_response_code(200);
echo 'OK';
