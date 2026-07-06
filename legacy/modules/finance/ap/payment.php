<?php
// modules/finance/ap/payment.php

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) die("Error: ID tidak valid.");
$id = (int) $_GET['id'];
if ($id <= 0) die("Error: ID tidak valid.");

// 1. AMBIL DATA TAGIHAN
$sql = "SELECT sb.*, s.name as supplier_name 
        FROM supplier_bills sb 
        JOIN suppliers s ON sb.supplier_id = s.id 
        WHERE sb.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$bill = $stmt->fetch();

if (!$bill) die("Data Tagihan tidak ditemukan.");
$remaining = $bill['grand_total'] - $bill['paid_amount'];

// Gunakan toleransi float kecil
if ($remaining <= 0.01) {
    echo "<script>alert('Tagihan ini sudah LUNAS!'); window.location='index.php?page=fin-ap';</script>";
    exit;
}

// 2. PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    }
    $date = $_POST['payment_date'] ?? '';
    $amount = floatval(preg_replace('/[^0-9]/', '', $_POST['amount'] ?? '0'));
    $method = $_POST['method'] ?? '';
    $notes = clean($_POST['notes'] ?? '');
    $allowed_methods = ['Transfer Bank', 'Cash', 'Cek / Giro'];

    if (!isset($error) && empty($date)) {
        $error = "Tanggal pembayaran wajib diisi.";
    } elseif (!isset($error) && !in_array($method, $allowed_methods, true)) {
        $error = "Metode pembayaran tidak valid.";
    } elseif (!isset($error) && $amount <= 0) {
        $error = "Jumlah pembayaran harus lebih dari 0.";
    } else {
        try {
            $tx_started = false;
            try {
                $tx_started = $pdo->beginTransaction();
            } catch (Exception $e) {
                $tx_started = false;
            }

            // Lock row untuk mencegah pembayaran ganda bersamaan.
            $stmt_lock = $pdo->prepare("SELECT grand_total, paid_amount, bill_number FROM supplier_bills WHERE id = ? FOR UPDATE");
            $stmt_lock->execute([$id]);
            $bill_latest = $stmt_lock->fetch(PDO::FETCH_ASSOC);

            if (!$bill_latest) {
                throw new Exception("Data tagihan tidak ditemukan.");
            }

            $remaining_latest = $bill_latest['grand_total'] - $bill_latest['paid_amount'];
            if ($remaining_latest <= 0.01) {
                throw new Exception("Tagihan ini sudah lunas.");
            }
            if ($amount > ($remaining_latest + 1)) { // Toleransi 1 rupiah
                throw new Exception("Jumlah pembayaran melebihi sisa tagihan terbaru.");
            }

            // A. Insert History
            $sql_ins = "INSERT INTO supplier_payments (bill_id, payment_date, amount, method, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql_ins)->execute([$id, $date, $amount, $method, $notes, $_SESSION['user_id']]);

            // B. Update Header
            $new_paid = $bill_latest['paid_amount'] + $amount;
            $new_status = ($new_paid >= ($bill_latest['grand_total'] - 1)) ? 'paid' : 'partial';
            
            $pdo->prepare("UPDATE supplier_bills SET paid_amount = ?, status = ?, updated_at = NOW() WHERE id = ?")->execute([$new_paid, $new_status, $id]);

            // C. AUTO JURNAL (Debit Hutang, Kredit Bank/Kas)
            $journal_created = false;
            if (function_exists('get_coa_id') && function_exists('create_journal')) {
                $coa_ap = get_coa_id('2-1001'); // Hutang Usaha
                $cash_or_bank_code = ($method === 'Cash') ? '1-1001' : '1-1002';
                $coa_cash_or_bank = get_coa_id($cash_or_bank_code);

                if ($coa_ap && $coa_cash_or_bank) {
                    $jurnal_items = [
                        ['coa_id' => $coa_ap, 'debit' => $amount, 'credit' => 0],
                        ['coa_id' => $coa_cash_or_bank, 'debit' => 0, 'credit' => $amount]
                    ];
                    create_journal($date, $bill_latest['bill_number'], "Pembayaran Hutang ($method)", $jurnal_items, 'payment');
                    $journal_created = true;
                }
            }

            if (function_exists('notify_workflow_event')) {
                $evt = ($new_status === 'paid') ? 'fin.ap.paid.' : 'fin.ap.partial.';
                $title_evt = ($new_status === 'paid') ? 'Tagihan Supplier Lunas' : 'Pembayaran Parsial Tagihan';
                $msg_evt = ($new_status === 'paid')
                    ? "Tagihan {$bill_latest['bill_number']} sudah lunas."
                    : "Tagihan {$bill_latest['bill_number']} menerima pembayaran parsial.";
                notify_workflow_event(
                    $evt . (int)$id,
                    $title_evt,
                    $msg_evt,
                    "index.php?page=fin-ap&action=pay&id=" . (int)$id,
                    ($new_status === 'paid') ? 'success' : 'info',
                    ['permission_slug' => 'acc_view']
                );
            }

            if ($tx_started && $pdo->inTransaction()) {
                $pdo->commit();
            }
            $msg = $journal_created
                ? 'Pembayaran berhasil disimpan & Jurnal terbentuk!'
                : 'Pembayaran berhasil disimpan. Jurnal belum terbentuk (COA/Helper belum lengkap).';
            echo "<script>alert('$msg'); window.location='index.php?page=fin-ap';</script>";
            exit;

        } catch (Exception $e) {
            if ($tx_started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Gagal: " . $e->getMessage();
        }
    }
}

