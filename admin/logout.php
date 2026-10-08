<?php
// admin/logout.php
// Administrator session teardown

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_admin_logged_in()) {
    log_admin_action($_SESSION['admin_id'], 'Logout successful');
    unset($_SESSION['admin_id']);
}

session_destroy();
header('Location: login.php');
exit;
?>
