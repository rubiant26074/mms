<?php
$id = isset($_GET['id']) ? $_GET['id'] : null;
if($id) {
    $csrf = $_GET['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=acc-assets';</script>";
        exit;
    }
    $pdo->prepare("DELETE FROM fixed_assets WHERE id=?")->execute([$id]);
    echo "<script>window.location='index.php?page=acc-assets';</script>";
}
?>
