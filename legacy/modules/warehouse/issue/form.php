<?php
// modules/warehouse/issue/form.php
// FIX POIN 6.1: Prod SPV Mengajukan ITR (Request)

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('whse_stock')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=whse-issue';</script>";
    exit;
}

$spk_id = isset($_GET['spk_id']) ? (int)$_GET['spk_id'] : 0;
$spk_id = $spk_id > 0 ? $spk_id : null;
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$items = [];

// PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=whse-issue';</script>";
        exit;
    }

    $spk_ref = (int)($_POST['spk_id'] ?? 0);
    $date = $_POST['itr_date'];
    // issued_by kosong saat request, nanti diisi Gudang saat approve
    $received = $_POST['received_by']; // Pemohon (Prod SPV)
    $notes = $_POST['notes'];
    
    $item_ids = $_POST['item_id'] ?? [];
    $qty_issued = $_POST['qty_issued'] ?? []; // Qty Request

    try {
        // Validasi Double Request di Backend (Safety Net)
        $chk_double = $pdo->prepare("SELECT COUNT(*) FROM material_issues WHERE spk_id = ? AND status != 'rejected'");
        $chk_double->execute([$spk_ref]);
        if ($chk_double->fetchColumn() > 0) {
            throw new Exception("SPK ini sudah memiliki ITR yang sedang diproses atau sudah selesai. Tidak bisa double request.");
        }

        $pdo->beginTransaction();

        $ym = date('ym');
        $stmt_no = $pdo->query("SELECT COUNT(*) FROM material_issues WHERE itr_number LIKE 'ITR-$ym-%'");
        $count = $stmt_no->fetchColumn() + 1;
        $itr_number = "ITR-" . $ym . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);

        // Status awal: 'request'
        $sql = "INSERT INTO material_issues (itr_number, spk_id, itr_date, received_by, notes, created_by, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'request')";
        $pdo->prepare($sql)->execute([$itr_number, $spk_ref, $date, $received, $notes, $_SESSION['user_id']]);
        $itr_id = $pdo->lastInsertId();

        // Simpan Detail (Qty Request)
        $stmt_ins = $pdo->prepare("INSERT INTO material_issue_items (material_issue_id, item_id, qty_issued) VALUES (?, ?, ?)");
        
        for ($i = 0; $i < count($item_ids); $i++) {
            $qty = floatval($qty_issued[$i]);
            if ($qty > 0) $stmt_ins->execute([$itr_id, $item_ids[$i], $qty]);
        }

        // Notif ke Gudang
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'whse.itr.request.' . (int)$itr_id,
                'Request Material Baru',
                "ITR #$itr_number diajukan Produksi. Mohon dicek dan disetujui.",
                "index.php?page=whse-issue",
                'warning',
                ['permission_slug' => 'whse_stock']
            );
        }

        $pdo->commit();
        echo "<script>alert('Permintaan Material (ITR) berhasil diajukan ke Gudang!'); window.location='index.php?page=whse-issue';</script>";
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<script>alert('Gagal menyimpan ITR.'); window.history.back();</script>";
        exit;
    }
}

// Load SPK Data jika ada parameter
if ($spk_id) {
    // Ambil Material yang dibutuhkan oleh SPK ini
    $sql_mat = "SELECT sm.*, i.item_name, i.item_code, i.unit, i.current_stock
                FROM spk_materials sm
                JOIN items i ON sm.item_id = i.id
                WHERE sm.spk_id = ?";
    $stmt_mat = $pdo->prepare($sql_mat);
    $stmt_mat->execute([$spk_id]);
    $items = $stmt_mat->fetchAll();
}

// FIX: Load List SPK yang Aktif TETAPI belum punya ITR (Anti Double)
// Mengambil SPK status 'released'/'in_production' YANG ID-nya TIDAK ADA di tabel material_issues (kecuali yang rejected)
$sql_spk_list = "SELECT id, spk_number, project_name 
                 FROM spk 
                 WHERE status IN ('released', 'in_production') 
                 AND id NOT IN (
                    SELECT spk_id FROM material_issues WHERE status != 'rejected'
                 )
                 ORDER BY id DESC";

// Jika sedang select SPK tertentu (misal baru reload halaman), pastikan dia tetap muncul di list agar tidak blank
if ($spk_id) {
    $sql_spk_with_selected = "SELECT id, spk_number, project_name FROM spk WHERE id = ? UNION " . $sql_spk_list;
    $stmt_spk_list = $pdo->prepare($sql_spk_with_selected);
    $stmt_spk_list->execute([(int)$spk_id]);
    $spk_list = $stmt_spk_list->fetchAll();
} else {
    $spk_list = $pdo->query($sql_spk_list)->fetchAll();
}

// Header Teks dinamis
$user_role = $_SESSION['role'] ?? '';
$page_title = (strpos($user_role, 'whse') !== false) ? "Proses ITR (Gudang)" : "Ajukan Material (Prod SPV)";

render_header($page_title);
?>

<form method="POST">
    <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">Info Pengajuan</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>Referensi SPK <span class="text-danger">*</span></label>
                        <select name="spk_id" class="form-select" required onchange="window.location.href='index.php?page=whse-issue&action=create&spk_id='+this.value">
                            <option value="">-- Pilih SPK --</option>
                            <?php foreach($spk_list as $s): 
                                $selected = ($s['id'] == $spk_id) ? 'selected' : '';
                            ?>
                                <option value="<?= $s['id'] ?>" <?= $selected ?>>
                                    <?= $s['spk_number'] ?> - <?= substr($s['project_name'],0,20) ?>...
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Hanya menampilkan SPK yang belum dibuatkan ITR.</small>
                    </div>
                    <div class="mb-3">
                        <label>Tanggal Request</label>
                        <input type="date" name="itr_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <!-- Issued By dihide, nanti diisi Gudang saat approve -->
                    <input type="hidden" name="issued_by" value="-">

                    <div class="mb-3">
                        <label>Pemohon (Produksi) <span class="text-danger">*</span></label>
                        <input type="text" name="received_by" class="form-control" value="<?= $_SESSION['fullname'] ?>" required placeholder="Nama Leader/Supervisor">
                    </div>
                    <div class="mb-3">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-info text-dark">
                    Daftar Material (Berdasarkan BOM SPK)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Material</th>
                                    <th>Stok Gudang</th>
                                    <th>Kebutuhan SPK</th>
                                    <th width="150">Jml Diminta</th>
                                    <th>Satuan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($items)): foreach($items as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?= $item['item_name'] ?></strong><br>
                                        <small class="text-muted"><?= $item['item_code'] ?></small>
                                        <input type="hidden" name="item_id[]" value="<?= $item['item_id'] ?>">
                                    </td>
                                    <td><?= $item['current_stock'] + 0 ?></td>
                                    <td><?= $item['qty_required'] + 0 ?></td>
                                    <td>
                                        <!-- Default qty dikeluarkan = qty dibutuhkan -->
                                        <input type="number" name="qty_issued[]" class="form-control fw-bold border-primary" value="<?= $item['qty_required'] + 0 ?>" step="0.0001" min="0" required>
                                    </td>
                                    <td><?= $item['unit'] ?></td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        Pilih SPK terlebih dahulu untuk memuat daftar material.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white text-end">
                    <a href="index.php?page=whse-issue" class="btn btn-secondary me-2">Batal</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-send"></i> Ajukan Permintaan
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php render_footer(); ?>
