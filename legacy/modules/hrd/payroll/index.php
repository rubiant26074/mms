<?php
// modules/hrd/payroll/index.php
render_header("Payroll & Penggajian");

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// ACTION: MARK AS PAID (Bayar Gaji)
if (isset($_GET['action']) && $_GET['action'] == 'pay' && isset($_GET['id'])) {
    if (has_permission('hrd_payroll_manage')) {
        // Update Status
        $pdo->prepare("UPDATE payrolls SET status='paid' WHERE id=? AND status='draft'")->execute([$_GET['id']]);
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'hrd.payroll.paid.' . (int)$_GET['id'],
                'Payroll Dibayarkan',
                "Slip payroll #" . (int)$_GET['id'] . " telah dibayar.",
                "index.php?page=hrd-payroll&action=print&id=" . (int)$_GET['id'],
                'success',
                ['target_roles' => ['executive', 'manager']]
            );
        }
        
        // AUTO JURNAL (Opsional: Kredit Bank, Debit Beban Gaji)
        // Code jurnal bisa ditambahkan di sini memanggil create_journal()
        
        echo "<script>alert('Gaji telah dibayarkan (Paid)!'); window.location='index.php?page=hrd-payroll';</script>";
    }
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-cash-stack"></i> Payroll Karyawan</h3>
        <p class="text-muted">Perhitungan dan pembayaran gaji bulanan.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=hrd-payroll&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Buat Slip Gaji
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end" id="payroll-filter-form">
            <input type="hidden" name="page" value="hrd-payroll">
            
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Pencarian</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="No Slip / Nama / Jabatan..." value="<?= $search_key ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Dari</label>
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Sampai</label>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="draft" <?= $filter_status=='draft'?'selected':'' ?>>Draft</option>
                    <option value="paid" <?= $filter_status=='paid'?'selected':'' ?>>Paid</option>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=hrd-payroll" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
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
                        <th>No. Slip</th>
                        <th>Periode</th>
                        <th>Nama Karyawan</th>
                        <th>Kehadiran</th>
                        <th class="text-end">THP (Gaji Bersih)</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT p.*, u.fullname, r.role_name 
                            FROM payrolls p
                            JOIN users u ON p.user_id = u.id
                            JOIN roles r ON u.role_id = r.id
                            WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND p.status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($start_date)) {
                        $sql .= " AND p.period_start >= ?";
                        $params[] = $start_date;
                    }
                    if (!empty($end_date)) {
                        $sql .= " AND p.period_end <= ?";
                        $params[] = $end_date;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (p.payroll_code LIKE ? OR u.fullname LIKE ? OR r.role_name LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY p.id DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    while ($row = $stmt->fetch()):
                        $badge = ($row['status'] == 'paid') ? 'bg-success' : 'bg-secondary';
                    ?>
                    <tr>
                        <td><strong><?= clean($row['payroll_code']) ?></strong></td>
                        <td>
                            <small class="text-muted">
                                <?= date('d/m', strtotime($row['period_start'])) ?> - <?= date('d/m/Y', strtotime($row['period_end'])) ?>
                            </small>
                        </td>
                        <td>
                            <strong><?= clean($row['fullname']) ?></strong><br>
                            <small class="text-muted"><?= clean($row['role_name']) ?></small>
                        </td>
                        <td><?= $row['total_attendance'] ?> Hari</td>
                        <td class="text-end fw-bold text-success">Rp <?= number_format($row['net_salary'], 0, ',', '.') ?></td>
                        <td><span class="badge <?= $badge ?>"><?= strtoupper($row['status']) ?></span></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <!-- Print Slip -->
                                <a href="index.php?page=hrd-payroll&action=print&id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Cetak Slip">
                                    <i class="bi bi-printer"></i>
                                </a>

                                <?php if($row['status'] == 'draft'): ?>
                                    <!-- Edit -->
                                    <a href="index.php?page=hrd-payroll&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning text-dark"><i class="bi bi-pencil"></i></a>
                                    <!-- Pay -->
                                    <a href="index.php?page=hrd-payroll&action=pay&id=<?= $row['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Tandai sudah dibayar?')" title="Bayar"><i class="bi bi-wallet2"></i></a>
                                    <!-- Delete -->
                                    <a href="index.php?page=hrd-payroll&action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus data gaji ini?')"><i class="bi bi-trash"></i></a>
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
    const form = document.getElementById('payroll-filter-form');
    if (!form) return;

    const search = form.querySelector('input[name="search"]');
    const status = form.querySelector('select[name="status"]');
    const start = form.querySelector('input[name="start_date"]');
    const end = form.querySelector('input[name="end_date"]');
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
    if (status) status.addEventListener('change', submit);
    if (start) start.addEventListener('change', submit);
    if (end) end.addEventListener('change', submit);
})();
</script>

<?php render_footer(); ?>
