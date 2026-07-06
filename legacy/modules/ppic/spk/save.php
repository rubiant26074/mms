<?php
// modules/ppic/spk/save.php
session_start();
require_once '../../../config/database.php';
require_once '../../../config/functions.php';
require_once __DIR__ . '/service.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_mms_csrf_token($csrf)) {
        echo "<script>alert('Token keamanan tidak valid.'); window.location='../../../index.php?page=ppic-spk';</script>";
        exit;
    }

    try {
        ppic_spk_save($pdo, [
            'id' => isset($_POST['id']) ? (int)$_POST['id'] : 0,
            'sales_order_id' => $_POST['sales_order_id'] ?? 0,
            'spk_date' => $_POST['spk_date'] ?? date('Y-m-d'),
            'deadline_date' => $_POST['deadline_date'] ?? date('Y-m-d'),
            'priority' => $_POST['priority'] ?? 'normal',
            'notes' => $_POST['notes'] ?? '',
            'processes' => isset($_POST['processes']) && is_array($_POST['processes']) ? $_POST['processes'] : [],
            'mat_id' => $_POST['mat_id'] ?? [],
            'mat_qty' => $_POST['mat_qty'] ?? [],
            'user_id' => (int)($_SESSION['user_id'] ?? 0),
        ]);
        echo "<script>alert('SPK Berhasil Disimpan!'); window.location='../../../index.php?page=ppic-spk';</script>";

    } catch (Exception $e) {
        error_log('[PPIC-SPK save] ' . $e->getMessage());
        echo "<script>alert('Gagal menyimpan SPK. Silakan coba lagi.'); window.location='../../../index.php?page=ppic-spk';</script>";
    }
}
