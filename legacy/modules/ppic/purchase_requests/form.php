<?php
// modules/ppic/purchase_requests/form.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('ppic_pr_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=ppic-pr';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$spk_id = isset($_POST['spk_id']) ? (int)$_POST['spk_id'] : (isset($_GET['spk_id']) ? (int)$_GET['spk_id'] : 0);
$is_edit = $id > 0;
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

$data = [
    'pr_number' => 'AUTO',
    'pr_date' => date('Y-m-d'),
    'required_date' => date('Y-m-d', strtotime('+3 days')),
    'notes' => '',
    'status' => 'draft'
];
$items = [];
$warning_msg = "";
$spk_ref = null;

// --- 1. LOAD DATA (EDIT MODE) ---
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM purchase_requests WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    if(!$data) die("PR tidak ditemukan");

    $stmt_items = $pdo->prepare("SELECT pri.*, i.item_name, i.item_code, i.unit 
                                 FROM purchase_request_items pri 
                                 LEFT JOIN items i ON pri.item_id = i.id 
                                 WHERE pri.purchase_request_id = ?");
    $stmt_items->execute([$id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// --- 2. LOAD DATA DARI SPK (AUTO GENERATE) ---
} elseif ($spk_id) {
    // Ambil Info SPK
    $stmt_spk = $pdo->prepare("SELECT id, spk_number, project_name, deadline_date, status FROM spk WHERE id = ?");
    $stmt_spk->execute([$spk_id]);
    $spk = $stmt_spk->fetch();

    if ($spk) {
        $spk_ref = $spk;
        $data['notes'] = "Auto-Generate dari SPK: " . $spk['spk_number'] . " Project: " . $spk['project_name'];
        if (!empty($spk['deadline_date'])) {
            $data['required_date'] = $spk['deadline_date'];
        }
        
        // Ambil Material dari SPK
        // Filter: Hanya ownership 'internal' (Barang kita sendiri), Abaikan 'customer' (Consignment)
        $sql_mat = "SELECT sm.item_id, sm.qty_required, i.item_name, i.item_code, i.unit, i.current_stock, i.min_stock
                    FROM spk_materials sm
                    JOIN items i ON sm.item_id = i.id
                    WHERE sm.spk_id = ? AND (i.ownership = 'internal' OR i.ownership IS NULL OR i.ownership = '')";
        
        $stmt_mat = $pdo->prepare($sql_mat);
        $stmt_mat->execute([$spk_id]);
        $raw_materials = $stmt_mat->fetchAll();

        foreach ($raw_materials as $rm) {
            // Hitung Kekurangan Stok (Defisit)
            // Rumus: Kebutuhan - Stok Saat Ini
            // Jika Stok < Kebutuhan, maka perlu beli selisihnya.
            // Opsi: Bisa juga kita order Full Kebutuhan (safety stock). Di sini kita pakai logika Defisit.
            
            $deficit = $rm['qty_required'] - $rm['current_stock'];
            
            // Jika Defisit > 0 (Stok Kurang) -> Masukkan ke PR
            // ATAU Jika stok sudah dibawah minimum -> Masukkan ke PR (meski cukup utk SPK ini)
            
            $qty_order = 0;
            if ($deficit > 0) {
                $qty_order = $deficit;
            } elseif ($rm['current_stock'] <= $rm['min_stock']) {
                $qty_order = $rm['min_stock'] * 2; // Restock standar jika low stock
            }

            // Jika butuh order, tambahkan ke list items
            if ($qty_order > 0 || $deficit > 0) { // Pakai deficit > 0 agar request sesuai kebutuhan produksi
                 // Di PR dari referensi SPK, qty default mengikuti kekurangan real stok.
                 $qty_final = max($qty_order, 1);

                 $items[] = [
                    'item_id' => $rm['item_id'],
                    'item_code' => $rm['item_code'],
                    'item_name' => $rm['item_name'],
                    'unit' => $rm['unit'],
                    'qty' => $qty_final,
                    'notes' => "Referensi BOM SPK " . $spk['spk_number'] . " | Need: " . ($rm['qty_required']+0) . " | Stock: " . ($rm['current_stock']+0)
                ];
            }
        }
        
        if (empty($items)) {
            $warning_msg = "Stok Gudang mencukupi untuk semua material di SPK ini. Tidak ada barang yang perlu dibeli.";
        }
    }
}

// --- 3. PROSES SIMPAN (hanya saat tombol simpan ditekan) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pr'])) {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    } else {
        $date = $_POST['pr_date'];
        $req_date = $_POST['required_date'];
        $notes = $_POST['notes'];
        
        $item_ids = $_POST['item_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $item_notes = $_POST['item_notes'] ?? [];

        try {
            $pdo->beginTransaction();

            if (!$is_edit) {
                $ym = date('ym');
                $stmt_no = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE pr_number LIKE 'PR-$ym-%'");
                $count = $stmt_no->fetchColumn() + 1;
                $pr_number = "PR-" . $ym . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);

                if (!empty($spk_id) && strpos((string)$notes, "[REF-SPK:") === false) {
                    $notes = trim((string)$notes) . " [REF-SPK:" . $spk_id . "]";
                }

                $sql = "INSERT INTO purchase_requests (pr_number, pr_date, required_date, notes, status, created_by) 
                        VALUES (?, ?, ?, ?, 'draft', ?)";
                $pdo->prepare($sql)->execute([$pr_number, $date, $req_date, $notes, $_SESSION['user_id']]);
                $pr_id = $pdo->lastInsertId();
            } else {
                $sql = "UPDATE purchase_requests SET pr_date=?, required_date=?, notes=? WHERE id=?";
                $pdo->prepare($sql)->execute([$date, $req_date, $notes, $id]);
                $pr_id = $id;
                $pdo->prepare("DELETE FROM purchase_request_items WHERE purchase_request_id=?")->execute([$id]);
            }

            $stmt_ins = $pdo->prepare("INSERT INTO purchase_request_items (purchase_request_id, item_id, qty, notes) VALUES (?, ?, ?, ?)");
            
            for ($i = 0; $i < count($item_ids); $i++) {
                if ($qtys[$i] > 0) {
                    $stmt_ins->execute([$pr_id, $item_ids[$i], $qtys[$i], $item_notes[$i]]);
                }
            }

            $pdo->commit();
            echo "<script>alert('Purchase Request berhasil disimpan!'); window.location='index.php?page=ppic-pr';</script>";
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Terjadi kesalahan saat menyimpan data.";
        }
    }
}

