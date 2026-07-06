<?php
$id = isset($_GET['id']) ? $_GET['id'] : null;
$csrf = $_GET['csrf'] ?? $_POST['csrf'] ?? '';

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('hrd_employee_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=hrd-employees';</script>";
    exit;
}
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=hrd-employees';</script>";
    exit;
}

if ($id) {
    if ($id == $_SESSION['user_id']) {
        echo "<script>alert('Anda tidak bisa menghapus akun sendiri!'); window.location='index.php?page=hrd-employees';</script>";
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo "<script>alert('Data Karyawan berhasil dihapus.'); window.location='index.php?page=hrd-employees';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Gagal menghapus. Data user ini terikat dengan transaksi (SO/PO/Log). Sebaiknya set status menjadi Resigned.'); window.location='index.php?page=hrd-employees';</script>";
    }
}
?>
