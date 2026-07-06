<?php
// modules/finance/ar/form.php

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$sj_id = isset($_GET['sj_id']) && is_numeric($_GET['sj_id']) ? (int)$_GET['sj_id'] : null;
$is_edit = ($id !== null);

$has_cust_nsfp_col = false;
try {
    $has_cust_nsfp_col = $pdo->query("SHOW COLUMNS FROM customers LIKE 'tax_invoice_number'")->rowCount() > 0;
} catch (Exception $e) {
    $has_cust_nsfp_col = false;
}

$data = [
    'invoice_number' => 'AUTO',
    'tax_invoice_number' => '', // BARU: Field NSFP
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'delivery_note_id' => '',
    'customer_id' => '',
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
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$id]);
    $fetch = $stmt->fetch();
    if(!$fetch) die("Invoice tidak ditemukan");
    $data = $fetch;
    
    // Pastikan key ada untuk data lama
    if (!isset($data['tax_invoice_number'])) $data['tax_invoice_number'] = '';

    if ($has_cust_nsfp_col && empty($data['tax_invoice_number']) && !empty($data['customer_id'])) {
        $stmt_cust_nsfp = $pdo->prepare("SELECT tax_invoice_number FROM customers WHERE id = ? LIMIT 1");
        $stmt_cust_nsfp->execute([(int)$data['customer_id']]);
        $cust_nsfp = (string)$stmt_cust_nsfp->fetchColumn();
        if ($cust_nsfp !== '') $data['tax_invoice_number'] = $cust_nsfp;
    }

    $sj_id = $data['delivery_note_id'];
}

