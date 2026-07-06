<?php
// modules/procurement/orders/print.php

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

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('purch_po')) {
    http_response_code(403);
    die('Akses ditolak.');
}

if (!isset($_GET['id'])) die("Error: ID PO tidak ditemukan.");
$id = (int) $_GET['id'];

// Header PO + supplier + signature user
$has_fin_cols = false;
try {
    $has_fin_cols = $pdo->query("SHOW COLUMNS FROM purchase_orders LIKE 'finance_approved_by'")->rowCount() > 0;
} catch (Exception $e) {
    $has_fin_cols = false;
}

$sql = "SELECT po.*,
               s.name as supp_name, s.address as supp_addr, s.contact_person as supp_cp, s.phone as supp_phone,
               u_create.fullname as creator_name, u_create.signature_path as creator_sig,
               u_approve.fullname as approver_name, u_approve.signature_path as approver_sig";
if ($has_fin_cols) {
    $sql .= ",
               u_fin.fullname as finance_name, u_fin.signature_path as finance_sig";
}
$sql .= "
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN users u_create ON po.created_by = u_create.id
        LEFT JOIN users u_approve ON po.approved_by = u_approve.id";
if ($has_fin_cols) {
    $sql .= " LEFT JOIN users u_fin ON po.finance_approved_by = u_fin.id";
}
$sql .= " WHERE po.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();
if (!$data) die("Data Purchase Order tidak ditemukan.");

