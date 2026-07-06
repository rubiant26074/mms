<?php
// modules/warehouse/delivery/print.php

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

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('whse_sj_manage')
) {
    die("Akses ditolak.");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) die("Error: ID tidak ditemukan.");
$id = (int) $_GET['id'];

// 1. Ambil Data Header SJ
$sql = "SELECT dn.*, 
               so.so_number, so.cust_po_number,
               c.name as cust_name, c.address as cust_addr, c.pic as cust_pic, c.phone as cust_phone,
               u.fullname as creator, u.signature_path as creator_sig,
               ua.fullname as approver, ua.signature_path as approver_sig,
               (SELECT GROUP_CONCAT(DISTINCT s.spk_number ORDER BY s.id SEPARATOR ', ')
                FROM spk s WHERE s.sales_order_id = so.id) as spk_numbers,
               (SELECT GROUP_CONCAT(DISTINCT COALESCE(s.project_name,'-') ORDER BY s.id SEPARATOR ', ')
                FROM spk s WHERE s.sales_order_id = so.id) as project_names
        FROM delivery_notes dn
        JOIN sales_orders so ON dn.sales_order_id = so.id
        JOIN customers c ON so.customer_id = c.id
        LEFT JOIN users u ON dn.created_by = u.id
        LEFT JOIN users ua ON dn.approved_by = ua.id
        WHERE dn.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) die("Data Surat Jalan tidak ditemukan.");

