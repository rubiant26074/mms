<?php
// modules/hrd/attendance/index.php

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS face_reference_path VARCHAR(255) NULL AFTER avatar_path");
} catch (Exception $e) {
    // ignore
}

$attendance_alter_queries = [
    "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_in_photo VARCHAR(255) NULL AFTER clock_in",
    "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_out_photo VARCHAR(255) NULL AFTER clock_out",
    "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_in_latitude DECIMAL(10,7) NULL AFTER clock_in_photo",
    "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_in_longitude DECIMAL(10,7) NULL AFTER clock_in_latitude",
    "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_out_latitude DECIMAL(10,7) NULL AFTER clock_out_photo",
    "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_out_longitude DECIMAL(10,7) NULL AFTER clock_out_latitude",
    "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_in_distance_meters DECIMAL(10,2) NULL AFTER clock_in_longitude",
    "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_out_distance_meters DECIMAL(10,2) NULL AFTER clock_out_longitude",
    "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS attendance_method VARCHAR(30) NULL AFTER notes",
];
foreach ($attendance_alter_queries as $attendance_alter_query) {
    try {
        $pdo->exec($attendance_alter_query);
    } catch (Exception $e) {
        // ignore
    }
}

$company_alter_queries = [
    "ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS attendance_location_name VARCHAR(150) NULL AFTER ui_theme",
    "ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS attendance_latitude DECIMAL(10,7) NULL AFTER attendance_location_name",
    "ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS attendance_longitude DECIMAL(10,7) NULL AFTER attendance_latitude",
    "ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS attendance_radius_meters INT NULL AFTER attendance_longitude",
];
foreach ($company_alter_queries as $company_alter_query) {
    try {
        $pdo->exec($company_alter_query);
    } catch (Exception $e) {
        // ignore
    }
}

render_header("Absensi Karyawan");

$user_id = (int)($_SESSION['user_id'] ?? 0);
$today = date('Y-m-d');
$now = date('H:i:s');
$success = null;
$error = null;

