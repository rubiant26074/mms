<?php
// modules/engineering/boms/delete.php

$id = isset($_GET['id']) ? $_GET['id'] : null;
$csrf = $_GET['csrf'] ?? $_POST['csrf'] ?? '';

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('eng_bom')) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=eng-bom';</script>";
    exit;
}
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=eng-bom';</script>";
    exit;
}

if ($id) {
    try {
        // Cek status, jika locked tidak boleh dihapus (opsional)
        $check = $pdo->prepare("SELECT status FROM boms WHERE id = ?");
        $check->execute([$id]);
        $status = $check->fetchColumn();

        if ($status === 'locked') {
            echo "<script>alert('Gagal! BOM berstatus LOCKED tidak boleh dihapus karena digunakan history produksi.'); window.location='index.php?page=eng-bom';</script>";
            exit;
        }

        // Hapus Header (Detail otomatis hilang krn Cascade)
        $stmt = $pdo->prepare("DELETE FROM boms WHERE id = ?");
        $stmt->execute([$id]);
        echo "<script>alert('BOM berhasil dihapus.'); window.location='index.php?page=eng-bom';</script>";

    } catch (PDOException $e) {
        echo "<script>alert('Gagal menghapus data.'); window.location='index.php?page=eng-bom';</script>";
    }
} else {
    mms_redirect('index.php?page=eng-bom');
}
?>
