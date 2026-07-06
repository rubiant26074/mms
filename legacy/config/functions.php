<?php
// config/functions.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// 1. BASIC HELPERS
// ============================================================================

function clean($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function mms_project_root() {
    static $root = null;
    if ($root !== null) return $root;
    $root = realpath(__DIR__ . '/..');
    if ($root === false) {
        $root = dirname(__DIR__);
    }
    return $root;
}

function mms_abs_path($relative_path = '') {
    $base = mms_project_root();
    $relative_path = trim((string)$relative_path);
    if ($relative_path === '') return $base;
    $relative_path = ltrim(str_replace('\\', '/', $relative_path), '/');
    return $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
}

function mms_asset_url($path, $cache_bust = true) {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('/^https?:\/\//i', $path)) return $path;

    $rel = ltrim(str_replace('\\', '/', $path), '/');
    $abs = mms_abs_path($rel);
    if (!is_file($abs)) {
        return $rel;
    }
    if (!$cache_bust) return $rel;

    $ver = @filemtime($abs);
    return $ver ? ($rel . '?v=' . $ver) : $rel;
}

function mms_get_development_config() {
    static $cfg = null;
    if (is_array($cfg)) return $cfg;

    $cfg = [
        'enabled' => false,
        'features' => [],
    ];

    $dev_file = mms_abs_path('modules/development.php');
    if (!is_file($dev_file)) return $cfg;

    try {
        $loaded = require $dev_file;
        if (is_array($loaded)) {
            if (array_key_exists('enabled', $loaded)) {
                $cfg['enabled'] = (bool)$loaded['enabled'];
            }
            if (isset($loaded['features']) && is_array($loaded['features'])) {
                $cfg['features'] = $loaded['features'];
            }
        }
    } catch (Throwable $e) {
        // fallback ke default config
    }

    return $cfg;
}

function mms_is_development_enabled() {
    $cfg = mms_get_development_config();
    return !empty($cfg['enabled']);
}

function mms_is_dev_feature_enabled($feature_key) {
    $feature_key = trim((string)$feature_key);
    if ($feature_key === '') return mms_is_development_enabled();

    $cfg = mms_get_development_config();
    if (empty($cfg['enabled'])) return false;

    $features = (array)($cfg['features'] ?? []);
    return !empty($features[$feature_key]);
}

function mms_theme_label($slug) {
    $slug = strtolower(trim((string)$slug));
    $label_map = [
        'original' => 'Original (Standar)',
        'pro' => 'Pro (Modern)',
        'aurora' => 'Aurora (Cerah)',
        'emerald' => 'Emerald (Hijau)',
        'slate' => 'Slate (Abu)',
        'nocturne' => 'Nocturne (Gelap)',
        'obsidian' => 'Obsidian (Hitam)',
    ];
    if ($slug === '') $slug = 'original';
    if (isset($label_map[$slug])) return $label_map[$slug];
    return ucwords(str_replace(['-', '_'], ' ', $slug));
}

function mms_get_available_themes() {
    static $themes = null;
    if (is_array($themes)) return $themes;

    $themes = [
        'original' => [
            'slug' => 'original',
            'label' => mms_theme_label('original'),
            'css_path' => '',
            'body_class' => '',
        ],
    ];

    $css_dir = mms_abs_path('assets/css');
    if (is_dir($css_dir)) {
        $files = glob($css_dir . DIRECTORY_SEPARATOR . 'theme-*.css');
        if (is_array($files)) {
            sort($files, SORT_STRING);
            foreach ($files as $abs_file) {
                $base = basename((string)$abs_file);
                if (!preg_match('/^theme-([a-z0-9][a-z0-9\-]*)\.css$/i', $base, $m)) continue;
                $slug = strtolower((string)$m[1]);
                if ($slug === 'original') continue;
                $themes[$slug] = [
                    'slug' => $slug,
                    'label' => mms_theme_label($slug),
                    'css_path' => 'assets/css/' . $base,
                    'body_class' => 'theme-' . $slug,
                ];
            }
        }
    }

    return $themes;
}

function mms_normalize_theme_slug($slug, $fallback = 'original') {
    $themes = mms_get_available_themes();
    $slug = strtolower(trim((string)$slug));
    if ($slug !== '' && isset($themes[$slug])) return $slug;

    $fallback = strtolower(trim((string)$fallback));
    if ($fallback !== '' && isset($themes[$fallback])) return $fallback;
    return 'original';
}

function mms_get_effective_theme_slug($company_profile = null) {
    $themes = mms_get_available_themes();
    $saved_theme = 'original';

    if (is_array($company_profile)) {
        $saved_theme = mms_normalize_theme_slug($company_profile['ui_theme'] ?? 'original', 'original');
    } else {
        $comp = get_company_profile();
        $saved_theme = mms_normalize_theme_slug($comp['ui_theme'] ?? 'original', 'original');
    }

    $override_theme = strtolower(trim((string)($_GET['theme'] ?? '')));
    if ($override_theme !== '' && isset($themes[$override_theme])) {
        return $override_theme;
    }

    return $saved_theme;
}