// 2. Ambil Data Items
$stmt_items = $pdo->prepare("SELECT dni.*, i.item_name, i.item_code, i.unit 
                             FROM delivery_note_items dni 
                             JOIN items i ON dni.item_id = i.id 
                             WHERE dni.delivery_note_id = ?
                             ORDER BY dni.id ASC");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll();

// 3. Ambil Identitas Perusahaan
$comp = get_company_profile();
$company_name = $comp['company_name'] ?? 'PT. MANUFAKTUR SEJAHTERA';
$company_addr = $comp['address'] ?? '-';
$company_phone = $comp['phone'] ?? '-';
$company_email = $comp['email'] ?? '-';
$company_logo = $comp['logo_path'] ?? '';

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

$logo_path_final = resolve_asset_path($company_logo);
$warehouse_sig_name = !empty($data['approver']) ? $data['approver'] : $data['creator'];
$warehouse_sig_path = !empty($data['approver_sig']) ? $data['approver_sig'] : $data['creator_sig'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan - <?= clean($data['dn_number']) ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box {
            max-width: 800px;
            margin: auto;
            border: 1px solid #ccc;
            padding: 20px;
            min-height: 96vh;
            display: flex;
            flex-direction: column;
        }
        .doc-content { flex: 1 1 auto; }
        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .logo-area img { max-height: 60px; object-fit: contain; }
        .header-right { text-align: right; }
        .doc-title { font-size: 24px; font-weight: bold; color: #555; letter-spacing: 2px; }
        .doc-number { margin-top: 5px; font-size: 14px; font-weight: bold; color: #333; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .info-table td { vertical-align: top; padding: 2px; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .item-table th {
            background: #f8f9fa;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        .item-table td { border-bottom: 1px solid #eee; padding: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .notes-box {
            border: 1px solid #ddd;
            background: #fafafa;
            padding: 8px 10px;
            margin-bottom: 10px;
            font-size: 10px;
        }
        .footer-sig { width: 100%; border-collapse: collapse; margin-top: 12px; table-layout: fixed; }
        .footer-sig th { border: 1px solid #000; background: #f9f9f9; padding: 5px; font-size: 10px; }
        .footer-sig td { border: 1px solid #000; height: 90px; text-align: center; vertical-align: bottom; padding: 5px; font-size: 10px; }
        .sig-image { height: 40px; max-width: 120px; object-fit: contain; display: block; margin: 0 auto 4px auto; }
        .sig-name { display: block; font-weight: bold; text-decoration: underline; }
        .sig-note { display: block; font-size: 9px; color: #555; }
        .page-footer {
            border-top: 1px solid #ccc;
            padding-top: 10px;
            text-align: center;
            font-size: 14.3px;
            font-weight: bold;
            margin-top: 20px;
        }
        .footer-addr { font-size: 9px; font-weight: normal; margin-top: 3px; color: #555; }

        @media print {
            .no-print { display: none; }
            .box { border: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <div style="text-align: center; margin-bottom: 20px;" class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #333; color: #fff; border: none; border-radius: 5px;">
            Cetak Surat Jalan
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; cursor: pointer; background: #ccc; border: none; border-radius: 5px; margin-left: 10px;">
            Tutup
        </button>
    </div>

    <div class="box">
        <div class="doc-content">
        
        <!-- HEADER KOP -->
        <div class="header">
            <div class="logo-area">
                <?php if (!empty($logo_path_final)): ?>
                    <img src="<?= clean($logo_path_final) ?>" alt="Logo">
                <?php endif; ?>
            </div>
            <div class="header-right">
                <div class="doc-title">SURAT JALAN</div>
                <div class="doc-number"><?= clean($data['dn_number']) ?></div>
            </div>
        </div>

        <!-- INFO PENGIRIMAN -->
        <table class="info-table">
            <tr>
                <td width="55%">
                    <strong>Kepada Yth:</strong><br>
                    <strong style="font-size: 13px;"><?= strtoupper(clean($data['cust_name'])) ?></strong><br>
                    UP: <?= clean($data['cust_pic']) ?><br>
                    <?= nl2br(clean($data['cust_addr'])) ?><br>
                    Telp: <?= clean($data['cust_phone']) ?>
                    <br><br>
                    <strong>Project:</strong> <?= clean($data['project_names'] ?: '-') ?>
                </td>
                <td width="45%" style="text-align: right;">
                    <table align="right">
                        <tr><td><strong>Tanggal Kirim :</strong></td><td align="right"><?= date('d/m/Y', strtotime($data['dn_date'])) ?></td></tr>
                        <tr><td><strong>No. SPK :</strong></td><td align="right"><?= clean($data['spk_numbers'] ?: '-') ?></td></tr>
                        <tr><td><strong>No. SO :</strong></td><td align="right"><?= clean($data['so_number']) ?></td></tr>
                        <tr><td><strong>No. PO Cust :</strong></td><td align="right"><?= !empty($data['cust_po_number']) ? clean($data['cust_po_number']) : '-' ?></td></tr>
                        <tr><td><strong>Kendaraan :</strong></td><td align="right"><?= !empty($data['vehicle_number']) ? clean($data['vehicle_number']) : '-' ?></td></tr>
                        <tr><td><strong>Pengemudi :</strong></td><td align="right"><?= !empty($data['driver_name']) ? clean($data['driver_name']) : '-' ?></td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- TABEL BARANG -->
        <table class="item-table">
            <thead>
                <tr style="background:#eee;">
                    <th width="5%" class="text-center">No</th>
                    <th>Nama Barang / Deskripsi</th>
                    <th width="15%" class="text-center">Qty Dikirim</th>
                    <th width="15%" class="text-center">Satuan</th>
                    <th width="20%">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php $no=1; foreach($items as $item): ?>
                <tr>
                    <td align="center"><?= $no++ ?></td>
                    <td>
                        <?= clean($item['item_name']) ?><br>
                        <small style="color:#666;">Kode: <?= clean($item['item_code']) ?></small>
                    </td>
                    <td align="center"><strong><?= $item['qty_sent'] + 0 ?></strong></td>
                    <td align="center"><?= clean($item['unit']) ?></td>
                    <td><?= clean($item['notes']) ?></td>
                </tr>
                <?php endforeach; ?>
                <!-- Spacer -->
                <?php for($i=0; $i<max(0, 5 - count($items)); $i++): ?>
                <tr><td colspan="5" style="padding: 15px;">&nbsp;</td></tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <?php if(!empty($data['notes'])): ?>
        <div class="notes-box">
            <strong>Catatan:</strong><br><?= nl2br(clean($data['notes'])) ?>
        </div>
        <?php endif; ?>

        <table class="footer-sig">
            <thead>
                <tr>
                    <th>Diterima Oleh</th>
                    <th>Pengirim / Supir</th>
                    <th>Security Check</th>
                    <th>Hormat Kami</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?= render_sig_img('') ?>
                        <span class="sig-name">Customer</span>
                        <span class="sig-note">(Tanda Tangan & Stempel)</span>
                    </td>
                    <td>
                        <?= render_sig_img('') ?>
                        <span class="sig-name"><?= !empty($data['driver_name']) ? clean($data['driver_name']) : '....................' ?></span>
                        <span class="sig-note">Pengemudi</span>
                    </td>
                    <td>
                        <?= render_sig_img('') ?>
                        <span class="sig-name">....................</span>
                        <span class="sig-note">Gate Pass</span>
                    </td>
                    <td>
                        <?= render_sig_img($warehouse_sig_path) ?>
                        <span class="sig-name"><?= !empty($warehouse_sig_name) ? clean($warehouse_sig_name) : '....................' ?></span>
                        <span class="sig-note"><?= !empty($data['approver']) ? 'Disetujui Gudang' : 'Bagian Gudang' ?></span>
                    </td>
                </tr>
            </tbody>
        </table>
        </div>

        <div class="page-footer">
            <span class="footer-comp-name"><?= strtoupper(clean($company_name)) ?></span>
            <div class="footer-addr"><?= clean($company_addr) ?></div>
        </div>
    </div>
</body>
</html>
