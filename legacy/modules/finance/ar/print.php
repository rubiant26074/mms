<?php
// modules/finance/ar/print.php

// Load Config
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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) die("Error: ID tidak ditemukan.");
$id = (int) $_GET['id'];

// 1. QUERY HEADER INVOICE
$sql = "SELECT inv.*, 
               c.name as cust_name, c.address as cust_addr, c.pic as cust_pic, c.phone as cust_phone, c.tax_id as cust_npwp,
               u.fullname as creator, u.signature_path as creator_sig,
               dn.dn_number, so.cust_po_number,
               u_mgr.fullname as mgr_name, u_mgr.signature_path as mgr_sig
        FROM invoices inv
        JOIN customers c ON inv.customer_id = c.id
        JOIN users u ON inv.created_by = u.id
        LEFT JOIN delivery_notes dn ON inv.delivery_note_id = dn.id
        LEFT JOIN sales_orders so ON dn.sales_order_id = so.id
        LEFT JOIN users u_mgr ON so.approved_by = u_mgr.id
        WHERE inv.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) die("Data Invoice tidak ditemukan.");

// 2. QUERY ITEMS (Ambil dari Delivery Note Items yang terkait)
// Perlu join ke SO Items untuk dapat harga
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

// 3. IDENTITAS PERUSAHAAN
$comp = get_company_profile();
$company_name = isset($comp['company_name']) && trim((string)$comp['company_name']) !== '' ? $comp['company_name'] : 'MMS System';
$company_addr = isset($comp['address']) && trim((string)$comp['address']) !== '' ? $comp['address'] : '-';
$company_phone = isset($comp['phone']) && trim((string)$comp['phone']) !== '' ? $comp['phone'] : '-';
$company_email = isset($comp['email']) && trim((string)$comp['email']) !== '' ? $comp['email'] : '-';
$company_website = isset($comp['website']) && trim((string)$comp['website']) !== '' ? $comp['website'] : '-';
$company_logo = isset($comp['logo_path']) ? $comp['logo_path'] : '';

function resolve_asset_path($path) {
    if (empty($path)) return '';
    if (file_exists($path)) return $path;
    $alt = '../../../' . ltrim($path, '/');
    if (file_exists($alt)) return $alt;
    return '';
}

function get_sig_img($path) {
    $resolved = resolve_asset_path($path);
    if (empty($resolved)) {
        return '<div style="height: 50px;"></div>';
    }
    return '<img src="' . clean($resolved) . '" style="height: 50px; max-width: 120px; object-fit: contain; display: block; margin: 0 auto; margin-bottom: -4px;">';
}
$logo_path_final = resolve_asset_path($company_logo);

