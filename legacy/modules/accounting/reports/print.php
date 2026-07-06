<?php
// modules/accounting/reports/print.php
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

// Defaults (match index)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-01-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['type']) ? $_GET['type'] : 'pl';

$data = [];
$total_revenue = 0;
$total_expense = 0;
$total_asset = 0;
$total_liability = 0;
$total_equity = 0;
$net_income = 0;

if ($report_type == 'pl') {
    $sql = "SELECT c.account_code, c.account_name, c.account_type, 
                   COALESCE(m.sum_debit, 0) as sum_debit,
                   COALESCE(m.sum_credit, 0) as sum_credit,
                   c.opening_balance,
                   c.normal_balance
            FROM coa c
            LEFT JOIN (
                SELECT ji.coa_id,
                       SUM(ji.debit) as sum_debit,
                       SUM(ji.credit) as sum_credit
                FROM journal_items ji
                JOIN journals j ON ji.journal_id = j.id
                WHERE j.journal_date BETWEEN ? AND ?
                GROUP BY ji.coa_id
            ) m ON m.coa_id = c.id
            WHERE c.account_type IN ('revenue', 'expense')
            ORDER BY c.account_code ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        $sum_debit = (float)($r['sum_debit'] ?? 0);
        $sum_credit = (float)($r['sum_credit'] ?? 0);
        $opening = (float)($r['opening_balance'] ?? 0);
        $normal = $r['normal_balance'] ?? 'debit';
        $movement = ($normal === 'credit') ? ($sum_credit - $sum_debit) : ($sum_debit - $sum_credit);
        $balance = $opening + $movement;
        if ($r['account_type'] == 'revenue') {
            $r['balance_rev'] = $balance;
            $data['revenue'][] = $r;
            $total_revenue += $r['balance_rev'];
        } else {
            $r['balance_exp'] = $balance;
            $data['expense'][] = $r;
            $total_expense += $r['balance_exp'];
        }
    }
    $net_income = $total_revenue - $total_expense;
} else {
    $sql = "SELECT c.account_code, c.account_name, c.account_type, 
                   COALESCE(m.sum_debit, 0) as sum_debit,
                   COALESCE(m.sum_credit, 0) as sum_credit,
                   c.opening_balance,
                   c.normal_balance
            FROM coa c
            LEFT JOIN (
                SELECT ji.coa_id,
                       SUM(ji.debit) as sum_debit,
                       SUM(ji.credit) as sum_credit
                FROM journal_items ji
                JOIN journals j ON ji.journal_id = j.id
                WHERE j.journal_date <= ?
                GROUP BY ji.coa_id
            ) m ON m.coa_id = c.id
            WHERE c.account_type IN ('asset', 'liability', 'equity')
            ORDER BY c.account_code ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$end_date]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        $sum_debit = (float)($r['sum_debit'] ?? 0);
        $sum_credit = (float)($r['sum_credit'] ?? 0);
        $opening = (float)($r['opening_balance'] ?? 0);
        $normal = $r['normal_balance'] ?? 'debit';
        $movement = ($normal === 'credit') ? ($sum_credit - $sum_debit) : ($sum_debit - $sum_credit);
        $balance = $opening + $movement;
        if ($r['account_type'] == 'asset') {
            $r['balance_asset'] = $balance;
            $data['asset'][] = $r;
            $total_asset += $r['balance_asset'];
        } elseif ($r['account_type'] == 'liability') {
            $r['balance_passiva'] = $balance;
            $data['liability'][] = $r;
            $total_liability += $r['balance_passiva'];
        } else {
            $r['balance_passiva'] = $balance;
            $data['equity'][] = $r;
            $total_equity += $r['balance_passiva'];
        }
    }

    $sql_re = "SELECT c.normal_balance, c.opening_balance,
                      COALESCE(m.sum_debit, 0) as sum_debit,
                      COALESCE(m.sum_credit, 0) as sum_credit
               FROM coa c
               LEFT JOIN (
                   SELECT ji.coa_id,
                          SUM(ji.debit) as sum_debit,
                          SUM(ji.credit) as sum_credit
                   FROM journal_items ji
                   JOIN journals j ON ji.journal_id = j.id
                   WHERE j.journal_date <= ?
                   GROUP BY ji.coa_id
               ) m ON m.coa_id = c.id
               WHERE c.account_type IN ('revenue', 'expense')";
    $stmt_re = $pdo->prepare($sql_re);
    $stmt_re->execute([$end_date]);
    $retained_earnings = 0;
    foreach ($stmt_re->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sum_debit = (float)($row['sum_debit'] ?? 0);
        $sum_credit = (float)($row['sum_credit'] ?? 0);
        $opening = (float)($row['opening_balance'] ?? 0);
        $normal = $row['normal_balance'] ?? 'debit';
        $movement = ($normal === 'credit') ? ($sum_credit - $sum_debit) : ($sum_debit - $sum_credit);
        $retained_earnings += ($opening + $movement);
    }

    $data['equity'][] = [
        'account_code' => '3-9999',
        'account_name' => 'Laba Tahun Berjalan',
        'balance_passiva' => $retained_earnings
    ];
    $total_equity += $retained_earnings;
}

