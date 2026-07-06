<?php
// modules/accounting/reports/index.php
render_header("Laporan Keuangan");

// Update Default: Dari Awal Tahun ini (Y-01-01)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-01-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['type']) ? $_GET['type'] : 'pl'; 

// ... (Bagian Logic Query tetap sama) ...
$data = [];
$total_revenue = 0;
$total_expense = 0;
$total_asset = 0;
$total_liability = 0;
$total_equity = 0;
$net_income = 0;

if ($report_type == 'pl') {
    // Laba Rugi: Revenue & Expense
    $sql = "SELECT c.account_code, c.account_name, c.account_type, 
                   COALESCE(m.sum_debit, 0) as sum_debit,
                   COALESCE(m.sum_credit, 0) as sum_credit,
                   c.opening_balance,
                   c.normal_balance
            FROM coa c
            LEFT JOIN (
                SELECT ji.coa_id,
                       SUM(ji.debit) as sum_debit,
                       SUM(ji.credit) as sum_credit
                FROM journal_items ji
                JOIN journals j ON ji.journal_id = j.id
                WHERE j.journal_date BETWEEN ? AND ?
                GROUP BY ji.coa_id
            ) m ON m.coa_id = c.id
            WHERE c.account_type IN ('revenue', 'expense')
            ORDER BY c.account_code ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $rows = $stmt->fetchAll();

    foreach($rows as $r) {
        $sum_debit = (float)($r['sum_debit'] ?? 0);
        $sum_credit = (float)($r['sum_credit'] ?? 0);
        $opening = (float)($r['opening_balance'] ?? 0);
        $normal = $r['normal_balance'] ?? 'debit';
        $movement = ($normal === 'credit') ? ($sum_credit - $sum_debit) : ($sum_debit - $sum_credit);
        $balance = $opening + $movement;
        if ($r['account_type'] == 'revenue') {
            $r['balance_rev'] = $balance;
            $data['revenue'][] = $r;
            $total_revenue += $r['balance_rev'];
        } else {
            $r['balance_exp'] = $balance;
            $data['expense'][] = $r;
            $total_expense += $r['balance_exp'];
        }
    }
    $net_income = $total_revenue - $total_expense;

} elseif ($report_type == 'bs') {
    // Neraca: Asset, Liability, Equity
    $sql = "SELECT c.account_code, c.account_name, c.account_type, 
                   COALESCE(m.sum_debit, 0) as sum_debit,
                   COALESCE(m.sum_credit, 0) as sum_credit,
                   c.opening_balance,
                   c.normal_balance
            FROM coa c
            LEFT JOIN (
                SELECT ji.coa_id,
                       SUM(ji.debit) as sum_debit,
                       SUM(ji.credit) as sum_credit
                FROM journal_items ji
                JOIN journals j ON ji.journal_id = j.id
                WHERE j.journal_date <= ?
                GROUP BY ji.coa_id
            ) m ON m.coa_id = c.id
            WHERE c.account_type IN ('asset', 'liability', 'equity')
            ORDER BY c.account_code ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$end_date]);
    $rows = $stmt->fetchAll();

    foreach($rows as $r) {
        $sum_debit = (float)($r['sum_debit'] ?? 0);
        $sum_credit = (float)($r['sum_credit'] ?? 0);
        $opening = (float)($r['opening_balance'] ?? 0);
        $normal = $r['normal_balance'] ?? 'debit';
        $movement = ($normal === 'credit') ? ($sum_credit - $sum_debit) : ($sum_debit - $sum_credit);
        $balance = $opening + $movement;
        if ($r['account_type'] == 'asset') {
            $r['balance_asset'] = $balance;
            $data['asset'][] = $r;
            $total_asset += $r['balance_asset'];
        } elseif ($r['account_type'] == 'liability') {
            $r['balance_passiva'] = $balance;
            $data['liability'][] = $r;
            $total_liability += $r['balance_passiva'];
        } else {
            $r['balance_passiva'] = $balance;
            $data['equity'][] = $r;
            $total_equity += $r['balance_passiva'];
        }
    }

    // Hitung Laba Ditahan
    $sql_re = "SELECT c.normal_balance, c.opening_balance,
                      COALESCE(m.sum_debit, 0) as sum_debit,
                      COALESCE(m.sum_credit, 0) as sum_credit
               FROM coa c
               LEFT JOIN (
                   SELECT ji.coa_id,
                          SUM(ji.debit) as sum_debit,
                          SUM(ji.credit) as sum_credit
                   FROM journal_items ji
                   JOIN journals j ON ji.journal_id = j.id
                   WHERE j.journal_date <= ?
                   GROUP BY ji.coa_id
               ) m ON m.coa_id = c.id
               WHERE c.account_type IN ('revenue', 'expense')";
    $stmt_re = $pdo->prepare($sql_re);
    $stmt_re->execute([$end_date]);
    $retained_earnings = 0;
    foreach ($stmt_re->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sum_debit = (float)($row['sum_debit'] ?? 0);
        $sum_credit = (float)($row['sum_credit'] ?? 0);
        $opening = (float)($row['opening_balance'] ?? 0);
        $normal = $row['normal_balance'] ?? 'debit';
        $movement = ($normal === 'credit') ? ($sum_credit - $sum_debit) : ($sum_debit - $sum_credit);
        $retained_earnings += ($opening + $movement);
    }
    
    $data['equity'][] = [
        'account_code' => '3-9999', 
        'account_name' => 'Laba Tahun Berjalan', 
        'balance_passiva' => $retained_earnings
    ];
    $total_equity += $retained_earnings;
}
?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end" id="report-filter-form">
            <input type="hidden" name="page" value="acc-report">
            
            <div class="col-md-3">
                <label class="form-label fw-bold">Jenis Laporan</label>
                <select name="type" class="form-select bg-light fw-bold text-primary" onchange="this.form.submit()">
                    <option value="pl" <?= $report_type=='pl'?'selected':'' ?>>Laba Rugi (Profit & Loss)</option>
                    <option value="bs" <?= $report_type=='bs'?'selected':'' ?>>Neraca (Balance Sheet)</option>
                </select>
            </div>
            
            <?php if($report_type == 'pl'): ?>
                <div class="col-md-3">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                </div>
            <?php endif; ?>
            
            <div class="col-md-3">
                <label class="form-label"><?= $report_type=='pl' ? 'Sampai Tanggal' : 'Per Tanggal (As of)' ?></label>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Tampilkan</button>
            </div>
            <div class="col-md-1">
                <a href="modules/accounting/reports/print.php?type=<?= urlencode($report_type) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" target="_blank" class="btn btn-outline-dark w-100"><i class="bi bi-printer"></i></a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('report-filter-form');
    if (!form) return;
    const type = form.querySelector('select[name="type"]');
    const start = form.querySelector('input[name="start_date"]');
    const end = form.querySelector('input[name="end_date"]');

    const submit = () => {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    };

    if (type) type.addEventListener('change', submit);
    if (start) start.addEventListener('change', submit);
    if (end) end.addEventListener('change', submit);
})();
</script>

