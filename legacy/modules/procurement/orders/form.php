<?php
// modules/procurement/orders/form.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('purch_po_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=purch-po';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pr_id = isset($_GET['pr_id']) ? (int)$_GET['pr_id'] : (isset($_POST['purchase_request_id']) ? (int)$_POST['purchase_request_id'] : 0);
$pr_id = $pr_id > 0 ? $pr_id : null;
$is_edit = $id > 0;
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$data = [
    'po_number' => 'AUTO',
    'po_date' => date('Y-m-d'),
    'purchase_request_id' => '',
    'supplier_id' => '',
    'delivery_date' => date('Y-m-d', strtotime('+3 days')),
    'payment_terms' => 'Net 30 Days',
    'ppn_percent' => 11,
    'discount_amount' => 0,
    'notes' => '',
    'status' => 'draft'
];
$items = [];

// --- LOGIC LOAD DATA ---
if ($is_edit) {
    // Mode Edit: Load dari database PO
    $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    if(!$data) die("PO tidak ditemukan");

    // Load Item PO yang sudah disimpan
    $stmt_items = $pdo->prepare("SELECT poi.*, i.item_name, i.item_code, i.unit 
                                 FROM purchase_order_items poi 
                                 JOIN items i ON poi.item_id = i.id 
                                 WHERE poi.purchase_order_id = ?");
    $stmt_items->execute([$id]);
    $items = $stmt_items->fetchAll();

} elseif ($pr_id) {
    // Mode Create dari PR: Load data PR & Items (PARTIAL LOGIC)
    $stmt_pr = $pdo->prepare("SELECT * FROM purchase_requests WHERE id = ?");
    $stmt_pr->execute([$pr_id]);
    $pr = $stmt_pr->fetch();
    
    if($pr) {
        $data['purchase_request_id'] = $pr['id'];
        $data['notes'] = "Ref PR: " . $pr['pr_number'] . "\n" . $pr['notes'];
        
        // HANYA AMBIL ITEM YANG BELUM LUNAS (Qty Request > Qty Ordered)
        // Hitung sisa qty (qty - qty_ordered)
        $sql_pri = "SELECT pri.id as pr_item_id, pri.item_id, (pri.qty - IFNULL(pri.qty_ordered,0)) as qty_remaining, 
                           i.item_name, i.item_code, i.unit 
                    FROM purchase_request_items pri 
                    JOIN items i ON pri.item_id = i.id 
                    WHERE pri.purchase_request_id = ? 
                    AND (pri.qty - IFNULL(pri.qty_ordered,0)) > 0"; // Filter sisa > 0
                    
        $stmt_pri = $pdo->prepare($sql_pri);
        $stmt_pri->execute([$pr_id]);
        $pr_items = $stmt_pri->fetchAll(PDO::FETCH_ASSOC);

        // Fallback: Jika detail PR kosong (misal PR lama tersimpan header-only),
        // tarik referensi item langsung dari BOM SPK pada notes PR: [REF-SPK:id].
        if (empty($pr_items) && !empty($pr['notes']) && preg_match('/\[REF-SPK:(\d+)\]/', (string)$pr['notes'], $m)) {
            $ref_spk_id = (int)$m[1];
            if ($ref_spk_id > 0) {
                $stmt_spk_mat = $pdo->prepare(
                    "SELECT sm.item_id,
                            i.item_name,
                            i.item_code,
                            i.unit,
                            sm.qty_required,
                            IFNULL(i.current_stock,0) as current_stock,
                            GREATEST(sm.qty_required - IFNULL(i.current_stock,0), 0) as qty_short
                     FROM spk_materials sm
                     LEFT JOIN items i ON i.id = sm.item_id
                     WHERE sm.spk_id = ?
                       AND (i.ownership = 'internal' OR i.ownership IS NULL OR i.ownership = '')"
                );
                $stmt_spk_mat->execute([$ref_spk_id]);
                $spk_rows = $stmt_spk_mat->fetchAll(PDO::FETCH_ASSOC);

                foreach ($spk_rows as $sr) {
                    $qty_seed = ((float)$sr['qty_short'] > 0) ? (float)$sr['qty_short'] : (float)$sr['qty_required'];
                    if ($qty_seed <= 0) continue;
                    $pr_items[] = [
                        'pr_item_id' => null,
                        'item_id' => $sr['item_id'],
                        'qty_remaining' => $qty_seed,
                        'item_name' => $sr['item_name'] ?: ('Item #' . $sr['item_id']),
                        'item_code' => $sr['item_code'] ?: ('ITEM-' . $sr['item_id']),
                        'unit' => $sr['unit'] ?: '-'
                    ];
                }

                if (!empty($pr_items)) {
                    $data['notes'] .= "\n[Fallback BOM SPK #" . $ref_spk_id . "]";
                }
            }
        }

        // Mapping ke format items PO
        foreach($pr_items as $pi) {
            $items[] = [
                'item_id' => $pi['item_id'],
                'item_code' => $pi['item_code'],
                'item_name' => $pi['item_name'],
                'unit' => $pi['unit'],
                'qty' => $pi['qty_remaining'], // Tampilkan sisa
                'unit_price' => 0,
                'subtotal' => 0,
                'notes' => '',
                'pr_item_id' => $pi['pr_item_id'] // Simpan ID referensi detail PR
            ];
        }
    }
}

// --- PROSES SIMPAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    } else {
        $supp_id = (int)($_POST['supplier_id'] ?? 0);
        $pr_ref = (int)($_POST['purchase_request_id'] ?? 0);
        $date = $_POST['po_date'];
        $del_date = $_POST['delivery_date'];
        $pay_term = $_POST['payment_terms'];
        $ppn_pct = (float)($_POST['ppn_percent'] ?? 0);
        $disc_amt = (float)str_replace('.', '', (string)($_POST['discount_amount'] ?? '0'));
        $notes = $_POST['notes'];
        $status = $_POST['status']; // Draft

        $item_ids = $_POST['item_id'] ?? [];
        $item_qtys = $_POST['qty'] ?? [];
        $item_prices = $_POST['price'] ?? [];
        $item_notes = $_POST['item_notes'] ?? [];
        // Hidden input untuk mapping ke PR Item ID (agar tau mana yang diupdate)
        $pr_item_ref_ids = $_POST['pr_item_ref_id'] ?? []; 

        $grand_total = 0;
        $total_bruto = 0;

        try {
            $pdo->beginTransaction();

        // 1. INSERT / UPDATE HEADER PO
        if (!$is_edit) {
            $ym = date('ym');
            $stmt_no = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE po_number LIKE 'PO-$ym-%'");
            $count = $stmt_no->fetchColumn() + 1;
            $po_number = "PO-" . $ym . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO purchase_orders (po_number, purchase_request_id, supplier_id, po_date, delivery_date, payment_terms, ppn_percent, discount_amount, status, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$po_number, $pr_ref, $supp_id, $date, $del_date, $pay_term, $ppn_pct, $disc_amt, $status, $notes, $_SESSION['user_id']]);
            $po_id = $pdo->lastInsertId();

        } else {
            // Edit Mode (Hati-hati: Edit PO yang sudah parsial agak rumit, sebaiknya batasi edit qty jika sudah link PR)
            // Untuk simplifikasi, di mode edit kita update header saja, item di-recreate
            // NAMUN: Kita harus mengembalikan qty_ordered di PR dulu sebelum recreate (Rollback Qty)
            
            // 1. Rollback Qty PR dulu
            $stmt_old_items = $pdo->prepare("SELECT pr_item_id, qty FROM purchase_order_items WHERE purchase_order_id = ?");
            $stmt_old_items->execute([$id]);
            $old_items = $stmt_old_items->fetchAll();
            foreach($old_items as $oi) {
                if ($oi['pr_item_id']) {
                    $pdo->prepare("UPDATE purchase_request_items SET qty_ordered = IFNULL(qty_ordered,0) - ? WHERE id = ?")->execute([$oi['qty'], $oi['pr_item_id']]);
                }
            }
            
            $sql = "UPDATE purchase_orders SET supplier_id=?, po_date=?, delivery_date=?, payment_terms=?, ppn_percent=?, discount_amount=?, status=?, notes=? WHERE id=?";
            $pdo->prepare($sql)->execute([$supp_id, $date, $del_date, $pay_term, $ppn_pct, $disc_amt, $status, $notes, $id]);
            $po_id = $id;
            
            // Hapus item lama
            $pdo->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id=?")->execute([$id]);
        }

        // 2. INSERT DETAIL PO & UPDATE PR PROGRESS
        $stmt_det = $pdo->prepare("INSERT INTO purchase_order_items (purchase_order_id, item_id, qty, unit_price, subtotal, notes, pr_item_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        // Prepare update PR item qty
        $stmt_update_pr_item = $pdo->prepare("UPDATE purchase_request_items SET qty_ordered = IFNULL(qty_ordered,0) + ? WHERE id = ?");

        for ($i = 0; $i < count($item_ids); $i++) {
            $qty = floatval($item_qtys[$i]);
            $price = floatval($item_prices[$i]);
            $sub = $qty * $price;
            $total_bruto += $sub;
            
            $pr_item_id = !empty($pr_item_ref_ids[$i]) ? (int)$pr_item_ref_ids[$i] : null;
            
            // Insert Item PO
            $stmt_det->execute([$po_id, $item_ids[$i], $qty, $price, $sub, $item_notes[$i] ?? '', $pr_item_id]);

            // Update Progress PR (Jika berasal dari PR)
            if ($pr_item_id) {
                $stmt_update_pr_item->execute([$qty, $pr_item_id]);
            }
        }

        // 3. UPDATE STATUS PR (Partial / Processed)
        if ($pr_ref) {
            // Cek apakah masih ada sisa di PR ini
            $chk_sisa = $pdo->prepare("SELECT COUNT(*) FROM purchase_request_items WHERE purchase_request_id = ? AND (qty - IFNULL(qty_ordered,0)) > 0.001");
            $chk_sisa->execute([$pr_ref]);
            $sisa_count = $chk_sisa->fetchColumn();

            if ($sisa_count > 0) {
                $new_pr_status = 'partial'; // Masih ada sisa
            } else {
                $new_pr_status = 'processed'; // Sudah habis semua
            }
            
            $pdo->prepare("UPDATE purchase_requests SET status = ? WHERE id = ?")->execute([$new_pr_status, $pr_ref]);
        }

        // 4. Update Grand Total PO
        $dpp = $total_bruto - $disc_amt;
        $ppn_val = $dpp * ($ppn_pct / 100);
        $grand_total = $dpp + $ppn_val;

        $pdo->prepare("UPDATE purchase_orders SET grand_total=? WHERE id=?")->execute([$grand_total, $po_id]);

        $pdo->commit();
        echo "<script>alert('PO berhasil disimpan!'); window.location='index.php?page=purch-po';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Terjadi kesalahan saat menyimpan PO.";
        }
    }
}

