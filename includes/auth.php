<?php
// includes/auth.php
// User & Admin Authentication functions

require_once __DIR__ . '/../config/database.php';

// Customer Authentication Helpers
function is_customer_logged_in() {
    return isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id']);
}

function require_customer_login() {
    if (!is_customer_logged_in()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        set_flash_message('error', 'Please login to access this page.');
        header('Location: login.php');
        exit;
    }
}

function get_logged_in_customer() {
    global $pdo;
    if (!is_customer_logged_in()) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['customer_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

// Admin Authentication Helpers
function is_admin_logged_in() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function require_admin_login() {
    if (!is_admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

// Checks if current logged admin has specified permission
function has_admin_permission($permission) {
    global $pdo;
    if (!is_admin_logged_in()) {
        return false;
    }

    try {
        // Fetch role permissions
        $stmt = $pdo->prepare("
            SELECT r.permissions 
            FROM admins a 
            JOIN roles r ON a.role_id = r.id 
            WHERE a.id = ? LIMIT 1
        ");
        $stmt->execute([$_SESSION['admin_id']]);
        $row = $stmt->fetch();
        
        if (!$row) return false;

        $perms = json_decode($row['permissions'], true);
        
        // If super admin, allow all
        if (isset($perms['all']) && $perms['all'] === true) {
            return true;
        }

        // Check granular permission
        if (isset($perms[$permission]) && ($perms[$permission] === 'write' || $perms[$permission] === 'read' || $perms[$permission] === true)) {
            return true;
        }

        return false;
    } catch (PDOException $e) {
        return false;
    }
}

function require_admin_permission($permission) {
    require_admin_login();
    if (!has_admin_permission($permission)) {
        // Redirect to admin dashboard with message
        set_flash_message('error', 'Unauthorized access! You do not have permission to access that module.');
        header('Location: index.php');
        exit;
    }
}
?>
