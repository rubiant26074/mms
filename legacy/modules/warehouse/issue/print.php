<?php
// modules/warehouse/issue/print.php

if (!function_exists('get_company_profile')) {
    if (file_exists('../../../config/database.php')) {
        require_once '../../../config/database.php';
        require_once '../../../config/functions.php';
    } elseif (file_exists('config/database.php')) {
        // Loaded via index.php
    } else {
        die("Error loading configuration.");
    }
}

if (!isset($_GET['id'])) die("Error: ID tidak ditemukan.");
$id = (int) $_GET['id'];

// 1. QUERY HEADER ITR
$sql = "SELECT mi.*, s.spk_number, s.project_name, s.sales_order_id,
               c.name as cust_name,
               u_req.fullname as requester_name, u_req.signature_path as requester_sig,
               u_iss.fullname as issuer_name, u_iss.signature_path as issuer_sig,
               (SELECT u.fullname FROM users u WHERE u.fullname = mi.received_by LIMIT 1) as receiver_name,
               (SELECT u.signature_path FROM users u WHERE u.fullname = mi.received_by LIMIT 1) as receiver_sig
        FROM material_issues mi
        JOIN spk s ON mi.spk_id = s.id
        JOIN sales_orders so ON s.sales_order_id = so.id
        JOIN customers c ON so.customer_id = c.id
        LEFT JOIN users u_req ON mi.created_by = u_req.id
        LEFT JOIN users u_iss ON u_iss.fullname = mi.issued_by
        WHERE mi.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) die("Data ITR tidak ditemukan.");

