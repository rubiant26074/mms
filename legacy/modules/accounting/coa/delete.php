<?php
$id = isset($_GET['id']) ? $_GET['id'] : null;
if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('acc_coa_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=acc-coa';</script>";
    exit;
}
if ($id) {
    $csrf = $_GET['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=acc-coa';</script>";
        exit;
    }
    // Validasi: Jangan hapus jika sudah dipakai di jurnal (nanti)
    $stmt = $pdo->prepare("DELETE FROM coa WHERE id = ?");
    $stmt->execute([$id]);
    echo "<script>alert('Akun berhasil dihapus.'); window.location='index.php?page=acc-coa';</script>";
} else {
    mms_redirect('index.php?page=acc-coa');
}
?>
