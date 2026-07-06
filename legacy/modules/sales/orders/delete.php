<?php
// modules/sales/orders/delete.php

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('sales_so_manage')) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=sales-so';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$csrf = $_GET['csrf'] ?? $_POST['csrf'] ?? '';
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=sales-so';</script>";
    exit;
}

if ($id > 0) {
    try {
        // Cek status, hanya draft yang boleh dihapus
        $check = $pdo->prepare("SELECT status FROM sales_orders WHERE id = ?");
        $check->execute([$id]);
        $status = $check->fetchColumn();

        if ($status !== 'draft' && $status !== 'cancelled') {
            echo "<script>alert('Gagal! Hanya SO berstatus Draft atau Cancelled yang boleh dihapus.'); window.location='index.php?page=sales-so';</script>";
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM sales_orders WHERE id = ?");
        $stmt->execute([$id]);
        echo "<script>alert('Sales Order berhasil dihapus.'); window.location='index.php?page=sales-so';</script>";

    } catch (PDOException $e) {
        echo "<script>alert('Gagal menghapus data.'); window.location='index.php?page=sales-so';</script>";
    }
} else {
    mms_redirect('index.php?page=sales-so');
}
?>
