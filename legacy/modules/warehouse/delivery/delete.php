<?php
$id = isset($_GET['id']) ? $_GET['id'] : null;
$csrf = $_GET['csrf'] ?? $_POST['csrf'] ?? '';

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('whse_sj_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=whse-sj';</script>";
    exit;
}
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=whse-sj';</script>";
    exit;
}

if ($id) {
    // Cek status, hanya draft yg bisa dihapus
    $stmt = $pdo->prepare("DELETE FROM delivery_notes WHERE id = ? AND status = 'draft'");
    $stmt->execute([$id]);
    if($stmt->rowCount()>0) echo "<script>alert('Berhasil dihapus.'); window.location='index.php?page=whse-sj';</script>";
    else echo "<script>alert('Gagal. Hanya draft yang bisa dihapus.'); window.location='index.php?page=whse-sj';</script>";
}
?>
