<?php
// modules/users/settings.php

if (!is_logged_in()) {
    mms_redirect('index.php');
}

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
    render_header("User Setting");
    echo "<div class='alert alert-danger m-4'>User tidak valid.</div>";
    render_footer();
    exit;
}

// Pastikan kolom avatar_path ada.
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) NULL AFTER signature_path");
} catch (Exception $e) {
    // ignore
}
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS face_reference_path VARCHAR(255) NULL AFTER avatar_path");
} catch (Exception $e) {
    // ignore
}

// Load user data
$stmt = $pdo->prepare("SELECT username, fullname, role_id, signature_path, avatar_path, face_reference_path, password FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    render_header("User Setting");
    echo "<div class='alert alert-danger m-4'>Data user tidak ditemukan.</div>";
    render_footer();
    exit;
}

// Role name
$role_name = $_SESSION['role_name'] ?? ($_SESSION['role'] ?? '');

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($current === '' || $new === '' || $confirm === '') {
            $error = "Semua field password wajib diisi.";
        } elseif (!password_verify($current, $user['password'])) {
            $error = "Password lama tidak sesuai.";
        } elseif (strlen($new) < 6) {
            $error = "Password baru minimal 6 karakter.";
        } elseif ($new !== $confirm) {
            $error = "Konfirmasi password tidak cocok.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);
            $success = "Password berhasil diubah.";
        }
    } elseif ($action === 'profile') {
        $sig_path = $user['signature_path'] ?? '';
        $avatar_path = $user['avatar_path'] ?? '';
        $face_reference_path = $user['face_reference_path'] ?? '';
        $upload_errors = [];

        // Upload signature
        if (isset($_FILES['signature_file']) && is_array($_FILES['signature_file'])) {
            $sig_err = null;
            $new_sig = mms_store_uploaded_image($_FILES['signature_file'], mms_upload_target('signature'), 'sig_' . $uid, $sig_err, ['png', 'jpg', 'jpeg']);
            if ($new_sig === false) {
                $upload_errors[] = "Tanda tangan: " . $sig_err;
            } elseif (is_string($new_sig) && $new_sig !== '') {
                if (!empty($sig_path) && stripos((string)$sig_path, 'http://') !== 0 && stripos((string)$sig_path, 'https://') !== 0) {
                    $old_sig_abs = mms_abs_path(ltrim((string)$sig_path, '/'));
                    if (is_file($old_sig_abs)) {
                        @unlink($old_sig_abs);
                    }
                }
                $sig_path = $new_sig;
            }
        }

        // Upload avatar
        if (isset($_FILES['avatar_file']) && is_array($_FILES['avatar_file'])) {
            $ava_err = null;
            $new_avatar = mms_store_uploaded_image($_FILES['avatar_file'], mms_upload_target('avatar'), 'ava_' . $uid, $ava_err, ['png', 'jpg', 'jpeg']);
            if ($new_avatar === false) {
                $upload_errors[] = "Avatar: " . $ava_err;
            } elseif (is_string($new_avatar) && $new_avatar !== '') {
                if (!empty($avatar_path) && stripos((string)$avatar_path, 'http://') !== 0 && stripos((string)$avatar_path, 'https://') !== 0) {
                    $old_ava_abs = mms_abs_path(ltrim((string)$avatar_path, '/'));
                    if (is_file($old_ava_abs)) {
                        @unlink($old_ava_abs);
                    }
                }
                $avatar_path = $new_avatar;
            }
        }

        if (isset($_FILES['face_reference_file']) && is_array($_FILES['face_reference_file'])) {
            $face_err = null;
            $new_face = mms_store_uploaded_image($_FILES['face_reference_file'], mms_upload_target('face_reference'), 'face_' . $uid, $face_err, ['png', 'jpg', 'jpeg']);
            if ($new_face === false) {
                $upload_errors[] = "Registrasi wajah: " . $face_err;
            } elseif (is_string($new_face) && $new_face !== '') {
                if (!empty($face_reference_path) && stripos((string)$face_reference_path, 'http://') !== 0 && stripos((string)$face_reference_path, 'https://') !== 0) {
                    $old_face_abs = mms_abs_path(ltrim((string)$face_reference_path, '/'));
                    if (is_file($old_face_abs)) {
                        @unlink($old_face_abs);
                    }
                }
                $face_reference_path = $new_face;
            }
        }

        $pdo->prepare("UPDATE users SET signature_path=?, avatar_path=?, face_reference_path=? WHERE id=?")->execute([$sig_path, $avatar_path, $face_reference_path, $uid]);
        if (empty($upload_errors)) {
            $success = "Profil berhasil diperbarui.";
        } else {
            $error = implode(' ', $upload_errors);
            $success = "Sebagian data profil berhasil diperbarui.";
        }
        $user['signature_path'] = $sig_path;
        $user['avatar_path'] = $avatar_path;
        $user['face_reference_path'] = $face_reference_path;
    }
}

render_header("User Setting");
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">User Setting</div>
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" value="<?= clean($user['fullname'] ?? '') ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jabatan</label>
                        <input type="text" class="form-control" value="<?= clean($role_name) ?>" readonly>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" class="mb-4">
                    <input type="hidden" name="action" value="profile">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Upload Tanda Tangan (PNG/JPG)</label>
                            <input type="file" name="signature_file" class="form-control" accept=".png,.jpg,.jpeg">
                            <?php if (!empty($user['signature_path'])): ?>
                                <div class="mt-2">
                                    <img src="<?= clean($user['signature_path']) ?>" alt="Signature" style="height:60px; max-width:200px; object-fit:contain;">
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Upload Avatar (PNG/JPG)</label>
                            <input type="file" name="avatar_file" class="form-control" accept=".png,.jpg,.jpeg,image/*">
                            <?php if (!empty($user['avatar_path'])): ?>
                                <div class="mt-2">
                                    <img src="<?= clean($user['avatar_path']) ?>" alt="Avatar" class="rounded-circle" style="height:60px; width:60px; object-fit:cover;">
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Registrasi Wajah Absensi (Selfie Kamera Depan)</label>
                            <input type="file" name="face_reference_file" class="form-control" accept="image/*" capture="user">
                            <div class="form-text">Gunakan kamera depan Android untuk mendaftarkan wajah referensi absensi.</div>
                            <?php if (!empty($user['face_reference_path'])): ?>
                                <div class="mt-2">
                                    <img src="<?= clean($user['face_reference_path']) ?>" alt="Face Reference" style="height:110px; width:110px; object-fit:cover;" class="rounded border">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan Profil</button>
                </form>

                <form method="POST">
                    <input type="hidden" name="action" value="password">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Password Lama</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Password Baru</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Konfirmasi Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-dark">Ubah Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
