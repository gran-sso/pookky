<?php
// includes/mail.php
// Email sending and HTML templating helper for Veesta

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

/**
 * Send an HTML formatted email
 *
 * @param string $to_email Destination email address
 * @param string $to_name Destination recipient name
 * @param string $subject Email subject line
 * @param string $html_body HTML formatted body
 * @return bool True if mail accepted for delivery, false otherwise
 */
function send_html_email($to_email, $to_name, $subject, $html_body) {
    $from_email = defined('SITE_EMAIL') ? SITE_EMAIL : 'no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'pookky.com');
    $site_name = defined('SITE_NAME') ? SITE_NAME : 'Pookky';

    // Encode subject for UTF-8 safety
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    // Format recipient
    $to = !empty($to_name) ? "=?UTF-8?B?" . base64_encode($to_name) . "?= <{$to_email}>" : $to_email;

    // Headers
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . "=?UTF-8?B?" . base64_encode($site_name) . "?= <{$from_email}>";
    $headers[] = 'Reply-To: ' . $from_email;
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    $header_string = implode("\r\n", $headers);

    // Suppress warning if local sendmail is not configured on development machine
    $mail_sent = @mail($to, $encoded_subject, $html_body, $header_string);

    return (bool)$mail_sent;
}

/**
 * Send a branded OTP verification email
 *
 * @param string $to_email Recipient email
 * @param string $to_name Recipient name
 * @param string $otp_code 6-digit OTP code
 * @param string $purpose Purpose text (e.g. 'Registration', 'Password Reset')
 * @return bool True if dispatched/logged
 */
function send_otp_email($to_email, $to_name, $otp_code, $purpose = 'Registration') {
    $site_name = defined('SITE_NAME') ? SITE_NAME : 'Pookky';
    $site_email = defined('SITE_EMAIL') ? SITE_EMAIL : 'info@pookky.com';
    $current_year = date('Y');

    $subject = "Your {$site_name} {$purpose} Verification Code: {$otp_code}";

    // Luxury branded HTML email template
    $html_body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #fbf9f8;
            margin: 0;
            padding: 0;
            color: #2b2b2b;
            line-height: 1.6;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #fbf9f8;
            padding: 40px 0;
        }
        .main {
            background-color: #ffffff;
            margin: 0 auto;
            width: 100%;
            max-width: 560px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #f0e6e4;
        }
        .header {
            background: linear-gradient(135deg, #24161a 0%, #3d232b 100%);
            padding: 30px 40px;
            text-align: center;
        }
        .brand-title {
            color: #fdf5f2;
            font-size: 26px;
            letter-spacing: 2px;
            font-weight: 700;
            text-transform: uppercase;
            margin: 0;
        }
        .content {
            padding: 40px;
            text-align: center;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 12px;
        }
        .description {
            font-size: 15px;
            color: #555555;
            margin-bottom: 30px;
        }
        .otp-container {
            background: #fdf5f2;
            border: 2px dashed #dca59b;
            border-radius: 12px;
            padding: 20px;
            margin: 0 auto 30px auto;
            max-width: 320px;
        }
        .otp-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #92535d;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .otp-code {
            font-size: 36px;
            font-weight: 700;
            letter-spacing: 8px;
            color: #24161a;
            font-family: 'Courier New', Courier, monospace;
            padding-left: 8px;
        }
        .expiry-note {
            font-size: 13px;
            color: #888888;
            margin-top: 8px;
        }
        .security-warning {
            font-size: 13px;
            color: #777777;
            border-top: 1px solid #f0e6e4;
            padding-top: 20px;
            margin-top: 20px;
            line-height: 1.5;
        }
        .footer {
            background-color: #faf5f4;
            padding: 20px 40px;
            text-align: center;
            font-size: 12px;
            color: #999999;
            border-top: 1px solid #f0e6e4;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="main">
            <div class="header">
                <div class="brand-title">{$site_name}</div>
            </div>
            <div class="content">
                <div class="greeting">Hello {$to_name},</div>
                <div class="description">
                    Thank you for signing up with {$site_name}. Please use the verification code below to verify your email address and complete your account registration.
                </div>
                <div class="otp-container">
                    <div class="otp-label">One-Time Verification Code</div>
                    <div class="otp-code">{$otp_code}</div>
                    <div class="expiry-note">⏱ Code expires in <strong>10 minutes</strong></div>
                </div>
                <div class="security-warning">
                    <strong>Security Notice:</strong> If you did not request this verification code, please disregard this email. Never share your verification code with anyone.
                </div>
            </div>
            <div class="footer">
                &copy; {$current_year} {$site_name}. All rights reserved.<br>
                For support, contact us at <a href="mailto:{$site_email}" style="color: #92535d; text-decoration: none;">{$site_email}</a>
            </div>
        </div>
    </div>
</body>
</html>
HTML;

    // Send the email via PHP mail()
    $sent = send_html_email($to_email, $to_name, $subject, $html_body);

    // For local development convenience: save last sent OTP in session to allow instant developer preview
    $_SESSION['dev_last_otp'] = [
        'email' => $to_email,
        'code' => $otp_code,
        'purpose' => $purpose,
        'time' => time()
    ];

    return $sent;
}

