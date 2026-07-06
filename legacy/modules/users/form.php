<?php
// modules/users/form.php

$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = $id ? true : false;
$user_data = ['username'=>'', 'fullname'=>'', 'role_id'=>'', 'signature_path'=>'']; 

// Ambil data user jika mode edit
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user_data = $stmt->fetch();
    if (!$user_data) die("User tidak ditemukan");
}

// PROSES SUBMIT FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username']);
    $fullname = clean($_POST['fullname']);
    $role_id  = clean($_POST['role_id']); 
    $password = $_POST['password'];

    // --- LOGIC UPLOAD TANDA TANGAN ---
    $signature_path = $user_data['signature_path']; // Default pake lama

    if (isset($_FILES['signature']) && is_array($_FILES['signature'])) {
        $uid = $id ? $id : uniqid();
        $upload_err = null;
        $new_sig = mms_store_uploaded_image(
            $_FILES['signature'],
            mms_upload_target('signature'),
            'sig_' . $uid,
            $upload_err,
            ['jpg', 'jpeg', 'png']
        );
        if ($new_sig === false) {
            $error = "Gagal mengupload gambar tanda tangan: " . $upload_err;
        } elseif (is_string($new_sig) && $new_sig !== '') {
            // Hapus file lama jika ada dan file lokal.
            if (!empty($user_data['signature_path']) && stripos((string)$user_data['signature_path'], 'http://') !== 0 && stripos((string)$user_data['signature_path'], 'https://') !== 0) {
                $old_abs = mms_abs_path(ltrim((string)$user_data['signature_path'], '/'));
                if (is_file($old_abs)) {
                    @unlink($old_abs);
                }
            }
            $signature_path = $new_sig;
        }
    }

    if (!isset($error)) {
        try {
            // Cek Duplikat Username
            if ($is_edit) {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                $stmt_check->execute([$username, $id]);
            } else {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt_check->execute([$username]);
            }

            if ($stmt_check->fetchColumn() > 0) {
                throw new Exception("Username '$username' sudah terdaftar!");
            }

            // PROSES SIMPAN KE DB
            if ($is_edit) {
                // UPDATE
                $query = "UPDATE users SET username=?, fullname=?, role_id=?, signature_path=?";
                $params = [$username, $fullname, $role_id, $signature_path];

                if (!empty($password)) {
                    $query .= ", password=?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                
                $query .= " WHERE id=?";
                $params[] = $id;
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);

            } else {
                // INSERT
                if (empty($password)) throw new Exception("Password wajib diisi untuk user baru!");
                
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (username, fullname, role_id, password, signature_path) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, $fullname, $role_id, $hash, $signature_path]);
            }
            
            echo "<script>alert('Data User berhasil disimpan!'); window.location='index.php?page=users';</script>";
            exit;

        } catch (Exception $e) {
            $error = "Gagal: " . $e->getMessage();
        }
    }
}

render_header($is_edit ? "Edit User" : "Tambah User Baru");
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><?= $is_edit ? "Edit User" : "Tambah User Baru" ?></h5>
            </div>
            <div class="card-body">
                
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?= $error ?></div>
                    </div>
                <?php endif; ?>

                <!-- Tambahkan enctype="multipart/form-data" agar bisa upload file -->
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" value="<?= $user_data['username'] ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="fullname" class="form-control" value="<?= $user_data['fullname'] ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Role / Jabatan <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select" required>
                            <option value="">-- Pilih Role --</option>
                            <?php
                            $roles = $pdo->query("SELECT * FROM roles ORDER BY role_name ASC")->fetchAll();
                            foreach($roles as $r):
                                $selected = ($user_data['role_id'] == $r['id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $r['id'] ?>" <?= $selected ?>>
                                    <?= $r['role_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Password <?= $is_edit ? '<small class="text-muted">(Kosongkan jika tidak ingin mengubah)</small>' : '<span class="text-danger">*</span>' ?></label>
                        <input type="password" name="password" class="form-control" <?= $is_edit ? '' : 'required' ?>>
                    </div>

                    <div class="mb-3">
                        <label>Tanda Tangan Digital (Scan/Foto)</label>
                        
                        <!-- Preview Gambar -->
                        <?php if (!empty($user_data['signature_path'])): ?>
                            <div class="mb-2 p-2 border rounded bg-light" style="width: fit-content;">
                                <img src="<?= $user_data['signature_path'] ?>" alt="Signature" style="height: 80px;">
                                <div class="small text-muted mt-1 text-center">Current Signature</div>
                            </div>
                        <?php endif; ?>

                        <input type="file" name="signature" class="form-control" accept="image/png, image/jpeg">
                        <div class="form-text">Format: JPG/PNG. Background transparan disarankan. Max 2MB.</div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php?page=users" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
