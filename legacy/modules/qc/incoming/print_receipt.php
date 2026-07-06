<?php
// modules/qc/incoming/print_receipt.php

// Load Config
if (file_exists('../../../config/database.php')) {
    require_once '../../../config/database.php';
    require_once '../../../config/functions.php';
} else {
    die("Error loading configuration.");
}

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('qc_incoming_view')) {
    http_response_code(403);
    die('Akses ditolak.');
}

if (!isset($_GET['id'])) die("Error: ID tidak ditemukan.");
$id = (int)$_GET['id'];
if ($id <= 0) die("Error: ID tidak valid.");

// Ambil Data QC & GR
$sql = "SELECT qc.*, 
               gr.gr_number, gr.gr_date, gr.delivery_note_number, gr.receipt_type,
               po.po_number, 
               s.name as supp_name, c.name as cust_name,
               u_wh.fullname as warehouse_pic, u_wh.signature_path as warehouse_sig
        FROM qc_incoming qc
        JOIN goods_receipts gr ON qc.goods_receipt_id = gr.id
        LEFT JOIN purchase_orders po ON gr.purchase_order_id = po.id
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN customers c ON gr.customer_id = c.id
        LEFT JOIN users u_wh ON qc.handover_by = u_wh.id
        WHERE qc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();
if (!$data) die("Data tidak ditemukan.");

