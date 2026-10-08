<?php
// includes/otp.php
// One-Time Password (OTP) Generation and Verification Engine

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mail.php';

/**
 * Ensure OTP table and customer columns exist in database
 */
function ensure_otp_schema_exists() {
    global $pdo;
    static $checked = false;
    if ($checked) return;

    try {
        // Create email_otps table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS email_otps (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(100) NOT NULL,
                otp_code VARCHAR(10) NOT NULL,
                purpose VARCHAR(50) NOT NULL DEFAULT 'registration',
                expires_at DATETIME NOT NULL,
                is_used TINYINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_email_purpose (email, purpose, is_used)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Check if email_verified column exists on customers table
        $col_check = $pdo->query("SHOW COLUMNS FROM customers LIKE 'email_verified'")->fetch();
        if (!$col_check) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN email_verified TINYINT DEFAULT 1 COMMENT '1=Verified, 0=Unverified' AFTER status");
        }

        $col_check2 = $pdo->query("SHOW COLUMNS FROM customers LIKE 'email_verified_at'")->fetch();
        if (!$col_check2) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN email_verified_at TIMESTAMP NULL DEFAULT NULL AFTER email_verified");
        }

        $checked = true;
    } catch (PDOException $e) {
        // Ignore or log error to prevent breaking application
    }
}

// Auto-check schema on include
ensure_otp_schema_exists();

/**
 * Generate a new 6-digit OTP code and persist it
 *
 * @param string $email Recipient email
 * @param string $purpose Purpose of OTP ('registration', 'login', etc.)
 * @param int $expiry_minutes Lifetime in minutes
 * @return string The generated 6-digit OTP
 */
function create_otp($email, $purpose = 'registration', $expiry_minutes = 10) {
    global $pdo;
    ensure_otp_schema_exists();

    $email = strtolower(trim($email));

    // Invalidate prior unused OTPs for this email and purpose
    try {
        $stmt = $pdo->prepare("UPDATE email_otps SET is_used = 2 WHERE email = ? AND purpose = ? AND is_used = 0");
        $stmt->execute([$email, $purpose]);
    } catch (PDOException $e) {
        // Continue
    }

    // Generate cryptographically secure 6-digit OTP
    $otp_code = (string)random_int(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', time() + ($expiry_minutes * 60));

    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_otps (email, otp_code, purpose, expires_at, is_used) 
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute([$email, $otp_code, $purpose, $expires_at]);
    } catch (PDOException $e) {
        error_log("OTP generation error: " . $e->getMessage());
    }

    return $otp_code;
}

/**
 * Check if user is allowed to request a new OTP based on cooldown timer
 *
 * @param string $email Recipient email
 * @param string $purpose Purpose
 * @param int $cooldown_seconds Minimum delay between resends
 * @return array ['allowed' => bool, 'remaining_seconds' => int]
 */
function can_resend_otp($email, $purpose = 'registration', $cooldown_seconds = 60) {
    global $pdo;
    ensure_otp_schema_exists();

    $email = strtolower(trim($email));

    try {
        $stmt = $pdo->prepare("
            SELECT UNIX_TIMESTAMP(created_at) as created_ts 
            FROM email_otps 
            WHERE email = ? AND purpose = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$email, $purpose]);
        $last_otp = $stmt->fetch();

        if ($last_otp) {
            $elapsed = time() - (int)$last_otp['created_ts'];
            if ($elapsed < $cooldown_seconds) {
                return [
                    'allowed' => false,
                    'remaining_seconds' => $cooldown_seconds - $elapsed
                ];
            }
        }
    } catch (PDOException $e) {
        // Fallback
    }

    return [
        'allowed' => true,
        'remaining_seconds' => 0
    ];
}

/**
 * Verify submitted OTP code
 *
 * @param string $email Recipient email
 * @param string $otp_code Submitted OTP
 * @param string $purpose Purpose
 * @return array ['success' => bool, 'message' => string]
 */
function verify_otp($email, $otp_code, $purpose = 'registration') {
    global $pdo;
    ensure_otp_schema_exists();

    $email = strtolower(trim($email));
    $otp_code = trim($otp_code);

    if (empty($otp_code) || strlen($otp_code) !== 6) {
        return [
            'success' => false,
            'message' => 'Please enter a valid 6-digit verification code.'
        ];
    }

    try {
        // Find latest matching OTP
        $stmt = $pdo->prepare("
            SELECT id, otp_code, expires_at, is_used 
            FROM email_otps 
            WHERE email = ? AND purpose = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$email, $purpose]);
        $row = $stmt->fetch();

        if (!$row) {
            return [
                'success' => false,
                'message' => 'No verification code was found. Please request a new one.'
            ];
        }

        if ((int)$row['is_used'] === 1) {
            return [
                'success' => false,
                'message' => 'This verification code has already been used. Please request a new one.'
            ];
        }

        // Check expiry
        if (strtotime($row['expires_at']) < time()) {
            return [
                'success' => false,
                'message' => 'Verification code has expired. Please request a new code.'
            ];
        }

        // Validate code equality
        if ($row['otp_code'] !== $otp_code) {
            return [
                'success' => false,
                'message' => 'Invalid verification code. Please check your email and try again.'
            ];
        }

        // Mark OTP as used
        $update_stmt = $pdo->prepare("UPDATE email_otps SET is_used = 1 WHERE id = ?");
        $update_stmt->execute([$row['id']]);

        return [
            'success' => true,
            'message' => 'Email verified successfully!'
        ];

    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Verification system error. Please try again.'
        ];
    }
}
?>
