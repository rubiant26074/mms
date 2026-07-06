<?php
require_once 'config/database.php';
require_once 'config/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (function_exists('record_log') && isset($_SESSION['user_id'])) {
    record_log('AUTH', 'LOGOUT', 'User logout');
}

$_SESSION = [];
session_destroy();
header("Location: index.php");
exit();
?>
