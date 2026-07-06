<?php
// modules/sales/quotations/delete.php

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('sales_quotation_delete')) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=sales-quote';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$csrf = $_GET['csrf'] ?? $_POST['csrf'] ?? '';
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=sales-quote';</script>";
    exit;
}

if ($id > 0) {
    try {
        // Cek status dulu, hanya draft yang boleh dihapus
        $stmt_check = $pdo->prepare("SELECT status FROM quotations WHERE id = ?");
        $stmt_check->execute([$id]);
        $status = $stmt_check->fetchColumn();

        if ($status !== 'draft' && $status !== 'rejected') {
            echo "<script>alert('Hanya penawaran berstatus Draft atau Rejected yang boleh dihapus.'); window.location='index.php?page=sales-quote';</script>";
            exit;
        }

        // Hapus Header (Detail akan terhapus otomatis karena ON DELETE CASCADE di SQL)
        $stmt = $pdo->prepare("DELETE FROM quotations WHERE id = ?");
        $stmt->execute([$id]);
        
        echo "<script>alert('Penawaran berhasil dihapus.'); window.location='index.php?page=sales-quote';</script>";

    } catch (PDOException $e) {
        echo "<script>alert('Gagal menghapus data.'); window.location='index.php?page=sales-quote';</script>";
    }
} else {
    mms_redirect('index.php?page=sales-quote');
}
?>
