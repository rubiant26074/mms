<?php
// modules/engineering/machines/form.php
if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('eng_machine_manage')) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=admin-machines';</script>";
    exit;
}

$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = $id ? true : false;
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$data = ['machine_code'=>'', 'machine_name'=>'', 'process_type'=>'', 'status'=>'active', 'location'=>'', 'notes'=>''];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM machines WHERE id = ?");
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
    $code = clean($_POST['machine_code']);
    $name = clean($_POST['machine_name']);
    $type = clean($_POST['process_type']);
    $status = clean($_POST['status']);
    $loc = clean($_POST['location']);
    $notes = clean($_POST['notes']);

    try {
        if ($is_edit) {
            // Cek duplikat
            $check = $pdo->prepare("SELECT COUNT(*) FROM machines WHERE machine_code = ? AND id != ?");
            $check->execute([$code, $id]);
            if($check->fetchColumn() > 0) throw new Exception("Kode Mesin sudah digunakan!");

            $sql = "UPDATE machines SET machine_code=?, machine_name=?, process_type=?, status=?, location=?, notes=? WHERE id=?";
            $pdo->prepare($sql)->execute([$code, $name, $type, $status, $loc, $notes, $id]);
        } else {
            $check = $pdo->prepare("SELECT COUNT(*) FROM machines WHERE machine_code = ?");
            $check->execute([$code]);
            if($check->fetchColumn() > 0) throw new Exception("Kode Mesin sudah digunakan!");

            $sql = "INSERT INTO machines (machine_code, machine_name, process_type, status, location, notes) VALUES (?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$code, $name, $type, $status, $loc, $notes]);
        }

        // --- TRIGGER NOTIFIKASI MESIN PROBLEM ---
        if ($status == 'broken' || $status == 'maintenance') {
            broadcast_notification(
                'prod_view', // Kirim ke Tim Produksi & PPIC
                'Mesin Down / Maintenance', 
                "Mesin $name ($code) saat ini berstatus " . strtoupper($status) . ". Mohon sesuaikan jadwal produksi.", 
                'index.php?page=admin-machines', 
                'danger'
            );
        }

        echo "<script>alert('Data Mesin tersimpan!'); window.location='index.php?page=admin-machines';</script>";
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    }
}

render_header($is_edit ? "Edit Mesin" : "Tambah Mesin");
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Form Data Mesin</h5>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?><div class="alert alert-danger"><?= $esc($error) ?></div><?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Kode Mesin <span class="text-danger">*</span></label>
                            <input type="text" name="machine_code" class="form-control fw-bold" value="<?= $esc($data['machine_code']) ?>" required placeholder="MC-XXX-01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Nama Mesin <span class="text-danger">*</span></label>
                            <input type="text" name="machine_name" class="form-control" value="<?= $esc($data['machine_name']) ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Tipe Proses <span class="text-danger">*</span></label>
                            <select name="process_type" class="form-select" required>
                                <option value="">-- Pilih Proses --</option>
                                <option value="Fibre Laser" <?= $data['process_type']=='Fibre Laser'?'selected':'' ?>>Fibre Laser</option>
                                <option value="CO Laser" <?= $data['process_type']=='CO Laser'?'selected':'' ?>>CO Laser</option>
                                <option value="Metal Bending" <?= $data['process_type']=='Metal Bending'?'selected':'' ?>>Metal Bending</option>
                                <option value="Acrylic Bending" <?= $data['process_type']=='Acrylic Bending'?'selected':'' ?>>Acrylic Bending</option>
                                <option value="Welding" <?= $data['process_type']=='Welding'?'selected':'' ?>>Welding</option>
                                <option value="Assembling" <?= $data['process_type']=='Assembling'?'selected':'' ?>>Assembling</option>
                                <option value="Powder Coating" <?= $data['process_type']=='Powder Coating'?'selected':'' ?>>Powder Coating</option>
                                <option value="Machining" <?= $data['process_type']=='Machining'?'selected':'' ?>>Machining</option>
                                <option value="Other" <?= $data['process_type']=='Other'?'selected':'' ?>>Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Status Operasional</label>
                            <select name="status" class="form-select fw-bold">
                                <option value="active" <?= $data['status']=='active'?'selected':'' ?> class="text-success">Active (Siap Jalan)</option>
                                <option value="maintenance" <?= $data['status']=='maintenance'?'selected':'' ?> class="text-warning">Maintenance (Perbaikan)</option>
                                <option value="broken" <?= $data['status']=='broken'?'selected':'' ?> class="text-danger">Broken (Rusak)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Lokasi / Line</label>
                        <input type="text" name="location" class="form-control" value="<?= $esc($data['location']) ?>" placeholder="Contoh: Line A, Gedung 2">
                    </div>

                    <div class="mb-3">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"><?= $esc($data['notes']) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php?page=admin-machines" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
