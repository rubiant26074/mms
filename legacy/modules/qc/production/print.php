<?php
// modules/qc/production/print.php

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('qc_production_view')) {
    http_response_code(403);
    die('Akses ditolak.');
}

$qc_id = isset($_GET['qc_id']) ? (int)$_GET['qc_id'] : 0;
if ($qc_id <= 0) die("QC ID required.");

$sql = "SELECT qc.*, spk.spk_number, spk.deadline_date, spk.sales_order_id,
               COALESCE(spk.project_name, '-') as project_name,
               c.name as customer_name,
               u.fullname as inspector_name, u.signature_path as inspector_sig,
               u_app.fullname as approver_name, u_app.signature_path as approver_sig
        FROM qc_production qc
        JOIN spk ON qc.spk_id = spk.id
        LEFT JOIN sales_orders so ON so.id = spk.sales_order_id
        LEFT JOIN customers c ON c.id = so.customer_id
        LEFT JOIN users u ON u.id = qc.inspector_id
        LEFT JOIN users u_app ON u_app.id = qc.approved_by
        WHERE qc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$qc_id]);
$qc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$qc) die("Data QC tidak ditemukan.");

$sql_items = "SELECT i.item_name, i.item_code, i.unit, soi.qty as plan_qty
              FROM spk
              LEFT JOIN sales_orders so ON so.id = spk.sales_order_id
              JOIN sales_order_items soi ON soi.sales_order_id = so.id
              JOIN items i ON i.id = soi.item_id
              WHERE spk.id = ?";
