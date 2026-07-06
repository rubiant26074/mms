<?php
// modules/finance/tax/index.php
render_header("Taxation (Perpajakan)");

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('m');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');

$tax_table_exists = false;
try {
    $tax_table_exists = $pdo->query("SHOW TABLES LIKE 'tax_payments'")->rowCount() > 0;
} catch (Exception $e) {
    $tax_table_exists = false;
}

// 1. PPN KELUARAN (Output VAT)
$sql_out = "SELECT SUM(tax_amount) as total_ppn, SUM(subtotal) as dpp 
            FROM invoices 
            WHERE status != 'cancelled' 
            AND MONTH(invoice_date) = ? AND YEAR(invoice_date) = ?";
$stmt_out = $pdo->prepare($sql_out);
$stmt_out->execute([$month, $year]);
$out = $stmt_out->fetch();
$ppn_out = (float)($out['total_ppn'] ?: 0);
$dpp_out = (float)($out['dpp'] ?: 0);

// 2. PPN MASUKAN (Input VAT)
$sql_in = "SELECT SUM(tax_amount) as total_ppn, SUM(subtotal) as dpp 
           FROM supplier_bills 
           WHERE status != 'cancelled' 
           AND MONTH(bill_date) = ? AND YEAR(bill_date) = ?";
$stmt_in = $pdo->prepare($sql_in);
$stmt_in->execute([$month, $year]);
$in = $stmt_in->fetch();
$ppn_in = (float)($in['total_ppn'] ?: 0);
$dpp_in = (float)($in['dpp'] ?: 0);

$ppn_payable = $ppn_out - $ppn_in;
$tax_due = max(0, $ppn_payable);

$tax_paid = 0;
$payment_history = [];
if ($tax_table_exists) {
    $stmt_paid = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM tax_payments WHERE tax_type='ppn' AND period_month=? AND period_year=? AND status='posted'");
    $stmt_paid->execute([$month, $year]);
    $tax_paid = (float)$stmt_paid->fetchColumn();

    $stmt_hist = $pdo->prepare("SELECT tp.*, j.journal_no 
                                FROM tax_payments tp
                                LEFT JOIN journals j ON j.id = tp.journal_id
                                WHERE tp.tax_type='ppn' AND tp.period_month=? AND tp.period_year=?
                                ORDER BY tp.payment_date DESC, tp.id DESC");
    $stmt_hist->execute([$month, $year]);
    $payment_history = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
}

$tax_remaining = max(0, $tax_due - $tax_paid);
$status_tax = ($ppn_payable > 0) ? "KURANG BAYAR" : (($ppn_payable < 0) ? "LEBIH BAYAR (RESTITUSI)" : "NIHIL");
$color_tax = ($ppn_payable > 0) ? "text-danger" : "text-success";

// 3. Proses Setor Pajak
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['tax_action'] ?? '') === 'pay_ppn') {
    $csrf = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    }
    $pay_month = isset($_POST['period_month']) ? (int)$_POST['period_month'] : $month;
    $pay_year = isset($_POST['period_year']) ? (int)$_POST['period_year'] : $year;
    $payment_date = $_POST['payment_date'] ?? '';
    $method = $_POST['method'] ?? '';
    $reference_no = clean($_POST['reference_no'] ?? '');
    $notes = clean($_POST['notes'] ?? '');
    $amount = floatval(preg_replace('/[^0-9]/', '', $_POST['amount'] ?? '0'));
    $allowed_methods = ['Transfer Bank', 'Cash', 'e-Billing Pajak'];

    if (!isset($error) && !$tax_table_exists) {
        $error = "Tabel pembayaran pajak belum tersedia. Jalankan migration database/migrations/20260211_02_tax_payments.sql";
    } elseif (!isset($error) && ($pay_month < 1 || $pay_month > 12 || $pay_year < 2000 || $pay_year > 2100)) {
        $error = "Masa pajak tidak valid.";
    } elseif (!isset($error) && empty($payment_date)) {
        $error = "Tanggal setor wajib diisi.";
    } elseif (!isset($error) && !in_array($method, $allowed_methods, true)) {
        $error = "Metode pembayaran tidak valid.";
    } elseif (!isset($error) && $amount <= 0) {
        $error = "Nominal setor pajak harus lebih dari 0.";
    } else {
        // Recalculate nilai periode yang dipilih agar akurat
        $stmt_out->execute([$pay_month, $pay_year]);
        $o = $stmt_out->fetch();
        $p_out = (float)($o['total_ppn'] ?: 0);

        $stmt_in->execute([$pay_month, $pay_year]);
        $i = $stmt_in->fetch();
        $p_in = (float)($i['total_ppn'] ?: 0);

        $due = max(0, $p_out - $p_in);

        $stmt_paid_period = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM tax_payments WHERE tax_type='ppn' AND period_month=? AND period_year=? AND status='posted'");
        $stmt_paid_period->execute([$pay_month, $pay_year]);
        $already_paid = (float)$stmt_paid_period->fetchColumn();
        $remaining_period = max(0, $due - $already_paid);

        if ($remaining_period <= 0.01) {
            $error = "PPN masa ini sudah lunas.";
        } elseif ($amount > ($remaining_period + 1)) {
            $error = "Nominal melebihi sisa PPN yang harus disetor.";
        } else {
            try {
                $pdo->beginTransaction();

                $journal_id = null;
                $journal_note = '';
                if (function_exists('get_coa_id') && function_exists('create_journal')) {
                    $coa_tax_payable = get_coa_id('2-2001'); // Disarankan: Utang PPN
                    $cash_or_bank_code = ($method === 'Cash') ? '1-1001' : '1-1002';
                    $coa_cash_bank = get_coa_id($cash_or_bank_code);

                    if ($coa_tax_payable && $coa_cash_bank) {
                        $jurnal_items = [
                            ['coa_id' => $coa_tax_payable, 'debit' => $amount, 'credit' => 0],
                            ['coa_id' => $coa_cash_bank, 'debit' => 0, 'credit' => $amount]
                        ];
                        $period_ref = sprintf("PPN-%04d%02d", $pay_year, $pay_month);
                        $journal_id = create_journal(
                            $payment_date,
                            $period_ref,
                            "Setor PPN Masa " . date('F Y', mktime(0, 0, 0, $pay_month, 1, $pay_year)) . " ($method)",
                            $jurnal_items,
                            'payment'
                        );
                    } else {
                        $journal_note = " Jurnal belum dibuat (COA 2-2001 / kas-bank belum tersedia).";
                    }
                }

                $sql_pay = "INSERT INTO tax_payments (tax_type, period_month, period_year, payment_date, amount, method, reference_no, notes, journal_id, status, created_by)
                            VALUES ('ppn', ?, ?, ?, ?, ?, ?, ?, ?, 'posted', ?)";
                $pdo->prepare($sql_pay)->execute([
                    $pay_month,
                    $pay_year,
                    $payment_date,
                    $amount,
                    $method,
                    $reference_no,
                    $notes,
                    $journal_id,
                    $_SESSION['user_id'] ?? null
                ]);

                if (function_exists('notify_workflow_event')) {
                    notify_workflow_event(
                        'fin.tax.payment.' . $pay_year . str_pad((string)$pay_month, 2, '0', STR_PAD_LEFT),
                        'Pembayaran Pajak Tercatat',
                        "Setor PPN masa " . date('F Y', mktime(0, 0, 0, $pay_month, 1, $pay_year)) . " berhasil dicatat.",
                        "index.php?page=fin-tax&month={$pay_month}&year={$pay_year}",
                        'success',
                        ['permission_slug' => 'acc_view']
                    );
                }

                $pdo->commit();
                echo "<script>alert('Setor pajak berhasil disimpan!{$journal_note}'); window.location='index.php?page=fin-tax&month={$pay_month}&year={$pay_year}';</script>";
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Gagal simpan setor pajak: " . $e->getMessage();
            }
        }
    }
}

