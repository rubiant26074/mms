<?php
// modules/finance/ap/index.php
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
if (isset($_GET['action'], $_GET['id']) && is_numeric($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    $csrf_req = $_GET['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=fin-ap';</script>";
        exit;
    }

    if ($action === 'post') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT sb.*, s.name AS supplier_name FROM supplier_bills sb JOIN suppliers s ON s.id = sb.supplier_id WHERE sb.id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bill) {
                throw new Exception("Tagihan tidak ditemukan.");
            }
            if ($bill['status'] !== 'draft') {
                throw new Exception("Hanya tagihan status Draft yang bisa diposting.");
            }

            $stmt_items = $pdo->prepare("SELECT COUNT(*) FROM supplier_bill_items WHERE bill_id = ?");
            $stmt_items->execute([$id]);
            $item_count = (int)$stmt_items->fetchColumn();
            if ($item_count <= 0) {
                throw new Exception("Tagihan belum memiliki item.");
            }

            $pdo->prepare("UPDATE supplier_bills SET status='unpaid', updated_at=NOW() WHERE id=?")->execute([$id]);
            if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'fin.ap.post.' . (int)$id,
                    'Tagihan Supplier Diposting',
                    "Tagihan {$bill['bill_number']} sudah diposting ke AP.",
                    "index.php?page=fin-ap&action=pay&id=" . (int)$id,
                    'info',
                    ['permission_slug' => 'fin_ap_manage']
                );
            }

            $journal_note = '';
            $coa_inv = get_coa_id('1-1301'); // Persediaan Bahan Baku
            $coa_ap = get_coa_id('2-1001');  // Hutang Usaha
            if ($coa_inv && $coa_ap && (float)$bill['grand_total'] > 0) {
                $jurnal_items = [
                    ['coa_id' => $coa_inv, 'debit' => (float)$bill['grand_total'], 'credit' => 0],
                    ['coa_id' => $coa_ap, 'debit' => 0, 'credit' => (float)$bill['grand_total']]
                ];
                create_journal($bill['bill_date'], $bill['bill_number'], "Posting Tagihan Supplier {$bill['supplier_name']}", $jurnal_items, 'purchase');
            } else {
                $journal_note = " Jurnal belum dibuat (COA belum lengkap).";
            }

            $pdo->commit();
            echo "<script>alert('Tagihan berhasil diposting!{$journal_note}'); window.location='index.php?page=fin-ap';</script>";
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "<script>alert('Gagal posting: " . addslashes($e->getMessage()) . "'); window.location='index.php?page=fin-ap';</script>";
            exit;
        }
    }

    if ($action === 'unpost') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM supplier_bills WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$bill) {
                throw new Exception("Tagihan tidak ditemukan.");
            }

            if ($bill['status'] !== 'unpaid' || (float)$bill['paid_amount'] > 0) {
                throw new Exception("Unpost hanya untuk tagihan Unpaid tanpa pembayaran.");
            }

            $stmt_pay = $pdo->prepare("SELECT COUNT(*) FROM supplier_payments WHERE bill_id = ?");
            $stmt_pay->execute([$id]);
            if ((int)$stmt_pay->fetchColumn() > 0) {
                throw new Exception("Tagihan sudah memiliki histori pembayaran.");
            }

            $pdo->prepare("UPDATE supplier_bills SET status='draft', updated_at=NOW() WHERE id=?")->execute([$id]);
            delete_journal_by_reference($bill['bill_number'], 'purchase');
            if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'fin.ap.unpost.' . (int)$id,
                    'Tagihan Supplier Di-unpost',
                    "Tagihan {$bill['bill_number']} dikembalikan ke draft untuk revisi.",
                    "index.php?page=fin-ap&action=edit&id=" . (int)$id,
                    'warning',
                    ['permission_slug' => 'fin_ap_manage']
                );
            }

            $pdo->commit();
            echo "<script>alert('Posting tagihan berhasil dibatalkan.'); window.location='index.php?page=fin-ap';</script>";
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "<script>alert('Gagal unpost: " . addslashes($e->getMessage()) . "'); window.location='index.php?page=fin-ap';</script>";
            exit;
        }
    }
}

