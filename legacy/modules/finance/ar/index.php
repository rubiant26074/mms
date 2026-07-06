<?php
// modules/finance/ar/index.php
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
if (isset($_GET['action'], $_GET['id']) && is_numeric($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    $csrf_req = $_GET['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=fin-ar';</script>";
        exit;
    }

    if ($action === 'post') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT inv.*, c.name AS cust_name, c.phone AS cust_phone FROM invoices inv JOIN customers c ON c.id = inv.customer_id WHERE inv.id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$inv) {
                throw new Exception("Invoice tidak ditemukan.");
            }
            if ($inv['status'] !== 'draft') {
                throw new Exception("Hanya invoice status Draft yang bisa diterbitkan.");
            }
            if ((float)$inv['tax_amount'] > 0 && empty($inv['tax_invoice_number'])) {
                throw new Exception("No. Seri Faktur Pajak wajib diisi sebelum invoice diterbitkan.");
            }

            $pdo->prepare("UPDATE invoices SET status='unpaid', updated_at=NOW() WHERE id=?")->execute([$id]);
            if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'fin.ar.post.' . (int)$id,
                    'Invoice Diterbitkan',
                    "Invoice {$inv['invoice_number']} sudah terbit dan siap ditagih.",
                    "index.php?page=fin-ar&action=pay&id=" . (int)$id,
                    'info',
                    ['permission_slug' => 'fin_ar_manage']
                );
            }

            $journal_note = '';
            $coa_ar = get_coa_id('1-1201');      // Piutang Usaha
            $coa_sales = get_coa_id('4-1001');   // Pendapatan Penjualan

            if ($coa_ar && $coa_sales && (float)$inv['grand_total'] > 0) {
                $jurnal_items = [
                    ['coa_id' => $coa_ar, 'debit' => (float)$inv['grand_total'], 'credit' => 0],
                    ['coa_id' => $coa_sales, 'debit' => 0, 'credit' => (float)$inv['grand_total']]
                ];
                create_journal($inv['invoice_date'], $inv['invoice_number'], "Penerbitan Invoice {$inv['cust_name']}", $jurnal_items, 'sales');
            } else {
                $journal_note = " Jurnal belum dibuat (COA belum lengkap).";
            }

            $pdo->commit();

            $wa_note = '';
            try {
                if (!empty($inv['cust_phone']) && function_exists('build_public_invoice_url') && function_exists('send_wa_fonte')) {
                    $public_link = build_public_invoice_url((int)$id, 24 * 30);
                    if ($public_link !== '') {
                        $msg = "Yth. {$inv['cust_name']},\n";
                        $msg .= "Invoice {$inv['invoice_number']} telah kami terbitkan.\n";
                        $msg .= "Silakan buka / print invoice melalui link berikut (tanpa login):\n{$public_link}\n";
                        $msg .= "Terima kasih.";

                        $wa_res = send_wa_fonte($inv['cust_phone'], $msg, $public_link);
                        if (empty($wa_res['ok'])) {
                            $wa_note = "\\nWA customer gagal dikirim: " . addslashes((string)($wa_res['error'] ?? 'provider error'));
                        } else {
                            $wa_note = "\\nLink invoice berhasil dikirim ke WA customer.";
                        }
                    } else {
                        $wa_note = "\\nWA customer gagal dikirim: link invoice publik tidak tersedia.";
                    }
                } else {
                    if (empty($inv['cust_phone'])) {
                        $wa_note = "\\nWA customer tidak dikirim: nomor HP customer belum diisi.";
                    }
                }
            } catch (Exception $e) {
                $wa_note = "\\nWA customer gagal dikirim.";
            }

            echo "<script>alert('Invoice berhasil diterbitkan!{$journal_note}{$wa_note}'); window.location='index.php?page=fin-ar';</script>";
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "<script>alert('Gagal terbitkan invoice: " . addslashes($e->getMessage()) . "'); window.location='index.php?page=fin-ar';</script>";
            exit;
        }
    }

    if ($action === 'unpost') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$inv) {
                throw new Exception("Invoice tidak ditemukan.");
            }

            if ($inv['status'] !== 'unpaid' || (float)$inv['paid_amount'] > 0) {
                throw new Exception("Unpost hanya untuk invoice Unpaid tanpa pembayaran.");
            }

            $stmt_pay = $pdo->prepare("SELECT COUNT(*) FROM invoice_payments WHERE invoice_id = ?");
            $stmt_pay->execute([$id]);
            if ((int)$stmt_pay->fetchColumn() > 0) {
                throw new Exception("Invoice sudah memiliki histori pembayaran.");
            }

            $pdo->prepare("UPDATE invoices SET status='draft', updated_at=NOW() WHERE id=?")->execute([$id]);
            delete_journal_by_reference($inv['invoice_number'], 'sales');
            if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'fin.ar.unpost.' . (int)$id,
                    'Invoice Dikembalikan ke Draft',
                    "Invoice {$inv['invoice_number']} di-unpost untuk revisi.",
                    "index.php?page=fin-ar&action=edit&id=" . (int)$id,
                    'warning',
                    ['permission_slug' => 'fin_ar_manage']
                );
            }

            $pdo->commit();
            echo "<script>alert('Penerbitan invoice berhasil dibatalkan.'); window.location='index.php?page=fin-ar';</script>";
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "<script>alert('Gagal unpost invoice: " . addslashes($e->getMessage()) . "'); window.location='index.php?page=fin-ar';</script>";
            exit;
        }
    }
}

render_header("Account Receivable (Invoice)");

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';