// Status Lunas/Belum
$status_label = "";
if ($data['status'] == 'paid') {
    $status_label = '<div class="stamp is-paid">LUNAS / PAID</div>';
} elseif ($data['status'] == 'cancelled') {
    $status_label = '<div class="stamp is-cancelled">BATAL</div>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice - <?= $data['invoice_number'] ?></title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 0; position: relative; }
        
        .invoice-box {
            max-width: 800px; margin: auto; border: 1px solid #ccc; padding: 24px 28px 40px;
            position: relative; overflow: hidden; background: #fff;
        }

        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: flex-start; }
        .logo-area { width: 50%; }
        .logo-area img { max-height: 60px; object-fit: contain; }
        .company-name { margin-top: 4px; font-size: 15px; font-weight: bold; letter-spacing: 0.3px; text-transform: uppercase; color: #2c3e50; }
        .company-meta { margin: 2px 0; font-size: 10px; color: #555; line-height: 1.3; }
        .header-right { text-align: right; width: 45%; }
        .doc-title-box { border: 1.5px solid #000; padding: 6px 8px; display: inline-block; min-width: 230px; text-align: center; }
        .doc-title { font-size: 18px; font-weight: bold; color: #2c3e50; letter-spacing: 1px; }
        .doc-subtitle { font-size: 10px; color: #444; }
        .doc-number { margin-top: 8px; font-size: 13px; font-weight: bold; color: #333; }

        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { vertical-align: top; padding: 3px; }
        
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .item-table th { background: #2c3e50; color: #fff; padding: 10px; text-align: left; }
        .item-table td { border-bottom: 1px solid #eee; padding: 10px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .total-table { width: 40%; margin-left: auto; border-collapse: collapse; }
        .total-table td { padding: 5px; }
        .grand-total { font-weight: bold; font-size: 14px; border-top: 2px solid #333; padding-top: 10px; }

        .payment-info { margin-top: 30px; border: 1px solid #ddd; padding: 10px; background: #f9f9f9; width: 50%; float: left; }
        .payment-info h4 { margin: 0 0 5px 0; font-size: 12px; text-decoration: underline; }

        .signature-section { margin-top: 24px; display: flex; justify-content: space-between; text-align: center; clear: both; }
        .sig-box { width: 30%; }
        .sig-line { border-top: 1px solid #000; margin-top: 4px; padding-top: 3px; font-weight: bold; font-size: 10px; }

        .page-footer {
            border-top: 1px solid #ccc;
            padding-top: 10px;
            text-align: center;
            margin-top: 24px;
            break-inside: avoid;
        }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }

        /* Stamp Style */
        .stamp {
            position: absolute; top: 150px; right: 50px;
            font-size: 40px; font-weight: bold; text-transform: uppercase;
            border: 5px solid; padding: 10px 20px; transform: rotate(-15deg);
            opacity: 0.3; pointer-events: none;
        }
        .is-paid { color: green; border-color: green; }
        .is-cancelled { color: red; border-color: red; }

        .signature-section { break-inside: avoid; }
        @media print {
            .no-print { display: none; }
            .invoice-box { border: none; box-shadow: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="text-align: center; margin-bottom: 15px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #333; color: #fff; border:none; border-radius: 4px;">Cetak Invoice</button>
        <button onclick="window.close()" style="padding: 10px 20px; cursor: pointer; background: #ccc; border:none; border-radius: 4px; margin-left: 10px;">Tutup</button>
    </div>

    <div class="invoice-box">
        <?= $status_label ?>

        <div class="header">
            <div class="logo-area">
                <?php if (!empty($logo_path_final)): ?>
                    <img src="<?= clean($logo_path_final) ?>" alt="Logo">
                <?php endif; ?>
                <?php if (!empty($company_name) && false): ?>
                <div class="company-name"><?= clean($company_name) ?></div>
                <?php endif; ?>
                <?php if (false): ?>
                <div class="company-meta">
                    <?= nl2br(clean($company_addr)) ?><br>
                    <?= clean($company_phone) ?> | <?= clean($company_email) ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="header-right">
                <div class="doc-title-box">
                    <div class="doc-title">INVOICE</div>
                    <div class="doc-subtitle">ACCOUNT RECEIVABLE</div>
                </div>
                <div class="doc-number"># <?= clean($data['invoice_number']) ?></div>
            </div>
        </div>

        <table class="info-table">
            <tr>
                <td width="60%">
                    <strong>DITAGIHKAN KEPADA:</strong><br>
                    <strong style="font-size: 14px;"><?= strtoupper($data['cust_name']) ?></strong><br>
                    <?= nl2br($data['cust_addr']) ?><br>
                    UP: <?= $data['cust_pic'] ?><br>
                    NPWP: <?= $data['cust_npwp'] ?? '-' ?>
                </td>
                <td width="40%" style="text-align: right;">
                    <table align="right" width="100%">
                        <tr><td><strong>Tanggal Invoice</strong></td><td>: <?= date('d/m/Y', strtotime($data['invoice_date'])) ?></td></tr>
                        <tr><td><strong>Jatuh Tempo</strong></td><td>: <?= date('d/m/Y', strtotime($data['due_date'])) ?></td></tr>
                        <tr><td><strong>Ref. SJ (Delivery)</strong></td><td>: <?= $data['dn_number'] ?></td></tr>
                        <tr><td><strong>Ref. PO Customer</strong></td><td>: <?= $data['cust_po_number'] ?></td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="item-table">
            <thead>
                <tr>
                    <th width="5%" class="text-center">No</th>
                    <th width="40%">Deskripsi Barang</th>
                    <th width="10%" class="text-center">Qty</th>
                    <th width="15%" class="text-right">Harga Satuan</th>
                    <th width="20%" class="text-right">Total (IDR)</th>
                </tr>
            </thead>
            <tbody>
                <?php $no=1; foreach($items as $item): ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td>
                        <strong><?= $item['item_name'] ?></strong><br>
                        <small style="color:#666;">Kode: <?= $item['item_code'] ?></small>
                    </td>
                    <td class="text-center"><?= $item['qty'] + 0 ?> <?= $item['unit'] ?></td>
                    <td class="text-right"><?= number_format($item['unit_price'], 0, ',', '.') ?></td>
                    <td class="text-right"><?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- FOOTER: Payment Info & Totals -->
        <div style="overflow: hidden;">
            <div class="payment-info">
                <h4>INFORMASI PEMBAYARAN</h4>
                Pembayaran mengikuti instruksi resmi dari bagian Finance.<br><br>
                <strong><?= clean($company_name) ?></strong><br>
                Kontak: <?= clean($company_phone) ?> | <?= clean($company_email) ?><br>
                Website: <?= clean($company_website) ?><br><br>
                <small>* Mohon cantumkan No. Invoice pada berita pembayaran.</small>
            </div>

            <div class="total-section">
                <table class="total-table">
                    <tr>
                        <td align="right">Subtotal :</td>
                        <td align="right">Rp <?= number_format($data['subtotal'], 0, ',', '.') ?></td>
                    </tr>
                    <?php if($data['discount_amount'] > 0): ?>
                    <tr>
                        <td align="right">Diskon :</td>
                        <td align="right" style="color:red;">- Rp <?= number_format($data['discount_amount'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td align="right">PPN (11%) :</td>
                        <td align="right">Rp <?= number_format($data['tax_amount'], 0, ',', '.') ?></td>
                    </tr>
                    <tr style="color: #2c3e50;">
                        <td align="right" class="grand-total">GRAND TOTAL :</td>
                        <td align="right" class="grand-total">Rp <?= number_format($data['grand_total'], 0, ',', '.') ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <?php if(!empty($data['notes'])): ?>
        <div style="margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 10px; font-style: italic;">
            Note: <?= nl2br($data['notes']) ?>
        </div>
        <?php endif; ?>

        <div class="signature-section">
            <div class="sig-box">
                Diterima Oleh,
                <div style="height: 50px;"></div>
                <div class="sig-line">Customer<br><small>(Tanda Tangan & Stempel)</small></div>
            </div>
            <div class="sig-box">
                Mengetahui,
                <?= get_sig_img($data['mgr_sig'] ?? '') ?>
                <div class="sig-line"><?= !empty($data['mgr_name']) ? clean($data['mgr_name']) : 'Manager' ?><br><small>Sales Manager</small></div>
            </div>
            <div class="sig-box">
                Hormat Kami,
                <?= get_sig_img($data['creator_sig'] ?? '') ?>
                <div class="sig-line"><?= clean($data['creator']) ?><br><small>Finance Dept.</small></div>
            </div>
        </div>

        <div class="page-footer">
            <span class="footer-comp-name"><?= strtoupper(clean($company_name)) ?></span>
            <span class="footer-addr"><?= clean($company_addr) ?></span>
        </div>

    </div>
</body>
</html>
