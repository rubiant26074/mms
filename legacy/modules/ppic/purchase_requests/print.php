<?php
// modules/ppic/purchase_requests/print.php

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

if (!isset($_GET['id'])) die("Error: ID Request tidak ditemukan.");
$id = (int) $_GET['id'];

// 2. DATA HEADER (Ambil Signature Requester & Approver)
$sql = "SELECT pr.*, u.fullname as requester_name, u.signature_path as requester_sig,
               u_app.fullname as approver_name, u_app.signature_path as approver_sig
        FROM purchase_requests pr
        LEFT JOIN users u ON pr.created_by = u.id
        LEFT JOIN users u_app ON pr.approved_by = u_app.id
        WHERE pr.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) die("Data Purchase Request tidak ditemukan.");

// 3. DATA ITEMS
$stmt_items = $pdo->prepare("SELECT pri.*, i.item_name, i.item_code, i.unit 
                             FROM purchase_request_items pri 
                             LEFT JOIN items i ON pri.item_id = i.id 
                             WHERE pri.purchase_request_id = ?");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll();

// Fallback: jika detail PR kosong, coba tarik ulang dari referensi SPK di notes.
if (empty($items) && !empty($data['notes']) && preg_match('/\[REF-SPK:(\d+)\]/', (string)$data['notes'], $m)) {
    $ref_spk_id = (int)$m[1];
    if ($ref_spk_id > 0) {
        $stmt_fallback = $pdo->prepare("SELECT sm.item_id, sm.qty_required AS qty, 
                                               i.item_name, i.item_code, i.unit,
                                               CONCAT('Referensi BOM SPK #', ?) AS notes
                                        FROM spk_materials sm
                                        LEFT JOIN items i ON i.id = sm.item_id
                                        WHERE sm.spk_id = ?");
        $stmt_fallback->execute([$ref_spk_id, $ref_spk_id]);
        $items = $stmt_fallback->fetchAll();
    }
}

$comp = get_company_profile();
$logo_path_final = file_exists($comp['logo_path']) ? $comp['logo_path'] : '../../../' . $comp['logo_path'];

function get_sig_img($path) {
    $rel_path = '../../../' . $path;
    if (!empty($path) && file_exists($rel_path)) {
        return '<img src="'.$rel_path.'" style="height: 55px; max-width: 120px; object-fit: contain; margin-bottom: -8px; display: block; margin-left: auto; margin-right: auto;">';
    }
    return '<div style="height: 55px;"></div>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>PR - <?= $data['pr_number'] ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { max-width: 800px; margin: auto; position: relative; min-height: 96vh; }
        
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-start; }
        .logo-area { width: 50%; }
        .logo-area img { max-height: 60px; object-fit: contain; }
        
        .header-right { text-align: right; width: 45%; }
        .doc-title-box { border: 1.5px solid #000; padding: 5px; display: inline-block; text-align: center; min-width: 250px; }
        .doc-title { font-size: 18px; font-weight: bold; letter-spacing: 1px; }

        .info-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .info-table td { vertical-align: top; padding: 2px; }
        
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 8px; }
        .data-table th { background: #f2f2f2; font-size: 10px; text-transform: uppercase; }

        .footer-sig { width: 100%; border-collapse: collapse; margin-top: 30px; table-layout: fixed; }
        .footer-sig th { border: 1px solid #000; background: #f9f9f9; padding: 5px; font-size: 10px; }
        .footer-sig td { border: 1px solid #000; height: 90px; text-align: center; vertical-align: bottom; padding: 8px; font-size: 10px; }
        .sig-name { font-weight: bold; text-decoration: underline; display: block; }
        .sig-date { font-size: 9px; margin-top: 2px; display: block; }

        .page-footer { position: absolute; bottom: 10px; left: 0; right: 0; text-align: center; border-top: 1px solid #ccc; padding-top: 10px; }
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
                <div class="doc-title-box"><div class="doc-title">PURCHASE REQUEST</div><div style="font-size: 9px; letter-spacing: 1px;">FORM PERMINTAAN PEMBELIAN</div></div>
                <div style="font-size: 13px; font-weight: bold; margin-top: 8px;"><?= $data['pr_number'] ?></div>
            </div>
        </div>

        <table class="info-table">
            <tr>
                <td width="55%"><strong>Requester:</strong><br><strong>PPIC / PRODUCTION</strong><br>Status: <?= strtoupper($data['status']) ?></td>
                <td width="45%" align="right"><strong>Tanggal Request :</strong> <?= !empty($data['pr_date']) ? date('d F Y', strtotime($data['pr_date'])) : '-' ?><br><strong>Keperluan :</strong> <?= !empty($data['notes']) ? nl2br(htmlspecialchars($data['notes'])) : '-' ?><br><strong>Tgl Dibutuhkan :</strong> <?= !empty($data['required_date']) ? date('d F Y', strtotime($data['required_date'])) : '-' ?></td>
            </tr>
        </table>

        <table class="data-table">
            <thead><tr><th width="5%">No</th><th width="15%">Kode Barang</th><th width="45%">Deskripsi Barang</th><th width="10%">Qty</th><th width="10%">Unit</th><th width="15%">Est. Tgl Pakai</th></tr></thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="6" align="center" style="color:#666;">Tidak ada detail item PR.</td></tr>
                <?php else: $n=1; foreach($items as $item): ?>
                <tr><td align="center"><?= $n++ ?></td><td align="center"><?= htmlspecialchars(!empty($item['item_code']) ? $item['item_code'] : ('ITEM-' . (int)$item['item_id'])) ?></td><td><strong><?= htmlspecialchars(!empty($item['item_name']) ? $item['item_name'] : ('Item #' . (int)$item['item_id'])) ?></strong><?php if(!empty($item['notes'])): ?><br><small><i>Ket: <?= htmlspecialchars($item['notes']) ?></i></small><?php endif; ?></td><td align="center"><strong><?= $item['qty'] + 0 ?></strong></td><td align="center"><?= htmlspecialchars(!empty($item['unit']) ? $item['unit'] : '-') ?></td><td align="center"><?= !empty($data['required_date']) ? date('d/m/Y', strtotime($data['required_date'])) : '-' ?></td></tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <table class="footer-sig">
            <thead><tr><th>Dibuat (Requester)</th><th>Disetujui (Plant Manager)</th></tr></thead>
            <tbody>
                <tr>
                    <td><?= get_sig_img($data['requester_sig']) ?><span class="sig-name"><?= $data['requester_name'] ?: 'Staff PPIC' ?></span><span class="sig-date">Tgl: <?= !empty($data['pr_date']) ? date('d/m/Y', strtotime($data['pr_date'])) : '-' ?></span></td>
                    <td><?php if(in_array($data['status'],['approved','processed'])) echo get_sig_img($data['approver_sig']); else echo '<div style="height:55px;"></div>'; ?><span class="sig-name"><?= $data['approver_name'] ?: '....................' ?></span><span class="sig-date">Tgl: &nbsp;&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;&nbsp;/</span></td>
                </tr>
            </tbody>
        </table>

        <div class="page-footer"><span class="footer-comp-name"><?= strtoupper($comp['company_name']) ?></span><span class="footer-addr"><?= $comp['address'] ?></span></div>
    </div>
</body>
</html>
