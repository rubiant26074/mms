<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$url = isset($_GET['url']) ? urldecode((string)$_GET['url']) : 'index.php';

if ($id > 0) {
    $uid = (int)$_SESSION['user_id'];
    $role = (string)($_SESSION['role'] ?? ($_SESSION['user_role'] ?? ''));
    // Tandai sudah dibaca jika notifikasi memang milik user/role ini.
    $stmt = $pdo->prepare("UPDATE notifications 
                           SET is_read = 1 
                           WHERE id = ? 
                             AND (user_id = ? OR LOWER(target_role) = LOWER(?))");
    $stmt->execute([$id, $uid, $role]);
}

// Hindari open redirect sederhana: hanya ijinkan path lokal relatif.
if (preg_match('/^https?:\\/\\//i', $url) || strpos($url, '//') === 0) {
    $url = 'index.php';
}
$url = ltrim($url, '/');
if ($url === '') $url = 'index.php';

// Tambahkan CSRF token untuk URL action internal yang mengubah data.
if (function_exists('mms_csrf_token')) {
    $parts = parse_url($url);
    $path = $parts['path'] ?? '';
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $hasAction = isset($query['action']) && $query['action'] !== '';
    if ($hasAction && empty($query['csrf'])) {
        $query['csrf'] = mms_csrf_token();
        $newQuery = http_build_query($query);
        $url = $path . ($newQuery !== '' ? ('?' . $newQuery) : '');
    }
}

header("Location: ../../" . $url);
exit();
?>
