<?php
// modules/qc/incoming/print.php

// Load Config & Functions - more robust check
if (!function_exists('get_company_profile')) {
    if (file_exists('../../../config/database.php')) {
        require_once '../../../config/database.php';
        require_once '../../../config/functions.php';
    } elseif (file_exists('config/database.php')) {
        // Fallback for different include contexts
        require_once 'config/database.php';
        require_once 'config/functions.php';
    } else {
        die("Error loading configuration.");
    }
}

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('qc_incoming_view')) {
    http_response_code(403);
    die('Akses ditolak.');
}

if (!isset($_GET['id'])) die("Error: ID tidak ditemukan.");
$id = (int)$_GET['id'];
if ($id <= 0) die("Error: ID tidak valid.");

// 1. QUERY HEADER QC (Lengkap dengan data Approver)
$sql = "SELECT qc.*, 
               gr.gr_number, gr.gr_date, gr.delivery_note_number, gr.receipt_type,
               po.po_number, 
               s.name as supp_name, c.name as cust_name,
               u.fullname as inspector, u.signature_path as inspector_sig,
               u_app.fullname as approver, u_app.signature_path as approver_sig,
               u_wh.fullname as warehouse_pic, u_wh.signature_path as warehouse_sig
        FROM qc_incoming qc
        JOIN goods_receipts gr ON qc.goods_receipt_id = gr.id
        LEFT JOIN purchase_orders po ON gr.purchase_order_id = po.id
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN customers c ON gr.customer_id = c.id
        LEFT JOIN users u ON qc.inspector_id = u.id
        LEFT JOIN users u_app ON qc.approved_by = u_app.id
        LEFT JOIN users u_wh ON qc.handover_by = u_wh.id
        WHERE qc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();
if (!$data) die("Data QC tidak ditemukan.");