$comp = get_company_profile();
$doc_title = ($report_type == 'pl') ? 'LAPORAN LABA RUGI' : 'LAPORAN NERACA';

function acc_resolve_asset_path($path) {
    if (empty($path)) return '';
    if (file_exists($path)) return $path;
    $alt = '../../../' . ltrim((string)$path, '/');
    if (file_exists($alt)) return $alt;
    return '';
}

function acc_render_sig_img($path) {
    $resolved = acc_resolve_asset_path($path);
    if (empty($resolved)) {
        return '<div style="height:50px;"></div>';
    }
    return '<img src="' . clean($resolved) . '" alt="Signature" class="sig-image">';
}

$logo_path_final = acc_resolve_asset_path($comp['logo_path'] ?? '');
$company_name = clean($comp['company_name'] ?? 'MMS SYSTEM');
$company_addr = clean($comp['address'] ?? '-');

$prepared_name = (string)($_SESSION['fullname'] ?? ($_SESSION['username'] ?? ''));
$prepared_sig = '';
if (!empty($_SESSION['user_id'])) {
    try {
        $stmt_user = $pdo->prepare("SELECT fullname, signature_path FROM users WHERE id = ?");
        $stmt_user->execute([(int)$_SESSION['user_id']]);
        $u = $stmt_user->fetch(PDO::FETCH_ASSOC);
        if (!empty($u['fullname'])) $prepared_name = (string)$u['fullname'];
        $prepared_sig = (string)($u['signature_path'] ?? '');
    } catch (Exception $e) {
        // fallback ke session
    }
}
if ($prepared_name === '') $prepared_name = '....................';

