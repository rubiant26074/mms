<?php
// modules/admin/company/index.php

// Pastikan kolom running_text tersedia (aman jika sudah ada)
try {
    $pdo->exec("ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS running_text TEXT NULL AFTER website");
} catch (Exception $e) {
    // Abaikan jika server DB tidak mendukung IF NOT EXISTS atau hak ALTER terbatas.
}
try {
    $pdo->exec("ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS fonte_token VARCHAR(255) NULL AFTER running_text");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS fonte_token VARCHAR(255) NULL AFTER website");
    } catch (Exception $e2) {
        // Abaikan jika server DB tidak mendukung IF NOT EXISTS atau hak ALTER terbatas.
    }
}
try {
    $pdo->exec("ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS ui_theme VARCHAR(100) NULL AFTER fonte_token");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE company_profile ADD COLUMN ui_theme VARCHAR(100) NULL AFTER fonte_token");
    } catch (Exception $e2) {
        // Abaikan jika server DB tidak mendukung ALTER atau kolom sudah ada.
    }
}
try {
    $pdo->exec("ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS attendance_location_name VARCHAR(150) NULL AFTER ui_theme");
    $pdo->exec("ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS attendance_latitude DECIMAL(10,7) NULL AFTER attendance_location_name");
    $pdo->exec("ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS attendance_longitude DECIMAL(10,7) NULL AFTER attendance_latitude");
    $pdo->exec("ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS attendance_radius_meters INT NULL AFTER attendance_longitude");
} catch (Exception $e) {
    // Abaikan bila hak ALTER terbatas.
}
$available_themes = function_exists('mms_get_available_themes')
    ? mms_get_available_themes()
    : ['original' => ['slug' => 'original', 'label' => 'Theme Original']];

// 1. Ambil Data Saat Ini
$stmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1");
$data = $stmt->fetch();
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
        'attendance_location_name' => '',
        'attendance_latitude' => '',
        'attendance_longitude' => '',
        'attendance_radius_meters' => 100,
        'logo_path' => ''
    ];
}

$data['ui_theme'] = function_exists('mms_normalize_theme_slug')
    ? mms_normalize_theme_slug($data['ui_theme'] ?? 'original', 'original')
    : 'original';

$logo_preview_url = mms_asset_url((string)($data['logo_path'] ?? ''), true);

// 2. PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = clean($_POST['company_name']);
    $addr    = clean($_POST['address']);
    $phone   = clean($_POST['phone']);
    $email   = clean($_POST['email']);
    $web     = clean($_POST['website']);
    $running_text = trim($_POST['running_text'] ?? '');
    $fonte_token = trim($_POST['fonte_token'] ?? '');
    $ui_theme_input = strtolower(trim((string)($_POST['ui_theme'] ?? 'original')));
    $attendance_location_name = trim((string)($_POST['attendance_location_name'] ?? ''));
    $attendance_latitude = trim((string)($_POST['attendance_latitude'] ?? ''));
    $attendance_longitude = trim((string)($_POST['attendance_longitude'] ?? ''));
    $attendance_radius_meters = (int)($_POST['attendance_radius_meters'] ?? 100);
    $ui_theme = function_exists('mms_normalize_theme_slug')
        ? mms_normalize_theme_slug($ui_theme_input, 'original')
        : 'original';
    if ($attendance_radius_meters <= 0) {
        $attendance_radius_meters = 100;
    }
    if (($attendance_latitude !== '' && !is_numeric(str_replace(',', '.', $attendance_latitude))) || ($attendance_longitude !== '' && !is_numeric(str_replace(',', '.', $attendance_longitude)))) {
        $error = "Latitude dan longitude geotag harus berupa angka yang valid.";
    }
    
    // Logic Upload Logo (universal: XAMPP + cPanel)
    $logo_path = $data['logo_path']; // Default pake lama
    if (isset($_FILES['logo']) && is_array($_FILES['logo'])) {
        $upload_msg = null;
        $new_logo = mms_store_uploaded_image($_FILES['logo'], mms_upload_target('company_logo'), 'logo', $upload_msg, ['jpg', 'jpeg', 'png']);
        if ($new_logo === false) {
            $error = $upload_msg;
        } elseif (is_string($new_logo) && $new_logo !== '') {
            if (!empty($data['logo_path']) && stripos((string)$data['logo_path'], 'http://') !== 0 && stripos((string)$data['logo_path'], 'https://') !== 0) {
                $old_abs = mms_abs_path(ltrim((string)$data['logo_path'], '/'));
                if (is_file($old_abs)) {
                    @unlink($old_abs);
                }
            }
            $logo_path = $new_logo;
        }
    }

    // Update Database
    if (empty($error)) {
        try {
            $has_running_text = false;
            $has_fonte_token = false;
            $has_ui_theme = false;
            $has_attendance_location_name = false;
            $has_attendance_latitude = false;
            $has_attendance_longitude = false;
            $has_attendance_radius_meters = false;
            try {
                $check_col = $pdo->query("SHOW COLUMNS FROM company_profile LIKE 'running_text'");
                $has_running_text = (bool)$check_col->fetch();
            } catch (Exception $e_col) {}
            try {
                $check_col2 = $pdo->query("SHOW COLUMNS FROM company_profile LIKE 'fonte_token'");
                $has_fonte_token = (bool)$check_col2->fetch();
            } catch (Exception $e_col) {}
            try {
                $check_col3 = $pdo->query("SHOW COLUMNS FROM company_profile LIKE 'ui_theme'");
                $has_ui_theme = (bool)$check_col3->fetch();
            } catch (Exception $e_col) {}
            try {
                $has_attendance_location_name = (bool)$pdo->query("SHOW COLUMNS FROM company_profile LIKE 'attendance_location_name'")->fetch();
            } catch (Exception $e_col) {}
            try {
                $has_attendance_latitude = (bool)$pdo->query("SHOW COLUMNS FROM company_profile LIKE 'attendance_latitude'")->fetch();
            } catch (Exception $e_col) {}
            try {
                $has_attendance_longitude = (bool)$pdo->query("SHOW COLUMNS FROM company_profile LIKE 'attendance_longitude'")->fetch();
            } catch (Exception $e_col) {}
            try {
                $has_attendance_radius_meters = (bool)$pdo->query("SHOW COLUMNS FROM company_profile LIKE 'attendance_radius_meters'")->fetch();
            } catch (Exception $e_col) {}

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
            if ($has_attendance_location_name) $update_map['attendance_location_name'] = $attendance_location_name;
            if ($has_attendance_latitude) $update_map['attendance_latitude'] = ($attendance_latitude !== '' ? str_replace(',', '.', $attendance_latitude) : null);
            if ($has_attendance_longitude) $update_map['attendance_longitude'] = ($attendance_longitude !== '' ? str_replace(',', '.', $attendance_longitude) : null);
            if ($has_attendance_radius_meters) $update_map['attendance_radius_meters'] = $attendance_radius_meters;

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
            $error = "Error: " . $e->getMessage();
        }
    }
}