$stmt_items = $pdo->prepare($sql_items);
$stmt_items->execute([$qc['spk_id']]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Ringkasan final harus akumulatif per SPK sampai QC ini (termasuk recheck pengganti NG),
// bukan hanya sesi QC terakhir. Rumus efektif:
// Qty OK = akumulasi qty_pass
// Qty NG = qty_reject pada sesi QC yang sedang dicetak (outstanding terakhir)
// Qty Check = Qty OK + Qty NG (mewakili total part target saat ini)
$plan_total_qty = 0.0;
foreach ($items as $it_sum) {
    $plan_total_qty += (float)($it_sum['plan_qty'] ?? 0);
}

$qc_sum = [
    'sum_pass' => (float)($qc['qty_pass'] ?? 0),
];
try {
    $stmt_qc_sum = $pdo->prepare("SELECT COALESCE(SUM(qty_pass), 0) AS sum_pass
                                  FROM qc_production
                                  WHERE spk_id = ? AND id <= ?");
    $stmt_qc_sum->execute([(int)$qc['spk_id'], (int)$qc['id']]);
    $qc_sum_row = $stmt_qc_sum->fetch(PDO::FETCH_ASSOC);
    if ($qc_sum_row) {
        $qc_sum['sum_pass'] = (float)($qc_sum_row['sum_pass'] ?? 0);
    }
} catch (Exception $e) {
    // Fallback ke data QC saat ini saja jika query agregasi gagal.
}

$summary_qty_ng = max(0.0, (float)($qc['qty_reject'] ?? 0));
$summary_qty_ok = max(0.0, (float)($qc_sum['sum_pass'] ?? 0));
$summary_qty_check = $summary_qty_ok + $summary_qty_ng;
if ($plan_total_qty > 0) {
    // Cegah tampilan melebihi qty rencana jika ada anomali pembulatan/data lama.
    $summary_qty_check = min($summary_qty_check, $plan_total_qty);
    if ($summary_qty_ok > $summary_qty_check) {
        $summary_qty_ok = $summary_qty_check;
    }
}

$qc_history = [];
try {
    $stmt_qc_hist = $pdo->prepare("SELECT q.id, q.qc_number, q.qc_date, q.status, q.qty_check, q.qty_pass, q.qty_reject, q.notes,
                                          u.fullname AS inspector_name
                                   FROM qc_production q
                                   LEFT JOIN users u ON u.id = q.inspector_id
                                   WHERE q.spk_id = ? AND q.id <= ?
                                   ORDER BY q.id ASC");
    $stmt_qc_hist->execute([(int)$qc['spk_id'], (int)$qc['id']]);
    $qc_history = $stmt_qc_hist->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $qc_history = [];
}

$notes = (string)($qc['notes'] ?? '');
$laser = '-';
$bending = '-';
$welding = '-';
if (preg_match('/- Laser:\s*(.*)/i', $notes, $m)) $laser = trim($m[1]);
if (preg_match('/- Bending:\s*(.*)/i', $notes, $m)) $bending = trim($m[1]);
if (preg_match('/- Welding:\s*(.*)/i', $notes, $m)) $welding = trim($m[1]);

$comp = function_exists('get_company_profile') ? get_company_profile() : [
    'company_name' => 'PT. MANUFAKTUR SEJAHTERA',
    'address' => '-',
    'logo_path' => ''
];

$logo_html = '';
$logo_path = $comp['logo_path'] ?? '';
if (!empty($logo_path)) {
    $candidate = [
        $logo_path,
        '../' . $logo_path,
        '../../' . $logo_path,
        '../../../' . $logo_path
    ];
    foreach ($candidate as $p) {
        if (file_exists($p)) {
            $logo_html = '<img src="' . $p . '" alt="Logo" style="max-height:55px; object-fit:contain;">';
            break;
        }
    }
}

function get_sig_img_qc_prod($path) {
    if (empty($path)) return '<div class="sig-img-placeholder"></div>';
    $candidate = [
        $path,
        '../' . $path,
        '../../' . $path,
        '../../../' . $path
    ];
    foreach ($candidate as $p) {
        if (file_exists($p)) {
            return '<img src="' . $p . '" class="sig-img" alt="Signature">';
        }
    }
    return '<div class="sig-img-placeholder"></div>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Verifikasi QC Produksi - <?= clean($qc['qc_number']) ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { max-width: 800px; margin: auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-start; }
        .logo-area { width: 50%; }
        .header-right { text-align: right; width: 45%; }
        .doc-title-box { border: 1.5px solid #000; padding: 5px; display: inline-block; text-align: center; min-width: 280px; }
        .doc-title { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
        .info-table { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .info-table td { vertical-align: top; padding: 2px; }
        .section-header { font-weight: bold; font-size: 11px; margin-bottom: 5px; text-decoration: underline; text-transform: uppercase; background: #f8f9fa; padding: 4px; border: 1px solid #ccc; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 6px; }
        .data-table th { background: #f2f2f2; font-size: 10px; }
        .footer-sig { width: 100%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
        .footer-sig th { border: 1px solid #000; background: #f9f9f9; padding: 5px; font-size: 10px; }
        .footer-sig td { border: 1px solid #000; height: 100px; text-align: center; vertical-align: bottom; padding: 5px; font-size: 10px; }
        .sig-img { height: 48px; max-width: 120px; object-fit: contain; display: block; margin: 0 auto 4px auto; }
        .sig-img-placeholder { height: 48px; }
        .sig-name { font-weight: bold; text-decoration: underline; display: block; }
        .sig-date { font-size: 9px; margin-top: 2px; display: block; }
        .page-footer { margin-top: 14px; text-align: center; border-top: 1px solid #ccc; padding-top: 10px; page-break-inside: avoid; }
        .footer-comp-name { font-size: 14px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }
        .page-break { page-break-before: always; }
        .label-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .label-card { border: 2px solid #222; padding: 10px; min-height: 190px; position: relative; }
        .label-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .label-brand { display: flex; align-items: center; gap: 6px; }
        .label-brand img { height: 18px; width: auto; object-fit: contain; }
        .qc-badge { background: #0b5d2a; color: #fff; font-weight: bold; border-radius: 4px; padding: 4px 8px; letter-spacing: 0.5px; font-size: 11px; }
        .lbl-title { font-size: 13px; font-weight: bold; }
        .lbl-item { font-size: 16px; font-weight: bold; margin: 6px 0; line-height: 1.2; }
        .lbl-code { font-size: 11px; color: #333; margin-bottom: 6px; }
        .lbl-row { display: flex; justify-content: space-between; border-top: 1px dashed #aaa; padding-top: 5px; margin-top: 5px; font-size: 11px; }
        .no-print { text-align: center; margin-bottom: 15px; }
        .no-print button { padding: 8px 15px; cursor: pointer; margin-right: 8px; }
        @media print {
            .no-print { display: none; }
            .box { border: none; }
            .page-footer { margin-top: 10px; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print">
        <button onclick="window.print()">Cetak</button>
        <button onclick="window.close()">Tutup</button>
    </div>

    <div class="box">
        <div class="header">
            <div class="logo-area"><?= $logo_html ?></div>
            <div class="header-right">
                <div class="doc-title-box">
                    <div class="doc-title">QC FINAL PRODUCTION</div>
                    <div style="font-size: 9px; letter-spacing: 1px;">VERIFIKASI HASIL PEMERIKSAAN</div>
                </div>
                <div style="font-size: 13px; font-weight: bold; margin-top: 8px;"><?= clean($qc['qc_number']) ?></div>
            </div>
        </div>

        <table class="info-table">
            <tr>
                <td width="15%"><strong>No. SPK</strong></td>
                <td width="35%">: <?= clean($qc['spk_number']) ?></td>
                <td width="15%"><strong>Tgl QC</strong></td>
                <td width="35%">: <?= date('d F Y', strtotime($qc['qc_date'])) ?></td>
            </tr>
            <tr>
                <td><strong>Project</strong></td>
                <td>: <?= clean($qc['project_name']) ?></td>
                <td><strong>Inspector</strong></td>
                <td>: <?= clean($qc['inspector_name'] ?: '-') ?></td>
            </tr>
            <tr>
                <td><strong>Deadline SPK</strong></td>
                <td>: <?= !empty($qc['deadline_date']) ? date('d F Y', strtotime($qc['deadline_date'])) : '-' ?></td>
                <td><strong>Status QC</strong></td>
                <td>: <strong><?= strtoupper(clean($qc['status'])) ?></strong></td>
            </tr>
        </table>

        <div class="section-header">1. Ringkasan Hasil QC</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th width="20%">Qty Check</th>
                    <th width="20%">Qty OK</th>
                    <th width="20%">Qty NG</th>
                    <th width="40%">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td align="center"><strong><?= $summary_qty_check + 0 ?></strong></td>
                    <td align="center" style="color: #0b5d2a;"><strong><?= $summary_qty_ok + 0 ?></strong></td>
                    <td align="center" style="color: #b00020;"><strong><?= $summary_qty_ng + 0 ?></strong></td>
                    <td><?= ($summary_qty_ng + 0) > 0 ? 'Ada item NG, tindak lanjut NCR diperlukan.' : 'Semua hasil dinyatakan OK.' ?></td>
                </tr>
            </tbody>
        </table>

        <div class="section-header">2. Checklist Verifikasi Proses</div>
        <table class="data-table">
            <thead>
                <tr><th width="20%">Proses</th><th>Hasil Verifikasi</th></tr>
            </thead>
            <tbody>
                <tr><td><strong>Laser</strong></td><td><?= clean($laser ?: '-') ?></td></tr>
                <tr><td><strong>Bending</strong></td><td><?= clean($bending ?: '-') ?></td></tr>
                <tr><td><strong>Welding</strong></td><td><?= clean($welding ?: '-') ?></td></tr>
            </tbody>
        </table>

        <div class="section-header">3. Item Referensi SPK / SO</div>
        <table class="data-table">
            <thead>
                <tr><th width="5%">No</th><th width="22%">Kode Item</th><th>Nama Item</th><th width="12%">Qty</th><th width="10%">Unit</th></tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="5" align="center">Data item tidak ditemukan.</td></tr>
                <?php else: $n=1; foreach ($items as $it): ?>
                    <tr>
                        <td align="center"><?= $n++ ?></td>
                        <td align="center"><?= clean($it['item_code']) ?></td>
                        <td><?= clean($it['item_name']) ?></td>
                        <td align="center"><strong><?= $it['plan_qty'] + 0 ?></strong></td>
                        <td align="center"><?= clean($it['unit']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <div style="border: 1px solid #000; padding: 6px; min-height: 40px; margin-bottom: 10px;">
            <strong>Catatan QC (Histori):</strong><br>
            <?php if (empty($qc_history)): ?>
                <?= nl2br(clean($qc['notes'] ?: '-')) ?>
            <?php else: ?>
                <?php foreach ($qc_history as $idx_hist => $hist): ?>
                    <div style="<?= $idx_hist > 0 ? 'border-top:1px dashed #999; margin-top:5px; padding-top:5px;' : 'margin-top:4px;' ?>">
                        <strong><?= clean($hist['qc_number'] ?: ('QC #' . (int)$hist['id'])) ?></strong>
                        | Tgl: <?= !empty($hist['qc_date']) ? date('d/m/Y', strtotime($hist['qc_date'])) : '-' ?>
                        | Inspector: <?= clean($hist['inspector_name'] ?: '-') ?>
                        | Status: <strong><?= strtoupper(clean($hist['status'] ?: '-')) ?></strong>
                        | Check: <?= (float)($hist['qty_check'] ?? 0) + 0 ?>
                        | OK: <?= (float)($hist['qty_pass'] ?? 0) + 0 ?>
                        | NG: <?= (float)($hist['qty_reject'] ?? 0) + 0 ?>
                        <div style="margin-top:2px;">
                            <?= nl2br(clean(trim((string)($hist['notes'] ?? '')) !== '' ? (string)$hist['notes'] : '-')) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <table class="footer-sig">
            <thead>
                <tr>
                    <th>Diperiksa (QC Inspector)</th>
                    <th>Disetujui (QC Manager)</th>
                    <th>Mengetahui (Supervisor)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?= get_sig_img_qc_prod($qc['inspector_sig'] ?? '') ?>
                        <span class="sig-name"><?= clean($qc['inspector_name'] ?: 'Inspector') ?></span>
                        <span class="sig-date">Tgl: <?= date('d/m/Y', strtotime($qc['qc_date'])) ?></span>
                    </td>
                    <td>
                        <?= get_sig_img_qc_prod($qc['approver_sig'] ?? '') ?>
                        <span class="sig-name"><?= clean($qc['approver_name'] ?: '______________________') ?></span>
                        <span class="sig-date">Tgl: <?= !empty($qc['approved_at']) ? date('d/m/Y', strtotime($qc['approved_at'])) : '____ / ____ / ______' ?></span>
                    </td>
                    <td>
                        <div class="sig-img-placeholder"></div>
                        <span class="sig-name">______________________</span>
                        <span class="sig-date">Tgl: ____ / ____ / ______</span>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="page-footer">
            <span class="footer-comp-name"><?= strtoupper(clean($comp['company_name'] ?? '-')) ?></span>
            <span class="footer-addr"><?= clean($comp['address'] ?? '-') ?></span>
        </div>
    </div>

    <div class="box page-break">
        <div style="margin-bottom:10px;">
            <strong style="font-size:14px;">LABEL QC PRODUKSI</strong><br>
            <small style="color:#555;">Cetak per item untuk ditempel pada barang siap kirim</small>
        </div>

        <div class="label-wrap">
            <?php if (empty($items)): ?>
                <div class="label-card">
                    <div class="lbl-title">Tidak ada item untuk label.</div>
                </div>
            <?php else: foreach ($items as $it): ?>
                <div class="label-card">
                    <div class="label-hdr">
                        <div class="label-brand">
                            <?php if (!empty($logo_html)): ?>
                                <?= str_replace('max-height:55px; object-fit:contain;', 'height:18px; max-height:18px; object-fit:contain;', $logo_html) ?>
                            <?php endif; ?>
                            <div class="lbl-title">LABEL PENGIRIMAN</div>
                        </div>
                        <div class="qc-badge">QC OK</div>
                    </div>
                    <div><strong>SPK:</strong> <?= clean($qc['spk_number']) ?></div>
                    <div><strong>Customer:</strong> <?= clean($qc['customer_name'] ?: '-') ?></div>
                    <div><strong>QC No:</strong> <?= clean($qc['qc_number']) ?></div>
                    <div class="lbl-item"><?= clean($it['item_name']) ?></div>
                    <div class="lbl-code">Kode: <?= clean($it['item_code']) ?></div>
                    <div class="lbl-row">
                        <span><strong>Qty:</strong> <?= $it['plan_qty'] + 0 ?> <?= clean($it['unit']) ?></span>
                        <span><strong>Tgl QC:</strong> <?= date('d/m/Y', strtotime($qc['qc_date'])) ?></span>
                    </div>
                    <div class="lbl-row">
                        <span><strong>Inspector:</strong> <?= clean($qc['inspector_name'] ?: '-') ?></span>
                        <span><strong>Project:</strong> <?= clean($qc['project_name']) ?></span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</body>
</html>
