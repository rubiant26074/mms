<?php
// modules/engineering/boms/form.php
if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('eng_bom')) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=eng-bom';</script>";
    exit;
}
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

// 1. Logika 'Mark as Read' jika masuk dari notifikasi
if (isset($_GET['notif_id'])) {
    $notif_id = (int)$_GET['notif_id'];
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$notif_id]);
}

$action = $_GET['action'] ?? 'create';
$id = $_GET['id'] ?? null;
$so_id = $_GET['so_id'] ?? null; 
$prefill_item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

function bom_qty_display($value) {
    if ($value === null || $value === '') return '';
    if (!is_numeric($value)) return clean((string)$value);
    $num = (float)$value;
    if (floor($num) == $num) return (string)((int)$num);
    return rtrim(rtrim(number_format($num, 6, '.', ''), '0'), '.');
}

// 2. QUERY BARANG JADI (Hanya item_type = 'finish_good' & belum punya BOM)
// Menggunakan alias 'i' agar i.item_name di ORDER BY valid
$sql_fg = "SELECT i.id, i.item_code, i.item_name 
           FROM items i 
           LEFT JOIN boms b ON i.id = b.item_id 
           WHERE i.item_type = 'finish_good' AND b.id IS NULL";

if ($action == 'edit' && $id) {
    $sql_fg .= " OR i.id = (SELECT item_id FROM boms WHERE id = " . (int)$id . ")";
}
$sql_fg .= ($prefill_item_id > 0 ? " OR i.id = " . $prefill_item_id : "");
$sql_fg .= " ORDER BY i.item_name ASC";
$stmt_fg = $pdo->query($sql_fg);
$finish_goods = $stmt_fg->fetchAll();

// 3. QUERY KOMPOSISI (Hanya item_type = 'raw_material' atau 'consumable')
// PERBAIKAN: Menambahkan alias 'i' agar 'ORDER BY i.item_name' tidak error
$sql_rm = "SELECT i.id, i.item_code, i.item_name, i.unit 
           FROM items i 
           WHERE i.item_type IN ('raw_material', 'consumable') 
           ORDER BY i.item_name ASC";
$stmt_rm = $pdo->query($sql_rm);
$raw_materials = $stmt_rm->fetchAll();

// 4. Load Data jika Edit
$data = ['item_id' => '', 'qty_result' => 1, 'status' => 'active'];
$details = [];
if ($action == 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM boms WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    
    $stmt_det = $pdo->prepare("SELECT * FROM bom_details WHERE bom_id = ?");
    $stmt_det->execute([$id]);
    $details = $stmt_det->fetchAll();
} elseif ($action == 'create' && $prefill_item_id > 0) {
    $data['item_id'] = $prefill_item_id;
}

render_header($action == 'create' ? "Buat BOM Baru" : "Edit BOM");
?>

<div class="container-fluid">
    <form action="modules/engineering/boms/save.php" method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="<?= htmlspecialchars((string)$action, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="id" value="<?= (int)$id ?>">

        <div class="card shadow-sm mb-4 border-start border-primary border-5">
            <div class="card-header bg-white fw-bold text-primary">
                <i class="bi bi-box-seam me-2"></i>Header BOM (Barang Jadi)
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Pilih Finish Good <span class="text-danger">*</span></label>
                        <select name="item_id" class="form-select select2" required>
                            <option value="">-- Pilih Barang Jadi --</option>
                            <?php foreach ($finish_goods as $item): ?>
                                <option value="<?= (int)$item['id'] ?>" <?= $data['item_id'] == $item['id'] ? 'selected' : '' ?>>
                                    <?= clean($item['item_code']) ?> - <?= htmlspecialchars($item['item_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted fst-italic">*Menampilkan Finish Good yang belum ada BOM-nya</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Qty Output</label>
                        <div class="input-group">
                            <input type="number" step="any" name="qty_result" class="form-control fw-bold" value="<?= bom_qty_display($data['qty_result']) ?>" min="1" required>
                            <span class="input-group-text">Unit</span>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Status</label>
                        <select name="status" class="form-select fw-bold text-primary">
                            <option value="active" <?= $data['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="draft" <?= $data['status'] == 'draft' ? 'selected' : '' ?>>Draft</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-start border-info border-5">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold text-info"><i class="bi bi-list-check me-2"></i>Komposisi Raw Material / Consumable</span>
                <button type="button" class="btn btn-info btn-sm fw-bold" onclick="addRow()">
                    <i class="bi bi-plus-circle me-1"></i>Tambah Item
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" id="bomTable">
                        <thead class="bg-light text-center small">
                            <tr>
                                <th>MATERIAL / CONSUMABLE</th>
                                <th width="200">QTY KEBUTUHAN</th>
                                <th width="80">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($details)): ?>
                            <tr>
                                <td>
                                    <select name="material_id[]" class="form-select select2">
                                        <option value="">-- Pilih Raw Material / Consumable --</option>
                                        <?php foreach ($raw_materials as $rm): ?>
                                            <option value="<?= (int)$rm['id'] ?>"><?= clean($rm['item_code']) ?> - <?= htmlspecialchars($rm['item_name']) ?> (<?= clean($rm['unit']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" step="any" name="qty_needed[]" class="form-control text-end fw-bold" placeholder="0.00"></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                            <?php else: foreach ($details as $det): ?>
                            <tr>
                                <td>
                                    <select name="material_id[]" class="form-select">
                                        <?php foreach ($raw_materials as $rm): ?>
                                            <option value="<?= (int)$rm['id'] ?>" <?= $det['material_id'] == $rm['id'] ? 'selected' : '' ?>><?= clean($rm['item_code']) ?> - <?= htmlspecialchars($rm['item_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" step="any" name="qty_needed[]" class="form-control text-end fw-bold" value="<?= bom_qty_display($det['qty']) ?>"></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-end py-3">
                <a href="index.php?page=eng-bom" class="btn btn-light border px-4 me-2">Batal</a>
                <button type="submit" class="btn btn-primary px-5 fw-bold shadow">
                    <i class="bi bi-save me-2"></i>Simpan BOM
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function addRow() {
    const table = document.getElementById('bomTable').getElementsByTagName('tbody')[0];
    const newRow = table.insertRow();
    newRow.innerHTML = `
        <td>
            <select name="material_id[]" class="form-select">
                <option value="">-- Pilih Raw Material / Consumable --</option>
                <?php foreach ($raw_materials as $rm): ?>
                    <option value="<?= (int)$rm['id'] ?>"><?= clean($rm['item_code']) ?> - <?= htmlspecialchars($rm['item_name']) ?> (<?= clean($rm['unit']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="number" step="any" name="qty_needed[]" class="form-control text-end fw-bold" placeholder="0.00"></td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button>
        </td>
    `;
}
</script>

<?php render_footer(); ?>
