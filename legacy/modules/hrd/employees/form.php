<?php
// modules/hrd/employees/form.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('hrd_employee_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=hrd-employees';</script>";
    exit;
}

$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = $id ? true : false;
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

$data = [
    'username' => '',
    'fullname' => '',
    'role_id' => '',
    'nik' => '',
    'phone' => '',
    'address' => '',
    'department' => '',
    'join_date' => date('Y-m-d'),
    'employee_status' => 'probation',
    'basic_salary' => 0,
    'bank_account' => ''
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $fetch = $stmt->fetch();
    if(!$fetch) die("Data karyawan tidak ditemukan.");
    $data = array_merge($data, $fetch);
}

// PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    } else {
    // Data Login
    $username = clean($_POST['username']);
    $password = $_POST['password']; // Kosong jika edit & tidak ganti pass
    $role_id  = $_POST['role_id'];
    $fullname = clean($_POST['fullname']);
    
    // Data HRD
    $nik      = clean($_POST['nik']);
    $phone    = clean($_POST['phone']);
    $addr     = clean($_POST['address']);
    $dept     = clean($_POST['department']);
    $join     = $_POST['join_date'];
    $status   = $_POST['employee_status'];
    $salary   = floatval(str_replace('.', '', $_POST['basic_salary']));
    $bank     = clean($_POST['bank_account']);

    try {
        // Cek Username Duplikat
        if ($is_edit) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
            $check->execute([$username, $id]);
        } else {
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $check->execute([$username]);
        }
        
        if ($check->fetchColumn() > 0) throw new Exception("Username '$username' sudah digunakan!");

        if (!$is_edit) {
            // INSERT BARU
            if (empty($password)) throw new Exception("Password wajib diisi untuk karyawan baru.");
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (username, password, fullname, role_id, nik, phone, address, department, join_date, employee_status, basic_salary, bank_account) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$username, $hash, $fullname, $role_id, $nik, $phone, $addr, $dept, $join, $status, $salary, $bank]);

        } else {
            // UPDATE
            $sql = "UPDATE users SET username=?, fullname=?, role_id=?, nik=?, phone=?, address=?, department=?, join_date=?, employee_status=?, basic_salary=?, bank_account=?";
            $params = [$username, $fullname, $role_id, $nik, $phone, $addr, $dept, $join, $status, $salary, $bank];

            if (!empty($password)) {
                $sql .= ", password=?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id=?";
            $params[] = $id;

            $pdo->prepare($sql)->execute($params);
        }

        echo "<script>alert('Data Karyawan berhasil disimpan!'); window.location='index.php?page=hrd-employees';</script>";
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    }
}

// Load Roles
$roles = $pdo->query("SELECT * FROM roles ORDER BY role_name ASC")->fetchAll();

render_header($is_edit ? "Edit Karyawan" : "Tambah Karyawan");
?>

<form method="POST">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="row justify-content-center">
        <!-- Kolom Kiri: Data Pribadi & Login -->
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">Data Pribadi & Akun</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>NIK <span class="text-danger">*</span></label>
                            <input type="text" name="nik" class="form-control" value="<?= $data['nik'] ?>" required placeholder="KRY-001">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label>Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="fullname" class="form-control" value="<?= $data['fullname'] ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>No. HP / WA</label>
                            <input type="text" name="phone" class="form-control" value="<?= $data['phone'] ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Departemen</label>
                            <select name="department" class="form-select">
                                <option value="">-- Pilih --</option>
                                <option value="Production" <?= $data['department']=='Production'?'selected':'' ?>>Production</option>
                                <option value="Sales" <?= $data['department']=='Sales'?'selected':'' ?>>Sales & Marketing</option>
                                <option value="PPIC" <?= $data['department']=='PPIC'?'selected':'' ?>>PPIC / Warehouse</option>
                                <option value="Finance" <?= $data['department']=='Finance'?'selected':'' ?>>Finance & Acc</option>
                                <option value="HRD" <?= $data['department']=='HRD'?'selected':'' ?>>HRD / GA</option>
                                <option value="Management" <?= $data['department']=='Management'?'selected':'' ?>>Management</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Alamat Domisili</label>
                        <textarea name="address" class="form-control" rows="2"><?= $data['address'] ?></textarea>
                    </div>

                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Username (Login)</label>
                            <input type="text" name="username" class="form-control fw-bold" value="<?= $data['username'] ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" <?= $is_edit?'':'required' ?> placeholder="<?= $is_edit?'(Biarkan kosong jika tetap)':'' ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label>Role / Jabatan Sistem <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select" required>
                            <option value="">-- Pilih Role --</option>
                            <?php foreach($roles as $r): 
                                $selected = $r['id'] == $data['role_id'] ? 'selected' : '';
                            ?>
                                <option value="<?= $r['id'] ?>" <?= $selected ?>><?= $r['role_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Kepegawaian & Gaji -->
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">Status & Penggajian</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Tanggal Masuk</label>
                            <input type="date" name="join_date" class="form-control" value="<?= $data['join_date'] ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Status Karyawan</label>
                            <select name="employee_status" class="form-select">
                                <option value="probation" <?= $data['employee_status']=='probation'?'selected':'' ?>>Probation (Percobaan)</option>
                                <option value="contract" <?= $data['employee_status']=='contract'?'selected':'' ?>>Contract (PKWT)</option>
                                <option value="permanent" <?= $data['employee_status']=='permanent'?'selected':'' ?>>Permanent (Tetap)</option>
                                <option value="resigned" <?= $data['employee_status']=='resigned'?'selected':'' ?>>Resigned (Keluar)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Gaji Pokok (Basic Salary)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" name="basic_salary" class="form-control fw-bold text-end" 
                                   value="<?= number_format($data['basic_salary'], 0, ',', '.') ?>" onkeyup="formatRibuan(this)">
                        </div>
                        <small class="text-muted">Akan digunakan sebagai default di modul Payroll.</small>
                    </div>

                    <div class="mb-3">
                        <label>Info Rekening Bank</label>
                        <input type="text" name="bank_account" class="form-control" value="<?= $data['bank_account'] ?>" placeholder="Nama Bank - No. Rekening">
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="index.php?page=hrd-employees" class="btn btn-secondary btn-lg px-4">Kembali</a>
                <button type="submit" class="btn btn-primary btn-lg px-5 shadow">Simpan Data</button>
            </div>
        </div>
    </div>
</form>

<script>
function formatRibuan(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    input.value = new Intl.NumberFormat('id-ID').format(value);
}
</script>

<?php render_footer(); ?>
