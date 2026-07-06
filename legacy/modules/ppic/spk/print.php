<?php
// modules/ppic/spk/print.php

// 1. KONEKSI & CONFIG (Path dinamis)
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

require_once __DIR__ . '/service.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    die("Error: ID SPK tidak valid.");
}

// 2. DATA HEADER SPK & JOIN SIGNATURES
$sql = "SELECT s.*, so.so_number, so.cust_po_number, c.name as cust_name, c.address as cust_addr, 
               u1.fullname as ppic_name, u1.signature_path as ppic_sig,
               u2.fullname as eng_name, u2.signature_path as eng_sig,
               u3.fullname as mgr_name, u3.signature_path as mgr_sig,
               u4.fullname as spv_name, u4.signature_path as spv_sig
        FROM spk s
        JOIN sales_orders so ON s.sales_order_id = so.id
        JOIN customers c ON so.customer_id = c.id
        LEFT JOIN users u1 ON s.created_by = u1.id
        LEFT JOIN users u2 ON s.approved_by_eng = u2.id
        LEFT JOIN users u3 ON s.approved_by_mgr = u3.id
        LEFT JOIN users u4 ON s.approved_by_spv = u4.id
        WHERE s.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) die("Data SPK tidak ditemukan.");

// 3. DATA MATERIAL & ITEM JADI
$stmtMaterials = $pdo->prepare("SELECT sm.*, i.item_name, i.item_code, i.unit FROM spk_materials sm JOIN items i ON sm.item_id = i.id WHERE sm.spk_id = ?");
$stmtMaterials->execute([$id]);
$materials = $stmtMaterials->fetchAll(PDO::FETCH_ASSOC);