/**
 * Send order confirmation email to customer
 *
 * @param string $to_email Customer's email
 * @param string $order_number Generated order identifier
 * @param float $grand_total Order grand total
 * @param string $receiver_name Name of recipient
 * @return bool
 */
function send_order_confirmation_email($to_email, $order_number, $grand_total, $receiver_name = 'Valued Customer') {
    $site_name = defined('SITE_NAME') ? SITE_NAME : 'Pookky';
    $site_email = defined('SITE_EMAIL') ? SITE_EMAIL : 'info@pookky.com';
    $currency_symbol = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '₹';
    $current_year = date('Y');
    $formatted_amount = number_format((float)$grand_total, 2);

    $subject = "Order Confirmed: #{$order_number} - {$site_name}";
    $html_body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order Confirmation</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8f9fa; margin: 0; padding: 20px; color: #212529; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #eeeeee; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { background: #E12B56; color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
        .content { padding: 30px 25px; line-height: 1.6; }
        .order-card { background: #fdf5f7; border: 1px solid #fae1e6; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center; }
        .order-num { font-size: 20px; font-weight: bold; color: #E12B56; margin-bottom: 5px; }
        .order-total { font-size: 18px; color: #212529; font-weight: 600; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; border-top: 1px solid #eeeeee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Thank You for Your Order!</h1>
        </div>
        <div class="content">
            <p>Dear <strong>{$receiver_name}</strong>,</p>
            <p>We are excited to let you know that we have received your order and our team is getting it ready for shipment.</p>
            <div class="order-card">
                <div class="order-num">Order #{$order_number}</div>
                <div class="order-total">Total: {$currency_symbol}{$formatted_amount}</div>
            </div>
            <p>You can track the progress of your order anytime by visiting your account dashboard on our website.</p>
            <p>If you have any questions, feel free to reply directly to this email or contact our support team at <a href="mailto:{$site_email}">{$site_email}</a>.</p>
        </div>
        <div class="footer">
            &copy; {$current_year} {$site_name}. All rights reserved.
        </div>
    </div>
</body>
</html>
HTML;

    return send_html_email($to_email, $receiver_name, $subject, $html_body);
}

/**
 * Send new order notification email to the store admin / merchant
 *
 * @param int $order_id Database order ID
 * @return bool True if mail was dispatched to at least one recipient
 */
function send_admin_order_notification_email($order_id) {
    global $pdo;

    $enabled = defined('ORDER_NOTIFICATION_ENABLED') ? ORDER_NOTIFICATION_ENABLED : (get_setting('order_notification_enabled', '1') === '1');
    if (!$enabled) {
        return false;
    }

    $recipients_str = defined('ORDER_NOTIFICATION_EMAIL') ? ORDER_NOTIFICATION_EMAIL : get_setting('order_notification_email', 'pookkyinnerwears@gmail.com');
    if (empty(trim((string)$recipients_str))) {
        $recipients_str = 'pookkyinnerwears@gmail.com';
    }

    // Parse comma-separated emails
    $raw_emails = explode(',', (string)$recipients_str);
    $valid_recipients = [];
    foreach ($raw_emails as $em) {
        $clean = trim($em);
        if (filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            $valid_recipients[] = $clean;
        }
    }

    if (empty($valid_recipients)) {
        return false;
    }

    // Fetch order details
    $order_stmt = $pdo->prepare("
        SELECT o.*, c.email as customer_account_email, c.name as customer_account_name 
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.id 
        WHERE o.id = ? LIMIT 1
    ");
    $order_stmt->execute([$order_id]);
    $order = $order_stmt->fetch();

    if (!$order) {
        return false;
    }

    // Fetch order items
    $items_stmt = $pdo->prepare("
        SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC
    ");
    $items_stmt->execute([$order_id]);
    $items = $items_stmt->fetchAll();

    $site_name = defined('SITE_NAME') ? SITE_NAME : 'Pookky';
    $currency_symbol = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '₹';
    $order_number = $order['order_number'];
    $grand_total = number_format((float)$order['grand_total'], 2);
    $subtotal = number_format((float)$order['subtotal'], 2);
    $shipping = (float)$order['shipping_charge'] > 0 ? $currency_symbol . number_format((float)$order['shipping_charge'], 2) : 'FREE';
    $discount = (float)$order['discount'] > 0 ? '-' . $currency_symbol . number_format((float)$order['discount'], 2) : 'None';
    $coupon = !empty($order['coupon_code']) ? htmlspecialchars($order['coupon_code']) : '';
    $payment_method = strtoupper($order['payment_method'] ?? 'COD');
    $payment_status = strtoupper($order['payment_status'] ?? 'PENDING');
    $is_paid = ($payment_status === 'PAID');

    // Receiver info
    $receiver_name = htmlspecialchars($order['receiver_name'] ?? 'Customer');
    $receiver_phone = htmlspecialchars($order['receiver_phone'] ?? '');
    $customer_email = htmlspecialchars($order['customer_account_email'] ?? '');
    $full_address = htmlspecialchars(trim(($order['address_line1'] ?? '') . (!empty($order['address_line2']) ? ', ' . $order['address_line2'] : '')));
    $city_state_pin = htmlspecialchars(trim(($order['city'] ?? '') . ', ' . ($order['state'] ?? '') . ' - ' . ($order['pincode'] ?? '')));
    $order_date = date('d M, Y - h:i A', strtotime($order['created_at']));

    // Protocol & Domain for 1-click links
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Build admin links
    $admin_order_url = $protocol . $host . '/admin/orders.php?search=' . urlencode($order_number);
    $admin_shipping_label_url = $protocol . $host . '/admin/shipping-label.php?order_id=' . $order_id;

    // Build items HTML table rows
    $items_html = '';
    $total_qty = 0;
    foreach ($items as $item) {
        $p_name = htmlspecialchars($item['product_name']);
        $p_sku = htmlspecialchars($item['sku'] ?? 'N/A');
        $size_str = htmlspecialchars($item['size_name'] ?? '');
        $color_str = htmlspecialchars($item['color_name'] ?? '');
        $qty = (int)$item['quantity'];
        $total_qty += $qty;
        $price = $currency_symbol . number_format((float)$item['price'], 2);
        $row_total = $currency_symbol . number_format((float)$item['total'], 2);

        $variant_desc = [];
        if (!empty($size_str)) $variant_desc[] = "Size: <strong>{$size_str}</strong>";
        if (!empty($color_str)) $variant_desc[] = "Color: <strong>{$color_str}</strong>";
        $variant_line = !empty($variant_desc) ? '<div style="font-size: 12px; color: #666666; margin-top: 3px;">' . implode(' &bull; ', $variant_desc) . '</div>' : '';

        // Combo breakdown if available
        $combo_note = '';
        if (!empty($item['combo_product_id']) && !empty($item['combo_config'])) {
            $c_conf = json_decode($item['combo_config'], true);
            if (is_array($c_conf) && !empty($c_conf['selections'])) {
                $c_items = [];
                foreach ($c_conf['selections'] as $c_sel) {
                    $c_items[] = htmlspecialchars(($c_sel['product_name'] ?? '') . ' (' . ($c_sel['size_name'] ?? '') . ')');
                }
                $combo_note = '<div style="font-size: 11px; color: #a12b48; margin-top: 4px; background: #fff5f7; padding: 4px 8px; border-radius: 4px;">Bundle contains: ' . implode(', ', $c_items) . '</div>';
            }
        }

        $items_html .= "
        <tr style=\"border-bottom: 1px solid #f0e6e4;\">
            <td style=\"padding: 12px 10px; vertical-align: top;\">
                <strong style=\"color: #1a1a1a;\">{$p_name}</strong>
                {$variant_line}
                {$combo_note}
                <div style=\"font-size: 11px; color: #888888; margin-top: 2px;\">SKU: {$p_sku}</div>
            </td>
            <td style=\"padding: 12px 10px; text-align: center; vertical-align: top;\">{$qty}</td>
            <td style=\"padding: 12px 10px; text-align: right; vertical-align: top;\">{$price}</td>
            <td style=\"padding: 12px 10px; text-align: right; vertical-align: top; font-weight: 600;\">{$row_total}</td>
        </tr>";
    }

    $subject = "🚨 New Order Received: #{$order_number} ({$currency_symbol}{$grand_total} - {$payment_method})";

    $status_badge_bg = $is_paid ? '#28a745' : '#e67e22';
    $status_badge_text = $is_paid ? 'PAID &bull; ONLINE' : 'CASH ON DELIVERY (PENDING)';

    // Build coupon HTML if applicable
    $coupon_row = '';
    if (!empty($coupon) && $discount !== 'None') {
        $coupon_row = "
        <tr>
            <td style=\"color: #28a745;\">Coupon Discount ({$coupon})</td>
            <td style=\"text-align: right; color: #28a745; font-weight: 600;\">{$discount}</td>
        </tr>";
    }

    $html_body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$subject}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f7f6f2; margin: 0; padding: 25px 15px; color: #2b2b2b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 14px; overflow: hidden; border: 1px solid #e8e3df; box-shadow: 0 4px 20px rgba(0,0,0,0.06);">
        <!-- Header -->
        <tr>
            <td style="background: linear-gradient(135deg, #24161a 0%, #3d232b 100%); padding: 28px 30px; text-align: center;">
                <div style="color: #fdf5f2; font-size: 24px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;">{$site_name}</div>
                <div style="color: #dca59b; font-size: 13px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; margin-top: 4px;">⚡ New Order Alert</div>
            </td>
        </tr>

        <!-- Alert Summary Banner -->
        <tr>
            <td style="padding: 24px 30px; background: #fdf7f5; border-bottom: 1px solid #f0e6e4;">
                <table width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td>
                            <div style="font-size: 12px; color: #888888; text-transform: uppercase; letter-spacing: 0.5px;">Order Number</div>
                            <div style="font-size: 22px; font-weight: 700; color: #92535d; margin-top: 2px;">#{$order_number}</div>
                            <div style="font-size: 12px; color: #777777; margin-top: 3px;">Placed on {$order_date}</div>
                        </td>
                        <td style="text-align: right; vertical-align: middle;">
                            <span style="display: inline-block; background: {$status_badge_bg}; color: #ffffff; font-size: 11px; font-weight: 700; padding: 6px 12px; border-radius: 20px; letter-spacing: 0.5px;">
                                {$status_badge_text}
                            </span>
                            <div style="font-size: 22px; font-weight: 700; color: #1a1a1a; margin-top: 6px;">{$currency_symbol}{$grand_total}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Customer & Delivery Info -->
        <tr>
            <td style="padding: 24px 30px;">
                <table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom: 20px;">
                    <tr>
                        <td width="50%" style="vertical-align: top; padding-right: 15px;">
                            <div style="font-size: 11px; font-weight: 700; color: #92535d; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">👤 Customer Details</div>
                            <div style="font-size: 15px; font-weight: 600; color: #1a1a1a;">{$receiver_name}</div>
                            <div style="font-size: 13px; color: #444444; margin-top: 4px;">📞 <a href="tel:{$receiver_phone}" style="color: #2b2b2b; text-decoration: none; font-weight: 600;">{$receiver_phone}</a></div>
                            <div style="font-size: 13px; color: #666666; margin-top: 2px;">✉️ <a href="mailto:{$customer_email}" style="color: #92535d; text-decoration: none;">{$customer_email}</a></div>
                        </td>
                        <td width="50%" style="vertical-align: top; padding-left: 15px; border-left: 1px solid #f0e6e4;">
                            <div style="font-size: 11px; font-weight: 700; color: #92535d; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">📍 Shipping Address</div>
                            <div style="font-size: 13px; color: #333333; line-height: 1.5;">
                                {$full_address}<br>
                                <strong>{$city_state_pin}</strong>
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- Items Breakdown -->
                <div style="font-size: 13px; font-weight: 700; color: #1a1a1a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; border-bottom: 2px solid #24161a; padding-bottom: 6px;">
                    📦 Order Items ({$total_qty} units)
                </div>
                <table width="100%" cellspacing="0" cellpadding="0" style="font-size: 13px; border-collapse: collapse; margin-bottom: 20px;">
                    <thead>
                        <tr style="background: #faf5f4; color: #777777; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <th style="padding: 8px 10px; text-align: left;">Item</th>
                            <th style="padding: 8px 10px; text-align: center;">Qty</th>
                            <th style="padding: 8px 10px; text-align: right;">Unit</th>
                            <th style="padding: 8px 10px; text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$items_html}
                    </tbody>
                </table>

                <!-- Financials -->
                <table width="100%" cellspacing="0" cellpadding="0" style="font-size: 13px; line-height: 1.8; margin-bottom: 25px;">
                    <tr>
                        <td style="color: #666666;">Subtotal</td>
                        <td style="text-align: right; color: #1a1a1a; font-weight: 600;">{$currency_symbol}{$subtotal}</td>
                    </tr>
                    <tr>
                        <td style="color: #666666;">Shipping Charge</td>
                        <td style="text-align: right; color: #1a1a1a; font-weight: 600;">{$shipping}</td>
                    </tr>
                    {$coupon_row}
                    <tr style="border-top: 2px solid #24161a; font-size: 16px;">
                        <td style="padding-top: 8px; font-weight: 700; color: #1a1a1a;">Grand Total</td>
                        <td style="padding-top: 8px; text-align: right; font-weight: 700; color: #92535d;">{$currency_symbol}{$grand_total}</td>
                    </tr>
                </table>

                <!-- Action CTA Buttons -->
                <table width="100%" cellspacing="0" cellpadding="0" style="margin-top: 25px;">
                    <tr>
                        <td align="center">
                            <a href="{$admin_order_url}" style="display: inline-block; background: #92535d; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 25px; font-weight: 600; font-size: 13px; margin: 4px;">
                                🔍 View Order in Admin
                            </a>
                            <a href="{$admin_shipping_label_url}" style="display: inline-block; background: #24161a; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 25px; font-weight: 600; font-size: 13px; margin: 4px;">
                                🏷️ Print Shipping Label
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td style="background-color: #faf5f4; padding: 18px 30px; text-align: center; font-size: 11px; color: #888888; border-top: 1px solid #f0e6e4;">
                This is an automated operational notification sent from your <strong>{$site_name}</strong> store platform.<br>
                To change where order notifications are sent, visit Admin &rarr; Global Settings &rarr; Contact &amp; Support.
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

    // Send to all valid recipient emails
    $any_sent = false;
    foreach ($valid_recipients as $recipient_email) {
        $sent = send_html_email($recipient_email, "{$site_name} Admin", $subject, $html_body);
        if ($sent) {
            $any_sent = true;
        }
    }

    return $any_sent;
}
?>
