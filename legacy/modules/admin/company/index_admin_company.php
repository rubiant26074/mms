<?php
// modules/admin/company/index_admin_company.php
// Versi cPanel (Linux-safe) untuk halaman Identitas Perusahaan

if (!defined('PDO::ATTR_DRIVER_NAME') && !isset($pdo)) {
    die("Akses langsung ditolak.");
}

$logo_rel_dir = mms_upload_target('company_logo');
$logo_abs_dir = mms_abs_path($logo_rel_dir);
$upload_diag = mms_upload_runtime_info($logo_rel_dir);
$upload_diag_avatar = mms_upload_runtime_info('uploads/avatars');
$upload_diag_sig = mms_upload_runtime_info(mms_upload_target('signature'));

// Pastikan kolom tambahan tersedia
try {
    $pdo->exec("ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS running_text TEXT NULL AFTER website");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS fonte_token VARCHAR(255) NULL AFTER running_text");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS fonte_token VARCHAR(255) NULL AFTER website");
    } catch (Exception $e2) {}
}
try {
    $pdo->exec("ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS ui_theme VARCHAR(100) NULL AFTER fonte_token");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE company_profile ADD COLUMN ui_theme VARCHAR(100) NULL AFTER fonte_token");
    } catch (Exception $e2) {}
}

$available_themes = function_exists('mms_get_available_themes')
    ? mms_get_available_themes()
    : ['original' => ['slug' => 'original', 'label' => 'Theme Original']];

// Ambil data saat ini
$stmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1");
$data = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$data) {
    $data = [
        'company_name' => '',
        'address' => '',
        'phone' => '',
        'email' => '',
        'website' => '',
        'running_text' => '',
        'fonte_token' => '',
        'ui_theme' => 'original',
        'logo_path' => ''
    ];
}

