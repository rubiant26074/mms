<?php
// modules/warehouse/receive/form.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('whse_receive_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=whse-receive';</script>";
    exit;
}

$id = isset($_GET['id']) ? $_GET['id'] : null;
$po_id = isset($_GET['po_id']) ? $_GET['po_id'] : null;
$is_edit = $id ? true : false;
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

$data = [
    'gr_number' => 'AUTO',
    'gr_date' => date('Y-m-d'),
    'receipt_type' => 'normal', // Default Normal
    'purchase_order_id' => '',
    'customer_id' => '',
    'delivery_note_number' => '',
    'driver_name' => '',
    'vehicle_number' => '',
    'received_by' => $_SESSION['fullname'],
    'status' => 'draft',
    'notes' => ''
];
$items = [];

// --- LOGIKA LOAD DATA EDIT ---
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM goods_receipts WHERE id = ?");
    $stmt->execute([$id]);
    $fetch = $stmt->fetch();
    if(!$fetch) die("Data GR tidak ditemukan");
    
    // Merge data
    $data = array_merge($data, $fetch);

    $stmt_items = $pdo->prepare("SELECT gri.*, i.item_name, i.item_code, i.unit 
                                 FROM goods_receipt_items gri 
                                 JOIN items i ON gri.item_id = i.id 
                                 WHERE gri.goods_receipt_id = ?");
    $stmt_items->execute([$id]);
    $items = $stmt_items->fetchAll();
} elseif ($po_id) {
    $data['purchase_order_id'] = $po_id;
}

// --- PROSES SIMPAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    } else {
    $type = $_POST['receipt_type'];
    // Validasi input berdasarkan tipe
    $po_ref = ($type == 'normal') ? $_POST['purchase_order_id'] : null;
    $cust_ref = ($type == 'consignment') ? $_POST['customer_id'] : null;
    
    $date = $_POST['gr_date'];
    $sj_num = clean($_POST['delivery_note_number']);
    $driver = clean($_POST['driver_name']);
    $plat = clean($_POST['vehicle_number']);
    $rec_by = clean($_POST['received_by']);
    $notes = clean($_POST['notes']);
    
    // Status Logic: Jika klik 'Simpan & QC', status jadi qc_pending
    $status = isset($_POST['submit_qc']) ? 'qc_pending' : 'draft';

    $item_ids = $_POST['item_id'] ?? [];
    $qty_recs = $_POST['qty_received'] ?? [];
    $qty_pos  = $_POST['qty_po'] ?? []; // Bisa array kosong jika consignment

    try {
        $pdo->beginTransaction();

        if (!$is_edit) {
            $ym = date('ym');
            $stmt_no = $pdo->query("SELECT COUNT(*) FROM goods_receipts WHERE gr_number LIKE 'GR-$ym-%'");
            $count = $stmt_no->fetchColumn() + 1;
            $gr_number = "GR-" . $ym . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO goods_receipts (gr_number, receipt_type, purchase_order_id, customer_id, gr_date, delivery_note_number, driver_name, vehicle_number, received_by, status, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$gr_number, $type, $po_ref, $cust_ref, $date, $sj_num, $driver, $plat, $rec_by, $status, $notes, $_SESSION['user_id']]);
            $gr_id = $pdo->lastInsertId();

        } else {
            $sql = "UPDATE goods_receipts SET receipt_type=?, purchase_order_id=?, customer_id=?, gr_date=?, delivery_note_number=?, driver_name=?, vehicle_number=?, received_by=?, status=?, notes=? WHERE id=?";
            $pdo->prepare($sql)->execute([$type, $po_ref, $cust_ref, $date, $sj_num, $driver, $plat, $rec_by, $status, $notes, $id]);
            $gr_id = $id;
            
            // Hapus item lama untuk insert ulang
            $pdo->prepare("DELETE FROM goods_receipt_items WHERE goods_receipt_id=?")->execute([$id]);
        }

        $stmt_ins = $pdo->prepare("INSERT INTO goods_receipt_items (goods_receipt_id, item_id, qty_po, qty_received) VALUES (?, ?, ?, ?)");
        
        // Ambil Qty PO asli untuk referensi di tabel GR items
        $stmt_po_qty = $pdo->prepare("SELECT qty FROM purchase_order_items WHERE purchase_order_id = ? AND item_id = ?");

        for ($i = 0; $i < count($item_ids); $i++) {
            // Jika consignment, qty_po = 0
            $val_po = ($type == 'normal' && isset($qty_pos[$i])) ? $qty_pos[$i] : 0;
            $stmt_ins->execute([$gr_id, $item_ids[$i], $val_po, $qty_recs[$i]]);
        }
        
        $pdo->commit();

        // --- NOTIFIKASI KE QC INCOMING ---
        // Jika statusnya bukan draft (langsung submit ke QC) atau ini adalah barang masuk
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'whse.gr.received.' . (int)$gr_id,
                'Barang Masuk Gudang',
                "GR #$gr_number telah diterima (" . strtoupper($type) . "). Segera lakukan inspeksi QC.",
                "index.php?page=qc-incoming&action=inspect&gr_id=" . (int)$gr_id,
                'info',
                ['permission_slug' => 'qc_incoming_manage']
            );

            if ($type == 'consignment') {
                notify_workflow_event(
                    'whse.gr.consignment.' . (int)$gr_id,
                    'Material Consignment',
                    "Material titipan Customer (GR: $gr_number) telah diterima di Gudang.",
                    'index.php?page=whse-receive',
                    'info',
                    ['permission_slug' => 'sales_view']
                );
            }
        }

        echo "<script>alert('Penerimaan Barang berhasil disimpan!'); window.location='index.php?page=whse-receive';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
    }
}

