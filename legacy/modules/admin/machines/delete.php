<?php
// modules/engineering/machines/delete.php
$id = isset($_GET['id']) ? $_GET['id'] : null;
$csrf = $_GET['csrf'] ?? $_POST['csrf'] ?? '';

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('eng_machine_manage')) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=admin-machines';</script>";
    exit;
}
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=admin-machines';</script>";
    exit;
}

if ($id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM machines WHERE id = ?");
        $stmt->execute([$id]);
        echo "<script>alert('Mesin berhasil dihapus.'); window.location='index.php?page=admin-machines';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Gagal menghapus. Mesin mungkin sedang digunakan dalam jadwal produksi.'); window.location='index.php?page=admin-machines';</script>";
    }
} else {
    mms_redirect('index.php?page=admin-machines');
}
?>
