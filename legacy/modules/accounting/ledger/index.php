<?php
// modules/accounting/ledger/index.php
render_header("Buku Besar (General Ledger)");

// Filter Default
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$coa_id = isset($_GET['coa_id']) ? $_GET['coa_id'] : '';

$print_params = [
    'page' => 'acc-ledger',
    'action' => 'print',
    'start_date' => $start_date,
    'end_date' => $end_date,
    'coa_id' => $coa_id
];
$print_url = 'index.php?' . http_build_query($print_params);

// Ambil Daftar Akun untuk Dropdown
$accounts = $pdo->query("SELECT * FROM coa ORDER BY account_code ASC")->fetchAll();

$ledger = [];
$account_info = null;
$opening_balance = 0;

if ($coa_id) {
    // 1. Ambil Info Akun
    $stmt_acc = $pdo->prepare("SELECT * FROM coa WHERE id = ?");
    $stmt_acc->execute([$coa_id]);
    $account_info = $stmt_acc->fetch();

    // 2. Hitung Saldo Awal (Transaksi sebelum Start Date)
    // Rumus: Saldo Awal Master + (Total Debit - Total Kredit sebelum tanggal ini)
    // Perlu disesuaikan dengan Normal Balance (Debit/Credit)
    
    // Total Mutasi Sebelum Periode
    $sql_open = "SELECT SUM(debit) as tot_debit, SUM(credit) as tot_credit 
                 FROM journal_items ji
                 JOIN journals j ON ji.journal_id = j.id
                 WHERE ji.coa_id = ? AND j.journal_date < ?";
    $stmt_open = $pdo->prepare($sql_open);
    $stmt_open->execute([$coa_id, $start_date]);
    $prev_mut = $stmt_open->fetch();
    
    $prev_debit = $prev_mut['tot_debit'] ?? 0;
    $prev_credit = $prev_mut['tot_credit'] ?? 0;
    
    // Hitung Saldo Awal berdasarkan Tipe Akun
    if ($account_info['normal_balance'] == 'debit') {
        $opening_balance = $account_info['opening_balance'] + ($prev_debit - $prev_credit);
    } else {
        $opening_balance = $account_info['opening_balance'] + ($prev_credit - $prev_debit);
    }

    // 3. Ambil Transaksi Periode Ini
    $sql_ledger = "SELECT j.journal_date, j.journal_no, j.reference_no, j.description, 
                          ji.debit, ji.credit
                   FROM journal_items ji
                   JOIN journals j ON ji.journal_id = j.id
                   WHERE ji.coa_id = ? AND j.journal_date BETWEEN ? AND ?
                   ORDER BY j.journal_date ASC, j.id ASC";
    $stmt_ledger = $pdo->prepare($sql_ledger);
    $stmt_ledger->execute([$coa_id, $start_date, $end_date]);
    $ledger = $stmt_ledger->fetchAll();
}
?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end" id="ledger-filter-form">
            <input type="hidden" name="page" value="acc-ledger">
            
            <div class="col-md-4">
                <label class="form-label fw-bold">Pilih Akun</label>
                <select name="coa_id" class="form-select select2" required>
                    <option value="">-- Pilih Akun --</option>
                    <?php foreach($accounts as $acc): 
                        $selected = ($acc['id'] == $coa_id) ? 'selected' : '';
                    ?>
                        <option value="<?= $acc['id'] ?>" <?= $selected ?>>
                            <?= $acc['account_code'] ?> - <?= $acc['account_name'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Dari Tanggal</label>
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Tampilkan</button>
            </div>
            <div class="col-md-2">
                <?php if (!empty($coa_id)): ?>
                    <a href="<?= htmlspecialchars($print_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-outline-dark w-100">
                        <i class="bi bi-printer"></i> Print
                    </a>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary w-100" disabled title="Pilih akun dulu untuk print">
                        <i class="bi bi-printer"></i> Print
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('ledger-filter-form');
    if (!form) return;
    const coa = form.querySelector('select[name="coa_id"]');
    const start = form.querySelector('input[name="start_date"]');
    const end = form.querySelector('input[name="end_date"]');

    const submit = () => {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    };

    if (coa) coa.addEventListener('change', submit);
    if (start) start.addEventListener('change', submit);
    if (end) end.addEventListener('change', submit);
})();
</script>

<?php if ($account_info): ?>
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0 text-primary">
            <?= $account_info['account_code'] ?> - <?= $account_info['account_name'] ?>
        </h5>
        <small class="text-muted">Saldo Normal: <?= strtoupper($account_info['normal_balance']) ?></small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light text-center">
                    <tr>
                        <th>Tanggal</th>
                        <th>No. Jurnal</th>
                        <th>Keterangan</th>
                        <th>Debit</th>
                        <th>Kredit</th>
                        <th>Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Saldo Awal -->
                    <tr class="bg-light fw-bold">
                        <td colspan="5" class="text-end">SALDO AWAL (Per <?= date('d/m/Y', strtotime($start_date)) ?>)</td>
                        <td class="text-end"><?= number_format($opening_balance, 0, ',', '.') ?></td>
                    </tr>

                    <?php 
                    $running_balance = $opening_balance;
                    $total_debit = 0;
                    $total_credit = 0;

                    if (empty($ledger)): 
                    ?>
                        <tr><td colspan="6" class="text-center py-3 text-muted">Tidak ada transaksi pada periode ini.</td></tr>
                    <?php else: 
                        foreach($ledger as $row): 
                            $debit = floatval($row['debit']);
                            $credit = floatval($row['credit']);
                            
                            $total_debit += $debit;
                            $total_credit += $credit;

                            // Hitung Saldo Berjalan
                            if ($account_info['normal_balance'] == 'debit') {
                                $running_balance += ($debit - $credit);
                            } else {
                                $running_balance += ($credit - $debit);
                            }
                    ?>
                        <tr>
                            <td class="text-center"><?= date('d/m/Y', strtotime($row['journal_date'])) ?></td>
                            <td>
                                <span class="fw-bold text-primary"><?= $row['journal_no'] ?></span><br>
                                <small class="text-muted"><?= $row['reference_no'] ?></small>
                            </td>
                            <td><?= $row['description'] ?></td>
                            <td class="text-end"><?= $debit > 0 ? number_format($debit, 0, ',', '.') : '-' ?></td>
                            <td class="text-end"><?= $credit > 0 ? number_format($credit, 0, ',', '.') : '-' ?></td>
                            <td class="text-end fw-bold bg-light"><?= number_format($running_balance, 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="3" class="text-end">TOTAL MUTASI :</td>
                        <td class="text-end text-success"><?= number_format($total_debit, 0, ',', '.') ?></td>
                        <td class="text-end text-danger"><?= number_format($total_credit, 0, ',', '.') ?></td>
                        <td class="text-end bg-warning bg-opacity-25"><?= number_format($running_balance, 0, ',', '.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php render_footer(); ?>
