<?php
// modules/finance/ar/print_tax.php

if (!function_exists('get_company_profile')) {
    if (file_exists('../../../config/database.php')) {
        require_once '../../../config/database.php';
        require_once '../../../config/functions.php';
    } elseif (file_exists('config/database.php')) {
        // Loaded via index.php
    } else {
        die("Error config.");
    }
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: ID tidak valid.");
}
$id = (int) $_GET['id'];

// 1. DATA INVOICE & CUSTOMER
$sql = "SELECT inv.*, 
               c.name as cust_name, c.address as cust_addr, c.tax_id as cust_npwp,
               u.fullname as creator_name, u.signature_path as creator_sig
        FROM invoices inv
        JOIN customers c ON inv.customer_id = c.id
        LEFT JOIN users u ON inv.created_by = u.id
        WHERE inv.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) die("Invoice tidak ditemukan.");

// 2. DATA ITEM (Ambil dari DN Items via relasi)
$sql_items = "SELECT dni.qty_sent as qty, i.item_name, i.item_code, i.unit,
                     soi.unit_price, (dni.qty_sent * soi.unit_price) as subtotal
              FROM delivery_note_items dni
              JOIN delivery_notes dn ON dni.delivery_note_id = dn.id
              JOIN sales_orders so ON dn.sales_order_id = so.id
              JOIN sales_order_items soi ON (so.id = soi.sales_order_id AND dni.item_id = soi.item_id)
              JOIN items i ON dni.item_id = i.id
              WHERE dni.delivery_note_id = ?";
$stmt_items = $pdo->prepare($sql_items);
$stmt_items->execute([$data['delivery_note_id']]);
$items = $stmt_items->fetchAll();

// 3. DATA PENJUAL (Perusahaan Kita)
$comp = get_company_profile();
$seller_name = (!empty($comp['company_name']) ? $comp['company_name'] : 'MMS System');
$seller_addr = (!empty($comp['address']) ? $comp['address'] : '-');
$seller_npwp = (!empty($comp['npwp']) ? $comp['npwp'] : '-');
$seller_city = (!empty($comp['city']) ? $comp['city'] : '-');

$invoice_no = $data['invoice_number'] ?? '-';
$tax_invoice_no = !empty($data['tax_invoice_number']) ? $data['tax_invoice_number'] : '____________________';
$invoice_date = !empty($data['invoice_date']) ? date('d-m-Y', strtotime($data['invoice_date'])) : '-';

$dpp = max(0, (float)$data['subtotal'] - (float)$data['discount_amount']);
$ppn = (float)$data['tax_amount'];

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
        return '<div style="height:52px;"></div>';
    }
    return '<img src="' . clean($resolved) . '" alt="Tanda Tangan" style="height:50px; max-width:130px; object-fit:contain; display:block; margin-left:auto; margin-right:auto;">';
}

