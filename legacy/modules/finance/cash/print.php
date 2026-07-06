<?php
// modules/finance/cash/print.php

if (!function_exists('get_company_profile')) {
    if (file_exists('../../../config/database.php')) {
        require_once '../../../config/database.php';
        require_once '../../../config/functions.php';
    } elseif (file_exists('config/database.php')) {
        require_once 'config/database.php';
        require_once 'config/functions.php';
    } else {
        die("Error loading configuration.");
    }
}

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('fin_view')) {
    die("Akses ditolak.");
}

require_once __DIR__ . '/common.php';
fin_cash_ensure_schema($pdo);

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

$cash_coa_options = [];
try {
    $cash_coa_options = $pdo->query("SELECT id, account_code, account_name
                                     FROM coa
                                     WHERE account_type = 'asset' AND (is_active = 1 OR is_active IS NULL)
                                     ORDER BY account_code ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $cash_coa_options = [];
}
$cash_map = [];
foreach ($cash_coa_options as $acc) {
    $cash_map[(int)$acc['id']] = $acc;
}
if ($rekap_cash_coa > 0 && !isset($cash_map[$rekap_cash_coa])) {
    $rekap_cash_coa = 0;
}

$rekap_opening = 0;
$rekap_income = 0;
$rekap_expense = 0;
$rekap_closing = 0;
$rekap_rows = [];
$trx_rows = [];

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

    $sql_trx = "SELECT fce.*,
                       c1.account_code AS counter_code, c1.account_name AS counter_name,
                       c2.account_code AS cash_code, c2.account_name AS cash_name
                FROM finance_cash_expenses fce
                LEFT JOIN coa c1 ON c1.id = fce.coa_id
                LEFT JOIN coa c2 ON c2.id = fce.cash_coa_id
                $where_period
                ORDER BY fce.expense_date ASC, fce.id ASC";
    $stmt_trx = $pdo->prepare($sql_trx);
    $stmt_trx->execute($params_period);
    $trx_rows = $stmt_trx->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Gagal generate rekap kas: " . clean($e->getMessage()));
}

$comp = get_company_profile();
$company_name = $comp['company_name'] ?? 'MMS';
$company_addr = $comp['address'] ?? '-';
$company_logo = $comp['logo_path'] ?? '';

$printed_by = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'System');
$cash_label = 'Semua Akun Kas/Bank';
if ($rekap_cash_coa > 0 && isset($cash_map[$rekap_cash_coa])) {
    $cash_label = $cash_map[$rekap_cash_coa]['account_code'] . ' - ' . $cash_map[$rekap_cash_coa]['account_name'];
}

