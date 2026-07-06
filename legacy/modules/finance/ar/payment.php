<?php
// modules/finance/ar/payment.php

if (!isset($_GET['id'])) die("Error: ID tidak ditemukan.");
$id = $_GET['id'];
if (function_exists('mms_ensure_sales_orders_fulfillment_source_column')) {
    mms_ensure_sales_orders_fulfillment_source_column($pdo);
}

// 1. AMBIL DATA INVOICE & STATUS SURAT JALAN TERKAIT
// Kita perlu status SJ dan ID Sales Order untuk logika penutupan otomatis
$sql = "SELECT inv.*, c.name as cust_name, 
               dn.status as sj_status, dn.sales_order_id,
               COALESCE(so.fulfillment_source, 'spk') as fulfillment_source
        FROM invoices inv 
        JOIN customers c ON inv.customer_id = c.id 
        LEFT JOIN delivery_notes dn ON inv.delivery_note_id = dn.id
        LEFT JOIN sales_orders so ON so.id = dn.sales_order_id
        WHERE inv.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$inv = $stmt->fetch();

if (!$inv) die("Data Invoice tidak ditemukan.");

// Hitung Sisa Tagihan
$remaining = $inv['grand_total'] - $inv['paid_amount'];

// Jika sudah lunas, redirect
if ($remaining <= 0.01) {
    echo "<script>alert('Invoice ini sudah LUNAS!'); window.location='index.php?page=fin-ar';</script>";
    exit;
}

