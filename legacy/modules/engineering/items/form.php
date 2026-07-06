<?php
// modules/engineering/items/form.php
if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('eng_items')) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=eng-items';</script>";
    exit;
}

$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = $id ? true : false;
$can_see_price = has_permission('item_price_view');
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$data = [
    'item_code' => '', // Auto Generated
    'customer_id' => '', // Field Baru
    'item_name' => '', 
    'item_type' => 'finish_good', 
    'ownership' => 'internal', // Default Internal
    'qc_type'   => 'general',
    'unit' => 'Pcs', 
    'base_price' => 0, 
    'min_stock' => 0, 
    'description' => '',
    'drawing_file' => ''
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $fetch = $stmt->fetch();
    if(!$fetch) die("Data tidak ditemukan.");
    $data = $fetch;
    // Handle jika data lama belum ada customer_id
    if(!isset($data['customer_id'])) $data['customer_id'] = '';
}

// PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    } else {
    $code = clean($_POST['item_code']);
    // Jika internal, customer_id bisa null atau diisi jika dialokasikan khusus
    $cust_id = !empty($_POST['customer_id']) ? $_POST['customer_id'] : null;
    $name = clean($_POST['item_name']);
    $type = clean($_POST['item_type']);
    $own  = clean($_POST['ownership']);
    $qc   = clean($_POST['qc_type']); 
    $unit = clean($_POST['unit']);
    $min  = clean($_POST['min_stock']);
    $desc = clean($_POST['description']);
    
    // Harga (Hanya jika punya izin)
    $price = 0;
    if ($can_see_price) {
        $price = floatval(str_replace('.', '', $_POST['base_price']));
    }

    // Upload Drawing Logic
    $drawing_path = $data['drawing_file'];
    if (isset($_FILES['drawing_file']) && $_FILES['drawing_file']['error'] == 0) {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $filename = $_FILES['drawing_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if ($_FILES['drawing_file']['size'] > $max_size) {
            $error = "Ukuran drawing maksimal 5MB.";
        } elseif (in_array($ext, $allowed, true)) {
            // Nama file: draw_KODE_TIMESTAMP.ext
            $safe_code = preg_replace('/[^A-Za-z0-9]/', '-', $code);
            $new_name = "draw_" . $safe_code . "_" . time() . "." . $ext;
            $upload_dir = "uploads/drawings/";
            
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $target = $upload_dir . $new_name;
            if (move_uploaded_file($_FILES['drawing_file']['tmp_name'], $target)) {
                $drawing_path = $target;
            } else {
                $error = "Gagal mengunggah drawing.";
            }
        } else {
            $error = "Format drawing tidak diizinkan.";
        }
    }

    if (!isset($error)) try {
        if ($is_edit) {
            // Cek duplikat kode selain ID ini
            $check = $pdo->prepare("SELECT COUNT(*) FROM items WHERE item_code = ? AND id != ?");
            $check->execute([$code, $id]);
            if($check->fetchColumn() > 0) throw new Exception("Kode Barang '$code' sudah digunakan!");

            // Update Query
            if ($can_see_price) {
                $sql = "UPDATE items SET customer_id=?, item_code=?, item_name=?, item_type=?, ownership=?, qc_type=?, unit=?, base_price=?, min_stock=?, description=?, drawing_file=? WHERE id=?";
                $pdo->prepare($sql)->execute([$cust_id, $code, $name, $type, $own, $qc, $unit, $price, $min, $desc, $drawing_path, $id]);
            } else {
                $sql = "UPDATE items SET customer_id=?, item_code=?, item_name=?, item_type=?, ownership=?, qc_type=?, unit=?, min_stock=?, description=?, drawing_file=? WHERE id=?";
                $pdo->prepare($sql)->execute([$cust_id, $code, $name, $type, $own, $qc, $unit, $min, $desc, $drawing_path, $id]);
            }

        } else {
            // Cek duplikat kode
            $check = $pdo->prepare("SELECT COUNT(*) FROM items WHERE item_code = ?");
            $check->execute([$code]);
            if($check->fetchColumn() > 0) throw new Exception("Kode Barang '$code' sudah digunakan!");

            // Insert Query
            $sql = "INSERT INTO items (customer_id, item_code, item_name, item_type, ownership, qc_type, unit, base_price, min_stock, description, drawing_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$cust_id, $code, $name, $type, $own, $qc, $unit, $price, $min, $desc, $drawing_path]);
        }
        echo "<script>alert('Data tersimpan!'); window.location='index.php?page=eng-items';</script>";
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    }
}

// Data Customer untuk Dropdown
$customers = $pdo->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll();

