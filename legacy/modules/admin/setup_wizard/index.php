<?php
// modules/admin/setup_wizard/index.php

if ($_SESSION['role'] !== 'admin') {
    render_header("Wizard Setup ERP");
    echo "<div class='alert alert-danger m-4'>Akses ditolak.</div>";
    render_footer();
    exit;
}

function sw_table_exists($pdo, $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function sw_ensure_system_settings_table($pdo) {
    if (sw_table_exists($pdo, 'system_settings')) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(50) NOT NULL,
        setting_value VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function sw_set_flash($type, $message) {
    $_SESSION['sw_flash'] = ['type' => $type, 'message' => $message];
}

function sw_redirect_step($step) {
    header("Location: index.php?page=admin-setup-wizard&step=" . (int)$step);
    exit;
}

function sw_get_setting($pdo, $key, $default = null) {
    if (!sw_table_exists($pdo, 'system_settings')) return $default;
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return ($val === false || $val === null || $val === '') ? $default : $val;
}

function sw_put_setting($pdo, $key, $value) {
    sw_ensure_system_settings_table($pdo);
    $sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$key, $value]);
}

function sw_parse_amount($value) {
    $v = str_replace([' ', ','], ['', ''], (string)$value);
    $v = preg_replace('/[^0-9.\-]/', '', $v);
    if ($v === '' || $v === '-' || $v === '.') return 0.0;
    return (float)$v;
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($step < 1) $step = 1;
if ($step > 3) $step = 3;

$flash = $_SESSION['sw_flash'] ?? null;
unset($_SESSION['sw_flash']);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $current_step = isset($_POST['current_step']) ? (int)$_POST['current_step'] : 1;
        $action = $_POST['wizard_action'] ?? 'next';

        if ($action === 'skip') {
            sw_set_flash('warning', "Langkah " . $current_step . " dilewati.");
            sw_redirect_step(min(3, $current_step + 1));
        }

        if ($action === 'back') {
            sw_redirect_step(max(1, $current_step - 1));
        }

        if ($current_step === 1 && $action === 'next') {
            // Legacy-safe: beberapa instalasi lama punya company_profile bukan di id=1.
            $stmt = $pdo->query("SELECT * FROM company_profile ORDER BY id ASC LIMIT 1");
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) $existing = ['logo_path' => ''];

            $name = clean($_POST['company_name'] ?? '');
            $addr = clean($_POST['address'] ?? '');
            $phone = clean($_POST['phone'] ?? '');
            $email = clean($_POST['email'] ?? '');
            $web = clean($_POST['website'] ?? '');
            $npwp = clean($_POST['npwp'] ?? '');
            $pkp_date = !empty($_POST['pkp_date']) ? $_POST['pkp_date'] : null;

            if ($name === '') {
                throw new Exception("Nama perusahaan wajib diisi.");
            }

            $logo_path = $existing['logo_path'] ?? '';
            if (isset($_FILES['logo']) && is_array($_FILES['logo'])) {
                $logo_err = null;
                $new_logo = mms_store_uploaded_image(
                    $_FILES['logo'],
                    mms_upload_target('company_logo'),
                    'logo',
                    $logo_err,
                    ['jpg', 'jpeg', 'png']
                );
                if ($new_logo === false) {
                    throw new Exception("Gagal upload logo: " . $logo_err);
                } elseif (is_string($new_logo) && $new_logo !== '') {
                    if (!empty($logo_path) && stripos((string)$logo_path, 'http://') !== 0 && stripos((string)$logo_path, 'https://') !== 0) {
                        $old_abs = mms_abs_path(ltrim((string)$logo_path, '/'));
                        if (is_file($old_abs)) {
                            @unlink($old_abs);
                        }
                    }
                    $logo_path = $new_logo;
                }
            }

            $pdo->beginTransaction();
            $exists = $pdo->query("SELECT COUNT(*) FROM company_profile WHERE id = 1")->fetchColumn();
            if ((int)$exists > 0) {
                $sql = "UPDATE company_profile
                        SET company_name=?, address=?, phone=?, email=?, website=?, npwp=?, pkp_date=?, logo_path=?
                        WHERE id=1";
                $pdo->prepare($sql)->execute([$name, $addr, $phone, $email, $web, $npwp, $pkp_date, $logo_path]);
            } else {
                $legacy_id = isset($existing['id']) ? (int)$existing['id'] : 0;
                if ($legacy_id > 0) {
                    $sql = "UPDATE company_profile
                            SET id=1, company_name=?, address=?, phone=?, email=?, website=?, npwp=?, pkp_date=?, logo_path=?
                            WHERE id=?";
                    $pdo->prepare($sql)->execute([$name, $addr, $phone, $email, $web, $npwp, $pkp_date, $logo_path, $legacy_id]);
                } else {
                    $sql = "INSERT INTO company_profile (id, company_name, address, phone, email, website, npwp, pkp_date, logo_path)
                            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $pdo->prepare($sql)->execute([$name, $addr, $phone, $email, $web, $npwp, $pkp_date, $logo_path]);
                }
            }
            sw_put_setting($pdo, 'setup_wizard_step1_done_at', date('Y-m-d H:i:s'));
            $pdo->commit();

            sw_set_flash('success', "Company Profile tersimpan.");
            sw_redirect_step(2);
        }

        if ($current_step === 2 && $action === 'next') {
            if (!sw_table_exists($pdo, 'coa')) {
                throw new Exception("Tabel COA tidak ditemukan.");
            }

            $fiscal_start_month = (int)($_POST['fiscal_year_start_month'] ?? 1);
            if ($fiscal_start_month < 1 || $fiscal_start_month > 12) $fiscal_start_month = 1;
            $currency_code = strtoupper(clean($_POST['currency_code'] ?? 'IDR'));
            $lock_backdate_days = (int)($_POST['lock_backdate_days'] ?? 0);
            if ($lock_backdate_days < 0) $lock_backdate_days = 0;
            $opening_date = !empty($_POST['opening_date']) ? $_POST['opening_date'] : date('Y-m-d');
            $opening_capital_amount = sw_parse_amount($_POST['opening_capital_amount'] ?? 0);
            $opening_capital_coa = clean($_POST['opening_capital_coa'] ?? '3-1001');
            $opening_cash_coa = clean($_POST['opening_cash_coa'] ?? '1-1001');

            $codes = $_POST['coa_code'] ?? [];
            $names = $_POST['coa_name'] ?? [];
            $types = $_POST['coa_type'] ?? [];
            $norms = $_POST['coa_normal'] ?? [];

            $asset_names = $_POST['asset_name'] ?? [];
            $asset_categories = $_POST['asset_category'] ?? [];
            $asset_dates = $_POST['asset_date'] ?? [];
            $asset_costs = $_POST['asset_cost'] ?? [];
            $asset_salvages = $_POST['asset_salvage'] ?? [];
            $asset_lifes = $_POST['asset_life'] ?? [];
            $asset_notes = $_POST['asset_note'] ?? [];
            $asset_sync_mode = clean($_POST['asset_sync_mode'] ?? 'replace');
            if (!in_array($asset_sync_mode, ['replace', 'append'], true)) $asset_sync_mode = 'replace';

            $saved = 0;
            $pdo->beginTransaction();

            for ($i = 0; $i < count($codes); $i++) {
                $code = clean($codes[$i] ?? '');
                $name = clean($names[$i] ?? '');
                $type = clean($types[$i] ?? '');
                $normal = clean($norms[$i] ?? '');
                if ($code === '' || $name === '' || $type === '' || $normal === '') continue;

                $stmt = $pdo->prepare("SELECT id FROM coa WHERE account_code = ? LIMIT 1");
                $stmt->execute([$code]);
                $id_existing = $stmt->fetchColumn();
                if ($id_existing) {
                    $upd = $pdo->prepare("UPDATE coa SET account_name=?, account_type=?, normal_balance=?, is_active=1 WHERE id=?");
                    $upd->execute([$name, $type, $normal, (int)$id_existing]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO coa (account_code, account_name, account_type, normal_balance, opening_balance, current_balance, is_active)
                                          VALUES (?, ?, ?, ?, 0, 0, 1)");
                    $ins->execute([$code, $name, $type, $normal]);
                }
                $saved++;
            }

            sw_put_setting($pdo, 'fiscal_year_start_month', (string)$fiscal_start_month);
            sw_put_setting($pdo, 'base_currency', $currency_code);
            sw_put_setting($pdo, 'lock_backdate_days', (string)$lock_backdate_days);
            sw_put_setting($pdo, 'opening_date', $opening_date);
            sw_put_setting($pdo, 'opening_capital_amount', (string)$opening_capital_amount);
            sw_put_setting($pdo, 'opening_capital_coa', $opening_capital_coa);
            sw_put_setting($pdo, 'opening_cash_coa', $opening_cash_coa);

            if ($opening_capital_amount > 0 && function_exists('get_coa_id') && function_exists('create_journal') && function_exists('delete_journal_by_reference')) {
                $coa_capital_id = get_coa_id($opening_capital_coa);
                $coa_cash_id = get_coa_id($opening_cash_coa);
                if ($coa_capital_id && $coa_cash_id) {
                    delete_journal_by_reference('WIZ-OPEN-CAPITAL', 'general');
                    $items = [
                        ['coa_id' => $coa_cash_id, 'debit' => $opening_capital_amount, 'credit' => 0],
                        ['coa_id' => $coa_capital_id, 'debit' => 0, 'credit' => $opening_capital_amount]
                    ];
                    create_journal($opening_date, 'WIZ-OPEN-CAPITAL', 'Setup Wizard - Modal Awal', $items, 'general');
                }
            }

            $asset_saved = 0;
            if (sw_table_exists($pdo, 'fixed_assets')) {
                if ($asset_sync_mode === 'replace') {
                    $pdo->prepare("DELETE FROM fixed_assets WHERE notes LIKE '[WIZARD_SETUP_ASSET]%' OR notes LIKE '[WIZARD_SETUP_ASSET:%'")->execute();
                }

                for ($i = 0; $i < count($asset_names); $i++) {
                    $an = clean($asset_names[$i] ?? '');
                    $ac = clean($asset_categories[$i] ?? 'equipment');
                    $ad = !empty($asset_dates[$i]) ? $asset_dates[$i] : $opening_date;
                    $cost = sw_parse_amount($asset_costs[$i] ?? 0);
                    $salvage = sw_parse_amount($asset_salvages[$i] ?? 0);
                    $life = (int)($asset_lifes[$i] ?? 4);
                    if ($life <= 0) $life = 4;
                    $note = clean($asset_notes[$i] ?? '');

                    if ($an === '' || $cost <= 0) continue;
                    if (!in_array($ac, ['machinery', 'vehicle', 'building', 'equipment', 'electronic'], true)) {
                        $ac = 'equipment';
                    }

                    $sig_source = strtolower($an . '|' . $ac . '|' . $ad . '|' . number_format($cost, 2, '.', '') . '|' . number_format($salvage, 2, '.', '') . '|' . $life);
                    $sig = substr(sha1($sig_source), 0, 12);
                    $note_prefix = '[WIZARD_SETUP_ASSET:' . $sig . ']';

                    if ($asset_sync_mode === 'append') {
                        $stmt_dup = $pdo->prepare("SELECT id FROM fixed_assets WHERE notes LIKE ? LIMIT 1");
                        $stmt_dup->execute([$note_prefix . '%']);
                        if ($stmt_dup->fetchColumn()) {
                            continue;
                        }
                    }

                    $ym = date('y');
                    $stmt_no = $pdo->query("SELECT COUNT(*) FROM fixed_assets WHERE asset_code LIKE 'FA-$ym-%'");
                    $count = ((int)$stmt_no->fetchColumn()) + 1;
                    $asset_code = "FA-" . $ym . "-" . str_pad((string)$count, 3, '0', STR_PAD_LEFT);

                    $depreciable = $cost - $salvage;
                    $months = $life * 12;
                    $monthly_dep = $months > 0 ? ($depreciable / $months) : 0;
                    $full_note = trim($note_prefix . ' ' . $note);

                    $sql_asset = "INSERT INTO fixed_assets 
                        (asset_code, asset_name, category, acquisition_date, acquisition_cost, salvage_value, useful_life_years, monthly_depreciation, accumulated_depreciation, book_value, status, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'active', ?)";
                    $pdo->prepare($sql_asset)->execute([
                        $asset_code, $an, $ac, $ad, $cost, $salvage, $life, $monthly_dep, $cost, $full_note
                    ]);
                    $asset_saved++;
                }
            }
            sw_put_setting($pdo, 'wizard_asset_sync_mode', $asset_sync_mode);

            sw_put_setting($pdo, 'setup_wizard_step2_done_at', date('Y-m-d H:i:s'));
            $pdo->commit();

            sw_set_flash('success', "Accounting & Fiscal Setup tersimpan. COA: $saved, Aset awal: $asset_saved (mode: $asset_sync_mode).");
            sw_redirect_step(3);
        }

        if ($current_step === 3 && ($action === 'next' || $action === 'finish')) {
            $ppn_rate = (float)($_POST['tax_ppn_rate'] ?? 11);
            $pph23_rate = (float)($_POST['tax_pph23_rate'] ?? 2);
            $pph21_rate = (float)($_POST['tax_pph21_rate'] ?? 5);
            $pphfinal_rate = (float)($_POST['tax_pph_final_rate'] ?? 0.5);
            $efaktur = isset($_POST['enable_efaktur']) ? '1' : '0';
            $tax_invoice_prefix = clean($_POST['tax_invoice_prefix'] ?? 'MMS');
            $vat_out_code = clean($_POST['coa_vat_out_code'] ?? '2-1101');
            $vat_in_code = clean($_POST['coa_vat_in_code'] ?? '1-1301');
            $vat_payable_code = clean($_POST['coa_vat_payable_code'] ?? '2-2001');

            $pdo->beginTransaction();
            sw_put_setting($pdo, 'tax_ppn_rate', (string)$ppn_rate);
            sw_put_setting($pdo, 'tax_pph23_rate', (string)$pph23_rate);
            sw_put_setting($pdo, 'tax_pph21_rate', (string)$pph21_rate);
            sw_put_setting($pdo, 'tax_pph_final_rate', (string)$pphfinal_rate);
            sw_put_setting($pdo, 'tax_enable_efaktur', $efaktur);
            sw_put_setting($pdo, 'tax_invoice_prefix', $tax_invoice_prefix);
            sw_put_setting($pdo, 'tax_coa_vat_out', $vat_out_code);
            sw_put_setting($pdo, 'tax_coa_vat_in', $vat_in_code);
            sw_put_setting($pdo, 'tax_coa_vat_payable', $vat_payable_code);
            sw_put_setting($pdo, 'setup_wizard_step3_done_at', date('Y-m-d H:i:s'));
            sw_put_setting($pdo, 'setup_wizard_completed_at', date('Y-m-d H:i:s'));
            sw_put_setting($pdo, 'setup_wizard_completed_by', (string)($_SESSION['user_id'] ?? 0));
            $pdo->commit();

            sw_set_flash('success', "Tax Configuration tersimpan. Wizard setup selesai.");
            sw_redirect_step(3);
        }
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $flash = ['type' => 'danger', 'message' => $e->getMessage()];
}