// 2. QUERY ITEMS
$stmt_items = $pdo->prepare("SELECT mii.*, i.item_name, i.item_code, i.unit, i.ownership
                             FROM material_issue_items mii 
                             JOIN items i ON mii.item_id = i.id 
                             WHERE mii.material_issue_id = ?");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll();

// 3. IDENTITAS PERUSAHAAN
$comp = get_company_profile();
$company_name = isset($comp['company_name']) ? $comp['company_name'] : 'PT. MANUFAKTUR SEJAHTERA';
$company_addr = isset($comp['address']) ? $comp['address'] : '-';
$logo_path = isset($comp['logo_path']) ? $comp['logo_path'] : '';

function resolve_asset_path($path) {
    if (empty($path)) return '';
    if (file_exists($path)) return $path;
    $alt = '../../../' . ltrim($path, '/');
    if (file_exists($alt)) return $alt;
    return '';
}

function render_sig_img($path) {
    $resolved = resolve_asset_path($path);
    if (empty($resolved)) {
        return '<div style="height:42px;"></div>';
    }
    return '<img src="' . clean($resolved) . '" alt="Signature" class="sig-image">';
}

$logo_html = '';
$logo_path_final = resolve_asset_path($logo_path);
if (!empty($logo_path_final)) {
    $logo_html = '<img src="' . clean($logo_path_final) . '" alt="Logo" style="max-height: 50px; margin-right: 15px;">';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>ITR - <?= clean($data['itr_number']) ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px 20px 40px; color: #000; }
        .box { border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: auto; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }

        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header-left { display: flex; align-items: center; width: 52%; }
        .header-left h1 { margin: 0; font-size: 16px; text-transform: uppercase; }
        .header-left small { font-size: 10px; color: #555; }
        .header-right { text-align: right; width: 44%; }
        .doc-title-box { border: 1.5px solid #000; padding: 5px; display: inline-block; text-align: center; min-width: 230px; }
        .doc-title { font-size: 16px; font-weight: bold; letter-spacing: 1px; }

        .info-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .info-table td { padding: 3px 2px; vertical-align: top; }

        .section-header { font-weight: bold; font-size: 11px; margin-bottom: 5px; text-decoration: underline; text-transform: uppercase; background: #f8f9fa; padding: 4px; border: 1px solid #ccc; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .item-table th, .item-table td { border: 1px solid #000; padding: 5px; }
        .item-table th { background: #f2f2f2; font-size: 10px; }

        .notes-box { border: 1px solid #000; padding: 6px; margin-bottom: 12px; min-height: 42px; font-size: 10px; }

        .footer-sig { width: 100%; border-collapse: collapse; margin-top: 18px; table-layout: fixed; }
        .footer-sig th { border: 1px solid #000; background: #f9f9f9; padding: 5px; font-size: 10px; }
        .footer-sig td { border: 1px solid #000; height: 95px; text-align: center; vertical-align: bottom; padding: 5px; font-size: 10px; }
        .sig-image { height: 40px; max-width: 120px; object-fit: contain; display: block; margin: 0 auto 4px; }
        .sig-name { display: block; font-weight: bold; text-decoration: underline; }
        .sig-note { display: block; font-size: 9px; color: #555; }
        .doc-meta { font-size: 9px; margin-top: 8px; text-align: center; color: #555; }
        .page-footer { margin-top: auto; text-align: center; border-top: 1px solid #ccc; padding-top: 10px; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }

        @media print { .no-print { display: none; } .box { border: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="box">
        <div class="no-print" style="text-align: center; margin-bottom: 10px;">
            <button onclick="window.print()" style="padding: 8px 15px; cursor: pointer;">Cetak Bukti</button>
        </div>
        <div class="doc-content">
            <div class="header">
                <div class="header-left">
                    <?= $logo_html ?>
                </div>
                <div class="header-right">
                    <div class="doc-title-box">
                        <div class="doc-title">BUKTI PENGELUARAN BARANG</div>
                        <div style="font-size:10px;">WAREHOUSE ISSUE / ITR</div>
                    </div>
                    <div style="font-size: 13px; font-weight: bold; margin-top: 8px;"><?= clean($data['itr_number']) ?></div>
                </div>
            </div>

        <table class="info-table">
            <tr>
                <td width="15%"><strong>No. ITR</strong></td><td width="35%">: <strong><?= clean($data['itr_number']) ?></strong></td>
                <td width="15%"><strong>Tgl Keluar</strong></td><td width="35%">: <?= date('d/m/Y', strtotime($data['itr_date'])) ?></td>
            </tr>
            <tr>
                <td><strong>Ref. SPK</strong></td><td>: <?= clean($data['spk_number']) ?></td>
                <td><strong>Customer</strong></td><td>: <?= clean($data['cust_name']) ?></td>
            </tr>
            <tr>
                <td><strong>Project</strong></td><td>: <?= clean($data['project_name']) ?></td>
                <td><strong>Ref. SO</strong></td><td>: <?= !empty($data['sales_order_id']) ? ('#' . (int)$data['sales_order_id']) : '-' ?></td>
            </tr>
        </table>

        <div class="section-header">Daftar Material Keluar</div>
        <table class="item-table">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="40%">Nama Material</th>
                    <th width="20%">Kode Item</th>
                    <th width="15%">Kepemilikan</th>
                    <th width="10%">Qty Keluar</th>
                    <th width="10%">Satuan</th>
                </tr>
            </thead>
            <tbody>
                <?php $no=1; foreach($items as $item): 
                    $own = ($item['ownership']=='customer') ? 'Consignment' : 'Internal';
                ?>
                <tr>
                    <td align="center"><?= $no++ ?></td>
                    <td><?= clean($item['item_name']) ?></td>
                    <td align="center"><?= clean($item['item_code']) ?></td>
                    <td align="center"><small><?= $own ?></small></td>
                    <td align="center"><strong><?= $item['qty_issued'] + 0 ?></strong></td>
                    <td align="center"><?= clean($item['unit']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="notes-box">
            <strong>Catatan Gudang:</strong> <?= !empty($data['notes']) ? nl2br(clean($data['notes'])) : '-' ?>
        </div>

            <table class="footer-sig">
                <thead>
                    <tr>
                        <th>Diserahkan Oleh (Gudang)</th>
                        <th>Diterima Oleh (Produksi)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                    <td>
                        <?= render_sig_img($data['issuer_sig'] ?? '') ?>
                        <span class="sig-name"><?= !empty($data['issuer_name']) ? clean($data['issuer_name']) : (!empty($data['issued_by']) ? clean($data['issued_by']) : '....................') ?></span>
                        <span class="sig-note">Petugas Gudang</span>
                    </td>
                    <td>
                        <?= render_sig_img($data['receiver_sig'] ?? ($data['requester_sig'] ?? '')) ?>
                        <span class="sig-name"><?= !empty($data['receiver_name']) ? clean($data['receiver_name']) : (!empty($data['received_by']) ? clean($data['received_by']) : '....................') ?></span>
                        <span class="sig-note">Produksi</span>
                    </td>
                    </tr>
                </tbody>
            </table>

            <div class="doc-meta">
                Dicetak pada: <?= date('d/m/Y H:i') ?> | Status: <?= strtoupper($data['status']) ?>
            </div>
        </div>

        <div class="page-footer">
            <span class="footer-comp-name"><?= strtoupper(clean($company_name)) ?></span>
            <span class="footer-addr"><?= clean($company_addr) ?></span>
        </div>
    </div>
</body>
</html>