// 2. PROSES SIMPAN PEMBAYARAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    }
    $date = $_POST['payment_date'];
    $amount = floatval(preg_replace('/[^0-9]/', '', $_POST['amount'] ?? '0'));
    $method = $_POST['method'] ?? '';
    $notes = clean($_POST['notes']);
    $allowed_methods = ['Transfer Bank', 'Cash', 'Cek / Giro'];

    if (!isset($error) && !in_array($method, $allowed_methods, true)) {
        $error = "Metode pembayaran tidak valid.";
    } elseif (!isset($error) && $amount <= 0) {
        $error = "Jumlah pembayaran harus lebih dari 0.";
    } elseif (!isset($error) && $amount > ($remaining + 1)) {
        $error = "Jumlah pembayaran melebihi sisa tagihan.";
    } else {
        try {
            $tx_started = false;
            try {
                $tx_started = $pdo->beginTransaction();
            } catch (Exception $e) {
                $tx_started = false;
            }

            // Lock invoice untuk mencegah pembayaran ganda bersamaan.
            $stmt_lock = $pdo->prepare(
                "SELECT inv.grand_total,
                        inv.paid_amount,
                        inv.invoice_number,
                        inv.delivery_note_id,
                        inv.customer_id,
                        dn.status AS sj_status,
                        dn.sales_order_id,
                        COALESCE(so.fulfillment_source, 'spk') AS fulfillment_source
                 FROM invoices inv
                 LEFT JOIN delivery_notes dn ON dn.id = inv.delivery_note_id
                 LEFT JOIN sales_orders so ON so.id = dn.sales_order_id
                 WHERE inv.id = ?
                 FOR UPDATE"
            );
            $stmt_lock->execute([$id]);
            $inv_latest = $stmt_lock->fetch(PDO::FETCH_ASSOC);
            if (!$inv_latest) {
                throw new Exception("Invoice tidak ditemukan.");
            }

            $remaining_latest = (float)$inv_latest['grand_total'] - (float)$inv_latest['paid_amount'];
            if ($remaining_latest <= 0.01) {
                throw new Exception("Invoice ini sudah lunas.");
            }
            if ($amount > ($remaining_latest + 1)) {
                throw new Exception("Jumlah pembayaran melebihi sisa tagihan terbaru.");
            }

            // Gate close invoice: pelunasan (status paid) hanya boleh jika SPK SO sudah CLOSED.
            $new_paid_preview = (float)$inv_latest['paid_amount'] + $amount;
            $will_be_paid = $new_paid_preview >= ((float)$inv_latest['grand_total'] - 1);
            if ($will_be_paid) {
                $so_id_gate = (int)($inv_latest['sales_order_id'] ?? 0);
                $so_fulfillment_source = function_exists('mms_normalize_sales_order_fulfillment_source')
                    ? mms_normalize_sales_order_fulfillment_source($inv_latest['fulfillment_source'] ?? 'spk')
                    : 'spk';
                if ($so_id_gate <= 0) {
                    throw new Exception("Pelunasan ditahan: referensi SO pada invoice tidak ditemukan.");
                }
                if ($so_fulfillment_source !== 'fg_stock') {
                    $stmt_spk = $pdo->prepare("SELECT COUNT(*) FROM spk WHERE sales_order_id = ? AND status = 'closed'");
                    $stmt_spk->execute([$so_id_gate]);
                    if ((int)$stmt_spk->fetchColumn() <= 0) {
                        throw new Exception("Pelunasan/close invoice ditahan: SPK untuk SO terkait belum CLOSED (QC belum selesai).");
                    }
                }
            }

            // A. Insert History Pembayaran
            $sql_ins = "INSERT INTO invoice_payments (invoice_id, payment_date, amount, method, notes, recorded_by) 
                        VALUES (?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql_ins)->execute([$id, $date, $amount, $method, $notes, $_SESSION['user_id']]);

            // B. Update Header Invoice
            $new_paid = (float)$inv_latest['paid_amount'] + $amount;
            // Jika sisa < 1 rupiah dianggap lunas (pembulatan)
            $new_status = ($new_paid >= ((float)$inv_latest['grand_total'] - 1)) ? 'paid' : 'partial';

            $sql_upd = "UPDATE invoices SET paid_amount = ?, status = ?, updated_at = NOW() WHERE id = ?";
            $pdo->prepare($sql_upd)->execute([$new_paid, $new_status, $id]);

            // C. AUTO JURNAL (Debit Kas/Bank, Kredit Piutang)
            $coa_bank = get_coa_id('1-1002'); // Akun Bank
            $coa_ar = get_coa_id('1-1201');   // Piutang Usaha
            
            if ($coa_bank && $coa_ar) {
                $jurnal_items = [
                    ['coa_id' => $coa_bank, 'debit' => $amount, 'credit' => 0],
                    ['coa_id' => $coa_ar, 'debit' => 0, 'credit' => $amount]
                ];
                create_journal($date, $inv['invoice_number'], "Penerimaan Pembayaran ($method)", $jurnal_items, 'receipt');
            }

            // D. LOGIKA OTOMATIS COMPLETE/DELIVERED ORDER (SO)
            // Syarat: Invoice Lunas
            if ($new_status == 'paid') {
                $so_id = (int)($inv_latest['sales_order_id'] ?? 0);
                
                if ($so_id) {
                    // Cek Status Surat Jalan
                    // Jika SJ Approved/Sent -> Completed (Lunas + Kirim)
                    // Jika SJ belum -> Tetap (nanti berubah saat SJ approved)
                    if (in_array((string)($inv_latest['sj_status'] ?? ''), ['approved', 'sent'], true)) {
                         $final_status = 'completed';
                    } else {
                         // Pembayaran lunas tapi barang belum kirim -> status tetap atau bisa custom status 'paid_waiting_delivery'
                         // Untuk simplifikasi kita biarkan status SO apa adanya (misal 'confirmed' atau 'in_production')
                         $final_status = null; 
                    }

                    if ($final_status) {
                        if ($final_status === 'completed' && function_exists('mms_can_mark_sales_order_completed')) {
                            $guard = mms_can_mark_sales_order_completed((int)$so_id, $pdo);
                            if (empty($guard['ok'])) {
                                $so_no_guard = (string)($guard['so_number'] ?? ('#' . (int)$so_id));
                                throw new Exception("SO {$so_no_guard} tidak bisa di-set COMPLETED. " . (string)($guard['reason'] ?? 'Validasi gagal.'));
                            }
                        }

                        // Update Sales Order
                        $pdo->prepare("UPDATE sales_orders SET status = ? WHERE id = ?")->execute([$final_status, $so_id]);
                        
                        // Opsional: Tutup SPK juga jika belum closed
                        // $pdo->prepare("UPDATE spk SET status='completed' WHERE sales_order_id=?")->execute([$so_id]);
                    }
                }

                if (function_exists('notify_workflow_event')) {
                    notify_workflow_event(
                        'fin.ar.paid.' . (int)$id,
                        'Invoice Lunas',
                        "Invoice {$inv_latest['invoice_number']} sudah lunas.",
                        "index.php?page=fin-ar&action=print&id=" . (int)$id,
                        'success',
                        ['permission_slug' => 'acc_view']
                    );
                }
            } else {
                if (function_exists('notify_workflow_event')) {
                    notify_workflow_event(
                        'fin.ar.partial.' . (int)$id,
                        'Pembayaran Parsial Invoice',
                        "Invoice {$inv_latest['invoice_number']} menerima pembayaran parsial.",
                        "index.php?page=fin-ar&action=pay&id=" . (int)$id,
                        'info',
                        ['permission_slug' => 'fin_ar_manage']
                    );
                }
            }

            if ($tx_started && $pdo->inTransaction()) {
                $pdo->commit();
            }
            
            $msg = "Pembayaran berhasil disimpan!";
            if (isset($final_status) && $final_status == 'completed') {
                $msg .= " Order Penjualan (SO) telah otomatis menjadi COMPLETED.";
            }
            
            echo "<script>alert('$msg'); window.location='index.php?page=fin-ar';</script>";
            exit;

        } catch (Exception $e) {
            if ($tx_started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Gagal: " . $e->getMessage();
        }
    }
}