$materialExpr = ppic_spk_material_select_expr($pdo);
$stmtSoItems = $pdo->prepare("SELECT soi.qty,
                                     COALESCE(i.item_name, soi.item_name_manual, '') AS item_name,
                                     COALESCE(i.item_code, soi.item_code_manual, '') AS item_code,
                                     COALESCE(i.unit, soi.unit_manual, '') AS unit,
                                     $materialExpr AS material
                              FROM sales_order_items soi
                              LEFT JOIN items i ON soi.item_id = i.id
                              WHERE soi.sales_order_id = ?");
$stmtSoItems->execute([(int)$data['sales_order_id']]);
$so_items = $stmtSoItems->fetchAll(PDO::FETCH_ASSOC);

$processes = !empty($data['required_processes']) ? explode(',', $data['required_processes']) : [];
$comp = get_company_profile();

// Penyesuaian path logo & fungsi tanda tangan
$logo_path_final = file_exists($comp['logo_path']) ? $comp['logo_path'] : '../../../' . $comp['logo_path'];

function get_sig_img($path, $date) {
    if (empty($path) || empty($date)) return '<div style="height: 50px;"></div>';
    $rel_path = '../../../' . $path;
    if (file_exists($rel_path)) {
        return '<img src="'.$rel_path.'" style="height: 50px; max-width: 120px; object-fit: contain; margin-bottom: -5px; display: block; margin-left: auto; margin-right: auto;">';
    }
    return '<div style="height: 50px; font-style: italic; font-size: 8px; padding-top:20px;">Digitally Signed</div>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SPK - <?= $data['spk_number'] ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px 20px 40px; color: #000; }
        .box { max-width: 800px; margin: auto; min-height: 96vh; display: flex; flex-direction: column; }
        
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; }
        .logo-area { width: 50%; }
        .logo-area img { max-height: 55px; object-fit: contain; }
        
        .header-right { text-align: right; width: 45%; }
        .doc-title-box { border: 1.5px solid #000; padding: 5px; display: inline-block; text-align: center; min-width: 250px; }
        .doc-title { font-size: 18px; font-weight: bold; letter-spacing: 1px; }

        .info-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .info-table td { vertical-align: top; padding: 2px; }
        
        .section-header { font-weight: bold; font-size: 11px; margin-bottom: 5px; text-decoration: underline; text-transform: uppercase; background: #f8f9fa; padding: 4px; border: 1px solid #ccc; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 5px; }
        .data-table th { background: #f2f2f2; font-size: 10px; }

        .route-sheet { border: 1px solid #000; padding: 10px; margin-bottom: 15px; display: flex; flex-direction: column; gap: 8px; }
        .route-row { display: flex; align-items: center; }
        .route-label { width: 80px; font-weight: bold; font-size: 10px; }
        .check-wrapper { display: flex; flex-wrap: wrap; flex: 1; }
        .check-item { width: 130px; font-size: 10px; display: flex; align-items: center; }
        .check-item input { margin-right: 5px; }

        .footer-sig { width: 100%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
        .footer-sig th { border: 1px solid #000; background: #f9f9f9; padding: 5px; font-size: 10px; }
        .footer-sig td { border: 1px solid #000; height: 95px; text-align: center; vertical-align: bottom; padding: 5px; font-size: 10px; }
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
        <div class="header">
            <div class="logo-area"><img src="<?= $logo_path_final ?>" alt="Logo"></div>
            <div class="header-right">
                <div class="doc-title-box">
                    <div class="doc-title">SURAT PERINTAH KERJA</div>
                    <div style="font-size: 10px;">PRODUCTION PLANNING & INVENTORY CONTROL</div>
                </div>
                <div style="font-size: 13px; font-weight: bold; margin-top: 8px;"><?= $data['spk_number'] ?></div>
            </div>
        </div>

        <table class="info-table">
            <tr>
                <td width="55%"><strong>Customer:</strong><br><strong><?= strtoupper($data['cust_name']) ?></strong><br><span style="font-size: 10px;"><?= nl2br($data['cust_addr']) ?></span></td>
                <td width="45%" align="right"><strong>Tgl Terbit :</strong> <?= date('d F Y', strtotime($data['spk_date'])) ?><br><strong>Nomor SO :</strong> <?= $data['so_number'] ?><br><strong>PO Customer :</strong> <?= $data['cust_po_number'] ?: '-' ?><br><strong>Deadline :</strong> <span style="color:red; font-weight:bold;"><?= date('d F Y', strtotime($data['deadline_date'])) ?></span></td>
            </tr>
        </table>

        <div class="section-header">1. Kebutuhan Material (Gudang)</div>
        <table class="data-table">
            <thead><tr><th width="5%">No</th><th>Deskripsi Material</th><th width="20%">Kode</th><th width="10%">Qty</th><th width="10%">Unit</th><th width="5%">Cek</th></tr></thead>
            <tbody>
                <?php $n=1; foreach($materials as $m): ?>
                <tr><td align="center"><?= $n++ ?></td><td><?= $m['item_name'] ?></td><td align="center"><?= $m['item_code'] ?></td><td align="center"><strong><?= $m['qty_required'] + 0 ?></strong></td><td align="center"><?= $m['unit'] ?></td><td></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="section-header">2. Alur Proses (Route Sheet)</div>
        <div class="route-sheet">
            <div class="route-row"><div class="route-label">INTERNAL:</div><div class="check-wrapper"><?php foreach(['Fibre Laser','CO Laser','Metal Bending','Acrylic Bending','Welding','Assembling'] as $p): ?><div class="check-item"><input type="checkbox" <?= in_array($p, $processes)?'checked':'' ?>> <?= $p ?></div><?php endforeach; ?></div></div>
            <div class="route-row"><div class="route-label">SUB-CON:</div><div class="check-wrapper"><?php foreach(['Powder Coating','Plating','Hot Deep Galv','Machining'] as $p): ?><div class="check-item"><input type="checkbox" <?= in_array($p, $processes)?'checked':'' ?>> <?= $p ?></div><?php endforeach; ?></div></div>
        </div>

        <div class="section-header">3. Daftar Item Barang Jadi (Finished Goods)</div>
        <table class="data-table">
            <thead><tr><th width="5%">No</th><th width="18%">Kode Item</th><th width="38%">Nama Barang Jadi</th><th width="19%">Material</th><th width="10%">Qty</th><th width="10%">Unit</th></tr></thead>
            <tbody>
                <?php $ni=1; foreach($so_items as $si): ?>
                <tr><td align="center"><?= $ni++ ?></td><td align="center"><strong><?= $si['item_code'] ?></strong></td><td><?= $si['item_name'] ?></td><td><?= htmlspecialchars($si['material'] ?? '-') ?></td><td align="center"><strong><?= $si['qty'] + 0 ?></strong></td><td align="center"><?= $si['unit'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="font-size: 10px; margin-top: 5px;"><strong>Catatan Produksi:</strong> <?= nl2br($data['notes']) ?: '-' ?></div>

        <table class="footer-sig">
            <thead><tr><th>Dibuat (PPIC Staff)</th><th>Teknis (Engineering)</th><th>Disetujui (Prod Manager)</th><th>Diterima (Prod Supervisor)</th></tr></thead>
            <tbody>
                <tr>
                    <td>
                        <?= get_sig_img($data['ppic_sig'], $data['created_at']) ?>
                        <span class="sig-name"><?= $data['ppic_name'] ?: 'PPIC Staff' ?></span>
                        <span class="sig-date">Tgl: <?= date('d/m/Y', strtotime($data['created_at'])) ?></span>
                    </td>
                    <td>
                        <?= get_sig_img($data['eng_sig'], $data['approved_at_eng']) ?>
                        <span class="sig-name"><?= $data['eng_name'] ?: '....................' ?></span>
                        <span class="sig-date">Tgl: <?= $data['approved_at_eng'] ? date('d/m/Y', strtotime($data['approved_at_eng'])) : '/ /' ?></span>
                    </td>
                    <td>
                        <?= get_sig_img($data['mgr_sig'], $data['approved_at_mgr']) ?>
                        <span class="sig-name"><?= $data['mgr_name'] ?: '....................' ?></span>
                        <span class="sig-date">Tgl: <?= $data['approved_at_mgr'] ? date('d/m/Y', strtotime($data['approved_at_mgr'])) : '/ /' ?></span>
                    </td>
                    <td>
                        <?= get_sig_img($data['spv_sig'], $data['approved_at_spv']) ?>
                        <span class="sig-name"><?= $data['spv_name'] ?: '....................' ?></span>
                        <span class="sig-date">Tgl: <?= $data['approved_at_spv'] ? date('d/m/Y', strtotime($data['approved_at_spv'])) : '/ /' ?></span>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="page-footer"><span class="footer-comp-name"><?= strtoupper($comp['company_name']) ?></span><span class="footer-addr"><?= $comp['address'] ?></span></div>
    </div>
</body>
</html>