$signature_token = generate_tax_signature_token(
    $data['invoice_number'] ?? '',
    $data['tax_invoice_number'] ?? '',
    $data['invoice_date'] ?? '',
    (float)$data['grand_total'],
    $data['cust_npwp'] ?? '',
    $seller_npwp
);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$verifyPath = ($basePath === '' ? '' : $basePath) . '/modules/finance/ar/verify_tax.php';
$verifyUrl = $scheme . '://' . $host . $verifyPath . '?inv=' . (int)$data['id'] . '&token=' . rawurlencode($signature_token);
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=170x170&data=' . rawurlencode($verifyUrl);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Faktur Pajak - <?= clean($tax_invoice_no) ?></title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            font-size: 10.5px;
            color: #000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .sheet {
            width: 190mm;
            margin: 0 auto;
            border: 1.6px solid #000;
            padding: 0;
        }
        .print-toolbar {
            text-align: center;
            margin-bottom: 10px;
        }
        .print-toolbar button {
            border: 1px solid #333;
            background: #fff;
            color: #111;
            padding: 7px 12px;
            font-size: 12px;
            cursor: pointer;
        }
        .head-title {
            text-align: center;
            border-bottom: 1.6px solid #000;
            padding: 8px 6px 6px;
        }
        .head-title h1 {
            margin: 0;
            font-size: 20px;
            letter-spacing: 0.8px;
        }
        .head-sub {
            margin-top: 2px;
            font-size: 9px;
        }
        .fp-no {
            width: 100%;
            border-collapse: collapse;
        }
        .fp-no td {
            border-bottom: 1px solid #000;
            padding: 6px 8px;
        }
        .fp-no .label {
            width: 42%;
            font-weight: bold;
            border-right: 1px solid #000;
        }
        .section-label {
            padding: 5px 8px;
            font-weight: bold;
            border-bottom: 1px solid #000;
            background: #f2f2f2;
        }
        .identity {
            width: 100%;
            border-collapse: collapse;
        }
        .identity td {
            border-bottom: 1px solid #000;
            padding: 5px 8px;
            vertical-align: top;
        }
        .identity .label {
            width: 18%;
            border-right: 1px solid #000;
            font-weight: bold;
        }
        .identity .value {
            width: 82%;
        }
        .item-table {
            width: 100%;
            border-collapse: collapse;
        }
        .item-table th,
        .item-table td {
            border-bottom: 1px solid #000;
            border-right: 1px solid #000;
            padding: 5px 6px;
            vertical-align: top;
        }
        .item-table th:last-child,
        .item-table td:last-child {
            border-right: none;
        }
        .item-table th {
            text-align: center;
            background: #f7f7f7;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .totals td {
            font-weight: normal;
        }
        .totals .sum-label {
            text-align: right;
            font-weight: bold;
        }
        .signature {
            width: 100%;
            border-collapse: collapse;
        }
        .signature td {
            padding: 10px 8px;
            vertical-align: top;
        }
        .sig-box {
            border: 1px solid #000;
            min-height: 90px;
            padding: 8px;
        }
        .sig-city {
            margin-bottom: 4px;
            text-align: right;
        }
        .sig-title {
            text-align: right;
        }
        .sig-name {
            margin-top: 48px;
            text-align: right;
            font-weight: bold;
            text-decoration: underline;
        }
        .qr-box {
            border: 1px solid #000;
            min-height: 90px;
            padding: 8px;
            text-align: center;
        }
        .qr-caption {
            margin-top: 4px;
            font-size: 8.5px;
            line-height: 1.25;
            word-break: break-word;
        }
        .note-bar {
            border-top: 1px solid #000;
            padding: 6px 8px;
            font-size: 9px;
        }
        @media print {
            .print-toolbar { display: none; }
            .sheet { border: 1.6px solid #000; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="print-toolbar">
        <button onclick="window.print()">Cetak Faktur Pajak</button>
        <button onclick="window.close()">Tutup</button>
    </div>

    <div class="sheet">
        <div class="head-title">
            <h1>FAKTUR PAJAK</h1>
            <div class="head-sub">Dokumen Pajak Keluaran (Format Cetak A4)</div>
        </div>

        <table class="fp-no">
            <tr>
                <td class="label">Kode dan Nomor Seri Faktur Pajak</td>
                <td><?= clean($tax_invoice_no) ?></td>
            </tr>
            <tr>
                <td class="label">Nomor Invoice Referensi</td>
                <td><?= clean($invoice_no) ?> | Tanggal: <?= clean($invoice_date) ?></td>
            </tr>
        </table>

        <div class="section-label">I. PENGUSAHA KENA PAJAK (PENJUAL)</div>
        <table class="identity">
            <tr>
                <td class="label">Nama</td>
                <td class="value"><?= strtoupper(clean($seller_name)) ?></td>
            </tr>
            <tr>
                <td class="label">Alamat</td>
                <td class="value"><?= nl2br(clean($seller_addr)) ?></td>
            </tr>
            <tr>
                <td class="label">NPWP</td>
                <td class="value"><?= clean($seller_npwp) ?></td>
            </tr>
        </table>

        <div class="section-label">II. PEMBELI BARANG KENA PAJAK / PENERIMA JASA KENA PAJAK</div>
        <table class="identity">
            <tr>
                <td class="label">Nama</td>
                <td class="value"><?= strtoupper(clean($data['cust_name'])) ?></td>
            </tr>
            <tr>
                <td class="label">Alamat</td>
                <td class="value"><?= nl2br(clean($data['cust_addr'])) ?></td>
            </tr>
            <tr>
                <td class="label">NPWP</td>
                <td class="value"><?= !empty($data['cust_npwp']) ? clean($data['cust_npwp']) : '00.000.000.0-000.000' ?></td>
            </tr>
        </table>

        <div class="section-label">III. DETAIL BARANG / JASA KENA PAJAK</div>
        <table class="item-table">
            <thead>
                <tr>
                    <th width="8%">No</th>
                    <th width="58%">Nama Barang Kena Pajak / Jasa Kena Pajak</th>
                    <th width="34%">Harga Jual / Penggantian</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach($items as $item): ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td>
                        <?= clean($item['item_name']) ?><br>
                        <small>(<?= $item['qty']+0 ?> <?= clean($item['unit']) ?> x @<?= number_format($item['unit_price'],0,',','.') ?>)</small>
                    </td>
                    <td class="text-right">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php for($i=0; $i<(5-count($items)); $i++): ?>
                <tr><td>&nbsp;</td><td></td><td></td></tr>
                <?php endfor; ?>
                <tr class="totals">
                    <td colspan="2" class="sum-label">Harga Jual / Penggantian</td>
                    <td class="text-right">Rp <?= number_format($data['subtotal'], 0, ',', '.') ?></td>
                </tr>
                <tr class="totals">
                    <td colspan="2" class="sum-label">Dikurangi Potongan Harga</td>
                    <td class="text-right">Rp <?= number_format($data['discount_amount'], 0, ',', '.') ?></td>
                </tr>
                <tr class="totals">
                    <td colspan="2" class="sum-label">Dikurangi Uang Muka</td>
                    <td class="text-right">Rp 0</td>
                </tr>
                <tr class="totals">
                    <td colspan="2" class="sum-label">Dasar Pengenaan Pajak (DPP)</td>
                    <td class="text-right">Rp <?= number_format($dpp, 0, ',', '.') ?></td>
                </tr>
                <tr class="totals">
                    <td colspan="2" class="sum-label">PPN 11% x DPP</td>
                    <td class="text-right">Rp <?= number_format($ppn, 0, ',', '.') ?></td>
                </tr>
                <tr class="totals">
                    <td colspan="2" class="sum-label">PPnBM</td>
                    <td class="text-right">Rp 0</td>
                </tr>
            </tbody>
        </table>

        <table class="signature">
            <tr>
                <td width="40%">
                    <div class="qr-box">
                        <div><strong>QR Tanda Tangan Resmi</strong></div>
                        <img src="<?= clean($qrUrl) ?>" alt="QR Verifikasi" style="width:34mm; height:34mm; margin-top:4px;">
                        <div class="qr-caption">
                            Scan untuk verifikasi keabsahan faktur pajak.<br>
                            Token: <?= clean($signature_token) ?>
                        </div>
                    </div>
                </td>
                <td width="25%">
                    <div class="sig-box">
                        <div><strong>Keterangan:</strong> Dokumen ini dicetak dari sistem internal perusahaan.</div>
                        <div style="margin-top:8px;">Tanggal Invoice: <?= clean($invoice_date) ?></div>
                        <div style="margin-top:6px;">No. FP: <?= clean($tax_invoice_no) ?></div>
                    </div>
                </td>
                <td width="35%">
                    <div class="sig-box">
                        <div class="sig-city"><?= clean($seller_city) ?>, <?= clean($invoice_date) ?></div>
                        <div class="sig-title">PKP Penjual / Kuasa</div>
                        <?= render_sig_img($data['creator_sig'] ?? '') ?>
                        <div class="sig-name"><?= !empty($data['creator_name']) ? clean($data['creator_name']) : '(...................................)' ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="note-bar">
            Pastikan Nomor Seri Faktur Pajak terisi sesuai ketentuan DJP sebelum digunakan sebagai dokumen perpajakan resmi.
        </div>
    </div>
</body>
</html>
