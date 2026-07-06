<?php
// modules/qc/incoming/form.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('qc_incoming_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=qc-incoming';</script>";
    exit;
}

$gr_id = isset($_GET['gr_id']) ? (int)$_GET['gr_id'] : 0;
if ($gr_id <= 0) {
    echo "<script>alert('GR ID tidak valid.'); window.location='index.php?page=qc-incoming';</script>";
    exit;
}
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

// 1. Ambil Data Header GR
$stmt_gr = $pdo->prepare("SELECT gr.*, s.name as supp_name, c.name as cust_name 
                          FROM goods_receipts gr 
                          LEFT JOIN purchase_orders po ON gr.purchase_order_id = po.id
                          LEFT JOIN suppliers s ON po.supplier_id = s.id
                          LEFT JOIN customers c ON gr.customer_id = c.id
                          WHERE gr.id = ?");
$stmt_gr->execute([$gr_id]);
$gr = $stmt_gr->fetch();

if (!$gr) {
    echo "<script>alert('Data penerimaan tidak ditemukan.'); window.location='index.php?page=qc-incoming';</script>";
    exit;
}

// 2. Ambil Item GR beserta Tipe QC-nya dari Master Barang
$stmt_items = $pdo->prepare("SELECT gri.*, i.item_name, i.item_code, i.unit, i.qc_type 
                             FROM goods_receipt_items gri 
                             JOIN items i ON gri.item_id = i.id 
                             WHERE gri.goods_receipt_id = ?");
$stmt_items->execute([$gr_id]);
$items = $stmt_items->fetchAll();

// --- PROSES SIMPAN QC (SUBMIT FOR APPROVAL) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    } else {
        $qc_date = date('Y-m-d');
        $notes = $_POST['notes'];
        
        // Data Post Arrays
        $post_good = $_POST['qty_good'];
        $post_reject = $_POST['qty_reject'];
        $post_notes = $_POST['item_notes'];
        
        $total_items = count($items);
        $total_reject_count = 0;

        try {
            $pdo->beginTransaction();
        
        // A. Buat Nomor QC: QC-IN-YYMM-0001
        $ym = date('ym');
        $stmt_no = $pdo->query("SELECT COUNT(*) FROM qc_incoming WHERE qc_number LIKE 'QC-IN-$ym-%'");
        $count = $stmt_no->fetchColumn() + 1;
        $qc_number = "QC-IN-" . $ym . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);
        
        // Hitung Keputusan Sementara
        foreach ($post_reject as $r) { 
            if(floatval($r) > 0) $total_reject_count++; 
        }
        $decision = ($total_reject_count == 0) ? 'accepted' : (($total_reject_count == $total_items) ? 'rejected' : 'partial');

        // B. Insert Header QC (Status: Waiting Approval)
        $sql_head = "INSERT INTO qc_incoming (qc_number, goods_receipt_id, qc_date, inspector_id, status, final_decision, notes) 
                     VALUES (?, ?, ?, ?, 'waiting_approval', ?, ?)";
        $pdo->prepare($sql_head)->execute([$qc_number, $gr_id, $qc_date, $_SESSION['user_id'], $decision, $notes]);
        $qc_id = $pdo->lastInsertId();
        
        // C. Simpan Detail Item & Checklist
        $stmt_det = $pdo->prepare("INSERT INTO qc_incoming_items (qc_incoming_id, item_id, qty_received, qty_good, qty_reject, checklist_data, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        // Update sementara GR Items (Qty Good/Reject) untuk tracking
        $stmt_upd_gr = $pdo->prepare("UPDATE goods_receipt_items SET qty_good=?, qty_reject=? WHERE goods_receipt_id=? AND item_id=?");

        foreach ($items as $idx => $item) {
            $itemId = $item['item_id'];
            $qtyRec = $item['qty_received'];
            $qtyG = floatval($post_good[$idx]);
            $qtyR = floatval($post_reject[$idx]);
            $iNote = $post_notes[$idx];

            // Ambil Checklist JSON dari POST
            $checklist_json = '';
            if (isset($_POST['checklist'][$itemId])) {
                $checklist_json = json_encode($_POST['checklist'][$itemId]);
            }
            
            // Insert QC Detail
            $stmt_det->execute([$qc_id, $itemId, $qtyRec, $qtyG, $qtyR, $checklist_json, $iNote]);
            
            // Update GR Items
            $stmt_upd_gr->execute([$qtyG, $qtyR, $gr_id, $itemId]);
        }
        
        // Update Status GR Header tetap 'qc_pending' sampai di-approve manager
        // (Opsional: Bisa buat status 'qc_submitted' jika perlu)
        
        $pdo->commit();
        echo "<script>alert('Hasil QC berhasil disimpan! Menunggu Approval Manager.'); window.location='index.php?page=qc-incoming';</script>";
        exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Terjadi kesalahan saat menyimpan hasil QC.";
        }
    }
}

