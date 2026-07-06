<?php
// modules/production/report/index.php
render_header("Laporan Hasil Produksi");

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$activity = isset($_GET['activity']) ? trim((string)$_GET['activity']) : 'all';
$allowed_activity = ['all', 'start', 'hold', 'resume', 'finish'];
if (!in_array($activity, $allowed_activity)) $activity = 'all';

// Query: Aktivitas produksi (start/hold/resume/finish) pada tanggal terpilih
$sql = "SELECT pl.*, pa.process_name, pa.qty_good, pa.qty_reject, pa.notes as task_notes,
               pa.status as task_status,
               u.fullname as operator_name,
               s.spk_number, s.project_name
        FROM production_logs pl
        JOIN production_assignments pa ON pl.assignment_id = pa.id
        JOIN users u ON pl.operator_id = u.id
        JOIN spk s ON pa.spk_id = s.id
        WHERE DATE(pl.log_time) = ?
        ORDER BY pl.log_time DESC";

if ($activity !== 'all') {
    $sql = str_replace("ORDER BY pl.log_time DESC", "AND pl.activity = ? ORDER BY pl.log_time DESC", $sql);
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date, $activity]);
} else {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date]);
}
$reports = $stmt->fetchAll();

// Hitung Ringkasan (ambil langsung dari assignment yang benar-benar selesai di tanggal tsb)
$stmt_sum = $pdo->prepare("SELECT
                                COALESCE(SUM(qty_good),0) AS sum_good,
                                COALESCE(SUM(qty_reject),0) AS sum_reject,
                                COUNT(*) AS count_finish
                           FROM production_assignments
                           WHERE status = 'completed' AND DATE(end_time) = ?");
$stmt_sum->execute([$date]);
$sum_row = $stmt_sum->fetch() ?: ['sum_good' => 0, 'sum_reject' => 0, 'count_finish' => 0];
$sum_good = (float)$sum_row['sum_good'];
$sum_reject = (float)$sum_row['sum_reject'];
$count_finish = (int)$sum_row['count_finish'];

$total_out = $sum_good + $sum_reject;
$yield = ($total_out > 0) ? round(($sum_good / $total_out) * 100, 1) : 0;

// Query tambahan: progres partlist per tanggal
$sql_part = "SELECT pp.*, pa.process_name, u.fullname as operator_name, s.spk_number, s.project_name, pl.part_name, pl.drawing_no
             FROM production_partlist_progress pp
             JOIN production_assignments pa ON pp.assignment_id = pa.id
             LEFT JOIN users u ON pp.created_by = u.id
             JOIN spk s ON pp.spk_id = s.id
             LEFT JOIN spk_partlists pl ON pp.partlist_id = pl.id
             WHERE DATE(pp.created_at) = ?
             ORDER BY pp.created_at DESC";
$stmt_part = $pdo->prepare($sql_part);
$stmt_part->execute([$date]);
$part_reports = $stmt_part->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Laporan Produksi Harian</h4>
        <p class="text-muted mb-0">Tanggal: <?= date('d F Y', strtotime($date)) ?> | Aktivitas: <?= strtoupper($activity) ?></p>
    </div>
    <form method="GET" class="d-flex gap-2">
        <input type="hidden" name="page" value="prod-report">
        <input type="date" name="date" class="form-control w-auto" value="<?= $date ?>">
        <select name="activity" class="form-select w-auto">
            <option value="all" <?= $activity === 'all' ? 'selected' : '' ?>>Semua Aktivitas</option>
            <option value="start" <?= $activity === 'start' ? 'selected' : '' ?>>Start</option>
            <option value="hold" <?= $activity === 'hold' ? 'selected' : '' ?>>Hold</option>
            <option value="resume" <?= $activity === 'resume' ? 'selected' : '' ?>>Resume</option>
            <option value="finish" <?= $activity === 'finish' ? 'selected' : '' ?>>Finish</option>
        </select>
        <button type="submit" class="btn btn-primary"><i class="bi bi-filter"></i></button>
        <button type="button" class="btn btn-dark" onclick="window.print()"><i class="bi bi-printer"></i></button>
    </form>
</div>

<!-- SCORECARDS -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-success text-white shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold mb-0"><?= number_format($sum_good, 0, ',', '.') ?></h2>
                    <small class="text-white-50 text-uppercase">Total Good (Finish)</small>
                </div>
                <i class="bi bi-check-circle fs-1 opacity-25"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold mb-0"><?= number_format($sum_reject, 0, ',', '.') ?></h2>
                    <small class="text-white-50 text-uppercase">Total Reject (Finish)</small>
                </div>
                <i class="bi bi-x-circle fs-1 opacity-25"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold mb-0"><?= $yield ?>%</h2>
                    <small class="text-white-50 text-uppercase">Yield Rate (Finish)</small>
                </div>
                <i class="bi bi-pie-chart fs-1 opacity-25"></i>
            </div>
        </div>
    </div>
</div>

<!-- TABLE -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-bold">Aktivitas Tugas Operator</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-striped">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Waktu</th>
                        <th>Aktivitas</th>
                        <th>Operator</th>
                        <th>Project & Proses</th>
                        <th class="text-center text-success">Good</th>
                        <th class="text-center text-danger">Reject</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($reports)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">Belum ada aktivitas produksi pada tanggal ini.</td></tr>
                    <?php else: foreach($reports as $row): ?>
                    <tr>
                        <td class="ps-4 text-muted font-monospace"><?= date('H:i', strtotime($row['log_time'])) ?></td>
                        <td>
                            <?php
                            $act = strtolower($row['activity']);
                            $act_badge = 'bg-secondary';
                            if ($act === 'start') $act_badge = 'bg-primary';
                            if ($act === 'hold') $act_badge = 'bg-warning text-dark';
                            if ($act === 'resume') $act_badge = 'bg-info text-dark';
                            if ($act === 'finish') $act_badge = 'bg-success';
                            ?>
                            <span class="badge <?= $act_badge ?>"><?= strtoupper($row['activity']) ?></span>
                        </td>
                        <td class="fw-bold"><?= clean($row['operator_name']) ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= clean($row['spk_number']) ?></div>
                            <span class="badge bg-secondary"><?= clean($row['process_name']) ?></span>
                            <small class="text-muted ms-1"><?= mb_strimwidth($row['project_name'],0,20,'..') ?></small>
                        </td>
                        <td class="text-center fw-bold text-success fs-5"><?= $row['activity'] === 'finish' ? ($row['qty_good'] + 0) : '-' ?></td>
                        <td class="text-center fw-bold text-danger fs-5"><?= $row['activity'] === 'finish' ? ($row['qty_reject'] + 0) : '-' ?></td>
                        <td class="text-muted small fst-italic"><?= clean($row['notes'] ?: $row['task_notes']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if(!empty($reports)): ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end pe-3">TOTAL FINISH (<?= $count_finish ?>):</td>
                        <td class="text-center text-success"><?= $sum_good ?></td>
                        <td class="text-center text-danger"><?= $sum_reject ?></td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-white fw-bold">Progress Partlist Operator</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-striped">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Waktu</th>
                        <th>Operator</th>
                        <th>SPK & Proses</th>
                        <th>Part</th>
                        <th class="text-center">Qty Update</th>
                        <th class="text-center">Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($part_reports)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada progress partlist pada tanggal ini.</td></tr>
                    <?php else: foreach ($part_reports as $row): ?>
                    <tr>
                        <td class="ps-4 text-muted font-monospace"><?= date('H:i', strtotime($row['created_at'])) ?></td>
                        <td class="fw-bold"><?= clean($row['operator_name'] ?: '-') ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= clean($row['spk_number']) ?></div>
                            <span class="badge bg-secondary"><?= clean($row['process_name']) ?></span>
                        </td>
                        <td>
                            <div class="fw-bold"><?= clean($row['part_name'] ?: '-') ?></div>
                            <small class="text-muted">DWG: <?= clean($row['drawing_no'] ?: '-') ?></small>
                        </td>
                        <td class="text-center fw-bold text-primary"><?= $row['qty_done'] + 0 ?></td>
                        <td class="text-center">
                            <?php if (($row['progress_state'] ?? '') === 'done'): ?>
                                <span class="badge bg-success">SELESAI</span>
                            <?php elseif (($row['progress_state'] ?? '') === 'pending'): ?>
                                <span class="badge bg-danger">BELUM</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">PROGRESS</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small fst-italic"><?= clean($row['notes'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php render_footer(); ?>
