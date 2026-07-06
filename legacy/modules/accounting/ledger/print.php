<?php
// modules/accounting/ledger/print.php

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

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('acc_view')) {
    die("Akses ditolak.");
}

$start_date = isset($_GET['start_date']) ? clean($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? clean($_GET['end_date']) : date('Y-m-d');
$coa_id = isset($_GET['coa_id']) ? (int)$_GET['coa_id'] : 0;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) $start_date = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) $end_date = date('Y-m-d');
if ($end_date < $start_date) {
    $tmp = $end_date;
    $end_date = $start_date;
    $start_date = $tmp;
}
if ($coa_id <= 0) {
    die("Akun COA belum dipilih.");
}

$stmt_acc = $pdo->prepare("SELECT * FROM coa WHERE id = ?");
$stmt_acc->execute([$coa_id]);
$account_info = $stmt_acc->fetch(PDO::FETCH_ASSOC);
if (!$account_info) {
    die("Akun COA tidak ditemukan.");
}

$sql_open = "SELECT SUM(debit) as tot_debit, SUM(credit) as tot_credit
             FROM journal_items ji
             JOIN journals j ON ji.journal_id = j.id
             WHERE ji.coa_id = ? AND j.journal_date < ?";
$stmt_open = $pdo->prepare($sql_open);
$stmt_open->execute([$coa_id, $start_date]);
$prev_mut = $stmt_open->fetch(PDO::FETCH_ASSOC);

$prev_debit = (float)($prev_mut['tot_debit'] ?? 0);
$prev_credit = (float)($prev_mut['tot_credit'] ?? 0);

if (($account_info['normal_balance'] ?? 'debit') === 'debit') {
    $opening_balance = (float)$account_info['opening_balance'] + ($prev_debit - $prev_credit);
} else {
    $opening_balance = (float)$account_info['opening_balance'] + ($prev_credit - $prev_debit);
}

$sql_ledger = "SELECT j.journal_date, j.journal_no, j.reference_no, j.description, ji.debit, ji.credit
               FROM journal_items ji
               JOIN journals j ON ji.journal_id = j.id
               WHERE ji.coa_id = ? AND j.journal_date BETWEEN ? AND ?
               ORDER BY j.journal_date ASC, j.id ASC";
$stmt_ledger = $pdo->prepare($sql_ledger);
$stmt_ledger->execute([$coa_id, $start_date, $end_date]);
$ledger = $stmt_ledger->fetchAll(PDO::FETCH_ASSOC);

$running_balance = $opening_balance;
$total_debit = 0;
$total_credit = 0;
foreach ($ledger as $row) {
    $debit = (float)$row['debit'];
    $credit = (float)$row['credit'];
    $total_debit += $debit;
    $total_credit += $credit;
    if (($account_info['normal_balance'] ?? 'debit') === 'debit') {
        $running_balance += ($debit - $credit);
    } else {
        $running_balance += ($credit - $debit);
    }
}

$comp = get_company_profile();
$company_name = $comp['company_name'] ?? 'MMS SYSTEM';
$company_addr = $comp['address'] ?? '-';
$company_logo = $comp['logo_path'] ?? '';

$logo_path_final = '';
if (!empty($company_logo)) {
    if (file_exists($company_logo)) {
        $logo_path_final = $company_logo;
    } else {
        $alt = '../../../' . ltrim((string)$company_logo, '/');
        if (file_exists($alt)) $logo_path_final = $alt;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buku Besar - <?= clean($account_info['account_code']) ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: auto; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header-left img { max-height: 60px; object-fit: contain; }
        .header-right { text-align: right; }
        .doc-title { font-size: 24px; font-weight: bold; color: #555; letter-spacing: 1.2px; }
        .info-table { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .info-table td { vertical-align: top; padding: 2px; }
        .summary-table, .item-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .summary-table th, .summary-table td, .item-table th, .item-table td { border: 1px solid #000; padding: 5px; }
        .summary-table th, .item-table th { background: #f8f9fa; font-size: 10px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .page-footer { border-top: 1px solid #ccc; padding-top: 10px; text-align: center; margin-top: auto; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }
        @media print { .no-print { display: none; } .box { border: none; } }
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
                    <div class="doc-title">BUKU BESAR</div>
                    <div style="font-size: 14px; font-weight: bold; color: #333; margin-top: 5px;"><?= clean($account_info['account_code']) ?> - <?= clean($account_info['account_name']) ?></div>
                </div>
            </div>

            <table class="info-table">
                <tr>
                    <td width="55%">
                        <strong>Periode:</strong> <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?><br>
                        <strong>Saldo Normal:</strong> <?= strtoupper(clean($account_info['normal_balance'])) ?>
                    </td>
                    <td width="45%" align="right">
                        <strong>Tanggal Cetak:</strong> <?= date('d/m/Y H:i') ?><br>
                        <strong>User:</strong> <?= clean($_SESSION['fullname'] ?? ($_SESSION['username'] ?? '-')) ?>
                    </td>
                </tr>
            </table>

            <table class="summary-table">
                <thead>
                    <tr>
                        <th class="text-center">Saldo Awal</th>
                        <th class="text-center">Total Debit</th>
                        <th class="text-center">Total Kredit</th>
                        <th class="text-center">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center fw-bold">Rp <?= number_format($opening_balance, 0, ',', '.') ?></td>
                        <td class="text-center fw-bold">Rp <?= number_format($total_debit, 0, ',', '.') ?></td>
                        <td class="text-center fw-bold">Rp <?= number_format($total_credit, 0, ',', '.') ?></td>
                        <td class="text-center fw-bold">Rp <?= number_format($running_balance, 0, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>

            <table class="item-table">
                <thead>
                    <tr>
                        <th width="12%">Tanggal</th>
                        <th width="18%">No. Jurnal / Ref</th>
                        <th width="30%">Keterangan</th>
                        <th width="14%" class="text-right">Debit</th>
                        <th width="14%" class="text-right">Kredit</th>
                        <th width="12%" class="text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" class="text-right fw-bold">SALDO AWAL (Per <?= date('d/m/Y', strtotime($start_date)) ?>)</td>
                        <td class="text-right fw-bold"><?= number_format($opening_balance, 0, ',', '.') ?></td>
                    </tr>
                    <?php if (empty($ledger)): ?>
                        <tr><td colspan="6" class="text-center">Tidak ada transaksi pada periode ini.</td></tr>
                    <?php else: ?>
                        <?php $saldo = $opening_balance; ?>
                        <?php foreach ($ledger as $row): ?>
                            <?php
                                $debit = (float)$row['debit'];
                                $credit = (float)$row['credit'];
                                if (($account_info['normal_balance'] ?? 'debit') === 'debit') {
                                    $saldo += ($debit - $credit);
                                } else {
                                    $saldo += ($credit - $debit);
                                }
                            ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($row['journal_date'])) ?></td>
                                <td><strong><?= clean($row['journal_no']) ?></strong><br><small><?= !empty($row['reference_no']) ? clean($row['reference_no']) : '-' ?></small></td>
                                <td><?= clean($row['description']) ?></td>
                                <td class="text-right"><?= $debit > 0 ? number_format($debit, 0, ',', '.') : '-' ?></td>
                                <td class="text-right"><?= $credit > 0 ? number_format($credit, 0, ',', '.') : '-' ?></td>
                                <td class="text-right fw-bold"><?= number_format($saldo, 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
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