$user_stmt = $pdo->prepare("SELECT id, fullname, face_reference_path FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$current_user = $user_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$face_reference_path = (string)($current_user['face_reference_path'] ?? '');

$company_stmt = $pdo->query("SELECT attendance_location_name, attendance_latitude, attendance_longitude, attendance_radius_meters FROM company_profile WHERE id = 1");
$attendance_cfg = $company_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$office_name = trim((string)($attendance_cfg['attendance_location_name'] ?? ''));
$office_lat = mms_to_float_or_null($attendance_cfg['attendance_latitude'] ?? null);
$office_lng = mms_to_float_or_null($attendance_cfg['attendance_longitude'] ?? null);
$office_radius = (int)($attendance_cfg['attendance_radius_meters'] ?? 0);
$geo_required = ($office_lat !== null && $office_lng !== null && $office_radius > 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = (string)($_POST['type'] ?? '');
    $latitude = mms_to_float_or_null($_POST['latitude'] ?? null);
    $longitude = mms_to_float_or_null($_POST['longitude'] ?? null);
    $distance = ($geo_required ? mms_haversine_distance_meters($latitude, $longitude, $office_lat, $office_lng) : null);

    if ($face_reference_path === '') {
        $error = "Wajah belum didaftarkan. Silakan masuk ke User Setting lalu registrasikan wajah terlebih dahulu.";
    } elseif (!in_array($type, ['in', 'out'], true)) {
        $error = "Tipe absensi tidak valid.";
    } elseif ($latitude === null || $longitude === null) {
        $error = "Lokasi GPS wajib aktif saat absen.";
    } elseif ($geo_required && ($distance === null || $distance > $office_radius)) {
        $distance_label = ($distance !== null ? number_format($distance, 1, ',', '.') . ' m' : 'tidak diketahui');
        $office_label = ($office_name !== '' ? $office_name : 'titik absensi admin');
        $error = "Lokasi Anda di luar radius absensi. Jarak saat ini {$distance_label} dari {$office_label}.";
    } else {
        $photo_field = ($type === 'in') ? 'selfie_in' : 'selfie_out';
        $photo_error = null;
        $photo_path = mms_store_uploaded_image($_FILES[$photo_field] ?? null, mms_upload_target('attendance_selfie'), 'att_' . $type . '_' . $user_id, $photo_error, ['jpg', 'jpeg', 'png']);
        if ($photo_path === null) {
            $error = "Foto selfie wajib diambil dari kamera depan.";
        } elseif ($photo_path === false) {
            $error = "Foto selfie gagal diupload: " . $photo_error;
        } else {
            if ($type === 'in') {
                $check = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
                $check->execute([$user_id, $today]);
                if ($check->fetch()) {
                    $error = "Anda sudah melakukan absen masuk hari ini.";
                } else {
                    $status = ($now > '08:15:00') ? 'late' : 'present';
                    $stmt = $pdo->prepare(
                        "INSERT INTO attendance (
                            user_id, date, clock_in, clock_in_photo, clock_in_latitude, clock_in_longitude, clock_in_distance_meters, status, attendance_method
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([$user_id, $today, $now, $photo_path, $latitude, $longitude, $distance, $status, 'selfie_geotag']);
                    $success = "Berhasil absen masuk pada jam {$now}.";
                }
            } else {
                $check = $pdo->prepare("SELECT id, clock_out FROM attendance WHERE user_id = ? AND date = ?");
                $check->execute([$user_id, $today]);
                $existing_attendance = $check->fetch(PDO::FETCH_ASSOC);
                if (!$existing_attendance) {
                    $error = "Absen pulang tidak bisa dilakukan sebelum absen masuk.";
                } elseif (!empty($existing_attendance['clock_out'])) {
                    $error = "Anda sudah melakukan absen pulang hari ini.";
                } else {
                    $stmt = $pdo->prepare(
                        "UPDATE attendance
                         SET clock_out = ?, clock_out_photo = ?, clock_out_latitude = ?, clock_out_longitude = ?, clock_out_distance_meters = ?, attendance_method = ?
                         WHERE user_id = ? AND date = ?"
                    );
                    $stmt->execute([$now, $photo_path, $latitude, $longitude, $distance, 'selfie_geotag', $user_id, $today]);
                    $success = "Berhasil absen pulang pada jam {$now}.";
                }
            }
        }
    }
}

$stmt_my = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
$stmt_my->execute([$user_id, $today]);
$my_att = $stmt_my->fetch(PDO::FETCH_ASSOC);

$has_in = $my_att ? true : false;
$has_out = ($my_att && !empty($my_att['clock_out'])) ? true : false;
$is_hrd = has_permission('hrd_attendance_manage');
$filter_date = isset($_GET['date']) ? $_GET['date'] : $today;

if ($is_hrd) {
    $sql_list = "SELECT a.*, u.fullname, r.role_name
                 FROM attendance a
                 JOIN users u ON a.user_id = u.id
                 JOIN roles r ON u.role_id = r.id
                 WHERE a.date = ?
                 ORDER BY a.clock_in ASC";
    $params = [$filter_date];
} else {
    $sql_list = "SELECT a.*, u.fullname, r.role_name
                 FROM attendance a
                 JOIN users u ON a.user_id = u.id
                 JOIN roles r ON u.role_id = r.id
                 WHERE a.user_id = ?
                 ORDER BY a.date DESC LIMIT 30";
    $params = [$user_id];
}

$stmt_list = $pdo->prepare($sql_list);
$stmt_list->execute($params);
$list = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card shadow border-primary h-100">
            <div class="card-header bg-primary text-white text-center">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> Absensi Selfie</h5>
                <small><?= date('l, d F Y') ?></small>
            </div>
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert-danger"><?= clean($error) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?= clean($success) ?></div><?php endif; ?>

                <div class="text-center mb-3">
                    <h2 class="fw-bold text-dark mb-2" id="clockDisplay">00:00:00</h2>
                    <?php if ($office_name !== '' || $geo_required): ?>
                        <div class="small text-muted">
                            Lokasi target:
                            <strong><?= clean($office_name !== '' ? $office_name : 'Titik Admin') ?></strong>
                            <?php if ($geo_required): ?>
                                <br>Radius valid: <?= (int)$office_radius ?> meter
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($face_reference_path === ''): ?>
                    <div class="alert alert-warning">
                        Registrasi wajah belum ada. Buka `User Setting` untuk mengambil foto wajah referensi terlebih dahulu.
                    </div>
                <?php else: ?>
                    <div class="text-center mb-3">
                        <div class="small text-muted mb-2">Wajah referensi terdaftar</div>
                        <img src="<?= clean($face_reference_path) ?>" alt="Face Reference" class="rounded border" style="width:100px; height:100px; object-fit:cover;">
                    </div>
                <?php endif; ?>

                <?php if (!$has_in): ?>
                    <form method="POST" enctype="multipart/form-data" class="attendance-form" data-form-type="in">
                        <input type="hidden" name="type" value="in">
                        <input type="hidden" name="latitude" class="latitude-field">
                        <input type="hidden" name="longitude" class="longitude-field">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Foto Selfie Masuk</label>
                            <input type="file" name="selfie_in" class="form-control" accept="image/*" capture="user" required>
                            <div class="form-text">Gunakan kamera depan Android untuk foto selfie absensi masuk.</div>
                        </div>
                        <div class="small text-muted mb-3 geo-status">Lokasi belum diambil.</div>
                        <button type="button" class="btn btn-outline-primary w-100 mb-2 btn-detect-geo">
                            <i class="bi bi-crosshair"></i> Ambil Lokasi GPS
                        </button>
                        <button type="submit" class="btn btn-success btn-lg w-100 py-3 shadow">
                            <i class="bi bi-box-arrow-in-right display-6 d-block mb-1"></i>
                            ABSEN MASUK
                        </button>
                    </form>
                <?php elseif (!$has_out): ?>
                    <div class="alert alert-success py-2 mb-3">
                        Masuk: <strong><?= date('H:i', strtotime((string)$my_att['clock_in'])) ?></strong>
                        <?php if (!empty($my_att['clock_in_distance_meters'])): ?>
                            <br><small>Jarak: <?= number_format((float)$my_att['clock_in_distance_meters'], 1, ',', '.') ?> m</small>
                        <?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" class="attendance-form" data-form-type="out">
                        <input type="hidden" name="type" value="out">
                        <input type="hidden" name="latitude" class="latitude-field">
                        <input type="hidden" name="longitude" class="longitude-field">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Foto Selfie Pulang</label>
                            <input type="file" name="selfie_out" class="form-control" accept="image/*" capture="user" required>
                            <div class="form-text">Gunakan kamera depan Android untuk foto selfie absensi pulang.</div>
                        </div>
                        <div class="small text-muted mb-3 geo-status">Lokasi belum diambil.</div>
                        <button type="button" class="btn btn-outline-primary w-100 mb-2 btn-detect-geo">
                            <i class="bi bi-crosshair"></i> Ambil Lokasi GPS
                        </button>
                        <button type="submit" class="btn btn-danger btn-lg w-100 py-3 shadow" onclick="return confirm('Yakin ingin absen pulang?')">
                            <i class="bi bi-box-arrow-left display-6 d-block mb-1"></i>
                            ABSEN PULANG
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-check-circle-fill display-4 d-block mb-2"></i>
                        <h5>Kehadiran Selesai</h5>
                        <p class="mb-0">
                            In: <strong><?= date('H:i', strtotime((string)$my_att['clock_in'])) ?></strong><br>
                            Out: <strong><?= date('H:i', strtotime((string)$my_att['clock_out'])) ?></strong>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">
                    <?= $is_hrd ? "Rekap Harian: " . date('d/m/Y', strtotime($filter_date)) : "Riwayat Kehadiran Saya" ?>
                </h6>

                <?php if ($is_hrd): ?>
                <form class="d-flex" method="GET">
                    <input type="hidden" name="page" value="hrd-attendance">
                    <input type="date" name="date" class="form-control form-control-sm me-2" value="<?= clean($filter_date) ?>">
                    <button type="submit" class="btn btn-sm btn-primary">Cari</button>
                </form>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-striped">
                        <thead class="table-light">
                            <tr>
                                <?php if ($is_hrd): ?>
                                    <th>Nama Karyawan</th>
                                    <th>Jabatan</th>
                                <?php else: ?>
                                    <th>Tanggal</th>
                                <?php endif; ?>
                                <th class="text-center">Jam Masuk</th>
                                <th class="text-center">Jam Pulang</th>
                                <th class="text-center">Selfie</th>
                                <th class="text-center">Geotag</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($list)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada data absensi.</td></tr>
                            <?php else: foreach ($list as $row):
                                $badge = ($row['status'] == 'late') ? 'bg-warning text-dark' : (($row['status'] == 'absent') ? 'bg-danger' : 'bg-success');
                                $in_photo = trim((string)($row['clock_in_photo'] ?? ''));
                                $out_photo = trim((string)($row['clock_out_photo'] ?? ''));
                                $geo_parts = [];
                                if (!empty($row['clock_in_distance_meters'])) {
                                    $geo_parts[] = 'IN ' . number_format((float)$row['clock_in_distance_meters'], 1, ',', '.') . 'm';
                                }
                                if (!empty($row['clock_out_distance_meters'])) {
                                    $geo_parts[] = 'OUT ' . number_format((float)$row['clock_out_distance_meters'], 1, ',', '.') . 'm';
                                }
                            ?>
                            <tr>
                                <?php if ($is_hrd): ?>
                                    <td><strong><?= clean($row['fullname']) ?></strong></td>
                                    <td><small class="text-muted"><?= clean($row['role_name']) ?></small></td>
                                <?php else: ?>
                                    <td><?= date('d/m/Y', strtotime((string)$row['date'])) ?></td>
                                <?php endif; ?>

                                <td class="text-center fw-bold"><?= !empty($row['clock_in']) ? date('H:i', strtotime((string)$row['clock_in'])) : '-' ?></td>
                                <td class="text-center"><?= !empty($row['clock_out']) ? date('H:i', strtotime((string)$row['clock_out'])) : '-' ?></td>
                                <td class="text-center">
                                    <?php if ($in_photo !== ''): ?><a href="<?= clean($in_photo) ?>" target="_blank" class="btn btn-sm btn-outline-success mb-1">Masuk</a><?php endif; ?>
                                    <?php if ($out_photo !== ''): ?><a href="<?= clean($out_photo) ?>" target="_blank" class="btn btn-sm btn-outline-danger">Pulang</a><?php endif; ?>
                                    <?php if ($in_photo === '' && $out_photo === ''): ?>-<?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?= !empty($geo_parts) ? clean(implode(' | ', $geo_parts)) : '-' ?>
                                </td>
                                <td class="text-center"><span class="badge <?= $badge ?>"><?= strtoupper(clean((string)$row['status'])) ?></span></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { hour12: false });
    const clock = document.getElementById('clockDisplay');
    if (clock) {
        clock.textContent = timeString;
    }
}
setInterval(updateClock, 1000);
updateClock();

