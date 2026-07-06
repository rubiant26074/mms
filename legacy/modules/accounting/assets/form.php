<?php
// modules/accounting/assets/form.php

$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = $id ? true : false;
$data = [
    'asset_code' => 'AUTO', 'asset_name' => '', 'category' => 'machinery', 
    'acquisition_date' => date('Y-m-d'), 'acquisition_cost' => 0, 
    'salvage_value' => 0, 'useful_life_years' => 4, 'notes' => ''
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM fixed_assets WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['asset_name'];
    $cat = $_POST['category'];
    $date = $_POST['acquisition_date'];
    $cost = floatval(str_replace('.', '', $_POST['acquisition_cost']));
    $salvage = floatval(str_replace('.', '', $_POST['salvage_value']));
    $life = intval($_POST['useful_life_years']);
    $notes = $_POST['notes'];
    
    // Hitung Depresiasi Per Bulan (Straight Line)
    // (Harga - Residu) / (Tahun * 12)
    $depreciable_amount = $cost - $salvage;
    $months = $life * 12;
    $monthly_dep = ($months > 0) ? ($depreciable_amount / $months) : 0;
    
    try {
        if (!$is_edit) {
            // Generate Code FA-YY-001
            $ym = date('y');
            $stmt_no = $pdo->query("SELECT COUNT(*) FROM fixed_assets WHERE asset_code LIKE 'FA-$ym-%'");
            $count = $stmt_no->fetchColumn() + 1;
            $code = "FA-" . $ym . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);
            
            $sql = "INSERT INTO fixed_assets (asset_code, asset_name, category, acquisition_date, acquisition_cost, salvage_value, useful_life_years, monthly_depreciation, book_value, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            // Book value awal = Cost
            $pdo->prepare($sql)->execute([$code, $name, $cat, $date, $cost, $salvage, $life, $monthly_dep, $cost, $notes]);
        } else {
            // Edit (Hanya update data deskriptif, rekalkulasi nilai buku manual jika perlu advanced logic)
            // Sederhana: Update data dasar, depresiasi bulan depan menyesuaikan
            $sql = "UPDATE fixed_assets SET asset_name=?, category=?, notes=? WHERE id=?";
            $pdo->prepare($sql)->execute([$name, $cat, $notes, $id]);
        }
        
        echo "<script>alert('Aset berhasil disimpan!'); window.location='index.php?page=acc-assets';</script>";
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

render_header($is_edit ? "Edit Aset" : "Registrasi Aset Tetap");
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">Form Aset Tetap</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Nama Aset</label>
                            <input type="text" name="asset_name" class="form-control" value="<?= $data['asset_name'] ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Kategori</label>
                            <select name="category" class="form-select">
                                <option value="machinery" <?= $data['category']=='machinery'?'selected':'' ?>>Mesin & Peralatan</option>
                                <option value="vehicle" <?= $data['category']=='vehicle'?'selected':'' ?>>Kendaraan</option>
                                <option value="building" <?= $data['category']=='building'?'selected':'' ?>>Bangunan</option>
                                <option value="electronic" <?= $data['category']=='electronic'?'selected':'' ?>>Elektronik / Komputer</option>
                                <option value="equipment" <?= $data['category']=='equipment'?'selected':'' ?>>Inventaris Kantor</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Tanggal Perolehan</label>
                            <input type="date" name="acquisition_date" class="form-control" value="<?= $data['acquisition_date'] ?>" <?= $is_edit?'readonly':'' ?> required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Harga Perolehan (Rp)</label>
                            <input type="text" name="acquisition_cost" class="form-control fw-bold" value="<?= number_format($data['acquisition_cost'],0,',','.') ?>" onkeyup="formatRibuan(this)" <?= $is_edit?'readonly':'' ?> required>
                        </div>
                    </div>

                    <div class="row bg-light p-3 rounded border mb-3">
                        <div class="col-12 mb-2 fw-bold text-primary">Parameter Penyusutan (Garis Lurus)</div>
                        <div class="col-md-6 mb-3">
                            <label>Umur Ekonomis (Tahun)</label>
                            <input type="number" name="useful_life_years" class="form-control" value="<?= $data['useful_life_years'] ?>" <?= $is_edit?'readonly':'' ?> required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Nilai Sisa / Residu (Rp)</label>
                            <input type="text" name="salvage_value" class="form-control" value="<?= number_format($data['salvage_value'],0,',','.') ?>" onkeyup="formatRibuan(this)" <?= $is_edit?'readonly':'' ?>>
                            <div class="form-text">Nilai aset di akhir umur ekonomis.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"><?= $data['notes'] ?></textarea>
                    </div>

                    <div class="text-end">
                        <a href="index.php?page=acc-assets" class="btn btn-secondary me-2">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan Aset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function formatRibuan(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    input.value = new Intl.NumberFormat('id-ID').format(value);
}
</script>
<?php render_footer(); ?>