<?php
// modules/ppic/purchase_requests/index.php
render_header("Purchase Requests (PR)");
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';

// --- LOGIKA ACTION LAINNYA (SUBMIT & APPROVE) ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = (string)$_GET['action'];
    if (in_array($action, ['submit', 'approve'], true)) {
        $csrfReq = $_GET['csrf'] ?? '';
        if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrfReq)) {
            echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=ppic-pr';</script>";
            exit;
        }
    }
    $id = (int)$_GET['id'];
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($id <= 0 || $uid <= 0) {
        echo "<script>alert('Request tidak valid.'); window.location='index.php?page=ppic-pr';</script>";
        exit;
    }

    if ($action == 'submit') {
        if (!has_permission('ppic_pr_manage')) {
            echo "<script>alert('Akses Ditolak!'); window.location='index.php?page=ppic-pr';</script>";
            exit;
        }
        $pdo->prepare("UPDATE purchase_requests SET status='submitted' WHERE id=? AND status='draft'")->execute([$id]);
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'ppic.pr.submit',
                'Approval PR Baru',
                "PR #$id diajukan dan menunggu approval.",
                "index.php?page=ppic-pr&status=submitted",
                'warning',
                ['permission_slug' => 'ppic_pr_approve']
            );
        }
        echo "<script>alert('PR diajukan.'); window.location='index.php?page=ppic-pr';</script>";
    }

    if ($action == 'approve') {
        if (!has_permission('ppic_pr_approve')) die("<script>alert('Akses Ditolak!'); window.history.back();</script>");
        $pdo->prepare("UPDATE purchase_requests SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?")->execute([$uid, $id]);
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'ppic.pr.approve',
                'PR Disetujui',
                "PR #$id disetujui dan siap diproses Purchasing.",
                "index.php?page=purch-po&action=create&pr_id=$id",
                'success',
                ['permission_slug' => 'purch_po_manage']
            );
        }
        echo "<script>alert('PR disetujui.'); window.location='index.php?page=ppic-pr';</script>";
    }
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-cart-check"></i> Purchase Requests (PR)</h3>
        <p class="text-muted">Manajemen permintaan pembelian material produksi.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=ppic-pr&action=create" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-lg"></i> Buat PR Baru
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="pr-filter-form">
            <input type="hidden" name="page" value="ppic-pr">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari No. PR / Keperluan / Pembuat..." value="<?= $search_key ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="draft" <?= $filter_status=='draft'?'selected':'' ?>>Draft</option>
                    <option value="submitted" <?= $filter_status=='submitted'?'selected':'' ?>>Submitted</option>
                    <option value="approved" <?= $filter_status=='approved'?'selected':'' ?>>Approved</option>
                    <option value="partial" <?= $filter_status=='partial'?'selected':'' ?>>Partial</option>
                    <option value="processed" <?= $filter_status=='processed'?'selected':'' ?>>Processed</option>
                    <option value="rejected" <?= $filter_status=='rejected'?'selected':'' ?>>Rejected</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=ppic-pr" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
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
                        <th>No. PR</th>
                        <th>Tanggal</th>
                        <th>Tujuan / Keperluan</th>
                        <th>Status</th>
                        <th class="text-center" width="220">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Ambil semua kolom header PR dari schema aktif.
                    $sql = "SELECT pr.*, u.fullname as creator_name 
                            FROM purchase_requests pr
                            LEFT JOIN users u ON pr.created_by = u.id
                            WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND pr.status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (pr.pr_number LIKE ? OR pr.notes LIKE ? OR u.fullname LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY pr.id DESC";
                    
                    try {
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        while ($row = $stmt->fetch()):
                            $badge = match($row['status']) {
                                'draft'            => 'bg-secondary',
                                'submitted'        => 'bg-warning text-dark',
                                'approved'         => 'bg-success',
                                'partial'          => 'bg-info text-dark',
                                'processed'        => 'bg-dark',
                                'rejected'         => 'bg-danger',
                                default            => 'bg-secondary'
                            };
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['pr_number']) ?></strong></td>
                        <td><?= !empty($row['pr_date']) ? date('d/m/Y', strtotime($row['pr_date'])) : '-' ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['notes'] ?? '-') ?></div>
                            <small class="text-muted fst-italic">Oleh: <?= htmlspecialchars($row['creator_name'] ?? 'System') ?></small>
                        </td>
                        <td><span class="badge <?= $badge ?>"><?= ucwords(str_replace('_',' ',$row['status'])) ?></span></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="modules/ppic/purchase_requests/print.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Cetak"><i class="bi bi-printer"></i></a>

                                <?php if(in_array($row['status'], ['draft', 'rejected'])): ?>
                                    <a href="index.php?page=ppic-pr&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <a href="index.php?page=ppic-pr&action=submit&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Ajukan PR ini?')"><i class="bi bi-send"></i></a>
                                <?php endif; ?>

                                <?php if(in_array($row['status'], ['draft', 'rejected']) || $_SESSION['role'] === 'admin'): ?>
                                    <a href="index.php?page=ppic-pr&action=delete&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus data PR ini secara permanen?')"><i class="bi bi-trash"></i></a>
                                <?php endif; ?>

                                <?php if($row['status'] == 'submitted' && has_permission('ppic_pr_approve')): ?>
                                    <a href="index.php?page=ppic-pr&action=approve&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-primary">Approve</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php 
                        endwhile; 
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='5' class='text-center text-danger fw-bold'>Gagal memuat data.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('pr-filter-form');
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
