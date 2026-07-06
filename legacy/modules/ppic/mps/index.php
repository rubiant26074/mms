<?php
// modules/ppic/mps/index.php
render_header("Master Production Schedule (MPS)");

// Filter Bulan
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('m');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');

// Query Data SPK (Active & Planned)
$sql = "SELECT spk.*,
               COALESCE(spk.project_name, '-') AS project_name,
               c.name as customer_name,
               (SELECT COUNT(*) FROM production_assignments WHERE spk_id = spk.id) as total_tasks,
               (SELECT COUNT(*) FROM production_assignments WHERE spk_id = spk.id AND status = 'completed') as completed_tasks
        FROM spk
        LEFT JOIN sales_orders so ON so.id = spk.sales_order_id
        LEFT JOIN customers c ON so.customer_id = c.id
        WHERE (MONTH(spk.deadline_date) = ? AND YEAR(spk.deadline_date) = ?)
        OR spk.status IN ('released', 'in_production') -- Tampilkan juga yg aktif meski beda bulan
        ORDER BY spk.deadline_date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$month, $year]);
$schedules = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-calendar-week"></i> Jadwal Produksi (MPS)</h3>
        <p class="text-muted">Monitoring deadline dan progres produksi.</p>
    </div>
    <div class="col-md-6">
        <form method="GET" class="d-flex justify-content-end gap-2">
            <input type="hidden" name="page" value="ppic-mps">
            <select name="month" class="form-select w-auto">
                <?php for($m=1; $m<=12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m==$month?'selected':'' ?>><?= date('F', mktime(0,0,0,$m, 1)) ?></option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-select w-auto">
                <?php for($y=date('Y')-1; $y<=date('Y')+1; $y++): ?>
                    <option value="<?= $y ?>" <?= $y==$year?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>
    </div>
</div>

<div class="row">
    <?php if(empty($schedules)): ?>
        <div class="col-12 text-center py-5 text-muted bg-white border rounded">
            <i class="bi bi-calendar-x display-4"></i>
            <p class="mt-3">Tidak ada jadwal produksi pada periode ini.</p>
        </div>
    <?php else: foreach($schedules as $row): 
        // Hitung Hari Tersisa
        $today_obj = new DateTime(date('Y-m-d'));
        $deadline_obj = new DateTime(date('Y-m-d', strtotime($row['deadline_date'])));
        $diff = (int)$today_obj->diff($deadline_obj)->format('%r%a');
        
        // Warna Card berdasarkan Deadline
        $border_class = 'border-success'; // Aman
        $bg_class = 'bg-success bg-opacity-10';
        $text_class = 'text-success';
        
        if (in_array($row['status'], ['completed', 'closed'], true)) {
            $border_class = 'border-dark';
            $bg_class = 'bg-secondary bg-opacity-10';
            $text_class = 'text-dark';
            $status_text = "SELESAI";
        } elseif ($diff < 0) {
            $border_class = 'border-danger'; // Telat
            $bg_class = 'bg-danger bg-opacity-10';
            $text_class = 'text-danger';
            $status_text = "TERLAMBAT " . abs($diff) . " HARI";
        } elseif ($diff <= 3) {
            $border_class = 'border-warning'; // Warning
            $bg_class = 'bg-warning bg-opacity-10';
            $text_class = 'text-warning text-dark';
            $status_text = "Sisa $diff Hari";
        } else {
            $status_text = "Sisa $diff Hari";
        }

        // Hitung Progress Bar
        $total = $row['total_tasks'];
        $done = $row['completed_tasks'];
        $percent = ($total > 0) ? round(($done / $total) * 100) : 0;
        
        // Jika status SPK completed tapi task 0, anggap 100%
        if ($row['status'] == 'completed' || $row['status'] == 'closed') $percent = 100;
    ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100 shadow-sm <?= $border_class ?> border-top border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="fw-bold mb-0"><?= $row['spk_number'] ?></h6>
                        <small class="text-muted">Ref SO: <?= !empty($row['sales_order_id']) ? ('#' . (int)$row['sales_order_id']) : '-' ?></small>
                    </div>
                    <span class="badge <?= $bg_class ?> <?= $text_class ?>"><?= $status_text ?></span>
                </div>
                
                <h5 class="card-title text-primary"><?= $row['project_name'] ?></h5>
                <p class="card-text small text-muted mb-3">
                        Customer: <strong><?= !empty($row['customer_name']) ? $row['customer_name'] : '-' ?></strong><br>
                        Deadline: <?= date('d M Y', strtotime($row['deadline_date'])) ?>
                </p>

                <!-- PROGRESS BAR -->
                <div class="d-flex justify-content-between small mb-1">
                    <span>Progress Produksi</span>
                    <span class="fw-bold"><?= $percent ?>%</span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar <?= ($percent >= 100 ? 'bg-success' : (($diff<0)?'bg-danger':'bg-primary')) ?>" role="progressbar" style="width: <?= $percent ?>%"></div>
                </div>
                <div class="mt-2 small text-muted text-end">
                    Task Selesai: <?= $done ?> / <?= $total ?>
                </div>
            </div>
            <div class="card-footer bg-white text-center">
                <a href="index.php?page=ppic-spk&action=print&id=<?= (int)$row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark w-100">Lihat Detail SPK</a>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<?php render_footer(); ?>
