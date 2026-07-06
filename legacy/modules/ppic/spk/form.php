<?php
// modules/ppic/spk/form.php

require_once __DIR__ . '/service.php';

$id = isset($_GET['id']) ? $_GET['id'] : null;
// Prioritas ID SO: 1. Dari Input POST (saat reload), 2. Dari URL (saat create new), 3. Null
$so_id = isset($_POST['sales_order_id']) ? $_POST['sales_order_id'] : (isset($_GET['so_id']) ? $_GET['so_id'] : null);
$has_so_id = ($so_id !== null && $so_id !== '');
$is_edit = $id ? true : false;

// Default Data
$data = [
    'spk_number' => 'AUTO',
    'spk_date' => date('Y-m-d'),
    'deadline_date' => date('Y-m-d', strtotime('+7 days')),
    'sales_order_id' => $so_id, // Set awal dari input
    'priority' => 'normal',
    'notes' => '',
    'required_processes' => '',
    'customer_name' => '',
    'so_number' => ''
];
$materials = [];
$so_items = [];
$missing_bom_items = []; 
$missing_bom_details = [];
$bom_request_sent = false;
$selected_processes = isset($_POST['processes']) && is_array($_POST['processes']) ? $_POST['processes'] : [];
$internal_processes = ['Fibre Laser', 'CO Laser', 'Metal Bending', 'Acrylic Bending', 'Welding', 'Assembling'];
$subcon_processes = ['Powder Coating', 'Plating', 'Hot Deep Galv', 'Machining'];
$csrf = mms_csrf_token();

