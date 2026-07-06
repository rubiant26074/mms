<?php
// modules/engineering/items/delete.php

$id = isset($_GET['id']) ? $_GET['id'] : null;
$csrf = $_GET['csrf'] ?? $_POST['csrf'] ?? '';

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('eng_items')) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=eng-items';</script>";
    exit;
}
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=eng-items';</script>";
    exit;
}

if ($id) {
    // Cek apakah barang sudah dipakai di BOM atau Transaksi lain (Logic menyusul)
    // Untuk tahap ini, kita allow delete dulu
    try {
        $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
        $stmt->execute([$id]);
        echo "<script>alert('Barang berhasil dihapus.'); window.location='index.php?page=eng-items';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Gagal menghapus. Barang mungkin sudah digunakan dalam transaksi.'); window.location='index.php?page=eng-items';</script>";
    }
} else {
    mms_redirect('index.php?page=eng-items');
}
?>
