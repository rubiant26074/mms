<?php
// modules/finance/ar/verify_tax.php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/functions.php';

$inv_id = isset($_GET['inv']) && is_numeric($_GET['inv']) ? (int)$_GET['inv'] : 0;
$token = strtoupper(trim($_GET['token'] ?? ''));

$valid = false;
$message = "Data verifikasi tidak valid.";
$row = null;

if ($inv_id > 0 && $token !== '') {
    $sql = "SELECT inv.invoice_number, inv.tax_invoice_number, inv.invoice_date, inv.grand_total, inv.status,
                   c.name as cust_name, c.tax_id as cust_npwp
            FROM invoices inv
            JOIN customers c ON c.id = inv.customer_id
            WHERE inv.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$inv_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $comp = get_company_profile();
        $company_npwp = $comp['npwp'] ?? '';
        $expected = generate_tax_signature_token(
            $row['invoice_number'] ?? '',
            $row['tax_invoice_number'] ?? '',
            $row['invoice_date'] ?? '',
            (float)($row['grand_total'] ?? 0),
            $row['cust_npwp'] ?? '',
            $company_npwp
        );

        if (hash_equals($expected, $token)) {
            $valid = true;
            $message = "Dokumen FAKTUR PAJAK terverifikasi SAH.";
        } else {
            $message = "Token tidak cocok. Dokumen tidak dapat diverifikasi.";
        }
    } else {
        $message = "Invoice tidak ditemukan.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Faktur Pajak</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 24px; color: #111; }
        .card { max-width: 760px; margin: 0 auto; background: #fff; border: 1px solid #d9dee3; border-radius: 10px; overflow: hidden; }
        .head { padding: 14px 18px; border-bottom: 1px solid #e5e8eb; font-weight: bold; }
        .body { padding: 18px; }
        .status { padding: 12px 14px; border-radius: 8px; margin-bottom: 14px; font-weight: bold; }
        .ok { background: #ecfdf3; color: #0f6d3d; border: 1px solid #b8efcf; }
        .bad { background: #fff1f2; color: #b42318; border: 1px solid #fecdd3; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td { padding: 8px 6px; border-bottom: 1px solid #eef1f4; vertical-align: top; }
        td:first-child { width: 220px; color: #555; }
        .mono { font-family: Consolas, monospace; font-size: 12px; }
        .foot { margin-top: 12px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">Verifikasi Keabsahan Faktur Pajak</div>
        <div class="body">
            <div class="status <?= $valid ? 'ok' : 'bad' ?>"><?= clean($message) ?></div>

            <?php if ($row): ?>
            <table>
                <tr><td>No. Invoice</td><td><strong><?= clean($row['invoice_number']) ?></strong></td></tr>
                <tr><td>No. Seri Faktur Pajak</td><td><strong><?= !empty($row['tax_invoice_number']) ? clean($row['tax_invoice_number']) : '-' ?></strong></td></tr>
                <tr><td>Tanggal Invoice</td><td><?= date('d/m/Y', strtotime($row['invoice_date'])) ?></td></tr>
                <tr><td>Customer</td><td><?= clean($row['cust_name']) ?></td></tr>
                <tr><td>Nilai Dokumen</td><td>Rp <?= number_format((float)$row['grand_total'], 0, ',', '.') ?></td></tr>
                <tr><td>Status Invoice</td><td><?= strtoupper(clean($row['status'])) ?></td></tr>
                <tr><td>Token Scan</td><td class="mono"><?= clean($token) ?></td></tr>
            </table>
            <?php endif; ?>

            <div class="foot">
                Halaman ini adalah hasil verifikasi QR tanda tangan resmi dokumen faktur pajak dari sistem.
            </div>
        </div>
    </div>
</body>
</html>
