<?php
// modules/warehouse/delivery/index.php
render_header("Surat Jalan (Delivery Note)");
if (function_exists('mms_ensure_sales_orders_fulfillment_source_column')) {
    mms_ensure_sales_orders_fulfillment_source_column($pdo);
}

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

// ACTION: APPROVE (Kurangi Stok Finish Good & Cek Status SO)
if (isset($_GET['action']) && $_GET['action'] == 'approve' && isset($_GET['id'])) {
    $csrf_req = $_GET['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=whse-sj';</script>";
        exit;
    }
    if (!has_permission('whse_sj_manage')) {
        echo "<script>alert('Akses Ditolak! Anda tidak memiliki izin Approve Surat Jalan.'); window.location='index.php?page=whse-sj';</script>";
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        $id = $_GET['id'];

        // Gate: SJ hanya boleh diproses bila SPK terkait SO sudah CLOSED (QC selesai).
        $stmt_gate = $pdo->prepare(
            "SELECT dn.sales_order_id, so.so_number,
                    COALESCE(so.fulfillment_source, 'spk') AS fulfillment_source,
                    EXISTS(
                        SELECT 1
                        FROM spk s
                        WHERE s.sales_order_id = so.id
                          AND s.status = 'closed'
                    ) AS spk_closed
             FROM delivery_notes dn
             JOIN sales_orders so ON so.id = dn.sales_order_id
             WHERE dn.id = ?
             FOR UPDATE"
        );
        $stmt_gate->execute([$id]);
        $gate = $stmt_gate->fetch(PDO::FETCH_ASSOC);
        if (!$gate) {
            throw new Exception("Data Surat Jalan tidak ditemukan.");
        }
        $so_fulfillment_source = function_exists('mms_normalize_sales_order_fulfillment_source')
            ? mms_normalize_sales_order_fulfillment_source($gate['fulfillment_source'] ?? 'spk')
            : 'spk';
        if ($so_fulfillment_source !== 'fg_stock' && (int)($gate['spk_closed'] ?? 0) !== 1) {
            throw new Exception("SO {$gate['so_number']} belum selesai QC/SPK (status SPK harus CLOSED).");
        }
        
        // 1. Update Status Surat Jalan (DN) jadi Approved
        $stmt = $pdo->prepare("UPDATE delivery_notes SET status='approved', approved_by=?, created_at=NOW() WHERE id=? AND status='draft'");
        $stmt->execute([$_SESSION['user_id'], $id]);
        
        if ($stmt->rowCount() > 0) {
            // 2. Kurangi Stok Finish Good
            $stmt_items = $pdo->prepare("SELECT item_id, qty_sent FROM delivery_note_items WHERE delivery_note_id=?");
            $stmt_items->execute([$id]);
            $items = $stmt_items->fetchAll();
            
            $stmt_stock = $pdo->prepare("UPDATE items SET current_stock = current_stock - ? WHERE id=?");
            foreach ($items as $item) {
                $stmt_stock->execute([$item['qty_sent'], $item['item_id']]);
            }
            
            // 3. LOGIKA UPDATE STATUS SO (FIXED)
            // Ambil sales_order_id dari Surat Jalan ini
            $stmt_so = $pdo->prepare("SELECT sales_order_id FROM delivery_notes WHERE id=?");
            $stmt_so->execute([$id]);
            $so_id = $stmt_so->fetchColumn();

            if ($so_id) {
                // Cek Status Pembayaran di Invoice terkait Delivery Note ini
                $is_paid = false;
                $chk_inv = $pdo->prepare("SELECT status FROM invoices WHERE delivery_note_id = ? AND status='paid'");
                $chk_inv->execute([$id]);
                if ($chk_inv->rowCount() > 0) {
                    $is_paid = true;
                }

                // TENTUKAN STATUS BARU
                // Jika sudah bayar lunas -> COMPLETED
                // Jika belum -> DELIVERED (Terkirim)
                $new_status = $is_paid ? 'completed' : 'delivered';

                // Hardening: status COMPLETED hanya boleh jika SO punya SPK CLOSED.
                if ($new_status === 'completed' && function_exists('mms_can_mark_sales_order_completed')) {
                    $guard = mms_can_mark_sales_order_completed((int)$so_id, $pdo);
                    if (empty($guard['ok'])) {
                        $so_no_guard = (string)($guard['so_number'] ?? $gate['so_number'] ?? ('#' . (int)$so_id));
                        throw new Exception("Status SO {$so_no_guard} tidak boleh COMPLETED. " . (string)($guard['reason'] ?? 'Validasi gagal.'));
                    }
                }

                // Update SO
                $pdo->prepare("UPDATE sales_orders SET status = ? WHERE id = ?")->execute([$new_status, $so_id]);
                
                if (function_exists('notify_workflow_event')) {
                    notify_workflow_event(
                        'whse.sj.approve.' . (int)$id,
                        'Surat Jalan Disetujui',
                        "SJ #$id disetujui. Status order menjadi " . strtoupper($new_status) . ".",
                        "index.php?page=fin-ar&action=create&so_id=" . (int)$so_id,
                        'info',
                        ['permission_slug' => 'fin_ar_manage']
                    );
                }
            }

            $pdo->commit();
            echo "<script>alert('Surat Jalan Disetujui! Stok berkurang. Status Order: ".strtoupper($new_status)."'); window.location='index.php?page=whse-sj';</script>";
        } else {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "<script>alert('Gagal Approve. Status mungkin sudah berubah.'); window.location='index.php?page=whse-sj';</script>";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<script>alert('Error: ".$e->getMessage()."'); window.location='index.php?page=whse-sj';</script>";
    }
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-truck"></i> Surat Jalan</h3>
        <p class="text-muted">Pengiriman barang jadi ke customer.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=whse-sj&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Buat Surat Jalan
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="sj-filter-form">
            <input type="hidden" name="page" value="whse-sj">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari No. SJ / Customer / SO..." value="<?= $search_key ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="draft" <?= $filter_status=='draft'?'selected':'' ?>>Draft</option>
                    <option value="approved" <?= $filter_status=='approved'?'selected':'' ?>>Approved</option>
                    <option value="sent" <?= $filter_status=='sent'?'selected':'' ?>>Sent</option>
                    <option value="returned" <?= $filter_status=='returned'?'selected':'' ?>>Returned</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=whse-sj" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>No. SJ</th>
                        <th>Tgl Kirim</th>
                        <th>Customer</th>
                        <th>Ref. SO</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT dn.*, so.so_number, c.name as cust_name
                            FROM delivery_notes dn
                            JOIN sales_orders so ON dn.sales_order_id = so.id
                            JOIN customers c ON so.customer_id = c.id
                            WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND dn.status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (dn.dn_number LIKE ? OR c.name LIKE ? OR so.so_number LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY dn.id DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    while ($row = $stmt->fetch()):
                        $badge = match($row['status']) {
                            'draft' => 'bg-secondary',
                            'approved' => 'bg-primary', 
                            'sent' => 'bg-success',     
                            'returned' => 'bg-danger',
                            default => 'bg-light'
                        };
                    ?>
                    <tr>
                        <td><strong><?= clean($row['dn_number']) ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($row['dn_date'])) ?></td>
                        <td><?= clean($row['cust_name']) ?></td>
                        <td><?= clean($row['so_number']) ?></td>
                        <td><span class="badge <?= $badge ?>"><?= strtoupper($row['status']) ?></span></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="index.php?page=whse-sj&action=print&id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Cetak SJ"><i class="bi bi-printer"></i></a>

                                <?php if($row['status'] == 'draft'): ?>
                                    <a href="index.php?page=whse-sj&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning text-dark"><i class="bi bi-pencil"></i></a>
                                    
                                    <?php if(has_permission('whse_sj_manage')): ?>
                                        <a href="index.php?page=whse-sj&action=approve&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve pengiriman ini? Stok akan berkurang.')"><i class="bi bi-check-lg"></i> Approve</a>
                                    <?php endif; ?>
                                    
                                    <a href="index.php?page=whse-sj&action=delete&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus SJ?')"><i class="bi bi-trash"></i></a>
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
    const form = document.getElementById('sj-filter-form');
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
