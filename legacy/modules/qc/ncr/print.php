<?php
// modules/qc/ncr/print.php

if (!function_exists('get_company_profile')) {
    if (file_exists('../../../config/database.php')) {
        require_once '../../../config/database.php';
        require_once '../../../config/functions.php';
    } elseif (file_exists('config/database.php')) {
        require_once 'config/database.php';
        require_once 'config/functions.php';
    } else { die("Error config."); }
}

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('qc_ncr_view')) {
    http_response_code(403);
    die('Akses ditolak.');
}

if (!isset($_GET['id'])) die("Error: ID tidak ditemukan.");
$id = (int)$_GET['id'];
if ($id <= 0) die("Error: ID tidak valid.");

// 1. QUERY DATA NCR
$sql = "SELECT ncr.*, 
               i.item_code, i.item_name, i.unit,
               u_create.fullname as creator, u_create.signature_path as creator_sig,
               u_app.fullname as approver, u_app.signature_path as approver_sig,
               u_gm.fullname as gm_name, u_gm.signature_path as gm_sig,
               u_op.fullname as operator_name,
               u_resp.fullname as resp_name, u_resp.signature_path as resp_sig
        FROM ncr
        JOIN items i ON ncr.item_id = i.id
        LEFT JOIN users u_create ON ncr.created_by = u_create.id
        LEFT JOIN users u_app ON ncr.approved_by = u_app.id
        LEFT JOIN users u_gm ON ncr.gm_approved_by = u_gm.id
        LEFT JOIN users u_op ON ncr.operator_id = u_op.id
        LEFT JOIN users u_resp ON ncr.resp_signed_by = u_resp.id
        WHERE ncr.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) die("Data NCR tidak ditemukan.");

// 2. IDENTITAS PERUSAHAAN
$comp = get_company_profile();
$company_name = isset($comp['company_name']) ? $comp['company_name'] : 'PT. MANUFAKTUR SEJAHTERA';
$logo_path = isset($comp['logo_path']) ? $comp['logo_path'] : '';
$logo_html = (!empty($logo_path) && file_exists($logo_path)) ? '<img src="'.$logo_path.'" alt="Logo" style="max-height: 50px; margin-right: 15px;">' : '';

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
        return '<div style="height:60px;"></div>';
    }
    return '<img src="' . clean($resolved) . '" alt="Signature" class="sig-image">';
}

