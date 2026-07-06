<?php
// modules/finance/tax/print.php

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

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('fin_ar_view')) {
    die("Akses ditolak.");
}

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('m');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');

$tax_table_exists = false;
try {
    $tax_table_exists = $pdo->query("SHOW TABLES LIKE 'tax_payments'")->rowCount() > 0;
} catch (Exception $e) {
    $tax_table_exists = false;
}

$sql_out = "SELECT SUM(tax_amount) as total_ppn, SUM(subtotal) as dpp
            FROM invoices
            WHERE status != 'cancelled'
            AND MONTH(invoice_date) = ? AND YEAR(invoice_date) = ?";
$stmt_out = $pdo->prepare($sql_out);
$stmt_out->execute([$month, $year]);
$out = $stmt_out->fetch(PDO::FETCH_ASSOC);
$ppn_out = (float)($out['total_ppn'] ?? 0);
$dpp_out = (float)($out['dpp'] ?? 0);

$sql_in = "SELECT SUM(tax_amount) as total_ppn, SUM(subtotal) as dpp
           FROM supplier_bills
           WHERE status != 'cancelled'
           AND MONTH(bill_date) = ? AND YEAR(bill_date) = ?";
$stmt_in = $pdo->prepare($sql_in);
$stmt_in->execute([$month, $year]);
$in = $stmt_in->fetch(PDO::FETCH_ASSOC);
$ppn_in = (float)($in['total_ppn'] ?? 0);
$dpp_in = (float)($in['dpp'] ?? 0);

$ppn_payable = $ppn_out - $ppn_in;
$tax_due = max(0, $ppn_payable);
$tax_paid = 0;
$payment_history = [];

