<?php
// modules/warehouse/batch_expiry/print.php
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

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('whse_view')) {
    http_response_code(403);
    die('Akses ditolak.');
}
if (!function_exists('mms_is_dev_feature_enabled') || !mms_is_dev_feature_enabled('whse_batch_expiry')) {
    http_response_code(404);
    die('Modul belum diaktifkan.');
}

$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$search = trim((string)($_GET['search'] ?? ''));
$expiry_filter = trim((string)($_GET['expiry'] ?? 'all'));

if (!function_exists('whse_batch_resolve_asset_path')) {
    function whse_batch_resolve_asset_path($path) {
        $path = trim((string)$path);
        if ($path === '') return '';
        if (file_exists($path)) return $path;
        $alt = '../../../' . ltrim($path, '/');
        if (file_exists($alt)) return $alt;
        return '';
    }
}

$summary = [
    'total_batches' => 0,
    'near_expiry' => 0,
    'expired' => 0,
    'total_qty' => 0,
];
try {
    $summary_sql = "SELECT
        COUNT(*) AS total_batches,
        SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired,
        SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS near_expiry,
        COALESCE(SUM(qty_available), 0) AS total_qty
        FROM warehouse_batches
        WHERE qty_available > 0";
    $summary_stmt = $pdo->query($summary_sql);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: $summary;
} catch (Exception $e) {}

$sql = "SELECT b.*, i.item_code, i.item_name, COALESCE(NULLIF(b.unit, ''), i.unit) AS unit_name
        FROM warehouse_batches b
        JOIN items i ON i.id = b.item_id
        WHERE 1=1 AND b.qty_available > 0";
$params = [];
if ($search !== '') {
    $sql .= " AND (i.item_code LIKE ? OR i.item_name LIKE ? OR b.batch_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($expiry_filter === 'expired') {
    $sql .= " AND b.expiry_date IS NOT NULL AND b.expiry_date < CURDATE()";
} elseif ($expiry_filter === 'near') {
    $sql .= " AND b.expiry_date IS NOT NULL AND b.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
} elseif ($expiry_filter === 'no_expiry') {
    $sql .= " AND b.expiry_date IS NULL";
} elseif ($expiry_filter === 'safe') {
    $sql .= " AND b.expiry_date IS NOT NULL AND b.expiry_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}
$sql .= " ORDER BY (b.expiry_date IS NULL) ASC, b.expiry_date ASC, b.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$comp = get_company_profile();
$company_name = $comp['company_name'] ?? 'MMS SYSTEM';
$company_addr = $comp['address'] ?? '-';
$logo_path = whse_batch_resolve_asset_path($comp['logo_path'] ?? '');

$filter_label = match ($expiry_filter) {
    'expired' => 'EXPIRED',
    'near' => 'NEAR EXPIRY <= 30 HARI',
    'safe' => 'SAFE > 30 HARI',
    'no_expiry' => 'TANPA EXPIRY',
    default => 'SEMUA',
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Batch & Expiry</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: auto; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-start; }
        .doc-title { font-size: 21px; font-weight: bold; color: #555; letter-spacing: 1px; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .meta-table td { padding: 2px 4px; vertical-align: top; }
        .sum-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .sum-table td { border: 1px solid #ddd; padding: 6px 8px; }
        .sum-label { font-size: 10px; color: #666; text-transform: uppercase; }
        .sum-value { font-weight: bold; font-size: 14px; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .item-table th { background: #f8f9fa; border: 1px solid #000; padding: 6px; text-align: left; font-size: 10px; }
        .item-table td { border: 1px solid #000; padding: 6px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .page-footer { border-top: 1px solid #ccc; padding-top: 10px; text-align: center; margin-top: 20px; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }
        @media print { .no-print { display: none; } .box { border: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="text-align: center; margin-bottom: 12px;">
        <button onclick="window.print()" style="padding:8px 14px; cursor:pointer;">Cetak</button>
        <button onclick="window.close()" style="padding:8px 14px; cursor:pointer;">Tutup</button>
    </div>

    <div class="box">
        <div class="doc-content">
            <div class="header">
                <div><?= $logo_path !== '' ? '<img src="' . $esc($logo_path) . '" style="max-height:55px;">' : '' ?></div>
                <div style="text-align:right;">
                    <div class="doc-title">LAPORAN BATCH & EXPIRY</div>
                    <div style="font-size:12px; font-weight:bold; margin-top:4px;">WAREHOUSE</div>
                </div>
            </div>

            <table class="meta-table">
                <tr>
                    <td width="16%"><strong>Tanggal Cetak</strong></td>
                    <td width="34%">: <?= date('d/m/Y H:i') ?></td>
                    <td width="16%"><strong>Filter Expiry</strong></td>
                    <td width="34%">: <?= $esc($filter_label) ?></td>
                </tr>
                <tr>
                    <td><strong>Filter Search</strong></td>
                    <td colspan="3">: <?= $search !== '' ? $esc($search) : '-' ?></td>
                </tr>
            </table>

            <table class="sum-table">
                <tr>
                    <td><div class="sum-label">Batch Aktif</div><div class="sum-value"><?= (int)$summary['total_batches'] ?></div></td>
                    <td><div class="sum-label">Near Expiry</div><div class="sum-value"><?= (int)$summary['near_expiry'] ?></div></td>
                    <td><div class="sum-label">Expired</div><div class="sum-value"><?= (int)$summary['expired'] ?></div></td>
                    <td><div class="sum-label">Total Qty</div><div class="sum-value"><?= number_format((float)$summary['total_qty'], 2, ',', '.') ?></div></td>
                </tr>
            </table>

            <table class="item-table">
                <thead>
                    <tr>
                        <th width="4%" class="text-center">No</th>
                        <th width="17%">Item Code</th>
                        <th width="26%">Item Name</th>
                        <th width="14%">Batch</th>
                        <th width="10%" class="text-center">MFG</th>
                        <th width="10%" class="text-center">Expiry</th>
                        <th width="10%" class="text-right">Qty</th>
                        <th width="9%" class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="text-center">Tidak ada data.</td></tr>
                    <?php else: $no = 1; foreach ($rows as $r): ?>
                        <?php
                            $expiry = (string)($r['expiry_date'] ?? '');
                            $status = 'NO EXP';
                            if ($expiry !== '') {
                                if ($expiry < date('Y-m-d')) $status = 'EXPIRED';
                                elseif ($expiry <= date('Y-m-d', strtotime('+30 days'))) $status = 'NEAR';
                                else $status = 'SAFE';
                            }
                        ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><?= $esc($r['item_code']) ?></td>
                            <td><?= $esc($r['item_name']) ?></td>
                            <td><?= $esc($r['batch_number']) ?></td>
                            <td class="text-center"><?= !empty($r['mfg_date']) ? date('d/m/Y', strtotime((string)$r['mfg_date'])) : '-' ?></td>
                            <td class="text-center"><?= !empty($r['expiry_date']) ? date('d/m/Y', strtotime((string)$r['expiry_date'])) : '-' ?></td>
                            <td class="text-right"><?= number_format((float)$r['qty_available'], 2, ',', '.') . ' ' . $esc($r['unit_name']) ?></td>
                            <td class="text-center"><?= $esc($status) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="page-footer">
            <span class="footer-comp-name"><?= strtoupper($esc($company_name)) ?></span>
            <span class="footer-addr"><?= $esc($company_addr) ?></span>
        </div>
    </div>
</body>
</html>
