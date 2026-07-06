<?php
// modules/procurement/vendor_rating/print.php
if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('purch_vendor_view')
) {
    die('Akses ditolak.');
}
if (!function_exists('mms_is_dev_feature_enabled') || !mms_is_dev_feature_enabled('purch_vendor_rating')) {
    die('Modul Vendor Rating belum diaktifkan.');
}

if (function_exists('purch_vr_ensure_schema')) {
    try {
        purch_vr_ensure_schema($pdo);
    } catch (Exception $e) {
        die('Gagal menyiapkan tabel Vendor Rating.');
    }
}

$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$period_filter = trim((string)($_GET['period'] ?? date('Y-m')));
$supplier_filter = (int)($_GET['supplier_id'] ?? 0);

$sql = "SELECT vr.*, s.code AS supplier_code, s.name AS supplier_name
        FROM vendor_ratings vr
        JOIN suppliers s ON s.id = vr.supplier_id
        WHERE 1=1";
$params = [];
if ($period_filter !== '') {
    $sql .= " AND vr.rating_period = ?";
    $params[] = $period_filter;
}
if ($supplier_filter > 0) {
    $sql .= " AND vr.supplier_id = ?";
    $params[] = $supplier_filter;
}
$sql .= " ORDER BY vr.rating_period DESC, vr.total_score DESC, s.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$company = function_exists('get_company_profile') ? get_company_profile() : [];
$company_name = trim((string)($company['company_name'] ?? 'MMS'));
$avg_score = 0.0;
if (!empty($rows)) {
    $avg_score = array_sum(array_map(static fn($r) => (float)$r['total_score'], $rows)) / count($rows);
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Print Vendor Rating</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; }
        .wrap { width: 100%; max-width: 1080px; margin: 0 auto; }
        .head { margin-bottom: 12px; }
        .title { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cfcfcf; padding: 6px 8px; }
        th { background: #f3f3f3; text-align: left; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .summary { margin: 10px 0; }
        @media print {
            body { margin: 0; }
        }
    </style>
</head>
<body onload="window.print()">
<div class="wrap">
    <div class="head">
        <div class="title">Laporan Vendor Rating</div>
        <div class="muted"><?= $esc($company_name) ?></div>
        <div class="muted">
            Periode: <?= $esc($period_filter !== '' ? $period_filter : 'Semua') ?> |
            Supplier: <?= $supplier_filter > 0 ? (int)$supplier_filter : 'Semua' ?> |
            Tanggal Cetak: <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <div class="summary">
        <strong>Jumlah Data:</strong> <?= count($rows) ?> |
        <strong>Rata-rata Skor:</strong> <?= number_format($avg_score, 2, ',', '.') ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>Periode</th>
                <th>Vendor</th>
                <th class="text-end">Lead Time</th>
                <th class="text-end">Kualitas</th>
                <th class="text-end">Harga</th>
                <th class="text-end">Total</th>
                <th class="text-center">Grade</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center">Tidak ada data.</td></tr>
        <?php else: foreach ($rows as $r): ?>
            <?php
                $score = (float)$r['total_score'];
                $grade = function_exists('purch_vr_grade') ? purch_vr_grade($score) : ($score >= 85 ? 'A' : ($score >= 70 ? 'B' : ($score >= 55 ? 'C' : 'D')));
            ?>
            <tr>
                <td><?= $esc($r['rating_period']) ?></td>
                <td><?= $esc($r['supplier_code']) ?> - <?= $esc($r['supplier_name']) ?></td>
                <td class="text-end"><?= number_format((float)$r['lead_time_score'], 2, ',', '.') ?></td>
                <td class="text-end"><?= number_format((float)$r['quality_score'], 2, ',', '.') ?></td>
                <td class="text-end"><?= number_format((float)$r['price_score'], 2, ',', '.') ?></td>
                <td class="text-end"><strong><?= number_format((float)$r['total_score'], 2, ',', '.') ?></strong></td>
                <td class="text-center"><?= $esc($grade) ?></td>
                <td><?= $esc($r['notes'] ?: '-') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
