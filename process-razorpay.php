<?php
// process-razorpay.php
// Handles Razorpay Payment Verification and Order Finalization

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/mail.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$order_number = trim($_POST['order_number'] ?? '');
$razorpay_payment_id = trim($_POST['razorpay_payment_id'] ?? '');
$razorpay_order_id = trim($_POST['razorpay_order_id'] ?? '');
$razorpay_signature = trim($_POST['razorpay_signature'] ?? '');

if (empty($order_number) || empty($razorpay_payment_id) || empty($razorpay_order_id) || empty($razorpay_signature)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required payment details: razorpay_payment_id, razorpay_order_id, and razorpay_signature are required.'
    ]);
    exit;
}

require_once __DIR__ . '/includes/razorpay.php';

// Cryptographic signature verification using HMAC-SHA256
if (!verify_razorpay_signature($razorpay_order_id, $razorpay_payment_id, $razorpay_signature)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Payment signature verification failed. Generated signature does not match.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
    $stmt->execute([$order_number]);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    // Already paid? Return success immediately to prevent double processing
    if ($order['payment_status'] === 'paid') {
        echo json_encode([
            'success'  => true,
            'message'  => 'Order is already confirmed as paid.',
            'redirect' => 'order-success.php?order_num=' . urlencode($order['order_number'])
        ]);
        exit;
    }

    // Security check: Verify Razorpay Order ID matches the registered Razorpay Order ID for this order
    $check_pay = $pdo->prepare("SELECT transaction_reference FROM payments WHERE order_id = ? AND payment_method = 'Razorpay' LIMIT 1");
    $check_pay->execute([$order['id']]);
    $stored_ref = $check_pay->fetchColumn();

    if (!empty($stored_ref) && strpos($stored_ref, 'order_') === 0 && $stored_ref !== $razorpay_order_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Security validation error: Razorpay Order ID does not match the registered order.'
        ]);
        exit;
    }

    $update = $pdo->prepare("
        UPDATE orders 
        SET payment_status = 'paid', 
            order_status = 'processing', 
            transaction_id = ?, 
            payment_method = 'Razorpay' 
        WHERE id = ?
    ");
    $update->execute([$razorpay_payment_id, $order['id']]);

    // Update payments table status
    $up_pay = $pdo->prepare("UPDATE payments SET status = 'success', transaction_reference = ? WHERE order_id = ? AND payment_method = 'Razorpay'");
    $up_pay->execute([$razorpay_payment_id, $order['id']]);

    // Clear cart for this customer / session
    $cust_id = $order['customer_id'];
    $sess_id = session_id();
    if ($cust_id) {
        $clr = $pdo->prepare("DELETE FROM cart WHERE customer_id = ?");
        $clr->execute([$cust_id]);
    } else {
        $clr = $pdo->prepare("DELETE FROM cart WHERE session_id = ?");
        $clr->execute([$sess_id]);
    }
    unset($_SESSION['checkout_coupon']);

    // Send confirmation email
    try {
        $cust_stmt = $pdo->prepare("SELECT email FROM customers WHERE id = ? LIMIT 1");
        $cust_stmt->execute([$cust_id]);
        $cust_row = $cust_stmt->fetch();
        if ($cust_row && !empty($cust_row['email'])) {
            send_order_confirmation_email($cust_row['email'], $order['order_number'], $order['grand_total'], $order['receiver_name']);
        }
    } catch (Exception $e) {
        // Email failure non-blocking
    }

    echo json_encode([
        'success' => true,
        'message' => 'Payment verified successfully.',
        'redirect' => 'order-success.php?order_num=' . urlencode($order['order_number'])
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
