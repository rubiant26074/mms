<?php
// modules/finance/ap/form.php

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$po_id = isset($_GET['po_id']) && is_numeric($_GET['po_id']) ? (int)$_GET['po_id'] : null;
$is_edit = ($id !== null);

$data = [
    'bill_number' => 'AUTO',
    'supplier_inv_number' => '',
    'bill_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'purchase_order_id' => '',
    'supplier_id' => '',
    'subtotal' => 0,
    'tax_amount' => 0,
    'discount_amount' => 0,
    'grand_total' => 0,
    'status' => 'draft',
    'notes' => ''
];
$items = [];

// --- LOGIC LOAD DATA ---
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM supplier_bills WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    if(!$data) die("Bill tidak ditemukan");
    if (($data['status'] ?? '') !== 'draft') die("Tagihan non-draft tidak dapat diedit.");
    
    // Load Items dari DB
    $stmt_items = $pdo->prepare("SELECT sbi.*, i.item_name, i.item_code, i.unit 
                                 FROM supplier_bill_items sbi
                                 JOIN items i ON sbi.item_id = i.id
                                 WHERE sbi.bill_id = ?");
    $stmt_items->execute([$id]);
    $items = $stmt_items->fetchAll();
} 
elseif ($po_id) {
    // Load Data dari PO
    $stmt_po = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
    $stmt_po->execute([$po_id]);
    $po = $stmt_po->fetch();
    
    if($po) {
        $data['purchase_order_id'] = $po['id'];
        $data['supplier_id'] = $po['supplier_id'];
        $data['notes'] = "Ref PO: " . $po['po_number'];
        
        // Copy nilai dari PO ke Bill
        $data['subtotal'] = $po['grand_total'] - $po['ppn_percent'] * ($po['grand_total'] / (1 + $po['ppn_percent']/100)); // Estimasi balik
        $data['discount_amount'] = $po['discount_amount'];
        $data['ppn_percent'] = $po['ppn_percent']; 
        
        // Ambil Item PO
        $stmt_poi = $pdo->prepare("SELECT poi.item_id, poi.qty, poi.unit_price, (poi.qty * poi.unit_price) as subtotal, i.item_name, i.item_code, i.unit 
                                   FROM purchase_order_items poi 
                                   JOIN items i ON poi.item_id = i.id 
                                   WHERE poi.purchase_order_id = ?");
        $stmt_poi->execute([$po_id]);
        $items = $stmt_poi->fetchAll(PDO::FETCH_ASSOC);
        
        // Recalculate total agar presisi
        $sub = 0;
        foreach($items as $it) $sub += $it['subtotal'];
        $data['subtotal'] = $sub;
        $dpp = $sub - $data['discount_amount'];
        $data['tax_amount'] = $dpp * ($data['ppn_percent'] / 100);
        $data['grand_total'] = $dpp + $data['tax_amount'];
    }
}

// --- PROSES SIMPAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    }
    $po_ref = isset($_POST['purchase_order_id']) && $_POST['purchase_order_id'] !== '' ? (int)$_POST['purchase_order_id'] : null;
    $supp_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
    $inv_num = clean($_POST['supplier_inv_number'] ?? '');
    $date = $_POST['bill_date'] ?? '';
    $due = $_POST['due_date'] ?? '';
    $notes = clean($_POST['notes'] ?? '');
    $ppn_percent = isset($_POST['ppn_percent']) ? (float)$_POST['ppn_percent'] : 11;
    if ($ppn_percent < 0) $ppn_percent = 0;
    if ($ppn_percent > 100) $ppn_percent = 100;
    
    // Item Arrays
    $item_ids = $_POST['item_id'] ?? [];
    $item_qtys = $_POST['qty'] ?? [];
    $item_prices = $_POST['price'] ?? [];

    // Kalkulasi (recompute server-side)
    $subtotal = 0;
    $disc = floatval(str_replace('.', '', $_POST['discount_amount']));
    for ($i = 0; $i < count($item_ids); $i++) {
        $qty = isset($item_qtys[$i]) ? (float)$item_qtys[$i] : 0;
        $price = isset($item_prices[$i]) ? (float)$item_prices[$i] : 0;
        if ($qty <= 0 || $price < 0) {
            continue;
        }
        $subtotal += ($qty * $price);
    }
    $dpp = max(0, $subtotal - $disc);
    $tax = $dpp * ($ppn_percent / 100);
    $grand = $dpp + $tax;

    try {
        if (isset($error)) {
            throw new Exception($error);
        }
        if (empty($item_ids)) {
            throw new Exception("Item tagihan belum diisi.");
        }
        $pdo->beginTransaction();

        if (!$is_edit) {
            $ym = date('ym');
            $stmt_no = $pdo->query("SELECT COUNT(*) FROM supplier_bills WHERE bill_number LIKE 'BILL-$ym-%'");
            $count = $stmt_no->fetchColumn() + 1;
            $bill_number = "BILL-" . $ym . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO supplier_bills (bill_number, supplier_inv_number, purchase_order_id, supplier_id, bill_date, due_date, subtotal, discount_amount, tax_amount, grand_total, status, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?)";
            $pdo->prepare($sql)->execute([$bill_number, $inv_num, $po_ref, $supp_id, $date, $due, $subtotal, $disc, $tax, $grand, $notes, $_SESSION['user_id']]);
            $bill_id = $pdo->lastInsertId();
            
            // Update PO Status (Optional)
            if ($po_ref) {
                 $pdo->prepare("UPDATE purchase_orders SET status='completed' WHERE id=?")->execute([$po_ref]);
            }

        } else {
            $sql = "UPDATE supplier_bills SET supplier_inv_number=?, bill_date=?, due_date=?, subtotal=?, discount_amount=?, tax_amount=?, grand_total=?, notes=? WHERE id=?";
            $pdo->prepare($sql)->execute([$inv_num, $date, $due, $subtotal, $disc, $tax, $grand, $notes, $id]);
            $bill_id = $id;
            $pdo->prepare("DELETE FROM supplier_bill_items WHERE bill_id=?")->execute([$id]);
        }

        $stmt_det = $pdo->prepare("INSERT INTO supplier_bill_items (bill_id, item_id, qty, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
        
        for ($i = 0; $i < count($item_ids); $i++) {
            $qty = floatval($item_qtys[$i]);
            $price = floatval($item_prices[$i]);
            $sub = $qty * $price;
            $stmt_det->execute([$bill_id, $item_ids[$i], $qty, $price, $sub]);
        }

        $pdo->commit();
        echo "<script>alert('Tagihan berhasil disimpan!'); window.location='index.php?page=fin-ap';</script>";
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error: " . $e->getMessage();
    }
}

// Data Master
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();
// List PO siap tagih
$pos = $pdo->query("SELECT po.id, po.po_number, s.name as supplier_name 
                    FROM purchase_orders po 
                    JOIN suppliers s ON po.supplier_id = s.id 
                    WHERE po.status IN ('approved', 'sent') 
                    ORDER BY po.id DESC")->fetchAll();

render_header($is_edit ? "Edit Tagihan" : "Input Tagihan Supplier");
?>

<form method="POST">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(mms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">Info Tagihan</div>
                <div class="card-body">
                    <div class="mb-2">
                        <label>No. Bill (Internal)</label>
                        <input type="text" class="form-control fw-bold" value="<?= $data['bill_number'] ?>" readonly>
                    </div>
                    <div class="mb-2">
                        <label>No. Invoice Supplier <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_inv_number" class="form-control" value="<?= $data['supplier_inv_number'] ?>" required placeholder="Nomor dari Vendor">
                    </div>
                    <div class="mb-2">
                        <label>Ref. PO</label>
                        <select name="purchase_order_id" class="form-select" <?= $is_edit ? '' : "onchange=\"window.location.href='index.php?page=fin-ap&action=create&po_id='+this.value\"" ?>>
                            <option value="">-- Pilih PO --</option>
                            <?php foreach($pos as $po): 
                                $selected = $po['id'] == $data['purchase_order_id'] ? 'selected' : '';
                            ?>
                                <option value="<?= $po['id'] ?>" <?= $selected ?>><?= $po['po_number'] ?> - <?= $po['supplier_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label>Supplier</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach($suppliers as $s): 
                                $selected = $s['id'] == $data['supplier_id'] ? 'selected' : '';
                            ?>
                                <option value="<?= $s['id'] ?>" <?= $selected ?>><?= $s['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">Detail & Tanggal</div>
                <div class="card-body">
                     <div class="row">
                        <div class="col-6 mb-3">
                            <label>Tanggal Tagihan</label>
                            <input type="date" name="bill_date" class="form-control" value="<?= $data['bill_date'] ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label>Jatuh Tempo</label>
                            <input type="date" name="due_date" class="form-control" value="<?= $data['due_date'] ?>" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"><?= $data['notes'] ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ITEMS -->
    <div class="card shadow-sm mb-4">
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Barang</th>
                        <th width="10%">Qty</th>
                        <th width="20%">Harga</th>
                        <th width="20%">Subtotal</th>
                    </tr>
                </thead>
                <tbody id="apItems">
                    <?php if(!empty($items)): foreach($items as $item): ?>
                    <tr>
                        <td>
                            <?= $item['item_name'] ?> (<?= $item['item_code'] ?>)
                            <input type="hidden" name="item_id[]" value="<?= $item['item_id'] ?>">
                        </td>
                        <td>
                            <input type="number" name="qty[]" class="form-control text-center qty" value="<?= $item['qty'] + 0 ?>" step="0.01" oninput="calcTotal()" required>
                        </td>
                        <td>
                            <input type="number" name="price[]" class="form-control text-end price" value="<?= $item['unit_price'] + 0 ?>" step="0.01" oninput="calcTotal()" required>
                        </td>
                        <td>
                            <input type="text" class="form-control text-end subtotal bg-light" value="<?= number_format($item['subtotal'],0,',','.') ?>" readonly>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="4" class="text-center py-4 text-muted">Pilih PO untuk memuat item...</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                     <!-- Hidden Inputs untuk POST -->
                    <input type="hidden" name="subtotal_hidden" id="inpSubtotal" value="<?= $data['subtotal'] ?>">
                    <input type="hidden" name="tax_amount_hidden" id="inpTax" value="<?= $data['tax_amount'] ?>">
                    <input type="hidden" name="grand_total_hidden" id="inpGrand" value="<?= $data['grand_total'] ?>">

                    <tr>
                        <td colspan="3" class="text-end">Subtotal :</td>
                        <td class="text-end fw-bold" id="txtSub">Rp <?= number_format($data['subtotal'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end align-middle">Diskon (Rp) :</td>
                        <td>
                            <input type="text" name="discount_amount" id="disc" class="form-control text-end" value="<?= number_format($data['discount_amount'], 0, ',', '.') ?>" onkeyup="calcTotal()">
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end align-middle">
                            PPN (%) : <input type="number" id="ppnPct" value="<?= isset($data['ppn_percent'])?$data['ppn_percent']:11 ?>" style="width:50px" oninput="calcTotal()">
                            <input type="hidden" name="ppn_percent" id="ppnInput" value="11">
                        </td>
                        <td class="text-end" id="txtTax">Rp <?= number_format($data['tax_amount'], 0, ',', '.') ?></td>
                    </tr>
                    <tr class="bg-light">
                        <td colspan="3" class="text-end fw-bold">GRAND TOTAL :</td>
                        <td class="text-end fw-bold fs-5" id="txtGrand">Rp <?= number_format($data['grand_total'], 0, ',', '.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-5">
        <a href="index.php?page=fin-ap" class="btn btn-secondary">Kembali</a>
        <button type="submit" class="btn btn-primary btn-lg">Simpan Tagihan</button>
    </div>
</form>

<script>
function formatRupiah(num) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
}

function calcTotal() {
    let bruto = 0;
    document.querySelectorAll('#apItems tr').forEach(row => {
        let qty = parseFloat(row.querySelector('.qty').value) || 0;
        let price = parseFloat(row.querySelector('.price').value) || 0;
        let sub = qty * price;
        row.querySelector('.subtotal').value = new Intl.NumberFormat('id-ID').format(sub);
        bruto += sub;
    });

    let disc = parseFloat(document.getElementById('disc').value.replace(/\./g, '')) || 0;
    let ppn_pct = parseFloat(document.getElementById('ppnPct').value) || 0;
    document.getElementById('ppnInput').value = ppn_pct;
    
    let dpp = bruto - disc;
    let tax = dpp * (ppn_pct / 100);
    let grand = dpp + tax;

    document.getElementById('inpSubtotal').value = bruto;
    document.getElementById('inpTax').value = tax;
    document.getElementById('inpGrand').value = grand;
    
    document.getElementById('txtSub').innerText = formatRupiah(bruto);
    document.getElementById('txtTax').innerText = formatRupiah(tax);
    document.getElementById('txtGrand').innerText = formatRupiah(grand);
}
</script>

<?php render_footer(); ?>
