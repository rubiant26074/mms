<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$uid = (int)$_SESSION['user_id'];
$role = (string)($_SESSION['role'] ?? ($_SESSION['user_role'] ?? ''));

try {
    $sql_count = "SELECT COUNT(*) 
                  FROM notifications
                  WHERE (user_id = ? OR LOWER(target_role) = LOWER(?))
                    AND is_read = 0";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute([$uid, $role]);
    $unread = (int)$stmt_count->fetchColumn();

    $sql_list = "SELECT id, title, message, link, is_read, created_at
                 FROM notifications
                 WHERE (user_id = ? OR LOWER(target_role) = LOWER(?))
                 ORDER BY created_at DESC
                 LIMIT 10";
    $stmt_list = $pdo->prepare($sql_list);
    $stmt_list->execute([$uid, $role]);
    $items = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'unread_count' => $unread,
        'unread' => $unread,
        'items' => $items
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
