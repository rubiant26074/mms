<?php
// modules/warehouse/receive/delete.php
$id = isset($_GET['id']) ? $_GET['id'] : null;
$csrf = $_GET['csrf'] ?? $_POST['csrf'] ?? '';

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('whse_receive_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=whse-receive';</script>";
    exit;
}
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=whse-receive';</script>";
    exit;
}

if ($id) {
    // Cek status, hanya draft yang boleh dihapus
    $stmt = $pdo->prepare("DELETE FROM goods_receipts WHERE id = ? AND status = 'draft'");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        echo "<script>alert('Data penerimaan dihapus.'); window.location='index.php?page=whse-receive';</script>";
    } else {
        echo "<script>alert('Gagal! Hanya status Draft yang bisa dihapus.'); window.location='index.php?page=whse-receive';</script>";
    }
} else {
    mms_redirect('index.php?page=whse-receive');
}
?>
