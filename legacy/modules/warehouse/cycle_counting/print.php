<?php
// modules/warehouse/cycle_counting/print.php
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

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('whse_view')) {
    http_response_code(403);
    die('Akses ditolak.');
}
if (!function_exists('mms_is_dev_feature_enabled') || !mms_is_dev_feature_enabled('whse_cycle_counting')) {
    http_response_code(404);
    die('Modul belum diaktifkan.');
}

$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('Error: ID Session tidak valid.');

if (!function_exists('whse_cc_resolve_asset_path')) {
    function whse_cc_resolve_asset_path($path) {
        $path = trim((string)$path);
        if ($path === '') return '';
        if (file_exists($path)) return $path;
        $alt = '../../../' . ltrim($path, '/');
        if (file_exists($alt)) return $alt;
        return '';
    }
}

$stmt_head = $pdo->prepare("SELECT s.*, u.fullname AS created_name, p.fullname AS posted_name
                            FROM cycle_count_sessions s
                            LEFT JOIN users u ON u.id = s.created_by
                            LEFT JOIN users p ON p.id = s.posted_by
                            WHERE s.id = ? LIMIT 1");
$stmt_head->execute([$id]);
$head = $stmt_head->fetch(PDO::FETCH_ASSOC);
if (!$head) die('Session cycle count tidak ditemukan.');

$stmt_line = $pdo->prepare("SELECT l.*, i.item_code, i.item_name, i.unit
                            FROM cycle_count_session_items l
                            JOIN items i ON i.id = l.item_id
                            WHERE l.session_id = ?
                            ORDER BY i.item_name ASC");
$stmt_line->execute([$id]);
$lines = $stmt_line->fetchAll(PDO::FETCH_ASSOC);

$comp = get_company_profile();
$company_name = $comp['company_name'] ?? 'MMS SYSTEM';
$company_addr = $comp['address'] ?? '-';
$logo_path = whse_cc_resolve_asset_path($comp['logo_path'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cycle Counting - <?= $esc($head['session_number']) ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: auto; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-start; }
        .doc-title { font-size: 22px; font-weight: bold; color: #555; letter-spacing: 1px; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta-table td { padding: 2px 4px; vertical-align: top; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .item-table th { background: #f8f9fa; border: 1px solid #000; padding: 7px; font-size: 10px; text-align: left; }
        .item-table td { border: 1px solid #000; padding: 7px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .footer-sig { width: 100%; border-collapse: collapse; margin-top: 18px; table-layout: fixed; }
        .footer-sig th { border: 1px solid #000; background: #f9f9f9; padding: 5px; font-size: 10px; }
        .footer-sig td { border: 1px solid #000; height: 86px; text-align: center; vertical-align: bottom; padding: 6px; font-size: 10px; }
        .sig-name { display: block; font-weight: bold; text-decoration: underline; }
        .sig-note { display: block; font-size: 9px; color: #555; }
        .page-footer { border-top: 1px solid #ccc; padding-top: 10px; text-align: center; margin-top: 16px; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }
        @media print { .no-print { display: none; } .box { border: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="text-align:center; margin-bottom:12px;">
        <button onclick="window.print()" style="padding:8px 14px; cursor:pointer;">Cetak</button>
        <button onclick="window.close()" style="padding:8px 14px; cursor:pointer;">Tutup</button>
    </div>

    <div class="box">
        <div class="doc-content">
            <div class="header">
                <div><?= $logo_path !== '' ? '<img src="' . $esc($logo_path) . '" style="max-height:55px;">' : '' ?></div>
                <div style="text-align:right;">
                    <div class="doc-title">CYCLE COUNTING</div>
                    <div style="font-size:12px; font-weight:bold; margin-top:4px;"><?= $esc($head['session_number']) ?></div>
                </div>
            </div>

            <table class="meta-table">
                <tr>
                    <td width="18%"><strong>Tanggal Count</strong></td>
                    <td width="32%">: <?= date('d/m/Y', strtotime((string)$head['count_date'])) ?></td>
                    <td width="18%"><strong>Status</strong></td>
                    <td width="32%">: <?= strtoupper($esc($head['status'])) ?></td>
                </tr>
                <tr>
                    <td><strong>Area / Zona</strong></td>
                    <td>: <?= $esc($head['count_area'] ?: '-') ?></td>
                    <td><strong>Dibuat Oleh</strong></td>
                    <td>: <?= $esc($head['created_name'] ?: '-') ?></td>
                </tr>
                <tr>
                    <td><strong>Posted Oleh</strong></td>
                    <td>: <?= $esc($head['posted_name'] ?: '-') ?></td>
                    <td><strong>Tgl Post</strong></td>
                    <td>: <?= !empty($head['posted_at']) ? date('d/m/Y H:i', strtotime((string)$head['posted_at'])) : '-' ?></td>
                </tr>
                <tr>
                    <td><strong>Catatan</strong></td>
                    <td colspan="3">: <?= $esc($head['notes'] ?: '-') ?></td>
                </tr>
            </table>

            <table class="item-table">
                <thead>
                    <tr>
                        <th width="4%" class="text-center">No</th>
                        <th width="18%">Item Code</th>
                        <th width="30%">Item Name</th>
                        <th width="12%" class="text-right">System Qty</th>
                        <th width="12%" class="text-right">Counted Qty</th>
                        <th width="12%" class="text-right">Variance</th>
                        <th width="12%">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lines)): ?>
                        <tr><td colspan="7" class="text-center">Tidak ada item pada session ini.</td></tr>
                    <?php else: $no = 1; foreach ($lines as $ln): ?>
                        <?php $var = (float)$ln['variance_qty']; ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><?= $esc($ln['item_code']) ?></td>
                            <td><?= $esc($ln['item_name']) ?></td>
                            <td class="text-right"><?= number_format((float)$ln['system_qty'], 4, ',', '.') . ' ' . $esc($ln['unit']) ?></td>
                            <td class="text-right"><?= number_format((float)$ln['counted_qty'], 4, ',', '.') . ' ' . $esc($ln['unit']) ?></td>
                            <td class="text-right"><?= number_format($var, 4, ',', '.') . ' ' . $esc($ln['unit']) ?></td>
                            <td><?= $esc($ln['reason'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <table class="footer-sig">
                <thead>
                    <tr>
                        <th>Checker</th>
                        <th>Approver</th>
                        <th>Admin Gudang</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <span class="sig-name">....................</span>
                            <span class="sig-note">Pemeriksa Fisik</span>
                        </td>
                        <td>
                            <span class="sig-name">....................</span>
                            <span class="sig-note">Verifikasi Variance</span>
                        </td>
                        <td>
                            <span class="sig-name"><?= !empty($head['posted_name']) ? $esc($head['posted_name']) : '....................' ?></span>
                            <span class="sig-note">Posting Penyesuaian</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="page-footer">
            <span class="footer-comp-name"><?= strtoupper($esc($company_name)) ?></span>
            <span class="footer-addr"><?= $esc($company_addr) ?></span>
        </div>
    </div>
</body>
</html>