// 2. QUERY ITEMS
$stmt_items = $pdo->prepare("SELECT qci.*, i.item_name, i.item_code, i.unit, i.qc_type 
                             FROM qc_incoming_items qci 
                             JOIN items i ON qci.item_id = i.id 
                             WHERE qci.qc_incoming_id = ?");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll();

// 3. IDENTITAS PERUSAHAAN
$comp = get_company_profile();
$company_name = isset($comp['company_name']) ? $comp['company_name'] : 'PT. MANUFAKTUR SEJAHTERA';
$logo_path = isset($comp['logo_path']) ? $comp['logo_path'] : '';

// Logic Logo - adjust path for printing
$logo_html = '';
$logo_path_final = '';
if (!empty($logo_path)) {
    $logo_path_final = file_exists('../../../' . $logo_path) ? '../../../' . $logo_path : (file_exists($logo_path) ? $logo_path : '');
}

if (!empty($logo_path_final)) {
    $logo_html = '<img src="'.$logo_path_final.'" alt="Logo" style="max-height: 60px; object-fit: contain;">';
}

// Helper Label Mapping untuk Checklist
function get_label($key) {
    $map = [
        'thickness' => 'Ketebalan',
        'flatness' => 'Kerataan',
        'rust' => 'Karat/Korosi',
        'dimension' => 'Dimensi',
        'micron' => 'Micron (Cat)',
        'color' => 'Warna',
        'adhesion' => 'Daya Rekat',
        'defect' => 'Cacat Fisik',
        'surface' => 'Permukaan',
        'thread' => 'Drat/Ulir',
        'check' => 'Cek Umum'
    ];
    return isset($map[$key]) ? $map[$key] : ucfirst($key);
}

// Logic Status
$is_completed = ($data['status'] == 'completed');

// Helper for signature images
function get_sig_img_qc($path) {
    // Cek beberapa kemungkinan path untuk memastikan gambar ketemu
    $paths_to_check = [
        '../../../' . $path,
        '../../' . $path,
        $path
    ];

    foreach ($paths_to_check as $p) {
        if (!empty($path) && file_exists($p)) {
            return '<img src="'.$p.'" style="height: 55px; max-width: 120px; object-fit: contain; margin-bottom: -8px; display: block; margin-left: auto; margin-right: auto;">';
        }
    }
    return '<div style="height: 55px;"></div>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>QC Report - <?= $data['qc_number'] ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { max-width: 800px; margin: auto; position: relative; min-height: 96vh; }
        
        /* Header */
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-start; }
        .logo-area { width: 50%; }
        .header-right { text-align: right; width: 45%; }
        .doc-title-box { border: 1.5px solid #000; padding: 5px; display: inline-block; text-align: center; min-width: 250px; }
        .doc-title { font-size: 18px; font-weight: bold; letter-spacing: 1px; }

        /* Info Table */
        .info-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .info-table td { vertical-align: top; padding: 2px; }
        
        /* Content Table */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 8px; }
        .data-table th { background: #f2f2f2; font-size: 10px; text-transform: uppercase; }
        
        /* Checklist Box */
        .checklist-grid { display: flex; flex-wrap: wrap; gap: 5px; }
        .checklist-item { border: 1px solid #ccc; padding: 2px 4px; font-size: 9px; border-radius: 3px; background: #fff; white-space: nowrap; }
        .check-label { color: #555; margin-right: 3px; }
        .check-val { font-weight: bold; }
        
        /* Status Badges for Print */
        .status-ng { color: red; font-weight: bold; }

        /* Signature */
        .footer-sig { width: 100%; border-collapse: collapse; margin-top: 30px; table-layout: fixed; }
        .footer-sig th { border: 1px solid #000; background: #f9f9f9; padding: 5px; font-size: 10px; }
        .footer-sig td { border: 1px solid #000; height: 90px; text-align: center; vertical-align: bottom; padding: 8px; font-size: 10px; }
        .sig-name { font-weight: bold; text-decoration: underline; display: block; }
        .sig-date { font-size: 9px; margin-top: 2px; display: block; }

        /* Footer */
        .page-footer { position: absolute; bottom: 10px; left: 0; right: 0; text-align: center; border-top: 1px solid #ccc; padding-top: 10px; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }

        @media print {
            .no-print { display: none; }
            .box { border: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="text-align: center; margin-bottom: 15px;">
        <button onclick="window.print()" style="padding: 8px 15px; cursor: pointer;">Cetak</button>
        <button onclick="window.close()" style="padding: 8px 15px; cursor: pointer;">Tutup</button>
    </div>

    <div class="box">
        <div class="header">
            <div class="logo-area"><?= $logo_html ?></div>
            <div class="header-right">
                <div class="doc-title-box">
                    <div class="doc-title">INCOMING INSPECTION</div>
                    <div style="font-size: 9px; letter-spacing: 1px;">LAPORAN PEMERIKSAAN BARANG MASUK</div>
                </div>
                <div style="font-size: 13px; font-weight: bold; margin-top: 8px;"><?= $data['qc_number'] ?></div>
            </div>
        </div>

        <table class="info-table">
            <tr>
                <td width="15%"><strong>Sumber</strong></td>
                <td width="35%">: <?= ($data['receipt_type']=='consignment') ? $data['cust_name'].' (Customer)' : $data['supp_name'] ?></td>
                <td width="15%"><strong>Tgl Inspeksi</strong></td>
                <td width="35%">: <?= date('d F Y', strtotime($data['qc_date'])) ?></td>
            </tr>
            <tr>
                <td><strong>Ref. GR</strong></td>
                <td>: <?= $data['gr_number'] ?></td>
                <td><strong>No. SJ</strong></td>
                <td>: <?= $data['delivery_note_number'] ?></td>
            </tr>
            <tr>
                <td><strong>Ref. PO</strong></td>
                <td>: <?= $data['po_number'] ?? '-' ?></td>
                <td><strong>Inspector</strong></td>
                <td>: <?= $data['inspector'] ?></td>
            </tr>
            <tr>
                <td><strong>Keputusan</strong></td>
                <td colspan="3">: <strong style="text-transform:uppercase; font-size:12px;"><?= $data['final_decision'] ?></strong></td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th width="3%">No</th>
                    <th width="25%">Nama Barang / Material</th>
                    <th width="8%">Qty Rec</th>
                    <th width="8%">Qty OK</th>
                    <th width="8%">Qty NG</th>
                    <th width="35%">Detail Pengecekan (Checklist)</th>
                    <th width="13%">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php $no=1; foreach($items as $item): 
                    $checks = !empty($item['checklist_data']) ? json_decode($item['checklist_data'], true) : [];
                    $status_row = ($item['qty_reject'] > 0) ? 'color:red;' : '';
                ?>
                <tr style="<?= $status_row ?>">
                    <td align="center"><?= $no++ ?></td>
                    <td>
                        <strong><?= $item['item_name'] ?></strong><br>
                        <small>Kode: <?= $item['item_code'] ?></small><br>
                        <small style="font-style:italic;">Std: <?= ucfirst($item['qc_type']) ?></small>
                    </td>
                    <td align="center"><?= $item['qty_received'] + 0 ?></td>
                    <td align="center" style="font-weight:bold; color:green;"><?= $item['qty_good'] + 0 ?></td>
                    <td align="center" style="font-weight:bold; color:red;"><?= $item['qty_reject'] + 0 ?></td>
                    <td>
                        <?php if(!empty($checks) && is_array($checks)): ?>
                            <div class="checklist-grid">
                                <?php foreach($checks as $key => $val): 
                                    $class = ($val == 'NG' || $val == 'Fail' || $val == 'Major') ? 'status-ng' : '';
                                ?>
                                    <div class="checklist-item">
                                        <span class="check-label"><?= get_label($key) ?>:</span>
                                        <span class="check-val <?= $class ?>"><?= $val ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span style="color:#999; font-style:italic;">- Visual Check Only -</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $item['notes'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="border: 1px solid #000; padding: 5px; min-height: 40px; margin-bottom: 15px;">
            <strong>Catatan / Kesimpulan:</strong><br>
            <?= nl2br($data['notes']) ?>
        </div>

        <table class="footer-sig">
            <thead>
                <tr>
                    <th>Diperiksa Oleh (Inspector)</th>
                    <th>Disetujui (QC Manager)</th>
                    <th>Gudang (Serah Terima)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?= get_sig_img_qc($data['inspector_sig']) ?>
                        <span class="sig-name"><?= $data['inspector'] ?></span>
                        <span class="sig-date">Tgl: <?= date('d/m/Y', strtotime($data['qc_date'])) ?></span>
                    </td>
                    <td>
                        <?php if($is_completed) echo get_sig_img_qc($data['approver_sig']); else echo '<div style="height:55px;"></div>'; ?>
                        <span class="sig-name"><?= $data['approver'] ?: '....................' ?></span>
                        <span class="sig-date">Tgl: <?= ($is_completed && !empty($data['approved_at'])) ? date('d/m/Y', strtotime($data['approved_at'])) : '&nbsp;&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;&nbsp;/' ?></span>
                    </td>
                    <td>
                        <?php if(!empty($data['handover_by'])) echo get_sig_img_qc($data['warehouse_sig']); else echo '<div style="height:55px;"></div>'; ?>
                        <span class="sig-name"><?= $data['warehouse_pic'] ?: '( Staff Gudang )' ?></span>
                        <span class="sig-date">Tgl: <?= (!empty($data['handover_at'])) ? date('d/m/Y', strtotime($data['handover_at'])) : '&nbsp;&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;&nbsp;/' ?></span>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="page-footer">
            <span class="footer-comp-name"><?= strtoupper($comp['company_name']) ?></span>
            <span class="footer-addr"><?= $comp['address'] ?></span>
        </div>
    </div>
</body>
</html>
