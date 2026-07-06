<?php
// modules/sales/customers/form.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('sales_customer_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=sales-customers';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$data = [
    'customer_code' => '', 
    'name' => '',
    'address' => '',
    'phone' => '',
    'pic' => '',
    'email' => '',
    'tax_id' => '',
    'tax_invoice_number' => ''
];

if ($is_edit) {
    // Mode Edit: Ambil data dari database
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    if(!$data) die("Customer tidak ditemukan");
    
    // Pastikan key tax_id ada
    if (!isset($data['tax_id'])) $data['tax_id'] = '';
    if (!isset($data['tax_invoice_number'])) $data['tax_invoice_number'] = '';

} else {
    // Mode Tambah: GENERATE AUTO CODE (CT-XXX) - 3 DIGIT
    
    // 1. Ambil kode terakhir
    $stmt_last = $pdo->query("SELECT customer_code FROM customers WHERE customer_code LIKE 'CT-%' ORDER BY id DESC LIMIT 1");
    $last_code = $stmt_last->fetchColumn();

    if ($last_code) {
        // Ambil angka dari string (misal CT-001 -> ambil 001)
        // substr 3 karakter ('CT-')
        $last_no = intval(substr($last_code, 3));
        $new_no = $last_no + 1;
    } else {
        $new_no = 1;
    }
    
    // Format ulang ke 3 digit (misal: 1 -> 001)
    $data['customer_code'] = 'CT-' . str_pad($new_no, 3, '0', STR_PAD_LEFT);
}

// PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    } else {
        $code = $_POST['customer_code'];
        $name = clean($_POST['name']);
        $addr = clean($_POST['address']);
        $phone = clean($_POST['phone']);
        $pic = clean($_POST['pic']);
        $email = clean($_POST['email']);
        $npwp = clean($_POST['tax_id']); 
        $nsfp_raw = trim($_POST['tax_invoice_number'] ?? '');
        $nsfp = preg_replace('/\s+/', '', $nsfp_raw);

        if ($nsfp !== '' && !preg_match('/^\d{3}\.\d{3}-\d{2}\.\d{8}$/', $nsfp)) {
            $error = "Format No. Seri Faktur Pajak tidak valid. Gunakan format 000.000-YY.12345678";
        }

        if (!isset($error)) try {
            if (!$is_edit) {
                // INSERT BARU
                
                // Cek duplikat kode
                $chk = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE customer_code = ?");
                $chk->execute([$code]);
                if ($chk->fetchColumn() > 0) {
                    // Regenerate nomor jika duplikat saat proses simpan
                    $new_no++;
                    $code = 'CT-' . str_pad($new_no, 3, '0', STR_PAD_LEFT); // Pastikan 3 Digit
                }
                
                // Cek duplikat nama
                $chk_name = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE name = ?");
                $chk_name->execute([$name]);
                if ($chk_name->fetchColumn() > 0) {
                    throw new Exception("Nama Customer '$name' sudah terdaftar!");
                }

                $sql = "INSERT INTO customers (customer_code, name, address, phone, pic, email, tax_id, tax_invoice_number, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$code, $name, $addr, $phone, $pic, $email, $npwp, $nsfp !== '' ? $nsfp : null, $_SESSION['user_id']]);
            
            } else {
                // UPDATE EXISTING
                $sql = "UPDATE customers SET name=?, address=?, phone=?, pic=?, email=?, tax_id=?, tax_invoice_number=? WHERE id=?";
                $pdo->prepare($sql)->execute([$name, $addr, $phone, $pic, $email, $npwp, $nsfp !== '' ? $nsfp : null, $id]);
            }

            echo "<script>alert('Data Customer berhasil disimpan!'); window.location='index.php?page=sales-customers';</script>";
            exit;

        } catch (Exception $e) {
            $error = "Gagal menyimpan data customer.";
        }
    }
}

render_header($is_edit ? "Edit Customer" : "Tambah Customer");
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-person-vcard"></i> Form Data Customer</h5>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= $esc($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
                    <div class="row">
                        <div class="col-md-6 border-end">
                            <h6 class="text-primary mb-3">Identitas Perusahaan</h6>
                            
                            <div class="mb-3">
                                <label class="fw-bold">Kode Customer <span class="text-danger">*</span></label>
                                <input type="text" name="customer_code" class="form-control bg-light fw-bold text-primary" value="<?= $esc($data['customer_code']) ?>" readonly>
                                <div class="form-text small">Auto Generate (CT-XXX).</div>
                            </div>

                            <div class="mb-3">
                                <label>Nama Perusahaan <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="<?= $esc($data['name']) ?>" required placeholder="Contoh: PT. Maju Jaya">
                            </div>

                            <div class="mb-3">
                                <label>NPWP (Tax ID)</label>
                                <input type="text" name="tax_id" class="form-control" value="<?= $esc($data['tax_id']) ?>" placeholder="00.000.000.0-000.000">
                            </div>

                            <div class="mb-3">
                                <label>No. Seri Faktur Pajak (Default)</label>
                                <input type="text" name="tax_invoice_number" class="form-control" value="<?= $esc($data['tax_invoice_number']) ?>" placeholder="010.000-24.00000001">
                                <div class="form-text small">Format: 000.000-YY.12345678</div>
                            </div>

                            <div class="mb-3">
                                <label>Alamat Lengkap</label>
                                <textarea name="address" class="form-control" rows="3" placeholder="Alamat kirim / tagihan"><?= $esc($data['address']) ?></textarea>
                            </div>
                        </div>

                        <div class="col-md-6 ps-4">
                            <h6 class="text-primary mb-3">Kontak Person</h6>

                            <div class="mb-3">
                                <label>PIC (Contact Person)</label>
                                <input type="text" name="pic" class="form-control" value="<?= $esc($data['pic']) ?>" placeholder="Nama Kontak">
                            </div>

                            <div class="mb-3">
                                <label>No. Telepon / HP</label>
                                <input type="text" name="phone" class="form-control" value="<?= $esc($data['phone']) ?>">
                            </div>

                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?= $esc($data['email']) ?>" placeholder="email@perusahaan.com">
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mt-3">
                        <a href="index.php?page=sales-customers" class="btn btn-secondary px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-5 fw-bold"><i class="bi bi-save"></i> Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
