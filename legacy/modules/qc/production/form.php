<?php
// modules/qc/production/form.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('qc_production_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=qc-production';</script>";
    exit;
}

$spk_id = isset($_GET['spk_id']) ? (int)$_GET['spk_id'] : 0;
if ($spk_id <= 0) {
    echo "<script>alert('SPK ID tidak valid.'); window.location='index.php?page=qc-production';</script>";
    exit;
}
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

// 1. AMBIL HEADER SPK & QC TYPE
$stmt_spk = $pdo->prepare("SELECT spk.*, COALESCE(spk.project_name, '-') as project_name, spk.spk_number
                           FROM spk 
                           WHERE spk.id = ?");
$stmt_spk->execute([$spk_id]);
$spk_head = $stmt_spk->fetch();

if (!$spk_head) {
    echo "<script>alert('Data SPK tidak ditemukan.'); window.location='index.php?page=qc-production';</script>";
    exit;
}

// 2. AMBIL ITEM & QC TYPE
// Tambahkan i.qc_type ke query untuk mendeteksi standar QC
$sql_items = "SELECT i.id as item_id, i.item_name, i.item_code, i.unit, i.qc_type, soi.qty as plan_qty,
                     (
                        SELECT COALESCE(SUM(qcp.qty_check), 0)
                        FROM qc_production qcp
                        WHERE qcp.spk_id = spk.id
                     ) as total_checked_previously
              FROM spk
              LEFT JOIN sales_orders so ON so.id = spk.sales_order_id
              JOIN sales_order_items soi ON so.id = soi.sales_order_id
              JOIN items i ON soi.item_id = i.id
              WHERE spk.id = ?";
$stmt_items = $pdo->prepare($sql_items);
$stmt_items->execute([$spk_id]);
$products = $stmt_items->fetchAll(); 

// Ambil QC terakhir jika sudah ada (normalisasi status lama yang anomali)
$stmt_qc_last = $pdo->prepare("SELECT id, qc_number, status, qty_check, qty_reject,
                                      CASE
                                          WHEN COALESCE(qty_reject, 0) > 0
                                               AND (status IS NULL OR status = '' OR status = 'completed')
                                          THEN 'ng'
                                          ELSE status
                                      END AS normalized_status
                               FROM qc_production
                               WHERE spk_id = ?
                               ORDER BY id DESC
                               LIMIT 1");
$stmt_qc_last->execute([$spk_id]);
$qc_last = $stmt_qc_last->fetch(PDO::FETCH_ASSOC);
$qc_last_status = strtolower(trim((string)($qc_last['normalized_status'] ?? $qc_last['status'] ?? '')));

// --- PROSES SIMPAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=qc-production';</script>";
        exit;
    }

    // Cegah double QC jika QC terakhir bukan NG
    if (!empty($qc_last) && $qc_last_status !== 'ng') {
        $print_url = "index.php?page=qc-production&action=print&qc_id=" . (int)$qc_last['id'];
        echo "<script>
                alert('QC untuk SPK ini sudah pernah disimpan (".$qc_last['qc_number'].").');
                window.open('".$print_url."', '_blank');
                window.location='index.php?page=qc-production';
              </script>";
        exit;
    }

    $qc_date = date('Y-m-d');
    $notes = $_POST['notes'];
    
    // Ambil Data Checklist (Jika Ada)
    $chk_laser = isset($_POST['chk_laser']) ? implode(', ', $_POST['chk_laser']) : '-';
    $chk_bend  = isset($_POST['chk_bend']) ? implode(', ', $_POST['chk_bend']) : '-';
    $chk_weld  = isset($_POST['chk_weld']) ? implode(', ', $_POST['chk_weld']) : '-';

    // Rangkum Checklist ke Notes
    $checklist_summary = "";
    if ($chk_laser != '-' || $chk_bend != '-' || $chk_weld != '-') {
        $checklist_summary .= "\n[QC CHECKLIST]";
        $checklist_summary .= "\n- Laser: $chk_laser";
        $checklist_summary .= "\n- Bending: $chk_bend";
        $checklist_summary .= "\n- Welding: $chk_weld";
    }

    $post_item_ids = $_POST['item_id'];
    $post_checks = $_POST['qty_check'];
    $post_oks = $_POST['qty_pass'];
    $post_ngs = $_POST['qty_reject'];

    // Hitung total check input (untuk validasi)
    $total_check_current = 0;
    foreach ($post_checks as $v) {
        $total_check_current += floatval($v);
    }
    
    $total_ok = 0;
    $total_ng = 0;
    $detail_log = ""; 
    $ng_items = [];

    $item_map = [];
    foreach ($products as $p) {
        $item_map[$p['item_id']] = $p;
    }

    // Validasi kuantitas QC agar tidak melebihi sisa yang boleh di-QC
    $is_recheck = (!empty($qc_last) && $qc_last_status === 'ng');
    $max_allowed_check = 0.0;
    if ($is_recheck) {
        $max_allowed_check = max(0.0, (float)($qc_last['qty_reject'] ?? 0));
    } else {
        $total_plan = 0.0;
        $total_checked_prev = 0.0;
        if (!empty($products)) {
            foreach ($products as $p) {
                $total_plan += (float)($p['plan_qty'] ?? 0);
            }
            $total_checked_prev = (float)($products[0]['total_checked_previously'] ?? 0);
        }
        $max_allowed_check = max(0.0, $total_plan - $total_checked_prev);
    }
    if ($total_check_current > ($max_allowed_check + 0.001)) {
        $max_label = $is_recheck ? "sisa NG dari QC sebelumnya" : "sisa qty rencana yang belum di-QC";
        echo "<script>alert('Qty Check melebihi {$max_label}. Maksimum: " . rtrim(rtrim(number_format($max_allowed_check, 2, '.', ''), '0'), '.') . "'); window.location='index.php?page=qc-production';</script>";
        exit;
    }

    // Tentukan operator penanggung jawab default berdasarkan pekerjaan terakhir di SPK ini
    $default_operator_id = null;
    try {
        $stmt_last_op = $pdo->prepare("SELECT operator_id 
                                       FROM production_assignments 
                                       WHERE spk_id = ? AND status = 'completed' AND operator_id IS NOT NULL
                                       ORDER BY end_time DESC, id DESC
                                       LIMIT 1");
        $stmt_last_op->execute([$spk_id]);
        $default_operator_id = $stmt_last_op->fetchColumn();
    } catch (Exception $e) {
        $default_operator_id = null;
    }

    $inTrans = false;
    try {
        $pdo->beginTransaction();
        $inTrans = $pdo->inTransaction();
        
        $ym = date('ym');
        $stmt_no = $pdo->query("SELECT COUNT(*) FROM qc_production WHERE qc_number LIKE 'QC-PRD-$ym-%'");
        $count = $stmt_no->fetchColumn() + 1;
        $qc_number = "QC-PRD-" . $ym . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);

        $stmt_update_stock = $pdo->prepare("UPDATE items SET current_stock = current_stock + ? WHERE id=?");
        $all_items_completed = true;

        for ($i = 0; $i < count($post_item_ids); $i++) {
            $itemId = $post_item_ids[$i];
            $qtyCheck = floatval($post_checks[$i]);
            $qtyOK = floatval($post_oks[$i]);
            $qtyNG = floatval($post_ngs[$i]);
            
            $total_ok += $qtyOK;
            $total_ng += $qtyNG;

            // Cek Sisa untuk menentukan apakah SPK bisa di-close
            foreach($products as $prod) {
                if($prod['item_id'] == $itemId) {
                    $total_checked_so_far = $prod['total_checked_previously'] + $qtyCheck;
                    // Toleransi float kecil
                    if ($total_checked_so_far < ($prod['plan_qty'] - 0.001)) {
                        $all_items_completed = false; 
                    }
                    break;
                }
            }
            
            // Update Stok (Hanya barang OK)
            if ($qtyOK > 0) {
                $stmt_update_stock->execute([$qtyOK, $itemId]);
                $detail_log .= "Item ID $itemId (OK: $qtyOK). ";
            }
            
            // Catat NG untuk NCR otomatis
            if ($qtyNG > 0) {
                $ng_items[] = [
                    'item_id' => $itemId,
                    'qty' => $qtyNG
                ];
            }
        }

        // Simpan Header QC + Summary Checklist di notes
        $final_notes = $notes . " " . $detail_log . $checklist_summary;
        $qc_status = ($total_ng > 0) ? 'ng' : 'completed';
        
        $sql_ins = "INSERT INTO qc_production (qc_number, spk_id, qc_date, inspector_id, status, qty_check, qty_pass, qty_reject, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $pdo->prepare($sql_ins)->execute([
            $qc_number, $spk_id, $qc_date, $_SESSION['user_id'], $qc_status,
            $total_check_current, $total_ok, $total_ng, $final_notes, $_SESSION['user_id']
        ]);
        $new_qc_id = (int)$pdo->lastInsertId();

        // Hardening untuk database lama: pastikan record NG tidak tersimpan sebagai kosong/completed.
        if ($total_ng > 0) {
            $pdo->prepare("UPDATE qc_production SET status='ng' WHERE id=?")->execute([$new_qc_id]);
        }

        // Auto create NCR jika ada NG
        if (!empty($ng_items)) {
            $ym_ncr = date('ym');
            $stmt_ncr_no = $pdo->query("SELECT COUNT(*) FROM ncr WHERE ncr_number LIKE 'NCR-$ym_ncr-%'");
            $ncr_count = (int)$stmt_ncr_no->fetchColumn();
            $stmt_ncr_ins = $pdo->prepare("INSERT INTO ncr (ncr_number, source_type, reference_id, item_id, qty_reject, issue_description, root_cause, corrective_action, operator_id, disposition, status, created_by) 
                                            VALUES (?, 'production', ?, ?, ?, ?, '', '', ?, 'pending', 'waiting_responsible', ?)");

            $idx = 0;
            foreach ($ng_items as $ng) {
                $idx++;
                $ncr_num = "NCR-" . $ym_ncr . "-" . str_pad($ncr_count + $idx, 4, '0', STR_PAD_LEFT);
                $item_id = (int)$ng['item_id'];
                $qty_ng = (float)$ng['qty'];
                $item_name = $item_map[$item_id]['item_name'] ?? 'Item';
                $item_code = $item_map[$item_id]['item_code'] ?? '-';
                $issue = "Reject QC Produksi - SPK {$spk_head['spk_number']} ({$item_code} - {$item_name})";
                $stmt_ncr_ins->execute([$ncr_num, $new_qc_id, $item_id, $qty_ng, $issue, $default_operator_id, $_SESSION['user_id']]);
                $new_ncr_id = (int)$pdo->lastInsertId();

                if (function_exists('notify_workflow_event')) {
                    notify_workflow_event(
                        'qc.production.ncr.' . (int)$new_ncr_id,
                        'NCR Menunggu Tanda Tangan Penanggung Jawab',
                        "NCR $ncr_num dibuat otomatis dari QC. Mohon tanda tangan penanggung jawab.",
                        'index.php?page=qc-ncr&action=edit&id=' . (int)$new_ncr_id,
                        'danger',
                        ['permission_slug' => 'qc_ncr_resp_approve']
                    );
                }
            }
        }
        
        // Jika semua item sudah dicek sesuai target qty dan tidak ada NG, tutup SPK
        if ($all_items_completed && $total_ng <= 0) {
            $pdo->prepare("UPDATE spk SET status='closed' WHERE id=?")->execute([$spk_id]);
            if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'qc.production.close_spk.' . (int)$spk_id,
                    'SPK Ditutup oleh QC',
                    "SPK {$spk_head['spk_number']} telah selesai QC dan ditutup.",
                    "index.php?page=eng-partlist&view=archive",
                    'success',
                    ['target_roles' => ['executive', 'manager']]
                );
            }

            // WA otomatis ke customer via Fonte saat SPK close oleh QC
            if (function_exists('send_wa_fonte')) {
                try {
                    $stmt_cust = $pdo->prepare("SELECT c.name AS cust_name, c.phone AS cust_phone
                                                FROM spk s
                                                LEFT JOIN sales_orders so ON so.id = s.sales_order_id
                                                LEFT JOIN customers c ON c.id = so.customer_id
                                                WHERE s.id = ?
                                                LIMIT 1");
                    $stmt_cust->execute([$spk_id]);
                    $cust = $stmt_cust->fetch(PDO::FETCH_ASSOC);
                    if ($cust && !empty($cust['cust_phone'])) {
                        $msg = "Yth. {$cust['cust_name']},\n";
                        $msg .= "Produksi dan QC untuk SPK {$spk_head['spk_number']} telah selesai (CLOSE).\n";
                        $msg .= "Terima kasih atas kepercayaannya.";
                        send_wa_fonte($cust['cust_phone'], $msg);
                    }
                } catch (Exception $e) {
                    // Jangan hentikan proses utama jika kirim WA gagal.
                }
            }
        }
        
        if (!empty($inTrans) && $pdo->inTransaction()) {
            $pdo->commit();
        }
        $print_url = "index.php?page=qc-production&action=print&qc_id=" . $new_qc_id;
        echo "<script>
                alert('QC Disimpan! Verifikasi dan label akan dibuka untuk dicetak.');
                window.open('".$print_url."', '_blank');
                window.location='index.php?page=qc-production';
              </script>";
        exit;

    } catch (Exception $e) {
        if (!empty($inTrans) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<script>alert('Gagal menyimpan QC produksi.'); window.location='index.php?page=qc-production';</script>";
        exit;
    }
}