// Detail item PO
$stmt_items = $pdo->prepare("SELECT poi.*, i.item_name, i.item_code, i.unit
                             FROM purchase_order_items poi
                             LEFT JOIN items i ON poi.item_id = i.id
                             WHERE poi.purchase_order_id = ?");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll();

$comp = get_company_profile();
$logo_path_final = file_exists($comp['logo_path']) ? $comp['logo_path'] : '../../../' . $comp['logo_path'];

$is_approved = in_array((string)$data['status'], ['approved', 'approved_finance', 'sent', 'completed'], true);
$is_pm_approved = in_array((string)$data['status'], ['approved', 'approved_pm', 'approved_finance', 'sent', 'completed'], true);
$is_fin_approved = in_array((string)$data['status'], ['approved', 'approved_finance', 'sent', 'completed'], true);

function get_sig_img_po($path, $show = true) {
    if (!$show || empty($path)) return '<div style="height: 55px;"></div>';
    $paths = [$path, '../../../' . $path, '../../' . $path];
    foreach ($paths as $p) {
        if (file_exists($p)) {
            return '<img src="'.$p.'" style="height: 55px; max-width: 120px; object-fit: contain; margin-bottom: -8px; display: block; margin-left: auto; margin-right: auto;">';
        }
    }
    return '<div style="height: 55px;"></div>';
}

$total_bruto = 0;
foreach ($items as $it) {
    $total_bruto += (float) $it['subtotal'];
}
$discount_amount = (float) ($data['discount_amount'] ?? 0);
$ppn_percent = (float) ($data['ppn_percent'] ?? 0);
$dpp = $total_bruto - $discount_amount;
if ($dpp < 0) $dpp = 0;
$ppn_val = $dpp * ($ppn_percent / 100);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>PO - <?= htmlspecialchars($data['po_number']) ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px 20px 40px; color: #000; }
        .box { max-width: 800px; margin: auto; min-height: 96vh; display: flex; flex-direction: column; }
        .watermark {
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 90px;
            font-weight: bold;
            color: rgba(200, 0, 0, 0.14);
            border: 4px solid rgba(200, 0, 0, 0.14);
            padding: 8px 30px;
            pointer-events: none;
            z-index: 0;
            white-space: nowrap;
        }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-start; position: relative; z-index: 1; }
        .logo-area { width: 50%; }
        .logo-area img { max-height: 60px; object-fit: contain; }
        .header-right { text-align: right; width: 45%; }
        .doc-title-box { border: 1.5px solid #000; padding: 5px; display: inline-block; text-align: center; min-width: 250px; }
        .doc-title { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
        .info-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; position: relative; z-index: 1; }
        .info-table td { vertical-align: top; padding: 2px; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; position: relative; z-index: 1; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 8px; }
        .data-table th { background: #f2f2f2; font-size: 10px; text-transform: uppercase; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .summary-table { width: 42%; margin-left: auto; border-collapse: collapse; margin-bottom: 15px; position: relative; z-index: 1; }
        .summary-table td { border: 1px solid #000; padding: 6px 8px; }
        .summary-table .lbl { background: #f9f9f9; font-weight: bold; }
        .summary-table .grand td { font-weight: bold; border-top: 2px solid #000; }
        .notes-box { border: 1px solid #000; background: #fafafa; padding: 8px; margin-bottom: 20px; position: relative; z-index: 1; }
        .footer-sig { width: 100%; border-collapse: collapse; margin-top: 10px; table-layout: fixed; position: relative; z-index: 1; }
        .footer-sig th { border: 1px solid #000; background: #f9f9f9; padding: 5px; font-size: 10px; }
        .footer-sig td { border: 1px solid #000; height: 92px; text-align: center; vertical-align: bottom; padding: 8px; font-size: 10px; }
        .sig-name { font-weight: bold; text-decoration: underline; display: block; }
        .sig-date { font-size: 9px; margin-top: 2px; display: block; }
        .page-footer { margin-top: auto; text-align: center; border-top: 1px solid #ccc; padding-top: 10px; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }
        @media print { .no-print { display: none; } .box { border: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="box">
        <?php if (!$is_approved): ?><div class="watermark">DRAFT</div><?php endif; ?>

        <div class="header">
            <div class="logo-area"><img src="<?= $logo_path_final ?>" alt="Logo"></div>
            <div class="header-right">
                <div class="doc-title-box">
                    <div class="doc-title">PURCHASE ORDER</div>
                    <div style="font-size: 9px; letter-spacing: 1px;">FORM PEMBELIAN MATERIAL</div>
                </div>
                <div style="font-size: 13px; font-weight: bold; margin-top: 8px;"><?= htmlspecialchars($data['po_number']) ?></div>
            </div>
        </div>

        <table class="info-table">
            <tr>
                <td width="55%">
                    <strong>Supplier:</strong><br>
                    <strong><?= strtoupper(htmlspecialchars($data['supp_name'])) ?></strong><br>
                    Attn: <?= htmlspecialchars($data['supp_cp'] ?: '-') ?><br>
                    <?= nl2br(htmlspecialchars($data['supp_addr'] ?: '-')) ?><br>
                    Telp: <?= htmlspecialchars($data['supp_phone'] ?: '-') ?>
                </td>
                <td width="45%" align="right">
                    <strong>Tgl PO :</strong> <?= !empty($data['po_date']) ? date('d F Y', strtotime($data['po_date'])) : '-' ?><br>
                    <strong>Tgl Kirim (Est) :</strong> <?= !empty($data['delivery_date']) ? date('d F Y', strtotime($data['delivery_date'])) : '-' ?><br>
                    <strong>Terms :</strong> <?= htmlspecialchars(!empty($data['payment_terms']) ? $data['payment_terms'] : '-') ?><br>
                    <strong>Status :</strong> <?= strtoupper(htmlspecialchars(str_replace('_', ' ', (string)$data['status']))) ?>
                </td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="16%">Kode Barang</th>
                    <th width="34%">Deskripsi Barang</th>
                    <th width="10%">Qty</th>
                    <th width="10%">Unit</th>
                    <th width="12%">Harga Satuan</th>
                    <th width="13%">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="7" align="center" style="color:#666;">Tidak ada detail item PO.</td>
                </tr>
                <?php else: $n = 1; foreach ($items as $item): ?>
                <tr>
                    <td class="text-center"><?= $n++ ?></td>
                    <td class="text-center"><?= htmlspecialchars(!empty($item['item_code']) ? $item['item_code'] : ('ITEM-' . (int)$item['item_id'])) ?></td>
                    <td>
                        <strong><?= htmlspecialchars(!empty($item['item_name']) ? $item['item_name'] : ('Item #' . (int)$item['item_id'])) ?></strong>
                        <?php if (!empty($item['notes'])): ?><br><small><i>Ket: <?= htmlspecialchars($item['notes']) ?></i></small><?php endif; ?>
                    </td>
                    <td class="text-center"><strong><?= (float)$item['qty'] + 0 ?></strong></td>
                    <td class="text-center"><?= htmlspecialchars(!empty($item['unit']) ? $item['unit'] : '-') ?></td>
                    <td class="text-right"><?= number_format((float)$item['unit_price'], 0, ',', '.') ?></td>
                    <td class="text-right"><?= number_format((float)$item['subtotal'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <table class="summary-table">
            <tr>
                <td class="lbl">Subtotal</td>
                <td class="text-right">Rp <?= number_format($total_bruto, 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td class="lbl">Diskon</td>
                <td class="text-right">Rp <?= number_format($discount_amount, 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td class="lbl">PPN (<?= $ppn_percent + 0 ?>%)</td>
                <td class="text-right">Rp <?= number_format($ppn_val, 0, ',', '.') ?></td>
            </tr>
            <tr class="grand">
                <td>GRAND TOTAL</td>
                <td class="text-right">Rp <?= number_format((float)$data['grand_total'], 0, ',', '.') ?></td>
            </tr>
        </table>

        <?php if (!empty($data['notes'])): ?>
        <div class="notes-box">
            <strong>Catatan:</strong><br>
            <?= nl2br(htmlspecialchars($data['notes'])) ?>
        </div>
        <?php endif; ?>

        <table class="footer-sig">
            <thead>
                <tr>
                    <th>Dibuat (Purchasing)</th>
                    <th>Disetujui (Plant Manager)</th>
                    <th>Disetujui (Finance)</th>
                    <th>Dikonfirmasi (Supplier)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?= get_sig_img_po($data['creator_sig'], true) ?>
                        <span class="sig-name"><?= htmlspecialchars($data['creator_name'] ?: 'Staff Purchasing') ?></span>
                        <span class="sig-date">Tgl: <?= !empty($data['po_date']) ? date('d/m/Y', strtotime($data['po_date'])) : '-' ?></span>
                    </td>
                    <td>
                        <?= get_sig_img_po($data['approver_sig'], $is_pm_approved) ?>
                        <span class="sig-name"><?= htmlspecialchars($data['approver_name'] ?: '....................') ?></span>
                        <span class="sig-date">Tgl: <?= !empty($data['approved_at']) ? date('d/m/Y', strtotime($data['approved_at'])) : '/ /' ?></span>
                    </td>
                    <td>
                        <?= get_sig_img_po($data['finance_sig'] ?? '', $is_fin_approved) ?>
                        <span class="sig-name"><?= htmlspecialchars($data['finance_name'] ?? '....................') ?></span>
                        <span class="sig-date">Tgl: <?= !empty($data['finance_approved_at']) ? date('d/m/Y', strtotime($data['finance_approved_at'])) : '/ /' ?></span>
                    </td>
                    <td>
                        <div style="height:55px;"></div>
                        <span class="sig-name">Supplier</span>
                        <span class="sig-date">(Tanda Tangan & Stempel)</span>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="page-footer">
            <span class="footer-comp-name"><?= strtoupper(htmlspecialchars($comp['company_name'] ?? '-')) ?></span>
            <span class="footer-addr"><?= htmlspecialchars($comp['address'] ?? '-') ?></span>
        </div>
    </div>
</body>
</html>