$approver_name = '';
$approver_sig = '';
try {
    $stmt_appr = $pdo->prepare("SELECT u.fullname, u.signature_path
                                FROM users u
                                LEFT JOIN roles r ON u.role_id = r.id
                                WHERE LOWER(COALESCE(r.role_name, '')) LIKE '%finance%'
                                   OR LOWER(COALESCE(r.role_name, '')) LIKE '%account%'
                                ORDER BY CASE WHEN LOWER(COALESCE(r.role_name, '')) LIKE '%manager%' THEN 0 ELSE 1 END, u.id ASC
                                LIMIT 1");
    $stmt_appr->execute();
    $appr = $stmt_appr->fetch(PDO::FETCH_ASSOC);
    if (!empty($appr['fullname'])) {
        $approver_name = (string)$appr['fullname'];
        $approver_sig = (string)($appr['signature_path'] ?? '');
    }
} catch (Exception $e) {
    // optional
}
if ($approver_name === '') $approver_name = '....................';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= $doc_title ?> - <?= strtoupper($comp['company_name']) ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: auto; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .logo-area { width: 50%; }
        .logo-area img { max-height: 60px; object-fit: contain; }
        .header-right { text-align: right; width: 45%; }
        .doc-title { font-size: 22px; font-weight: bold; color: #555; letter-spacing: 2px; }
        .period { font-size: 12px; color: #333; margin-top: 4px; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .item-table th { background: #f8f9fa; border-bottom: 1px solid #000; border-top: 1px solid #000; padding: 8px; text-align: left; }
        .item-table td { border-bottom: 1px solid #eee; padding: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .section-title { background: #f1f3f5; font-weight: bold; }
        .total-row { font-weight: bold; background: #fafafa; }
        .signature-section { margin-top: 16px; display: flex; justify-content: space-between; text-align: center; gap: 10px; }
        .sig-box { width: 48%; border: 1px solid #000; padding: 8px; min-height: 120px; display: flex; flex-direction: column; justify-content: flex-end; }
        .sig-label { font-weight: bold; margin-bottom: 6px; background: #f8f9fa; padding: 4px; border: 1px solid #ddd; }
        .sig-image { height: 50px; max-width: 120px; object-fit: contain; display: block; margin: 0 auto 5px auto; }
        .sig-name { border-top: 1px solid #000; padding-top: 4px; font-weight: bold; font-size: 10px; }
        .sig-note { font-size: 9px; color: #555; }
        .page-footer { border-top: 1px solid #ccc; padding-top: 10px; text-align: center; margin-top: 16px; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }
        @media print { .no-print { display: none; } .box { border: none; } }
    </style>
    </head>
<body onload="window.print()">
    <div class="box">
        <div class="doc-content">
            <div class="header">
                <div class="logo-area">
                    <?php if (!empty($logo_path_final)): ?>
                        <img src="<?= clean($logo_path_final) ?>" alt="Logo">
                    <?php endif; ?>
                </div>
                <div class="header-right">
                    <div class="doc-title"><?= $doc_title ?></div>
                    <?php if ($report_type == 'pl'): ?>
                        <div class="period">Periode: <?= date('d F Y', strtotime($start_date)) ?> s/d <?= date('d F Y', strtotime($end_date)) ?></div>
                    <?php else: ?>
                        <div class="period">Per Tanggal: <?= date('d F Y', strtotime($end_date)) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($report_type == 'pl'): ?>
                <table class="item-table">
                    <thead>
                        <tr>
                            <th>Akun</th>
                            <th width="25%" class="text-right">Jumlah (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="section-title"><td colspan="2">PENDAPATAN (REVENUE)</td></tr>
                        <?php if (!empty($data['revenue'])): foreach ($data['revenue'] as $r): ?>
                            <tr>
                                <td><?= $r['account_code'] ?> - <?= $r['account_name'] ?></td>
                                <td class="text-right"><?= number_format($r['balance_rev'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="2" class="text-muted">Belum ada pendapatan.</td></tr>
                        <?php endif; ?>
                        <tr class="total-row">
                            <td class="text-right">Total Pendapatan</td>
                            <td class="text-right"><?= number_format($total_revenue, 0, ',', '.') ?></td>
                        </tr>

                        <tr class="section-title"><td colspan="2">BEBAN (EXPENSES)</td></tr>
                        <?php if (!empty($data['expense'])): foreach ($data['expense'] as $r): ?>
                            <tr>
                                <td><?= $r['account_code'] ?> - <?= $r['account_name'] ?></td>
                                <td class="text-right"><?= number_format($r['balance_exp'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="2" class="text-muted">Belum ada beban.</td></tr>
                        <?php endif; ?>
                        <tr class="total-row">
                            <td class="text-right">Total Beban</td>
                            <td class="text-right"><?= number_format($total_expense, 0, ',', '.') ?></td>
                        </tr>

                        <tr class="total-row">
                            <td class="text-right">LABA / (RUGI) BERSIH</td>
                            <td class="text-right"><?= number_format($net_income, 0, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <table class="item-table">
                    <thead>
                        <tr>
                            <th>AKTIVA (ASSETS)</th>
                            <th width="25%" class="text-right">Jumlah (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data['asset'])): foreach ($data['asset'] as $r): ?>
                            <tr>
                                <td><?= $r['account_code'] ?> - <?= $r['account_name'] ?></td>
                                <td class="text-right"><?= number_format($r['balance_asset'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        <tr class="total-row">
                            <td class="text-right">TOTAL ASSET</td>
                            <td class="text-right"><?= number_format($total_asset, 0, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>

                <table class="item-table">
                    <thead>
                        <tr>
                            <th>KEWAJIBAN (LIABILITY)</th>
                            <th width="25%" class="text-right">Jumlah (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data['liability'])): foreach ($data['liability'] as $r): ?>
                            <tr>
                                <td><?= $r['account_code'] ?> - <?= $r['account_name'] ?></td>
                                <td class="text-right"><?= number_format($r['balance_passiva'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        <tr class="total-row">
                            <td class="text-right">Total Kewajiban</td>
                            <td class="text-right"><?= number_format($total_liability, 0, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>

                <table class="item-table">
                    <thead>
                        <tr>
                            <th>MODAL (EQUITY)</th>
                            <th width="25%" class="text-right">Jumlah (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data['equity'])): foreach ($data['equity'] as $r): ?>
                            <tr>
                                <td><?= $r['account_code'] ?> - <?= $r['account_name'] ?></td>
                                <td class="text-right"><?= number_format($r['balance_passiva'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        <tr class="total-row">
                            <td class="text-right">Total Modal</td>
                            <td class="text-right"><?= number_format($total_equity, 0, ',', '.') ?></td>
                        </tr>
                        <tr class="total-row">
                            <td class="text-right">TOTAL PASIVA</td>
                            <td class="text-right"><?= number_format($total_liability + $total_equity, 0, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="signature-section">
                <div class="sig-box">
                    <div class="sig-label">Disusun Oleh</div>
                    <?= acc_render_sig_img($prepared_sig) ?>
                    <div class="sig-name"><?= clean($prepared_name) ?></div>
                    <div class="sig-note">Accounting Staff</div>
                </div>
                <div class="sig-box">
                    <div class="sig-label">Mengetahui</div>
                    <?= acc_render_sig_img($approver_sig) ?>
                    <div class="sig-name"><?= clean($approver_name) ?></div>
                    <div class="sig-note">Finance / Accounting</div>
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