$company = $pdo->query("SELECT * FROM company_profile WHERE id=1")->fetch(PDO::FETCH_ASSOC);
if (!$company) {
    $company = [
        'company_name' => '',
        'address' => '',
        'phone' => '',
        'email' => '',
        'website' => '',
        'npwp' => '',
        'pkp_date' => '',
        'logo_path' => ''
    ];
}

$coa_count = sw_table_exists($pdo, 'coa') ? (int)$pdo->query("SELECT COUNT(*) FROM coa")->fetchColumn() : 0;
$fixed_assets_count = sw_table_exists($pdo, 'fixed_assets') ? (int)$pdo->query("SELECT COUNT(*) FROM fixed_assets")->fetchColumn() : 0;
$fiscal_year_start_month = (int)sw_get_setting($pdo, 'fiscal_year_start_month', '1');
$currency_code = sw_get_setting($pdo, 'base_currency', 'IDR');
$lock_backdate_days = (int)sw_get_setting($pdo, 'lock_backdate_days', '0');
$opening_date = sw_get_setting($pdo, 'opening_date', date('Y-m-d'));
$opening_capital_amount = sw_get_setting($pdo, 'opening_capital_amount', '0');
$opening_capital_coa = sw_get_setting($pdo, 'opening_capital_coa', '3-1001');
$opening_cash_coa = sw_get_setting($pdo, 'opening_cash_coa', '1-1001');
$wizard_asset_sync_mode = sw_get_setting($pdo, 'wizard_asset_sync_mode', 'replace');