try {
    // --- 1. LOAD DATA EDIT (Jika Edit) ---
    if ($is_edit) {
        $stmt = $pdo->prepare("SELECT s.*, so.so_number, c.name as customer_name 
                               FROM spk s
                               JOIN sales_orders so ON s.sales_order_id = so.id
                               JOIN customers c ON so.customer_id = c.id
                               WHERE s.id = ?");
        $stmt->execute([$id]);
        $data_fetch = $stmt->fetch();
        if(!$data_fetch) throw new Exception("Data SPK tidak ditemukan.");
        
        // Merge data DB ke variabel data, tapi sales_order_id dari POST (jika ada) menang
        $saved_so_id = $data_fetch['sales_order_id'];
        $data = array_merge($data, $data_fetch);
        
        // Jika user mengubah SO di dropdown (POST), pakai yang baru. Jika tidak, pakai yang di DB.
        if ($has_so_id) {
            $data['sales_order_id'] = $so_id;
        } else {
            $data['sales_order_id'] = $saved_so_id;
        }

        // Load Material Existing (Hanya jika SO tidak berubah)
        if ($data['sales_order_id'] == $saved_so_id) {
            $stmt_mat = $pdo->prepare("SELECT sm.*, i.item_name, i.item_code, i.unit, i.current_stock 
                                       FROM spk_materials sm 
                                       JOIN items i ON sm.item_id = i.id 
                                       WHERE sm.spk_id = ?");
            $stmt_mat->execute([$id]);
            $materials = $stmt_mat->fetchAll();
        }
    }

    if ($is_edit && empty($selected_processes) && !empty($data['required_processes'])) {
        $selected_processes = array_filter(array_map('trim', explode(',', $data['required_processes'])));
    }

    // --- 2. LOGIKA MRP & INFO SO ---
    // Jalan jika ada sales_order_id (baik dari edit, get, atau post refresh)
    $ref_so = $data['sales_order_id'];
    $has_ref_so = ($ref_so !== null && $ref_so !== '');
    
    if ($has_ref_so) {
        // Ambil Info Header SO (Customer, No SO)
        $stmt_so_head = $pdo->prepare("SELECT so.so_number, c.name as customer_name 
                                       FROM sales_orders so 
                                       JOIN customers c ON so.customer_id = c.id 
                                       WHERE so.id = ?");
        $stmt_so_head->execute([$ref_so]);
        $so_info = $stmt_so_head->fetch();
        if ($so_info) {
            $data['so_number'] = $so_info['so_number'];
            $data['customer_name'] = $so_info['customer_name'];
        }

        // Ambil Item Produksi (Finish Good dari SO)
        $materialExpr = ppic_spk_material_select_expr($pdo);
        $stmt_so_items = $pdo->prepare("SELECT soi.item_id, soi.qty,
                                               COALESCE(i.item_name, soi.item_name_manual, '') AS item_name,
                                               COALESCE(i.item_code, soi.item_code_manual, '') AS item_code,
                                               COALESCE(i.unit, soi.unit_manual, '') AS unit,
                                               $materialExpr AS material
                                        FROM sales_order_items soi
                                        LEFT JOIN items i ON soi.item_id = i.id
                                        WHERE soi.sales_order_id = ?");
        $stmt_so_items->execute([$ref_so]);
        $so_items = $stmt_so_items->fetchAll();

        // Hitung Ulang MRP jika:
        // 1. User mengubah dropdown SO (POST calculate_mrp ada)
        // 2. Atau material masih kosong (baru buat)
        // 3. Atau sedang Edit tapi user mengganti SO (ref_so beda dengan saved_so)
        $should_calc_mrp = isset($_POST['calculate_mrp']) || empty($materials) || ($is_edit && $ref_so != $saved_so_id);

        if ($should_calc_mrp) {
            $raw_needs = [];
            
            foreach ($so_items as $prod) {
                // Cek BOM
                $stmt_bom = $pdo->prepare("SELECT id, qty_result 
                                           FROM boms 
                                           WHERE item_id = ? 
                                             AND status IN ('active', 'locked', 'draft')
                                           ORDER BY FIELD(status, 'active', 'locked', 'draft'), id DESC 
                                           LIMIT 1");
                $stmt_bom->execute([$prod['item_id']]);
                $bom = $stmt_bom->fetch();

                if ($bom) {
                    // Ambil detail material
                    $stmt_bom_mat = $pdo->prepare("SELECT bd.material_id, bd.qty, i.item_name, i.item_code, i.unit, i.current_stock 
                                                   FROM bom_details bd
                                                   JOIN items i ON bd.material_id = i.id
                                                   WHERE bd.bom_id = ?");
                    $stmt_bom_mat->execute([$bom['id']]);
                    $bom_mats = $stmt_bom_mat->fetchAll();

                    if (empty($bom_mats)) {
                        $missing_bom_items[] = $prod['item_name'] . " (BOM tanpa detail)";
                        $missing_bom_details[$prod['item_id']] = [
                            'item_id' => (int)$prod['item_id'],
                            'item_code' => (string)($prod['item_code'] ?? ''),
                            'item_name' => (string)($prod['item_name'] ?? ''),
                            'reason' => 'BOM tanpa detail'
                        ];
                        continue;
                    }

                    $bom_output = isset($bom['qty_result']) && (float)$bom['qty_result'] > 0 ? (float)$bom['qty_result'] : 1.0;
                    $qty_so = (float)$prod['qty'];

                    foreach ($bom_mats as $bm) {
                        // Kebutuhan proporsional terhadap output BOM.
                        $total_need = ((float)$bm['qty'] / $bom_output) * $qty_so;
                        $mid = $bm['material_id'];
                        
                        if (isset($raw_needs[$mid])) {
                            $raw_needs[$mid]['qty_required'] += $total_need;
                        } else {
                            $raw_needs[$mid] = [
                                'item_id' => $mid,
                                'item_code' => $bm['item_code'],
                                'item_name' => $bm['item_name'],
                                'unit' => $bm['unit'],
                                'current_stock' => $bm['current_stock'],
                                'qty_required' => $total_need
                            ];
                        }
                    }
                } else {
                    $missing_bom_items[] = $prod['item_name'];
                    $missing_bom_details[$prod['item_id']] = [
                        'item_id' => (int)$prod['item_id'],
                        'item_code' => (string)($prod['item_code'] ?? ''),
                        'item_name' => (string)($prod['item_name'] ?? ''),
                        'reason' => 'BOM belum dibuat'
                    ];
                }
            }
            // Reset materials dengan hasil hitungan baru
            $materials = array_values($raw_needs);
            $missing_bom_items = array_values(array_unique($missing_bom_items));
        }

        // Cek apakah instruksi BOM sudah pernah dikirim untuk semua item yang missing
        if (!empty($missing_bom_details)) {
            try {
                $links = [];
                foreach ($missing_bom_details as $d) {
                    $item_id = (int)$d['item_id'];
                    $links[] = "index.php?page=eng-bom&action=create&so_id={$ref_so}&item_id={$item_id}";
                }
                if (!empty($links)) {
                    $placeholders = implode(',', array_fill(0, count($links), '?'));
                    $stmt_exist = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND link IN ($placeholders)");
                    $stmt_exist->execute($links);
                    $cnt_exist = (int)$stmt_exist->fetchColumn();
                    if ($cnt_exist >= count($links)) {
                        $bom_request_sent = true;
                    }
                }
            } catch (Exception $e) {
                $bom_request_sent = false;
            }
        }
    }

    // --- 3. PROSES SIMPAN SPK ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_spk'])) {
        $so_ref = $_POST['sales_order_id'];
        
        if ($so_ref === null || $so_ref === '') throw new Exception("Pilih Sales Order terlebih dahulu!");
        
        // Blokir jika BOM kurang, KECUALI user ingin simpan sebagai draft/waiting (opsional)
        // Di sini kita blokir sesuai request sebelumnya agar data valid
        if (!empty($missing_bom_items)) {
            throw new Exception("Tidak bisa simpan: BOM belum lengkap.");
        }

        $csrfForm = $_POST['csrf'] ?? '';
        if (!verify_mms_csrf_token($csrfForm)) {
            throw new Exception("Token keamanan tidak valid. Refresh halaman lalu coba lagi.");
        }

        $saved = ppic_spk_save($pdo, [
            'id' => $is_edit ? (int)$id : 0,
            'sales_order_id' => $so_ref,
            'spk_date' => $_POST['spk_date'] ?? date('Y-m-d'),
            'deadline_date' => $_POST['deadline_date'] ?? date('Y-m-d'),
            'priority' => $_POST['priority'] ?? 'normal',
            'notes' => $_POST['notes'] ?? '',
            'processes' => isset($_POST['processes']) && is_array($_POST['processes']) ? $_POST['processes'] : [],
            'mat_id' => $_POST['mat_id'] ?? [],
            'mat_qty' => $_POST['mat_qty'] ?? [],
            'user_id' => (int)($_SESSION['user_id'] ?? 0),
        ]);
        $spk_id = (int)$saved['spk_id'];
        $spk_number = (string)$saved['spk_number'];
        
        // Notif Engineering saat SPK baru dibuat (status awal: preliminary).
        if (!$saved['is_edit'] && function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'ppic.spk.preliminary.' . $spk_id,
                'Request Drawing & Partlist',
                "SPK {$spk_number} status PRELIMINARY. Mohon Engineering membuat drawing dan partlist.",
                "index.php?page=eng-partlist&action=create&spk_id={$spk_id}",
                'info',
                [
                    'permission_slug' => 'eng_partlist_manage',
                    'ttl_seconds' => 86400,
                ]
            );
        }

        echo "<script>alert('SPK berhasil disimpan!'); window.location='index.php?page=ppic-spk';</script>";
        exit;
    }

} catch (Exception $e) {
    $raw = (string)$e->getMessage();
    if (
        stripos($raw, 'Pilih Sales Order') !== false ||
        stripos($raw, 'BOM') !== false ||
        stripos($raw, 'Token keamanan') !== false ||
        stripos($raw, 'Data SPK tidak ditemukan') !== false ||
        stripos($raw, 'Session user tidak valid') !== false
    ) {
        $error = $raw;
    } else {
        error_log('[PPIC-SPK form] ' . $raw);
        $error = "Terjadi kesalahan sistem saat memproses SPK.";
    }
}

// Load List SO untuk Dropdown
$sql_dropdown = "SELECT so.id, so.so_number 
                 FROM sales_orders so 
                 WHERE so.status IN ('confirmed', 'in_production') 
                 ORDER BY so.id DESC";
$sales_orders = $pdo->query($sql_dropdown)->fetchAll();

render_header($is_edit ? "Edit SPK" : "Buat SPK Baru");
?>

<div class="container-fluid">
    <form method="POST">
        <!-- Hidden ID untuk Edit -->
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        
        <?php if(isset($error)): ?><div class="alert alert-danger fw-bold"><?= $error ?></div><?php endif; ?>
        
<?php if(!empty($missing_bom_items)): ?>
        <div class="alert alert-danger d-flex justify-content-between align-items-center shadow-sm border-start border-5 border-danger mb-4">
            <div>
                <h5 class="alert-heading fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill"></i> STOP! BOM Belum Lengkap</h5>
                Item berikut belum memiliki BOM: <strong><?= implode(", ", $missing_bom_items) ?></strong>
            </div>
            <div class="d-flex gap-2">
                <button
                    type="button"
                    id="btnReqBom"
                    class="btn btn-danger btn-sm fw-bold"
                    <?= $bom_request_sent ? 'disabled' : '' ?>
                    onclick="sendBomRequestToEngineering(<?= (int)($data['sales_order_id'] ?? 0) ?>)">
                    <?= $bom_request_sent ? 'SUDAH DIINSTRUKSIKAN KE ENGINEERING' : 'INSTRUKSIKAN KE ENGINEERING' ?>
                </button>
                <?php
                $first_missing_id = 0;
                foreach ($missing_bom_details as $d) { $first_missing_id = (int)$d['item_id']; break; }
                ?>
                <?php if ($first_missing_id > 0): ?>
                    <a href="index.php?page=eng-bom&action=create&item_id=<?= $first_missing_id ?>&so_id=<?= (int)($data['sales_order_id'] ?? 0) ?>" class="btn btn-outline-danger btn-sm fw-bold">BUKA ITEM PERTAMA</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <fieldset <?= !empty($missing_bom_items) ? 'disabled' : '' ?>>
            <div class="row">
                <div class="col-md-4">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-primary text-white fw-bold">Data Utama SPK</div>
                        <div class="card-body">
                             <div class="mb-3">
                                <label class="form-label fw-bold">No. SPK</label>
                                <input type="text" class="form-control fw-bold bg-light" value="<?= $data['spk_number'] ?>" readonly>
                             </div>
                             <div class="mb-3">
                                <label class="form-label fw-bold">Ref. Sales Order <span class="text-danger">*</span></label>
                                <!-- PENTING: Value select ini akan terisi dari $data['sales_order_id'] yang sudah di-handle di atas -->
                                <select name="sales_order_id" class="form-select select2" onchange="this.form.submit()" required>
                                    <option value="">-- Pilih SO --</option>
                                    <?php foreach($sales_orders as $s): 
                                        $selected = ($s['id'] == $data['sales_order_id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $s['id'] ?>" <?= $selected ?>><?= $s['so_number'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- Hidden input untuk trigger logic hitung MRP saat submit karena change -->
                                <input type="hidden" name="calculate_mrp" value="1">
                             </div>

                             <?php if($data['sales_order_id'] !== null && $data['sales_order_id'] !== ''): ?>
                             <div class="mb-3">
                                 <label class="form-label text-muted small">Customer</label>
                                 <input type="text" class="form-control bg-light fw-bold" value="<?= htmlspecialchars($data['customer_name']) ?>" readonly>
                             </div>
                             <?php endif; ?>

                             <div class="mb-3">
                                <label class="form-label">Tgl SPK</label>
                                <input type="date" name="spk_date" class="form-control" value="<?= $data['spk_date'] ?>" required>
                             </div>
                             <div class="mb-3">
                                <label class="form-label">Target Selesai</label>
                                <input type="date" name="deadline_date" class="form-control" value="<?= $data['deadline_date'] ?>" required>
                             </div>
                             <div class="mb-3">
                                <label class="form-label">Prioritas</label>
                                <select name="priority" class="form-select fw-bold">
                                    <option value="normal" <?= $data['priority']=='normal'?'selected':'' ?>>Normal</option>
                                    <option value="high" <?= $data['priority']=='high'?'selected':'' ?>>High</option>
                                    <option value="urgent" <?= $data['priority']=='urgent'?'selected':'' ?>>Urgent</option>
                                </select>
                             </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    
                    <!-- INFO ITEM PRODUKSI -->
                    <div class="card shadow-sm mb-3 border-warning">
                        <div class="card-header bg-warning text-dark fw-bold">2. Item Produksi (Finished Goods dari SO)</div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0 small">
                                <thead class="table-light text-center">
                                    <tr><th>Kode Item</th><th>Nama Barang</th><th>Material</th><th width="100">Qty SO</th></tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($so_items)): ?>
                                        <tr><td colspan="4" class="text-center py-3 text-muted">Silakan Pilih Sales Order</td></tr>
                                    <?php else: foreach($so_items as $si): ?>
                                    <tr>
                                        <td class="fw-bold px-3"><?= $si['item_code'] ?></td>
                                        <td><?= htmlspecialchars($si['item_name']) ?></td>
                                        <td><?= htmlspecialchars($si['material'] ?? '-') ?></td>
                                        <td class="text-center fw-bold text-primary"><?= $si['qty']+0 ?> <?= $si['unit'] ?></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TABEL MRP -->
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-light fw-bold">1. Analisa Kebutuhan Material (MRP)</div>
                        <div class="card-body p-0">
                            <table class="table table-bordered mb-0 small">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th>Material</th>
                                        <th width="80">Stok</th>
                                        <th width="120">Dibutuhkan</th>
                                        <th width="80">Unit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($materials)): ?>
                                        <tr><td colspan="4" class="text-center py-4 text-muted italic">Pilih Sales Order untuk memuat kebutuhan material</td></tr>
                                    <?php else: foreach($materials as $m): 
                                        $bg_stok = ($m['current_stock'] < $m['qty_required']) ? 'text-danger fw-bold' : 'text-success';
                                    ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($m['item_name']) ?>
                                                <input type="hidden" name="mat_id[]" value="<?= $m['item_id'] ?>">
                                            </td>
                                            <td class="text-center <?= $bg_stok ?>"><?= $m['current_stock']+0 ?></td>
                                            <td><input type="number" step="any" name="mat_qty[]" value="<?= $m['qty_required']+0 ?>" class="form-control form-control-sm text-end fw-bold border-primary"></td>
                                            <td class="text-center small"><?= $m['unit'] ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-light fw-bold">3. Route Proses Produksi</div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-md-2 fw-bold small text-muted">INTERNAL</div>
                                <div class="col-md-10">
                                    <?php foreach ($internal_processes as $proc): ?>
                                        <div class="form-check form-check-inline me-3 mb-2">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="processes[]"
                                                value="<?= htmlspecialchars($proc) ?>"
                                                id="proc_<?= md5($proc) ?>"
                                                <?= in_array($proc, $selected_processes, true) ? 'checked' : '' ?>
                                            >
                                            <label class="form-check-label small" for="proc_<?= md5($proc) ?>"><?= htmlspecialchars($proc) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <hr class="my-2">
                            <div class="row g-2">
                                <div class="col-md-2 fw-bold small text-muted">SUB-CON</div>
                                <div class="col-md-10">
                                    <?php foreach ($subcon_processes as $proc): ?>
                                        <div class="form-check form-check-inline me-3 mb-2">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="processes[]"
                                                value="<?= htmlspecialchars($proc) ?>"
                                                id="proc_<?= md5($proc) ?>"
                                                <?= in_array($proc, $selected_processes, true) ? 'checked' : '' ?>
                                            >
                                            <label class="form-check-label small" for="proc_<?= md5($proc) ?>"><?= htmlspecialchars($proc) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Catatan Produksi</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Tulis instruksi khusus jika ada..."><?= $data['notes'] ?></textarea>
                    </div>

                    <div class="text-end mb-5">
                        <a href="index.php?page=ppic-spk" class="btn btn-secondary px-4 me-2">Batal</a>
                        <button type="submit" name="save_spk" class="btn btn-primary btn-lg px-5 shadow fw-bold">
                            <i class="bi bi-save-fill me-2"></i> SIMPAN & RILIS SPK
                        </button>
                    </div>
                </div>
            </div>
        </fieldset>
    </form>
</div>
<script>
async function sendBomRequestToEngineering(soId) {
    if (!soId) {
        alert('SO belum dipilih.');
        return;
    }
    const btn = document.getElementById('btnReqBom');
    if (btn && btn.disabled) return;
    try {
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'MENGIRIM...';
        }
        const body = new URLSearchParams({
            so_id: String(soId),
            csrf: '<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>'
        });
        const res = await fetch('modules/ppic/spk/request_bom_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        });
        const data = await res.json();
        if (data.status === 'success') {
            const total = data.notified_count || 0;
            alert('Instruksi BOM berhasil dikirim ke Engineering (' + total + ' item).');
            if (btn) {
                btn.textContent = 'SUDAH DIINSTRUKSIKAN KE ENGINEERING';
                btn.disabled = true;
            }
        } else {
            alert('Gagal kirim instruksi BOM: ' + (data.message || 'Unknown error'));
            if (btn) {
                btn.textContent = 'INSTRUKSIKAN KE ENGINEERING';
                btn.disabled = false;
            }
        }
    } catch (err) {
        alert('Gagal kirim instruksi BOM. Cek koneksi/server.');
        if (btn) {
            btn.textContent = 'INSTRUKSIKAN KE ENGINEERING';
            btn.disabled = false;
        }
    }
}
</script>
<?php render_footer(); ?>