render_header("Form Inspeksi QC Incoming");
?>

<form method="POST">
    <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $esc($error) ?></div><?php endif; ?>

    <!-- HEADER INFO -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Info Penerimaan Barang</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label class="fw-bold">No. GR</label>
                    <div class="form-control-plaintext"><?= $esc($gr['gr_number']) ?></div>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold">Tgl Terima</label>
                    <div class="form-control-plaintext"><?= date('d/m/Y', strtotime($gr['gr_date'])) ?></div>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold">Sumber</label>
                    <div class="form-control-plaintext">
                        <?= $esc(($gr['receipt_type'] == 'consignment') ? (($gr['cust_name'] ?? '') . ' (Cust)') : ($gr['supp_name'] ?? '')) ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="fw-bold">No. SJ</label>
                    <div class="form-control-plaintext"><?= $esc($gr['delivery_note_number']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- LOOPING ITEM UNTUK INSPEKSI -->
    <?php foreach($items as $idx => $item): 
        $type = $item['qc_type']; // Mengambil tipe QC dari Master Barang
    ?>
    <div class="card shadow-sm mb-4 border-start border-4 border-info">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 fw-bold"><?= $esc($item['item_name']) ?></h6>
                <small class="text-muted">Kode: <?= $esc($item['item_code']) ?></small>
            </div>
            <span class="badge bg-info text-dark">Standar QC: <?= strtoupper($esc($type)) ?></span>
        </div>
        <div class="card-body">
            <input type="hidden" name="item_id[]" value="<?= (int)$item['item_id'] ?>">
            
            <!-- Input Qty Good/Reject -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label>Qty Diterima</label>
                    <input type="number" class="form-control bg-light fw-bold" value="<?= $item['qty_received'] + 0 ?>" readonly>
                    <small class="text-muted"><?= $esc($item['unit']) ?></small>
                </div>
                <div class="col-md-3">
                    <label>Qty OK (Good) <span class="text-success">*</span></label>
                    <input type="number" name="qty_good[]" class="form-control border-success fw-bold qty-good" 
                           data-idx="<?= $idx ?>" value="<?= $item['qty_received'] + 0 ?>" step="0.01" required 
                           oninput="calcReject(<?= $idx ?>, <?= $item['qty_received'] ?>)">
                </div>
                <div class="col-md-3">
                    <label>Qty Reject <span class="text-danger">*</span></label>
                    <input type="number" name="qty_reject[]" id="reject_<?= $idx ?>" class="form-control border-danger fw-bold text-danger" 
                           value="0" step="0.01" readonly>
                </div>
                <div class="col-md-3">
                    <label>Catatan Item</label>
                    <input type="text" name="item_notes[]" class="form-control" placeholder="Keterangan reject...">
                </div>
            </div>

            <!-- CHECKLIST DINAMIS (Berdasarkan qc_type) -->
            <div class="p-3 bg-light rounded border">
                <h6 class="small fw-bold text-uppercase text-muted mb-2">Checklist Kriteria (<?= ucfirst($type) ?>)</h6>
                
                <div class="row g-2">
                    <?php if($type == 'plate'): ?>
                        <!-- CHECKLIST: PLATE -->
                        <div class="col-md-3">
                            <label class="small fw-bold">Ketebalan</label>
                            <select name="checklist[<?= $item['item_id'] ?>][thickness]" class="form-select form-select-sm">
                                <option value="OK">Sesuai Toleransi</option>
                                <option value="NG">Diluar Toleransi</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold">Kerataan</label>
                            <select name="checklist[<?= $item['item_id'] ?>][flatness]" class="form-select form-select-sm">
                                <option value="OK">Rata / Bagus</option>
                                <option value="NG">Gelombang</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold">Karat / Korosi</label>
                            <select name="checklist[<?= $item['item_id'] ?>][rust]" class="form-select form-select-sm">
                                <option value="None">Tidak Ada</option>
                                <option value="Minor">Minor (Bisa Bersih)</option>
                                <option value="Major">Major (Reject)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold">Dimensi PxL</label>
                            <select name="checklist[<?= $item['item_id'] ?>][dimension]" class="form-select form-select-sm">
                                <option value="OK">Sesuai</option>
                                <option value="NG">Tidak Sesuai</option>
                            </select>
                        </div>

                    <?php elseif($type == 'coating'): ?>
                        <!-- CHECKLIST: COATING -->
                        <div class="col-md-3">
                            <label class="small fw-bold">Ketebalan (Micron)</label>
                            <input type="text" name="checklist[<?= $item['item_id'] ?>][micron]" class="form-control form-control-sm" placeholder="ex: 60-80">
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold">Warna / Visual</label>
                            <select name="checklist[<?= $item['item_id'] ?>][color]" class="form-select form-select-sm">
                                <option value="OK">Sesuai Sample</option>
                                <option value="NG">Belang / Salah</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold">Adhesion</label>
                            <select name="checklist[<?= $item['item_id'] ?>][adhesion]" class="form-select form-select-sm">
                                <option value="Pass">Pass (Cross Cut)</option>
                                <option value="Fail">Fail (Peeling)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold">Cacat Fisik</label>
                            <select name="checklist[<?= $item['item_id'] ?>][defect]" class="form-select form-select-sm">
                                <option value="None">Mulus</option>
                                <option value="Scratch">Goresan</option>
                                <option value="Bubble">Gelembung</option>
                            </select>
                        </div>

                    <?php elseif($type == 'machining'): ?>
                        <!-- CHECKLIST: MACHINING -->
                        <div class="col-md-4">
                            <label class="small fw-bold">Dimensi vs Drawing</label>
                            <select name="checklist[<?= $item['item_id'] ?>][dimension]" class="form-select form-select-sm">
                                <option value="OK">Presisi / Masuk Tol.</option>
                                <option value="NG">Out of Spec</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold">Surface Finish</label>
                            <select name="checklist[<?= $item['item_id'] ?>][surface]" class="form-select form-select-sm">
                                <option value="Smooth">Halus / Sesuai</option>
                                <option value="Rough">Kasar</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold">Drat / Thread</label>
                            <select name="checklist[<?= $item['item_id'] ?>][thread]" class="form-select form-select-sm">
                                <option value="OK">Gauge GO/NO-GO OK</option>
                                <option value="NG">Loose / Tight</option>
                            </select>
                        </div>

                    <?php else: ?>
                        <!-- GENERAL / CONSUMABLE -->
                        <div class="col-12">
                            <div class="alert alert-info py-1 mb-0 small">
                                <i class="bi bi-check-circle"></i> Item General/Consumable: Cek Kuantitas, Kemasan, dan Kesesuaian Fisik.
                            </div>
                            <input type="hidden" name="checklist[<?= $item['item_id'] ?>][check]" value="General Inspection OK">
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
    <?php endforeach; ?>

    <!-- TOMBOL SIMPAN -->
    <div class="card shadow-sm mb-5">
        <div class="card-body">
            <label class="fw-bold">Catatan Kesimpulan QC:</label>
            <textarea name="notes" class="form-control mb-3" rows="2" placeholder="Contoh: Barang diterima dalam kondisi baik, dokumen lengkap."></textarea>
            
            <div class="d-flex justify-content-between">
                <a href="index.php?page=qc-incoming" class="btn btn-secondary px-4">Batal</a>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow">
                    <i class="bi bi-save"></i> Simpan & Ajukan Approval
                </button>
            </div>
        </div>
    </div>

</form>

<script>
// Fungsi Hitung Otomatis Reject
function calcReject(idx, totalQty) {
    const inputGood = document.querySelector(`.qty-good[data-idx='${idx}']`);
    const inputReject = document.getElementById(`reject_${idx}`);
    
    let good = parseFloat(inputGood.value) || 0;
    let total = parseFloat(totalQty);
    
    if(good > total) {
        alert("Jumlah Good tidak boleh melebihi Jumlah Diterima!");
        inputGood.value = total;
        good = total;
    }
    
    let reject = total - good;
    // Rounding untuk menghindari floating point issue (misal 0.99999)
    inputReject.value = Math.round(reject * 100) / 100;
}
</script>

<?php render_footer(); ?>
