<?php
// modules/qc/ncr/index.php
if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('qc_ncr_view')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=dashboard';</script>";
    exit;
}

render_header("Non-Conformance Report (NCR)");

$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

// Action: Tanda tangan penanggung jawab (operator)
if (isset($_GET['action']) && $_GET['action'] == 'sign-resp' && isset($_GET['id'])) {
    $csrf_req = $_GET['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    if (!has_permission('qc_ncr_resp_approve')) {
        echo "<script>alert('Akses Ditolak.'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    $stmt_ncr = $pdo->prepare("SELECT id, status, operator_id FROM ncr WHERE id = ?");
    $stmt_ncr->execute([$_GET['id']]);
    $ncr = $stmt_ncr->fetch();
    if (!$ncr) {
        echo "<script>alert('NCR tidak ditemukan.'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    if ($ncr['status'] !== 'waiting_responsible') {
        echo "<script>alert('NCR ini tidak dalam status menunggu tanda tangan.'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    if (!empty($ncr['operator_id']) && (int)$ncr['operator_id'] !== (int)$_SESSION['user_id']) {
        echo "<script>alert('Hanya penanggung jawab yang ditunjuk yang boleh menandatangani.'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    $pdo->prepare("UPDATE ncr SET status='waiting_gm', resp_signed_by=?, resp_signed_at=NOW() WHERE id=?")
        ->execute([$_SESSION['user_id'], $_GET['id']]);

    if (function_exists('notify_workflow_event')) {
        notify_workflow_event(
            'qc.ncr.wait_gm.' . (int)$_GET['id'],
            'NCR Menunggu Approval GM',
            "NCR #" . (int)$_GET['id'] . " sudah ditandatangani penanggung jawab. Mohon approval GM.",
            "index.php?page=qc-ncr&action=edit&id=" . (int)$_GET['id'],
            'warning',
            ['permission_slug' => 'qc_ncr_approve']
        );
    }
    echo "<script>alert('Tanda tangan penanggung jawab berhasil.'); window.location='index.php?page=qc-ncr';</script>";
    exit;
}

// Action: Banding penanggung jawab (kembali ke QC)
if (isset($_GET['action']) && $_GET['action'] == 'appeal' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    if (!has_permission('qc_ncr_resp_approve')) {
        echo "<script>alert('Akses Ditolak.'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    $note = trim((string)($_POST['appeal_note'] ?? ''));
    if ($note === '') {
        echo "<script>alert('Isi alasan banding.'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    $stmt_ncr = $pdo->prepare("SELECT id, status, operator_id FROM ncr WHERE id = ?");
    $stmt_ncr->execute([$_GET['id']]);
    $ncr = $stmt_ncr->fetch();
    if (!$ncr) {
        echo "<script>alert('NCR tidak ditemukan.'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    if ($ncr['status'] !== 'waiting_responsible') {
        echo "<script>alert('NCR ini tidak dalam status menunggu penanggung jawab.'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    if (!empty($ncr['operator_id']) && (int)$ncr['operator_id'] !== (int)$_SESSION['user_id']) {
        echo "<script>alert('Hanya penanggung jawab yang ditunjuk yang boleh banding.'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    $pdo->prepare("UPDATE ncr SET status='appealed', resp_appeal_by=?, resp_appeal_at=NOW(), resp_appeal_note=? WHERE id=?")
        ->execute([$_SESSION['user_id'], $note, $_GET['id']]);

    if (function_exists('notify_workflow_event')) {
        notify_workflow_event(
            'qc.ncr.appeal.' . (int)$_GET['id'],
            'NCR Dibanding',
            "Penanggung jawab mengajukan banding untuk NCR #" . (int)$_GET['id'] . ".",
            "index.php?page=qc-ncr&action=edit&id=" . (int)$_GET['id'],
            'warning',
            ['permission_slug' => 'qc_ncr_manage']
        );
    }
    echo "<script>alert('Banding terkirim ke QC.'); window.location='index.php?page=qc-ncr';</script>";
    exit;
}

// Action: Assign penanggung jawab
if (isset($_GET['action']) && $_GET['action'] == 'assign-resp' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    if (!has_permission('qc_ncr_manage')) {
        echo "<script>alert('Akses Ditolak.'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    $op_id = (int)($_POST['operator_id'] ?? 0);
    if ($op_id <= 0) {
        echo "<script>alert('Pilih operator terlebih dahulu.'); window.location='index.php?page=dashboard';</script>";
        exit;
    }
    $stmt_ncr = $pdo->prepare("SELECT id, status FROM ncr WHERE id = ?");
    $stmt_ncr->execute([$_GET['id']]);
    $ncr = $stmt_ncr->fetch();
    if (!$ncr) {
        echo "<script>alert('NCR tidak ditemukan.'); window.location='index.php?page=dashboard';</script>";
        exit;
    }
    if ($ncr['status'] !== 'waiting_responsible') {
        echo "<script>alert('NCR ini tidak dalam status menunggu penanggung jawab.'); window.location='index.php?page=dashboard';</script>";
        exit;
    }
    $pdo->prepare("UPDATE ncr SET operator_id=? WHERE id=?")->execute([$op_id, $_GET['id']]);

    if (function_exists('notify_workflow_event')) {
        notify_workflow_event(
            'qc.ncr.assign_resp.' . (int)$_GET['id'],
            'NCR Ditugaskan',
            "Anda ditunjuk sebagai penanggung jawab NCR #" . (int)$_GET['id'] . ".",
            'index.php?page=dashboard',
            'warning',
            ['user_ids' => [(int)$op_id], 'include_admin' => false, 'exclude_sender' => false]
        );
    }
    echo "<script>alert('Penanggung jawab berhasil ditugaskan.'); window.location='index.php?page=dashboard';</script>";
    exit;
}

// Action: Approve
if (isset($_GET['action']) && $_GET['action'] == 'approve' && isset($_GET['id'])) {
    $csrf_req = $_GET['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    if (has_permission('qc_ncr_approve')) {
        $stmt_ncr = $pdo->prepare("SELECT ncr.*, qc.spk_id, i.item_code, i.item_name 
                                   FROM ncr 
                                   LEFT JOIN qc_production qc ON qc.id = ncr.reference_id
                                   LEFT JOIN items i ON ncr.item_id = i.id
                                   WHERE ncr.id = ?");
        $stmt_ncr->execute([$_GET['id']]);
        $ncr = $stmt_ncr->fetch();

        if (!$ncr) {
            echo "<script>alert('NCR tidak ditemukan.'); window.location='index.php?page=qc-ncr';</script>";
            exit;
        }

        if (!in_array($ncr['status'], ['waiting_gm'])) {
            echo "<script>alert('NCR ini tidak dalam status menunggu approval GM.'); window.location='index.php?page=qc-ncr';</script>";
            exit;
        }

        if (empty($ncr['resp_signed_by']) || empty($ncr['resp_signed_at'])) {
            echo "<script>alert('NCR belum ditandatangani penanggung jawab.'); window.location='index.php?page=qc-ncr';</script>";
            exit;
        }

        if ($ncr['disposition'] === 'pending' || empty($ncr['disposition'])) {
            echo "<script>alert('Disposisi belum ditentukan (Repair/Scrap). Silakan isi terlebih dahulu.'); window.location='index.php?page=qc-ncr&action=edit&id=".(int)$ncr['id']."';</script>";
            exit;
        }

        $pdo->prepare("UPDATE ncr SET status='approved', gm_approved_by=?, gm_approved_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $_GET['id']]);

        // Buat tugas produksi untuk rework/scrap (jika perlu)
        if (!empty($ncr['spk_id']) && in_array($ncr['disposition'], ['repair', 'scrap'])) {
            $spk_id = (int)$ncr['spk_id'];
            $ncr_label = !empty($ncr['ncr_number']) ? $ncr['ncr_number'] : ('NCR-' . (int)$ncr['id']);
            $item_label = trim(($ncr['item_code'] ?? '') . ' ' . ($ncr['item_name'] ?? ''));
            $proc_name = ($ncr['disposition'] === 'repair')
                ? "NCR {$ncr_label} - REPAIR {$item_label}"
                : "NCR {$ncr_label} - SCRAP {$item_label}";
            $proc_name = trim($proc_name);

            $stmt_chk = $pdo->prepare("SELECT id FROM production_assignments WHERE spk_id = ? AND process_name = ? LIMIT 1");
            $stmt_chk->execute([$spk_id, $proc_name]);
            $exist = $stmt_chk->fetchColumn();
            if (!$exist) {
                $pdo->prepare("INSERT INTO production_assignments (spk_id, process_name, operator_id, status) VALUES (?, ?, NULL, 'assigned')")
                    ->execute([$spk_id, $proc_name]);
            }

            if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'prod.ncr.task.' . (int)$ncr['id'],
                    'Tugas NCR untuk Produksi',
                    "NCR disetujui GM. Silakan buat penugasan untuk proses: " . $proc_name,
                    'index.php?page=prod-task&spk_id=' . (int)$spk_id,
                    'warning',
                    ['permission_slug' => 'prod_task_manage']
                );
            }
        }

        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'qc.ncr.approve.' . (int)$_GET['id'],
                'NCR Disetujui',
                "NCR #" . (int)$_GET['id'] . " disetujui GM. Lanjutkan eksekusi disposisi.",
                "index.php?page=qc-ncr&action=edit&id=" . (int)$_GET['id'],
                'warning',
                ['permission_slug' => 'qc_ncr_manage']
            );
        }
        echo "<script>alert('NCR Disetujui. Silakan eksekusi (Repair/Scrap).'); window.location='index.php?page=qc-ncr';</script>";
    }
}

// Action: Close
if (isset($_GET['action']) && $_GET['action'] == 'close' && isset($_GET['id'])) {
    $csrf_req = $_GET['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    if (!has_permission('qc_ncr_manage')) {
        echo "<script>alert('Akses Ditolak.'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    $stmt_ncr = $pdo->prepare("SELECT id, status FROM ncr WHERE id = ?");
    $stmt_ncr->execute([$_GET['id']]);
    $ncr = $stmt_ncr->fetch();
    if (!$ncr) {
        echo "<script>alert('NCR tidak ditemukan.'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    if ($ncr['status'] !== 'approved') {
        echo "<script>alert('NCR ini belum disetujui GM.'); window.location='index.php?page=qc-ncr';</script>";
        exit;
    }
    $pdo->prepare("UPDATE ncr SET status='closed' WHERE id=?")->execute([$_GET['id']]);
    if (function_exists('notify_workflow_event')) {
        notify_workflow_event(
            'qc.ncr.close.' . (int)$_GET['id'],
            'NCR Ditutup',
            "Kasus NCR #" . (int)$_GET['id'] . " sudah ditutup.",
            "index.php?page=qc-ncr",
            'success',
            ['target_roles' => ['manager', 'executive']]
        );
    }
    echo "<script>alert('Kasus NCR Ditutup.'); window.location='index.php?page=qc-ncr';</script>";
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold text-danger"><i class="bi bi-exclamation-octagon-fill"></i> Laporan Ketidaksesuaian (NCR)</h3>
        <p class="text-muted">Manajemen produk reject dan analisis perbaikan.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=qc-ncr&action=create" class="btn btn-danger">
            <i class="bi bi-plus-lg"></i> Buat NCR Manual
        </a>
    </div>
</div>

<div class="card shadow-sm border-start border-4 border-danger">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No. NCR</th>
                        <th>Tanggal</th>
                        <th>Sumber</th>
                        <th>Barang Reject</th>
                        <th>Isu / Masalah</th>
                        <th>Disposisi</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT ncr.*, i.item_name, i.item_code 
                            FROM ncr 
                            JOIN items i ON ncr.item_id = i.id 
                            ORDER BY ncr.id DESC";
                    $stmt = $pdo->query($sql);
                    
                    while ($row = $stmt->fetch()):
                        $badge = match($row['status']) {
                            'open' => 'bg-danger',
                            'analyzed' => 'bg-warning text-dark',
                            'waiting_responsible' => 'bg-warning text-dark',
                            'waiting_gm' => 'bg-warning text-dark',
                            'appealed' => 'bg-danger',
                            'approved' => 'bg-primary',
                            'closed' => 'bg-success',
                            default => 'bg-light'
                        };
                        $status_label = match($row['status']) {
                            'waiting_responsible' => 'Menunggu Penanggung Jawab',
                            'waiting_gm' => 'Menunggu GM',
                            'appealed' => 'Banding ke QC',
                            'open' => 'Open',
                            'analyzed' => 'Analyzed',
                            'approved' => 'Approved',
                            'closed' => 'Closed',
                            default => strtoupper((string)$row['status'])
                        };
                        $disp_badge = match($row['disposition']) {
                            'repair' => 'bg-info text-dark',
                            'scrap' => 'bg-dark',
                            'return_to_vendor' => 'bg-secondary',
                            default => 'bg-light text-muted border'
                        };
                    ?>
                    <tr>
                        <td><strong><?= clean($row['ncr_number']) ?></strong></td>
                        <td><?= date('d/m/y', strtotime($row['created_at'])) ?></td>
                        <td><?= strtoupper($row['source_type']) ?></td>
                        <td>
                            <strong class="text-danger"><?= clean($row['qty_reject']) + 0 ?></strong> <?= clean($row['item_code']) ?><br>
                            <small><?= clean($row['item_name']) ?></small>
                        </td>
                        <td><small><?= mb_strimwidth(clean($row['issue_description']), 0, 30, "...") ?></small></td>
                        <td><span class="badge <?= $disp_badge ?>"><?= strtoupper(str_replace('_',' ',$row['disposition'] ?? '-')) ?></span></td>
                        <td><span class="badge <?= $badge ?>"><?= $status_label ?></span></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <!-- Print -->
                                <a href="index.php?page=qc-ncr&action=print&id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Cetak NCR"><i class="bi bi-printer"></i></a>

                                <!-- Edit / Analisa -->
                                <?php if(in_array($row['status'], ['open','analyzed','waiting_responsible'])): ?>
                                    <a href="index.php?page=qc-ncr&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning text-dark" title="Analisa / Edit"><i class="bi bi-pencil-square"></i></a>
                                <?php endif; ?>

                                <!-- Tanda tangan penanggung jawab -->
                                <?php if($row['status'] == 'waiting_responsible' && has_permission('qc_ncr_resp_approve')): ?>
                                    <a href="index.php?page=qc-ncr&action=sign-resp&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-info text-white" title="Tanda Tangan Penanggung Jawab" onclick="return confirm('Tanda tangan sebagai penanggung jawab?')">
                                        <i class="bi bi-pen"></i>
                                    </a>
                                <?php endif; ?>

                                <!-- Approve (Manager) -->
                                <?php if(in_array($row['status'], ['waiting_gm']) && has_permission('qc_ncr_approve')): ?>
                                    <a href="index.php?page=qc-ncr&action=approve&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-success" title="Approve GM" onclick="return confirm('Setujui langkah perbaikan ini?')"><i class="bi bi-check-lg"></i></a>
                                <?php endif; ?>

                                <!-- Close (Setelah selesai) -->
                                <?php if($row['status'] == 'approved' && has_permission('qc_ncr_manage')): ?>
                                    <a href="index.php?page=qc-ncr&action=close&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-dark" title="Tutup Kasus" onclick="return confirm('Tutup kasus NCR ini?')"><i class="bi bi-archive"></i></a>
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

<?php render_footer(); ?>