<?php if ($report_type == 'pl'): ?>
<!-- TAMPILAN LABA RUGI -->
<div class="row justify-content-center">
    <div class="col-md-9">
        <div class="card shadow border-top border-4 border-primary">
            <div class="card-header bg-white text-center py-4">
                <h4 class="mb-1 fw-bold">LAPORAN LABA RUGI</h4>
                <p class="text-muted mb-0">Periode: <?= date('d M Y', strtotime($start_date)) ?> s/d <?= date('d M Y', strtotime($end_date)) ?></p>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <!-- PENDAPATAN -->
                    <tr class="table-success"><td colspan="2" class="fw-bold ps-3">PENDAPATAN (REVENUE)</td></tr>
                    <?php if(!empty($data['revenue'])): foreach($data['revenue'] as $r): ?>
                        <tr>
                            <td class="ps-4"><?= $r['account_code'] ?> - <?= $r['account_name'] ?></td>
                            <td class="text-end pe-4"><?= number_format($r['balance_rev'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="2" class="ps-4 text-muted fst-italic py-3">
                            <i class="bi bi-info-circle"></i> Belum ada pendapatan. Pastikan Invoice sudah di-<strong>POSTING</strong>.
                        </td></tr>
                    <?php endif; ?>
                    <tr class="fw-bold bg-light">
                        <td class="text-end pe-3">Total Pendapatan</td>
                        <td class="text-end pe-4 text-success"><?= number_format($total_revenue, 0, ',', '.') ?></td>
                    </tr>

                    <!-- BEBAN -->
                    <tr class="table-danger border-top"><td colspan="2" class="fw-bold ps-3">BEBAN (EXPENSES)</td></tr>
                    <?php if(!empty($data['expense'])): foreach($data['expense'] as $r): ?>
                        <tr>
                            <td class="ps-4"><?= $r['account_code'] ?> - <?= $r['account_name'] ?></td>
                            <td class="text-end pe-4"><?= number_format($r['balance_exp'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="2" class="ps-4 text-muted fst-italic">Belum ada beban tercatat.</td></tr>
                    <?php endif; ?>
                    <tr class="fw-bold bg-light">
                        <td class="text-end pe-3">Total Beban</td>
                        <td class="text-end pe-4 text-danger"><?= number_format($total_expense, 0, ',', '.') ?></td>
                    </tr>

                    <!-- HASIL -->
                    <tr class="table-primary border-top border-3 border-dark">
                        <td class="fw-bold fs-5 ps-3">LABA / (RUGI) BERSIH</td>
                        <td class="text-end fw-bold fs-5 pe-4"><?= number_format($net_income, 0, ',', '.') ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php elseif ($report_type == 'bs'): ?>
<!-- TAMPILAN NERACA -->
<div class="row">
    <!-- AKTIVA -->
    <div class="col-md-6 mb-3">
        <div class="card shadow h-100 border-top border-4 border-success">
            <div class="card-header bg-white text-center py-3">
                <h5 class="mb-0 fw-bold text-success">AKTIVA (ASSETS)</h5>
                <small class="text-muted">Per Tanggal: <?= date('d M Y', strtotime($end_date)) ?></small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <?php if(!empty($data['asset'])): foreach($data['asset'] as $r): ?>
                        <tr>
                            <td class="ps-3"><?= $r['account_code'] ?> - <?= $r['account_name'] ?></td>
                            <td class="text-end pe-3"><?= number_format($r['balance_asset'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </table>
            </div>
            <div class="card-footer bg-light fw-bold d-flex justify-content-between px-3">
                <span>TOTAL ASSET</span>
                <span><?= number_format($total_asset, 0, ',', '.') ?></span>
            </div>
        </div>
    </div>

    <!-- PASIVA -->
    <div class="col-md-6 mb-3">
        <div class="card shadow h-100 border-top border-4 border-danger">
            <div class="card-header bg-white text-center py-3">
                <h5 class="mb-0 fw-bold text-danger">PASIVA (LIABILITY + EQUITY)</h5>
                <small class="text-muted">Per Tanggal: <?= date('d M Y', strtotime($end_date)) ?></small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <!-- KEWAJIBAN -->
                    <tr class="table-warning"><td colspan="2" class="fw-bold ps-3">KEWAJIBAN (LIABILITY)</td></tr>
                    <?php if(!empty($data['liability'])): foreach($data['liability'] as $r): ?>
                        <tr>
                            <td class="ps-4"><?= $r['account_code'] ?> - <?= $r['account_name'] ?></td>
                            <td class="text-end pe-3"><?= number_format($r['balance_passiva'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <tr class="fw-bold bg-light"><td class="text-end pe-3">Total Kewajiban</td><td class="text-end pe-3"><?= number_format($total_liability, 0, ',', '.') ?></td></tr>

                    <!-- MODAL -->
                    <tr class="table-info border-top"><td colspan="2" class="fw-bold ps-3">MODAL (EQUITY)</td></tr>
                    <?php if(!empty($data['equity'])): foreach($data['equity'] as $r): ?>
                        <tr>
                            <td class="ps-4"><?= $r['account_code'] ?> - <?= $r['account_name'] ?></td>
                            <td class="text-end pe-3"><?= number_format($r['balance_passiva'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <tr class="fw-bold bg-light"><td class="text-end pe-3">Total Modal</td><td class="text-end pe-3"><?= number_format($total_equity, 0, ',', '.') ?></td></tr>
                </table>
            </div>
            <div class="card-footer bg-light fw-bold d-flex justify-content-between px-3 border-top border-3 border-dark">
                <span>TOTAL PASIVA</span>
                <span><?= number_format($total_liability + $total_equity, 0, ',', '.') ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
@media print {
    form, .btn, .navbar, #sidebar { display: none !important; }
    .card { border: none !important; shadow: none !important; }
    .card-header { background-color: white !important; color: black !important; border-bottom: 2px solid black !important; }
    body { background-color: white !important; font-size: 11pt; }
    .table td, .table th { border: 1px solid #ddd !important; }
}
</style>

<?php render_footer(); ?>
