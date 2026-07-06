<?php
// modules/accounting/assets/print.php

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

$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';

$sql = "SELECT * FROM fixed_assets WHERE 1=1";
$params = [];
if ($filter_status !== '') {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
}
if ($search_key !== '') {
    $sql .= " AND (asset_code LIKE ? OR asset_name LIKE ? OR category LIKE ?)";
    $params[] = "%$search_key%";
    $params[] = "%$search_key%";
    $params[] = "%$search_key%";
}
$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_acq = 0;
$total_book = 0;
$total_depr_month = 0;
foreach ($assets as $a) {
    $total_acq += (float)$a['acquisition_cost'];
    $total_book += (float)$a['book_value'];
    $total_depr_month += (float)$a['monthly_depreciation'];
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
    <title>Fixed Asset - Print</title>
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
                    <div class="doc-title">FIXED ASSETS</div>
                    <div style="font-size: 14px; font-weight: bold; color: #333; margin-top: 5px;">AKTIVA TETAP</div>
                </div>
            </div>

            <table class="info-table">
                <tr>
                    <td width="55%">
                        <strong>Status Filter:</strong> <?= $filter_status !== '' ? strtoupper(clean($filter_status)) : 'SEMUA' ?><br>
                        <strong>Pencarian:</strong> <?= $search_key !== '' ? clean($search_key) : '-' ?>
                    </td>
                    <td width="45%" align="right">
                        <strong>Jumlah Aset:</strong> <?= count($assets) ?><br>
                        <strong>Tanggal Cetak:</strong> <?= date('d/m/Y H:i') ?>
                    </td>
                </tr>
            </table>

            <table class="summary-table">
                <thead>
                    <tr>
                        <th class="text-center">Total Harga Perolehan</th>
                        <th class="text-center">Total Nilai Buku</th>
                        <th class="text-center">Total Susut / Bulan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center fw-bold">Rp <?= number_format($total_acq, 0, ',', '.') ?></td>
                        <td class="text-center fw-bold">Rp <?= number_format($total_book, 0, ',', '.') ?></td>
                        <td class="text-center fw-bold">Rp <?= number_format($total_depr_month, 0, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>

            <table class="item-table">
                <thead>
                    <tr>
                        <th width="10%">Kode</th>
                        <th width="20%">Nama Aset</th>
                        <th width="12%">Kategori</th>
                        <th width="10%" class="text-center">Tgl Beli</th>
                        <th width="14%" class="text-right">Harga Perolehan</th>
                        <th width="14%" class="text-right">Nilai Buku</th>
                        <th width="10%" class="text-right">Susut/Bln</th>
                        <th width="10%" class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assets)): ?>
                        <tr><td colspan="8" class="text-center">Tidak ada data aset pada filter ini.</td></tr>
                    <?php else: foreach ($assets as $row): ?>
                        <tr>
                            <td class="fw-bold"><?= clean($row['asset_code']) ?></td>
                            <td><?= clean($row['asset_name']) ?></td>
                            <td><?= strtoupper(clean($row['category'])) ?></td>
                            <td class="text-center"><?= date('d/m/Y', strtotime($row['acquisition_date'])) ?></td>
                            <td class="text-right"><?= number_format((float)$row['acquisition_cost'], 0, ',', '.') ?></td>
                            <td class="text-right fw-bold"><?= number_format((float)$row['book_value'], 0, ',', '.') ?></td>
                            <td class="text-right"><?= number_format((float)$row['monthly_depreciation'], 0, ',', '.') ?></td>
                            <td class="text-center"><?= strtoupper(clean($row['status'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
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