if ($sj_id) {
    $data['delivery_note_id'] = $sj_id;
    
    // Ambil Data Customer & Harga dari SO via SJ
    $sql_items = "SELECT dni.item_id, dni.qty_sent, 
                         i.item_name, i.item_code, i.unit,
                         soi.unit_price,
                         so.customer_id, so.ppn_percent, so.discount_amount as so_disc, so.payment_terms
                  FROM delivery_note_items dni
                  JOIN delivery_notes dn ON dni.delivery_note_id = dn.id
                  JOIN sales_orders so ON dn.sales_order_id = so.id
                  JOIN sales_order_items soi ON (so.id = soi.sales_order_id AND dni.item_id = soi.item_id)
                  JOIN items i ON dni.item_id = i.id
                  WHERE dni.delivery_note_id = ?";
                  
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([$sj_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    // Auto fill header jika baru create
    if (!$is_edit && !empty($items)) {
        $data['customer_id'] = $items[0]['customer_id'];
        
        $term = $items[0]['payment_terms'];
        $days = 30; 
        if (preg_match('/(\d+)/', $term, $matches)) {
            $days = intval($matches[0]);
        }
        $data['due_date'] = date('Y-m-d', strtotime("+$days days"));
        
        // Kalkulasi Awal
        $subtotal = 0;
        foreach($items as $it) $subtotal += ($it['qty_sent'] * $it['unit_price']);
        
        $data['subtotal'] = $subtotal;
        $data['discount_amount'] = $items[0]['so_disc']; 
        $dpp = $subtotal - $data['discount_amount'];
        $data['tax_amount'] = $dpp * ($items[0]['ppn_percent'] / 100);
        $data['grand_total'] = $dpp + $data['tax_amount'];

        if ($has_cust_nsfp_col && empty($data['tax_invoice_number'])) {
            $stmt_cust_nsfp = $pdo->prepare("SELECT tax_invoice_number FROM customers WHERE id = ? LIMIT 1");
            $stmt_cust_nsfp->execute([(int)$data['customer_id']]);
            $cust_nsfp = (string)$stmt_cust_nsfp->fetchColumn();
            if ($cust_nsfp !== '') $data['tax_invoice_number'] = $cust_nsfp;
        }
    }
}

// --- PROSES SIMPAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    }
    $sj_ref = isset($_POST['delivery_note_id']) ? (int)$_POST['delivery_note_id'] : 0;
    $cust_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $nsfp_raw = trim($_POST['tax_invoice_number'] ?? '');
    $nsfp = preg_replace('/\s+/', '', $nsfp_raw);
    $date = $_POST['invoice_date'] ?? '';
    $due = $_POST['due_date'] ?? '';
    $notes = clean($_POST['notes'] ?? '');

    // Ambil nilai kalkulasi final (recompute server-side)
    $disc = floatval(str_replace('.', '', $_POST['discount_amount'] ?? '0'));
    $subtotal = 0;
    $ppn_percent = 11;
    if (!isset($error)) {
        $sql_calc = "SELECT dni.qty_sent, soi.unit_price, so.ppn_percent
                     FROM delivery_note_items dni
                     JOIN delivery_notes dn ON dni.delivery_note_id = dn.id
                     JOIN sales_orders so ON dn.sales_order_id = so.id
                     JOIN sales_order_items soi ON (so.id = soi.sales_order_id AND dni.item_id = soi.item_id)
                     WHERE dni.delivery_note_id = ?";
        $stmt_calc = $pdo->prepare($sql_calc);
        $stmt_calc->execute([$sj_ref]);
        $calc_rows = $stmt_calc->fetchAll(PDO::FETCH_ASSOC);
        if (empty($calc_rows)) {
            $error = "Item invoice tidak ditemukan untuk Surat Jalan tersebut.";
        } else {
            $ppn_percent = (float)$calc_rows[0]['ppn_percent'];
            foreach ($calc_rows as $r) {
                $subtotal += ((float)$r['qty_sent'] * (float)$r['unit_price']);
            }
        }
    }
    $dpp = max(0, $subtotal - $disc);
    $tax = $dpp * ($ppn_percent / 100);
    $grand = $dpp + $tax;

    // Validasi format NSFP (opsional, tapi jika diisi harus valid)
    if ($nsfp !== '' && !preg_match('/^\d{3}\.\d{3}-\d{2}\.\d{8}$/', $nsfp)) {
        $error = "Format No. Seri Faktur Pajak tidak valid. Gunakan format 000.000-YY.12345678";
    }

    // Cek duplikasi NSFP jika diisi
    if (!isset($error) && $nsfp !== '') {
        if ($is_edit) {
            $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE tax_invoice_number = ? AND id != ?");
            $stmt_chk->execute([$nsfp, $id]);
        } else {
            $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE tax_invoice_number = ?");
            $stmt_chk->execute([$nsfp]);
        }
        if ((int)$stmt_chk->fetchColumn() > 0) {
            $error = "No. Seri Faktur Pajak sudah digunakan pada invoice lain.";
        }
    }

    if (isset($error)) {
        // refill form values jika validasi gagal
        $data['tax_invoice_number'] = $nsfp_raw;
        $data['invoice_date'] = $date ?: $data['invoice_date'];
        $data['due_date'] = $due ?: $data['due_date'];
        $data['notes'] = $notes;
        $data['discount_amount'] = $disc;
        $data['subtotal'] = $subtotal;
        $data['tax_amount'] = $tax;
        $data['grand_total'] = $grand;
    } else {
        try {
            $pdo->beginTransaction();

            $nsfp_db = ($nsfp !== '') ? $nsfp : null;

            if (!$is_edit) {
                $ym = date('ym');
                $stmt_no = $pdo->query("SELECT COUNT(*) FROM invoices WHERE invoice_number LIKE 'INV-$ym-%'");
                $count = $stmt_no->fetchColumn() + 1;
                $inv_number = "INV-" . $ym . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);

                // Insert dengan NSFP
                $sql = "INSERT INTO invoices (invoice_number, tax_invoice_number, delivery_note_id, customer_id, invoice_date, due_date, subtotal, discount_amount, tax_amount, grand_total, status, notes, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?)";
                $pdo->prepare($sql)->execute([$inv_number, $nsfp_db, $sj_ref, $cust_id, $date, $due, $subtotal, $disc, $tax, $grand, $notes, $_SESSION['user_id']]);
                
            } else {
                // Update dengan NSFP
                $sql = "UPDATE invoices SET tax_invoice_number=?, invoice_date=?, due_date=?, subtotal=?, discount_amount=?, tax_amount=?, grand_total=?, notes=? WHERE id=?";
                $pdo->prepare($sql)->execute([$nsfp_db, $date, $due, $subtotal, $disc, $tax, $grand, $notes, $id]);
            }

            $pdo->commit();
            echo "<script>alert('Invoice berhasil disimpan!'); window.location='index.php?page=fin-ar';</script>";
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error: " . $e->getMessage();
        }
    }
}

// List SJ yang Siap Tagih
$sql_sj = "SELECT dn.id, dn.dn_number, c.name as cust_name 
           FROM delivery_notes dn
           JOIN sales_orders so ON dn.sales_order_id = so.id
           JOIN customers c ON so.customer_id = c.id
           WHERE dn.status IN ('approved', 'sent')
           AND dn.id NOT IN (SELECT delivery_note_id FROM invoices WHERE status != 'cancelled')
           ORDER BY dn.id DESC";

if ($is_edit) {
    $sql_sj = "SELECT dn.id, dn.dn_number, c.name as cust_name FROM delivery_notes dn JOIN sales_orders so ON dn.sales_order_id = so.id JOIN customers c ON so.customer_id = c.id WHERE dn.id = " . $data['delivery_note_id'];
}
$sjs = $pdo->query($sql_sj)->fetchAll();

render_header($is_edit ? "Edit Invoice" : "Buat Invoice");
?>