render_header($is_edit ? "Edit Barang" : "Tambah Barang");
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><?= $is_edit ? "Edit Barang" : "Tambah Barang Baru" ?></h5>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?><div class="alert alert-danger"><?= $esc($error) ?></div><?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
                    
                    <!-- AREA PILIH CUSTOMER & OWNER -->
                    <div class="row bg-light p-3 rounded mb-3 border">
                        <div class="col-12 mb-2 fw-bold text-primary">Klasifikasi Barang</div>
                        
                        <div class="col-md-6 mb-3">
                            <label>Kepemilikan (Ownership) <span class="text-danger">*</span></label>
                            <select name="ownership" id="ownership" class="form-select" onchange="toggleCustomer()">
                                <option value="internal" <?= $data['ownership']=='internal'?'selected':'' ?>>Internal (Milik Kita)</option>
                                <option value="customer" <?= $data['ownership']=='customer'?'selected':'' ?>>Consignment (Milik Customer)</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3" id="custBox">
                            <label>Customer</label>
                            <select name="customer_id" id="customerId" class="form-select" onchange="generateCode()">
                                <option value="">-- Pilih Customer --</option>
                                <?php foreach($customers as $c): 
                                    $selected = ($c['id'] == $data['customer_id']) ? 'selected' : '';
                                    // Tampilkan Kode Customer di Dropdown untuk memudahkan
                                    $code_display = !empty($c['customer_code']) ? " ({$c['customer_code']})" : "";
                                ?>
                                    <option value="<?= $c['id'] ?>" <?= $selected ?>><?= $esc($c['name']) ?><?= $esc($code_display) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Pilih customer untuk generate kode barang otomatis.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Kode Barang <span class="text-danger">*</span></label>
                            <!-- Readonly agar otomatis generated dari API -->
                            <input type="text" name="item_code" id="itemCode" class="form-control fw-bold bg-light" value="<?= $esc($data['item_code']) ?>" readonly required placeholder="Otomatis (Pilih Customer)">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Nama Barang <span class="text-danger">*</span></label>
                            <input type="text" name="item_name" class="form-control" value="<?= $esc($data['item_name']) ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Tipe Barang</label>
                            <select name="item_type" class="form-select" id="itemType" onchange="generateCode()">
                                <option value="finish_good" <?= $data['item_type']=='finish_good'?'selected':'' ?>>Finish Good</option>
                                <option value="wip" <?= $data['item_type']=='wip'?'selected':'' ?>>Work In Progress</option>
                                <option value="raw_material" <?= $data['item_type']=='raw_material'?'selected':'' ?>>Raw Material</option>
                                <option value="consumable" <?= $data['item_type']=='consumable'?'selected':'' ?>>Consumable</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="text-primary fw-bold">Standar QC</label>
                            <select name="qc_type" class="form-select border-primary fw-bold">
                                <option value="general" <?= $data['qc_type']=='general'?'selected':'' ?>>General (Umum)</option>
                                <option value="sheet_metal" <?= $data['qc_type']=='sheet_metal'?'selected':'' ?>>Sheet Metal Process (Laser/Bend/Weld)</option>
                                <option value="plate" <?= $data['qc_type']=='plate'?'selected':'' ?>>Plate / Sheet</option>
                                <option value="coating" <?= $data['qc_type']=='coating'?'selected':'' ?>>Coating / Paint</option>
                                <option value="machining" <?= $data['qc_type']=='machining'?'selected':'' ?>>Machining / Bubut</option>
                                <option value="consumable" <?= $data['qc_type']=='consumable'?'selected':'' ?>>Consumable</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Satuan</label>
                            <input type="text" name="unit" class="form-control" value="<?= $esc($data['unit']) ?>" required placeholder="Pcs, Kg, Set">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Min. Stok</label>
                            <input type="number" name="min_stock" class="form-control" value="<?= $esc($data['min_stock']) ?>" data-allow-zero="1">
                        </div>

                        <?php if($can_see_price): ?>
                        <div class="col-md-4 mb-3">
                            <label class="text-success fw-bold">Harga Dasar (Rp)</label>
                            <input type="text" name="base_price" class="form-control border-success fw-bold text-end" 
                                   value="<?= number_format($data['base_price'], 0, ',', '.') ?>" onkeyup="formatRibuan(this)">
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-bold text-danger"><i class="bi bi-file-earmark-pdf"></i> Upload Drawing (PDF/IMG)</label>
                        <input type="file" name="drawing_file" class="form-control" accept=".pdf, .jpg, .jpeg, .png">
                        <div class="form-text small">Max 5MB.</div>
                        <?php if (!empty($data['drawing_file']) && file_exists($data['drawing_file'])): ?>
                            <div class="mt-2">
                                <a href="<?= $esc($data['drawing_file']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Lihat Drawing Saat Ini</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label>Deskripsi / Spesifikasi</label>
                        <textarea name="description" class="form-control" rows="3"><?= $esc($data['description']) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php?page=eng-items" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary px-5">Simpan Data</button>
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

function toggleCustomer() {
    const own = document.getElementById('ownership').value;
    const custIdSelect = document.getElementById('customerId');
    
    // Jika Internal, apakah customer harus diisi? 
    // Sesuai request: kode barang otomatis dari kode master customer.
    // Jadi customer select harus tetap aktif agar bisa generate kode INT atau CUST based.
    
    // Trigger generate jika sudah ada value
    if(custIdSelect.value) generateCode();
}

function generateCode() {
    // Jangan generate otomatis saat Edit, agar kode lama tidak berubah
    <?php if($is_edit): ?> return; <?php endif; ?> 

    const custId = document.getElementById('customerId').value;
    const own = document.getElementById('ownership').value;
    const itemType = document.getElementById('itemType').value;
    const itemCodeInput = document.getElementById('itemCode');
    
    // Jika customer belum dipilih, jangan generate dulu (kecuali tipe consumable)
    if (!custId && own === 'customer' && itemType !== 'consumable') {
        itemCodeInput.value = '';
        return;
    }
    
    // Panggil API
    fetch(`modules/engineering/items/api_gen_code.php?customer_id=${encodeURIComponent(custId)}&type=${encodeURIComponent(own)}&item_type=${encodeURIComponent(itemType)}`)
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                itemCodeInput.value = data.code;
            } else {
                console.error(data.message);
                itemCodeInput.value = "ERROR";
            }
        })
        .catch(err => {
            console.error(err);
            itemCodeInput.value = "CONN ERR";
        });
}

// Init State
<?php if(!$is_edit): ?>
window.onload = function() {
    toggleCustomer();
};
<?php endif; ?>
</script>
<?php render_footer(); ?>
