<?php
// modules/qc/ncr/form.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('qc_ncr_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=qc-ncr';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$data = [
    'ncr_number' => 'AUTO',
    'source_type' => 'production',
    'item_id' => '',
    'qty_reject' => 0,
    'issue_description' => '',
    'root_cause' => '',
    'corrective_action' => '',
    'operator_id' => '',
    'disposition' => 'pending',
    'status' => 'open'
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM ncr WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    if(!$data) die("NCR tidak ditemukan");
}

// PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    } else {
        $source = $_POST['source_type'];
        $itemId = $_POST['item_id'];
        $qty    = $_POST['qty_reject'];
        $issue  = $_POST['issue_description'];
        $cause  = $_POST['root_cause'];
        $action = $_POST['corrective_action'];
        $op_id  = !empty($_POST['operator_id']) ? $_POST['operator_id'] : null;
        $disp   = $_POST['disposition'];
        
        // Logic Status: Jika sudah diisi analisa, status naik jadi 'analyzed'
        $new_status = ($data['status'] == 'open' && !empty($cause)) ? 'analyzed' : $data['status'];

        try {
            if (!$is_edit) {
                $ym = date('ym');
                $stmt_no = $pdo->query("SELECT COUNT(*) FROM ncr WHERE ncr_number LIKE 'NCR-$ym-%'");
                $count = $stmt_no->fetchColumn() + 1;
                $ncr_num = "NCR-" . $ym . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);

                $sql = "INSERT INTO ncr (ncr_number, source_type, reference_id, item_id, qty_reject, issue_description, root_cause, corrective_action, operator_id, disposition, status, created_by) 
                        VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                // ref_id 0 karena manual create
                $pdo->prepare($sql)->execute([$ncr_num, $source, $itemId, $qty, $issue, $cause, $action, $op_id, $disp, 'open', $_SESSION['user_id']]);
            } else {
                $sql = "UPDATE ncr SET item_id=?, qty_reject=?, issue_description=?, root_cause=?, corrective_action=?, operator_id=?, disposition=?, status=? WHERE id=?";
                $pdo->prepare($sql)->execute([$itemId, $qty, $issue, $cause, $action, $op_id, $disp, $new_status, $id]);
            }
            
            echo "<script>alert('NCR berhasil disimpan!'); window.location='index.php?page=qc-ncr';</script>";
            exit;
        } catch (Exception $e) {
            $error = "Terjadi kesalahan saat menyimpan NCR.";
        }
    }
}

// Data Master
$items = $pdo->query("SELECT * FROM items ORDER BY item_name ASC")->fetchAll();
$operators = $pdo->query("SELECT * FROM users WHERE role_id != 1 ORDER BY fullname ASC")->fetchAll();

render_header($is_edit ? "Analisa NCR" : "Buat NCR Baru");
?>

<form method="POST">
    <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $esc($error) ?></div><?php endif; ?>

    <div class="row">
        <!-- Kolom Kiri: Informasi Barang & Reject -->
        <div class="col-md-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-danger text-white">Informasi Ketidaksesuaian</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>No. NCR</label>
                        <input type="text" class="form-control fw-bold" value="<?= $esc($data['ncr_number']) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Sumber Masalah</label>
                        <select name="source_type" class="form-select">
                            <option value="production" <?= $data['source_type']=='production'?'selected':'' ?>>Produksi (Internal)</option>
                            <option value="incoming" <?= $data['source_type']=='incoming'?'selected':'' ?>>Incoming (Supplier)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Barang / Material</label>
                        <select name="item_id" class="form-select select2" required>
                            <option value="">-- Pilih Barang --</option>
                            <?php foreach($items as $i): ?>
                                <option value="<?= (int)$i['id'] ?>" <?= (int)$i['id']==(int)$data['item_id']?'selected':'' ?>>
                                    <?= $esc($i['item_code']) ?> - <?= $esc($i['item_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Jumlah Reject (Qty)</label>
                        <input type="number" name="qty_reject" class="form-control border-danger text-danger fw-bold" value="<?= (float)$data['qty_reject'] ?>" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label>Deskripsi Masalah (Problem)</label>
                        <textarea name="issue_description" class="form-control" rows="3" required placeholder="Jelaskan apa yang salah..."><?= $esc($data['issue_description']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Analisa & Mitigasi -->
        <div class="col-md-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-warning text-dark">Analisa & Tindakan Perbaikan</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="fw-bold">Akar Penyebab (Root Cause)</label>
                        <textarea name="root_cause" class="form-control" rows="3" placeholder="Mengapa bisa terjadi? (Man/Machine/Method/Material)"><?= $esc($data['root_cause']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Tindakan Perbaikan (Corrective Action)</label>
                        <textarea name="corrective_action" class="form-control" rows="3" placeholder="Apa yang dilakukan untuk memperbaiki?"><?= $esc($data['corrective_action']) ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Penanggung Jawab (Operator)</label>
                            <select name="operator_id" class="form-select">
                                <option value="">-- Tidak Ada / Umum --</option>
                                <?php foreach($operators as $u): ?>
                                    <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id']==(int)$data['operator_id']?'selected':'' ?>><?= $esc($u['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Disposisi (Keputusan)</label>
                            <select name="disposition" class="form-select fw-bold">
                                <option value="pending" <?= $data['disposition']=='pending'?'selected':'' ?>>Pending</option>
                                <option value="repair" <?= $data['disposition']=='repair'?'selected':'' ?>>Repair (Perbaiki)</option>
                                <option value="scrap" <?= $data['disposition']=='scrap'?'selected':'' ?>>Scrap (Buang/Daur Ulang)</option>
                                <option value="return_to_vendor" <?= $data['disposition']=='return_to_vendor'?'selected':'' ?>>Return to Vendor</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-end">
                <a href="index.php?page=qc-ncr" class="btn btn-secondary">Kembali</a>
                <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-save"></i> Simpan Analisa</button>
            </div>
        </div>
    </div>
</form>

<?php render_footer(); ?>
