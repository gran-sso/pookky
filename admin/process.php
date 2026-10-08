<?php
// admin/process.php
// AJAX endpoints for admin operations

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

// Guarantee admin session
if (!is_admin_logged_in()) {
    json_response('error', 'Unauthorized access.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        json_response('error', 'Invalid CSRF security token.');
    }

    // Toggle active status dynamically
    if ($action === 'toggle_status') {
        $table = $_POST['table'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['status'] ?? 0);

        // Strict table whitelist to prevent SQL injection on table name
        $allowed_tables = ['products', 'categories', 'coupons', 'banners', 'newsletter_subscribers', 'customers', 'admins'];

        if (!in_array($table, $allowed_tables)) {
            json_response('error', 'Illegal table selection.');
        }

        if ($id <= 0) {
            json_response('error', 'Invalid record identifier.');
        }

        // Prevent self suspension for admins
        if ($table === 'admins' && $id === $_SESSION['admin_id'] && $status === 0) {
            json_response('error', 'Cannot suspend your own active login profile.');
        }

        try {
            $stmt = $pdo->prepare("UPDATE {$table} SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            log_admin_action($_SESSION['admin_id'], "AJAX Toggled status of {$table} ID {$id} to {$status}");
            json_response('success', 'Status updated successfully.');
        } catch (PDOException $e) {
            json_response('error', 'Database execution failed: ' . $e->getMessage());
        }
    }
}

json_response('error', 'Invalid request action.');
?>
