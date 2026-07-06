<?php
if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('purch_po')) {
    die('Akses ditolak.');
}
if (!function_exists('mms_is_dev_feature_enabled') || !mms_is_dev_feature_enabled('purch_rfq')) {
    die('Modul RFQ belum diaktifkan.');
}
if (!isset($_GET['id'])) die('ID RFQ tidak valid.');
$id = (int)$_GET['id'];
if ($id <= 0) die('ID RFQ tidak valid.');

if (function_exists('purch_rfq_ensure_schema')) {
    try {
        purch_rfq_ensure_schema($pdo);
    } catch (Exception $e) {
        die('Gagal menyiapkan tabel RFQ.');
    }
}

$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$stmt_h = $pdo->prepare("SELECT r.*, u.fullname AS created_name
                         FROM purchase_rfqs r
                         LEFT JOIN users u ON u.id = r.created_by
                         WHERE r.id = ? LIMIT 1");
$stmt_h->execute([$id]);
$head = $stmt_h->fetch(PDO::FETCH_ASSOC);
if (!$head) die('Data RFQ tidak ditemukan.');

$stmt_l = $pdo->prepare("SELECT q.*, s.code AS supplier_code, s.name AS supplier_name
                         FROM purchase_rfq_quotes q
                         JOIN suppliers s ON s.id = q.supplier_id
                         WHERE q.rfq_id = ?
                         ORDER BY q.item_name ASC, q.unit_price ASC");
$stmt_l->execute([$id]);
$lines = $stmt_l->fetchAll(PDO::FETCH_ASSOC);

$best = [];
foreach ($lines as $ln) {
    $k = trim((string)$ln['item_name']) . '|' . trim((string)$ln['unit']);
    $p = (float)$ln['unit_price'];
    if (!isset($best[$k]) || $p < $best[$k]) $best[$k] = $p;
}

$company = function_exists('get_company_profile') ? get_company_profile() : [];
$company_name = trim((string)($company['company_name'] ?? 'MMS'));
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Print RFQ</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; }
        .wrap { max-width: 1180px; margin: 0 auto; }
        .title { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #f3f3f3; text-align: left; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body onload="window.print()">
<div class="wrap">
    <div class="title">RFQ Comparison Report</div>
    <div class="muted"><?= $esc($company_name) ?></div>
    <div class="muted">
        No RFQ: <strong><?= $esc($head['rfq_number']) ?></strong> |
        Tanggal: <?= date('d/m/Y', strtotime((string)$head['rfq_date'])) ?> |
        Due: <?= !empty($head['due_date']) ? date('d/m/Y', strtotime((string)$head['due_date'])) : '-' ?> |
        Status: <?= strtoupper($esc($head['status'])) ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-end">Qty</th>
                <th>Unit</th>
                <th>Vendor</th>
                <th class="text-end">Harga</th>
                <th class="text-end">Lead Time</th>
                <th class="text-end">Subtotal</th>
                <th class="text-center">Flag</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($lines)): ?>
            <tr><td colspan="8" class="text-center">Belum ada quote vendor.</td></tr>
        <?php else: foreach ($lines as $ln): ?>
            <?php
                $k = trim((string)$ln['item_name']) . '|' . trim((string)$ln['unit']);
                $is_best = (float)$ln['unit_price'] <= (float)($best[$k] ?? 0);
                $subtotal = (float)$ln['qty'] * (float)$ln['unit_price'];
            ?>
            <tr>
                <td><?= $esc($ln['item_name']) ?></td>
                <td class="text-end"><?= number_format((float)$ln['qty'], 4, ',', '.') ?></td>
                <td><?= $esc($ln['unit']) ?></td>
                <td><?= $esc($ln['supplier_code']) ?> - <?= $esc($ln['supplier_name']) ?></td>
                <td class="text-end"><?= number_format((float)$ln['unit_price'], 2, ',', '.') ?></td>
                <td class="text-end"><?= ($ln['lead_time_days'] !== null && $ln['lead_time_days'] !== '') ? (int)$ln['lead_time_days'] . ' hari' : '-' ?></td>
                <td class="text-end"><?= number_format($subtotal, 2, ',', '.') ?></td>
                <td class="text-center"><?= $is_best ? 'Best Price' : '-' ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