// 4. Monitoring NSFP (Nomor Seri Faktur Pajak)
$stmt_nsfp = $pdo->prepare("SELECT id, invoice_number, invoice_date, due_date, tax_invoice_number, customer_id
                            FROM invoices
                            WHERE status != 'cancelled'
                            AND MONTH(invoice_date) = ? AND YEAR(invoice_date) = ?
                            ORDER BY invoice_date DESC, id DESC");
$stmt_nsfp->execute([$month, $year]);
$inv_rows = $stmt_nsfp->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-receipt"></i> Rekapitulasi PPN</h3>
        <p class="text-muted">Masa Pajak: <?= date('F Y', mktime(0,0,0,$month, 1, $year)) ?></p>
    </div>
    <div class="col-md-6 text-end">
        <form method="GET" class="d-flex justify-content-end gap-2">
            <input type="hidden" name="page" value="fin-tax">
            <select name="month" class="form-select w-auto">
                <?php for($m=1; $m<=12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m==$month?'selected':'' ?>><?= date('F', mktime(0,0,0,$m, 1)) ?></option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-select w-auto">
                <?php for($y=date('Y')-1; $y<=date('Y')+1; $y++): ?>
                    <option value="<?= $y ?>" <?= $y==$year?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-primary">Lihat</button>
            <button type="submit" name="action" value="print" formtarget="_blank" class="btn btn-outline-dark">
                <i class="bi bi-printer"></i> Print
            </button>
        </form>
    </div>
</div>

<?php if(isset($error)): ?>
    <div class="alert alert-danger"><?= clean($error) ?></div>
<?php endif; ?>

<?php if (!$tax_table_exists): ?>
    <div class="alert alert-warning">
        Tabel <code>tax_payments</code> belum tersedia. Jalankan migration:
        <code>database/migrations/20260211_02_tax_payments.sql</code>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-start border-4 border-info">
            <div class="card-header bg-white fw-bold text-info">PPN KELUARAN (Penjualan)</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>DPP</span>
                    <strong>Rp <?= number_format($dpp_out, 0, ',', '.') ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Total PPN Dipungut</span>
                    <h4 class="text-info fw-bold">Rp <?= number_format($ppn_out, 0, ',', '.') ?></h4>
                </div>
                <hr>
                <small class="text-muted">Sumber: Invoice Customer</small>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-start border-4 border-success">
            <div class="card-header bg-white fw-bold text-success">PPN MASUKAN (Pembelian)</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>DPP</span>
                    <strong>Rp <?= number_format($dpp_in, 0, ',', '.') ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Total PPN Dibayar</span>
                    <h4 class="text-success fw-bold">Rp <?= number_format($ppn_in, 0, ',', '.') ?></h4>
                </div>
                <hr>
                <small class="text-muted">Sumber: Tagihan Supplier</small>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <small class="text-muted text-uppercase">Status PPN</small>
                <h5 class="fw-bold mt-2 <?= $color_tax ?>"><?= $status_tax ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <small class="text-muted text-uppercase">Kewajiban Setor PPN</small>
                <h4 class="fw-bold mt-2 text-danger">Rp <?= number_format($tax_due, 0, ',', '.') ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <small class="text-muted text-uppercase">Sudah Disetor / Sisa</small>
                <div class="mt-2">
                    <div class="fw-bold text-success">Rp <?= number_format($tax_paid, 0, ',', '.') ?></div>
                    <div class="fw-bold text-danger">Sisa: Rp <?= number_format($tax_remaining, 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-light fw-bold">Input Pembayaran Pajak (Setor PPN)</div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(mms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="tax_action" value="pay_ppn">
            <input type="hidden" name="period_month" value="<?= $month ?>">
            <input type="hidden" name="period_year" value="<?= $year ?>">

            <div class="col-md-3">
                <label class="form-label">Tanggal Setor</label>
                <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Metode</label>
                <select name="method" class="form-select" required>
                    <option value="Transfer Bank">Transfer Bank</option>
                    <option value="Cash">Cash</option>
                    <option value="e-Billing Pajak">e-Billing Pajak</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Nominal Setor (Rp)</label>
                <input type="text" name="amount" class="form-control" value="<?= number_format($tax_remaining, 0, ',', '.') ?>" onkeyup="formatRibuan(this)" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">No. Referensi</label>
                <input type="text" name="reference_no" class="form-control" placeholder="NTPN / e-Billing">
            </div>
            <div class="col-12">
                <label class="form-label">Catatan</label>
                <textarea name="notes" rows="2" class="form-control" placeholder="Catatan pembayaran pajak"></textarea>
            </div>
            <div class="col-12 d-flex justify-content-between">
                <small class="text-muted align-self-center">Sisa setor masa ini: Rp <?= number_format($tax_remaining, 0, ',', '.') ?></small>
                <button type="submit" class="btn btn-danger" <?= ($tax_remaining <= 0 || !$tax_table_exists) ? 'disabled' : '' ?>>Simpan Setor Pajak</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-light fw-bold">Riwayat Pembayaran Pajak Masa <?= strtoupper(date('F Y', mktime(0,0,0,$month, 1, $year))) ?></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Metode</th>
                        <th>Referensi</th>
                        <th>No. Jurnal</th>
                        <th class="text-end">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($payment_history)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada pembayaran pajak untuk masa ini.</td></tr>
                    <?php else: foreach($payment_history as $p): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($p['payment_date'])) ?></td>
                            <td><?= clean($p['method']) ?></td>
                            <td><?= !empty($p['reference_no']) ? clean($p['reference_no']) : '-' ?></td>
                            <td><?= !empty($p['journal_no']) ? clean($p['journal_no']) : '-' ?></td>
                            <td class="text-end fw-bold">Rp <?= number_format($p['amount'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light fw-bold">Monitoring Nomor Faktur Pajak (Invoice)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No. Invoice</th>
                        <th>Tanggal</th>
                        <th>Jatuh Tempo</th>
                        <th>No. Seri Faktur Pajak</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($inv_rows)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada invoice pada periode ini.</td></tr>
                    <?php else: foreach($inv_rows as $inv): ?>
                        <?php $has_nsfp = !empty($inv['tax_invoice_number']); ?>
                        <tr>
                            <td><strong><?= clean($inv['invoice_number']) ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($inv['due_date'])) ?></td>
                            <td><?= $has_nsfp ? clean($inv['tax_invoice_number']) : '<span class="text-danger">Belum diisi</span>' ?></td>
                            <td class="text-center">
                                <?php if($has_nsfp): ?>
                                    <span class="badge bg-success">Lengkap</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Belum</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="index.php?page=fin-ar&action=edit&id=<?= (int)$inv['id'] ?>" class="btn btn-sm btn-outline-primary">Edit Invoice</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function formatRibuan(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    input.value = new Intl.NumberFormat('id-ID').format(value);
}
</script>

<?php render_footer(); ?>
