<?php
// logout.php
// Customer logout handler

session_start();
unset($_SESSION['customer_id']);
unset($_SESSION['customer_name']);

// Keep admin session active if they are logged in separately, else destroy
if (empty($_SESSION['admin_id'])) {
    session_destroy();
}

header('Location: index.php');
exit;
?>