// Master Data Barang (Raw Material Only)
$raw_materials = $pdo->query("SELECT * FROM items WHERE item_type IN ('raw_material','consumable') ORDER BY item_name ASC")->fetchAll();
$raw_lookup = [];
foreach ($raw_materials as $rm) {
    $raw_lookup[(int)$rm['id']] = $rm;
}

// Fallback deskripsi barang jika data join kosong/null.
foreach ($items as &$it) {
    $iid = isset($it['item_id']) ? (int)$it['item_id'] : 0;
    if ($iid > 0 && isset($raw_lookup[$iid])) {
        if (empty($it['item_code'])) $it['item_code'] = $raw_lookup[$iid]['item_code'] ?? ('ITEM-' . $iid);
        if (empty($it['item_name'])) $it['item_name'] = $raw_lookup[$iid]['item_name'] ?? ('Item #' . $iid);
        if (empty($it['unit'])) $it['unit'] = $raw_lookup[$iid]['unit'] ?? '-';
    } else {
        if (empty($it['item_code'])) $it['item_code'] = 'ITEM-' . $iid;
        if (empty($it['item_name'])) $it['item_name'] = 'Item #' . $iid;
        if (empty($it['unit'])) $it['unit'] = '-';
    }
}
unset($it);

$spk_options = $pdo->query("SELECT id, spk_number, status, deadline_date FROM spk WHERE status IN ('preliminary','waiting_mgr','released','in_production') ORDER BY id DESC LIMIT 200")->fetchAll();

render_header($is_edit ? "Edit Purchase Request" : "Buat PR Baru");
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>