// Ambil Items
$stmt_items = $pdo->prepare("SELECT qci.*, i.item_name, i.item_code, i.unit 
                             FROM qc_incoming_items qci 
                             JOIN items i ON qci.item_id = i.id 
                             WHERE qci.qc_incoming_id = ?");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll();

// Identitas Perusahaan
$comp = get_company_profile();
$company_name = isset($comp['company_name']) ? $comp['company_name'] : 'PT. MANUFAKTUR SEJAHTERA';
$logo_path = isset($comp['logo_path']) ? $comp['logo_path'] : '';

// Logic Logo
$logo_html = '';
$logo_path_final = '';
if (!empty($logo_path)) {
    $logo_path_final = file_exists('../../../' . $logo_path) ? '../../../' . $logo_path : (file_exists($logo_path) ? $logo_path : '');
}
if (!empty($logo_path_final)) {
    $logo_html = '<img src="'.$logo_path_final.'" alt="Logo" style="max-height: 45px; object-fit: contain;">';
}

// Helper Signature
function get_sig_img_receipt($path) {
    $paths = ['../../../' . $path, '../../' . $path, $path];
    foreach ($paths as $p) {
        if (!empty($path) && file_exists($p)) {
            return '<img src="'.$p.'" style="height: 40px; object-fit: contain; display:block; margin:0 auto;">';
        }
    }
    return '<div style="height: 40px;"></div>';
}

// Template HTML untuk 1 Receipt (Akan di-loop 2x)
function render_receipt($title_suffix, $data, $items, $company_name, $logo_html, $comp) {
    ?>
    <div class="receipt-container">
        <div class="header">
            <div class="logo-area"><?= $logo_html ?></div>
            <div class="header-right">
                <div class="doc-title-box">
                    <div class="doc-title">TANDA TERIMA BARANG</div>
                    <div style="font-size: 9px; letter-spacing: 1px;">MATERIAL RECEIPT</div>
                </div>
                <div style="font-size: 13px; font-weight: bold; margin-top: 5px;"><?= $data['qc_number'] ?></div>
                <div style="font-size: 9px; font-style: italic; margin-top: 2px;"><?= $title_suffix ?></div>
            </div>
        </div>

        <table class="info-table">
            <tr>
                <td width="15%"><strong>Supplier</strong></td>
                <td width="35%">: <?= ($data['receipt_type']=='consignment') ? $data['cust_name'] : $data['supp_name'] ?></td>
                <td width="15%"><strong>Tanggal</strong></td>
                <td width="35%">: <?= date('d/m/Y', strtotime($data['handover_at'] ?? $data['qc_date'])) ?></td>
            </tr>
            <tr>
                <td><strong>No. SJ</strong></td>
                <td>: <?= $data['delivery_note_number'] ?></td>
                <td><strong>Ref. GR</strong></td>
                <td>: <?= $data['gr_number'] ?></td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="45%">Nama Barang</th>
                    <th width="15%">Qty Datang</th>
                    <th width="15%">Qty Diterima</th>
                    <th width="20%">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php $no=1; foreach($items as $item): 
                    $qty_ok = $item['qty_good'] + 0;
                    $qty_rec = $item['qty_received'] + 0;
                ?>
                <tr>
                    <td align="center"><?= $no++ ?></td>
                    <td><?= $item['item_name'] ?> <br> <small><?= $item['item_code'] ?></small></td>
                    <td align="center"><?= $qty_rec ?> <?= $item['unit'] ?></td>
                    <td align="center" style="font-weight:bold;"><?= $qty_ok ?> <?= $item['unit'] ?></td>
                    <td><?= ($item['qty_reject'] > 0) ? 'Reject: '.($item['qty_reject']+0) : 'OK' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="border: 1px solid #000; padding: 5px; min-height: 30px; margin-bottom: 10px; font-size: 10px;">
            <strong>Catatan:</strong><br>
            Barang telah diperiksa oleh QC dan diterima oleh Gudang dalam kondisi baik sesuai kolom "Qty Diterima".
        </div>

        <table class="footer-sig">
            <thead>
                <tr>
                    <th>Pengirim (Driver / Supplier)</th>
                    <th>Diterima Oleh (Gudang)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div style="height: 40px;"></div>
                        <span class="sig-name">( ........................... )</span>
                    </td>
                    <td>
                        <?= get_sig_img_receipt($data['warehouse_sig']) ?>
                        <span class="sig-name"><?= $data['warehouse_pic'] ?: '( ........................... )' ?></span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tanda Terima - <?= $data['qc_number'] ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; font-size: 10px; }
        .no-print { padding: 10px 15px; background: #f8f9fa; border-bottom: 1px solid #ddd; display: flex; gap: 8px; }
        .no-print .btn { padding: 6px 12px; border-radius: 4px; border: 1px solid #ccc; background: #fff; cursor: pointer; font-size: 12px; }
        .no-print .btn-primary { background: #0d6efd; color: #fff; border-color: #0d6efd; }
        .no-print .btn-secondary { background: #6c757d; color: #fff; border-color: #6c757d; text-decoration: none; display: inline-flex; align-items: center; }
        
        .page-split { width: 100%; height: 148mm; box-sizing: border-box; padding: 15px 25px; position: relative; }
        .separator { border-bottom: 1px dashed #999; width: 100%; position: absolute; bottom: 0; left: 0; }
        
        .receipt-container { height: 100%; box-sizing: border-box; position: relative; }
        
        /* Header Style from print.php */
        .header { border-bottom: 2px solid #333; padding-bottom: 5px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: flex-start; }
        .logo-area { width: 50%; }
        .header-right { text-align: right; width: 45%; }
        .doc-title-box { border: 1.5px solid #000; padding: 3px; display: inline-block; text-align: center; min-width: 200px; }
        .doc-title { font-size: 14px; font-weight: bold; letter-spacing: 1px; }
        
        .info-table { width: 100%; font-size: 10px; margin-bottom: 10px; border-collapse: collapse; }
        .info-table td { padding: 2px; vertical-align: top; }
        
        .data-table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 10px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 4px; }
        .data-table th { background: #f2f2f2; text-align: center; text-transform: uppercase; font-size: 9px; }
        
        .footer-sig { width: 100%; border-collapse: collapse; margin-top: 5px; table-layout: fixed; }
        .footer-sig th { border: 1px solid #000; background: #f9f9f9; padding: 3px; font-size: 9px; }
        .footer-sig td { border: 1px solid #000; height: 60px; text-align: center; vertical-align: bottom; padding: 5px; font-size: 9px; }
        .sig-name { font-weight: bold; text-decoration: underline; display: block; margin-top: 5px; }
        @media print { .no-print { display: none; } }
    </style>
    <script>
        function doPrint() {
            window.print();
        }
    </script>
</head>
<body>
    <div class="no-print">
        <button class="btn btn-primary" type="button" onclick="doPrint()">Print</button>
        <a class="btn btn-secondary" href="/mms/index.php?page=qc-incoming">Kembali</a>
    </div>

    <!-- COPY 1: UNTUK SUPPLIER -->
    <div class="page-split">
        <?php render_receipt("LEMBAR 1: UNTUK SUPPLIER / PENGIRIM", $data, $items, $company_name, $logo_html, $comp); ?>
        <div class="separator"></div>
    </div>

    <!-- COPY 2: ARSIP GUDANG -->
    <div class="page-split">
        <?php render_receipt("LEMBAR 2: ARSIP GUDANG", $data, $items, $company_name, $logo_html, $comp); ?>
    </div>

</body>
</html>
