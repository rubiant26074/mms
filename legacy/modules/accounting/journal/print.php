<?php
// modules/accounting/journal/print.php

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

$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? clean($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? clean($_GET['end_date']) : '';

if ($start_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) $start_date = '';
if ($end_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) $end_date = '';

$sql = "SELECT j.id as journal_id, j.journal_date, j.journal_no, j.reference_no, j.description,
               ji.debit, ji.credit, c.account_name, c.account_code
        FROM journal_items ji
        JOIN journals j ON ji.journal_id = j.id
        JOIN coa c ON ji.coa_id = c.id
        WHERE 1=1";
$params = [];

if ($start_date !== '') {
    $sql .= " AND j.journal_date >= ?";
    $params[] = $start_date;
}
if ($end_date !== '') {
    $sql .= " AND j.journal_date <= ?";
    $params[] = $end_date;
}
if ($search_key !== '') {
    $sql .= " AND (j.journal_no LIKE ? OR j.reference_no LIKE ? OR j.description LIKE ? OR c.account_name LIKE ? OR c.account_code LIKE ?)";
    $params[] = "%$search_key%";
    $params[] = "%$search_key%";
    $params[] = "%$search_key%";
    $params[] = "%$search_key%";
    $params[] = "%$search_key%";
}
$sql .= " ORDER BY j.journal_date DESC, j.id DESC, ji.credit ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_debit = 0;
$total_credit = 0;
$journal_count_map = [];
foreach ($rows as $r) {
    $total_debit += (float)$r['debit'];
    $total_credit += (float)$r['credit'];
    $journal_count_map[(int)$r['journal_id']] = true;
}
$journal_count = count($journal_count_map);

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

$period_label = 'Semua Periode';
if ($start_date !== '' && $end_date !== '') {
    $period_label = date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date));
} elseif ($start_date !== '') {
    $period_label = 'Mulai ' . date('d/m/Y', strtotime($start_date));
} elseif ($end_date !== '') {
    $period_label = 'Sampai ' . date('d/m/Y', strtotime($end_date));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jurnal Umum - Print</title>
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
        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .summary-table th, .summary-table td { border: 1px solid #000; padding: 6px; }
        .summary-table th { background: #f8f9fa; font-size: 10px; text-transform: uppercase; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .item-table th, .item-table td { border: 1px solid #000; padding: 5px; }
        .item-table th { background: #f8f9fa; font-size: 10px; }
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
                    <div class="doc-title">JURNAL UMUM</div>
                    <div style="font-size: 14px; font-weight: bold; color: #333; margin-top: 5px;"><?= clean($period_label) ?></div>
                </div>
            </div>

            <table class="info-table">
                <tr>
                    <td width="55%">
                        <strong>Periode:</strong> <?= clean($period_label) ?><br>
                        <strong>Pencarian:</strong> <?= $search_key !== '' ? clean($search_key) : '-' ?>
                    </td>
                    <td width="45%" align="right">
                        <strong>Jumlah Jurnal:</strong> <?= (int)$journal_count ?><br>
                        <strong>Waktu Cetak:</strong> <?= date('d/m/Y H:i') ?>
                    </td>
                </tr>
            </table>

            <table class="summary-table">
                <thead>
                    <tr>
                        <th class="text-center">Total Debit</th>
                        <th class="text-center">Total Kredit</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center fw-bold">Rp <?= number_format($total_debit, 0, ',', '.') ?></td>
                        <td class="text-center fw-bold">Rp <?= number_format($total_credit, 0, ',', '.') ?></td>
                        <td class="text-center fw-bold"><?= round($total_debit, 2) === round($total_credit, 2) ? 'BALANCE' : 'TIDAK BALANCE' ?></td>
                    </tr>
                </tbody>
            </table>

            <table class="item-table">
                <thead>
                    <tr>
                        <th width="11%">Tanggal</th>
                        <th width="19%">No Jurnal / Ref</th>
                        <th width="24%">Akun (COA)</th>
                        <th width="22%">Keterangan</th>
                        <th width="12%" class="text-right">Debit</th>
                        <th width="12%" class="text-right">Kredit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Tidak ada data jurnal pada filter ini.</td>
                        </tr>
                    <?php else: ?>
                        <?php $current_journal = ''; ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $is_new = ($current_journal !== (string)$row['journal_no']);
                                $current_journal = (string)$row['journal_no'];
                                $indent_style = ((float)$row['credit'] > 0) ? 'padding-left: 20px;' : 'font-weight: bold;';
                            ?>
                            <tr>
                                <?php if ($is_new): ?>
                                    <td><?= date('d/m/Y', strtotime($row['journal_date'])) ?></td>
                                    <td>
                                        <strong><?= clean($row['journal_no']) ?></strong><br>
                                        <small><?= !empty($row['reference_no']) ? clean($row['reference_no']) : '-' ?></small>
                                    </td>
                                <?php else: ?>
                                    <td></td>
                                    <td></td>
                                <?php endif; ?>

                                <td style="<?= $indent_style ?>">
                                    <?= clean($row['account_code']) ?> - <?= clean($row['account_name']) ?>
                                </td>

                                <?php if ($is_new): ?>
                                    <td><?= clean($row['description']) ?></td>
                                <?php else: ?>
                                    <td></td>
                                <?php endif; ?>

                                <td class="text-right"><?= (float)$row['debit'] > 0 ? number_format((float)$row['debit'], 0, ',', '.') : '-' ?></td>
                                <td class="text-right"><?= (float)$row['credit'] > 0 ? number_format((float)$row['credit'], 0, ',', '.') : '-' ?></td>
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

