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

if (empty($order_number) || empty($razorpay_payment_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing payment details.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
    $stmt->execute([$order_number]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    // Verify signature if secret and razorpay_order_id provided
    if (!empty(RAZORPAY_KEY_SECRET) && !empty($razorpay_order_id) && !empty($razorpay_signature)) {
        $expected_signature = hash_hmac('sha256', $razorpay_order_id . '|' . $razorpay_payment_id, RAZORPAY_KEY_SECRET);
        if ($expected_signature !== $razorpay_signature) {
            echo json_encode(['success' => false, 'message' => 'Payment signature verification failed.']);
            exit;
        }
    }

    // Update order status to paid and processing
    $update = $pdo->prepare("
        UPDATE orders 
        SET payment_status = 'paid', 
            order_status = 'processing', 
            transaction_id = ?, 
            payment_method = 'Razorpay' 
        WHERE id = ?
    ");
    $update->execute([$razorpay_payment_id, $order['id']]);

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