// Pesan Notifikasi dari Redirect
if (isset($_GET['msg'])) {
    $msg = htmlspecialchars($_GET['msg']);
    echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>
            <i class='bi bi-check-circle me-2'></i> $msg
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
          </div>";
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-receipt-cutoff"></i> Invoice Penjualan</h3>
        <p class="text-muted">Penagihan ke customer dan Faktur Pajak.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=fin-ar&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Buat Invoice Baru
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="ar-filter-form">
            <input type="hidden" name="page" value="fin-ar">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari No. Invoice / FP / Customer..." value="<?= $search_key ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="draft" <?= $filter_status=='draft'?'selected':'' ?>>Draft</option>
                    <option value="unpaid" <?= $filter_status=='unpaid'?'selected':'' ?>>Unpaid</option>
                    <option value="partial" <?= $filter_status=='partial'?'selected':'' ?>>Partial</option>
                    <option value="paid" <?= $filter_status=='paid'?'selected':'' ?>>Paid</option>
                    <option value="cancelled" <?= $filter_status=='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=fin-ar" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- KARTU RINGKASAN -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white shadow-sm">
            <div class="card-body">
                <h6>Total Piutang (Outstanding)</h6>
                <?php 
                $stmt_piutang = $pdo->query("SELECT SUM(grand_total - paid_amount) FROM invoices WHERE status != 'paid' AND status != 'cancelled'");
                $total_piutang = $stmt_piutang->fetchColumn();
                ?>
                <h3 class="fw-bold mb-0">Rp <?= number_format($total_piutang, 0, ',', '.') ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>No. Invoice</th>
                        <th>Tanggal</th>
                        <th>Customer</th>
                        <th>Jatuh Tempo</th>
                        <th class="text-end">Total Tagihan</th>
                        <th class="text-end">Sisa Tagihan</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" width="220">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT inv.*, c.name as cust_name 
                            FROM invoices inv 
                            JOIN customers c ON inv.customer_id = c.id 
                            WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND inv.status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (inv.invoice_number LIKE ? OR inv.tax_invoice_number LIKE ? OR c.name LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY inv.id DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    while ($row = $stmt->fetch()):
                        $sisa = $row['grand_total'] - $row['paid_amount'];
                        $badge = match($row['status']) {
                            'draft' => 'bg-secondary',
                            'unpaid' => 'bg-danger',
                            'partial' => 'bg-warning text-dark',
                            'paid' => 'bg-success',
                            'cancelled' => 'bg-dark',
                            default => 'bg-light'
                        };
                    ?>
                    <tr>
                        <td>
                            <strong><?= clean($row['invoice_number']) ?></strong>
                            <?php if(!empty($row['tax_invoice_number'])): ?>
                                <br><small class="text-muted" style="font-size:10px;">FP: <?= clean($row['tax_invoice_number']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/y', strtotime($row['invoice_date'])) ?></td>
                        <td><?= clean($row['cust_name']) ?></td>
                        <td>
                            <?= date('d/m/y', strtotime($row['due_date'])) ?>
                            <?php if($row['status']!='paid' && $row['due_date'] < date('Y-m-d')): ?>
                                <i class="bi bi-exclamation-circle-fill text-danger" title="Jatuh Tempo!"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">Rp <?= number_format($row['grand_total'], 0, ',', '.') ?></td>
                        <td class="text-end fw-bold text-danger">Rp <?= number_format($sisa, 0, ',', '.') ?></td>
                        <td class="text-center"><span class="badge <?= $badge ?>"><?= strtoupper($row['status']) ?></span></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <!-- Print Invoice -->
                                <a href="index.php?page=fin-ar&action=print&id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Cetak Invoice">
                                    <i class="bi bi-printer"></i>
                                </a>
                                
                                <!-- Print Faktur Pajak (BARU) -->
                                <a href="index.php?page=fin-ar&action=print_tax&id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary font-monospace fw-bold" title="Cetak Faktur Pajak">FP</a>
                                
                                <!-- Bayar (Payment) -->
                                <?php if($row['status'] == 'unpaid' || $row['status'] == 'partial'): ?>
                                    <a href="index.php?page=fin-ar&action=pay&id=<?= $row['id'] ?>" class="btn btn-sm btn-success" title="Input Pembayaran"><i class="bi bi-cash-coin"></i></a>
                                <?php endif; ?>

                                <!-- Unpost (Batal Posting) -->
                                <?php if($row['status'] == 'unpaid' && $row['paid_amount'] == 0): ?>
                                    <a href="index.php?page=fin-ar&action=unpost&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Batalkan Posting? Jurnal akan dihapus dan status kembali ke Draft.')" title="Unpost / Revisi"><i class="bi bi-arrow-counterclockwise"></i></a>
                                <?php endif; ?>

                                <!-- Edit & Post (Hanya Draft) -->
                                <?php if($row['status'] == 'draft'): ?>
                                    <a href="index.php?page=fin-ar&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning text-dark" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <a href="index.php?page=fin-ar&action=post&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-primary" onclick="return confirm('Posting Invoice? Data tidak bisa diubah lagi.')" title="Post / Terbitkan"><i class="bi bi-send"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('ar-filter-form');
    if (!form) return;

    const search = form.querySelector('input[name="search"]');
    const status = form.querySelector('select[name="status"]');
    let t;

    const submit = () => {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    };

    if (search) {
        search.addEventListener('input', () => {
            clearTimeout(t);
            t = setTimeout(submit, 400);
        });
    }
    if (status) {
        status.addEventListener('change', submit);
    }
})();
</script>

<?php render_footer(); ?>
