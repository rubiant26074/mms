<?php
// modules/engineering/partlist/print.php
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
if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('eng_view')) {
    die("Akses ditolak.");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Error: ID SPK tidak ditemukan.");
}

$sql = "SELECT s.*, so.so_number, so.cust_po_number,
               c.name AS cust_name, c.address AS cust_addr,
               u1.fullname AS ppic_name, u1.signature_path AS ppic_sig,
               u2.fullname AS eng_name, u2.signature_path AS eng_sig,
               u3.fullname AS mgr_name, u3.signature_path AS mgr_sig,
               u4.fullname AS spv_name, u4.signature_path AS spv_sig
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
$data = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$data) {
    die("Data SPK tidak ditemukan.");
}

$stmt_pl = $pdo->prepare("SELECT * FROM spk_partlists WHERE spk_id = ? ORDER BY id ASC");
$stmt_pl->execute([$id]);
$partlists = $stmt_pl->fetchAll(PDO::FETCH_ASSOC);

$comp = get_company_profile();
$logo_path = $comp['logo_path'] ?? '';

function pl_resolve_asset_path($path) {
    if (empty($path)) return '';
    if (file_exists($path)) return $path;
    $alt = '../../../' . ltrim($path, '/');
    if (file_exists($alt)) return $alt;
    return '';
}

function pl_sig_img($path) {
    $resolved = pl_resolve_asset_path($path);
    if (empty($resolved)) return '<div style="height:50px;"></div>';
    return '<img src="' . clean($resolved) . '" style="height:50px;max-width:120px;object-fit:contain;margin-bottom:-5px;display:block;margin-left:auto;margin-right:auto;" alt="Signature">';
}

