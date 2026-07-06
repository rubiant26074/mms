<?php
/**
 * Development feature toggle config.
 *
 * Mode 1 (included via require): return array config untuk helper
 * mms_get_development_config().
 *
 * Mode 2 (direct browser access): render halaman setting sederhana
 * untuk mengaktifkan/menonaktifkan modul under development.
 */

$dev_defaults = [
    'enabled' => false,
    'features' => [
        'whse_batch_expiry' => false,
        'whse_cycle_counting' => false,
        'purch_rfq' => false,
        'purch_vendor_rating' => false,
    ],
];

$settings_file = __DIR__ . DIRECTORY_SEPARATOR . 'development.settings.json';

$normalize_dev_config = static function ($raw) use ($dev_defaults) {
    $cfg = $dev_defaults;

    if (!is_array($raw)) {
        return $cfg;
    }

    if (array_key_exists('enabled', $raw)) {
        $cfg['enabled'] = (bool)$raw['enabled'];
    }

    if (isset($raw['features']) && is_array($raw['features'])) {
        foreach ($cfg['features'] as $feature_key => $default_value) {
            if (array_key_exists($feature_key, $raw['features'])) {
                $cfg['features'][$feature_key] = (bool)$raw['features'][$feature_key];
            } else {
                $cfg['features'][$feature_key] = (bool)$default_value;
            }
        }
    }

    return $cfg;
};

$read_dev_config = static function () use ($settings_file, $normalize_dev_config, $dev_defaults) {
    if (!is_file($settings_file)) {
        return $dev_defaults;
    }

    $json = @file_get_contents($settings_file);
    if ($json === false || trim($json) === '') {
        return $dev_defaults;
    }

    $decoded = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $dev_defaults;
    }

    return $normalize_dev_config($decoded);
};

$write_dev_config = static function (array $cfg) use ($settings_file) {
    $encoded = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return false;
    }

    $bytes = @file_put_contents($settings_file, $encoded . PHP_EOL);
    return ($bytes !== false);
};

$script_file = isset($_SERVER['SCRIPT_FILENAME']) ? (string)$_SERVER['SCRIPT_FILENAME'] : '';
$is_direct_access = ($script_file !== '' && realpath($script_file) === __FILE__);

if (!$is_direct_access) {
    return $read_dev_config();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
$is_admin = ($role === 'admin');
$dev_password_hash = '';
$env_hash = trim((string)getenv('MMS_DEV_TOGGLE_PASSWORD_HASH'));
if ($env_hash !== '') {
    $dev_password_hash = $env_hash;
} else {
    $secret_file = __DIR__ . DIRECTORY_SEPARATOR . 'development.secret.php';
    if (is_file($secret_file)) {
        $secret_cfg = require $secret_file;
        if (is_array($secret_cfg) && !empty($secret_cfg['password_hash'])) {
            $dev_password_hash = trim((string)$secret_cfg['password_hash']);
        }
    }
}

$current = $read_dev_config();
$message = '';
$error = '';

if (!$is_admin) {
    http_response_code(403);
}

if ($is_admin && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $session_token = (string)($_SESSION['dev_config_token'] ?? '');
    $posted_token = (string)($_POST['_token'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if ($session_token === '' || !hash_equals($session_token, $posted_token)) {
        $error = 'Token keamanan tidak valid. Silakan refresh halaman lalu coba lagi.';
    } elseif ($confirm_password === '') {
        $error = 'Konfirmasi password wajib diisi.';
    } elseif ($dev_password_hash === '') {
        $error = 'Password developer belum dikonfigurasi.';
    } elseif (!password_verify($confirm_password, $dev_password_hash)) {
        $error = 'Password developer salah.';
    } else {
        $updated = [
            'enabled' => isset($_POST['enabled']),
            'features' => [
                'whse_batch_expiry' => isset($_POST['whse_batch_expiry']),
                'whse_cycle_counting' => isset($_POST['whse_cycle_counting']),
                'purch_rfq' => isset($_POST['purch_rfq']),
                'purch_vendor_rating' => isset($_POST['purch_vendor_rating']),
            ],
        ];

        $updated = $normalize_dev_config($updated);
        if ($write_dev_config($updated)) {
            $current = $updated;
            $message = 'Pengaturan development berhasil disimpan.';
        } else {
            $error = 'Gagal menyimpan pengaturan. Periksa permission folder modules/.';
        }
    }
}

try {
    $_SESSION['dev_config_token'] = bin2hex(random_bytes(16));
} catch (Exception $e) {
    $_SESSION['dev_config_token'] = sha1(uniqid('dev', true));
}
$csrf_token = (string)$_SESSION['dev_config_token'];

$esc = static function ($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
};
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Development Config</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f7fb;
        }
        .dev-wrap {
            max-width: 760px;
            margin: 30px auto;
        }
        .dev-card {
            border: 0;
            box-shadow: 0 10px 24px rgba(2, 17, 37, 0.08);
            border-radius: 14px;
        }
    </style>
</head>
<body>
<div class="container dev-wrap">
    <div class="card dev-card">
        <div class="card-body p-4 p-md-5">
            <h4 class="mb-1">Development Module Toggle</h4>
            <div class="text-muted mb-4">Aktifkan menu/modul yang masih under development.</div>

            <?php if (!$is_admin): ?>
                <div class="alert alert-danger mb-0">
                    Akses ditolak. Halaman ini hanya untuk role admin.
                </div>
            <?php else: ?>
                <?php if ($message !== ''): ?>
                    <div class="alert alert-success"><?= $esc($message) ?></div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger"><?= $esc($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" <?= !empty($current['enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="enabled">Enable Under Development Menu</label>
                        <div class="text-muted small">Jika OFF: menu dev tidak muncul dan modul dev tidak bisa diakses.</div>
                    </div>

                    <hr class="my-4">
                    <div class="fw-semibold mb-2">Warehouse Features</div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="whse_batch_expiry" name="whse_batch_expiry" <?= !empty($current['features']['whse_batch_expiry']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="whse_batch_expiry">Batch &amp; Expiry Tracking</label>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="whse_cycle_counting" name="whse_cycle_counting" <?= !empty($current['features']['whse_cycle_counting']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="whse_cycle_counting">Cycle Counting</label>
                    </div>

                    <hr class="my-4">
                    <div class="fw-semibold mb-2">Purchasing Features</div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="purch_rfq" name="purch_rfq" <?= !empty($current['features']['purch_rfq']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="purch_rfq">RFQ (Request for Quotation)</label>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="purch_vendor_rating" name="purch_vendor_rating" <?= !empty($current['features']['purch_vendor_rating']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="purch_vendor_rating">Vendor Rating</label>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label fw-semibold">Password Developer</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Masukkan password developer" autocomplete="off" required>
                        <div class="form-text">Password ini terpisah dari password akun admin.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Simpan</button>
                        <a class="btn btn-outline-secondary" href="../index.php">Kembali ke Dashboard</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
