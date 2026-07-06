<?php
// modules/procurement/orders/index.php
if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('purch_po')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=dashboard';</script>";
    exit;
}

render_header("Purchase Order (PO)");
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';

// --- LOGIKA ACTION ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = (string)$_GET['action'];
    $id = (int)$_GET['id'];
    $mutation_actions = ['submit', 'approve', 'approve_finance', 'send_vendor', 'cancel'];
    if (in_array($action, $mutation_actions, true)) {
        $csrf_req = $_GET['csrf'] ?? $_POST['csrf'] ?? '';
        if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
            echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=purch-po';</script>";
            exit;
        }
    }
    if ($id <= 0) {
        echo "<script>alert('Request tidak valid.'); window.location='index.php?page=purch-po';</script>";
        exit;
    }

    // 1. SUBMIT (Purchasing mengajukan ke Plant Manager)
    if ($action == 'submit') {
        if (!has_permission('purch_po_manage')) {
            echo "<script>alert('Akses Ditolak!'); window.location='index.php?page=purch-po';</script>";
            exit;
        }
        $pdo->prepare("UPDATE purchase_orders SET status='submitted' WHERE id=? AND status='draft'")->execute([$id]);
        
        // --- TRIGGER NOTIFIKASI KE PLANT MANAGER ---
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'purch.po.submit',
                'Approval PO (Plant Manager)',
                "Purchase Order #$id diajukan oleh Purchasing dan menunggu persetujuan Plant Manager.",
                "index.php?page=purch-po&status=submitted",
                'warning',
                ['permission_slug' => 'purch_po_approve']
            );
        }
        
        echo "<script>alert('PO berhasil diajukan untuk approval.'); window.location='index.php?page=purch-po';</script>";
    }

    // 2. APPROVE (Plant Manager menyetujui)
    if ($action == 'approve') {
        if (!has_permission('purch_po_approve')) {
            echo "<script>alert('Akses Ditolak! Anda tidak memiliki izin Approve PO.'); window.location='index.php?page=purch-po';</script>";
            exit;
        }

        $stmt = $pdo->prepare("UPDATE purchase_orders SET status='approved_pm', approved_by=?, approved_at=NOW() WHERE id=? AND status='submitted'");
        $stmt->execute([$_SESSION['user_id'], $id]);
        
        if ($stmt->rowCount() > 0) {
            // --- TRIGGER NOTIFIKASI KE FINANCE ---
            if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'purch.po.approve.pm',
                    'Approval Finance Dibutuhkan',
                    "PO #$id sudah disetujui Plant Manager. Menunggu approval Finance.",
                    "index.php?page=purch-po&status=approved_pm",
                    'success',
                    ['permission_slug' => 'purch_po_approve_finance']
                );
            }

            echo "<script>alert('PO berhasil di-Approve Plant Manager!'); window.location='index.php?page=purch-po';</script>";
        } else {
            echo "<script>alert('Gagal! PO harus berstatus Submitted untuk diapprove.'); window.location='index.php?page=purch-po';</script>";
        }
    }

    // 2b. APPROVE FINANCE
    if ($action == 'approve_finance') {
        if (!has_permission('purch_po_approve_finance')) {
            echo "<script>alert('Akses Ditolak! Anda tidak memiliki izin Approve PO (Finance).'); window.location='index.php?page=purch-po';</script>";
            exit;
        }

        $stmt = $pdo->prepare("UPDATE purchase_orders SET status='approved_finance', finance_approved_by=?, finance_approved_at=NOW() WHERE id=? AND status='approved_pm'");
        $stmt->execute([$_SESSION['user_id'], $id]);
        
        if ($stmt->rowCount() > 0) {
            if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'purch.po.approve.finance',
                    'PO Disetujui Finance',
                    "PO #$id sudah disetujui Finance. Silakan konfirmasi ke Supplier.",
                    "index.php?page=purch-po&status=approved_finance",
                    'success',
                    ['permission_slug' => 'purch_po_manage']
                );
            }

            echo "<script>alert('PO berhasil di-Approve Finance!'); window.location='index.php?page=purch-po';</script>";
        } else {
            echo "<script>alert('Gagal! PO harus berstatus Approved (Plant Manager) untuk diapprove Finance.'); window.location='index.php?page=purch-po';</script>";
        }
    }
    
    // 3. SENT TO VENDOR (Purchasing konfirmasi ke Vendor)
    if ($action == 'send_vendor') {
        if (!has_permission('purch_po_manage')) {
             echo "<script>alert('Akses Ditolak!'); window.location='index.php?page=purch-po';</script>";
             exit;
        }
        $pdo->prepare("UPDATE purchase_orders SET status='sent' WHERE id=? AND status='approved_finance'")->execute([$id]);
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'purch.po.send_vendor',
                'PO Terkirim ke Vendor',
                "PO #$id telah dikirim ke vendor. Menunggu penerimaan gudang.",
                "index.php?page=whse-receive&action=create&po_id=$id",
                'info',
                ['permission_slug' => 'whse_view']
            );
        }
        echo "<script>alert('Status PO diubah menjadi SENT (Terkirim ke Vendor).'); window.location='index.php?page=purch-po';</script>";
    }

    // 4. MATERIAL ARRIVED (Barang Datang -> Arahkan ke Penerimaan)
    if ($action == 'receive_material') {
        header("Location: index.php?page=whse-receive&action=create&po_id=" . $id);
        exit;
    }
    
    // 5. REJECT / CANCEL
    if ($action == 'cancel') {
        if (!has_permission('purch_po_manage')) {
            echo "<script>alert('Akses Ditolak!'); window.location='index.php?page=purch-po';</script>";
            exit;
        }
        $pdo->prepare("UPDATE purchase_orders SET status='cancelled' WHERE id=?")->execute([$id]);
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'purch.po.cancel',
                'PO Dibatalkan',
                "PO #$id dibatalkan.",
                "index.php?page=purch-po",
                'warning',
                ['permission_slug' => 'purch_po_approve']
            );
        }
        echo "<script>alert('PO dibatalkan.'); window.location='index.php?page=purch-po';</script>";
    }
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-bag-check"></i> Purchase Order</h3>
        <p class="text-muted">Order pembelian material ke Supplier/Vendor.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=purch-po&action=create" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-lg"></i> Buat PO Baru
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="po-filter-form">
            <input type="hidden" name="page" value="purch-po">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari No. PO / Supplier..." value="<?= $esc($search_key) ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="draft" <?= $filter_status=='draft'?'selected':'' ?>>Draft</option>
                    <option value="submitted" <?= $filter_status=='submitted'?'selected':'' ?>>Submitted</option>
                    <option value="approved_pm" <?= $filter_status=='approved_pm'?'selected':'' ?>>Approved PM</option>
                    <option value="approved_finance" <?= $filter_status=='approved_finance'?'selected':'' ?>>Approved Finance</option>
                    <option value="sent" <?= $filter_status=='sent'?'selected':'' ?>>Sent</option>
                    <option value="completed" <?= $filter_status=='completed'?'selected':'' ?>>Completed</option>
                    <option value="cancelled" <?= $filter_status=='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=purch-po" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
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
                        <th>No. PO</th>
                        <th>Tanggal</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th class="text-end">Total</th>
                        <th class="text-center" width="200">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $has_fin_cols = false;
                    try {
                        $has_fin_cols = $pdo->query("SHOW COLUMNS FROM purchase_orders LIKE 'finance_approved_by'")->rowCount() > 0;
                    } catch (Exception $e) {
                        $has_fin_cols = false;
                    }

                    $sql = "SELECT po.*, s.name as supplier_name,
                                   u_pm.fullname as pm_name";
                    if ($has_fin_cols) {
                        $sql .= ", u_fin.fullname as fin_name";
                    }
                    $sql .= " FROM purchase_orders po 
                              JOIN suppliers s ON po.supplier_id = s.id
                              LEFT JOIN users u_pm ON po.approved_by = u_pm.id";
                    if ($has_fin_cols) {
                        $sql .= " LEFT JOIN users u_fin ON po.finance_approved_by = u_fin.id";
                    }
                    $sql .= " WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND po.status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (po.po_number LIKE ? OR s.name LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY po.id DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    while ($row = $stmt->fetch()):
                        $badge = match($row['status']) {
                            'draft' => 'bg-secondary',
                            'submitted' => 'bg-warning text-dark',
                            'approved_pm' => 'bg-primary',
                            'approved_finance' => 'bg-success',
                            'sent' => 'bg-info text-dark',
                            'completed' => 'bg-success',
                            'cancelled' => 'bg-danger',
                            default => 'bg-light'
                        };
                        $can_approve = has_permission('purch_po_approve');
                        $can_finance = has_permission('purch_po_approve_finance');
                        $can_manage = has_permission('purch_po_manage');
                    ?>
                    <tr>
                        <td><strong><?= clean($row['po_number']) ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($row['po_date'])) ?></td>
                        <td><?= clean($row['supplier_name']) ?></td>
                        <td>
                            <div><span class="badge <?= $badge ?>"><?= strtoupper(str_replace('_', ' ', (string)$row['status'])) ?></span></div>
                            <div class="text-muted small mt-1">
                                PM: <?= !empty($row['pm_name']) ? clean($row['pm_name']) : '-' ?>
                                <?php if (!empty($row['approved_at'])): ?>
                                    (<?= date('d/m/Y H:i', strtotime($row['approved_at'])) ?>)
                                <?php endif; ?>
                            </div>
                            <?php if ($has_fin_cols): ?>
                            <div class="text-muted small">
                                FIN: <?= !empty($row['fin_name']) ? clean($row['fin_name']) : '-' ?>
                                <?php if (!empty($row['finance_approved_at'])): ?>
                                    (<?= date('d/m/Y H:i', strtotime($row['finance_approved_at'])) ?>)
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold">Rp <?= number_format($row['grand_total'], 0, ',', '.') ?></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <!-- Print Selalu Ada -->
                                <a href="index.php?page=purch-po&action=print&id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Cetak PO"><i class="bi bi-printer"></i></a>

                                <!-- 1. Draft: Edit, Submit, Hapus -->
                                <?php if($row['status'] == 'draft' && $can_manage): ?>
                                    <a href="index.php?page=purch-po&action=edit&id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning text-dark"><i class="bi bi-pencil"></i></a>
                                    <a href="index.php?page=purch-po&action=submit&id=<?= (int)$row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-success" onclick="return confirm('Ajukan PO ini?')" title="Submit"><i class="bi bi-send"></i></a>
                                    <a href="index.php?page=purch-po&action=delete&id=<?= (int)$row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus PO ini?')"><i class="bi bi-trash"></i></a>
                                <?php endif; ?>

                                <!-- 2. Submitted: Approve (Plant Manager) -->
                                <?php if($row['status'] == 'submitted' && $can_approve): ?>
                                    <a href="index.php?page=purch-po&action=approve&id=<?= (int)$row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-primary" onclick="return confirm('Approve PO ini?')" title="Approve"><i class="bi bi-check-lg"></i> Appv</a>
                                <?php endif; ?>

                                <!-- 3. Approved PM: Finance Approve -->
                                <?php if($row['status'] == 'approved_pm' && $can_finance): ?>
                                    <a href="index.php?page=purch-po&action=approve_finance&id=<?= (int)$row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve PO ini (Finance)?')" title="Approve Finance"><i class="bi bi-check-lg"></i> Finance</a>
                                <?php endif; ?>

                                <!-- 4. Approved Finance: Konfirmasi Supplier (Purchasing) -->
                                <?php if($row['status'] == 'approved_finance' && $can_manage): ?>
                                    <a href="index.php?page=purch-po&action=send_vendor&id=<?= (int)$row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-info text-dark" onclick="return confirm('Konfirmasi PO ke Supplier?')" title="Konfirmasi Supplier"><i class="bi bi-envelope-paper"></i> Konfirmasi</a>
                                <?php endif; ?>

                                <!-- 5. Sent: Material Datang (Arahkan ke Warehouse) -->
                                <?php if($row['status'] == 'sent'): ?>
                                    <a href="index.php?page=whse-receive&action=create&po_id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-success fw-bold" title="Barang Datang / Receive"><i class="bi bi-box-seam"></i> Terima</a>
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
    const form = document.getElementById('po-filter-form');
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
