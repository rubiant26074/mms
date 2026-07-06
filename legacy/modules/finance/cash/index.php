<?php
// modules/finance/cash/index.php
require_once __DIR__ . '/common.php';

$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$theme_q = !empty($_GET['theme']) ? '&theme=' . urlencode((string)$_GET['theme']) : '';
$type_label = static function (string $type): string {
    return $type === 'income' ? 'Pemasukan' : 'Pengeluaran';
};

try {
    fin_cash_ensure_schema($pdo);
} catch (Exception $e) {
    render_header("Cash / Chasier");
    echo "<div class='alert alert-danger m-3'>Gagal menyiapkan tabel Cash/Chasier.</div>";
    render_footer();
    exit;
}

if (isset($_GET['action'], $_GET['id']) && is_numeric($_GET['id'])) {
    $action = trim((string)$_GET['action']);
    $id = (int)$_GET['id'];

    if (in_array($action, ['post', 'unpost', 'cancel'], true)) {
        if (!has_permission('fin_ap_manage')) {
            echo "<script>alert('Akses ditolak.'); window.location='index.php?page=fin-cash{$theme_q}';</script>";
            exit;
        }
        $csrf_req = $_GET['csrf'] ?? '';
        if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
            echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=fin-cash{$theme_q}';</script>";
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM finance_cash_expenses WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $trx = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$trx) {
                throw new Exception("Data transaksi tidak ditemukan.");
            }

            $trx_type = strtolower(trim((string)($trx['transaction_type'] ?? 'expense')));
            if (!in_array($trx_type, ['expense', 'income'], true)) {
                $trx_type = 'expense';
            }

            if ($action === 'post') {
                if ((string)$trx['status'] !== 'draft') {
                    throw new Exception("Hanya data status draft yang bisa diposting.");
                }

                $amount = (float)$trx['amount'];
                if ($amount <= 0) {
                    throw new Exception("Nominal transaksi tidak valid.");
                }

                $counter_coa_id = (int)($trx['coa_id'] ?? 0);
                $cash_coa_id = (int)($trx['cash_coa_id'] ?? 0);

                $counter_allowed = ($trx_type === 'income') ? ['revenue'] : ['expense'];
                if (!fin_cash_is_coa_type_valid($pdo, $counter_coa_id, $counter_allowed)) {
                    $counter_coa_id = ($trx_type === 'income') ? (int)fin_cash_revenue_coa_id($pdo) : (int)fin_cash_expense_coa_id($pdo);
                }
                if (!fin_cash_is_coa_type_valid($pdo, $cash_coa_id, ['asset'])) {
                    $cash_coa_id = (int)fin_cash_default_cash_coa_id($pdo, (string)$trx['payment_method']);
                }

                if ($counter_coa_id <= 0 || $cash_coa_id <= 0) {
                    throw new Exception("Akun COA transaksi belum lengkap. Edit draft lalu pilih akun lawan dan akun kas/bank.");
                }

                if ($trx_type === 'income') {
                    create_journal(
                        (string)$trx['expense_date'],
                        (string)$trx['expense_number'],
                        "Pemasukan Kas/Kasir: " . (string)$trx['category'],
                        [
                            ['coa_id' => $cash_coa_id, 'debit' => $amount, 'credit' => 0],
                            ['coa_id' => $counter_coa_id, 'debit' => 0, 'credit' => $amount],
                        ],
                        'cash_income'
                    );
                } else {
                    create_journal(
                        (string)$trx['expense_date'],
                        (string)$trx['expense_number'],
                        "Pengeluaran Kas/Kasir: " . (string)$trx['category'],
                        [
                            ['coa_id' => $counter_coa_id, 'debit' => $amount, 'credit' => 0],
                            ['coa_id' => $cash_coa_id, 'debit' => 0, 'credit' => $amount],
                        ],
                        'cash_expense'
                    );
                }

                $pdo->prepare("UPDATE finance_cash_expenses SET status='posted', updated_at=NOW() WHERE id=?")->execute([$id]);
                $pdo->commit();
                echo "<script>alert('Transaksi {$type_label($trx_type)} berhasil diposting.'); window.location='index.php?page=fin-cash{$theme_q}';</script>";
                exit;
            }

            if ($action === 'unpost') {
                if ((string)$trx['status'] !== 'posted') {
                    throw new Exception("Hanya data posted yang bisa di-unpost.");
                }
                if (function_exists('delete_journal_by_reference')) {
                    $journal_type = ($trx_type === 'income') ? 'cash_income' : 'cash_expense';
                    delete_journal_by_reference((string)$trx['expense_number'], $journal_type);
                }
                $pdo->prepare("UPDATE finance_cash_expenses SET status='draft', updated_at=NOW() WHERE id=?")->execute([$id]);
                $pdo->commit();
                echo "<script>alert('Posting transaksi berhasil dibatalkan.'); window.location='index.php?page=fin-cash{$theme_q}';</script>";
                exit;
            }

            if ($action === 'cancel') {
                if ((string)$trx['status'] === 'posted' && function_exists('delete_journal_by_reference')) {
                    $journal_type = ($trx_type === 'income') ? 'cash_income' : 'cash_expense';
                    delete_journal_by_reference((string)$trx['expense_number'], $journal_type);
                }
                $pdo->prepare("UPDATE finance_cash_expenses SET status='cancelled', updated_at=NOW() WHERE id=?")->execute([$id]);
                $pdo->commit();
                echo "<script>alert('Data transaksi dibatalkan.'); window.location='index.php?page=fin-cash{$theme_q}';</script>";
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "<script>alert('Gagal proses: " . addslashes($e->getMessage()) . "'); window.location='index.php?page=fin-cash{$theme_q}';</script>";
            exit;
        }
    }
}