$logo_path_final = resolve_asset_path($logo_path);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>NCR - <?= $data['ncr_number'] ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: auto; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header-left { display: flex; align-items: center; gap: 10px; }
        .header-left h1 { margin: 0; font-size: 18px; text-transform: uppercase; line-height: 1.2; }
        .doc-title { font-size: 24px; font-weight: bold; color: #555; letter-spacing: 2px; }
        .doc-number { margin-top: 5px; font-size: 14px; font-weight: bold; color: #333; text-align: right; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .info-table td { padding: 4px; vertical-align: top; }
        .label { font-weight: bold; width: 130px; }

        .box-title { background: #333; color: #fff; padding: 3px 10px; font-weight: bold; display: inline-block; margin-bottom: 6px; font-size: 10px; }
        .analysis-box { border: 1px solid #000; padding: 10px; margin-bottom: 16px; min-height: 70px; }

        .disposition-table { width: 100%; border: 1px solid #000; margin-bottom: 16px; text-align: center; }
        .disposition-table td { border: 1px solid #000; padding: 10px; width: 25%; }
        .selected-disp { background-color: #ddd; font-weight: bold; border: 2px solid #000; }

        .signature-section { margin-top: 20px; display: flex; justify-content: space-between; text-align: center; gap: 10px; }
        .sig-box { width: 24%; border: 1px solid #000; padding: 6px; min-height: 120px; display: flex; flex-direction: column; justify-content: flex-end; }
        .sig-head { font-weight: bold; margin-bottom: 6px; background: #f0f0f0; padding: 4px; }
        .sig-image { height: 60px; max-width: 120px; object-fit: contain; display: block; margin: 0 auto 6px auto; }
        .sig-name { font-weight: bold; border-top: 1px solid #000; padding-top: 4px; font-size: 10px; }
        .sig-note { font-size: 9px; color: #555; }

        .page-footer { border-top: 1px solid #ccc; padding-top: 10px; text-align: center; margin-top: 20px; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }

        @media print { .no-print { display: none; } .box { border: none; } }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="text-align: center; margin-bottom: 15px;">
        <button onclick="window.print()" style="padding: 8px 15px; cursor: pointer; background: #333; color: #fff; border:none;">Cetak NCR</button>
        <button onclick="window.close()" style="padding: 8px 15px; cursor: pointer; background: #ccc; border:none;">Tutup</button>
    </div>

    <div class="box">
        <div class="doc-content">
        <!-- HEADER -->
        <div class="header">
            <div class="header-left">
                <?php if (!empty($logo_path_final)): ?>
                    <img src="<?= clean($logo_path_final) ?>" alt="Logo" style="max-height: 60px;">
                <?php endif; ?>
                <div>
                    <h1><?= clean($company_name) ?></h1>
                    <small>QUALITY ASSURANCE DEPARTMENT</small>
                </div>
            </div>
            <div class="header-right">
                <div class="doc-title">NCR REPORT</div>
                <div class="doc-number"><?= clean($data['ncr_number']) ?></div>
            </div>
        </div>

        <!-- INFO -->
        <table class="info-table">
            <tr>
                <td class="label">Tanggal Laporan</td>
                <td>: <?= date('d F Y', strtotime($data['created_at'])) ?></td>
                <td class="label">Sumber Masalah</td>
                <td>: <?= strtoupper($data['source_type']) ?></td>
            </tr>
            <tr>
                <td class="label">Kode Barang</td>
                <td>: <strong><?= clean($data['item_code']) ?></strong></td>
                <td class="label">Nama Barang</td>
                <td>: <?= clean($data['item_name']) ?></td>
            </tr>
            <tr>
                <td class="label">Jumlah Reject</td>
                <td>: <strong style="color:red; font-size:14px;"><?= $data['qty_reject'] + 0 ?> <?= $data['unit'] ?></strong></td>
                <td class="label">Penanggung Jawab</td>
                <td>: <?= !empty($data['operator_name']) ? $data['operator_name'] : '-' ?></td>
            </tr>
        </table>

        <!-- DESKRIPSI -->
        <div class="box-title">DESKRIPSI KETIDAKSESUAIAN (PROBLEM)</div>
        <div class="analysis-box">
            <?= nl2br(clean($data['issue_description'])) ?>
        </div>

        <!-- ANALISA -->
        <div class="box-title">AKAR PENYEBAB (ROOT CAUSE)</div>
        <div class="analysis-box">
            <?= !empty($data['root_cause']) ? nl2br(clean($data['root_cause'])) : "<em>Belum dianalisa</em>" ?>
        </div>

        <!-- TINDAKAN -->
        <div class="box-title">TINDAKAN PERBAIKAN (CORRECTIVE ACTION)</div>
        <div class="analysis-box">
            <?= !empty($data['corrective_action']) ? nl2br(clean($data['corrective_action'])) : "<em>Belum ditentukan</em>" ?>
        </div>

        <!-- DISPOSISI -->
        <div class="box-title">KEPUTUSAN / DISPOSISI</div>
        <table class="disposition-table">
            <tr>
                <td class="<?= $data['disposition']=='pending'?'selected-disp':'' ?>">PENDING</td>
                <td class="<?= $data['disposition']=='repair'?'selected-disp':'' ?>">REPAIR (Perbaiki)</td>
                <td class="<?= $data['disposition']=='scrap'?'selected-disp':'' ?>">SCRAP (Buang)</td>
                <td class="<?= $data['disposition']=='return_to_vendor'?'selected-disp':'' ?>">RETURN TO VENDOR</td>
            </tr>
        </table>

        <!-- TTD -->
        <div class="signature-section">
            <div class="sig-box">
                <div class="sig-head">Dibuat Oleh (QC/Prod)</div>
                <?= render_sig_img($data['creator_sig'] ?? '') ?>
                <div class="sig-name"><?= !empty($data['creator']) ? clean($data['creator']) : '....................' ?></div>
                <div class="sig-note">Inspector</div>
            </div>
            <div class="sig-box">
                <div class="sig-head">Penanggung Jawab</div>
                <?php if (!empty($data['resp_sig'])): ?>
                    <?= render_sig_img($data['resp_sig']) ?>
                <?php else: ?>
                    <div style="height:60px;"></div>
                <?php endif; ?>
                <div class="sig-name"><?= !empty($data['resp_name']) ? clean($data['resp_name']) : '....................' ?></div>
                <div class="sig-note"><?= !empty($data['resp_signed_at']) ? date('d/m/Y', strtotime($data['resp_signed_at'])) : 'Menunggu Tanda Tangan' ?></div>
            </div>
            <div class="sig-box">
                <div class="sig-head">Disetujui (GM)</div>
                <?php if (in_array($data['status'], ['approved','closed']) && !empty($data['gm_sig'])): ?>
                    <?= render_sig_img($data['gm_sig']) ?>
                <?php else: ?>
                    <div style="height:60px;"></div>
                <?php endif; ?>
                <div class="sig-name"><?= !empty($data['gm_name']) ? clean($data['gm_name']) : '....................' ?></div>
                <div class="sig-note"><?= !empty($data['gm_approved_at']) ? date('d/m/Y', strtotime($data['gm_approved_at'])) : 'Menunggu Approval' ?></div>
            </div>
            <div class="sig-box">
                <div class="sig-head">Diverifikasi (QA)</div>
                <div style="height:60px;"></div>
                <div class="sig-name">....................</div>
                <div class="sig-note">QA</div>
            </div>
        </div>
        </div>

        <div class="page-footer">
            <span class="footer-comp-name"><?= strtoupper(clean($company_name)) ?></span>
            <span class="footer-addr"><?= clean($comp['address'] ?? '-') ?></span>
        </div>
    </div>
</body>
</html>