// Data Master
// PO yang siap diterima (Approved/Sent) dan masih ada sisa
$sql_po = "SELECT po.id, po.po_number, s.name as supplier_name,
                  COALESCE(pq.total_qty, 0) as total_qty,
                  COALESCE(pr.total_received, 0) as total_received
           FROM purchase_orders po
           JOIN suppliers s ON po.supplier_id = s.id
           LEFT JOIN (
                SELECT purchase_order_id, SUM(qty) as total_qty
                FROM purchase_order_items
                GROUP BY purchase_order_id
           ) pq ON pq.purchase_order_id = po.id
           LEFT JOIN (
                SELECT gr.purchase_order_id, SUM(gri.qty_received) as total_received
                FROM goods_receipt_items gri
                JOIN goods_receipts gr ON gri.goods_receipt_id = gr.id
                WHERE gr.status != 'rejected'
                GROUP BY gr.purchase_order_id
           ) pr ON pr.purchase_order_id = po.id
           WHERE (po.status IN ('approved_finance', 'sent', 'completed') OR po.id = ?)
             AND ((COALESCE(pq.total_qty,0) - COALESCE(pr.total_received,0)) > 0 OR po.id = ?)
           ORDER BY po.id DESC";
$stmt_po = $pdo->prepare($sql_po);
$stmt_po->execute([
    $data['purchase_order_id'] ? $data['purchase_order_id'] : 0,
    $data['purchase_order_id'] ? $data['purchase_order_id'] : 0
]);
$pos = $stmt_po->fetchAll();

// Customer
$customers = $pdo->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll();

// Items (Raw Material Only)
$raw_materials = $pdo->query("SELECT * FROM items WHERE item_type IN ('raw_material','consumable') ORDER BY item_name ASC")->fetchAll();

render_header($is_edit ? "Edit Penerimaan" : "Input Penerimaan Material");
?>