if ($tax_table_exists) {
    $stmt_paid = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM tax_payments WHERE tax_type='ppn' AND period_month=? AND period_year=? AND status='posted'");
    $stmt_paid->execute([$month, $year]);
    $tax_paid = (float)$stmt_paid->fetchColumn();

    $stmt_hist = $pdo->prepare("SELECT tp.*, j.journal_no
                                FROM tax_payments tp
                                LEFT JOIN journals j ON j.id = tp.journal_id
                                WHERE tp.tax_type='ppn' AND tp.period_month=? AND tp.period_year=?
                                ORDER BY tp.payment_date DESC, tp.id DESC");
    $stmt_hist->execute([$month, $year]);
    $payment_history = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
}

$tax_remaining = max(0, $tax_due - $tax_paid);
$status_tax = ($ppn_payable > 0) ? "KURANG BAYAR" : (($ppn_payable < 0) ? "LEBIH BAYAR (RESTITUSI)" : "NIHIL");

$stmt_nsfp = $pdo->prepare("SELECT id, invoice_number, invoice_date, due_date, tax_invoice_number
                            FROM invoices
                            WHERE status != 'cancelled'
                            AND MONTH(invoice_date) = ? AND YEAR(invoice_date) = ?
                            ORDER BY invoice_date DESC, id DESC");
$stmt_nsfp->execute([$month, $year]);
$inv_rows = $stmt_nsfp->fetchAll(PDO::FETCH_ASSOC);

$comp = get_company_profile();
$company_name = $comp['company_name'] ?? 'MMS SYSTEM';
$company_addr = $comp['address'] ?? '-';
$company_logo = $comp['logo_path'] ?? '';

$logo_path_final = '';
if (!empty($company_logo)) {
    if (file_exists($company_logo)) {
        $logo_path_final = $company_logo;
    } else {
        $alt = '../../../' . ltrim((string)$company_logo, '/');
        if (file_exists($alt)) $logo_path_final = $alt;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi PPN - <?= clean(date('F Y', mktime(0, 0, 0, $month, 1, $year))) ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: auto; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header-left img { max-height: 60px; object-fit: contain; }
        .header-right { text-align: right; }
        .doc-title { font-size: 24px; font-weight: bold; color: #555; letter-spacing: 1.2px; }
        .info-table { width: 100%; margin-bottom: 16px; border-collapse: collapse; }
        .info-table td { vertical-align: top; padding: 2px; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .item-table th { background: #f8f9fa; border: 1px solid #000; padding: 8px; text-align: left; font-size: 10px; }
        .item-table td { border: 1px solid #000; padding: 6px; }
        .section-title { font-weight: bold; font-size: 11px; margin: 10px 0 6px; text-decoration: underline; text-transform: uppercase; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .ok { color: #0f5132; font-weight: bold; }
        .bad { color: #b02a37; font-weight: bold; }
        .page-footer { border-top: 1px solid #ccc; padding-top: 10px; text-align: center; margin-top: auto; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }
        @media print { .no-print { display: none; } .box { border: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="box">
        <div class="doc-content">
            <div class="header">
                <div class="header-left">
                    <?php if (!empty($logo_path_final)): ?>
                        <img src="<?= clean($logo_path_final) ?>" alt="Logo">
                    <?php endif; ?>
                </div>
                <div class="header-right">
                    <div class="doc-title">REKAPITULASI PPN</div>
                    <div style="font-size: 14px; font-weight: bold; color: #333; margin-top: 5px;"><?= clean(date('F Y', mktime(0, 0, 0, $month, 1, $year))) ?></div>
                </div>
            </div>

            <table class="info-table">
                <tr>
                    <td width="55%">
                        <strong>Status PPN:</strong> <?= clean($status_tax) ?><br>
                        <strong>Masa Pajak:</strong> <?= clean(date('F Y', mktime(0, 0, 0, $month, 1, $year))) ?><br>
                        <strong>Tabel Pembayaran Pajak:</strong> <?= $tax_table_exists ? 'Tersedia' : 'Belum Tersedia' ?>
                    </td>
                    <td width="45%" align="right">
                        <strong>Tanggal Cetak:</strong> <?= date('d/m/Y H:i') ?><br>
                        <strong>User:</strong> <?= clean($_SESSION['fullname'] ?? ($_SESSION['username'] ?? '-')) ?>
                    </td>
                </tr>
            </table>

            <table class="item-table">
                <thead>
                    <tr>
                        <th>Ringkasan PPN</th>
                        <th class="text-right">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>DPP Keluaran</td><td class="text-right">Rp <?= number_format($dpp_out, 0, ',', '.') ?></td></tr>
                    <tr><td>PPN Keluaran</td><td class="text-right ok">Rp <?= number_format($ppn_out, 0, ',', '.') ?></td></tr>
                    <tr><td>DPP Masukan</td><td class="text-right">Rp <?= number_format($dpp_in, 0, ',', '.') ?></td></tr>
                    <tr><td>PPN Masukan</td><td class="text-right ok">Rp <?= number_format($ppn_in, 0, ',', '.') ?></td></tr>
                    <tr><td>Kewajiban Setor PPN</td><td class="text-right bad">Rp <?= number_format($tax_due, 0, ',', '.') ?></td></tr>
                    <tr><td>Sudah Disetor</td><td class="text-right ok">Rp <?= number_format($tax_paid, 0, ',', '.') ?></td></tr>
                    <tr><td>Sisa Setor</td><td class="text-right bad">Rp <?= number_format($tax_remaining, 0, ',', '.') ?></td></tr>
                </tbody>
            </table>

            <div class="section-title">Riwayat Pembayaran Pajak</div>
            <table class="item-table">
                <thead>
                    <tr>
                        <th width="16%">Tanggal</th>
                        <th width="18%">Metode</th>
                        <th width="24%">Referensi</th>
                        <th width="18%">No. Jurnal</th>
                        <th width="24%" class="text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payment_history)): ?>
                        <tr><td colspan="5" class="text-center">Belum ada pembayaran pajak untuk masa ini.</td></tr>
                    <?php else: foreach($payment_history as $p): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($p['payment_date'])) ?></td>
                            <td><?= clean($p['method']) ?></td>
                            <td><?= !empty($p['reference_no']) ? clean($p['reference_no']) : '-' ?></td>
                            <td><?= !empty($p['journal_no']) ? clean($p['journal_no']) : '-' ?></td>
                            <td class="text-right"><strong>Rp <?= number_format((float)$p['amount'], 0, ',', '.') ?></strong></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <div class="section-title">Monitoring Nomor Seri Faktur Pajak</div>
            <table class="item-table">
                <thead>
                    <tr>
                        <th width="20%">No. Invoice</th>
                        <th width="16%">Tanggal</th>
                        <th width="16%">Jatuh Tempo</th>
                        <th width="30%">No. Seri Faktur Pajak</th>
                        <th width="18%" class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inv_rows)): ?>
                        <tr><td colspan="5" class="text-center">Tidak ada invoice pada periode ini.</td></tr>
                    <?php else: foreach ($inv_rows as $inv): ?>
                        <?php $has_nsfp = !empty($inv['tax_invoice_number']); ?>
                        <tr>
                            <td><strong><?= clean($inv['invoice_number']) ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($inv['due_date'])) ?></td>
                            <td><?= $has_nsfp ? clean($inv['tax_invoice_number']) : 'Belum diisi' ?></td>
                            <td class="text-center <?= $has_nsfp ? 'ok' : 'bad' ?>"><?= $has_nsfp ? 'Lengkap' : 'Belum' ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="page-footer">
            <span class="footer-comp-name"><?= strtoupper(clean($company_name)) ?></span>
            <span class="footer-addr"><?= clean($company_addr) ?></span>
        </div>
    </div>
</body>
</html>

