<?php
// modules/warehouse/return/form.php

if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('whse_stock')) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=whse-return';</script>";
    exit;
}

$spk_id = isset($_GET['spk_id']) ? $_GET['spk_id'] : null;
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

// PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    } else {
    $spk_ref = $_POST['spk_id'];
    $date = $_POST['ret_date'];
    $returned = $_POST['returned_by'];
    $notes = $_POST['notes'];
    
    $types = $_POST['type']; // Array
    $item_ids = $_POST['item_id']; // Array (utk intact)
    $manuals = $_POST['item_name_manual']; // Array (utk waste)
    $qtys = $_POST['qty'];
    $units = $_POST['unit'];

    try {
        $pdo->beginTransaction();

        $ym = date('ym');
        $stmt_no = $pdo->query("SELECT COUNT(*) FROM material_returns WHERE ret_number LIKE 'RET-$ym-%'");
        $count = $stmt_no->fetchColumn() + 1;
        $ret_number = "RET-" . $ym . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);

        // Header
        $sql = "INSERT INTO material_returns (ret_number, spk_id, ret_date, returned_by, notes, created_by, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'request')";
        $pdo->prepare($sql)->execute([$ret_number, $spk_ref, $date, $returned, $notes, $_SESSION['user_id']]);
        $ret_id = $pdo->lastInsertId();

        // Detail
        $stmt_ins = $pdo->prepare("INSERT INTO material_return_items (return_id, type, item_id, item_name_manual, qty, unit) VALUES (?, ?, ?, ?, ?, ?)");
        
        for ($i = 0; $i < count($types); $i++) {
            $type = $types[$i];
            $qty = floatval($qtys[$i]);
            
            // Logic: Jika intact, ambil item_id. Jika waste, ambil manual name.
            $itemId = ($type == 'intact') ? $item_ids[$i] : null;
            $itemName = ($type == 'waste') ? $manuals[$i] : null;
            $unit = $units[$i];

            if ($qty > 0) {
                $stmt_ins->execute([$ret_id, $type, $itemId, $itemName, $qty, $unit]);
            }
        }

        // Notif ke Gudang
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'whse.return.request.' . (int)$ret_id,
                'Pengembalian Material',
                "Ada pengembalian sisa produksi dari SPK #$spk_ref",
                "index.php?page=whse-return",
                'info',
                ['permission_slug' => 'whse_stock']
            );
        }

        $pdo->commit();
        echo "<script>alert('Pengembalian diajukan ke Gudang.'); window.location='index.php?page=whse-return';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Gagal: " . $e->getMessage();
    }
    }
}

// Data Master
$spk_list = $pdo->query("SELECT id, spk_number, project_name FROM spk WHERE status IN ('released', 'in_production', 'completed') ORDER BY id DESC")->fetchAll();
$raw_materials = $pdo->query("SELECT * FROM items WHERE item_type IN ('raw_material', 'consumable') ORDER BY item_name ASC")->fetchAll();

render_header("Buat Pengembalian Material");
?>

<form method="POST" id="formReturn">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">Info Pengembalian</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>Referensi SPK <span class="text-danger">*</span></label>
                        <select name="spk_id" class="form-select select2" required>
                            <option value="">-- Pilih SPK --</option>
                            <?php foreach($spk_list as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= $s['spk_number'] ?> - <?= mb_strimwidth($s['project_name'],0,20,'..') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Tanggal Kembali</label>
                        <input type="date" name="ret_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Dikembalikan Oleh (Operator)</label>
                        <input type="text" name="returned_by" class="form-control" value="<?= $_SESSION['fullname'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <strong>Daftar Material Kembali</strong>
                    <button type="button" class="btn btn-sm btn-success" onclick="addRow()">+ Tambah Item</button>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="20%">Tipe</th>
                                <th width="40%">Nama Barang</th>
                                <th width="15%">Qty</th>
                                <th width="15%">Satuan</th>
                                <th width="10%">Hapus</th>
                            </tr>
                        </thead>
                        <tbody id="retItems">
                            <!-- Rows added by JS -->
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white text-end">
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Ajukan ke Gudang</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
const materials = <?= json_encode($raw_materials) ?>;

function addRow() {
    // Build options for select
    let opts = '<option value="">-- Pilih Master Barang --</option>';
    materials.forEach(m => {
        opts += `<option value="${m.id}" data-unit="${m.unit}">${m.item_code} - ${m.item_name}</option>`;
    });

    const rowId = Date.now(); // Unique ID for row
    
    const row = `
    <tr id="row_${rowId}">
        <td>
            <select name="type[]" class="form-select form-select-sm" onchange="toggleInput(${rowId}, this.value)">
                <option value="intact">Utuh (Master)</option>
                <option value="waste">Sisa / Waste</option>
            </select>
        </td>
        <td>
            <!-- Input untuk Intact -->
            <div id="intact_box_${rowId}">
                <select name="item_id[]" class="form-select form-select-sm item-select" onchange="updateUnit(${rowId}, this)">
                    ${opts}
                </select>
            </div>
            <!-- Input untuk Waste (Manual) -->
            <div id="waste_box_${rowId}" class="d-none">
                <input type="text" name="item_name_manual[]" class="form-control form-control-sm" placeholder="Nama Barang Sisa (Manual)">
                <!-- Hidden item_id kosong utk waste -->
                <input type="hidden" name="item_id_dummy[]" value=""> 
            </div>
        </td>
        <td>
            <input type="number" name="qty[]" class="form-control form-control-sm" step="0.01" required>
        </td>
        <td>
            <input type="text" name="unit[]" id="unit_${rowId}" class="form-control form-control-sm" placeholder="Unit">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('row_${rowId}').remove()">X</button>
        </td>
    </tr>`;
    
    document.getElementById('retItems').insertAdjacentHTML('beforeend', row);
}

function toggleInput(rowId, type) {
    const intactBox = document.getElementById(`intact_box_${rowId}`);
    const wasteBox = document.getElementById(`waste_box_${rowId}`);
    const unitInput = document.getElementById(`unit_${rowId}`);
    
    if (type === 'intact') {
        intactBox.classList.remove('d-none');
        wasteBox.classList.add('d-none');
        unitInput.readOnly = true; // Unit ambil dari master
    } else {
        intactBox.classList.add('d-none');
        wasteBox.classList.remove('d-none');
        unitInput.value = 'Kg'; // Default unit waste
        unitInput.readOnly = false; // Bisa edit manual
    }
}

function updateUnit(rowId, select) {
    const unit = select.options[select.selectedIndex].getAttribute('data-unit') || '';
    document.getElementById(`unit_${rowId}`).value = unit;
}

// Init 1 Row
window.onload = addRow;
</script>

<?php render_footer(); ?>
