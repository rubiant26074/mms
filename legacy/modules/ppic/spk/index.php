<?php
// modules/ppic/spk/index.php
require_once __DIR__ . '/service.php';
render_header("SPK Produksi");
ppic_spk_ensure_schema($pdo);
$csrf = mms_csrf_token();

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';

// LOGIKA ACTION APPROVAL & RILIS
if (isset($_GET['action']) && isset($_GET['id'])) {
    $csrfReq = $_GET['csrf'] ?? $_POST['csrf'] ?? '';
    if (!verify_mms_csrf_token($csrfReq)) {
        echo "<script>alert('Token keamanan tidak valid.'); window.location='index.php?page=ppic-spk';</script>";
        exit;
    }

    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $role = $_SESSION['role'] ?? '';
    if ($id <= 0 || $uid <= 0) {
        echo "<script>alert('Request tidak valid.'); window.location='index.php?page=ppic-spk';</script>";
        exit;
    }
    $stmt_spk = $pdo->prepare("SELECT spk_number FROM spk WHERE id = ?");
    $stmt_spk->execute([$id]);
    $spk_number = $stmt_spk->fetchColumn() ?: ('#' . $id);

    // 1. Submit PPIC ke Engineering
    if ($action == 'submit') {
        $pdo->prepare("UPDATE spk SET status='waiting_eng' WHERE id=? AND status='draft'")->execute([$id]);
        if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'ppic.spk.submit',
                    'SPK Menunggu Engineering',
                    "SPK {$spk_number} diajukan ke Engineering untuk proses partlist/drawing.",
                    "index.php?page=eng-partlist&action=create&spk_id={$id}",
                'warning',
                ['permission_slug' => 'eng_partlist_manage']
            );
        }
        echo "<script>alert('SPK diajukan ke Engineering.'); window.location='index.php?page=ppic-spk';</script>";
    }

    // 2. Rilis Manager (Menandatangani Kolom 3)
    if ($action == 'approve_mgr' && ($role == 'manager' || $role == 'admin')) {
        try {
            $pdo->beginTransaction();

            // Pastikan transisi status valid agar tidak double process.
            $stmt_release = $pdo->prepare("UPDATE spk SET status='released', approved_by_mgr=?, approved_at_mgr=NOW() WHERE id=? AND status IN ('waiting_mgr','final')");
            $stmt_release->execute([$uid, $id]);
            if ($stmt_release->rowCount() === 0) {
                throw new Exception("SPK tidak dalam status waiting_mgr/final atau sudah diproses.");
            }

            // Ambil data SPK untuk kebutuhan auto PR.
            $stmt_spk_info = $pdo->prepare("SELECT deadline_date FROM spk WHERE id=?");
            $stmt_spk_info->execute([$id]);
            $spk_info = $stmt_spk_info->fetch();
            $required_date = $spk_info && !empty($spk_info['deadline_date']) ? $spk_info['deadline_date'] : date('Y-m-d');

            // Hitung kekurangan stok berdasarkan kebutuhan material SPK.
            $stmt_short = $pdo->prepare(
                "SELECT sm.item_id, i.item_code, i.item_name, i.current_stock,
                        SUM(sm.qty_required) AS qty_needed,
                        GREATEST(SUM(sm.qty_required) - IFNULL(i.current_stock, 0), 0) AS qty_short
                 FROM spk_materials sm
                 JOIN items i ON i.id = sm.item_id
                 WHERE sm.spk_id = ?
                   AND (i.ownership IS NULL OR i.ownership = 'internal')
                 GROUP BY sm.item_id, i.item_code, i.item_name, i.current_stock
                 HAVING qty_short > 0.0001"
            );
            $stmt_short->execute([$id]);
            $shortages = $stmt_short->fetchAll(PDO::FETCH_ASSOC);

            $auto_pr_created = false;
            $auto_pr_number = null;
            $auto_pr_id = null;
            $auto_pr_existing = null;
            $auto_pr_existing_id = null;

            if (!empty($shortages)) {
                $auto_tag = "[AUTO-SPK-ID:$id]";

                // Cegah duplikasi PR otomatis untuk SPK yang sama.
                $stmt_existing = $pdo->prepare("SELECT id, pr_number FROM purchase_requests WHERE notes LIKE ? AND status IN ('draft','submitted','approved','partial') ORDER BY id DESC LIMIT 1");
                $stmt_existing->execute(["%$auto_tag%"]);
                $existing_pr = $stmt_existing->fetch();

                if (!$existing_pr) {
                    // Generate nomor PR otomatis.
                    $ym = date('ym');
                    $prLockName = 'mms.pr.number.' . $ym;
                    ppic_spk_acquire_lock($pdo, $prLockName, 10);
                    try {
                        $stmt_no = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE pr_number LIKE 'PR-$ym-%'");
                        $count = (int)$stmt_no->fetchColumn() + 1;
                        $pr_number = "PR-" . $ym . "-" . str_pad((string)$count, 4, '0', STR_PAD_LEFT);

                        $pr_notes = "AUTO-GENERATE dari SPK: {$spk_number} $auto_tag";
                        $stmt_pr = $pdo->prepare("INSERT INTO purchase_requests (pr_number, pr_date, required_date, notes, status, created_by, approved_by, approved_at) VALUES (?, CURDATE(), ?, ?, 'approved', ?, ?, NOW())");
                        $stmt_pr->execute([$pr_number, $required_date, $pr_notes, $uid, $uid]);
                        $pr_id = $pdo->lastInsertId();
                    } finally {
                        ppic_spk_release_lock($pdo, $prLockName);
                    }

                    $stmt_pr_item = $pdo->prepare("INSERT INTO purchase_request_items (purchase_request_id, item_id, qty, notes) VALUES (?, ?, ?, ?)");
                    foreach ($shortages as $s) {
                        $qty_short = (float)$s['qty_short'];
                        $stmt_pr_item->execute([
                            $pr_id,
                            $s['item_id'],
                            $qty_short,
                            "Auto shortage from {$spk_number} | Need: " . ((float)$s['qty_needed']) . " | Stock: " . ((float)$s['current_stock'])
                        ]);
                    }

                    $auto_pr_created = true;
                    $auto_pr_number = $pr_number;
                    $auto_pr_id = (int)$pr_id;
                } else {
                    $auto_pr_existing = $existing_pr['pr_number'] ?? null;
                    $auto_pr_existing_id = isset($existing_pr['id']) ? (int)$existing_pr['id'] : null;
                }
            }

            $pdo->commit();

            if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'ppic.spk.release_mgr',
                    'SPK Dirilis ke Produksi',
                    "SPK {$spk_number} telah dirilis manager. Menunggu penerimaan Supervisor Produksi.",
                    "index.php?page=ppic-spk&action=receive_spv&id={$id}",
                    'info',
                    ['permission_slug' => 'prod_task_manage']
                );
            }

            if ($auto_pr_created && function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'ppic.spk.auto_pr',
                    'PR Otomatis dari SPK',
                    "Sistem membuat PR {$auto_pr_number} karena kekurangan material untuk SPK {$spk_number}.",
                    "index.php?page=ppic-pr&action=edit&id={$auto_pr_id}",
                    'warning',
                    ['permission_slug' => 'purch_po_manage']
                );
            }

            $msg = $auto_pr_created
                ? "SPK Dirilis. Kekurangan stok terdeteksi, PR otomatis ({$auto_pr_number}) sudah dibuat untuk Purchasing."
                : (($auto_pr_existing)
                    ? "SPK Dirilis. Kekurangan stok terdeteksi, PR otomatis sebelumnya ({$auto_pr_existing}) sudah ada."
                    : "SPK Dirilis ke Produksi.");
            $target_pr_id = $auto_pr_created ? $auto_pr_id : $auto_pr_existing_id;
            if (!empty($target_pr_id)) {
                $confirm_text = $msg . " Buka PR sekarang?";
                $confirm_js = json_encode($confirm_text, JSON_UNESCAPED_UNICODE);
                echo "<script>
                        const confirmText = $confirm_js;
                        if (typeof window.appConfirm === 'function') {
                            let handled = false;
                            const modalEl = document.getElementById('appConfirmModal');
                            if (modalEl) {
                                modalEl.addEventListener('hidden.bs.modal', () => {
                                    if (!handled) {
                                        window.location='index.php?page=ppic-spk';
                                    }
                                }, { once: true });
                            }
                            window.appConfirm(confirmText, () => {
                                handled = true;
                                window.location='index.php?page=ppic-pr&action=edit&id={$target_pr_id}';
                            });
                        } else if (confirm(confirmText)) {
                            window.location='index.php?page=ppic-pr&action=edit&id={$target_pr_id}';
                        } else {
                            window.location='index.php?page=ppic-spk';
                        }
                      </script>";
            } else {
                echo "<script>alert('{$msg}'); window.location='index.php?page=ppic-spk';</script>";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[PPIC-SPK approve_mgr] ' . $e->getMessage());
            echo "<script>alert('Gagal rilis SPK. Silakan coba lagi.'); window.location='index.php?page=ppic-spk';</script>";
        }
    }

    // 3. Terima Supervisor (Menandatangani Kolom 4)
    if ($action == 'receive_spv' && ($role == 'supervisor' || $role == 'admin')) {
        $pdo->prepare("UPDATE spk SET status='in_production', approved_by_spv=?, approved_at_spv=NOW() WHERE id=?")->execute([$uid, $id]);
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'ppic.spk.receive_spv',
                'SPK Diterima Supervisor',
                "SPK {$spk_number} sudah diterima supervisor. Lanjutkan pembagian tugas operator per proses.",
                "index.php?page=prod-task&action=manage&spk_id={$id}",
                'success',
                ['permission_slug' => 'prod_task_manage']
            );
        }
        echo "<script>alert('SPK Diterima Supervisor Workshop.'); window.location='index.php?page=ppic-spk';</script>";
    }
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-clipboard-data"></i> Surat Perintah Kerja (SPK)</h3>
        <p class="text-muted">Workflow: PPIC → Engineering → Prod Manager → Prod Supervisor</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=ppic-spk&action=create" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Buat SPK Baru</a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="spk-filter-form">
            <input type="hidden" name="page" value="ppic-spk">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari No SPK / Customer / SO..." value="<?= $search_key ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="draft" <?= $filter_status=='draft'?'selected':'' ?>>Draft</option>
                    <option value="waiting_eng" <?= $filter_status=='waiting_eng'?'selected':'' ?>>Waiting Eng</option>
                    <option value="waiting_mgr" <?= $filter_status=='waiting_mgr'?'selected':'' ?>>Waiting Mgr</option>
                    <option value="final" <?= $filter_status=='final'?'selected':'' ?>>Final (Menunggu Mgr)</option>
                    <option value="released" <?= $filter_status=='released'?'selected':'' ?>>Released</option>
                    <option value="in_production" <?= $filter_status=='in_production'?'selected':'' ?>>In Production</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=ppic-spk" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
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
                        <th>No. SPK</th>
                        <th>Target</th>
                        <th>Customer / Project</th>
                        <th>Status</th>
                        <th class="text-center" width="300">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT s.*, so.so_number, c.name as customer_name FROM spk s 
                            LEFT JOIN sales_orders so ON s.sales_order_id = so.id
                            LEFT JOIN customers c ON so.customer_id = c.id WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND s.status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (s.spk_number LIKE ? OR c.name LIKE ? OR so.so_number LIKE ? OR s.project_name LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY s.id DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    while ($row = $stmt->fetch()):
                        $badge = match($row['status']) {
                            'draft'         => 'bg-secondary',
                            'waiting_eng'   => 'bg-warning text-dark',
                            'waiting_mgr'   => 'bg-info',
                            'final'         => 'bg-info',
                            'released'      => 'bg-primary',
                            'in_production' => 'bg-success',
                            default         => 'bg-secondary'
                        };
                    ?>
                    <tr>
                        <td><strong><?= $row['spk_number'] ?></strong></td>
                        <td><?= date('d M Y', strtotime($row['deadline_date'])) ?></td>
                        <td>
                            <div class="fw-bold"><?= $row['customer_name'] ?></div>
                            <small class="text-muted">Ref SO: <?= $row['so_number'] ?></small>
                        </td>
                        <td><span class="badge <?= $badge ?>"><?= strtoupper($row['status']) ?></span></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="modules/ppic/spk/print.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer"></i></a>
                                
                                <?php if($row['status'] == 'draft'): ?>
                                    <a href="index.php?page=ppic-spk&action=submit&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-send"></i></a>
                                <?php endif; ?>

                                <?php if(in_array($row['status'], ['waiting_mgr','final'], true) && ($_SESSION['role'] == 'manager' || $_SESSION['role'] == 'admin')): ?>
                                    <a href="index.php?page=ppic-spk&action=approve_mgr&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-success fw-bold">RILIS MANAGER</a>
                                <?php endif; ?>

                                <?php if($row['status'] == 'released' && ($_SESSION['role'] == 'supervisor' || $_SESSION['role'] == 'admin')): ?>
                                    <a href="index.php?page=ppic-spk&action=receive_spv&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-warning fw-bold">TERIMA WS</a>
                                <?php endif; ?>

                                <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                                    <a href="index.php?page=ppic-spk&action=delete&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Hapus SPK <?= addslashes($row['spk_number']) ?>? Tindakan ini tidak bisa dibatalkan.')">
                                        <i class="bi bi-trash"></i>
                                    </a>
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
    const form = document.getElementById('spk-filter-form');
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
