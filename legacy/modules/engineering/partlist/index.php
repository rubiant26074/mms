<?php
// modules/engineering/partlist/index.php
render_header("Engineering Partlist & Drawing");
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';

// ACTION: APPROVE (Manager Engineering)
if (isset($_GET['action']) && $_GET['action'] == 'approve' && isset($_GET['id'])) {
    if (!has_permission('eng_partlist_approve')) die("Akses Ditolak: Butuh Approval Manager.");
    $csrf_req = $_GET['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=eng-partlist';</script>";
        exit;
    }
    $approve_id = (int)$_GET['id'];
    $stmt_spk_no = $pdo->prepare("SELECT spk_number FROM spk WHERE id=? LIMIT 1");
    $stmt_spk_no->execute([$approve_id]);
    $spk_number = (string)($stmt_spk_no->fetchColumn() ?: ('#' . $approve_id));
    
    // Update SPK jadi FINAL
    $pdo->prepare("UPDATE spk SET status='final' WHERE id=?")->execute([$approve_id]);
    
    // Notif ke General Manager & Manager Produksi (rilis SPK)
    if (function_exists('notify_workflow_event')) {
        notify_workflow_event(
            'engineering.partlist.final.' . (int)$approve_id,
            'Partlist Final - Menunggu Rilis Manager',
            "Engineering selesai membuat partlist untuk SPK {$spk_number}. Mohon approval/rilis General Manager / Manager Produksi.",
            "index.php?page=ppic-spk&action=approve_mgr&id=" . $approve_id,
            'success',
            [
                'permission_slug' => 'ppic_spk_approve_mgr',
                'target_roles' => ['manager'],
                'include_admin' => false,
                'ttl_seconds' => 86400,
            ]
        );
    }
    echo "<script>alert('Partlist Disetujui! SPK Status: FINAL'); window.location='index.php?page=eng-partlist';</script>";
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-gear-wide-connected"></i> Engineering Partlist</h3>
        <p class="text-muted">Manajemen gambar teknik dan rincian komponen produksi.</p>
    </div>
</div>

<div class="card shadow-sm">
    <?php
    $view_mode = isset($_GET['view']) && $_GET['view'] === 'archive' ? 'archive' : 'active';
    $active_statuses = "'preliminary', 'final', 'waiting_eng', 'waiting_mgr', 'released', 'in_production', 'completed'";
    $archive_statuses = "'closed'";
    $status_filter = $view_mode === 'archive' ? $archive_statuses : $active_statuses;
    $status_options = $view_mode === 'archive'
        ? ['closed' => 'Closed']
        : [
            'preliminary' => 'Preliminary',
            'final' => 'Final',
            'waiting_eng' => 'Waiting Eng',
            'waiting_mgr' => 'Waiting Mgr',
            'released' => 'Released',
            'in_production' => 'In Production',
            'completed' => 'Completed',
        ];
    ?>
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-center" id="partlist-filter-form">
            <input type="hidden" name="page" value="eng-partlist">
            <input type="hidden" name="view" value="<?= $view_mode ?>">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari No SPK / SO..." value="<?= $search_key ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <?php foreach ($status_options as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $filter_status==$val?'selected':'' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=eng-partlist&view=<?= $view_mode ?>" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?= $view_mode === 'archive' ? 'Arsip Partlist Engineering' : 'Daftar Partlist Engineering Aktif' ?></h5>
        <div>
            <a href="index.php?page=eng-partlist&view=active" class="btn btn-sm <?= $view_mode === 'active' ? 'btn-light text-primary fw-bold' : 'btn-outline-light' ?>">
                Aktif
            </a>
            <a href="index.php?page=eng-partlist&view=archive" class="btn btn-sm <?= $view_mode === 'archive' ? 'btn-light text-primary fw-bold' : 'btn-outline-light' ?>">
                Arsip (Closed)
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>No SPK</th>
                        <th>Project (Klik untuk Print SPK)</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT s.*, so.so_number 
                            FROM spk s 
                            JOIN sales_orders so ON s.sales_order_id=so.id 
                            WHERE s.status IN ($status_filter)";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND s.status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (s.spk_number LIKE ? OR so.so_number LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY s.id DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    if (!$stmt->rowCount()):
                    ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <?= $view_mode === 'archive' ? 'Belum ada data arsip partlist.' : 'Belum ada data partlist aktif.' ?>
                        </td>
                    </tr>
                    <?php
                    endif;
                    while($row = $stmt->fetch()):
                        $badge = match($row['status']) {
                            'final', 'released' => 'bg-success',
                            'in_production' => 'bg-primary',
                            'completed' => 'bg-dark',
                            'closed' => 'bg-secondary',
                            'waiting_eng' => 'bg-danger',
                            default => 'bg-warning text-dark'
                        };
                    ?>
                    <tr>
                        <td class="fw-bold"><?= clean($row['spk_number']) ?></td>
                        <td>
                            <a href="modules/ppic/spk/print.php?id=<?= (int)$row['id'] ?>" target="_blank" class="text-decoration-none fw-bold text-primary">
                                <i class="bi bi-printer me-1"></i> SO: <?= clean($row['so_number']) ?>
                            </a>
                        </td>
                        <td><?= date('d M Y', strtotime($row['deadline_date'])) ?></td>
                        <td><span class="badge <?= $badge ?>"><?= strtoupper($row['status']) ?></span></td>
                        <td class="text-center">
                            <?php if ($row['status'] === 'closed'): ?>
                                <a href="index.php?page=eng-partlist&action=print&id=<?= (int)$row['id'] ?>" target="_blank" class="btn btn-sm btn-secondary shadow-sm">
                                    <i class="bi bi-printer"></i> Lihat Arsip
                                </a>
                            <?php elseif($row['status'] === 'waiting_mgr' && has_permission('eng_partlist_approve')): ?>
                                <a href="index.php?page=eng-partlist&action=approve&id=<?= (int)$row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-success shadow-sm" onclick="return confirm('Approve Partlist ini menjadi FINAL?')">
                                    <i class="bi bi-check2-circle"></i> Approve Final
                                </a>
                            <?php elseif(in_array($row['status'], ['preliminary', 'waiting_eng'])): ?>
                                <a href="index.php?page=eng-partlist&action=create&spk_id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-primary shadow-sm">
                                    <i class="bi bi-pencil-square"></i> Buat Partlist
                                </a>
                            <?php else: ?>
                                <a href="index.php?page=eng-partlist&action=create&spk_id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-info text-white shadow-sm">
                                    <i class="bi bi-eye"></i> Lihat / Revisi
                                </a>
                            <?php endif; ?>
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
    const form = document.getElementById('partlist-filter-form');
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
