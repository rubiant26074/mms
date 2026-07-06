<?php
// modules/hrd/payroll/get_attendance_count.php
require_once '../../../config/database.php';

$uid = $_GET['uid'] ?? 0;
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

if ($uid && $start && $end) {
    // Hitung kehadiran (present/late)
    $sql = "SELECT COUNT(*) FROM attendance 
            WHERE user_id = ? AND date BETWEEN ? AND ? 
            AND status IN ('present', 'late')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid, $start, $end]);
    $count = $stmt->fetchColumn();
    
    echo json_encode(['count' => $count]);
} else {
    echo json_encode(['count' => 0]);
}
?>