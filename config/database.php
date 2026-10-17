<?php
// config/database.php
// Secure PDO Database connection settings

if (defined('POOKKY_DATABASE_LOADED') && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $pdo = $GLOBALS['pdo'];
    return;
}
define('POOKKY_DATABASE_LOADED', true);

if (!ob_get_level()) {
    ob_start();
}

// Helper to get environment variables with a fallback
if (!function_exists('env')) {
    function env($key, $default = null) {
        $val = getenv($key);
        if ($val === false) {
            $val = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }
        if ($val === null) {
            return $default;
        }
        if ($val === 'true') return true;
        if ($val === 'false') return false;
        if ($val === 'empty' || $val === 'null') return '';
        return $val;
    }
}

// Load environment variables from .env file
if (!function_exists('load_env')) {
    function load_env($filePath) {
        if (!file_exists($filePath)) {
            return;
        }
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1]);
                $val = preg_replace('/^["\']|["\']$/', '', $val);
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}
load_env(__DIR__ . '/../.env');

// Environment-aware error reporting (defaults to production on live domain)
$is_local_env = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
$app_env = env('APP_ENV', $is_local_env ? 'development' : 'production');
if ($app_env === 'production') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING);
    ini_set('log_errors', 1);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
}

if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session settings before starting session
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (env('SESSION_SECURE', false)) {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

if (!ob_get_level()) {
    ob_start();
}

$env_host = env('DB_HOST');
$env_user = env('DB_USER');
$env_pass = env('DB_PASS');
$env_name = env('DB_NAME');
$env_port = env('DB_PORT');

try {
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $connected = false;
    $connection_error = '';

    // If environment variables are set, attempt connection using them exactly
    if ($env_host !== null && $env_name !== null) {
        if (!defined('DB_HOST')) define('DB_HOST', $env_host);
        if (!defined('DB_USER')) define('DB_USER', $env_user ?? 'root');
        if (!defined('DB_PASS')) define('DB_PASS', $env_pass ?? '');
        if (!defined('DB_NAME')) define('DB_NAME', $env_name);
        
        $port_str = $env_port ? ";port=" . $env_port : "";
        try {
            $dsn = "mysql:host=" . DB_HOST . $port_str . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $connected = true;
        } catch (PDOException $e) {
            $connection_error = $e->getMessage();
        }
    }

    // Connection attempts chain fallback if env variables aren't set or connection failed:
    if (!$connected) {
        $fallback_host = '127.0.0.1';
        $fallback_name = 'veesta';
        
        if (!defined('DB_HOST')) {
            define('DB_HOST', $fallback_host);
            define('DB_USER', 'root');
            define('DB_PASS', '');
            define('DB_NAME', $fallback_name);
        }
        
        // Attempt 1: Port 3306, Password '' (Standard Homebrew/XAMPP)
        try {
            $dsn = "mysql:host=" . $fallback_host . ";dbname=" . $fallback_name . ";charset=utf8mb4";
            $pdo = new PDO($dsn, 'root', '', $options);
            $connected = true;
        } catch (PDOException $e) {
            $connection_error = $e->getMessage();
        }

        // Attempt 2: Port 3306, Password 'root'
        if (!$connected) {
            try {
                $dsn = "mysql:host=" . $fallback_host . ";dbname=" . $fallback_name . ";charset=utf8mb4";
                $pdo = new PDO($dsn, 'root', 'root', $options);
                $connected = true;
            } catch (PDOException $e) {
                $connection_error = $e->getMessage();
            }
        }

        // Attempt 3: Port 8889, Password 'root' (Standard MAMP default)
        if (!$connected) {
            try {
                $dsn = "mysql:host=" . $fallback_host . ";port=8889;dbname=" . $fallback_name . ";charset=utf8mb4";
                $pdo = new PDO($dsn, 'root', 'root', $options);
                $connected = true;
            } catch (PDOException $e) {
                $connection_error = $e->getMessage();
            }
        }

        // Attempt 4: Port 8889, Password ''
        if (!$connected) {
            try {
                $dsn = "mysql:host=" . $fallback_host . ";port=8889;dbname=" . $fallback_name . ";charset=utf8mb4";
                $pdo = new PDO($dsn, 'root', '', $options);
                $connected = true;
            } catch (PDOException $e) {
                $connection_error = $e->getMessage();
            }
        }
    }

    if (!$connected) {
        throw new PDOException($connection_error);
    }

} catch (PDOException $e) {
    // Hide details from user but log or show error safely
    die("Database connection failed. Check if MySQL is running. Details: " . htmlspecialchars($e->getMessage()));
}
?>