$tax_ppn_rate = sw_get_setting($pdo, 'tax_ppn_rate', '11');
$tax_pph23_rate = sw_get_setting($pdo, 'tax_pph23_rate', '2');
$tax_pph21_rate = sw_get_setting($pdo, 'tax_pph21_rate', '5');
$tax_pph_final_rate = sw_get_setting($pdo, 'tax_pph_final_rate', '0.5');
$tax_enable_efaktur = sw_get_setting($pdo, 'tax_enable_efaktur', '1');
$tax_invoice_prefix = sw_get_setting($pdo, 'tax_invoice_prefix', 'MMS');
$tax_coa_vat_out = sw_get_setting($pdo, 'tax_coa_vat_out', '2-1101');
$tax_coa_vat_in = sw_get_setting($pdo, 'tax_coa_vat_in', '1-1301');
$tax_coa_vat_payable = sw_get_setting($pdo, 'tax_coa_vat_payable', '2-2001');

$default_coa = [
    ['1-1001', 'Kas', 'asset', 'debit'],
    ['1-1002', 'Bank', 'asset', 'debit'],
    ['1-1101', 'Piutang Usaha', 'asset', 'debit'],
    ['1-1201', 'Persediaan', 'asset', 'debit'],
    ['2-1001', 'Utang Usaha', 'liability', 'credit'],
    ['2-2001', 'Utang Pajak (PPN)', 'liability', 'credit'],
    ['4-1001', 'Penjualan', 'revenue', 'credit'],
    ['5-1001', 'Harga Pokok Penjualan', 'expense', 'debit']
];

