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
?>