// Ambil Riwayat Pembayaran Sebelumnya
$stmt_hist = $pdo->prepare("SELECT * FROM invoice_payments WHERE invoice_id = ? ORDER BY payment_date DESC");
$stmt_hist->execute([$id]);
$history = $stmt_hist->fetchAll();

render_header("Input Penerimaan Pembayaran (AR)");
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        
        <!-- INFO INVOICE -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Informasi Invoice</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td width="120" class="text-muted">No. Invoice</td>
                                <td class="fw-bold"><?= $inv['invoice_number'] ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Customer</td>
                                <td><?= $inv['cust_name'] ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tgl Invoice</td>
                                <td><?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td>
                            </tr>
                             <tr>
                                <td class="text-muted">Status SJ</td>
                                <td>
                                    <?php if(in_array($inv['sj_status'], ['approved','sent'])): ?>
                                        <span class="badge bg-success">Terkirim</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Belum Kirim</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6 text-end">
                        <small class="text-muted">Total Tagihan</small>
                        <h4 class="fw-bold">Rp <?= number_format($inv['grand_total'], 0, ',', '.') ?></h4>
                        
                        <small class="text-muted">Sudah Dibayar</small>
                        <h5 class="text-success">Rp <?= number_format($inv['paid_amount'], 0, ',', '.') ?></h5>
                        
                        <hr>
                        <small class="text-muted">Sisa Tagihan</small>
                        <h3 class="text-danger fw-bold">Rp <?= number_format($remaining, 0, ',', '.') ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- FORM BAYAR -->
        <div class="card shadow mb-4">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-wallet2"></i> Form Penerimaan Dana</h6>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(mms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Tanggal Terima</label>
                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Metode Pembayaran</label>
                            <select name="method" class="form-select" required>
                                <option value="Transfer Bank">Transfer Bank</option>
                                <option value="Cash">Tunai (Cash)</option>
                                <option value="Cek / Giro">Cek / Giro</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Jumlah Diterima (Rp)</label>
                        <input type="text" name="amount" id="payAmount" class="form-control form-control-lg fw-bold text-success" 
                               value="<?= number_format($remaining, 0, ',', '.') ?>" required onkeyup="formatRibuan(this)">
                        <div class="form-text">Maksimal: Rp <?= number_format($remaining, 0, ',', '.') ?></div>
                    </div>

                    <div class="mb-3">
                        <label>Catatan / No. Referensi Transfer</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Pelunasan invoice / nomor referensi transfer"></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="index.php?page=fin-ar" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-success px-4 fw-bold">
                            <i class="bi bi-save"></i> Simpan & Close Order
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- RIWAYAT PEMBAYARAN -->
        <?php if (!empty($history)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0">Riwayat Pembayaran Masuk</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Metode</th>
                            <th>Catatan</th>
                            <th class="text-end">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($history as $hist): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($hist['payment_date'])) ?></td>
                            <td><?= $hist['method'] ?></td>
                            <td><?= $hist['notes'] ?></td>
                            <td class="text-end fw-bold">Rp <?= number_format($hist['amount'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function formatRibuan(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    input.value = new Intl.NumberFormat('id-ID').format(value);
}
</script>

<?php render_footer(); ?>