<form method="POST">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(mms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">Info Invoice</div>
                <div class="card-body">
                    <div class="mb-2">
                        <label>No. Invoice</label>
                        <input type="text" class="form-control fw-bold" value="<?= $data['invoice_number'] ?>" readonly>
                    </div>

                    <!-- INPUT BARU: NSFP -->
                    <div class="mb-2">
                        <label>No. Seri Faktur Pajak</label>
                        <input type="text" name="tax_invoice_number" class="form-control" value="<?= $data['tax_invoice_number'] ?>" placeholder="010.000-24.00000001">
                        <small class="text-muted" style="font-size: 10px;">Format: 000.000-YY.12345678</small>
                    </div>

                    <div class="mb-2">
                        <label>Referensi Surat Jalan <span class="text-danger">*</span></label>
                        <select name="delivery_note_id" class="form-select" required onchange="window.location.href='index.php?page=fin-ar&action=create&sj_id='+this.value">
                            <option value="">-- Pilih SJ --</option>
                            <?php foreach($sjs as $sj): 
                                $selected = $sj['id'] == $data['delivery_note_id'] ? 'selected' : '';
                            ?>
                                <option value="<?= $sj['id'] ?>" <?= $selected ?>><?= $sj['dn_number'] ?> - <?= $sj['cust_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="customer_id" value="<?= $data['customer_id'] ?>">
                    </div>
                    <div class="mb-2">
                        <label>Tanggal Invoice</label>
                        <input type="date" name="invoice_date" class="form-control" value="<?= $data['invoice_date'] ?>" required>
                    </div>
                    <div class="mb-2">
                        <label>Jatuh Tempo</label>
                        <input type="date" name="due_date" class="form-control" value="<?= $data['due_date'] ?>" required>
                    </div>
                    <div class="mb-2">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"><?= $data['notes'] ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">Detail Tagihan</div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Barang</th>
                                <th class="text-center">Qty Kirim (SJ)</th>
                                <th class="text-end">Harga Satuan (SO)</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if(!empty($items)): foreach($items as $item): 
                                $row_total = $item['qty_sent'] * $item['unit_price'];
                            ?>
                            <tr>
                                <td>
                                    <strong><?= $item['item_name'] ?></strong><br>
                                    <small class="text-muted"><?= $item['item_code'] ?></small>
                                </td>
                                <td class="text-center"><?= $item['qty_sent'] + 0 ?> <?= $item['unit'] ?></td>
                                <td class="text-end">Rp <?= number_format($item['unit_price'], 0, ',', '.') ?></td>
                                <td class="text-end">Rp <?= number_format($row_total, 0, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">Pilih Surat Jalan terlebih dahulu...</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <input type="hidden" name="subtotal_hidden" id="inpSubtotal" value="<?= $data['subtotal'] ?>">
                            <input type="hidden" name="tax_amount_hidden" id="inpTax" value="<?= $data['tax_amount'] ?>">
                            <input type="hidden" name="grand_total_hidden" id="inpGrand" value="<?= $data['grand_total'] ?>">

                            <tr>
                                <td colspan="3" class="text-end">Subtotal :</td>
                                <td class="text-end fw-bold">Rp <?= number_format($data['subtotal'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end align-middle">Diskon (Rp) :</td>
                                <td>
                                    <input type="text" name="discount_amount" id="disc" class="form-control text-end" value="<?= number_format($data['discount_amount'], 0, ',', '.') ?>" onkeyup="recalc()">
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end">PPN (11%) :</td>
                                <td class="text-end" id="txtTax">Rp <?= number_format($data['tax_amount'], 0, ',', '.') ?></td>
                            </tr>
                            <tr class="bg-primary text-white">
                                <td colspan="3" class="text-end fw-bold">GRAND TOTAL :</td>
                                <td class="text-end fw-bold fs-5" id="txtGrand">Rp <?= number_format($data['grand_total'], 0, ',', '.') ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="text-end">
                <a href="index.php?page=fin-ar" class="btn btn-secondary me-2">Batal</a>
                <button type="submit" class="btn btn-primary px-4 fw-bold">Simpan Invoice</button>
            </div>
        </div>
    </div>
</form>

<script>
function formatRupiah(num) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
}

function recalc() {
    let sub = parseFloat(document.getElementById('inpSubtotal').value) || 0;
    let discStr = document.getElementById('disc').value.replace(/\./g, '');
    let disc = parseFloat(discStr) || 0;

    let dpp = sub - disc;
    let tax = dpp * 0.11;
    let grand = dpp + tax;

    document.getElementById('txtTax').innerText = formatRupiah(tax);
    document.getElementById('txtGrand').innerText = formatRupiah(grand);

    document.getElementById('inpTax').value = tax;
    document.getElementById('inpGrand').value = grand;
}
</script>

<?php render_footer(); ?>