$step_done = [
    1 => false,
    2 => false,
    3 => false
];

$step_done[1] = !empty(sw_get_setting($pdo, 'setup_wizard_step1_done_at')) || !empty(trim((string)($company['company_name'] ?? '')));
$step_done[2] = !empty(sw_get_setting($pdo, 'setup_wizard_step2_done_at')) || $coa_count > 0;
$step_done[3] = !empty(sw_get_setting($pdo, 'setup_wizard_step3_done_at')) || !empty(sw_get_setting($pdo, 'tax_ppn_rate'));

$completed_steps = 0;
foreach ($step_done as $is_done) {
    if ($is_done) $completed_steps++;
}
$progress_percent = (int)round(($completed_steps / 3) * 100);
$step_titles = [
    1 => 'Company Profile Setup',
    2 => 'Accounting & Fiscal Setup',
    3 => 'Tax Configuration'
];

render_header("Wizard Setup ERP");
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-8">
            <h3 class="fw-bold mb-1"><i class="bi bi-magic"></i> Wizard Setup ERP (MMS)</h3>
            <p class="text-muted mb-0">Standar Accurate Style: isi step by step, atau pilih <strong>Lewati</strong> untuk lanjut.</p>
        </div>
        <div class="col-md-4 text-md-end mt-2 mt-md-0">
            <a href="index.php?page=dashboard" class="btn btn-outline-secondary"><i class="bi bi-house"></i> Kembali Dashboard</a>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= $flash['type'] ?> shadow-sm"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-bold">Progress Setup</span>
                <span class="badge bg-primary"><?= $progress_percent ?>%</span>
            </div>
            <div class="progress mb-3" role="progressbar" aria-valuenow="<?= $progress_percent ?>" aria-valuemin="0" aria-valuemax="100" style="height:10px;">
                <div class="progress-bar bg-success" style="width: <?= $progress_percent ?>%"></div>
            </div>
            <div class="row g-2 mb-3">
                <?php for ($s = 1; $s <= 3; $s++): ?>
                    <div class="col-md-4">
                        <div class="border rounded p-2 small <?= $step_done[$s] ? 'bg-success-subtle border-success' : 'bg-light' ?>">
                            <strong><?= $s ?>. <?= $step_titles[$s] ?></strong><br>
                            <span class="<?= $step_done[$s] ? 'text-success' : 'text-muted' ?>">
                                <?= $step_done[$s] ? 'Tersimpan' : 'Belum' ?>
                            </span>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?php for ($s = 1; $s <= 3; $s++): ?>
                    <?php
                    if ($s === $step) {
                        $cls = 'btn-primary';
                    } elseif ($step_done[$s]) {
                        $cls = 'btn-success';
                    } else {
                        $cls = 'btn-outline-secondary';
                    }
                    $label = $s . '. ' . ($s === 1 ? 'Company Profile' : ($s === 2 ? 'Accounting & Fiscal' : 'Tax Configuration'));
                    if ($step_done[$s]) $label .= ' ✓';
                    ?>
                    <a href="index.php?page=admin-setup-wizard&step=<?= $s ?>" class="btn btn-sm <?= $cls ?>"><?= $label ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <?php if ($step === 1): ?>
        <form method="POST" enctype="multipart/form-data" class="card shadow-sm">
            <input type="hidden" name="current_step" value="1">
            <div class="card-header bg-primary text-white fw-bold">1. Company Profile Setup (Data Perusahaan)</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nama Perusahaan <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($company['company_name'] ?? '') ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telepon</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($company['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($company['email'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NPWP</label>
                                <input type="text" name="npwp" class="form-control" value="<?= htmlspecialchars($company['npwp'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal PKP</label>
                                <input type="date" name="pkp_date" class="form-control" value="<?= htmlspecialchars($company['pkp_date'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Website</label>
                            <input type="text" name="website" class="form-control" value="<?= htmlspecialchars($company['website'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($company['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 bg-light">
                            <label class="form-label fw-bold d-block">Logo Perusahaan</label>
                            <?php if (!empty($company['logo_path']) && file_exists($company['logo_path'])): ?>
                                <img src="<?= htmlspecialchars($company['logo_path']) ?>" alt="Logo" style="max-width:100%;max-height:120px;" class="mb-2">
                            <?php else: ?>
                                <div class="small text-muted mb-2">Belum ada logo.</div>
                            <?php endif; ?>
                            <input type="file" name="logo" class="form-control form-control-sm" accept="image/png,image/jpeg">
                            <div class="form-text">Opsional, JPG/PNG.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <button type="submit" name="wizard_action" value="skip" class="btn btn-outline-secondary">Lewati</button>
                <button type="submit" name="wizard_action" value="next" class="btn btn-primary">Simpan & Next</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($step === 2): ?>
        <form method="POST" class="card shadow-sm">
            <input type="hidden" name="current_step" value="2">
            <div class="card-header bg-info text-white fw-bold">2. Accounting & Fiscal Setup</div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Bulan Awal Tahun Fiskal</label>
                        <select name="fiscal_year_start_month" class="form-select">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m === $fiscal_year_start_month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1, 2026)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Mata Uang Dasar</label>
                        <input type="text" name="currency_code" class="form-control" value="<?= htmlspecialchars($currency_code) ?>" maxlength="3">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Lock Backdate (Hari)</label>
                        <input type="number" name="lock_backdate_days" class="form-control" value="<?= $lock_backdate_days ?>" min="0">
                    </div>
                </div>
                <div class="border rounded p-3 mb-3 bg-light">
                    <div class="fw-bold mb-2">Modal Awal</div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Tanggal Opening</label>
                            <input type="date" name="opening_date" class="form-control" value="<?= htmlspecialchars($opening_date) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nominal Modal Awal</label>
                            <input type="number" step="0.01" name="opening_capital_amount" class="form-control" value="<?= htmlspecialchars($opening_capital_amount) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Kode COA Modal</label>
                            <input type="text" name="opening_capital_coa" class="form-control" value="<?= htmlspecialchars($opening_capital_coa) ?>" placeholder="3-1001">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Kode COA Kas/Bank</label>
                            <input type="text" name="opening_cash_coa" class="form-control" value="<?= htmlspecialchars($opening_cash_coa) ?>" placeholder="1-1001">
                        </div>
                    </div>
                    <div class="form-text">Jika akun ditemukan, wizard akan membuat ulang jurnal pembukaan dengan referensi `WIZ-OPEN-CAPITAL`.</div>
                </div>
                <div class="alert alert-light border small">Template COA default MMS. Anda masih bisa edit lengkap di menu COA.</div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="140">Kode Akun</th>
                                <th>Nama Akun</th>
                                <th width="130">Tipe</th>
                                <th width="140">Saldo Normal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($default_coa as $acc): ?>
                                <tr>
                                    <td><input type="text" name="coa_code[]" class="form-control" value="<?= htmlspecialchars($acc[0]) ?>"></td>
                                    <td><input type="text" name="coa_name[]" class="form-control" value="<?= htmlspecialchars($acc[1]) ?>"></td>
                                    <td>
                                        <select name="coa_type[]" class="form-select">
                                            <?php foreach (['asset', 'liability', 'equity', 'revenue', 'expense'] as $tp): ?>
                                                <option value="<?= $tp ?>" <?= $tp === $acc[2] ? 'selected' : '' ?>><?= strtoupper($tp) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="coa_normal[]" class="form-select">
                                            <option value="debit" <?= $acc[3] === 'debit' ? 'selected' : '' ?>>DEBIT</option>
                                            <option value="credit" <?= $acc[3] === 'credit' ? 'selected' : '' ?>>CREDIT</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-bold">Aset Awal</div>
                    <span class="badge bg-secondary">Total aset saat ini: <?= $fixed_assets_count ?></span>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Mode Sinkron Aset</label>
                        <select name="asset_sync_mode" class="form-select form-select-sm">
                            <option value="replace" <?= $wizard_asset_sync_mode === 'replace' ? 'selected' : '' ?>>Replace (Recommended)</option>
                            <option value="append" <?= $wizard_asset_sync_mode === 'append' ? 'selected' : '' ?>>Append</option>
                        </select>
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <div class="form-text mb-1">
                            `Replace`: hapus data aset hasil wizard sebelumnya lalu isi ulang. `Append`: tambah baru, skip jika terdeteksi duplikat signature.
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="wizardAssetTable">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Aset</th>
                                <th width="140">Kategori</th>
                                <th width="130">Tgl Perolehan</th>
                                <th width="130">Harga</th>
                                <th width="110">Nilai Sisa</th>
                                <th width="90">Umur (th)</th>
                                <th>Keterangan</th>
                                <th width="60">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($x = 0; $x < 3; $x++): ?>
                                <tr>
                                    <td><input type="text" name="asset_name[]" class="form-control" placeholder="Nama aset"></td>
                                    <td>
                                        <select name="asset_category[]" class="form-select">
                                            <option value="machinery">Machinery</option>
                                            <option value="vehicle">Vehicle</option>
                                            <option value="building">Building</option>
                                            <option value="equipment" selected>Equipment</option>
                                            <option value="electronic">Electronic</option>
                                        </select>
                                    </td>
                                    <td><input type="date" name="asset_date[]" class="form-control" value="<?= htmlspecialchars($opening_date) ?>"></td>
                                    <td><input type="number" step="0.01" name="asset_cost[]" class="form-control text-end" value="0"></td>
                                    <td><input type="number" step="0.01" name="asset_salvage[]" class="form-control text-end" value="0"></td>
                                    <td><input type="number" name="asset_life[]" class="form-control text-end" value="4" min="1"></td>
                                    <td><input type="text" name="asset_note[]" class="form-control" placeholder="Catatan"></td>
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">X</button></td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="swAddAssetRow()">+ Tambah Aset</button>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <button type="submit" name="wizard_action" value="back" class="btn btn-outline-dark">Back</button>
                <div>
                    <button type="submit" name="wizard_action" value="skip" class="btn btn-outline-secondary">Lewati</button>
                    <button type="submit" name="wizard_action" value="next" class="btn btn-primary">Simpan & Next</button>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($step === 3): ?>
        <form method="POST" class="card shadow-sm">
            <input type="hidden" name="current_step" value="3">
            <div class="card-header bg-success text-white fw-bold">3. Tax Configuration (Setup Pajak)</div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">PPN (%)</label>
                        <input type="number" step="0.01" name="tax_ppn_rate" class="form-control" value="<?= htmlspecialchars($tax_ppn_rate) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">PPh 23 (%)</label>
                        <input type="number" step="0.01" name="tax_pph23_rate" class="form-control" value="<?= htmlspecialchars($tax_pph23_rate) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">PPh 21 (%)</label>
                        <input type="number" step="0.01" name="tax_pph21_rate" class="form-control" value="<?= htmlspecialchars($tax_pph21_rate) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">PPh Final (%)</label>
                        <input type="number" step="0.01" name="tax_pph_final_rate" class="form-control" value="<?= htmlspecialchars($tax_pph_final_rate) ?>">
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Prefix Nomor Faktur Pajak</label>
                        <input type="text" name="tax_invoice_prefix" class="form-control" value="<?= htmlspecialchars($tax_invoice_prefix) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">COA PPN Keluaran</label>
                        <input type="text" name="coa_vat_out_code" class="form-control" value="<?= htmlspecialchars($tax_coa_vat_out) ?>" placeholder="2-1101">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">COA PPN Masukan</label>
                        <input type="text" name="coa_vat_in_code" class="form-control" value="<?= htmlspecialchars($tax_coa_vat_in) ?>" placeholder="1-1301">
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">COA Utang Pajak PPN</label>
                        <input type="text" name="coa_vat_payable_code" class="form-control" value="<?= htmlspecialchars($tax_coa_vat_payable) ?>" placeholder="2-2001">
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="enableEfaktur" name="enable_efaktur" <?= $tax_enable_efaktur === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="enableEfaktur">Aktifkan e-Faktur & validasi QR</label>
                        </div>
                    </div>
                </div>
                <div class="alert alert-light border small mb-0">
                    Setting ini dipakai sebagai baseline modul Finance/Tax MMS dan bisa disesuaikan kembali kapan pun.
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <button type="submit" name="wizard_action" value="back" class="btn btn-outline-dark">Back</button>
                <button type="submit" name="wizard_action" value="finish" class="btn btn-success">Simpan & Selesai</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
function swAddAssetRow() {
    const tbody = document.querySelector('#wizardAssetTable tbody');
    const openingDateInput = document.querySelector('input[name="opening_date"]');
    const openingDate = openingDateInput ? openingDateInput.value : '';
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="asset_name[]" class="form-control" placeholder="Nama aset"></td>
        <td>
            <select name="asset_category[]" class="form-select">
                <option value="machinery">Machinery</option>
                <option value="vehicle">Vehicle</option>
                <option value="building">Building</option>
                <option value="equipment" selected>Equipment</option>
                <option value="electronic">Electronic</option>
            </select>
        </td>
        <td><input type="date" name="asset_date[]" class="form-control" value="${openingDate}"></td>
        <td><input type="number" step="0.01" name="asset_cost[]" class="form-control text-end" value="0"></td>
        <td><input type="number" step="0.01" name="asset_salvage[]" class="form-control text-end" value="0"></td>
        <td><input type="number" name="asset_life[]" class="form-control text-end" value="4" min="1"></td>
        <td><input type="text" name="asset_note[]" class="form-control" placeholder="Catatan"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">X</button></td>
    `;
    tbody.appendChild(tr);
}
</script>

<?php render_footer(); ?>
