<?php
// modules/ppic/purchase_requests/delete.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('ppic_pr_delete')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=ppic-pr';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$csrf = $_GET['csrf'] ?? $_POST['csrf'] ?? '';
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=ppic-pr';</script>";
    exit;
}

if ($id > 0) {
    try {
        $pdo->beginTransaction();
        $check = $pdo->prepare("SELECT status FROM purchase_requests WHERE id = ?");
        $check->execute([$id]);
        $status = $check->fetchColumn();

        if ($status !== 'draft' && $status !== 'rejected') {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "<script>alert('Gagal! Hanya PR berstatus Draft/Rejected yang boleh dihapus.'); window.location='index.php?page=ppic-pr';</script>";
            exit;
        }

        $pdo->prepare("DELETE FROM purchase_request_items WHERE purchase_request_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM purchase_requests WHERE id = ?")->execute([$id]);
        $pdo->commit();
        echo "<script>alert('PR berhasil dihapus.'); window.location='index.php?page=ppic-pr';</script>";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<script>alert('Gagal menghapus data.'); window.location='index.php?page=ppic-pr';</script>";
    }
} else {
    mms_redirect('index.php?page=ppic-pr');
}
?>
