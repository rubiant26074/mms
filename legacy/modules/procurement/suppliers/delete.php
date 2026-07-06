<?php
// modules/procurement/suppliers/delete.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('purch_vendor_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=purch-vendor';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$csrf = $_GET['csrf'] ?? $_POST['csrf'] ?? '';
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=purch-vendor';</script>";
    exit;
}

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
        $stmt->execute([$id]);
        echo "<script>alert('Vendor berhasil dihapus.'); window.location='index.php?page=purch-vendor';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Gagal menghapus. Vendor sudah memiliki riwayat PO.'); window.location='index.php?page=purch-vendor';</script>";
    }
} else {
    mms_redirect('index.php?page=purch-vendor');
}
?>