render_header("Input Hasil QC Produksi");

// Cek apakah ada item yang tipe QC-nya "sheet_metal" untuk menampilkan checklist khusus
$is_sheet_metal = false;
foreach($products as $p) {
    if($p['qc_type'] == 'sheet_metal') {
        $is_sheet_metal = true;
        break;
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-10"> 
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-check2-all"></i> Form QC Final</h5>
            </div>
            <div class="card-body">
                
                <div class="row mb-3 bg-light p-3 rounded border mx-0">
                    <div class="col-md-4">
                        <label class="small text-muted">No. SPK</label>
                        <div class="fw-bold"><?= $esc($spk_head['spk_number']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted">Project</label>
                        <div class="fw-bold"><?= $esc($spk_head['project_name']) ?></div>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-warning text-dark">Total QC Sebelumnya:</span>
                        <?php 
                        $grand_checked = 0;
                        foreach($products as $p) $grand_checked += $p['total_checked_previously'];
                        echo "<strong>$grand_checked Pcs</strong>";
                        ?>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
                    
                    <!-- AREA CHECKLIST SHEET METAL (HANYA MUNCUL JIKA ADA ITEM BERTIPE SHEET METAL) -->
                    <?php if($is_sheet_metal): ?>
                    <div class="card mb-4 border-info">
                        <div class="card-header bg-info text-dark fw-bold">
                            <i class="bi bi-list-check"></i> Standard Checklist: Sheet Metal Process
                        </div>
                        <div class="card-body bg-light">
                            <div class="row">
                                <!-- LASER CHECK -->
                                <div class="col-md-4">
                                    <h6 class="text-primary border-bottom pb-1">Laser Cutting</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="chk_laser[]" value="Clean Cut / No Dross" id="lc1">
                                        <label class="form-check-label" for="lc1">Potongan Bersih (No Dross)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="chk_laser[]" value="Dimensi Akurat" id="lc2">
                                        <label class="form-check-label" for="lc2">Dimensi Akurat (+/- 0.2mm)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="chk_laser[]" value="No Scratch" id="lc3">
                                        <label class="form-check-label" for="lc3">Permukaan Halus (No Scratch)</label>
                                    </div>
                                </div>

                                <!-- BENDING CHECK -->
                                <div class="col-md-4">
                                    <h6 class="text-warning text-dark border-bottom pb-1">Bending</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="chk_bend[]" value="Sudut Sesuai" id="bd1">
                                        <label class="form-check-label" for="bd1">Sudut Tekuk Sesuai</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="chk_bend[]" value="Panjang Tekuk Sesuai" id="bd2">
                                        <label class="form-check-label" for="bd2">Panjang Tekukan Sesuai</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="chk_bend[]" value="No Cracking" id="bd3">
                                        <label class="form-check-label" for="bd3">Tidak Retak (Cracking)</label>
                                    </div>
                                </div>

                                <!-- WELDING CHECK -->
                                <div class="col-md-4">
                                    <h6 class="text-danger border-bottom pb-1">Welding / Assembling</h6>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="chk_weld[]" value="Las Matang/Kuat" id="wd1">
                                        <label class="form-check-label" for="wd1">Las Matang & Kuat</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="chk_weld[]" value="Finishing Rapi" id="wd2">
                                        <label class="form-check-label" for="wd2">Finishing Gerinda Rapi</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="chk_weld[]" value="Posisi Akurat" id="wd3">
                                        <label class="form-check-label" for="wd3">Posisi Part Akurat</label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-text mt-2 text-muted">* Centang poin yang SUDAH dicek dan OK. Jika ada yang NG, jangan dicentang dan masukkan ke Qty Reject.</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- TABEL INPUT QTY -->
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light text-center">
                                <tr>
                                    <th width="30%">Nama Barang</th>
                                    <th width="10%">Target</th>
                                    <th width="10%">Sisa</th>
                                    <th width="15%">Cek Sekarang</th>
                                    <th width="12%" class="text-success">OK</th>
                                    <th width="12%" class="text-danger">NG</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($products as $idx => $p): 
                                    // Hitung sisa yang belum dicek
                                    $sisa = $p['plan_qty'] - $p['total_checked_previously'];
                                    // Cegah minus jika over-checked
                                    if($sisa < 0) $sisa = 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong class="text-dark"><?= $esc($p['item_name']) ?></strong><br>
                                        <small class="text-muted"><?= $esc($p['item_code']) ?></small>
                                        <input type="hidden" name="item_id[]" value="<?= (int)$p['item_id'] ?>">
                                        <?php if($p['qc_type'] == 'sheet_metal'): ?>
                                            <span class="badge bg-secondary" style="font-size: 0.6rem;">Sheet Metal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center bg-light"><?= $p['plan_qty'] + 0 ?></td>
                                    <td class="text-center fw-bold"><?= $sisa + 0 ?></td>
                                    <td>
                                        <input type="number" name="qty_check[]" class="form-control text-center border-primary" 
                                               value="<?= $sisa ?>" min="0" step="0.01" 
                                               id="check_<?= $idx ?>" oninput="autoCalc(<?= $idx ?>)" required>
                                    </td>
                                    <td>
                                        <input type="number" name="qty_pass[]" id="ok_<?= $idx ?>" class="form-control text-center fw-bold text-success" 
                                               value="<?= $sisa ?>" min="0" step="0.01" oninput="autoCalc(<?= $idx ?>)" required>
                                    </td>
                                    <td>
                                        <input type="number" name="qty_reject[]" id="ng_<?= $idx ?>" class="form-control text-center fw-bold text-danger bg-light" 
                                               value="0" step="0.01" readonly>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Catatan Inspeksi</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="index.php?page=qc-production" class="btn btn-secondary px-4">Batal</a>
                        <button type="submit" class="btn btn-success px-4 fw-bold shadow">
                            <i class="bi bi-save"></i> Simpan Hasil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function autoCalc(idx) {
    const inputCheck = document.getElementById('check_' + idx);
    const inputOK = document.getElementById('ok_' + idx);
    const inputNG = document.getElementById('ng_' + idx);
    
    let check = parseFloat(inputCheck.value) || 0;
    let ok = parseFloat(inputOK.value) || 0;
    
    // Validasi: OK tidak boleh lebih dari Checked
    if (ok > check) {
        inputOK.value = check;
        ok = check;
    }
    
    // Hitung NG otomatis
    let ng = check - ok;
    // Pembulatan desimal untuk menghindari 0.000000001
    inputNG.value = Math.round(ng * 100) / 100;
}
</script>

<?php render_footer(); ?>
