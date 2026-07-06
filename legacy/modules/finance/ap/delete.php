<?php
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
if ($id === null) {
    mms_redirect('index.php?page=fin-ap');
}
$csrf = $_GET['csrf'] ?? '';
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=fin-ap';</script>";
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM supplier_bills WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        throw new Exception("Tagihan tidak ditemukan.");
    }
    if ($bill['status'] !== 'draft') {
        throw new Exception("Hanya tagihan status draft yang bisa dihapus.");
    }

    $stmt_pay = $pdo->prepare("SELECT COUNT(*) FROM supplier_payments WHERE bill_id = ?");
    $stmt_pay->execute([$id]);
    if ((int)$stmt_pay->fetchColumn() > 0) {
        throw new Exception("Tagihan sudah memiliki histori pembayaran.");
    }

    $pdo->prepare("DELETE FROM supplier_bill_items WHERE bill_id = ?")->execute([$id]);
    delete_journal_by_reference($bill['bill_number'], 'purchase');
    $pdo->prepare("DELETE FROM supplier_bills WHERE id = ?")->execute([$id]);

    $pdo->commit();
    echo "<script>alert('Tagihan dihapus.'); window.location='index.php?page=fin-ap';</script>";
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<script>alert('Gagal hapus: " . addslashes($e->getMessage()) . "'); window.location='index.php?page=fin-ap';</script>";
    exit;
}
?>