<form method="POST">
    <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
    <input type="hidden" name="spk_id" value="<?= $esc($spk_id) ?>">
    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $esc($error) ?></div><?php endif; ?>
    <?php if(!empty($warning_msg)): ?><div class="alert alert-warning"><i class="bi bi-info-circle"></i> <?= $esc($warning_msg) ?></div><?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">Header PR</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label>No. PR</label>
                    <input type="text" class="form-control fw-bold" value="<?= $esc($data['pr_number']) ?>" readonly>
                </div>
                <div class="col-md-3 mb-3">
                    <label>Tanggal Request</label>
                    <input type="date" name="pr_date" class="form-control" value="<?= $esc($data['pr_date']) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label>Tgl Dibutuhkan</label>
                    <input type="date" name="required_date" class="form-control" value="<?= $esc($data['required_date']) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label>Status</label>
                    <input type="text" class="form-control bg-light" value="<?= strtoupper($esc($data['status'])) ?>" readonly>
                </div>
                <?php if(!$is_edit): ?>
                <div class="col-12 mb-3">
                    <label class="fw-bold">Referensi SPK (Tarik BOM Otomatis)</label>
                    <div class="d-flex gap-2">
                        <select name="spk_id" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Tanpa Referensi SPK --</option>
                            <?php foreach($spk_options as $s): 
                                $sel = ((string)$s['id'] === (string)$spk_id) ? 'selected' : '';
                            ?>
                                <option value="<?= $s['id'] ?>" <?= $sel ?>>
                                    <?= $esc($s['spk_number']) ?> | <?= strtoupper($esc($s['status'])) ?> | Deadline <?= date('d/m/Y', strtotime($s['deadline_date'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <small class="text-muted">Jika dipilih, daftar material akan ditarik dari BOM SPK (melalui data kebutuhan `spk_materials`).</small>
                </div>
                <?php endif; ?>
                <?php if($spk_ref): ?>
                <div class="col-12 mb-2">
                    <div class="alert alert-info py-2 mb-0 small">
                        Referensi aktif: <strong><?= htmlspecialchars($spk_ref['spk_number']) ?></strong> |
                        Status: <strong><?= strtoupper(htmlspecialchars($spk_ref['status'])) ?></strong> |
                        Deadline SPK: <strong><?= date('d/m/Y', strtotime($spk_ref['deadline_date'])) ?></strong>
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-12">
                    <label>Catatan / Alasan Pembelian</label>
                    <textarea name="notes" class="form-control" rows="2"><?= $esc($data['notes']) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between">
            <strong>Daftar Barang yang Diminta</strong>
            <button type="button" class="btn btn-sm btn-success" onclick="addItem()">+ Tambah Item</button>
        </div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead class="bg-light text-center">
                    <tr>
                        <th width="40%">Nama Barang</th>
                        <th width="15%">Qty Request</th>
                        <th width="10%">Satuan</th>
                        <th>Keterangan</th>
                        <th width="5%">#</th>
                    </tr>
                </thead>
                <tbody id="prItems">
                    <?php if(!empty($items)): foreach($items as $item): ?>
                    <tr>
                        <td>
                            <select name="item_id[]" class="form-select" required>
                                <option value="<?= $item['item_id'] ?>"><?= $item['item_code'] ?> - <?= $item['item_name'] ?></option>
                                <?php foreach($raw_materials as $rm): ?>
                                    <option value="<?= $rm['id'] ?>"><?= $rm['item_code'] ?> - <?= $rm['item_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" name="qty[]" class="form-control text-center fw-bold" value="<?= $item['qty']+0 ?>" step="0.01" required></td>
                        <td class="text-center"><?= $esc($item['unit']) ?></td>
                        <td><input type="text" name="item_notes[]" class="form-control" value="<?= $esc($item['notes']) ?>"></td>
                        <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            
            <?php if(empty($items) && $spk_id && empty($warning_msg)): ?>
                 <!-- Fallback jika query SPK gagal tapi tidak ada warning -->
                 <div class="text-center py-3 text-muted">Tidak ada item yang perlu dibeli dari SPK ini (Stok Cukup / Consignment).</div>
            <?php endif; ?>
        </div>
        <div class="card-footer text-end">
            <a href="index.php?page=ppic-pr" class="btn btn-secondary">Batal</a>
            <button type="submit" name="save_pr" value="1" class="btn btn-primary px-4">Simpan Request</button>
        </div>
    </div>
</form>

<script>
const rawMaterials = <?= json_encode($raw_materials) ?>;

function addItem() {
    let opts = '<option value="">-- Pilih Material --</option>';
    rawMaterials.forEach(m => {
        opts += `<option value="${m.id}">${m.item_code} - ${m.item_name}</option>`;
    });

    const row = `
    <tr>
        <td><select name="item_id[]" class="form-select" required>${opts}</select></td>
        <td><input type="number" name="qty[]" class="form-control text-center fw-bold" value="1" step="0.01" required></td>
        <td class="text-center">-</td>
        <td><input type="text" name="item_notes[]" class="form-control" placeholder="Keterangan"></td>
        <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button></td>
    </tr>`;
    
    document.getElementById('prItems').insertAdjacentHTML('beforeend', row);
}
</script>

<?php render_footer(); ?>