// Ambil Riwayat
$stmt_hist = $pdo->prepare("SELECT * FROM supplier_payments WHERE bill_id = ? ORDER BY payment_date DESC, id DESC");
$stmt_hist->execute([$id]);
$history = $stmt_hist->fetchAll();

render_header("Input Pembayaran Hutang");
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white"><h5 class="mb-0">Informasi Tagihan</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><td width="120" class="text-muted">No. Bill</td><td class="fw-bold"><?= clean($bill['bill_number']) ?></td></tr>
                            <tr><td class="text-muted">Inv Supplier</td><td><?= clean($bill['supplier_inv_number']) ?></td></tr>
                            <tr><td class="text-muted">Supplier</td><td><?= clean($bill['supplier_name']) ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6 text-end">
                        <small class="text-muted">Total Tagihan</small>
                        <h4 class="fw-bold">Rp <?= number_format($bill['grand_total'], 0, ',', '.') ?></h4>
                        <small class="text-muted">Sudah Dibayar</small>
                        <h5 class="text-success">Rp <?= number_format($bill['paid_amount'], 0, ',', '.') ?></h5>
                        <hr>
                        <small class="text-muted">Sisa Tagihan</small>
                        <h3 class="text-danger fw-bold">Rp <?= number_format($remaining, 0, ',', '.') ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header bg-success text-white"><h6 class="mb-0 fw-bold">Form Pembayaran</h6></div>
            <div class="card-body">
                <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<form method="POST">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(mms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Tanggal Bayar</label>
                            <input type="date" name="payment_date" class="form-control" value="<?= clean($_POST['payment_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Metode Pembayaran</label>
                            <select name="method" class="form-select" required>
                                <option value="Transfer Bank" <?= (($_POST['method'] ?? '') === 'Transfer Bank') ? 'selected' : '' ?>>Transfer Bank</option>
                                <option value="Cash" <?= (($_POST['method'] ?? '') === 'Cash') ? 'selected' : '' ?>>Tunai (Cash)</option>
                                <option value="Cek / Giro" <?= (($_POST['method'] ?? '') === 'Cek / Giro') ? 'selected' : '' ?>>Cek / Giro</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Jumlah Pembayaran (Rp)</label>
                        <input type="text" name="amount" class="form-control form-control-lg fw-bold text-success" 
                               value="<?= isset($_POST['amount']) ? clean($_POST['amount']) : number_format($remaining, 0, ',', '.') ?>" required onkeyup="formatRibuan(this)">
                        <div class="form-text">Maksimal: Rp <?= number_format($remaining, 0, ',', '.') ?></div>
                    </div>
                    <div class="mb-3">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"><?= clean($_POST['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="index.php?page=fin-ap" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-success px-4 fw-bold">Simpan Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if (!empty($history)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-light"><h6 class="mb-0">Riwayat Pembayaran</h6></div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead class="table-light"><tr><th>Tanggal</th><th>Metode</th><th>Catatan</th><th class="text-end">Jumlah</th></tr></thead>
                    <tbody>
                        <?php foreach($history as $hist): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($hist['payment_date'])) ?></td>
                            <td><?= clean($hist['method']) ?></td>
                            <td><?= clean($hist['notes']) ?></td>
                            <td class="text-end fw-bold">Rp <?= number_format($hist['amount'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
function formatRibuan(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    input.value = new Intl.NumberFormat('id-ID').format(value);
}
</script>
<?php render_footer(); ?>
