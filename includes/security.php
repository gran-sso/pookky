<?php
// includes/security.php
// CSRF Protection and Upload Validation helper functions

// Generate a secure CSRF token if one doesn't exist
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify a CSRF token
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Generate the CSRF hidden input field for forms
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

// Image upload validator and processor
// Returns array [ 'success' => bool, 'filename' => string|null, 'error' => string|null ]
function validate_image_upload($file, $target_dir = '../uploads/') {
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size = 50 * 1024 * 1024; // 50MB
 
     if ($file['error'] !== UPLOAD_ERR_OK) {
         return ['success' => false, 'filename' => null, 'error' => 'File upload error occurred.'];
     }
 
     if ($file['size'] > $max_size) {
         return ['success' => false, 'filename' => null, 'error' => 'File size exceeds maximum limit of 50MB.'];
     }

    // Double check extensions and mime-types
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_extensions)) {
        return ['success' => false, 'filename' => null, 'error' => 'Invalid file extension. Only JPG, JPEG, PNG, and WEBP allowed.'];
    }

    if (!function_exists('mime_content_type')) {
        // Fallback if mime_content_type doesn't exist (rare on standard PHP hostings)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $mime_type = mime_content_type($file['tmp_name']);
    }

    if (!in_array($mime_type, $allowed_mime_types)) {
        return ['success' => false, 'filename' => null, 'error' => 'Invalid file mime type. The file must be a real image.'];
    }

    // Auto create upload directory if missing
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    // Generate unique name to prevent collisions and execution
    $new_filename = uniqid('img_', true) . '.' . $file_ext;
    $dest_path = rtrim($target_dir, '/') . '/' . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        return ['success' => true, 'filename' => $new_filename, 'error' => null];
    } else {
        return ['success' => false, 'filename' => null, 'error' => 'Failed to move uploaded file. Check directory write permissions.'];
    }
}

/**
 * Safely resolves the client's public IP address, supporting Cloudflare & proxy headers
 */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Ensures the login_attempts table exists in the database
 */
function ensure_login_throttle_schema_exists() {
    global $pdo;
    static $checked = false;
    if ($checked || !isset($pdo) || !($pdo instanceof PDO)) return;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                attempt_key VARCHAR(64) NOT NULL,
                user_identifier VARCHAR(150) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_type ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
                attempts INT NOT NULL DEFAULT 1,
                last_attempt DATETIME NOT NULL,
                locked_until DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_attempt_key_type (attempt_key, user_type),
                INDEX idx_locked (locked_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $checked = true;
    } catch (PDOException $e) {
        // Continue silently if check fails or table already exists
    }
}

/**
 * Check if a login attempt is throttled or locked out
 *
 * @param string $identifier Email or username
 * @param string $user_type 'customer' or 'admin'
 * @param int $max_attempts Maximum allowed failed attempts before lockout (default: 5)
 * @return array ['allowed' => bool, 'remaining_attempts' => int, 'remaining_seconds' => int, 'message' => string]
 */
function check_login_throttle($identifier, $user_type = 'customer', $max_attempts = 5) {
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return ['allowed' => true, 'remaining_attempts' => $max_attempts, 'remaining_seconds' => 0, 'message' => ''];
    }

    ensure_login_throttle_schema_exists();

    $clean_ident = strtolower(trim((string)$identifier));
    if (empty($clean_ident)) {
        return ['allowed' => true, 'remaining_attempts' => $max_attempts, 'remaining_seconds' => 0, 'message' => ''];
    }

    $ip = get_client_ip();
    $attempt_key = hash('sha256', $clean_ident . '|' . $ip);

    try {
        $stmt = $pdo->prepare("
            SELECT attempts, UNIX_TIMESTAMP(locked_until) as locked_ts, UNIX_TIMESTAMP(last_attempt) as last_ts 
            FROM login_attempts 
            WHERE attempt_key = ? AND user_type = ? 
            LIMIT 1
        ");
        $stmt->execute([$attempt_key, $user_type]);
        $row = $stmt->fetch();

        if ($row) {
            $now = time();
            $locked_ts = (int)($row['locked_ts'] ?? 0);

            // If currently locked out
            if ($locked_ts > $now) {
                $remaining_seconds = $locked_ts - $now;
                $remaining_minutes = max(1, ceil($remaining_seconds / 60));
                return [
                    'allowed' => false,
                    'remaining_attempts' => 0,
                    'remaining_seconds' => $remaining_seconds,
                    'message' => "Too many failed login attempts. Please wait {$remaining_minutes} minute" . ($remaining_minutes > 1 ? 's' : '') . " before trying again."
                ];
            }

            // If last attempt was older than 30 minutes, reset attempts
            if ($row['last_ts'] && ($now - (int)$row['last_ts']) > 1800) {
                clear_login_throttle($identifier, $user_type);
                return ['allowed' => true, 'remaining_attempts' => $max_attempts, 'remaining_seconds' => 0, 'message' => ''];
            }

            $current_attempts = (int)$row['attempts'];
            $remaining = max(0, $max_attempts - $current_attempts);

            return [
                'allowed' => true,
                'remaining_attempts' => $remaining,
                'remaining_seconds' => 0,
                'message' => ''
            ];
        }
    } catch (PDOException $e) {
        // Fallback safely to not block legitimate users on error
    }

    return ['allowed' => true, 'remaining_attempts' => $max_attempts, 'remaining_seconds' => 0, 'message' => ''];
}

