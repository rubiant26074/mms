<?php
// modules/procurement/orders/delete.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('purch_po_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=purch-po';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$csrf = $_GET['csrf'] ?? $_POST['csrf'] ?? '';
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=purch-po';</script>";
    exit;
}

if ($id > 0) {
    try {
        $pdo->beginTransaction();
        $check = $pdo->prepare("SELECT status FROM purchase_orders WHERE id = ?");
        $check->execute([$id]);
        $status = $check->fetchColumn();

        if ($status !== 'draft' && $status !== 'cancelled') {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "<script>alert('Gagal! Hanya PO berstatus Draft/Cancelled yang boleh dihapus.'); window.location='index.php?page=purch-po';</script>";
            exit;
        }

        $stmt_po = $pdo->prepare("SELECT purchase_request_id FROM purchase_orders WHERE id = ?");
        $stmt_po->execute([$id]);
        $pr_ref = (int)($stmt_po->fetchColumn() ?: 0);

        $stmt_old_items = $pdo->prepare("SELECT pr_item_id, qty FROM purchase_order_items WHERE purchase_order_id = ?");
        $stmt_old_items->execute([$id]);
        $old_items = $stmt_old_items->fetchAll(PDO::FETCH_ASSOC);
        foreach ($old_items as $oi) {
            $pr_item_id = (int)($oi['pr_item_id'] ?? 0);
            $qty = (float)($oi['qty'] ?? 0);
            if ($pr_item_id > 0 && $qty > 0) {
                $pdo->prepare("UPDATE purchase_request_items SET qty_ordered = GREATEST(IFNULL(qty_ordered,0) - ?, 0) WHERE id = ?")
                    ->execute([$qty, $pr_item_id]);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM purchase_orders WHERE id = ?");
        $stmt->execute([$id]);

        if ($pr_ref > 0) {
            $stmt_sisa = $pdo->prepare("SELECT COUNT(*) FROM purchase_request_items WHERE purchase_request_id = ? AND (qty - IFNULL(qty_ordered,0)) > 0.001");
            $stmt_sisa->execute([$pr_ref]);
            $sisa_count = (int)$stmt_sisa->fetchColumn();

            $stmt_ordered = $pdo->prepare("SELECT COUNT(*) FROM purchase_request_items WHERE purchase_request_id = ? AND IFNULL(qty_ordered,0) > 0.001");
            $stmt_ordered->execute([$pr_ref]);
            $ordered_count = (int)$stmt_ordered->fetchColumn();

            $new_pr_status = 'approved';
            if ($ordered_count > 0 && $sisa_count > 0) {
                $new_pr_status = 'partial';
            } elseif ($ordered_count > 0 && $sisa_count === 0) {
                $new_pr_status = 'processed';
            }
            $pdo->prepare("UPDATE purchase_requests SET status = ? WHERE id = ?")->execute([$new_pr_status, $pr_ref]);
        }

        $pdo->commit();
        echo "<script>alert('PO berhasil dihapus.'); window.location='index.php?page=purch-po';</script>";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<script>alert('Gagal menghapus data.'); window.location='index.php?page=purch-po';</script>";
    }
} else {
    mms_redirect('index.php?page=purch-po');
}
?>