render_header("Account Payable (Hutang)");

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-wallet2"></i> Tagihan Supplier (AP)</h3>
        <p class="text-muted">Manajemen hutang usaha kepada vendor.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=fin-ap&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Input Tagihan Baru
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="ap-filter-form">
            <input type="hidden" name="page" value="fin-ap">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari No. Bill / Supplier / Inv Supplier / PO..." value="<?= $search_key ?>" autocomplete="off">
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
                <a href="index.php?page=fin-ap" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-danger text-white shadow-sm">
            <div class="card-body">
                <h6>Total Hutang (Outstanding)</h6>
                <?php 
                $stmt_hutang = $pdo->query("SELECT SUM(grand_total - paid_amount) FROM supplier_bills WHERE status != 'paid' AND status != 'cancelled'");
                $total_hutang = $stmt_hutang->fetchColumn();
                ?>
                <h3 class="fw-bold mb-0">Rp <?= number_format($total_hutang, 0, ',', '.') ?></h3>
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
                        <th>No. Bill</th>
                        <th>Inv Supplier</th>
                        <th>Supplier</th>
                        <th>Jatuh Tempo</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Sisa</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" width="220">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT sb.*, s.name as supplier_name, po.po_number 
                            FROM supplier_bills sb 
                            JOIN suppliers s ON sb.supplier_id = s.id 
                            LEFT JOIN purchase_orders po ON sb.purchase_order_id = po.id
                            WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND sb.status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (sb.bill_number LIKE ? OR s.name LIKE ? OR sb.supplier_inv_number LIKE ? OR po.po_number LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY sb.id DESC";
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
                            <strong><?= clean($row['bill_number']) ?></strong>
                            <?php if($row['po_number']): ?>
                                <br><small class="text-muted">Ref PO: <?= $row['po_number'] ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= clean($row['supplier_inv_number']) ?></td>
                        <td><?= clean($row['supplier_name']) ?></td>
                        <td><?= date('d/m/Y', strtotime($row['due_date'])) ?></td>
                        <td class="text-end">Rp <?= number_format($row['grand_total'], 0, ',', '.') ?></td>
                        <td class="text-end fw-bold text-danger">Rp <?= number_format($sisa, 0, ',', '.') ?></td>
                        <td class="text-center"><span class="badge <?= $badge ?>"><?= strtoupper($row['status']) ?></span></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <!-- Bayar -->
                                <?php if($row['status'] == 'unpaid' || $row['status'] == 'partial'): ?>
                                    <a href="index.php?page=fin-ap&action=pay&id=<?= $row['id'] ?>" class="btn btn-sm btn-success" title="Bayar"><i class="bi bi-cash-stack"></i></a>
                                <?php endif; ?>

                                <!-- Unpost (Jika Salah Posting & Belum Dibayar) -->
                                <?php if($row['status'] == 'unpaid' && $row['paid_amount'] == 0): ?>
                                    <a href="index.php?page=fin-ap&action=unpost&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Batalkan Posting? Jurnal akan dihapus dan status kembali ke Draft.')" title="Unpost / Revisi"><i class="bi bi-arrow-counterclockwise"></i></a>
                                <?php endif; ?>

                                <!-- Edit & Post (Draft Only) -->
                                <?php if($row['status'] == 'draft'): ?>
                                    <a href="index.php?page=fin-ap&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning text-dark"><i class="bi bi-pencil"></i></a>
                                    <a href="index.php?page=fin-ap&action=post&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-primary" onclick="return confirm('Posting Tagihan? Data akan masuk buku hutang.')" title="Post"><i class="bi bi-send"></i></a>
                                    <a href="index.php?page=fin-ap&action=delete&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus data?')" title="Hapus"><i class="bi bi-trash"></i></a>
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
    const form = document.getElementById('ap-filter-form');
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
