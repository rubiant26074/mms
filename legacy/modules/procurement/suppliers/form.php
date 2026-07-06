<?php
// modules/procurement/suppliers/form.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('purch_vendor_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=purch-vendor';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$data = [
    'code'=>'', 'name'=>'', 'address'=>'', 'phone'=>'', 
    'email'=>'', 'contact_person'=>'', 'bank_name'=>'', 'bank_number'=>''
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    if(!$data) die("Data tidak ditemukan.");
}

// PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    } else {
        $code = clean($_POST['code']);
        $name = clean($_POST['name']);
        $address = clean($_POST['address']);
        $phone = clean($_POST['phone']);
        $email = clean($_POST['email']);
        $cp = clean($_POST['contact_person']);
        $bank = clean($_POST['bank_name']);
        $rek = clean($_POST['bank_number']);

        try {
            if ($is_edit) {
                // Cek duplikat kode
                $check = $pdo->prepare("SELECT COUNT(*) FROM suppliers WHERE code = ? AND id != ?");
                $check->execute([$code, $id]);
                if($check->fetchColumn() > 0) throw new Exception("Kode Vendor sudah digunakan!");

                $sql = "UPDATE suppliers SET code=?, name=?, address=?, phone=?, email=?, contact_person=?, bank_name=?, bank_number=? WHERE id=?";
                $pdo->prepare($sql)->execute([$code, $name, $address, $phone, $email, $cp, $bank, $rek, $id]);
            } else {
                $check = $pdo->prepare("SELECT COUNT(*) FROM suppliers WHERE code = ?");
                $check->execute([$code]);
                if($check->fetchColumn() > 0) throw new Exception("Kode Vendor sudah digunakan!");

                $sql = "INSERT INTO suppliers (code, name, address, phone, email, contact_person, bank_name, bank_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$code, $name, $address, $phone, $email, $cp, $bank, $rek]);
            }
            echo "<script>alert('Data Vendor tersimpan!'); window.location='index.php?page=purch-vendor';</script>";
            exit;
        } catch (Exception $e) {
            $msg = (string)$e->getMessage();
            $error = (stripos($msg, 'Kode Vendor sudah digunakan!') !== false)
                ? 'Kode Vendor sudah digunakan!'
                : 'Terjadi kesalahan saat menyimpan data vendor.';
        }
    }
}

render_header($is_edit ? "Edit Vendor" : "Tambah Vendor");
?>

<div class="row justify-content-center">
    <div class="col-md-9">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><?= $is_edit ? "Edit Vendor" : "Registrasi Vendor Baru" ?></h5>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?><div class="alert alert-danger"><?= $esc($error) ?></div><?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
                    <h6 class="text-primary border-bottom pb-2 mb-3">Identitas Vendor</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Kode Vendor <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" value="<?= $esc($data['code']) ?>" required placeholder="SUP-00X">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label>Nama Perusahaan / Toko <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= $esc($data['name']) ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Alamat Lengkap</label>
                        <textarea name="address" class="form-control" rows="2"><?= $esc($data['address']) ?></textarea>
                    </div>

                    <h6 class="text-primary border-bottom pb-2 mb-3 mt-3">Kontak Person</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Nama CP (Sales)</label>
                            <input type="text" name="contact_person" class="form-control" value="<?= $esc($data['contact_person']) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>No. Telepon / HP</label>
                            <input type="text" name="phone" class="form-control" value="<?= $esc($data['phone']) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="<?= $esc($data['email']) ?>">
                        </div>
                    </div>

                    <h6 class="text-primary border-bottom pb-2 mb-3 mt-3">Informasi Pembayaran</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Nama Bank</label>
                            <input type="text" name="bank_name" class="form-control" value="<?= $esc($data['bank_name']) ?>" placeholder="BCA / Mandiri">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Nomor Rekening</label>
                            <input type="text" name="bank_number" class="form-control" value="<?= $esc($data['bank_number']) ?>">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php?page=purch-vendor" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