function mms_can_mark_sales_order_completed($so_id, $pdo_conn = null) {
    if ($pdo_conn === null) {
        $pdo_conn = $GLOBALS['pdo'] ?? null;
    }

    $result = [
        'ok' => false,
        'so_id' => (int)$so_id,
        'so_number' => '',
        'so_status' => '',
        'spk_total' => 0,
        'spk_closed' => 0,
        'reason' => 'Koneksi database tidak tersedia.',
    ];

    $so_id = (int)$so_id;
    if ($so_id <= 0) {
        $result['reason'] = 'ID Sales Order tidak valid.';
        return $result;
    }
    if (!($pdo_conn instanceof PDO)) {
        return $result;
    }

    mms_ensure_sales_orders_fulfillment_source_column($pdo_conn);

    try {
        $stmt = $pdo_conn->prepare(
            "SELECT so.id,
                    so.so_number,
                    so.status,
                    COALESCE(so.fulfillment_source, 'spk') AS fulfillment_source,
                    COUNT(s.id) AS spk_total,
                    SUM(CASE WHEN s.status = 'closed' THEN 1 ELSE 0 END) AS spk_closed
             FROM sales_orders so
             LEFT JOIN spk s ON s.sales_order_id = so.id
             WHERE so.id = ?
             GROUP BY so.id, so.so_number, so.status, so.fulfillment_source
             LIMIT 1"
        );
        $stmt->execute([$so_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $result['reason'] = 'Sales Order tidak ditemukan.';
            return $result;
        }

        $result['so_number'] = (string)($row['so_number'] ?? '');
        $result['so_status'] = (string)($row['status'] ?? '');
        $fulfillment_source = mms_normalize_sales_order_fulfillment_source($row['fulfillment_source'] ?? 'spk');
        $result['spk_total'] = (int)($row['spk_total'] ?? 0);
        $result['spk_closed'] = (int)($row['spk_closed'] ?? 0);

        if ($fulfillment_source === 'fg_stock') {
            $result['ok'] = true;
            $result['reason'] = '';
            return $result;
        }

        if ($result['spk_total'] <= 0) {
            $result['reason'] = 'SO belum memiliki relasi SPK.';
            return $result;
        }
        if ($result['spk_closed'] <= 0) {
            $result['reason'] = 'SPK terkait SO belum CLOSED (QC belum selesai).';
            return $result;
        }

        $result['ok'] = true;
        $result['reason'] = '';
        return $result;
    } catch (Throwable $e) {
        $result['reason'] = 'Gagal validasi status SO: ' . $e->getMessage();
        return $result;
    }
}

function mms_ensure_sales_orders_fulfillment_source_column($pdo_conn = null) {
    static $done = false;
    if ($done) return true;
    if ($pdo_conn === null) {
        $pdo_conn = $GLOBALS['pdo'] ?? null;
    }
    if (!($pdo_conn instanceof PDO)) return false;

    try {
        $pdo_conn->exec("ALTER TABLE sales_orders ADD COLUMN IF NOT EXISTS fulfillment_source VARCHAR(20) NOT NULL DEFAULT 'spk' AFTER payment_terms");
    } catch (Exception $e) {
        try {
            $chk = $pdo_conn->query("SHOW COLUMNS FROM sales_orders LIKE 'fulfillment_source'");
            $exists = (bool)($chk && $chk->fetch());
            if (!$exists) {
                $pdo_conn->exec("ALTER TABLE sales_orders ADD COLUMN fulfillment_source VARCHAR(20) NOT NULL DEFAULT 'spk' AFTER payment_terms");
            }
        } catch (Exception $e2) {
            return false;
        }
    }

    try {
        $pdo_conn->exec("UPDATE sales_orders SET fulfillment_source='spk' WHERE fulfillment_source IS NULL OR TRIM(fulfillment_source)=''");
    } catch (Exception $e3) {
        // abaikan jika hak terbatas
    }

    $done = true;
    return true;
}

function mms_sales_order_fulfillment_label($source) {
    $source = strtolower(trim((string)$source));
    return match ($source) {
        'fg_stock' => 'FG Stock (Tanpa SPK Baru)',
        default => 'Produksi / SPK',
    };
}

function mms_normalize_sales_order_fulfillment_source($source) {
    $source = strtolower(trim((string)$source));
    return in_array($source, ['spk', 'fg_stock'], true) ? $source : 'spk';
}

function mms_upload_target($key) {
    $map = [
        'company_logo' => 'uploads/company',
        'signature' => 'uploads/signatures',
        'avatar' => 'uploads/avatars',
        'face_reference' => 'uploads/face-reference',
        'attendance_selfie' => 'uploads/attendance',
    ];
    $key = trim((string)$key);
    return $map[$key] ?? '';
}

function mms_to_float_or_null($value) {
    if ($value === null) return null;
    if (is_string($value)) {
        $value = trim($value);
        if ($value === '') return null;
        $value = str_replace(',', '.', $value);
    }
    return is_numeric($value) ? (float)$value : null;
}

function mms_haversine_distance_meters($lat1, $lng1, $lat2, $lng2) {
    $lat1 = mms_to_float_or_null($lat1);
    $lng1 = mms_to_float_or_null($lng1);
    $lat2 = mms_to_float_or_null($lat2);
    $lng2 = mms_to_float_or_null($lng2);

    if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
        return null;
    }

    $earth_radius = 6371000.0;
    $d_lat = deg2rad($lat2 - $lat1);
    $d_lng = deg2rad($lng2 - $lng1);
    $a = sin($d_lat / 2) * sin($d_lat / 2)
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
        * sin($d_lng / 2) * sin($d_lng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth_radius * $c;
}

function mms_upload_error_message($code) {
    $map = [
        UPLOAD_ERR_INI_SIZE => 'Ukuran file melebihi batas upload_max_filesize server.',
        UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas form.',
        UPLOAD_ERR_PARTIAL => 'File hanya ter-upload sebagian.',
        UPLOAD_ERR_NO_FILE => 'Tidak ada file yang dipilih.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary upload tidak tersedia di server.',
        UPLOAD_ERR_CANT_WRITE => 'Server tidak bisa menulis file ke disk.',
        UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP/server.',
    ];
    return $map[$code] ?? ('Upload gagal dengan kode error: ' . (int)$code);
}

function mms_upload_runtime_info($relative_dir = '') {
    $relative_dir = trim((string)$relative_dir);
    $dir_abs = $relative_dir !== '' ? mms_abs_path($relative_dir) : '';
    $max_upload = (string)ini_get('upload_max_filesize');
    $post_max = (string)ini_get('post_max_size');
    $upload_tmp = (string)ini_get('upload_tmp_dir');
    $tmp_effective = $upload_tmp !== '' ? $upload_tmp : (string)sys_get_temp_dir();
    $file_uploads_val = trim((string)ini_get('file_uploads'));
    $file_uploads_lower = strtolower($file_uploads_val);
    $file_uploads_known = $file_uploads_val !== '';
    $file_uploads_on = !$file_uploads_known || !in_array($file_uploads_lower, ['0', 'off', 'false', 'no'], true);

    return [
        'relative_dir' => $relative_dir,
        'absolute_dir' => $dir_abs,
        'dir_exists' => ($dir_abs !== '' ? is_dir($dir_abs) : false),
        'dir_writable' => ($dir_abs !== '' ? is_writable($dir_abs) : false),
        'upload_tmp_dir' => $upload_tmp,
        'upload_tmp_effective' => $tmp_effective,
        'upload_tmp_exists' => ($tmp_effective !== '' ? is_dir($tmp_effective) : false),
        'upload_tmp_writable' => ($tmp_effective !== '' ? is_writable($tmp_effective) : false),
        'open_basedir' => (string)ini_get('open_basedir'),
        'file_uploads' => $file_uploads_val,
        'file_uploads_known' => $file_uploads_known,
        'file_uploads_on' => $file_uploads_on,
        'upload_max_filesize' => $max_upload,
        'post_max_size' => $post_max,
        'max_file_uploads' => (string)ini_get('max_file_uploads'),
        'memory_limit' => (string)ini_get('memory_limit'),
    ];
}

function mms_log_upload_failure($stage, $detail = []) {
    $stage = trim((string)$stage);
    if ($stage === '') $stage = 'unknown';
    if (!is_array($detail)) $detail = ['detail' => (string)$detail];

    $payload = [
        'tag' => 'MMS_UPLOAD_FAIL',
        'stage' => $stage,
        'time' => date('c'),
        'detail' => $detail,
    ];
    @error_log(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function mms_ensure_writable_dir($relative_dir, &$error_message = null) {
    $error_message = null;
    $relative_dir = trim((string)$relative_dir);
    if ($relative_dir === '') {
        $error_message = 'Folder upload tidak valid.';
        return false;
    }

    $abs_dir = mms_abs_path($relative_dir);
    if (!is_dir($abs_dir)) {
        @mkdir($abs_dir, 0755, true);
    }
    if (!is_dir($abs_dir)) {
        $error_message = "Folder upload tidak ditemukan: {$relative_dir}";
        return false;
    }
    // is_writable() sering false-positive di Windows/XAMPP.
    // Verifikasi final dengan tes tulis file kecil.
    if (!is_writable($abs_dir)) {
        $test_file = rtrim($abs_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.mms_write_test_' . uniqid('', true) . '.tmp';
        $ok = @file_put_contents($test_file, 'ok') !== false;
        if ($ok) {
            @unlink($test_file);
        } else {
            $error_message = "Folder upload tidak bisa ditulis. Set permission '{$relative_dir}' ke 755/775 (Linux) atau Write/Modify (Windows).";
            return false;
        }
    }
    return $abs_dir;
}

function mms_store_uploaded_image($file_info, $relative_dir, $prefix, &$error_message = null, $allowed_ext = ['jpg', 'jpeg', 'png']) {
    $error_message = null;
    if (!is_array($file_info)) return null;

    $runtime = mms_upload_runtime_info($relative_dir);
    if (!empty($runtime['file_uploads_known']) && empty($runtime['file_uploads_on'])) {
        $error_message = 'Upload dinonaktifkan di server (file_uploads=Off). Aktifkan file_uploads terlebih dahulu.';
        mms_log_upload_failure('file_uploads_off', $runtime);
        return false;
    }

    $upload_error = (int)($file_info['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($upload_error === UPLOAD_ERR_NO_FILE) return null;
    if ($upload_error !== UPLOAD_ERR_OK) {
        $error_message = mms_upload_error_message($upload_error);
        return false;
    }

    $filename = (string)($file_info['name'] ?? '');
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) {
        $error_message = "Format file harus: " . strtoupper(implode(', ', $allowed_ext)) . '.';
        return false;
    }

    $abs_dir = mms_ensure_writable_dir($relative_dir, $dir_error);
    if ($abs_dir === false) {
        $error_message = $dir_error;
        mms_log_upload_failure('target_dir', mms_upload_runtime_info($relative_dir));
        return false;
    }

    $tmp_name = (string)($file_info['tmp_name'] ?? '');
    if ($tmp_name === '' || !is_file($tmp_name)) {
        $error_message = 'File upload tidak valid (tmp file tidak ditemukan). Periksa upload_tmp_dir/file_uploads.';
        mms_log_upload_failure('tmp_missing', array_merge(
            mms_upload_runtime_info($relative_dir),
            [
                'tmp_name' => $tmp_name,
                'tmp_exists' => ($tmp_name !== '' ? is_file($tmp_name) : false),
                'php_file_error' => (int)($file_info['error'] ?? -1),
                'php_file_error_text' => mms_upload_error_message((int)($file_info['error'] ?? -1)),
            ]
        ));
        return false;
    }

    try {
        $rand = bin2hex(random_bytes(3));
    } catch (Exception $e) {
        $rand = (string)mt_rand(100000, 999999);
    }

    $relative_dir = trim(str_replace('\\', '/', $relative_dir), '/');
    $new_basename = trim((string)$prefix) . '_' . time() . '_' . $rand . '.' . $ext;
    $new_relative_path = $relative_dir . '/' . $new_basename;
    $new_abs_path = mms_abs_path($new_relative_path);

    $uploaded_flag = is_uploaded_file($tmp_name);
    $move_ok = @move_uploaded_file($tmp_name, $new_abs_path);
    if (!$move_ok) {
        // Fallback untuk environment tertentu (beberapa cPanel/WAF case).
        $move_ok = @rename($tmp_name, $new_abs_path);
        if (!$move_ok) {
            $move_ok = @copy($tmp_name, $new_abs_path);
            if ($move_ok) {
                @unlink($tmp_name);
            }
        }
    }

    if (!$move_ok || !is_file($new_abs_path)) {
        $last_error = error_get_last();
        $error_message = 'Upload gagal di server. Periksa upload_tmp_dir, permission folder, atau batasan open_basedir/ModSecurity.';
        mms_log_upload_failure('move_failed', array_merge(
            mms_upload_runtime_info($relative_dir),
            [
                'php_file_error' => (int)($file_info['error'] ?? -1),
                'php_file_error_text' => mms_upload_error_message((int)($file_info['error'] ?? -1)),
                'tmp_name' => $tmp_name,
                'tmp_exists' => is_file($tmp_name),
                'tmp_uploaded_flag' => $uploaded_flag,
                'target_abs' => $new_abs_path,
                'target_parent_exists' => is_dir(dirname($new_abs_path)),
                'target_parent_writable' => is_writable(dirname($new_abs_path)),
                'last_error' => $last_error,
            ]
        ));
        return false;
    }

    return $new_relative_path;
}

function mms_csrf_token() {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_mms_csrf_token($token) {
    $token = (string)$token;
    $session_token = (string)($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $session_token === '') return false;
    return hash_equals($session_token, $token);
}

function mms_ensure_quotations_client_signature_columns($pdo_conn = null) {
    static $done = false;
    if ($done) return true;
    if ($pdo_conn === null) {
        $pdo_conn = $GLOBALS['pdo'] ?? null;
    }
    if (!($pdo_conn instanceof PDO)) return false;

    $ddl_list = [
        "ALTER TABLE quotations ADD COLUMN IF NOT EXISTS sent_to_client_at DATETIME NULL AFTER approved_by",
        "ALTER TABLE quotations ADD COLUMN IF NOT EXISTS sent_to_client_by INT NULL AFTER sent_to_client_at",
        "ALTER TABLE quotations ADD COLUMN IF NOT EXISTS client_signature_path VARCHAR(255) NULL AFTER sent_to_client_by",
        "ALTER TABLE quotations ADD COLUMN IF NOT EXISTS client_signed_name VARCHAR(150) NULL AFTER client_signature_path",
        "ALTER TABLE quotations ADD COLUMN IF NOT EXISTS client_signed_at DATETIME NULL AFTER client_signed_name",
        "ALTER TABLE quotations ADD COLUMN IF NOT EXISTS client_signature_ip VARCHAR(45) NULL AFTER client_signed_at",
        "ALTER TABLE quotations ADD COLUMN IF NOT EXISTS client_signature_user_agent VARCHAR(255) NULL AFTER client_signature_ip",
    ];

    foreach ($ddl_list as $ddl) {
        try {
            $pdo_conn->exec($ddl);
        } catch (Throwable $e) {
            // Fallback untuk MySQL lama tanpa IF NOT EXISTS pada ADD COLUMN.
            if (preg_match('/ADD COLUMN(?: IF NOT EXISTS)?\s+([a-zA-Z0-9_]+)/i', $ddl, $m)) {
                $col = (string)$m[1];
                try {
                    $chk = $pdo_conn->prepare("SHOW COLUMNS FROM quotations LIKE ?");
                    $chk->execute([$col]);
                    $exists = (bool)$chk->fetch(PDO::FETCH_ASSOC);
                    if (!$exists) {
                        $ddl_fallback = preg_replace('/ IF NOT EXISTS/i', '', $ddl);
                        $pdo_conn->exec($ddl_fallback);
                    }
                } catch (Throwable $e2) {
                    return false;
                }
            } else {
                return false;
            }
        }
    }

    $done = true;
    return true;
}

function mms_ensure_sales_orders_client_signature_columns($pdo_conn = null) {
    static $done = false;
    if ($done) return true;
    if ($pdo_conn === null) {
        $pdo_conn = $GLOBALS['pdo'] ?? null;
    }
    if (!($pdo_conn instanceof PDO)) return false;

    $ddl_list = [
        "ALTER TABLE sales_orders ADD COLUMN IF NOT EXISTS sent_to_client_at DATETIME NULL AFTER approved_at",
        "ALTER TABLE sales_orders ADD COLUMN IF NOT EXISTS sent_to_client_by INT NULL AFTER sent_to_client_at",
        "ALTER TABLE sales_orders ADD COLUMN IF NOT EXISTS client_signature_path VARCHAR(255) NULL AFTER sent_to_client_by",
        "ALTER TABLE sales_orders ADD COLUMN IF NOT EXISTS client_signed_name VARCHAR(150) NULL AFTER client_signature_path",
        "ALTER TABLE sales_orders ADD COLUMN IF NOT EXISTS client_signed_at DATETIME NULL AFTER client_signed_name",
        "ALTER TABLE sales_orders ADD COLUMN IF NOT EXISTS client_signature_ip VARCHAR(45) NULL AFTER client_signed_at",
        "ALTER TABLE sales_orders ADD COLUMN IF NOT EXISTS client_signature_user_agent VARCHAR(255) NULL AFTER client_signature_ip",
    ];

    foreach ($ddl_list as $ddl) {
        try {
            $pdo_conn->exec($ddl);
        } catch (Throwable $e) {
            if (preg_match('/ADD COLUMN(?: IF NOT EXISTS)?\s+([a-zA-Z0-9_]+)/i', $ddl, $m)) {
                $col = (string)$m[1];
                try {
                    $chk = $pdo_conn->prepare("SHOW COLUMNS FROM sales_orders LIKE ?");
                    $chk->execute([$col]);
                    if (!$chk->fetch(PDO::FETCH_ASSOC)) {
                        $pdo_conn->exec(preg_replace('/ IF NOT EXISTS/i', '', $ddl));
                    }
                } catch (Throwable $e2) {
                    return false;
                }
            } else {
                return false;
            }
        }
    }

    $done = true;
    return true;
}

function app_base_url() {
    $comp = get_company_profile();
    $website = trim((string)($comp['website'] ?? ''));
    if ($website !== '' && preg_match('/^https?:\/\//i', $website)) {
        return rtrim($website, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $scriptDir = rtrim($scriptDir, '/');
    if ($scriptDir === '/' || $scriptDir === '.') $scriptDir = '';
    return $scheme . '://' . $host . $scriptDir;
}

function normalize_wa_number($phone) {
    $raw = trim((string)$phone);
    if ($raw === '') return '';

    $num = preg_replace('/[^0-9+]/', '', $raw);
    if ($num === '') return '';

    if (strpos($num, '+62') === 0) {
        $num = '62' . substr($num, 3);
    } elseif (strpos($num, '0') === 0) {
        $num = '62' . substr($num, 1);
    } elseif (strpos($num, '62') !== 0) {
        $num = '62' . ltrim($num, '+');
    }

    $num = preg_replace('/\D/', '', $num);
    return $num;
}

function public_link_secret() {
    $comp = get_company_profile();
    $seed = implode('|', [
        'MMS-PUBLIC-LINK-V1',
        (string)($comp['company_name'] ?? ''),
        preg_replace('/\s+/', '', (string)($comp['npwp'] ?? '')),
    ]);
    return hash('sha256', $seed);
}

function generate_public_link_token($entity, $id, $expires_at) {
    $entity = trim((string)$entity);
    $id = (int)$id;
    $expires = (int)$expires_at;
    $payload = $entity . '|' . $id . '|' . $expires;
    $sig = hash_hmac('sha256', $payload, public_link_secret());
    return $expires . '.' . $sig;
}

function verify_public_link_token($entity, $id, $token) {
    $entity = trim((string)$entity);
    $id = (int)$id;
    $token = trim((string)$token);
    if ($token === '' || strpos($token, '.') === false) return false;

    [$expires, $sig] = explode('.', $token, 2);
    if (!ctype_digit((string)$expires)) return false;
    $expires = (int)$expires;
    if ($expires < time()) return false;

    $expected = generate_public_link_token($entity, $id, $expires);
    return hash_equals($expected, $token);
}

function build_public_quote_url($quote_id, $valid_hours = 24 * 14) {
    $quote_id = (int)$quote_id;
    if ($quote_id <= 0) return '';
    $expires = time() + max(3600, (int)$valid_hours * 3600);
    $token = generate_public_link_token('quotation', $quote_id, $expires);
    return app_base_url() . "/index.php?page=public-quote&q={$quote_id}&t=" . urlencode($token);
}

function build_public_invoice_url($invoice_id, $valid_hours = 24 * 30) {
    $invoice_id = (int)$invoice_id;
    if ($invoice_id <= 0) return '';
    $expires = time() + max(3600, (int)$valid_hours * 3600);
    $token = generate_public_link_token('invoice', $invoice_id, $expires);
    return app_base_url() . "/index.php?page=public-invoice&i={$invoice_id}&t=" . urlencode($token);
}

function build_public_so_url($so_id, $valid_hours = 24 * 14) {
    $so_id = (int)$so_id;
    if ($so_id <= 0) return '';
    $expires = time() + max(3600, (int)$valid_hours * 3600);
    $token = generate_public_link_token('sales_order', $so_id, $expires);
    return app_base_url() . "/index.php?page=public-so&s={$so_id}&t=" . urlencode($token);
}

function send_wa_fonte($target_phone, $message, $media_url = null) {
    global $pdo;

    try {
        $comp = get_company_profile();
        $token = trim((string)($comp['fonte_token'] ?? ''));
        if ($token === '') {
            log_wa_message([
                'recipient_phone_raw' => $target_phone,
                'recipient_phone' => '',
                'message' => (string)$message,
                'media_url' => (string)$media_url,
                'status' => 'failed',
                'error_message' => 'Token Fonte belum diisi di Identitas Perusahaan'
            ]);
            return ['ok' => false, 'error' => 'Token Fonte belum diisi di Identitas Perusahaan'];
        }

        $target = normalize_wa_number($target_phone);
        if ($target === '') {
            log_wa_message([
                'recipient_phone_raw' => $target_phone,
                'recipient_phone' => '',
                'message' => (string)$message,
                'media_url' => (string)$media_url,
                'status' => 'failed',
                'error_message' => 'Nomor WA customer tidak valid/kosong'
            ]);
            return ['ok' => false, 'error' => 'Nomor WA customer tidak valid/kosong'];
        }

        $payload = [
            'target' => $target,
            'message' => (string)$message
        ];
        if (!empty($media_url)) {
            $payload['url'] = (string)$media_url;
        }

        $endpoint = 'https://api.fonnte.com/send';
        $headers = [
            'Authorization: ' . $token
        ];

        $response = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $response = curl_exec($ch);
            $curl_error = curl_error($ch);
            curl_close($ch);
            if ($response === false) {
                $err = 'Fonte cURL error: ' . $curl_error;
                log_wa_message([
                    'recipient_phone_raw' => $target_phone,
                    'recipient_phone' => $target,
                    'message' => (string)$message,
                    'media_url' => (string)$media_url,
                    'status' => 'failed',
                    'error_message' => $err
                ]);
                return ['ok' => false, 'error' => $err];
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Authorization: {$token}\r\nContent-Type: application/x-www-form-urlencoded\r\n",
                    'content' => http_build_query($payload),
                    'timeout' => 15
                ]
            ]);
            $response = @file_get_contents($endpoint, false, $context);
            if ($response === false) {
                $err = 'Fonte request gagal (no cURL)';
                log_wa_message([
                    'recipient_phone_raw' => $target_phone,
                    'recipient_phone' => $target,
                    'message' => (string)$message,
                    'media_url' => (string)$media_url,
                    'status' => 'failed',
                    'error_message' => $err
                ]);
                return ['ok' => false, 'error' => $err];
            }
        }

        $decoded = json_decode((string)$response, true);
        $ok = true;
        if (is_array($decoded)) {
            if (isset($decoded['status'])) {
                $ok = (bool)$decoded['status'];
            } elseif (isset($decoded['detail']) && is_string($decoded['detail'])) {
                $ok = stripos($decoded['detail'], 'success') !== false;
            }
        }

        log_wa_message([
            'recipient_phone_raw' => $target_phone,
            'recipient_phone' => $target,
            'message' => (string)$message,
            'media_url' => (string)$media_url,
            'status' => $ok ? 'success' : 'failed',
            'provider_response' => (string)$response,
            'error_message' => $ok ? null : 'Provider mengembalikan status gagal'
        ]);

        return ['ok' => $ok, 'response' => $response];
    } catch (Exception $e) {
        log_wa_message([
            'recipient_phone_raw' => $target_phone,
            'recipient_phone' => normalize_wa_number($target_phone),
            'message' => (string)$message,
            'media_url' => (string)$media_url,
            'status' => 'failed',
            'error_message' => $e->getMessage()
        ]);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function ensure_wa_log_table() {
    global $pdo;
    static $checked = false;
    if ($checked) return;

    // Hindari DDL saat transaksi aktif karena MySQL dapat implicit commit.
    try {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            return;
        }
    } catch (Exception $e) {
        // lanjut best-effort
    }

    $checked = true;

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS wa_message_logs (
            id INT NOT NULL AUTO_INCREMENT,
            provider VARCHAR(30) NOT NULL DEFAULT 'fonte',
            recipient_phone_raw VARCHAR(50) DEFAULT NULL,
            recipient_phone VARCHAR(30) DEFAULT NULL,
            message_text TEXT NOT NULL,
            media_url TEXT DEFAULT NULL,
            status ENUM('success','failed') NOT NULL DEFAULT 'success',
            provider_response LONGTEXT DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_wa_created_at (created_at),
            KEY idx_wa_status (status),
            KEY idx_wa_phone (recipient_phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // silent
    }
}

function log_wa_message($data) {
    global $pdo;
    try {
        ensure_wa_log_table();
        $has = $pdo->query("SHOW TABLES LIKE 'wa_message_logs'")->rowCount() > 0;
        if (!$has) return;

        $sql = "INSERT INTO wa_message_logs
                (provider, recipient_phone_raw, recipient_phone, message_text, media_url, status, provider_response, error_message, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'fonte',
            $data['recipient_phone_raw'] ?? null,
            $data['recipient_phone'] ?? null,
            (string)($data['message'] ?? ''),
            $data['media_url'] ?? null,
            ($data['status'] ?? 'success') === 'failed' ? 'failed' : 'success',
            $data['provider_response'] ?? null,
            $data['error_message'] ?? null,
            $_SESSION['user_id'] ?? null
        ]);
    } catch (Exception $e) {
        // silent
    }
}

function generate_tax_signature_token($invoice_number, $tax_invoice_number, $invoice_date, $grand_total, $customer_npwp, $company_npwp = '') {
    $payload = implode('|', [
        (string)$invoice_number,
        (string)$tax_invoice_number,
        (string)$invoice_date,
        number_format((float)$grand_total, 2, '.', ''),
        preg_replace('/\s+/', '', (string)$customer_npwp),
    ]);
    $secret = 'MMS-TAX-SIGN-V1|' . preg_replace('/\s+/', '', (string)$company_npwp);
    return strtoupper(substr(hash_hmac('sha256', $payload, $secret), 0, 32));
}

function get_coa_id($account_code) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id FROM coa WHERE account_code = ? AND (is_active = 1 OR is_active IS NULL) LIMIT 1");
        $stmt->execute([$account_code]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    } catch (Exception $e) {
        return null;
    }
}

function create_journal($journal_date, $reference_no, $description, $items, $type = 'general') {
    global $pdo;

    if (!is_array($items) || empty($items)) {
        throw new Exception("Item jurnal kosong.");
    }

    $total_debit = 0;
    $total_credit = 0;
    $normalized_items = [];
    foreach ($items as $item) {
        $coa_id = isset($item['coa_id']) ? (int)$item['coa_id'] : 0;
        $debit = isset($item['debit']) ? (float)$item['debit'] : 0;
        $credit = isset($item['credit']) ? (float)$item['credit'] : 0;
        if ($coa_id <= 0 || ($debit <= 0 && $credit <= 0)) {
            continue;
        }
        $normalized_items[] = ['coa_id' => $coa_id, 'debit' => $debit, 'credit' => $credit];
        $total_debit += $debit;
        $total_credit += $credit;
    }

    if (empty($normalized_items)) {
        throw new Exception("Item jurnal tidak valid.");
    }
    if (round($total_debit, 2) !== round($total_credit, 2)) {
        throw new Exception("Jurnal tidak balance.");
    }
    if ($total_debit <= 0) {
        throw new Exception("Nominal jurnal tidak boleh nol.");
    }

    $own_tx = !$pdo->inTransaction();

    try {
        if ($own_tx) {
            $pdo->beginTransaction();
        }

        $ym = date('ym', strtotime($journal_date));
        $stmt_no = $pdo->prepare("SELECT COUNT(*) FROM journals WHERE journal_no LIKE ?");
        $stmt_no->execute(["JRN-$ym-%"]);
        $count = ((int)$stmt_no->fetchColumn()) + 1;
        $journal_no = "JRN-" . $ym . "-" . str_pad((string)$count, 4, '0', STR_PAD_LEFT);

        $sql = "INSERT INTO journals (journal_no, journal_date, reference_no, description, total_debit, total_credit, type, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([
            $journal_no,
            $journal_date,
            $reference_no,
            $description,
            $total_debit,
            $total_credit,
            $type,
            $_SESSION['user_id'] ?? null
        ]);
        $journal_id = (int)$pdo->lastInsertId();

        $coa_ids = array_values(array_unique(array_map(fn($x) => (int)$x['coa_id'], $normalized_items)));
        $placeholders = implode(',', array_fill(0, count($coa_ids), '?'));
        $stmt_coa = $pdo->prepare("SELECT id, normal_balance FROM coa WHERE id IN ($placeholders)");
        $stmt_coa->execute($coa_ids);
        $coa_map = [];
        foreach ($stmt_coa->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $coa_map[(int)$row['id']] = $row['normal_balance'];
        }

        $stmt_item = $pdo->prepare("INSERT INTO journal_items (journal_id, coa_id, debit, credit) VALUES (?, ?, ?, ?)");
        $stmt_upd = $pdo->prepare("UPDATE coa SET current_balance = current_balance + ? WHERE id = ?");

        foreach ($normalized_items as $item) {
            $coa_id = (int)$item['coa_id'];
            $debit = (float)$item['debit'];
            $credit = (float)$item['credit'];

            if (!isset($coa_map[$coa_id])) {
                throw new Exception("COA ID $coa_id tidak ditemukan.");
            }

            $stmt_item->execute([$journal_id, $coa_id, $debit, $credit]);

            $normal = $coa_map[$coa_id];
            $change = ($normal === 'credit') ? ($credit - $debit) : ($debit - $credit);
            $stmt_upd->execute([$change, $coa_id]);
        }

        if ($own_tx) {
            $pdo->commit();
        }
        return $journal_id;

    } catch (Exception $e) {
        if ($own_tx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function delete_journal_by_reference($reference_no, $type = null) {
    global $pdo;

    if (empty($reference_no)) {
        return 0;
    }

    $own_tx = !$pdo->inTransaction();
    try {
        if ($own_tx) {
            $pdo->beginTransaction();
        }

        if ($type === null) {
            $stmt = $pdo->prepare("SELECT id FROM journals WHERE reference_no = ?");
            $stmt->execute([$reference_no]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM journals WHERE reference_no = ? AND type = ?");
            $stmt->execute([$reference_no, $type]);
        }
        $journal_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($journal_ids)) {
            if ($own_tx) {
                $pdo->commit();
            }
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($journal_ids), '?'));
        $sql_items = "SELECT ji.coa_id, ji.debit, ji.credit, c.normal_balance
                      FROM journal_items ji
                      JOIN coa c ON c.id = ji.coa_id
                      WHERE ji.journal_id IN ($placeholders)";
        $stmt_items = $pdo->prepare($sql_items);
        $stmt_items->execute($journal_ids);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        $stmt_upd = $pdo->prepare("UPDATE coa SET current_balance = current_balance + ? WHERE id = ?");
        foreach ($items as $it) {
            $debit = (float)$it['debit'];
            $credit = (float)$it['credit'];
            $normal = $it['normal_balance'];
            $posted_change = ($normal === 'credit') ? ($credit - $debit) : ($debit - $credit);
            $stmt_upd->execute([-$posted_change, (int)$it['coa_id']]);
        }

        $stmt_del_items = $pdo->prepare("DELETE FROM journal_items WHERE journal_id IN ($placeholders)");
        $stmt_del_items->execute($journal_ids);

        $stmt_del_head = $pdo->prepare("DELETE FROM journals WHERE id IN ($placeholders)");
        $stmt_del_head->execute($journal_ids);

        if ($own_tx) {
            $pdo->commit();
        }
        return count($journal_ids);
    } catch (Exception $e) {
        if ($own_tx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function reconcile_coa_current_balances($coa_id = null) {
    global $pdo;

    $coa_id = ($coa_id !== null) ? (int)$coa_id : null;
    $own_tx = !$pdo->inTransaction();
    $updated = 0;

    try {
        if ($own_tx) {
            $pdo->beginTransaction();
        }

        $where = '';
        $params = [];
        if ($coa_id !== null && $coa_id > 0) {
            $where = "WHERE c.id = ?";
            $params[] = $coa_id;
        }

        $sql = "SELECT c.id, c.normal_balance, c.opening_balance, c.current_balance,
                       COALESCE(SUM(ji.debit), 0) AS sum_debit,
                       COALESCE(SUM(ji.credit), 0) AS sum_credit
                FROM coa c
                LEFT JOIN journal_items ji ON ji.coa_id = c.id
                $where
                GROUP BY c.id, c.normal_balance, c.opening_balance, c.current_balance";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt_upd = $pdo->prepare("UPDATE coa SET current_balance = ? WHERE id = ?");
        foreach ($rows as $row) {
            $normal = (string)($row['normal_balance'] ?? 'debit');
            $opening = (float)($row['opening_balance'] ?? 0);
            $sum_debit = (float)($row['sum_debit'] ?? 0);
            $sum_credit = (float)($row['sum_credit'] ?? 0);
            $current = round((float)($row['current_balance'] ?? 0), 2);
            $expected = round(
                $opening + (($normal === 'credit') ? ($sum_credit - $sum_debit) : ($sum_debit - $sum_credit)),
                2
            );

            if (abs($expected - $current) > 0.009) {
                $stmt_upd->execute([$expected, (int)$row['id']]);
                $updated++;
            }
        }

        if ($own_tx) {
            $pdo->commit();
        }
        return $updated;
    } catch (Exception $e) {
        if ($own_tx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function mms_redirect($url) {
    header("Location: $url");
    exit();
}

function get_company_profile() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? $data : [];
    } catch (PDOException $e) {
        return [];
    }
}

// ============================================================================
// 2. RBAC & MENU SYSTEM (RESTORED 100%)
// ============================================================================

function has_permission($permission_slug) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') return true; 
    if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) return false;
    return in_array($permission_slug, $_SESSION['permissions']);
}

function get_sidebar_menus() {
    global $pdo;
    $menus = [];
    $uid = $_SESSION['user_id'] ?? 0;
    $role = $_SESSION['role'] ?? '';

    // 1. Cek Mode Sistem (Role Based / Custom)
    $mode = 'role'; 
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'system_settings'");
        if ($check->rowCount() > 0) {
            $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='menu_mode'");
            if ($res = $stmt->fetchColumn()) {
                $mode = $res;
            }
        }
    } catch (Exception $e) {}

    // 2. Jika Mode Custom
    $custom_access = [];
    if ($mode == 'custom') {
        try {
            $stmt = $pdo->prepare("SELECT menu_slug FROM user_custom_menus WHERE user_id = ?");
            $stmt->execute([$uid]);
            $custom_access = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {}
    }

    $can_access = function($slug, $perm) use ($mode, $custom_access) {
        if ($mode == 'custom') {
            // Dalam mode custom, visibilitas final akan difilter granular per halaman.
            return true;
        } else {
            return has_permission($perm);
        }
    };

    // --- DEFINISI MENU 100% ---

    if ($can_access('dashboard', 'dashboard_view')) {
        $menus[] = ['label' => 'Dashboard', 'url' => 'index.php?page=dashboard', 'icon' => 'bi-speedometer2'];
    }

    if ($can_access('sales', 'sales_view')) {
        $menus[] = [
            'label' => 'Sales & Mkt', 'icon' => 'bi-currency-dollar', 'id' => 'menuSales',
            'submenu' => [
                ['label' => 'Master Customer', 'url' => 'index.php?page=sales-customers', 'icon' => 'bi-people-fill'],
                ['label' => 'Penawaran (Quote)', 'url' => 'index.php?page=sales-quote', 'icon' => 'bi-file-earmark-text'],
                ['label' => 'Sales Order', 'url' => 'index.php?page=sales-so', 'icon' => 'bi-cart-check'],
            ]
        ];
    }

    if ($can_access('eng', 'eng_view')) {
        $menus[] = [
            'label' => 'Engineering', 'icon' => 'bi-tools', 'id' => 'menuEng',
            'submenu' => [
                ['label' => 'Master Barang', 'url' => 'index.php?page=eng-items', 'icon' => 'bi-box'],
                ['label' => 'BOM', 'url' => 'index.php?page=eng-bom', 'icon' => 'bi-diagram-3'],
                ['label' => 'Partlist & Drawing', 'url' => 'index.php?page=eng-partlist', 'icon' => 'bi-file-earmark-ruled'],
            ]
        ];
    }

    if ($can_access('ppic', 'ppic_view')) {
        $menus[] = [
            'label' => 'PPIC', 'icon' => 'bi-calendar-range', 'id' => 'menuPpic',
            'submenu' => [
                ['label' => 'SPK Produksi', 'url' => 'index.php?page=ppic-spk', 'icon' => 'bi-clipboard-data'],
                ['label' => 'Jadwal (MPS)', 'url' => 'index.php?page=ppic-mps', 'icon' => 'bi-calendar-week'],
                ['label' => 'Purchase Req (PR)', 'url' => 'index.php?page=ppic-pr', 'icon' => 'bi-cart-plus'],
                ['label' => 'Inventory', 'url' => 'index.php?page=ppic-inventory', 'icon' => 'bi-boxes'],
            ]
        ];
    }

    if ($can_access('purch', 'purch_view')) {
        $purch_submenu = [
            ['label' => 'Purchase Order', 'url' => 'index.php?page=purch-po', 'icon' => 'bi-receipt'],
            ['label' => 'Vendor List', 'url' => 'index.php?page=purch-vendor', 'icon' => 'bi-shop'],
        ];
        if (mms_is_dev_feature_enabled('purch_rfq')) {
            $purch_submenu[] = ['label' => 'RFQ (Request for Quotation)', 'url' => 'index.php?page=purch-rfq', 'icon' => 'bi-clipboard2-data', 'is_dev' => true];
        }
        if (mms_is_dev_feature_enabled('purch_vendor_rating')) {
            $purch_submenu[] = ['label' => 'Vendor Rating', 'url' => 'index.php?page=purch-vendor-rating', 'icon' => 'bi-star-half', 'is_dev' => true];
        }
        $menus[] = [
            'label' => 'Purchasing', 'icon' => 'bi-bag-check', 'id' => 'menuPurch',
            'submenu' => $purch_submenu
        ];
    }

    if ($can_access('prod', 'prod_view')) {
        $menus[] = [
            'label' => 'Produksi', 'icon' => 'bi-gear-wide', 'id' => 'menuProd',
            'submenu' => [
                ['label' => 'Task Assignment', 'url' => 'index.php?page=prod-task', 'icon' => 'bi-list-task'],
                ['label' => 'Operator Scan', 'url' => 'index.php?page=prod-scan', 'icon' => 'bi-qr-code-scan'],
                ['label' => 'Laporan Harian', 'url' => 'index.php?page=prod-report', 'icon' => 'bi-file-bar-graph'],
            ]
        ];
    }

    if ($can_access('operator', 'prod_operator_access')) {
        $menus[] = ['label' => 'Operator Panel', 'url' => 'index.php?page=prod-operator', 'icon' => 'bi-phone'];
    }

    if ($can_access('qc', 'qc_view')) {
        $menus[] = [
            'label' => 'Quality Control', 'icon' => 'bi-patch-check', 'id' => 'menuQc',
            'submenu' => [
                ['label' => 'QC Incoming', 'url' => 'index.php?page=qc-incoming', 'icon' => 'bi-box-arrow-in-down'],
                ['label' => 'QC Production', 'url' => 'index.php?page=qc-production', 'icon' => 'bi-check-all'],
                ['label' => 'NCR Form', 'url' => 'index.php?page=qc-ncr', 'icon' => 'bi-exclamation-triangle'],
            ]
        ];
    }

    if ($can_access('whse', 'whse_view')) {
        $whse_submenu = [
            ['label' => 'Penerimaan (GR)', 'url' => 'index.php?page=whse-receive', 'icon' => 'bi-box-seam'],
            ['label' => 'Material Issue', 'url' => 'index.php?page=whse-issue', 'icon' => 'bi-box-arrow-right'],
            ['label' => 'Return Material', 'url' => 'index.php?page=whse-return', 'icon' => 'bi-arrow-return-left'],
            ['label' => 'Surat Jalan (SJ)', 'url' => 'index.php?page=whse-sj', 'icon' => 'bi-truck'],
        ];
        if (mms_is_dev_feature_enabled('whse_batch_expiry')) {
            $whse_submenu[] = ['label' => 'Batch & Expiry Tracking', 'url' => 'index.php?page=whse-batch-expiry', 'icon' => 'bi-upc-scan', 'is_dev' => true];
        }
        if (mms_is_dev_feature_enabled('whse_cycle_counting')) {
            $whse_submenu[] = ['label' => 'Cycle Counting', 'url' => 'index.php?page=whse-cycle-counting', 'icon' => 'bi-clipboard2-check', 'is_dev' => true];
        }
        $menus[] = [
            'label' => 'Warehouse', 'icon' => 'bi-house-door', 'id' => 'menuWhse',
            'submenu' => $whse_submenu
        ];
    }

    if ($can_access('fin', 'fin_view')) {
        $menus[] = [
            'label' => 'Finance', 'icon' => 'bi-cash-coin', 'id' => 'menuFin',
            'submenu' => [
                ['label' => 'AR / Invoice', 'url' => 'index.php?page=fin-ar', 'icon' => 'bi-receipt-cutoff'],
                ['label' => 'AP / Payment', 'url' => 'index.php?page=fin-ap', 'icon' => 'bi-wallet2'],
                ['label' => 'Cash / Chasier', 'url' => 'index.php?page=fin-cash', 'icon' => 'bi-cash-stack'],
                ['label' => 'Perpajakan', 'url' => 'index.php?page=fin-tax', 'icon' => 'bi-receipt'],
            ]
        ];
    }

    if ($can_access('acc', 'acc_view')) {
        $menus[] = [
            'label' => 'Accounting', 'icon' => 'bi-journal-bookmark', 'id' => 'menuAcc',
            'submenu' => [
                ['label' => 'COA', 'url' => 'index.php?page=acc-coa', 'icon' => 'bi-list-columns-reverse'],
                ['label' => 'Jurnal Umum', 'url' => 'index.php?page=acc-journal', 'icon' => 'bi-journal-text'],
                ['label' => 'Buku Besar', 'url' => 'index.php?page=acc-ledger', 'icon' => 'bi-book'],
                ['label' => 'Fixed Assets', 'url' => 'index.php?page=acc-assets', 'icon' => 'bi-building-gear'],
                ['label' => 'Laporan Keuangan', 'url' => 'index.php?page=acc-report', 'icon' => 'bi-graph-up'],
            ]
        ];
    }

    if ($can_access('hrd', 'hrd_view')) {
        $menus[] = [
            'label' => 'HRD', 'icon' => 'bi-people-fill', 'id' => 'menuHrd',
            'submenu' => [
                ['label' => 'Absensi', 'url' => 'index.php?page=hrd-attendance', 'icon' => 'bi-clock-history'],
                ['label' => 'Payroll', 'url' => 'index.php?page=hrd-payroll', 'icon' => 'bi-cash-stack'],
                ['label' => 'Karyawan', 'url' => 'index.php?page=hrd-employees', 'icon' => 'bi-person-vcard'],
            ]
        ];
    }

    if ($can_access('exec', 'owner_view')) {
        $menus[] = [
            'label' => 'Executive', 'icon' => 'bi-briefcase-fill', 'id' => 'menuOwner',
            'submenu' => [
                ['label' => 'KPI Dashboard', 'url' => 'index.php?page=exec-kpi', 'icon' => 'bi-trophy'],
                ['label' => 'System Logs', 'url' => 'index.php?page=exec-logs', 'icon' => 'bi-terminal'],
            ]
        ];
    }
    
    if ($can_access('tv', 'dashboard_view')) {
        $menus[] = [
            'label' => 'TV Monitor', 'icon' => 'bi-tv', 'id' => 'menuTV',
            'submenu' => [
                ['label' => 'TV Executive', 'url' => 'index.php?page=tv-exec', 'icon' => 'bi-bar-chart-line'],
                ['label' => 'TV Lobby', 'url' => 'index.php?page=tv-lobby', 'icon' => 'bi-tv'],
                ['label' => 'TV Produksi', 'url' => 'index.php?page=tv-prod', 'icon' => 'bi-display'],
            ]
        ];
    }

    if ($mode == 'custom') {
        // Filter granular: submenu berdasarkan page slug, parent tampil jika ada child terpilih.
        $extract_page_slug = function($url) {
            if (preg_match('/[?&]page=([^&]+)/', (string)$url, $m)) return $m[1];
            return null;
        };
        $legacy_parent_map = [
            'menuSales' => 'sales',
            'menuEng' => 'eng',
            'menuPpic' => 'ppic',
            'menuPurch' => 'purch',
            'menuProd' => 'prod',
            'menuQc' => 'qc',
            'menuWhse' => 'whse',
            'menuFin' => 'fin',
            'menuAcc' => 'acc',
            'menuHrd' => 'hrd',
            'menuOwner' => 'exec',
            'menuTV' => 'tv',
        ];
        $legacy_single_map = [
            'dashboard' => 'dashboard',
            'prod-operator' => 'operator',
        ];

        $filtered = [];
        foreach ($menus as $menu) {
            if (isset($menu['submenu']) && is_array($menu['submenu'])) {
                $parent_legacy = $legacy_parent_map[$menu['id'] ?? ''] ?? null;
                $allow_all_from_legacy = !empty($parent_legacy) && in_array($parent_legacy, $custom_access, true);
                $submenu_filtered = [];
                foreach ($menu['submenu'] as $sub) {
                    $sub_slug = $extract_page_slug($sub['url'] ?? '');
                    if (empty($sub_slug)) continue;
                    if ($allow_all_from_legacy || in_array($sub_slug, $custom_access, true)) {
                        $submenu_filtered[] = $sub;
                    }
                }
                if (!empty($submenu_filtered)) {
                    $menu['submenu'] = $submenu_filtered;
                    $filtered[] = $menu;
                }
            } else {
                $slug = $extract_page_slug($menu['url'] ?? '');
                $legacy = $legacy_single_map[$slug] ?? null;
                if (($slug && in_array($slug, $custom_access, true)) || ($legacy && in_array($legacy, $custom_access, true))) {
                    $filtered[] = $menu;
                }
            }
        }
        $menus = $filtered;
    }

    if ($role === 'admin') {
        $menus[] = [
            'label'   => 'Administrator',
            'icon'    => 'bi-person-badge-fill',
            'id'      => 'submenuAdmin',
            'submenu' => [
                ['label' => 'Manajemen User', 'url' => 'index.php?page=users', 'icon' => 'bi-people'],
                ['label' => 'Master Mesin', 'url' => 'index.php?page=admin-machines', 'icon' => 'bi-hdd-rack'],
                ['label' => 'Wizard Setup', 'url' => 'index.php?page=admin-setup-wizard', 'icon' => 'bi-magic text-primary'],
                ['label' => 'Custom Menu', 'url' => 'index.php?page=admin-menu', 'icon' => 'bi-grid-1x2-fill text-warning'],
                ['label' => 'RBAC & Role', 'url' => 'index.php?page=roles', 'icon' => 'bi-shield-lock'],
                ['label' => 'Hak Akses', 'url' => 'index.php?page=role-permissions', 'icon' => 'bi-key-fill'],
                ['label' => 'Identitas Perusahaan', 'url' => 'index.php?page=admin-company', 'icon' => 'bi-building-gear'],
                ['label' => 'WA Logs', 'url' => 'index.php?page=admin-wa-logs', 'icon' => 'bi-whatsapp'],
                ['label' => 'Backup Database', 'url' => 'index.php?page=admin-backup', 'icon' => 'bi-cloud-download-fill'],
                ['label' => 'Reset System', 'url' => 'index.php?page=admin-reset', 'icon' => 'bi-exclamation-octagon-fill text-danger']
            ]
        ];
    }

    return $menus;
}

// ============================================================================
// 3. NOTIFICATION & LOGGING (FIXED FOR NO CRASH)
// ============================================================================

function send_notification($user_id, $title, $message, $link, $type = 'info', $target_role = null) {
    global $pdo;
    try {
        $sender = $_SESSION['user_id'] ?? 0;
        $target_role = is_null($target_role) ? '' : (string)$target_role;
        $has_user_id = false;
        try {
            $has_user_id = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'user_id'")->rowCount() > 0;
        } catch (Exception $e) {
            $has_user_id = false;
        }

        if ($has_user_id) {
            $sql = "INSERT INTO notifications (sender_id, user_id, target_role, title, message, link, type) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$sender, $user_id, $target_role, $title, $message, $link, $type]);
        } else {
            $sql = "INSERT INTO notifications (sender_id, target_role, title, message, link, type) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$sender, $target_role, $title, $message, $link, $type]);
        }
    } catch (Exception $e) {}
}

function ensure_workflow_notification_table() {
    global $pdo;
    static $checked = false;
    if ($checked) return;

    // Hindari DDL saat transaksi aktif (MySQL dapat melakukan implicit commit).
    // Jika dipanggil di tengah transaksi bisnis, skip saja; notifikasi tetap bisa jalan
    // tanpa dedup persistent dan proses utama tidak terganggu.
    try {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            return;
        }
    } catch (Exception $e) {
        // lanjutkan best-effort
    }

    $checked = true;

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS workflow_notification_events (
            id INT NOT NULL AUTO_INCREMENT,
            event_key VARCHAR(120) NOT NULL,
            event_hash CHAR(64) NOT NULL,
            recipient_user_id INT NOT NULL,
            last_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_event_recipient (event_hash, recipient_user_id),
            KEY idx_event_key_time (event_key, last_sent_at),
            KEY idx_recipient_time (recipient_user_id, last_sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // Jika gagal create table, helper tetap lanjut tanpa dedup persistent.
    }
}

function notify_workflow_event($event_key, $title, $message, $link, $type = 'info', $options = []) {
    global $pdo;

    try {
        $event_key = trim((string)$event_key);
        if ($event_key === '') $event_key = 'workflow.generic';

        $ttl_seconds = isset($options['ttl_seconds']) ? (int)$options['ttl_seconds'] : 180;
        if ($ttl_seconds < 0) $ttl_seconds = 0;
        $include_admin = isset($options['include_admin']) ? (bool)$options['include_admin'] : true;
        $exclude_sender = isset($options['exclude_sender']) ? (bool)$options['exclude_sender'] : true;

        $permission_slug = $options['permission_slug'] ?? null;
        $target_roles = $options['target_roles'] ?? [];
        $user_ids = $options['user_ids'] ?? [];
        if (!is_array($target_roles)) $target_roles = [$target_roles];
        if (!is_array($user_ids)) $user_ids = [$user_ids];

        $recipient_ids = [];

        if (!empty($permission_slug)) {
            $sql_perm = "SELECT DISTINCT u.id
                         FROM users u
                         JOIN roles r ON r.id = u.role_id
                         JOIN role_permissions rp ON rp.role_id = r.id
                         JOIN permissions p ON p.id = rp.permission_id
                         WHERE p.permission_slug = ?";
            $stmt_perm = $pdo->prepare($sql_perm);
            $stmt_perm->execute([$permission_slug]);
            foreach ($stmt_perm->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                $recipient_ids[(int)$uid] = true;
            }
        }

        if (!empty($target_roles)) {
            $placeholders = implode(',', array_fill(0, count($target_roles), '?'));
            $sql_role = "SELECT DISTINCT u.id
                         FROM users u
                         JOIN roles r ON r.id = u.role_id
                         WHERE r.role_slug IN ($placeholders)";
            $stmt_role = $pdo->prepare($sql_role);
            $stmt_role->execute(array_values($target_roles));
            foreach ($stmt_role->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                $recipient_ids[(int)$uid] = true;
            }
        }

        foreach ($user_ids as $uid) {
            $uid = (int)$uid;
            if ($uid > 0) $recipient_ids[$uid] = true;
        }

        if ($include_admin) {
            $sql_admin = "SELECT u.id
                          FROM users u
                          JOIN roles r ON r.id = u.role_id
                          WHERE r.role_slug = 'admin'";
            $stmt_admin = $pdo->query($sql_admin);
            foreach ($stmt_admin->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                $recipient_ids[(int)$uid] = true;
            }
        }

        if (empty($recipient_ids)) {
            return;
        }

        if ($exclude_sender) {
            $sender = (int)($_SESSION['user_id'] ?? 0);
            if ($sender > 0 && isset($recipient_ids[$sender])) {
                unset($recipient_ids[$sender]);
            }
        }

        if (empty($recipient_ids)) {
            return;
        }

        ensure_workflow_notification_table();
        $event_hash = hash('sha256', $event_key . '|' . $title . '|' . $message . '|' . $link);
        $can_dedup = false;
        try {
            $can_dedup = $pdo->query("SHOW TABLES LIKE 'workflow_notification_events'")->rowCount() > 0;
        } catch (Exception $e) {
            $can_dedup = false;
        }

        $stmt_last = null;
        $stmt_upsert = null;
        if ($can_dedup) {
            $stmt_last = $pdo->prepare("SELECT last_sent_at FROM workflow_notification_events WHERE event_hash = ? AND recipient_user_id = ? LIMIT 1");
            $stmt_upsert = $pdo->prepare("INSERT INTO workflow_notification_events (event_key, event_hash, recipient_user_id, last_sent_at)
                                          VALUES (?, ?, ?, NOW())
                                          ON DUPLICATE KEY UPDATE event_key=VALUES(event_key), last_sent_at=VALUES(last_sent_at)");
        }

        foreach (array_keys($recipient_ids) as $uid) {
            $uid = (int)$uid;
            if ($uid <= 0) continue;

            $skip = false;
            if ($can_dedup && $stmt_last && $ttl_seconds > 0) {
                $stmt_last->execute([$event_hash, $uid]);
                $last = $stmt_last->fetchColumn();
                if (!empty($last) && (time() - strtotime($last) < $ttl_seconds)) {
                    $skip = true;
                }
            }

            if ($skip) continue;

            send_notification($uid, $title, $message, $link, $type);
            if ($can_dedup && $stmt_upsert) {
                $stmt_upsert->execute([$event_key, $event_hash, $uid]);
            }
        }
    } catch (Exception $e) {
        // Notifikasi tidak boleh menghentikan proses utama.
    }
}

function broadcast_notification($permission_slug, $title, $message, $link, $type = 'info') {
    global $pdo;

    try {
        $recipient_ids = [];

        // 1) Ambil user berdasarkan permission yang dituju.
        $sql_perm = "SELECT DISTINCT u.id
                     FROM users u
                     JOIN roles r ON r.id = u.role_id
                     JOIN role_permissions rp ON rp.role_id = r.id
                     JOIN permissions p ON p.id = rp.permission_id
                     WHERE p.permission_slug = ?";
        $stmt_perm = $pdo->prepare($sql_perm);
        $stmt_perm->execute([$permission_slug]);
        foreach ($stmt_perm->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $recipient_ids[(int) $uid] = true;
        }

        // 2) Sertakan admin sebagai fallback agar notifikasi kritikal tidak terlewat.
        $sql_admin = "SELECT u.id
                      FROM users u
                      JOIN roles r ON r.id = u.role_id
                      WHERE r.role_slug = 'admin'";
        $stmt_admin = $pdo->query($sql_admin);
        foreach ($stmt_admin->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $recipient_ids[(int) $uid] = true;
        }

        foreach (array_keys($recipient_ids) as $uid) {
            send_notification($uid, $title, $message, $link, $type);
        }
    } catch (Exception $e) {
        // Silent fail: notifikasi tidak boleh memblokir proses utama.
    }
}

function get_unread_notifications_count($user_id) {
    global $pdo;
    $role = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? null);
    if (empty($role)) return 0;
    try {
        // Mendukung pencarian via user_id (Lama) atau target_role (Baru)
        $sql = "SELECT COUNT(*) FROM notifications 
                WHERE (user_id = ? OR LOWER(target_role) = LOWER(?)) 
                AND is_read = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $role]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0; // Failsafe agar tidak Error 500
    }
}

function get_my_notifications($user_id) {
    global $pdo;
    $role = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? null);
    if (empty($role)) return [];
    try {
        $sql = "SELECT * FROM notifications 
                WHERE (user_id = ? OR LOWER(target_role) = LOWER(?)) 
                ORDER BY created_at DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $role]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function record_log($module, $action, $description) {
    global $pdo;
    $uid = $_SESSION['user_id'] ?? 0;
    $name = $_SESSION['fullname'] ?? 'System';
    $role = $_SESSION['role'] ?? 'guest';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    try {
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, user_name, role, module, action, description, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$uid, $name, $role, $module, $action, $description, $ip]);
    } catch (Exception $e) {}
}

// ============================================================================
// 5. TEMPLATE RENDERING
// ============================================================================

function render_header($title = "MMS System") {
    $role_name = $_SESSION['role_name'] ?? ($_SESSION['role'] ?? '');
    $comp_profile = get_company_profile();
    $logo_path = $comp_profile['logo_path'] ?? '';
    $logo_url = mms_asset_url($logo_path, true);
    $company_name = $comp_profile['company_name'] ?? 'MMS System';
    $available_themes = mms_get_available_themes();
    $active_theme = mms_get_effective_theme_slug($comp_profile);
    $active_theme_meta = $available_themes[$active_theme] ?? $available_themes['original'];
    $bodyClass = !is_logged_in() ? 'login-body' : '';
    if (is_logged_in() && !empty($active_theme_meta['body_class'])) {
        $bodyClass = trim($bodyClass . ' ' . (string)$active_theme_meta['body_class']);
    }

    $user_avatar = '';
    if (is_logged_in()) {
        try {
            $uid = (int)($_SESSION['user_id'] ?? 0);
            if ($uid > 0) {
                $stmt_user = $GLOBALS['pdo']->prepare("SELECT avatar_path FROM users WHERE id = ? LIMIT 1");
                $stmt_user->execute([$uid]);
                $user_avatar = (string)($stmt_user->fetchColumn() ?: '');
            }
        } catch (Exception $e) {
            $user_avatar = '';
        }
    }
    
        if (!empty($logo_url)) {
        $logo_html = '<img src="'.$logo_url.'" alt="Logo" class="img-fluid" style="max-width: 207px; max-height: 92px; object-fit: contain; display: block; margin: 0 auto; border-radius: 10px;">';
    } else {
        $logo_html = '<i class="bi bi-gear-wide-connected" style="font-size: 3rem; color: #2c3e50;"></i>';
    }

    $notif_count = 0; $notif_list = [];
    if(is_logged_in()) {
        $notif_count = get_unread_notifications_count($_SESSION['user_id']);
        $notif_list = get_my_notifications($_SESSION['user_id']);
    }
    $icon_palette = [
        'tone-blue', 'tone-teal', 'tone-green', 'tone-cyan', 'tone-orange',
        'tone-red', 'tone-indigo', 'tone-pink', 'tone-yellow', 'tone-slate'
    ];
    $pick_icon_tone = function($seed) use ($icon_palette) {
        $key = (string)($seed ?? '');
        $idx = abs(crc32($key)) % count($icon_palette);
        return $icon_palette[$idx];
    };
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $title ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        <link href="assets/css/style.css" rel="stylesheet">
        <?php if (!empty($active_theme_meta['css_path'])): ?>
            <link href="<?= mms_asset_url((string)$active_theme_meta['css_path'], true) ?>" rel="stylesheet">
        <?php endif; ?>
        <style>
            .sidebar-logo-container { padding: 20px 15px; background: #fff; border-bottom: 1px solid #eee; min-height: 120px; text-align: center; }
            .sidebar-submenu .nav-link { font-size: 0.9em; padding-left: 2.8rem !important; display: flex; align-items: center; gap: 10px; }
            .notif-unread { background-color: #f0f7ff; }
            .sidebar-menu-icon {
                width: 1.45rem;
                height: 1.45rem;
                border-radius: 0.45rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 0.86rem;
                flex-shrink: 0;
            }
            .tone-blue { color: #0b63f6; background: rgba(11, 99, 246, 0.14); }
            .tone-teal { color: #0f766e; background: rgba(15, 118, 110, 0.14); }
            .tone-green { color: #2b8a3e; background: rgba(43, 138, 62, 0.14); }
            .tone-cyan { color: #0891b2; background: rgba(8, 145, 178, 0.14); }
            .tone-orange { color: #d97706; background: rgba(217, 119, 6, 0.14); }
            .tone-red { color: #dc2626; background: rgba(220, 38, 38, 0.14); }
            .tone-indigo { color: #4f46e5; background: rgba(79, 70, 229, 0.14); }
            .tone-pink { color: #db2777; background: rgba(219, 39, 119, 0.14); }
            .tone-yellow { color: #a16207; background: rgba(161, 98, 7, 0.16); }
            .tone-slate { color: #334155; background: rgba(51, 65, 85, 0.14); }
        </style>
    </head>
    <body class="<?= $bodyClass ?>">
    <?php if(is_logged_in()): ?>
    <div class="wrapper">
        <?php include mms_abs_path('includes/sidebar.php'); ?>
        <div id="sidebarOverlay" aria-hidden="true"></div>
        <div id="content">
            <?php include mms_abs_path('includes/topbar.php'); ?>
            <div class="container-fluid">
    <?php endif;
}

function render_footer() {
    $comp_profile_footer = get_company_profile();
    $footer_company_name = trim((string)($comp_profile_footer['company_name'] ?? 'MMS System'));
    if ($footer_company_name === '') $footer_company_name = 'MMS System';
    if(is_logged_in()): ?>
            </div>
            <footer class="mms-app-footer mt-auto">
                <div class="mms-app-footer-main">Copyright &copy; 2026 <?= clean($footer_company_name) ?></div>
                <div class="mms-app-footer-sub">Manufacturing Management System (Supported by CCT-NET)</div>
            </footer>
        </div>
    </div>
            <script>
                (function () {
                    const sidebar = document.getElementById('sidebar');
                    const toggle = document.getElementById('sidebarCollapse');
                    if (!sidebar || !toggle) return;

                    const isMobile = () => window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
                    const overlay = document.getElementById('sidebarOverlay');

                    toggle.addEventListener('click', () => {
                        sidebar.classList.toggle('active');
                    });

                    overlay?.addEventListener('click', () => {
                        if (!isMobile()) return;
                        sidebar.classList.remove('active');
                    });

                    sidebar.addEventListener('click', (event) => {
                        if (!isMobile()) return;
                        const link = event.target.closest('a');
                        if (!link) return;
                        if (link.getAttribute('data-bs-toggle') === 'collapse') return;
                        sidebar.classList.remove('active');
                    });

                })();
            </script>
    <?php endif; ?>
    <?php include mms_abs_path('includes/footer_modals.php'); ?>
    </body></html>
<?php } ?>