$logo_path_final = pl_resolve_asset_path($logo_path);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Arsip Partlist - <?= clean($data['spk_number']) ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { max-width: 800px; margin: auto; position: relative; min-height: 96vh; }

        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; }
        .logo-area { width: 50%; }
        .logo-area img { max-height: 55px; object-fit: contain; }
        .header-right { text-align: right; width: 45%; }
        .doc-title-box { border: 1.5px solid #000; padding: 5px; display: inline-block; text-align: center; min-width: 250px; }
        .doc-title { font-size: 18px; font-weight: bold; letter-spacing: 1px; }

        .info-table { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .info-table td { vertical-align: top; padding: 2px; }
        .info-table .label { width: 115px; display: inline-block; font-weight: bold; }

        .meta-note {
            border: 1px solid #ddd;
            background: #fafafa;
            padding: 6px 8px;
            margin-bottom: 12px;
            font-size: 10px;
        }

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 4px; }
        .data-table th { background: #f2f2f2; font-size: 10px; text-transform: uppercase; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .footer-sig { width: 100%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
        .footer-sig th { border: 1px solid #000; background: #f9f9f9; padding: 5px; font-size: 10px; }
        .footer-sig td { border: 1px solid #000; height: 95px; text-align: center; vertical-align: bottom; padding: 5px; font-size: 10px; }
        .sig-name { font-weight: bold; text-decoration: underline; display: block; }
        .sig-date { font-size: 9px; margin-top: 2px; display: block; }

        .page-footer { position: absolute; bottom: 10px; left: 0; right: 0; text-align: center; border-top: 1px solid #ccc; padding-top: 10px; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }
        .no-print { text-align: center; margin-bottom: 12px; }
        .no-print button { padding: 10px 20px; cursor: pointer; background: #333; color: #fff; border: none; border-radius: 4px; }

        @media print { .no-print { display: none; } .box { border: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print"><button onclick="window.print()">Cetak Arsip Partlist</button></div>
    <div class="box">
        <div class="header">
            <div class="logo-area">
                <?php if (!empty($logo_path_final)): ?>
                    <img src="<?= clean($logo_path_final) ?>" alt="Logo">
                <?php endif; ?>
            </div>
            <div class="header-right">
                <div class="doc-title-box">
                    <div class="doc-title">ARSIP PARTLIST</div>
                    <div style="font-size: 10px;">ENGINEERING DEPARTMENT</div>
                </div>
                <div style="font-size: 13px; font-weight: bold; margin-top: 8px;"><?= clean($data['spk_number']) ?></div>
            </div>
        </div>

        <table class="info-table">
            <tr>
                <td width="55%">
                    <span class="label">Customer</span>: <strong><?= strtoupper(clean($data['cust_name'] ?? '-')) ?></strong><br>
                    <span class="label">Project</span>: <?= clean($data['project_name'] ?? '-') ?><br>
                    <span class="label">Alamat</span>: <?= nl2br(clean($data['cust_addr'] ?? '-')) ?>
                </td>
                <td width="45%">
                    <span class="label">Nomor SO</span>: <?= clean($data['so_number'] ?? '-') ?><br>
                    <span class="label">PO Customer</span>: <?= clean($data['cust_po_number'] ?? '-') ?><br>
                    <span class="label">Tgl SPK</span>: <?= !empty($data['spk_date']) ? date('d/m/Y', strtotime($data['spk_date'])) : '-' ?><br>
                    <span class="label">Deadline</span>: <?= !empty($data['deadline_date']) ? date('d/m/Y', strtotime($data['deadline_date'])) : '-' ?><br>
                    <span class="label">Status</span>: <strong><?= strtoupper(clean($data['status'] ?? '-')) ?></strong>
                </td>
            </tr>
        </table>

        <div class="meta-note">
            <strong>Link Drawing:</strong> <?= !empty($data['drawing_link']) ? clean($data['drawing_link']) : '-' ?>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="12%">No DWG</th>
                    <th>Nama Part</th>
                    <th width="6%">Qty</th>
                    <th width="12%">Material</th>
                    <th width="6%">Tebal</th>
                    <th width="6%">P</th>
                    <th width="6%">L</th>
                    <th width="12%">Proses</th>
                    <th width="13%">Ket</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($partlists)): ?>
                    <tr><td colspan="10" class="text-center" style="padding:12px;">Belum ada data partlist pada SPK ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($partlists as $row): ?>
                        <tr>
                            <td class="text-center"><?= clean($row['item_no'] ?? '') ?></td>
                            <td><?= clean($row['drawing_no'] ?? '') ?></td>
                            <td><?= clean($row['part_name'] ?? '') ?></td>
                            <td class="text-center"><?= (float)($row['qty'] ?? 0) ?></td>
                            <td><?= clean($row['material'] ?? '') ?></td>
                            <td class="text-center"><?= clean($row['thickness'] ?? '') ?></td>
                            <td class="text-center"><?= clean($row['length'] ?? '') ?></td>
                            <td class="text-center"><?= clean($row['width'] ?? '') ?></td>
                            <td><?= clean($row['process'] ?? '') ?></td>
                            <td><?= clean($row['notes'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <table class="footer-sig">
            <thead>
                <tr>
                    <th>Dibuat (PPIC Staff)</th>
                    <th>Teknis (Engineering)</th>
                    <th>Disetujui (Prod Manager)</th>
                    <th>Diterima (Prod Supervisor)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?= pl_sig_img($data['ppic_sig'] ?? '') ?>
                        <span class="sig-name"><?= clean($data['ppic_name'] ?? '....................') ?></span>
                        <span class="sig-date">Tgl: <?= !empty($data['created_at']) ? date('d/m/Y', strtotime($data['created_at'])) : '/ /' ?></span>
                    </td>
                    <td>
                        <?= pl_sig_img($data['eng_sig'] ?? '') ?>
                        <span class="sig-name"><?= clean($data['eng_name'] ?? '....................') ?></span>
                        <span class="sig-date">Tgl: <?= !empty($data['approved_at_eng']) ? date('d/m/Y', strtotime($data['approved_at_eng'])) : '/ /' ?></span>
                    </td>
                    <td>
                        <?= pl_sig_img($data['mgr_sig'] ?? '') ?>
                        <span class="sig-name"><?= clean($data['mgr_name'] ?? '....................') ?></span>
                        <span class="sig-date">Tgl: <?= !empty($data['approved_at_mgr']) ? date('d/m/Y', strtotime($data['approved_at_mgr'])) : '/ /' ?></span>
                    </td>
                    <td>
                        <?= pl_sig_img($data['spv_sig'] ?? '') ?>
                        <span class="sig-name"><?= clean($data['spv_name'] ?? '....................') ?></span>
                        <span class="sig-date">Tgl: <?= !empty($data['approved_at_spv']) ? date('d/m/Y', strtotime($data['approved_at_spv'])) : '/ /' ?></span>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="page-footer">
            <span class="footer-comp-name"><?= strtoupper(clean($comp['company_name'] ?? '')) ?></span>
            <span class="footer-addr"><?= clean($comp['address'] ?? '') ?></span>
        </div>
    </div>
</body>
</html>