/**
 * Record a failed login attempt and apply temporary lockout if limit reached
 *
 * @param string $identifier Email or username
 * @param string $user_type 'customer' or 'admin'
 * @param int $max_attempts Max attempts before lockout (default: 5)
 * @param int $lockout_minutes Minutes to pause (default: 15)
 * @return array ['locked' => bool, 'remaining_attempts' => int, 'message' => string]
 */
function record_failed_login($identifier, $user_type = 'customer', $max_attempts = 5, $lockout_minutes = 15) {
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return ['locked' => false, 'remaining_attempts' => $max_attempts - 1, 'message' => ''];
    }

    ensure_login_throttle_schema_exists();

    $clean_ident = strtolower(trim((string)$identifier));
    if (empty($clean_ident)) {
        return ['locked' => false, 'remaining_attempts' => $max_attempts - 1, 'message' => ''];
    }

    $ip = get_client_ip();
    $attempt_key = hash('sha256', $clean_ident . '|' . $ip);

    try {
        $stmt = $pdo->prepare("
            SELECT id, attempts, UNIX_TIMESTAMP(locked_until) as locked_ts, UNIX_TIMESTAMP(last_attempt) as last_ts 
            FROM login_attempts 
            WHERE attempt_key = ? AND user_type = ? 
            LIMIT 1
        ");
        $stmt->execute([$attempt_key, $user_type]);
        $row = $stmt->fetch();

        $now = time();

        if ($row) {
            $last_ts = (int)($row['last_ts'] ?? 0);
            if (($now - $last_ts) > 1800) {
                $new_attempts = 1;
            } else {
                $new_attempts = (int)$row['attempts'] + 1;
            }

            if ($new_attempts >= $max_attempts) {
                $locked_until = date('Y-m-d H:i:s', $now + ($lockout_minutes * 60));
                $up = $pdo->prepare("
                    UPDATE login_attempts 
                    SET attempts = ?, last_attempt = NOW(), locked_until = ? 
                    WHERE id = ?
                ");
                $up->execute([$new_attempts, $locked_until, $row['id']]);

                return [
                    'locked' => true,
                    'remaining_attempts' => 0,
                    'message' => "Too many failed login attempts. For security, access is paused for {$lockout_minutes} minutes. Please try again later."
                ];
            } else {
                $up = $pdo->prepare("
                    UPDATE login_attempts 
                    SET attempts = ?, last_attempt = NOW(), locked_until = NULL 
                    WHERE id = ?
                ");
                $up->execute([$new_attempts, $row['id']]);
                $remaining = max(0, $max_attempts - $new_attempts);

                return [
                    'locked' => false,
                    'remaining_attempts' => $remaining,
                    'message' => "Invalid email or password. You have {$remaining} attempt" . ($remaining === 1 ? '' : 's') . " remaining before a temporary pause."
                ];
            }
        } else {
            // First failed attempt
            $ins = $pdo->prepare("
                INSERT INTO login_attempts (attempt_key, user_identifier, ip_address, user_type, attempts, last_attempt) 
                VALUES (?, ?, ?, ?, 1, NOW())
            ");
            $ins->execute([$attempt_key, $clean_ident, $ip, $user_type]);
            $remaining = $max_attempts - 1;

            return [
                'locked' => false,
                'remaining_attempts' => $remaining,
                'message' => "Invalid email or password. You have {$remaining} attempt" . ($remaining === 1 ? '' : 's') . " remaining before a temporary pause."
            ];
        }
    } catch (PDOException $e) {
        return ['locked' => false, 'remaining_attempts' => $max_attempts - 1, 'message' => ''];
    }
}

/**
 * Clears recorded failed attempts upon successful login
 */
function clear_login_throttle($identifier, $user_type = 'customer') {
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) return;

    $clean_ident = strtolower(trim((string)$identifier));
    if (empty($clean_ident)) return;

    $ip = get_client_ip();
    $attempt_key = hash('sha256', $clean_ident . '|' . $ip);

    try {
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_key = ? AND user_type = ?");
        $stmt->execute([$attempt_key, $user_type]);
    } catch (PDOException $e) {
        // Continue silently
    }
}
?>