document.querySelectorAll('.attendance-form').forEach(function (form) {
    const detectButton = form.querySelector('.btn-detect-geo');
    const latField = form.querySelector('.latitude-field');
    const lngField = form.querySelector('.longitude-field');
    const geoStatus = form.querySelector('.geo-status');

    function setGeoStatus(message, isError = false) {
        if (!geoStatus) return;
        geoStatus.textContent = message;
        geoStatus.classList.toggle('text-danger', isError);
        geoStatus.classList.toggle('text-muted', !isError);
    }

    detectButton?.addEventListener('click', function () {
        if (!navigator.geolocation) {
            setGeoStatus('Browser tidak mendukung geolocation.', true);
            return;
        }
        setGeoStatus('Mengambil lokasi GPS...');
        navigator.geolocation.getCurrentPosition(function (position) {
            latField.value = position.coords.latitude.toFixed(7);
            lngField.value = position.coords.longitude.toFixed(7);
            const accuracy = Math.round(position.coords.accuracy || 0);
            setGeoStatus('Lokasi terekam. Akurasi ±' + accuracy + ' meter.');
        }, function (error) {
            setGeoStatus('Gagal mengambil lokasi: ' + error.message, true);
        }, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        });
    });

    form.addEventListener('submit', function (event) {
        if (!latField.value || !lngField.value) {
            event.preventDefault();
            setGeoStatus('Silakan ambil lokasi GPS terlebih dahulu.', true);
        }
    });
});
</script>

<?php render_footer(); ?>