render_header("Cash / Chasier");

$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$filter_type = isset($_GET['trx_type']) ? clean($_GET['trx_type']) : '';
if (!in_array($filter_type, ['expense', 'income', ''], true)) $filter_type = '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? clean($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? clean($_GET['date_to']) : '';
$rekap_from = isset($_GET['rekap_from']) ? clean($_GET['rekap_from']) : date('Y-m-01');
$rekap_to = isset($_GET['rekap_to']) ? clean($_GET['rekap_to']) : date('Y-m-t');
$rekap_cash_coa = isset($_GET['rekap_cash_coa']) ? (int)$_GET['rekap_cash_coa'] : 0;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rekap_from)) $rekap_from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rekap_to)) $rekap_to = date('Y-m-t');
if ($rekap_to < $rekap_from) {
    $tmp = $rekap_to;
    $rekap_to = $rekap_from;
    $rekap_from = $tmp;
}

$sum_income_month = 0;
$sum_expense_month = 0;
$sum_draft = 0;
try {
    $stmt_income = $pdo->query("SELECT SUM(amount) FROM finance_cash_expenses WHERE status='posted' AND transaction_type='income' AND DATE_FORMAT(expense_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
    $sum_income_month = (float)($stmt_income->fetchColumn() ?: 0);
    $stmt_expense = $pdo->query("SELECT SUM(amount) FROM finance_cash_expenses WHERE status='posted' AND transaction_type='expense' AND DATE_FORMAT(expense_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
    $sum_expense_month = (float)($stmt_expense->fetchColumn() ?: 0);
    $stmt_draft = $pdo->query("SELECT SUM(amount) FROM finance_cash_expenses WHERE status='draft'");
    $sum_draft = (float)($stmt_draft->fetchColumn() ?: 0);
} catch (Exception $e) {
    $sum_income_month = 0;
    $sum_expense_month = 0;
    $sum_draft = 0;
}
$sum_balance_month = $sum_income_month - $sum_expense_month;

$cash_coa_options = [];
try {
    $cash_coa_options = $pdo->query("SELECT id, account_code, account_name
                                     FROM coa
                                     WHERE account_type = 'asset' AND (is_active = 1 OR is_active IS NULL)
                                     ORDER BY account_code ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $cash_coa_options = [];
}
$cash_coa_ids = array_map(static fn($r) => (int)$r['id'], $cash_coa_options);
if ($rekap_cash_coa > 0 && !in_array($rekap_cash_coa, $cash_coa_ids, true)) {
    $rekap_cash_coa = 0;
}

$rekap_opening = 0;
$rekap_income = 0;
$rekap_expense = 0;
$rekap_closing = 0;
$rekap_rows = [];
try {
    $where_open = "WHERE status='posted' AND expense_date < ?";
    $where_period = "WHERE status='posted' AND expense_date >= ? AND expense_date <= ?";
    $params_open = [$rekap_from];
    $params_period = [$rekap_from, $rekap_to];

    if ($rekap_cash_coa > 0) {
        $where_open .= " AND cash_coa_id = ?";
        $where_period .= " AND cash_coa_id = ?";
        $params_open[] = $rekap_cash_coa;
        $params_period[] = $rekap_cash_coa;
    }

    $sql_open = "SELECT COALESCE(SUM(CASE WHEN transaction_type='income' THEN amount ELSE -amount END), 0)
                 FROM finance_cash_expenses $where_open";
    $stmt_open = $pdo->prepare($sql_open);
    $stmt_open->execute($params_open);
    $rekap_opening = (float)$stmt_open->fetchColumn();

    $sql_period = "SELECT
                    COALESCE(SUM(CASE WHEN transaction_type='income' THEN amount ELSE 0 END), 0) AS sum_income,
                    COALESCE(SUM(CASE WHEN transaction_type='expense' THEN amount ELSE 0 END), 0) AS sum_expense
                   FROM finance_cash_expenses $where_period";
    $stmt_period = $pdo->prepare($sql_period);
    $stmt_period->execute($params_period);
    $row_period = $stmt_period->fetch(PDO::FETCH_ASSOC) ?: ['sum_income' => 0, 'sum_expense' => 0];
    $rekap_income = (float)$row_period['sum_income'];
    $rekap_expense = (float)$row_period['sum_expense'];
    $rekap_closing = $rekap_opening + $rekap_income - $rekap_expense;

    $sql_rows = "SELECT
                    expense_date,
                    COALESCE(SUM(CASE WHEN transaction_type='income' THEN amount ELSE 0 END), 0) AS income_amount,
                    COALESCE(SUM(CASE WHEN transaction_type='expense' THEN amount ELSE 0 END), 0) AS expense_amount
                 FROM finance_cash_expenses
                 $where_period
                 GROUP BY expense_date
                 ORDER BY expense_date ASC";
    $stmt_rows = $pdo->prepare($sql_rows);
    $stmt_rows->execute($params_period);
    $rekap_rows = $stmt_rows->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rekap_opening = 0;
    $rekap_income = 0;
    $rekap_expense = 0;
    $rekap_closing = 0;
    $rekap_rows = [];
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-cash-stack"></i> Cash / Chasier</h3>
        <p class="text-muted">Kelola pemasukan dan pengeluaran umum di luar transaksi SO/Invoice.</p>
    </div>
    <div class="col-md-6 text-end">
        <?php if (has_permission('fin_ap_manage')): ?>
            <a href="index.php?page=fin-cash&action=create<?= $theme_q ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Input Transaksi Kas
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row mb-4 g-3">
    <div class="col-md-3">
        <div class="card border-start border-4 border-success shadow-sm h-100">
            <div class="card-body">
                <div class="small text-muted text-uppercase fw-bold">Pemasukan Posted (Bulan Ini)</div>
                <h5 class="fw-bold mt-2 mb-0 text-success">Rp <?= number_format($sum_income_month, 0, ',', '.') ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start border-4 border-danger shadow-sm h-100">
            <div class="card-body">
                <div class="small text-muted text-uppercase fw-bold">Pengeluaran Posted (Bulan Ini)</div>
                <h5 class="fw-bold mt-2 mb-0 text-danger">Rp <?= number_format($sum_expense_month, 0, ',', '.') ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start border-4 border-primary shadow-sm h-100">
            <div class="card-body">
                <div class="small text-muted text-uppercase fw-bold">Saldo Bersih (Bulan Ini)</div>
                <h5 class="fw-bold mt-2 mb-0 <?= $sum_balance_month >= 0 ? 'text-primary' : 'text-danger' ?>">Rp <?= number_format($sum_balance_month, 0, ',', '.') ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start border-4 border-warning shadow-sm h-100">
            <div class="card-body">
                <div class="small text-muted text-uppercase fw-bold">Draft Menunggu Posting</div>
                <h5 class="fw-bold mt-2 mb-0 text-warning">Rp <?= number_format($sum_draft, 0, ',', '.') ?></h5>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-info">
    <div class="card-header bg-light">
        <strong><i class="bi bi-clipboard-data"></i> Laporan Rekap Kas</strong>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-center mb-3">
            <input type="hidden" name="page" value="fin-cash">
            <?php if (!empty($_GET['theme'])): ?><input type="hidden" name="theme" value="<?= $esc($_GET['theme']) ?>"><?php endif; ?>
            <div class="col-md-2">
                <label class="form-label mb-1 small text-muted">Periode Dari</label>
                <input type="date" name="rekap_from" class="form-control" value="<?= $esc($rekap_from) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small text-muted">Sampai</label>
                <input type="date" name="rekap_to" class="form-control" value="<?= $esc($rekap_to) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1 small text-muted">Akun Kas / Bank</label>
                <select name="rekap_cash_coa" class="form-select">
                    <option value="0">Semua Akun Kas/Bank</option>
                    <?php foreach ($cash_coa_options as $opt): ?>
                        <option value="<?= (int)$opt['id'] ?>" <?= ((int)$rekap_cash_coa === (int)$opt['id'] ? 'selected' : '') ?>>
                            <?= $esc($opt['account_code'] . ' - ' . $opt['account_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small text-muted d-block">&nbsp;</label>
                <button type="submit" class="btn btn-info text-white w-100">Tampilkan Rekap</button>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small text-muted d-block">&nbsp;</label>
                <button type="submit" name="action" value="print" formtarget="_blank" class="btn btn-outline-dark w-100">
                    <i class="bi bi-printer"></i> Print Rekap
                </button>
            </div>
        </form>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted">Saldo Awal</div>
                    <div class="fw-bold fs-5">Rp <?= number_format($rekap_opening, 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted">Mutasi Masuk</div>
                    <div class="fw-bold fs-5 text-success">Rp <?= number_format($rekap_income, 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted">Mutasi Keluar</div>
                    <div class="fw-bold fs-5 text-danger">Rp <?= number_format($rekap_expense, 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted">Saldo Akhir</div>
                    <div class="fw-bold fs-5 <?= $rekap_closing >= 0 ? 'text-primary' : 'text-danger' ?>">Rp <?= number_format($rekap_closing, 0, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th class="text-end">Pemasukan</th>
                        <th class="text-end">Pengeluaran</th>
                        <th class="text-end">Net</th>
                        <th class="text-end">Saldo Berjalan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rekap_rows)): ?>
                        <?php $running_balance = $rekap_opening; ?>
                        <?php foreach ($rekap_rows as $rr):
                            $net = (float)$rr['income_amount'] - (float)$rr['expense_amount'];
                            $running_balance += $net;
                        ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime((string)$rr['expense_date'])) ?></td>
                                <td class="text-end text-success">Rp <?= number_format((float)$rr['income_amount'], 0, ',', '.') ?></td>
                                <td class="text-end text-danger">Rp <?= number_format((float)$rr['expense_amount'], 0, ',', '.') ?></td>
                                <td class="text-end <?= $net >= 0 ? 'text-primary' : 'text-danger' ?>">Rp <?= number_format($net, 0, ',', '.') ?></td>
                                <td class="text-end <?= $running_balance >= 0 ? 'text-primary' : 'text-danger' ?>">Rp <?= number_format($running_balance, 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">Belum ada transaksi posted di periode ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="cash-filter-form">
            <input type="hidden" name="page" value="fin-cash">
            <?php if (!empty($_GET['theme'])): ?><input type="hidden" name="theme" value="<?= $esc($_GET['theme']) ?>"><?php endif; ?>

            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari No / Kategori / Deskripsi / Relasi..." value="<?= $esc($search_key) ?>" autocomplete="off">
                </div>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control" value="<?= $esc($date_from) ?>" title="Tanggal Mulai">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control" value="<?= $esc($date_to) ?>" title="Tanggal Akhir">
            </div>
            <div class="col-md-2">
                <select name="trx_type" class="form-select">
                    <option value="">- Semua Jenis -</option>
                    <option value="income" <?= $filter_type === 'income' ? 'selected' : '' ?>>Pemasukan</option>
                    <option value="expense" <?= $filter_type === 'expense' ? 'selected' : '' ?>>Pengeluaran</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="draft" <?= $filter_status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="posted" <?= $filter_status === 'posted' ? 'selected' : '' ?>>Posted</option>
                    <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Jenis</th>
                        <th>No. Bukti</th>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Deskripsi</th>
                        <th>Akun</th>
                        <th class="text-end">Nominal</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" width="260">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT fce.*, c1.account_code AS counter_code, c1.account_name AS counter_name, c2.account_code AS cash_code, c2.account_name AS cash_name
                            FROM finance_cash_expenses fce
                            LEFT JOIN coa c1 ON c1.id = fce.coa_id
                            LEFT JOIN coa c2 ON c2.id = fce.cash_coa_id
                            WHERE 1=1";
                    $params = [];

                    if ($filter_status !== '') {
                        $sql .= " AND fce.status = ?";
                        $params[] = $filter_status;
                    }
                    if ($filter_type !== '') {
                        $sql .= " AND fce.transaction_type = ?";
                        $params[] = $filter_type;
                    }
                    if ($search_key !== '') {
                        $sql .= " AND (fce.expense_number LIKE ? OR fce.category LIKE ? OR fce.description LIKE ? OR fce.vendor_name LIKE ? OR fce.reference_no LIKE ? OR c1.account_name LIKE ? OR c2.account_name LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    if ($date_from !== '') {
                        $sql .= " AND fce.expense_date >= ?";
                        $params[] = $date_from;
                    }
                    if ($date_to !== '') {
                        $sql .= " AND fce.expense_date <= ?";
                        $params[] = $date_to;
                    }

                    $sql .= " ORDER BY fce.expense_date DESC, fce.id DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    $has_rows = false;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                        $has_rows = true;
                        $row_type = strtolower(trim((string)($row['transaction_type'] ?? 'expense')));
                        if (!in_array($row_type, ['expense', 'income'], true)) $row_type = 'expense';
                        $badge = match((string)$row['status']) {
                            'draft' => 'bg-secondary',
                            'posted' => 'bg-success',
                            'cancelled' => 'bg-danger',
                            default => 'bg-light text-dark',
                        };
                        $type_badge = ($row_type === 'income') ? 'bg-success' : 'bg-danger';
                    ?>
                    <tr>
                        <td class="text-center"><span class="badge <?= $type_badge ?>"><?= strtoupper($esc($type_label($row_type))) ?></span></td>
                        <td>
                            <strong><?= $esc($row['expense_number']) ?></strong>
                            <?php if (!empty($row['reference_no'])): ?>
                                <br><small class="text-muted">Ref: <?= $esc($row['reference_no']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime((string)$row['expense_date'])) ?></td>
                        <td><?= $esc($row['category']) ?></td>
                        <td>
                            <?= $esc($row['description']) ?>
                            <?php if (!empty($row['vendor_name'])): ?><br><small class="text-muted"><?= $esc($row['vendor_name']) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted d-block">Lawan:</small>
                            <small class="fw-semibold"><?= $esc(trim((string)($row['counter_code'] ?? '')) . (empty($row['counter_name']) ? '' : ' - ' . (string)$row['counter_name'])) ?></small>
                            <small class="text-muted d-block mt-1">Kas/Bank:</small>
                            <small class="fw-semibold"><?= $esc(trim((string)($row['cash_code'] ?? '')) . (empty($row['cash_name']) ? '' : ' - ' . (string)$row['cash_name'])) ?></small>
                        </td>
                        <td class="text-end fw-bold <?= $row_type === 'income' ? 'text-success' : 'text-danger' ?>">
                            Rp <?= number_format((float)$row['amount'], 0, ',', '.') ?>
                        </td>
                        <td class="text-center"><span class="badge <?= $badge ?>"><?= strtoupper($esc($row['status'])) ?></span></td>
                        <td class="text-center">
                            <?php if (has_permission('fin_ap_manage')): ?>
                                <div class="btn-group">
                                    <?php if ((string)$row['status'] === 'draft'): ?>
                                        <a href="index.php?page=fin-cash&action=edit&id=<?= (int)$row['id'] ?><?= $theme_q ?>" class="btn btn-sm btn-warning text-dark" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <a href="index.php?page=fin-cash&action=post&id=<?= (int)$row['id'] ?>&csrf=<?= urlencode($csrf) ?><?= $theme_q ?>" class="btn btn-sm btn-primary" onclick="return confirm('Posting transaksi <?= $esc(strtolower($type_label($row_type))) ?> ini?')" title="Post"><i class="bi bi-send"></i></a>
                                        <a href="index.php?page=fin-cash&action=delete&id=<?= (int)$row['id'] ?>&csrf=<?= urlencode($csrf) ?><?= $theme_q ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus data draft ini?')" title="Hapus"><i class="bi bi-trash"></i></a>
                                    <?php elseif ((string)$row['status'] === 'posted'): ?>
                                        <a href="index.php?page=fin-cash&action=unpost&id=<?= (int)$row['id'] ?>&csrf=<?= urlencode($csrf) ?><?= $theme_q ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Batalkan posting transaksi ini?')" title="Unpost"><i class="bi bi-arrow-counterclockwise"></i></a>
                                        <a href="index.php?page=fin-cash&action=cancel&id=<?= (int)$row['id'] ?>&csrf=<?= urlencode($csrf) ?><?= $theme_q ?>" class="btn btn-sm btn-danger" onclick="return confirm('Batalkan data transaksi ini?')" title="Cancel"><i class="bi bi-x-circle"></i></a>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small">Read Only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if (!$has_rows): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Belum ada data transaksi kas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('cash-filter-form');
    if (!form) return;
    const search = form.querySelector('input[name="search"]');
    let t;
    const submit = () => {
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
    };
    if (search) {
        search.addEventListener('input', () => {
            clearTimeout(t);
            t = setTimeout(submit, 400);
        });
    }
})();
</script>

<?php render_footer(); ?>