$data['ui_theme'] = function_exists('mms_normalize_theme_slug')
    ? mms_normalize_theme_slug($data['ui_theme'] ?? 'original', 'original')
    : 'original';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['company_name'] ?? '');
    $addr = clean($_POST['address'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $web = clean($_POST['website'] ?? '');
    $running_text = trim($_POST['running_text'] ?? '');
    $fonte_token = trim($_POST['fonte_token'] ?? '');
    $ui_theme_input = strtolower(trim((string)($_POST['ui_theme'] ?? 'original')));
    $ui_theme = function_exists('mms_normalize_theme_slug')
        ? mms_normalize_theme_slug($ui_theme_input, 'original')
        : 'original';

    $logo_path = (string)($data['logo_path'] ?? '');

    // Upload logo (universal: XAMPP + cPanel)
    if (isset($_FILES['logo']) && is_array($_FILES['logo'])) {
        $upload_msg = null;
        $new_logo = mms_store_uploaded_image($_FILES['logo'], $logo_rel_dir, 'logo', $upload_msg, ['jpg', 'jpeg', 'png']);
        if ($new_logo === false) {
            $error = $upload_msg;
        } elseif (is_string($new_logo) && $new_logo !== '') {
            $old_logo = (string)($data['logo_path'] ?? '');
            if ($old_logo !== '' && stripos($old_logo, 'http://') !== 0 && stripos($old_logo, 'https://') !== 0) {
                $old_abs = mms_abs_path(ltrim($old_logo, '/'));
                if (is_file($old_abs)) {
                    @unlink($old_abs);
                }
            }
            $logo_path = $new_logo;
        }
    }

    if ($error === null) {
        try {
            $has_running_text = false;
            $has_fonte_token = false;
            $has_ui_theme = false;
            try {
                $check_col = $pdo->query("SHOW COLUMNS FROM company_profile LIKE 'running_text'");
                $has_running_text = (bool)$check_col->fetch();
            } catch (Exception $e_col) {}
            try {
                $check_col2 = $pdo->query("SHOW COLUMNS FROM company_profile LIKE 'fonte_token'");
                $has_fonte_token = (bool)$check_col2->fetch();
            } catch (Exception $e_col2) {}
            try {
                $check_col3 = $pdo->query("SHOW COLUMNS FROM company_profile LIKE 'ui_theme'");
                $has_ui_theme = (bool)$check_col3->fetch();
            } catch (Exception $e_col3) {}

            $update_map = [
                'company_name' => $name,
                'address' => $addr,
                'phone' => $phone,
                'email' => $email,
                'website' => $web,
                'logo_path' => $logo_path,
            ];
            if ($has_running_text) $update_map['running_text'] = $running_text;
            if ($has_fonte_token) $update_map['fonte_token'] = $fonte_token;
            if ($has_ui_theme) $update_map['ui_theme'] = $ui_theme;

            $set_parts = [];
            $params = [];
            foreach ($update_map as $col => $value) {
                $set_parts[] = $col . "=?";
                $params[] = $value;
            }
            $params[] = 1;
            $sql = "UPDATE company_profile SET " . implode(', ', $set_parts) . " WHERE id=?";
            $pdo->prepare($sql)->execute($params);

            echo "<script>alert('Identitas Perusahaan berhasil diperbarui!'); window.location='index.php?page=admin-company';</script>";
            exit;
        } catch (Exception $e) {
            $error = "Error DB: " . $e->getMessage();
        }
    }
}

// Refresh data terbaru setelah simpan
$stmt_reload = $pdo->query("SELECT * FROM company_profile WHERE id = 1");
$data = $stmt_reload->fetch(PDO::FETCH_ASSOC) ?: $data;
$data['ui_theme'] = function_exists('mms_normalize_theme_slug')
    ? mms_normalize_theme_slug($data['ui_theme'] ?? 'original', 'original')
    : 'original';

$logo_preview_url = !empty($data['logo_path']) ? (string)$data['logo_path'] : '';

render_header("Identitas Perusahaan (cPanel)");
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-building"></i> Pengaturan Identitas Perusahaan (cPanel)</h5>
            </div>
            <div class="card-body">

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <div class="alert alert-info small">
                    Folder upload logo: <code><?= htmlspecialchars($logo_rel_dir, ENT_QUOTES, 'UTF-8') ?></code><br>
                    Server path: <code><?= htmlspecialchars($logo_abs_dir, ENT_QUOTES, 'UTF-8') ?></code>
                </div>
                <div class="alert alert-secondary small">
                    <div class="fw-bold mb-1">Diagnosa Upload (Server Runtime)</div>
                    <div>Folder ada: <strong><?= $upload_diag['dir_exists'] ? 'YA' : 'TIDAK' ?></strong></div>
                    <div>Folder writable: <strong><?= $upload_diag['dir_writable'] ? 'YA' : 'TIDAK' ?></strong></div>
                    <div>upload_tmp_dir (ini): <code><?= htmlspecialchars((string)$upload_diag['upload_tmp_dir'], ENT_QUOTES, 'UTF-8') ?></code></div>
                    <div>upload_tmp_dir efektif: <code><?= htmlspecialchars((string)$upload_diag['upload_tmp_effective'], ENT_QUOTES, 'UTF-8') ?></code></div>
                    <div>tmp dir ada: <strong><?= !empty($upload_diag['upload_tmp_exists']) ? 'YA' : 'TIDAK' ?></strong></div>
                    <div>tmp dir writable: <strong><?= !empty($upload_diag['upload_tmp_writable']) ? 'YA' : 'TIDAK' ?></strong></div>
                    <div>open_basedir: <code><?= htmlspecialchars((string)$upload_diag['open_basedir'], ENT_QUOTES, 'UTF-8') ?></code></div>
                    <div>file_uploads (raw): <code><?= htmlspecialchars((string)$upload_diag['file_uploads'], ENT_QUOTES, 'UTF-8') ?></code></div>
                    <div>file_uploads aktif: <strong><?= !empty($upload_diag['file_uploads_on']) ? 'YA' : 'TIDAK' ?></strong></div>
                    <div>upload_max_filesize: <code><?= htmlspecialchars((string)$upload_diag['upload_max_filesize'], ENT_QUOTES, 'UTF-8') ?></code></div>
                    <div>post_max_size: <code><?= htmlspecialchars((string)$upload_diag['post_max_size'], ENT_QUOTES, 'UTF-8') ?></code></div>
                    <div>max_file_uploads: <code><?= htmlspecialchars((string)$upload_diag['max_file_uploads'], ENT_QUOTES, 'UTF-8') ?></code></div>
                    <div>memory_limit: <code><?= htmlspecialchars((string)$upload_diag['memory_limit'], ENT_QUOTES, 'UTF-8') ?></code></div>
                    <hr class="my-2">
                    <div>Folder avatar writable: <strong><?= !empty($upload_diag_avatar['dir_writable']) ? 'YA' : 'TIDAK' ?></strong>
                        <code><?= htmlspecialchars((string)$upload_diag_avatar['absolute_dir'], ENT_QUOTES, 'UTF-8') ?></code></div>
                    <div>Folder tanda tangan writable: <strong><?= !empty($upload_diag_sig['dir_writable']) ? 'YA' : 'TIDAK' ?></strong>
                        <code><?= htmlspecialchars((string)$upload_diag_sig['absolute_dir'], ENT_QUOTES, 'UTF-8') ?></code></div>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div class="text-center mb-4 p-3 bg-light border rounded">
                        <label class="form-label fw-bold d-block">Logo Saat Ini:</label>
                        <?php if (!empty($logo_preview_url)): ?>
                            <img src="<?= htmlspecialchars($logo_preview_url, ENT_QUOTES, 'UTF-8') ?>" alt="Company Logo" style="max-height: 100px; max-width: 100%;">
                        <?php else: ?>
                            <span class="text-muted fst-italic">Belum ada logo diupload</span>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nama Perusahaan (PT/CV) <span class="text-danger">*</span></label>
                        <input type="text" name="company_name" class="form-control fw-bold" value="<?= htmlspecialchars($data['company_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($data['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Alamat Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($data['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Website</label>
                        <input type="text" name="website" class="form-control" value="<?= htmlspecialchars($data['website'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://mms.promindolaser.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Running Text TV Dashboard</label>
                        <textarea name="running_text" class="form-control" rows="2"><?= htmlspecialchars($data['running_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Token WA Fonte</label>
                        <input type="text" name="fonte_token" class="form-control" value="<?= htmlspecialchars($data['fonte_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Theme Aplikasi</label>
                        <select name="ui_theme" class="form-select">
                            <?php foreach ($available_themes as $slug => $theme_meta): ?>
                                <?php
                                    $slug_val = (string)$slug;
                                    $label_val = (string)($theme_meta['label'] ?? mms_theme_label($slug_val));
                                    $selected = ($data['ui_theme'] === $slug_val) ? 'selected' : '';
                                ?>
                                <option value="<?= htmlspecialchars($slug_val, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($label_val, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Daftar tema otomatis diambil dari file <code>assets/css/theme-*.css</code>.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Alamat Lengkap</label>
                        <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($data['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Upload Logo Baru (JPG/PNG)</label>
                        <input type="file" name="logo" class="form-control" accept="image/png, image/jpeg">
                        <div class="form-text">Biarkan kosong jika tidak ingin mengubah logo.</div>
                    </div>

                    <hr>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
