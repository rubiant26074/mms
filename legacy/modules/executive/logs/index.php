<?php
// modules/executive/logs/index.php
render_header("System Audit Logs");

// Filter
$filter_module = isset($_GET['module']) ? $_GET['module'] : '';
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$page_limit = 50; // Limit baris agar ringan

// Query Dasar
$sql = "SELECT * FROM system_logs WHERE 1=1";
$params = [];

if ($filter_module) {
    $sql .= " AND module = ?";
    $params[] = $filter_module;
}

if ($search) {
    $sql .= " AND (user_name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY id DESC LIMIT $page_limit";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-activity"></i> Audit Logs</h3>
        <p class="text-muted">Rekam jejak aktivitas pengguna dalam sistem.</p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <input type="hidden" name="page" value="exec-logs">
            <div class="col-md-3">
                <select name="module" class="form-select">
                    <option value="">- Semua Modul -</option>
                    <option value="AUTH" <?= $filter_module=='AUTH'?'selected':'' ?>>Login/Logout</option>
                    <option value="SALES" <?= $filter_module=='SALES'?'selected':'' ?>>Sales</option>
                    <option value="PPIC" <?= $filter_module=='PPIC'?'selected':'' ?>>PPIC</option>
                    <option value="PROD" <?= $filter_module=='PROD'?'selected':'' ?>>Production</option>
                    <option value="WHSE" <?= $filter_module=='WHSE'?'selected':'' ?>>Warehouse</option>
                    <option value="FINANCE" <?= $filter_module=='FINANCE'?'selected':'' ?>>Finance</option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Cari User / Aktivitas..." value="<?= $search ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light">
                    <tr>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Modul</th>
                        <th>Aksi</th>
                        <th>Deskripsi</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($logs)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada data log.</td></tr>
                    <?php else: foreach($logs as $log): 
                        $bg_mod = 'bg-secondary';
                        if($log['module']=='AUTH') $bg_mod = 'bg-dark';
                        if($log['module']=='SALES') $bg_mod = 'bg-success';
                        if($log['module']=='FINANCE') $bg_mod = 'bg-warning text-dark';
                        if($log['module']=='PROD') $bg_mod = 'bg-primary';
                        if($log['module']=='WHSE') $bg_mod = 'bg-info text-dark';
                    ?>
                    <tr>
                        <td class="text-nowrap"><?= date('d/m/y H:i:s', strtotime($log['created_at'])) ?></td>
                        <td class="fw-bold"><?= clean($log['user_name']) ?></td>
                        <td><?= clean($log['role']) ?></td>
                        <td><span class="badge <?= $bg_mod ?>"><?= $log['module'] ?></span></td>
                        <td><strong><?= clean($log['action']) ?></strong></td>
                        <td><?= clean($log['description']) ?></td>
                        <td class="text-muted small"><?= clean($log['ip_address']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-muted">
            Menampilkan <?= count($logs) ?> aktivitas terakhir.
        </div>
    </div>
</div>

<?php render_footer(); ?>