// Data Master
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();
$raw_materials = $pdo->query("SELECT * FROM items WHERE item_type IN ('raw_material','consumable') ORDER BY item_name ASC")->fetchAll();

// List PR: Tampilkan yang Approved ATAU Partial
$prs = $pdo->query("SELECT id, pr_number, notes FROM purchase_requests WHERE status IN ('approved', 'partial') ORDER BY id DESC")->fetchAll();

render_header($is_edit ? "Edit PO" : "Buat PO");
?>

<form method="POST">
    <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $esc($error) ?></div><?php endif; ?>

    <div class="row">
        <!-- HEADER -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">Header PO</div>
                <div class="card-body">
                    <div class="mb-2">
                        <label>No. PO</label>
                        <input type="text" class="form-control fw-bold" value="<?= $esc($data['po_number']) ?>" readonly>
                    </div>
                    <div class="mb-2">
                        <label>Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">-- Pilih Supplier --</option>
                            <?php foreach($suppliers as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= (int)$s['id']==(int)$data['supplier_id']?'selected':'' ?>><?= $esc($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label>Referensi PR</label>
                        <select name="purchase_request_id" class="form-select" onchange="if(this.value) window.location.href='index.php?page=purch-po&action=create&pr_id='+encodeURIComponent(this.value)">
                            <option value="">-- Tanpa PR (Direct) --</option>
                            <?php foreach($prs as $pr): 
                                $selected = $pr['id'] == $data['purchase_request_id'] ? 'selected' : '';
                            ?>
                                <option value="<?= (int)$pr['id'] ?>" <?= $selected ?>><?= $esc($pr['pr_number']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Pilih PR untuk tarik item sisa.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- DETAILS -->
        <div class="col-md-8">
             <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">Info Pengiriman & Bayar</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-4 mb-2">
                            <label>Tgl PO</label>
                            <input type="date" name="po_date" class="form-control" value="<?= $esc($data['po_date']) ?>" required>
                        </div>
                        <div class="col-4 mb-2">
                            <label>Tgl Kirim (Est)</label>
                            <input type="date" name="delivery_date" class="form-control" value="<?= $esc($data['delivery_date']) ?>">
                        </div>
                        <div class="col-4 mb-2">
                            <label>Terms</label>
                            <input type="text" name="payment_terms" class="form-control" value="<?= $esc($data['payment_terms']) ?>">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"><?= $esc($data['notes']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ITEMS -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between">
            <strong>Detail Item</strong>
            <button type="button" class="btn btn-sm btn-success" onclick="addItemRow()">+ Item</button>
        </div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead class="bg-light">
                    <tr>
                        <th width="35%">Barang</th>
                        <th width="10%">Qty</th>
                        <th width="20%">Harga Satuan</th>
                        <th width="20%">Subtotal</th>
                        <th width="5%"></th>
                    </tr>
                </thead>
                <tbody id="poItems">
                    <?php 
                    if(!empty($items)): 
                        foreach($items as $item): 
                            $price_val = isset($item['unit_price']) ? $item['unit_price'] : 0;
                            $subtotal_val = isset($item['subtotal']) ? $item['subtotal'] : ($item['qty'] * $price_val);
                            // Simpan ID relasi PR Item jika ada
                            $pr_item_id_val = isset($item['pr_item_id']) ? $item['pr_item_id'] : ''; 
                    ?>
                    <tr>
                        <td>
                            <select name="item_id[]" class="form-select mb-1" required>
                                <option value="<?= (int)$item['item_id'] ?>"><?= $esc($item['item_code']) ?> - <?= $esc($item['item_name']) ?></option>
                                <?php foreach($raw_materials as $rm): ?>
                                    <option value="<?= (int)$rm['id'] ?>"><?= $esc($rm['item_code']) ?> - <?= $esc($rm['item_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="item_notes[]" class="form-control form-control-sm" placeholder="Catatan" value="<?= $esc($item['notes'] ?? '') ?>">
                            <input type="hidden" name="pr_item_ref_id[]" value="<?= (int)$pr_item_id_val ?>">
                        </td>
                        <td><input type="number" name="qty[]" class="form-control qty" value="<?= $item['qty'] + 0 ?>" step="0.01" oninput="calcTotal()" required></td>
                        <td><input type="number" name="price[]" class="form-control price text-end" value="<?= $price_val + 0 ?>" step="0.01" oninput="calcTotal()" required></td>
                        <td><input type="text" class="form-control subtotal text-end bg-light" value="<?= number_format($subtotal_val,0,',','.') ?>" readonly></td>
                        <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            <div class="row justify-content-end">
                <div class="col-md-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Bruto:</span>
                        <strong id="totalBruto">0</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2 align-items-center">
                        <span>Diskon (Rp):</span>
                        <input type="text" name="discount_amount" id="discAmount" class="form-control form-control-sm text-end w-50" value="<?= number_format($data['discount_amount'],0,',','.') ?>" onkeyup="formatRibuan(this); calcTotal()">
                    </div>
                    <div class="d-flex justify-content-between mb-2 align-items-center">
                        <span>PPN (%):</span>
                        <input type="number" name="ppn_percent" id="ppnPercent" class="form-control form-control-sm text-end w-50" value="<?= $data['ppn_percent'] + 0 ?>" oninput="calcTotal()">
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="fs-5 fw-bold">Grand Total:</span>
                        <span class="fs-5 fw-bold text-primary" id="grandTotal">0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Status Default Draft -->
    <input type="hidden" name="status" value="<?= $esc($data['status']) ?>">

    <div class="d-flex justify-content-between mb-5">
        <a href="index.php?page=purch-po" class="btn btn-secondary">Kembali</a>
        <button type="submit" class="btn btn-primary btn-lg">Simpan PO</button>
    </div>
</form>

<script>
const rawMaterials = <?= json_encode($raw_materials, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const escHtml = (v) => String(v ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

function addItemRow() {
    let opts = '<option value="">-- Pilih --</option>';
    rawMaterials.forEach(rm => {
        const id = Number.parseInt(rm.id, 10) || 0;
        opts += '<option value="' + id + '">' + escHtml(rm.item_code) + ' - ' + escHtml(rm.item_name) + '</option>';
    });
    
    const row = '<tr>' +
        '<td>' +
            '<select name="item_id[]" class="form-select mb-1" required>' + opts + '</select>' +
            '<input type="text" name="item_notes[]" class="form-control form-control-sm" placeholder="Catatan">' +
            '<input type="hidden" name="pr_item_ref_id[]" value="">' + // Kosong jika manual
        '</td>' +
        '<td><input type="number" name="qty[]" class="form-control qty" value="1" step="0.01" oninput="calcTotal()" required></td>' +
        '<td><input type="number" name="price[]" class="form-control price text-end" value="0" step="0.01" oninput="calcTotal()" required></td>' +
        '<td><input type="text" class="form-control subtotal text-end bg-light" value="0" readonly></td>' +
        '<td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>' +
    '</tr>';
    
    document.getElementById('poItems').insertAdjacentHTML('beforeend', row);
}

function removeRow(btn) { btn.closest('tr').remove(); calcTotal(); }

function formatRibuan(input) {
    let val = input.value.replace(/[^0-9]/g, '');
    input.value = new Intl.NumberFormat('id-ID').format(val);
}

function calcTotal() {
    let bruto = 0;
    document.querySelectorAll('#poItems tr').forEach(row => {
        let qty = parseFloat(row.querySelector('.qty').value) || 0;
        let price = parseFloat(row.querySelector('.price').value) || 0;
        let sub = qty * price;
        row.querySelector('.subtotal').value = new Intl.NumberFormat('id-ID').format(sub);
        bruto += sub;
    });

    let disc = parseFloat(document.getElementById('discAmount').value.replace(/\./g, '')) || 0;
    let ppn = parseFloat(document.getElementById('ppnPercent').value) || 0;
    
    let dpp = bruto - disc;
    let tax = dpp * (ppn / 100);
    let grand = dpp + tax;

    document.getElementById('totalBruto').innerText = "Rp " + new Intl.NumberFormat('id-ID').format(bruto);
    document.getElementById('grandTotal').innerText = "Rp " + new Intl.NumberFormat('id-ID').format(grand);
}

<?php if(!$is_edit && empty($items)): ?>
window.onload = function() { addItemRow(); calcTotal(); };
<?php else: ?>
window.onload = calcTotal;
<?php endif; ?>
</script>

<?php render_footer(); ?>