<form method="POST" id="formGR">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <!-- PILIHAN TIPE PENERIMAAN -->
    <div class="card shadow-sm mb-3 border-primary">
        <div class="card-body">
            <label class="fw-bold mb-2">Sumber Material:</label>
            <div class="d-flex gap-4">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="receipt_type" id="typeNormal" value="normal" <?= $data['receipt_type']=='normal'?'checked':'' ?> onchange="toggleType()">
                    <label class="form-check-label fw-bold" for="typeNormal">
                        <i class="bi bi-building"></i> Internal / Pembelian (NORMAL)
                        <div class="text-muted small">Wajib Referensi PO Supplier</div>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="receipt_type" id="typeConsign" value="consignment" <?= $data['receipt_type']=='consignment'?'checked':'' ?> onchange="toggleType()">
                    <label class="form-check-label fw-bold" for="typeConsign">
                        <i class="bi bi-person-workspace"></i> Dari Customer (CONSIGNMENT)
                        <div class="text-muted small">Tanpa PO Internal, Barang Titipan</div>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Panel Kiri: Info Logistik -->
        <div class="col-md-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Informasi Logistik</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>No. GR (Internal)</label>
                        <input type="text" class="form-control fw-bold bg-light" value="<?= $data['gr_number'] ?>" readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>No. Surat Jalan <span class="text-danger">*</span></label>
                            <input type="text" name="delivery_note_number" class="form-control" value="<?= $data['delivery_note_number'] ?>" required placeholder="Nomor dari Supplier">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Tanggal Terima</label>
                            <input type="date" name="gr_date" class="form-control" value="<?= $data['gr_date'] ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Nama Sopir</label>
                            <input type="text" name="driver_name" class="form-control" value="<?= $data['driver_name'] ?>" placeholder="Nama Pengantar">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Plat Nomor</label>
                            <input type="text" name="vehicle_number" class="form-control" value="<?= $data['vehicle_number'] ?>" placeholder="B 1234 XXX">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Diterima Oleh (Gudang)</label>
                        <input type="text" name="received_by" class="form-control" value="<?= $data['received_by'] ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Kanan: Referensi -->
        <div class="col-md-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-dark">Referensi Asal Barang</div>
                <div class="card-body">
                    
                    <!-- SECTION NORMAL: PILIH PO -->
                    <div id="sectionPO" class="<?= $data['receipt_type']=='consignment'?'d-none':'' ?>">
                        <div class="mb-3">
                            <label class="fw-bold">Pilih Purchase Order (PO) <span class="text-danger">*</span></label>
                            <select name="purchase_order_id" id="poSelect" class="form-select form-select-lg" onchange="loadPOItems(this.value)">
                                <option value="">-- Pilih PO --</option>
                                <?php foreach($pos as $po): 
                                    $selected = $po['id'] == $data['purchase_order_id'] ? 'selected' : '';
                                ?>
                                    <option value="<?= $po['id'] ?>" <?= $selected ?>>
                                        <?= $po['po_number'] ?> - <?= $po['supplier_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Daftar barang akan otomatis dimuat dari PO.</div>
                        </div>
                    </div>

                    <!-- SECTION CONSIGNMENT: PILIH CUSTOMER -->
                    <div id="sectionCust" class="<?= $data['receipt_type']=='normal'?'d-none':'' ?>">
                        <div class="mb-3">
                            <label class="fw-bold">Pilih Customer Pengirim <span class="text-danger">*</span></label>
                            <select name="customer_id" id="custSelect" class="form-select form-select-lg">
                                <option value="">-- Pilih Customer --</option>
                                <?php foreach($customers as $c): 
                                    $selected = $c['id'] == $data['customer_id'] ? 'selected' : '';
                                ?>
                                    <option value="<?= $c['id'] ?>" <?= $selected ?>><?= $c['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Silakan input item barang secara manual di bawah.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Catatan Penerimaan</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Kondisi barang saat diterima, dll..."><?= $data['notes'] ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Checklist Barang -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="bi bi-list-check"></i> Checklist Barang Datang</h6>
            <!-- Tombol Tambah Manual hanya muncul jika Consignment -->
            <button type="button" id="btnAddManual" class="btn btn-sm btn-success <?= $data['receipt_type']=='normal'?'d-none':'' ?>" onclick="addManualItem()">
                <i class="bi bi-plus-lg"></i> Tambah Item
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="35%">Nama Barang</th>
                            <th width="10%">Satuan</th>
                            <th width="15%" class="text-center col-po-qty">Qty PO</th>
                            <th width="15%" class="text-center">Sisa PO</th>
                            <th width="15%" class="text-center bg-warning bg-opacity-10">Qty Diterima</th>
                            <th width="10%">Hapus</th>
                        </tr>
                    </thead>
                    <tbody id="grItems">
                        <?php if(!empty($items)): foreach($items as $item): ?>
                        <tr>
                            <td>
                                <?php if($data['receipt_type']=='normal'): ?>
                                    <strong><?= $item['item_name'] ?></strong><br>
                                    <small class="text-muted"><?= $item['item_code'] ?></small>
                                    <input type="hidden" name="item_id[]" value="<?= $item['item_id'] ?>">
                                <?php else: ?>
                                    <select name="item_id[]" class="form-select">
                                        <option value="<?= $item['item_id'] ?>"><?= $item['item_code'] ?> - <?= $item['item_name'] ?></option>
                                        <?php foreach($raw_materials as $rm): ?>
                                            <option value="<?= $rm['id'] ?>" <?= $rm['id']==$item['item_id']?'selected':'' ?>><?= $rm['item_code'] ?> - <?= $rm['item_name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </td>
                            <td class="unit-cell"><?= $item['unit'] ?></td>
                            <td class="text-center col-po-qty">
                                <input type="text" name="qty_po[]" class="form-control form-control-sm text-center bg-light" value="<?= $item['qty_po'] + 0 ?>" readonly>
                            </td>
                            <td class="text-center text-muted"> - </td>
                            <td class="bg-warning bg-opacity-10">
                                <input type="number" name="qty_received[]" class="form-control form-control-sm text-center fw-bold border-warning" value="<?= $item['qty_received'] + 0 ?>" step="0.01" required>
                            </td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm delete-row <?= $data['receipt_type']=='normal'?'d-none':'' ?>" onclick="this.closest('tr').remove()">X</button>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr id="emptyRow"><td colspan="6" class="text-center py-5 text-muted">Silakan pilih Referensi di atas...</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white small text-muted">
            * <strong>Qty Diterima</strong> adalah jumlah fisik yang dihitung oleh gudang. <br>
            * Jika barang datang parsial (sebagian), input sesuai yang datang saja. Sisa PO akan tetap terbuka.
        </div>
    </div>

    <!-- Tombol Aksi -->
    <div class="d-flex justify-content-between mb-5">
        <a href="index.php?page=whse-receive" class="btn btn-secondary btn-lg px-4">Kembali</a>
        <div>
            <button type="submit" name="save_draft" class="btn btn-outline-warning btn-lg me-2">Simpan Draft</button>
            <button type="submit" name="submit_qc" class="btn btn-primary btn-lg px-4 shadow">
                <i class="bi bi-check-circle"></i> Simpan & Ajukan QC
            </button>
        </div>
    </div>
</form>

<script>
// Data Master Barang untuk Dropdown Manual
const masterItems = <?= json_encode($raw_materials) ?>;

function toggleType() {
    const type = document.querySelector('input[name="receipt_type"]:checked').value;
    const sectionPO = document.getElementById('sectionPO');
    const sectionCust = document.getElementById('sectionCust');
    const poSelect = document.getElementById('poSelect');
    const custSelect = document.getElementById('custSelect');
    const btnAdd = document.getElementById('btnAddManual');
    const colQtyPO = document.querySelectorAll('.col-po-qty');
    const tbody = document.getElementById('grItems');

    // Reset Table only if empty
    if(tbody.querySelector('#emptyRow')) {
         // Keep it empty or reset
    } else {
         // If switching types, generally safer to reset table to avoid mismatch
         tbody.innerHTML = '<tr id="emptyRow"><td colspan="6" class="text-center py-5 text-muted">Silakan pilih Referensi...</td></tr>';
    }

    if (type === 'normal') {
        sectionPO.classList.remove('d-none');
        sectionCust.classList.add('d-none');
        poSelect.setAttribute('required', 'required');
        custSelect.removeAttribute('required');
        btnAdd.classList.add('d-none');
        
        colQtyPO.forEach(el => el.classList.remove('d-none'));
        
    } else {
        sectionPO.classList.add('d-none');
        sectionCust.classList.remove('d-none');
        poSelect.removeAttribute('required');
        custSelect.setAttribute('required', 'required');
        btnAdd.classList.remove('d-none');
        
        colQtyPO.forEach(el => el.classList.add('d-none'));
    }
}

function loadPOItems(poId) {
    const tbody = document.getElementById('grItems');
    if(!poId) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">Silakan pilih PO di atas...</td></tr>';
        return;
    }
    
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-primary"></div> Memuat detail PO...</td></tr>';

    fetch('modules/warehouse/receive/get_po_items.php?po_id=' + poId)
        .then(res => res.json())
        .then(data => {
            if(data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-danger">PO ini tidak memiliki item atau sudah selesai diterima semua.</td></tr>';
                return;
            }

            let rows = '';
            data.forEach(item => {
                // Konversi angka
                let qtyOrder = parseFloat(item.qty);
                let qtyReceivedTotal = parseFloat(item.total_received || 0); // Total yg sudah diterima sebelumnya
                let qtyRemaining = qtyOrder - qtyReceivedTotal;
                
                // Jika sisa 0 atau minus, beri tanda visual
                let sisaClass = qtyRemaining <= 0 ? 'text-success fw-bold' : 'text-danger fw-bold';
                let sisaText = qtyRemaining <= 0 ? 'LUNAS' : qtyRemaining;
                
                // Default input qty = sisa
                let defaultInput = qtyRemaining > 0 ? qtyRemaining : 0;

                rows += `
                <tr>
                    <td>
                        <strong class="text-dark">${item.item_name}</strong><br>
                        <small class="text-muted">${item.item_code}</small>
                        <input type="hidden" name="item_id[]" value="${item.item_id}">
                    </td>
                    <td class="unit-cell">${item.unit}</td>
                    <td class="text-center col-po-qty">
                        <span class="badge bg-secondary fs-6">${qtyOrder}</span>
                        <input type="hidden" name="qty_po[]" value="${qtyOrder}">
                    </td>
                    <td class="text-center">
                        <span class="${sisaClass}">${sisaText}</span>
                        ${qtyReceivedTotal > 0 ? `<br><small class="text-muted">Sudah: ${qtyReceivedTotal}</small>` : ''}
                    </td>
                    <td class="bg-warning bg-opacity-10">
                        <input type="number" name="qty_received[]" class="form-control form-control-sm text-center fw-bold border-warning" value="${defaultInput}" step="0.01" min="0" required>
                    </td>
                    <td></td>
                </tr>`;
            });
            tbody.innerHTML = rows;
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-danger">Terjadi kesalahan saat memuat data.</td></tr>';
        });
}

function addManualItem() {
    const emptyRow = document.getElementById('emptyRow');
    if(emptyRow) emptyRow.remove();

    const tbody = document.getElementById('grItems');
    let opts = '<option value="">-- Pilih Barang --</option>';
    masterItems.forEach(i => {
        opts += `<option value="${i.id}" data-unit="${i.unit}">${i.item_code} - ${i.item_name}</option>`;
    });

    const row = `
    <tr>
        <td>
            <select name="item_id[]" class="form-select" required onchange="updateUnit(this)">${opts}</select>
        </td>
        <td class="unit-cell">-</td>
        <td class="text-center col-po-qty d-none">
            <input type="hidden" name="qty_po[]" value="0"> -
        </td>
        <td class="text-center text-muted">-</td>
        <td class="bg-warning bg-opacity-10">
            <input type="number" name="qty_received[]" class="form-control form-control-sm text-center fw-bold border-warning" value="1" step="0.01" required>
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button>
        </td>
    </tr>`;
    tbody.insertAdjacentHTML('beforeend', row);
}

function updateUnit(select) {
    const unit = select.options[select.selectedIndex].getAttribute('data-unit') || '-';
    select.closest('tr').querySelector('.unit-cell').innerText = unit;
}

// Init State
<?php if(!$is_edit): ?>
toggleType();
<?php endif; ?>
</script>

<?php render_footer(); ?>
