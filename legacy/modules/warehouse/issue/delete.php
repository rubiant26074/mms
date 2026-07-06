<?php
// modules/warehouse/issue/delete.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('whse_stock')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=whse-issue';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$csrf = $_GET['csrf'] ?? $_POST['csrf'] ?? '';
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=whse-issue';</script>";
    exit;
}

if ($id > 0) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT status FROM material_issues WHERE id = ?");
        $stmt->execute([$id]);
        $status = $stmt->fetchColumn();

        if ($status !== 'request') {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "<script>alert('Hanya ITR berstatus request yang bisa dihapus.'); window.location='index.php?page=whse-issue';</script>";
            exit;
        }

        // Hapus detail lalu header. Untuk status request belum ada pengurangan stok.
        $pdo->prepare("DELETE FROM material_issue_items WHERE material_issue_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM material_issues WHERE id = ?")->execute([$id]);

        $pdo->commit();
        echo "<script>alert('ITR request berhasil dihapus.'); window.location='index.php?page=whse-issue';</script>";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<script>alert('Gagal menghapus ITR.'); window.location='index.php?page=whse-issue';</script>";
    }
} else {
    mms_redirect('index.php?page=whse-issue');
}
?>