function mms_resolve_asset_path($path) {
    if (empty($path)) return '';
    if (file_exists($path)) return $path;
    $alt = '../../../' . ltrim($path, '/');
    if (file_exists($alt)) return $alt;
    return '';
}
$logo_path_final = mms_resolve_asset_path($company_logo);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Print Rekap Cash / Chasier</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: auto; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header-left img { max-height: 60px; object-fit: contain; }
        .header-right { text-align: right; }
        .doc-title { font-size: 24px; font-weight: bold; color: #555; letter-spacing: 1.6px; }

        .info-table { width: 100%; margin-bottom: 16px; border-collapse: collapse; }
        .info-table td { vertical-align: top; padding: 2px 0; }

        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .summary-table th, .summary-table td { border: 1px solid #000; padding: 6px; }
        .summary-table th { background: #f8f9fa; font-size: 10px; text-transform: uppercase; }
        .summary-value { font-size: 14px; font-weight: bold; }

        .section-title { margin-top: 14px; margin-bottom: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; text-decoration: underline; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 5px; }
        .data-table th { background: #f2f2f2; font-size: 10px; text-align: left; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .ok { color: #0f5132; font-weight: bold; }
        .bad { color: #b02a37; font-weight: bold; }

        .page-footer { border-top: 1px solid #ccc; padding-top: 10px; text-align: center; margin-top: auto; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }
        @media print { .box { border: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="box">
        <div class="doc-content">
        <div class="header">
            <div class="header-left">
                <?php if (!empty($logo_path_final)): ?>
                    <img src="<?= clean($logo_path_final) ?>" alt="Logo">
                <?php endif; ?>
            </div>
            <div class="header-right">
                <div class="doc-title">REKAP CASH / CHASIER</div>
                <div style="font-size: 14px; font-weight: bold; color: #333; margin-top: 5px;">
                    <?= date('d/m/Y', strtotime($rekap_from)) ?> - <?= date('d/m/Y', strtotime($rekap_to)) ?>
                </div>
            </div>
        </div>

        <table class="info-table">
            <tr>
                <td width="55%">
                    <strong>Periode:</strong> <?= date('d/m/Y', strtotime($rekap_from)) ?> s/d <?= date('d/m/Y', strtotime($rekap_to)) ?><br>
                    <strong>Akun Kas/Bank:</strong> <?= clean($cash_label) ?>
                </td>
                <td width="45%" align="right">
                    <strong>Dicetak Oleh:</strong> <?= clean($printed_by) ?><br>
                    <strong>Waktu Cetak:</strong> <?= date('d/m/Y H:i:s') ?>
                </td>
            </tr>
        </table>

        <table class="summary-table">
            <thead>
                <tr>
                    <th class="text-center">Saldo Awal</th>
                    <th class="text-center">Mutasi Masuk</th>
                    <th class="text-center">Mutasi Keluar</th>
                    <th class="text-center">Saldo Akhir</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center summary-value">Rp <?= number_format($rekap_opening, 0, ',', '.') ?></td>
                    <td class="text-center summary-value ok">Rp <?= number_format($rekap_income, 0, ',', '.') ?></td>
                    <td class="text-center summary-value bad">Rp <?= number_format($rekap_expense, 0, ',', '.') ?></td>
                    <td class="text-center summary-value <?= $rekap_closing >= 0 ? 'ok' : 'bad' ?>">Rp <?= number_format($rekap_closing, 0, ',', '.') ?></td>
                </tr>
            </tbody>
        </table>

        <div class="section-title">Ringkasan Harian</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th width="18%">Tanggal</th>
                    <th width="21%" class="text-right">Pemasukan</th>
                    <th width="21%" class="text-right">Pengeluaran</th>
                    <th width="20%" class="text-right">Net</th>
                    <th width="20%" class="text-right">Saldo Berjalan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rekap_rows)): ?>
                    <?php $running = $rekap_opening; ?>
                    <?php foreach ($rekap_rows as $r):
                        $net = (float)$r['income_amount'] - (float)$r['expense_amount'];
                        $running += $net;
                    ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime((string)$r['expense_date'])) ?></td>
                            <td class="text-right ok">Rp <?= number_format((float)$r['income_amount'], 0, ',', '.') ?></td>
                            <td class="text-right bad">Rp <?= number_format((float)$r['expense_amount'], 0, ',', '.') ?></td>
                            <td class="text-right <?= $net >= 0 ? 'ok' : 'bad' ?>">Rp <?= number_format($net, 0, ',', '.') ?></td>
                            <td class="text-right <?= $running >= 0 ? 'ok' : 'bad' ?>">Rp <?= number_format($running, 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">Tidak ada transaksi posted pada periode ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="section-title">Detail Transaksi Posted</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th width="10%">Tanggal</th>
                    <th width="13%">No Bukti</th>
                    <th width="10%">Jenis</th>
                    <th width="14%">Kategori</th>
                    <th width="20%">Deskripsi</th>
                    <th width="15%">Akun Kas/Bank</th>
                    <th width="8%" class="text-right">Masuk</th>
                    <th width="10%" class="text-right">Keluar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($trx_rows)): ?>
                    <?php foreach ($trx_rows as $t): ?>
                        <?php
                        $is_income = ((string)$t['transaction_type'] === 'income');
                        $in = $is_income ? (float)$t['amount'] : 0;
                        $out = $is_income ? 0 : (float)$t['amount'];
                        $cash_acc = trim((string)($t['cash_code'] ?? '')) . (!empty($t['cash_name']) ? ' - ' . (string)$t['cash_name'] : '');
                        ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime((string)$t['expense_date'])) ?></td>
                            <td><?= clean($t['expense_number']) ?></td>
                            <td><?= $is_income ? 'Pemasukan' : 'Pengeluaran' ?></td>
                            <td><?= clean($t['category']) ?></td>
                            <td><?= clean($t['description']) ?></td>
                            <td><?= clean($cash_acc) ?></td>
                            <td class="text-right ok"><?= $in > 0 ? ('Rp ' . number_format($in, 0, ',', '.')) : '-' ?></td>
                            <td class="text-right bad"><?= $out > 0 ? ('Rp ' . number_format($out, 0, ',', '.')) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">Tidak ada data detail pada periode ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <div class="page-footer">
            <span class="footer-comp-name"><?= strtoupper(clean($company_name)) ?></span>
            <span class="footer-addr"><?= clean($company_addr) ?></span>
        </div>
    </div>
</body>
</html>
