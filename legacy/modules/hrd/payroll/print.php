<?php
// modules/hrd/payroll/print.php
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

$id = $_GET['id'] ?? 0;
$has_created_by = false;
try {
    $has_created_by = $pdo->query("SHOW COLUMNS FROM payrolls LIKE 'created_by'")->rowCount() > 0;
} catch (Exception $e) {
    $has_created_by = false;
}

$sql = "SELECT p.*,
               u.fullname AS employee_name, u.signature_path AS employee_sig,
               r.role_name";
if ($has_created_by) {
    $sql .= ", uc.fullname AS creator_name, uc.signature_path AS creator_sig";
}
$sql .= " FROM payrolls p
          JOIN users u ON p.user_id = u.id
          JOIN roles r ON u.role_id = r.id";
if ($has_created_by) {
    $sql .= " LEFT JOIN users uc ON p.created_by = uc.id";
}
$sql .= " WHERE p.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) die("Data Gaji tidak ditemukan.");

$comp = get_company_profile();

function payroll_resolve_asset_path($path) {
    if (empty($path)) return '';
    if (file_exists($path)) return $path;
    $alt = '../../../' . ltrim((string)$path, '/');
    if (file_exists($alt)) return $alt;
    return '';
}

function payroll_render_sig_img($path) {
    $resolved = payroll_resolve_asset_path($path);
    if (empty($resolved)) {
        return '<div style="height:50px;"></div>';
    }
    return '<img src="' . clean($resolved) . '" alt="Signature" class="sig-image">';
}

$company_name = clean($comp['company_name'] ?? 'MMS SYSTEM');
$company_addr = clean($comp['address'] ?? '-');
$logo_path_final = payroll_resolve_asset_path($comp['logo_path'] ?? '');
$prepared_name = clean($data['creator_name'] ?? 'HRD / Finance');
$receiver_name = clean($data['employee_name'] ?? '-');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji - <?= $data['payroll_code'] ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .slip-box { border: 1px solid #ccc; max-width: 760px; margin: auto; padding: 20px; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; margin-bottom: 18px; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: flex-start; }
        .logo-area { width: 50%; }
        .logo-area img { max-height: 55px; object-fit: contain; }
        .header-right { text-align: right; width: 45%; }
        .doc-title { font-size: 22px; font-weight: bold; color: #555; letter-spacing: 1px; }
        .doc-number { font-size: 13px; font-weight: bold; margin-top: 5px; color: #333; }
        .row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .label { font-weight: bold; }
        .nominal { text-align: right; }
        .divider { border-bottom: 1px dashed #ccc; margin: 10px 0; }
        .total-box { background: #f7f7f7; padding: 10px; font-weight: bold; border-top: 2px solid #000; margin-top: 8px; }
        .signature-section { margin-top: 20px; display: flex; justify-content: space-between; text-align: center; gap: 10px; }
        .sig-box { width: 48%; border: 1px solid #000; padding: 8px; min-height: 118px; display: flex; flex-direction: column; justify-content: flex-end; }
        .sig-label { font-weight: bold; margin-bottom: 6px; background: #f8f9fa; padding: 4px; border: 1px solid #ddd; }
        .sig-image { height: 50px; max-width: 120px; object-fit: contain; display: block; margin: 0 auto 5px auto; }
        .sig-name { border-top: 1px solid #000; padding-top: 4px; font-weight: bold; font-size: 10px; }
        .sig-note { font-size: 9px; color: #555; }
        .page-footer { border-top: 1px solid #ccc; padding-top: 10px; text-align: center; margin-top: 16px; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }
        @media print { .slip-box { border: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="slip-box">
        <div class="doc-content">
            <div class="header">
                <div class="logo-area">
                    <?php if (!empty($logo_path_final)): ?>
                        <img src="<?= clean($logo_path_final) ?>" alt="Logo">
                    <?php endif; ?>
                </div>
                <div class="header-right">
                    <div class="doc-title">SLIP GAJI</div>
                    <div class="doc-number"><?= clean($data['payroll_code']) ?></div>
                    <small>Periode: <?= date('d M Y', strtotime($data['period_start'])) ?> - <?= date('d M Y', strtotime($data['period_end'])) ?></small>
                </div>
            </div>

            <div class="row"><span class="label">Nama:</span> <span><?= clean($data['employee_name']) ?></span></div>
            <div class="row"><span class="label">Jabatan:</span> <span><?= clean($data['role_name']) ?></span></div>
            <div class="row"><span class="label">Kehadiran:</span> <span><?= (int)$data['total_attendance'] ?> Hari</span></div>

            <div class="divider"></div>

            <div class="row"><span class="label">Gaji Pokok:</span> <span class="nominal">Rp <?= number_format($data['basic_salary'],0,',','.') ?></span></div>
            <div class="row"><span class="label">Tunjangan:</span> <span class="nominal">Rp <?= number_format($data['allowance_total'],0,',','.') ?></span></div>
            <div class="row"><span class="label">Potongan:</span> <span class="nominal">(Rp <?= number_format($data['deduction_total'],0,',','.') ?>)</span></div>

            <div class="total-box row">
                <span>TAKE HOME PAY:</span>
                <span>Rp <?= number_format($data['net_salary'],0,',','.') ?></span>
            </div>

            <?php if (!empty($data['notes'])): ?>
                <div style="margin-top:10px; padding:8px; border:1px solid #eee; background:#fafafa;">
                    <strong>Catatan:</strong><br>
                    <?= nl2br(clean($data['notes'])) ?>
                </div>
            <?php endif; ?>

            <div class="signature-section">
                <div class="sig-box">
                    <div class="sig-label">Diterima Oleh</div>
                    <?= payroll_render_sig_img($data['employee_sig'] ?? '') ?>
                    <div class="sig-name"><?= $receiver_name ?: '....................' ?></div>
                    <div class="sig-note">Karyawan</div>
                </div>
                <div class="sig-box">
                    <div class="sig-label">Dibuat Oleh</div>
                    <?= payroll_render_sig_img($data['creator_sig'] ?? '') ?>
                    <div class="sig-name"><?= $prepared_name ?: '....................' ?></div>
                    <div class="sig-note">HRD / Finance</div>
                </div>
            </div>
        </div>

        <div class="page-footer">
            <span class="footer-comp-name"><?= strtoupper($company_name) ?></span>
            <span class="footer-addr"><?= $company_addr ?></span>
        </div>
    </div>
</body>
</html>