render_header("Identitas Perusahaan");
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-building"></i> Pengaturan Identitas Perusahaan</h5>
            </div>
            <div class="card-body">
                
                <?php if(isset($error)): ?><div class="alert alert-danger"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    
                    <!-- Preview Logo -->
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
                        <input type="text" name="company_name" class="form-control fw-bold" value="<?= $data['company_name'] ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="text" name="phone" class="form-control" value="<?= $data['phone'] ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Alamat Email</label>
                            <input type="email" name="email" class="form-control" value="<?= $data['email'] ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Website</label>
                        <input type="text" name="website" class="form-control" value="<?= $data['website'] ?>" placeholder="https://...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Running Text TV Dashboard</label>
                        <textarea name="running_text" class="form-control" rows="2" placeholder="Contoh: SAFETY FIRST - Gunakan APD lengkap - Jaga kebersihan area kerja"><?= htmlspecialchars($data['running_text'] ?? '') ?></textarea>
                        <div class="form-text">Teks berjalan untuk TV Lobby dan TV Production.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Token WA Fonte</label>
                        <input type="text" name="fonte_token" class="form-control" value="<?= htmlspecialchars($data['fonte_token'] ?? '') ?>" placeholder="Isi token API Fonte/Fonnte">
                        <div class="form-text">Dipakai untuk kirim notifikasi WhatsApp otomatis ke customer.</div>
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

                    <div class="card border-primary-subtle bg-light mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3"><i class="bi bi-geo-alt"></i> Setting Geotag Absensi</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nama Lokasi Absensi</label>
                                    <input type="text" name="attendance_location_name" class="form-control" value="<?= htmlspecialchars((string)($data['attendance_location_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Contoh: Kantor Pusat / Workshop">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Radius Valid (meter)</label>
                                    <input type="number" min="1" name="attendance_radius_meters" class="form-control" value="<?= (int)($data['attendance_radius_meters'] ?? 100) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Latitude</label>
                                    <input type="text" name="attendance_latitude" id="attendance_latitude" class="form-control" value="<?= htmlspecialchars((string)($data['attendance_latitude'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="-6.2000000">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Longitude</label>
                                    <input type="text" name="attendance_longitude" id="attendance_longitude" class="form-control" value="<?= htmlspecialchars((string)($data['attendance_longitude'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="106.8166667">
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnDetectGeoAdmin">
                                    <i class="bi bi-crosshair"></i> Ambil Lokasi Saat Ini
                                </button>
                                <small class="text-muted">Jalankan dari HP/laptop admin untuk mengambil titik lokasi absensi utama.</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Alamat Lengkap</label>
                        <textarea name="address" class="form-control" rows="3"><?= $data['address'] ?></textarea>
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

<script>
document.getElementById('btnDetectGeoAdmin')?.addEventListener('click', function () {
    if (!navigator.geolocation) {
        alert('Browser ini tidak mendukung geolocation.');
        return;
    }
    navigator.geolocation.getCurrentPosition(function (position) {
        document.getElementById('attendance_latitude').value = position.coords.latitude.toFixed(7);
        document.getElementById('attendance_longitude').value = position.coords.longitude.toFixed(7);
    }, function (error) {
        alert('Gagal mengambil lokasi: ' + error.message);
    }, {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 0
    });
});
</script>

<?php render_footer(); ?>
