<?php
session_start();
require_once '../../config/database.php';

if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $role = (string)($_SESSION['role'] ?? ($_SESSION['user_role'] ?? ''));
    $stmt = $pdo->prepare("UPDATE notifications 
                           SET is_read = 1 
                           WHERE user_id = ? OR LOWER(target_role) = LOWER(?)");
    $stmt->execute([$uid, $role]);
}

$back = isset($_GET['back']) ? urldecode((string)$_GET['back']) : 'index.php';
if (preg_match('/^https?:\\/\\//i', $back) || strpos($back, '//') === 0) {
    $back = 'index.php';
}
$back = ltrim($back, '/');
if ($back === '') $back = 'index.php';
header("Location: ../../" . $back);
exit();
?>
