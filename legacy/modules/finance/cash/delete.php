<?php
// modules/finance/cash/delete.php
require_once __DIR__ . '/common.php';

$theme_q = !empty($_GET['theme']) ? '&theme=' . urlencode((string)$_GET['theme']) : '';
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

if ($id === null) {
    echo "<script>window.location='index.php?page=fin-cash{$theme_q}';</script>";
    exit;
}

$csrf = $_GET['csrf'] ?? '';
if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
    echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=fin-cash{$theme_q}';</script>";
    exit;
}

try {
    fin_cash_ensure_schema($pdo);
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM finance_cash_expenses WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $trx = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trx) {
        throw new Exception("Data transaksi tidak ditemukan.");
    }
    if ((string)$trx['status'] !== 'draft') {
        throw new Exception("Hanya data draft yang bisa dihapus. Gunakan cancel untuk data posted.");
    }

    $pdo->prepare("DELETE FROM finance_cash_expenses WHERE id = ?")->execute([$id]);
    $pdo->commit();

    echo "<script>alert('Data transaksi berhasil dihapus.'); window.location='index.php?page=fin-cash{$theme_q}';</script>";
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<script>alert('Gagal hapus: " . addslashes($e->getMessage()) . "'); window.location='index.php?page=fin-cash{$theme_q}';</script>";
    exit;
}
?>
