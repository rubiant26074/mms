<?php
// modules/dashboard/index.php

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Akses langsung ditolak.");
}

$role = (string)($_SESSION['role'] ?? '');
$role_name = (string)($_SESSION['role_name'] ?? strtoupper($role));
$user_id = (int)($_SESSION['user_id'] ?? 0);
$fullname = (string)($_SESSION['fullname'] ?? 'User');
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$active_theme = function_exists('mms_get_effective_theme_slug')
    ? mms_get_effective_theme_slug()
    : strtolower(trim((string)($_GET['theme'] ?? 'original')));
$active_theme_label = function_exists('mms_theme_label')
    ? mms_theme_label($active_theme)
    : ucwords(str_replace(['-', '_'], ' ', (string)$active_theme));

if (!function_exists('dash_e')) {
    function dash_e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('dash_truncate')) {
    function dash_truncate($value, $width = 120) {
        $text = (string)$value;
        $limit = (int)$width;
        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $limit, '...');
        }
        if (strlen($text) <= $limit) return $text;
        return substr($text, 0, max(0, $limit - 3)) . '...';
    }
}

if (!function_exists('dash_safe_link')) {
    function dash_safe_link($link, $default = 'index.php') {
        $link = trim((string)$link);
        if ($link === '') return $default;
        if (preg_match('/^\s*(javascript|data|vbscript):/i', $link)) return $default;
        if (preg_match('#^https?://#i', $link)) return $default;
        if (strpos($link, '//') === 0) return $default;
        if (strpos($link, '/') === 0) return $default;
        return $link;
    }
}

if (!function_exists('dash_error')) {
    function dash_error($context, $e = null) {
        if ($e instanceof Throwable) {
            error_log('Dashboard [' . $context . '] ' . $e->getMessage());
        }
        echo "<div class='alert alert-danger'>Data dashboard tidak dapat dimuat sementara.</div>";
    }
}

render_header("Dashboard");
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- HEADER GLOBAL -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card dashboard-hero border-0 shadow-sm bg-white border-start border-5 border-primary">
            <div class="card-body p-4 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold mb-1 text-primary">Halo, <?= dash_e($fullname) ?>! 👋</h2>
                    <p class="mb-0 text-muted">
                        Login sebagai: <strong><?= dash_e($role_name) ?></strong> 
                        <span class="badge bg-secondary ms-2 small">Slug: <?= dash_e($role) ?></span>
                        <span class="badge bg-secondary ms-2 small">Tema: <?= dash_e($active_theme_label) ?></span>
                    </p>
                </div>
                <div class="text-end d-none d-md-block">
                    <h5 class="mb-0 text-dark"><?= date('d F Y') ?></h5>
                    <small class="text-success"><i class="bi bi-circle-fill" style="font-size: 8px;"></i> System Online</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// ============================================================================
// LOGIKA DASHBOARD PER ROLE
// ============================================================================

switch(true): 

    // ------------------------------------------------------------------------
    // 1. DASHBOARD SUPER ADMINISTRATOR (BARU!)
    // ------------------------------------------------------------------------
    case ($role == 'admin'):
        $today = date('Y-m-d');
        $stale_days = 14;
        $total_users = 0;
        $total_roles = 0;
        $total_logs_today = 0;
        $omzet = 0;
        $spk_active = 0;
        $recent_logs = [];
        $lbls = [];
        $vals = [];
        $db_ok = true;
        $db_size = null;
        $disk_used_pct = 0;
        $last_backup = null;
        $admin_events = [];
        $overdue_inv = 0;
        $stale_so = 0;
        $stale_po = 0;
        $stock_negative = 0;
        $stock_low = 0;
        $notif_total = 0;
        $notif_unread = 0;
        $notif_recent = [];
        $wf_events_7d = 0;
        try {

            // A. SYSTEM STATS
            $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $total_roles = $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
            $total_logs_today = $pdo->query("SELECT COUNT(*) FROM system_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            
            // B. BUSINESS SNAPSHOT
            $omzet = $pdo->query("SELECT SUM(grand_total) FROM invoices WHERE status NOT IN ('cancelled','draft')")->fetchColumn() ?: 0;
            $spk_active = $pdo->query("SELECT COUNT(*) FROM spk WHERE status IN ('released','in_production')")->fetchColumn();
            
            // C. RECENT SYSTEM ACTIVITY
            $recent_logs = $pdo->query("SELECT * FROM system_logs ORDER BY id DESC LIMIT 5")->fetchAll();

            // D. CHART DATA (Sales Trend)
            $sql_chart = "SELECT DATE_FORMAT(invoice_date, '%M') as month, SUM(grand_total) as total FROM invoices WHERE status!='cancelled' AND invoice_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(invoice_date, '%Y-%m') ORDER BY invoice_date ASC";
            $chart_data = $pdo->query($sql_chart)->fetchAll(PDO::FETCH_ASSOC);
            $lbls = []; $vals = []; foreach($chart_data as $d){ $lbls[]=$d['month']; $vals[]=$d['total']; }

            // E. SYSTEM HEALTH
            $db_ok = true;
            $db_size = null;
            try {
                $db_size = $pdo->query("SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
            } catch (Exception $e_db) {
                $db_ok = false;
            }
            $disk_total = @disk_total_space(__DIR__);
            $disk_free = @disk_free_space(__DIR__);
            $disk_used_pct = ($disk_total > 0) ? round((($disk_total - $disk_free) / $disk_total) * 100) : 0;
            $last_backup = $pdo->query("SELECT created_at FROM system_logs WHERE action LIKE '%backup%' ORDER BY id DESC LIMIT 1")->fetchColumn();

            // F. AUDIT & SECURITY (Admin Events)
            $admin_events = $pdo->query("SELECT * FROM system_logs WHERE action LIKE '%user%' OR action LIKE '%role%' OR action LIKE '%permission%' OR action LIKE '%reset%' ORDER BY id DESC LIMIT 5")->fetchAll();

            // G. ANOMALY & STALE DOCS
            $overdue_inv = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status NOT IN ('paid','cancelled') AND due_date < CURDATE()")->fetchColumn();
            $stale_so = $pdo->query("SELECT COUNT(*) FROM sales_orders WHERE status NOT IN ('completed','cancelled','rejected') AND so_date < DATE_SUB(CURDATE(), INTERVAL $stale_days DAY)")->fetchColumn();
            $stale_po = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status NOT IN ('completed','cancelled') AND po_date < DATE_SUB(CURDATE(), INTERVAL $stale_days DAY)")->fetchColumn();
            $stock_negative = $pdo->query("SELECT COUNT(*) FROM items WHERE current_stock < 0")->fetchColumn();
            $stock_low = $pdo->query("SELECT COUNT(*) FROM items WHERE current_stock <= min_stock")->fetchColumn();

            // H. NOTIFICATION MONITORING
            $notif_total = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
            $notif_unread = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();
            $notif_recent = $pdo->query("SELECT id, title, message, type, created_at, target_role, user_id FROM notifications ORDER BY id DESC LIMIT 5")->fetchAll();
            $wf_events_7d = 0;
            if (function_exists('ensure_workflow_notification_table')) {
                ensure_workflow_notification_table();
            }
            try {
                $wf_table_exists = $pdo->query("SHOW TABLES LIKE 'workflow_notification_events'")->rowCount() > 0;
                if ($wf_table_exists) {
                    $wf_events_7d = (int)$pdo->query("SELECT COUNT(*) FROM workflow_notification_events WHERE last_sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
                }
            } catch (Exception $e_wf) {
                $wf_events_7d = 0;
            }

        } catch (Exception $e) { dash_error('admin', $e); }
    ?>
    
    <!-- ROW 1: SYSTEM HEALTH & STATS -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card kpi-card h-100 bg-dark text-white shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="fw-bold mb-0"><?= $total_users ?></h3>
                        <small class="text-white-50 text-uppercase">Total Users</small>
                    </div>
                    <i class="bi bi-people-fill fs-1 text-secondary"></i>
                </div>
                <div class="card-footer bg-dark border-top border-secondary p-2 text-center">
                    <a href="index.php?page=users" class="text-white text-decoration-none small">Manage Users &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card h-100 bg-secondary text-white shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="fw-bold mb-0"><?= $total_roles ?></h3>
                        <small class="text-white-50 text-uppercase">Roles / Jabatan</small>
                    </div>
                    <i class="bi bi-shield-lock-fill fs-1 text-dark"></i>
                </div>
                <div class="card-footer bg-secondary border-top border-dark p-2 text-center">
                    <a href="index.php?page=roles" class="text-white text-decoration-none small">Access Control &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card h-100 border-start border-4 border-info shadow-sm">
                <div class="card-body">
                    <div class="text-secondary small fw-bold text-uppercase mb-1">Aktivitas Hari Ini</div>
                    <h3 class="fw-bold text-dark"><?= $total_logs_today ?> <span class="fs-6 text-muted">Log</span></h3>
                    <small class="text-info"><i class="bi bi-activity"></i> System Events</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card h-100 border-start border-4 border-success shadow-sm">
                <div class="card-body">
                    <div class="text-secondary small fw-bold text-uppercase mb-1">Total Revenue</div>
                    <h3 class="fw-bold text-dark text-truncate">Rp <?= number_format($omzet/1000000, 0, ',', '.') ?> Jt</h3>
                    <small class="text-success"><i class="bi bi-graph-up"></i> Business Health</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 1B: SYSTEM HEALTH / AUDIT / ANOMALI -->
    <div class="row mb-4">
        <div class="col-lg-4">
            <div class="card shadow h-100">
                <div class="card-header bg-white fw-bold text-dark"><i class="bi bi-activity"></i> System Health</div>
                <div class="card-body">
                    <div class="mb-2 d-flex justify-content-between">
                        <span>Database</span>
                        <span class="badge <?= $db_ok ? 'bg-success' : 'bg-danger' ?>"><?= $db_ok ? 'OK' : 'ERROR' ?></span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span>Ukuran DB</span>
                        <span class="fw-bold"><?= $db_size ? number_format($db_size / 1024 / 1024, 2, ',', '.') . ' MB' : '-' ?></span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span>Disk Usage</span>
                        <span class="fw-bold"><?= $disk_used_pct ?>%</span>
                    </div>
                    <div class="small text-muted">Backup Terakhir: <?= $last_backup ? date('d/m/Y H:i', strtotime($last_backup)) : '-' ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow h-100">
                <div class="card-header bg-white fw-bold text-dark"><i class="bi bi-shield-lock"></i> Audit & Security</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        <?php if (empty($admin_events)): ?>
                            <li class="list-group-item text-muted text-center py-4">Belum ada aktivitas admin.</li>
                        <?php else: foreach($admin_events as $log): ?>
                            <li class="list-group-item">
                                <span class="fw-bold text-primary"><?= dash_e($log['user_name'] ?? '-') ?></span>
                                <span class="text-muted">: <?= dash_e($log['action'] ?? '-') ?></span><br>
                                <span class="text-muted fst-italic" style="font-size:10px;"><?= dash_e($log['description'] ?? '-') ?></span>
                                <div class="text-end text-muted" style="font-size:10px;"><?= date('d/m H:i', strtotime($log['created_at'])) ?></div>
                            </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow h-100">
                <div class="card-header bg-white fw-bold text-danger"><i class="bi bi-exclamation-triangle"></i> Anomali Transaksi</div>
                <div class="card-body">
                    <div class="mb-2 d-flex justify-content-between">
                        <span>Invoice Overdue</span>
                        <span class="fw-bold text-danger"><?= $overdue_inv ?></span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span>SO > <?= $stale_days ?> Hari</span>
                        <span class="fw-bold"><?= $stale_so ?></span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span>PO > <?= $stale_days ?> Hari</span>
                        <span class="fw-bold"><?= $stale_po ?></span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span>Stok Negatif</span>
                        <span class="fw-bold text-danger"><?= $stock_negative ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Stok Menipis</span>
                        <span class="fw-bold text-warning"><?= $stock_low ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 1C: NOTIFICATION MONITORING -->
    <div class="row mb-4">
        <div class="col-lg-4">
            <div class="card shadow h-100">
                <div class="card-header bg-white fw-bold text-dark"><i class="bi bi-bell"></i> Monitoring Notifikasi</div>
                <div class="card-body">
                    <div class="mb-2 d-flex justify-content-between">
                        <span>Total Notifikasi</span>
                        <span class="fw-bold"><?= $notif_total ?></span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span>Belum Dibaca</span>
                        <span class="fw-bold text-warning"><?= $notif_unread ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Workflow Event 7 Hari</span>
                        <span class="fw-bold"><?= $wf_events_7d ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow h-100">
                <div class="card-header bg-white fw-bold text-secondary">Notifikasi Terbaru</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        <?php if (empty($notif_recent)): ?>
                            <li class="list-group-item text-muted text-center py-4">Belum ada notifikasi.</li>
                        <?php else: foreach($notif_recent as $n): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold text-primary"><?= !empty($n['title']) ? dash_e($n['title']) : '(Tanpa Judul)' ?></span>
                                    <span class="badge bg-light text-dark"><?= dash_e(strtoupper((string)($n['type'] ?? ''))) ?></span>
                                </div>
                                <div class="text-muted" style="font-size:10px;"><?= date('d/m H:i', strtotime($n['created_at'])) ?> | role: <?= dash_e($n['target_role'] ?: '-') ?> | user: <?= dash_e(($n['user_id'] ?? '') !== '' ? (string)$n['user_id'] : '-') ?></div>
                                <div class="text-muted fst-italic" style="font-size:11px;"><?= dash_e(dash_truncate($n['message'] ?? '', 120)) ?></div>
                            </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- CHART & TOOLS -->
        <div class="col-lg-8">
            <div class="card shadow h-100">
                <div class="card-header bg-white fw-bold text-primary"><i class="bi bi-bar-chart-line"></i> Monitoring Transaksi (6 Bulan)</div>
                <div class="card-body">
                    <div style="height: 300px; position: relative; width: 100%;">
                        <canvas id="adminChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- QUICK ADMIN TOOLS -->
            <div class="card shadow mb-4">
                <div class="card-header bg-danger text-white fw-bold"><i class="bi bi-tools"></i> Admin Tools</div>
                <div class="list-group list-group-flush">
                    <a href="index.php?page=admin-company" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-building me-2"></i> Identitas Perusahaan</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="index.php?page=role-permissions" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-key me-2"></i> Konfigurasi RBAC</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="index.php?page=exec-logs" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-journals me-2"></i> Audit Logs Lengkap</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="index.php?page=admin-reset" class="list-group-item list-group-item-action list-group-item-danger d-flex justify-content-between align-items-center text-danger fw-bold">
                        <span><i class="bi bi-trash3-fill me-2"></i> Reset Database</span>
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </a>
                </div>
            </div>

            <!-- RECENT LOGS -->
            <div class="card shadow">
                <div class="card-header bg-white fw-bold text-secondary">Log Terbaru</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        <?php foreach($recent_logs as $log): ?>
                        <li class="list-group-item">
                            <span class="fw-bold text-primary"><?= dash_e($log['user_name'] ?? '-') ?></span> 
                            <span class="text-muted">: <?= dash_e($log['action'] ?? '-') ?></span><br>
                            <span class="text-muted fst-italic" style="font-size:10px;"><?= dash_e($log['description'] ?? '-') ?></span>
                            <div class="text-end text-muted" style="font-size:10px;"><?= date('d/m H:i', strtotime($log['created_at'])) ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        new Chart(document.getElementById('adminChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($lbls) ?>,
                datasets: [{
                    label: 'Total Omzet System',
                    data: <?= json_encode($vals) ?>,
                    borderColor: '#2c3e50',
                    backgroundColor: 'rgba(44, 62, 80, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
        });
    </script>
    <?php break; ?>


    <?php 
    // ------------------------------------------------------------------------
    // 2. DASHBOARD SALES
    // ------------------------------------------------------------------------
    case (strpos($role, 'sales') !== false): 
        $month = date('m');
        $year = date('Y');
        $current_sales = 0;
        $target_sales = 500000000;
        $percentage = 0;
        $pipeline_val = 0;
        $active_orders = 0;
        $lbl_sales = [];
        $val_sales = [];
        $recent_so = [];
        try {
            $month = date('m'); $year = date('Y');
            $stmt_sales = $pdo->prepare("SELECT SUM(grand_total) FROM sales_orders WHERE status IN ('confirmed', 'in_production', 'completed') AND MONTH(so_date) = ? AND YEAR(so_date) = ?");
            $stmt_sales->execute([$month, $year]);
            $current_sales = $stmt_sales->fetchColumn() ?: 0;
            $target_sales = 500000000; 
            $percentage = ($target_sales > 0) ? round(($current_sales / $target_sales) * 100) : 0;
            $pipeline_val = $pdo->query("SELECT SUM(grand_total) FROM quotations WHERE status IN ('draft', 'waiting_approval', 'sent')")->fetchColumn() ?: 0;
            $active_orders = $pdo->query("SELECT COUNT(*) FROM sales_orders WHERE status IN ('confirmed', 'in_production')")->fetchColumn();
            
            $sql_chart = "SELECT DATE_FORMAT(so_date, '%M') as month_label, SUM(grand_total) as total FROM sales_orders WHERE status NOT IN ('cancelled', 'draft', 'rejected') AND so_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(so_date, '%Y-%m') ORDER BY so_date ASC";
            $sales_trend = $pdo->query($sql_chart)->fetchAll(PDO::FETCH_ASSOC);
            $lbl_sales = []; $val_sales = []; foreach($sales_trend as $d) { $lbl_sales[] = $d['month_label']; $val_sales[] = $d['total']; }

            // Recent Orders
            $recent_so = $pdo->query("SELECT so.*, c.name as cust_name FROM sales_orders so JOIN customers c ON so.customer_id = c.id ORDER BY so.id DESC LIMIT 5")->fetchAll();
        } catch (Exception $e) { dash_error('sales', $e); }
    ?>
    <div class="row mb-4">
        <div class="col-md-4"><div class="card h-100 border-start border-4 border-success shadow-sm"><div class="card-body"><div class="d-flex justify-content-between mb-2"><div class="text-secondary small fw-bold">PENCAPAIAN</div><span class="badge bg-success"><?= $percentage ?>%</span></div><h3 class="fw-bold text-dark mb-2">Rp <?= number_format($current_sales/1000000, 1, ',', '.') ?> Jt</h3><div class="progress" style="height: 6px;"><div class="progress-bar bg-success" style="width: <?= $percentage ?>%"></div></div></div></div></div>
        <div class="col-md-4"><div class="card h-100 border-start border-4 border-warning shadow-sm"><div class="card-body"><div class="text-secondary small fw-bold mb-1">PIPELINE</div><h3 class="fw-bold text-dark">Rp <?= number_format($pipeline_val/1000000, 1, ',', '.') ?> Jt</h3></div></div></div>
        <div class="col-md-4"><div class="card h-100 bg-primary text-white shadow-sm"><div class="card-body d-flex justify-content-between align-items-center"><div><h2 class="fw-bold mb-0"><?= $active_orders ?></h2><span class="small text-white-50">ORDER AKTIF</span></div><i class="bi bi-cart-check fs-1 text-white-50"></i></div></div></div>
    </div>
    <div class="card shadow mb-4"><div class="card-header bg-white fw-bold text-primary">Tren Penjualan</div><div class="card-body"><div style="height:250px; position:relative; width:100%"><canvas id="salesChart"></canvas></div></div></div>
    
    <div class="card shadow mb-4">
        <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary">Order Terbaru</h6></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>No. SO</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead><tbody><?php if(!empty($recent_so)): foreach($recent_so as $so): ?><tr><td><?= dash_e($so['so_number'] ?? '-') ?></td><td><?= dash_e($so['cust_name'] ?? '-') ?></td><td>Rp <?= number_format((float)($so['grand_total'] ?? 0),0,',','.') ?></td><td><span class="badge bg-secondary"><?= dash_e($so['status'] ?? '-') ?></span></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
    </div>

    <script>
    new Chart(document.getElementById('salesChart'), { type: 'line', data: { labels: <?= json_encode($lbl_sales) ?>, datasets: [{ label: 'Omzet', data: <?= json_encode($val_sales) ?>, borderColor: '#4e73df', backgroundColor: 'rgba(78, 115, 223, 0.05)', fill: true }] }, options: { maintainAspectRatio: false } });
    </script>
    <?php break; ?>


    <?php 
    // ------------------------------------------------------------------------
    // 3. DASHBOARD PURCHASING
    // ------------------------------------------------------------------------
    case (strpos($role, 'purch') !== false): 
        $month = date('m');
        $year = date('Y');
        $pr_pending = 0;
        $po_active = 0;
        $current_spending = 0;
        $lbl_spend = [];
        $val_spend = [];
        try {
            $month = date('m'); $year = date('Y');
            $pr_pending = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status='approved'")->fetchColumn();
            $po_active = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('approved', 'sent')")->fetchColumn();
            $stmt_spend = $pdo->prepare("SELECT SUM(grand_total) FROM purchase_orders WHERE status NOT IN ('cancelled', 'draft') AND MONTH(po_date) = ? AND YEAR(po_date) = ?");
            $stmt_spend->execute([$month, $year]);
            $current_spending = $stmt_spend->fetchColumn() ?: 0;
            $sql_chart_spend = "SELECT DATE_FORMAT(po_date, '%M') as month_label, SUM(grand_total) as total FROM purchase_orders WHERE status NOT IN ('cancelled', 'draft') AND po_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(po_date, '%Y-%m') ORDER BY po_date ASC";
            $spend_trend = $pdo->query($sql_chart_spend)->fetchAll(PDO::FETCH_ASSOC);
            $lbl_spend = []; $val_spend = []; foreach($spend_trend as $d) { $lbl_spend[] = $d['month_label']; $val_spend[] = $d['total']; }
        } catch (Exception $e) { dash_error('purchasing', $e); }
    ?>
    <div class="row mb-4">
        <div class="col-md-4"><div class="card h-100 border-start border-4 border-warning shadow-sm"><div class="card-body"><div class="d-flex justify-content-between mb-2"><div class="text-secondary small fw-bold text-uppercase">PR Menunggu PO</div></div><h3 class="fw-bold text-dark mb-0"><?= $pr_pending ?> Request</h3></div></div></div>
        <div class="col-md-4"><div class="card h-100 border-start border-4 border-info shadow-sm"><div class="card-body"><div class="text-secondary small fw-bold text-uppercase mb-1">PO Aktif (Open)</div><h3 class="fw-bold text-dark"><?= $po_active ?> Order</h3></div></div></div>
        <div class="col-md-4"><div class="card h-100 bg-danger text-white shadow-sm"><div class="card-body"><div class="text-white-50 small fw-bold text-uppercase">Total Belanja</div><h3 class="fw-bold mb-0">Rp <?= number_format($current_spending/1000000, 1, ',', '.') ?> Jt</h3></div></div></div>
    </div>
    <div class="card shadow mb-4"><div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-danger">Tren Pembelian</h6></div><div class="card-body"><div style="height: 300px; position: relative;"><canvas id="spendChart"></canvas></div></div></div>
    <script>
    new Chart(document.getElementById('spendChart'), {
        type: 'bar', data: { labels: <?= json_encode($lbl_spend) ?>, datasets: [{ label: 'Total Belanja', data: <?= json_encode($val_spend) ?>, backgroundColor: '#e74a3b' }] }, options: { maintainAspectRatio: false }
    });
    </script>
    <?php break; ?>


    <?php 
    // ------------------------------------------------------------------------
    // 4. DASHBOARD WAREHOUSE
    // ------------------------------------------------------------------------
    case (strpos($role, 'whse') !== false || strpos($role, 'gudang') !== false || strpos($role, 'warehouse') !== false): 
        $gr_today = 0;
        $sj_today = 0;
        $itr_today = 0;
        $low_stock = 0;
        $chart_days = [];
        $data_in = [];
        $data_out = [];
        $recent_activity = [];
        try {
            $today = date('Y-m-d');
            $window_start = date('Y-m-d', strtotime('-6 days'));

            $stmt_gr_today = $pdo->prepare("SELECT COUNT(*) FROM goods_receipts WHERE gr_date = ?");
            $stmt_gr_today->execute([$today]);
            $gr_today = (int)$stmt_gr_today->fetchColumn();

            $stmt_sj_today = $pdo->prepare("SELECT COUNT(*) FROM delivery_notes WHERE dn_date = ?");
            $stmt_sj_today->execute([$today]);
            $sj_today = (int)$stmt_sj_today->fetchColumn();

            $stmt_itr_today = $pdo->prepare("SELECT COUNT(*) FROM material_issues WHERE itr_date = ?");
            $stmt_itr_today->execute([$today]);
            $itr_today = (int)$stmt_itr_today->fetchColumn();
            $low_stock = $pdo->query("SELECT COUNT(*) FROM items WHERE current_stock <= min_stock")->fetchColumn();

            $stmt_gr_7d = $pdo->prepare("SELECT gr_date AS trx_date, COUNT(*) AS total
                                         FROM goods_receipts
                                         WHERE gr_date BETWEEN ? AND ?
                                         GROUP BY gr_date");
            $stmt_gr_7d->execute([$window_start, $today]);
            $gr_7d_rows = $stmt_gr_7d->fetchAll(PDO::FETCH_ASSOC);
            $gr_map = [];
            foreach ($gr_7d_rows as $row) {
                $gr_map[(string)$row['trx_date']] = (int)$row['total'];
            }

            $stmt_sj_7d = $pdo->prepare("SELECT dn_date AS trx_date, COUNT(*) AS total
                                         FROM delivery_notes
                                         WHERE dn_date BETWEEN ? AND ?
                                         GROUP BY dn_date");
            $stmt_sj_7d->execute([$window_start, $today]);
            $sj_7d_rows = $stmt_sj_7d->fetchAll(PDO::FETCH_ASSOC);
            $sj_map = [];
            foreach ($sj_7d_rows as $row) {
                $sj_map[(string)$row['trx_date']] = (int)$row['total'];
            }

            $stmt_itr_7d = $pdo->prepare("SELECT itr_date AS trx_date, COUNT(*) AS total
                                          FROM material_issues
                                          WHERE itr_date BETWEEN ? AND ?
                                          GROUP BY itr_date");
            $stmt_itr_7d->execute([$window_start, $today]);
            $itr_7d_rows = $stmt_itr_7d->fetchAll(PDO::FETCH_ASSOC);
            $itr_map = [];
            foreach ($itr_7d_rows as $row) {
                $itr_map[(string)$row['trx_date']] = (int)$row['total'];
            }
            
            // Grafik Mutasi
            $chart_days = []; $data_in = []; $data_out = [];
            for($i=6; $i>=0; $i--) {
                $d = date('Y-m-d', strtotime("-$i days"));
                $chart_days[] = date('d/m', strtotime($d));
                $data_in[] = (int)($gr_map[$d] ?? 0);
                $out1 = (int)($sj_map[$d] ?? 0);
                $out2 = (int)($itr_map[$d] ?? 0);
                $data_out[] = $out1 + $out2;
            }

            // Aktivitas
            $recent_gr = $pdo->query("SELECT gr_number as doc, gr_date as date, 'Masuk (GR)' as type, status FROM goods_receipts ORDER BY id DESC LIMIT 3")->fetchAll();
            $recent_sj = $pdo->query("SELECT dn_number as doc, dn_date as date, 'Keluar (SJ)' as type, status FROM delivery_notes ORDER BY id DESC LIMIT 3")->fetchAll();
            $recent_activity = array_merge($recent_gr, $recent_sj);
            usort($recent_activity, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
            $recent_activity = array_slice($recent_activity, 0, 5);
        } catch (Exception $e) { dash_error('warehouse', $e); }
    ?>
    <div class="row mb-4">
        <div class="col-md-3"><div class="card h-100 border-start border-4 border-info shadow-sm"><div class="card-body"><div class="text-secondary small fw-bold text-uppercase mb-1">Incoming Hari Ini</div><h3 class="fw-bold text-dark"><?= $gr_today ?> <span class="fs-6 text-muted">Dokumen</span></h3></div></div></div>
        <div class="col-md-3"><div class="card h-100 border-start border-4 border-success shadow-sm"><div class="card-body"><div class="text-secondary small fw-bold text-uppercase mb-1">Outgoing Hari Ini</div><h3 class="fw-bold text-dark"><?= $sj_today ?> <span class="fs-6 text-muted">Surat Jalan</span></h3></div></div></div>
        <div class="col-md-3"><div class="card h-100 border-start border-4 border-warning shadow-sm"><div class="card-body"><div class="text-secondary small fw-bold text-uppercase mb-1">Supply Produksi</div><h3 class="fw-bold text-dark"><?= $itr_today ?> <span class="fs-6 text-muted">Batch</span></h3></div></div></div>
        <div class="col-md-3"><div class="card h-100 bg-danger text-white shadow-sm"><div class="card-body"><div class="text-white-50 small fw-bold text-uppercase mb-1">Item Kritis</div><h3 class="fw-bold mb-0"><?= $low_stock ?></h3></div></div></div>
    </div>
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary"><i class="bi bi-graph-up"></i> Pergerakan Barang (7 Hari)</h6></div>
                <div class="card-body"><div style="height: 300px; position: relative; width: 100%;"><canvas id="whseChart"></canvas></div></div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <!-- Shortcuts -->
            <div class="card shadow mb-4">
                <div class="card-header bg-white fw-bold text-dark">Aksi Cepat</div>
                <div class="card-body d-grid gap-2">
                    <a href="index.php?page=whse-receive&action=create" class="btn btn-outline-primary text-start"><i class="bi bi-box-seam me-2"></i> Terima Barang (GR)</a>
                    <a href="index.php?page=whse-sj&action=create" class="btn btn-outline-success text-start"><i class="bi bi-truck me-2"></i> Buat Surat Jalan</a>
                    <a href="index.php?page=whse-issue&action=create" class="btn btn-outline-warning text-dark text-start"><i class="bi bi-box-arrow-right me-2"></i> Issue ke Produksi</a>
                </div>
            </div>
            <!-- Recent Activity -->
            <div class="card shadow h-100">
                <div class="card-header bg-white fw-bold text-secondary">Aktivitas Terakhir</div>
                <div class="list-group list-group-flush">
                    <?php if(empty($recent_activity)): ?>
                        <div class="p-3 text-center text-muted small">Belum ada aktivitas.</div>
                    <?php else: foreach($recent_activity as $act): 
                        $icon = strpos($act['type'], 'Masuk') !== false ? 'bi-arrow-down-circle text-primary' : 'bi-arrow-up-circle text-success';
                    ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div><i class="bi <?= $icon ?> me-2"></i> <strong><?= dash_e($act['doc'] ?? '-') ?></strong><div class="small text-muted ms-4"><?= dash_e($act['type'] ?? '-') ?></div></div>
                            <span class="badge bg-light text-dark border"><?= dash_e($act['status'] ?? '-') ?></span>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
    const ctxWhse = document.getElementById('whseChart').getContext('2d');
    new Chart(ctxWhse, { type: 'line', data: { labels: <?= json_encode($chart_days) ?>, datasets: [{ label: 'Masuk', data: <?= json_encode($data_in) ?>, borderColor: '#36b9cc', tension: 0.3, fill: true }, { label: 'Keluar', data: <?= json_encode($data_out) ?>, borderColor: '#1cc88a', tension: 0.3, fill: true }] }, options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { position: 'bottom' } } } });
    </script>
    <?php break; ?>


    <?php 
    // ------------------------------------------------------------------------
    // 5. DASHBOARD FINANCE & ACCOUNTING
    // ------------------------------------------------------------------------
    case (strpos($role, 'fin') !== false || strpos($role, 'acc') !== false):
        $total_ar = 0;
        $total_ap = 0;
        $cash_in = 0;
        $cash_out = 0;
        $overdue_inv = [];
        $chart_labels = [];
        $data_cash_in = [];
        $data_cash_out = [];
        try {
            $today = date('Y-m-d');
            $month = date('m');
            $year = date('Y');
            $sql_ar = "SELECT SUM(grand_total - paid_amount) FROM invoices WHERE status != 'paid' AND status != 'cancelled'";
            $total_ar = $pdo->query($sql_ar)->fetchColumn() ?: 0;
            $sql_ap = "SELECT SUM(grand_total - paid_amount) FROM supplier_bills WHERE status != 'paid' AND status != 'cancelled'";
            $total_ap = $pdo->query($sql_ap)->fetchColumn() ?: 0;

            $stmt_in = $pdo->prepare("SELECT SUM(amount) FROM invoice_payments WHERE MONTH(payment_date) = ? AND YEAR(payment_date) = ?");
            $stmt_in->execute([$month, $year]);
            $cash_in = $stmt_in->fetchColumn() ?: 0;

            $stmt_out = $pdo->prepare("SELECT SUM(amount) FROM supplier_payments WHERE MONTH(payment_date) = ? AND YEAR(payment_date) = ?");
            $stmt_out->execute([$month, $year]);
            $cash_out = $stmt_out->fetchColumn() ?: 0;

            $stmt_overdue = $pdo->prepare("SELECT inv.invoice_number, c.name AS customer, inv.grand_total, inv.due_date, (inv.grand_total - inv.paid_amount) AS sisa
                                           FROM invoices inv
                                           JOIN customers c ON inv.customer_id = c.id
                                           WHERE inv.status != 'paid' AND inv.status != 'cancelled' AND inv.due_date < ?
                                           ORDER BY inv.due_date ASC
                                           LIMIT 5");
            $stmt_overdue->execute([$today]);
            $overdue_inv = $stmt_overdue->fetchAll(PDO::FETCH_ASSOC);

            $start_6m = date('Y-m-01', strtotime('-5 months'));

            $stmt_chart_in = $pdo->prepare("SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM(amount) AS total
                                            FROM invoice_payments
                                            WHERE payment_date >= ?
                                            GROUP BY DATE_FORMAT(payment_date, '%Y-%m')");
            $stmt_chart_in->execute([$start_6m]);
            $in_rows = $stmt_chart_in->fetchAll(PDO::FETCH_ASSOC);
            $in_map = [];
            foreach ($in_rows as $row) {
                $in_map[(string)$row['ym']] = (float)$row['total'];
            }

            $stmt_chart_out = $pdo->prepare("SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM(amount) AS total
                                             FROM supplier_payments
                                             WHERE payment_date >= ?
                                             GROUP BY DATE_FORMAT(payment_date, '%Y-%m')");
            $stmt_chart_out->execute([$start_6m]);
            $out_rows = $stmt_chart_out->fetchAll(PDO::FETCH_ASSOC);
            $out_map = [];
            foreach ($out_rows as $row) {
                $out_map[(string)$row['ym']] = (float)$row['total'];
            }

            $chart_labels = []; $data_cash_in = []; $data_cash_out = [];
            for($i=5; $i>=0; $i--) {
                $month_cursor = strtotime("-$i months");
                $ym = date('Y-m', $month_cursor);
                $chart_labels[] = date('M y', $month_cursor);
                $in = (float)($in_map[$ym] ?? 0);
                $out = (float)($out_map[$ym] ?? 0);
                $data_cash_in[] = $in; $data_cash_out[] = $out;
            }
        } catch (Exception $e) { dash_error('finance', $e); }
    ?>
    <div class="row mb-4">
        <div class="col-md-3"><div class="card h-100 border-start border-4 border-success shadow-sm"><div class="card-body"><small class="text-uppercase fw-bold text-success">Total Piutang (AR)</small><h4 class="fw-bold text-dark mt-2">Rp <?= number_format($total_ar/1000000, 1, ',', '.') ?> Jt</h4><div class="small text-muted">Uang yang akan masuk</div></div></div></div>
        <div class="col-md-3"><div class="card h-100 border-start border-4 border-danger shadow-sm"><div class="card-body"><small class="text-uppercase fw-bold text-danger">Total Hutang (AP)</small><h4 class="fw-bold text-dark mt-2">Rp <?= number_format($total_ap/1000000, 1, ',', '.') ?> Jt</h4><div class="small text-muted">Kewajiban ke Supplier</div></div></div></div>
        <div class="col-md-3"><div class="card h-100 border-start border-4 border-primary shadow-sm bg-primary text-white"><div class="card-body"><small class="text-uppercase fw-bold text-white-50">Cash In (Bulan Ini)</small><h4 class="fw-bold mt-2">Rp <?= number_format($cash_in/1000000, 1, ',', '.') ?> Jt</h4></div></div></div>
        <div class="col-md-3"><div class="card h-100 border-start border-4 border-warning shadow-sm bg-warning text-dark"><div class="card-body"><small class="text-uppercase fw-bold text-dark-50">Cash Out (Bulan Ini)</small><h4 class="fw-bold mt-2">Rp <?= number_format($cash_out/1000000, 1, ',', '.') ?> Jt</h4></div></div></div>
    </div>
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-white fw-bold text-primary">Arus Kas (Cash Flow)</div>
                <div class="card-body"><div style="height: 300px; position: relative; width: 100%;"><canvas id="cashChart"></canvas></div></div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-danger text-white fw-bold d-flex justify-content-between align-items-center"><span><i class="bi bi-exclamation-triangle-fill me-2"></i> Jatuh Tempo (Overdue)</span></div>
                <div class="list-group list-group-flush">
                    <?php if(empty($overdue_inv)): ?><div class="p-4 text-center text-muted">Tidak ada invoice overdue.</div>
                    <?php else: foreach($overdue_inv as $ov): ?>
                        <div class="list-group-item"><div class="d-flex w-100 justify-content-between"><h6 class="mb-1 fw-bold text-danger"><?= dash_e($ov['invoice_number'] ?? '-') ?></h6><small class="text-muted"><?= date('d/m', strtotime($ov['due_date'])) ?></small></div><p class="mb-1 small"><?= dash_e($ov['customer'] ?? '-') ?></p><small class="text-danger fw-bold">Sisa: Rp <?= number_format((float)($ov['sisa'] ?? 0), 0, ',', '.') ?></small></div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="card-footer bg-white text-center"><a href="index.php?page=fin-ar" class="small text-danger fw-bold text-decoration-none">Kelola Invoice &rarr;</a></div>
            </div>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-12"><div class="card shadow-sm"><div class="card-body d-flex gap-3"><a href="index.php?page=fin-ar&action=create" class="btn btn-outline-success"><i class="bi bi-receipt-cutoff"></i> Buat Invoice</a><a href="index.php?page=fin-ap&action=create" class="btn btn-outline-danger"><i class="bi bi-wallet2"></i> Input Tagihan</a><a href="index.php?page=acc-journal&action=create" class="btn btn-outline-dark"><i class="bi bi-journal-plus"></i> Jurnal Manual</a><a href="index.php?page=acc-report" class="btn btn-primary ms-auto"><i class="bi bi-file-earmark-spreadsheet"></i> Laporan Keuangan</a></div></div></div>
    </div>
    <script>
    const ctxCash = document.getElementById('cashChart').getContext('2d');
    new Chart(ctxCash, { type: 'line', data: { labels: <?= json_encode($chart_labels) ?>, datasets: [{ label: 'Masuk (In)', data: <?= json_encode($data_cash_in) ?>, borderColor: '#4e73df', backgroundColor: 'rgba(78, 115, 223, 0.1)', fill: true, tension: 0.3 }, { label: 'Keluar (Out)', data: <?= json_encode($data_cash_out) ?>, borderColor: '#e74a3b', backgroundColor: 'rgba(231, 74, 59, 0.1)', fill: true, tension: 0.3 }] }, options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true } } } });
    </script>
    <?php break; ?>


    <?php
    // ------------------------------------------------------------------------
    // 6. DASHBOARD ENGINEERING
    // ------------------------------------------------------------------------
    case (strpos($role, 'eng') !== false):
        $machine_stats = [];
        $total_machines = 0;
        $active_machines = 0;
        $total_partlists = 0;
        $spk_with_drawing = 0;
        $total_items = 0;
        $total_bom = 0;
        $pending_bom = 0;
        $pending_partlist = 0;
        $bom_link = 'index.php?page=eng-bom';
        $pl_link = 'index.php?page=eng-partlist&view=active';
        try {
            $stmt_machine = $pdo->query("SELECT status, COUNT(*) as total FROM machines GROUP BY status");
            $machine_stats = $stmt_machine->fetchAll(PDO::FETCH_KEY_PAIR);
            $total_machines = array_sum($machine_stats);
            $active_machines = isset($machine_stats['active']) ? $machine_stats['active'] : 0;
            $total_items = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
            $total_bom = $pdo->query("SELECT COUNT(*) FROM boms")->fetchColumn();
            try {
                $total_partlists = (int)$pdo->query("SELECT COUNT(*) FROM spk_partlists")->fetchColumn();
            } catch (Exception $e_partlist) {
                $total_partlists = 0;
            }
            try {
                $spk_with_drawing = (int)$pdo->query("SELECT COUNT(*) FROM spk WHERE drawing_link IS NOT NULL AND TRIM(drawing_link) <> ''")->fetchColumn();
            } catch (Exception $e_drawing) {
                $spk_with_drawing = 0;
            }

            // Permintaan baru: BOM & Partlist (dari notifikasi)
            $uid = (int)($_SESSION['user_id'] ?? 0);
            $stmt_bom = $pdo->prepare("SELECT COUNT(*) FROM notifications
                                       WHERE is_read = 0
                                         AND link LIKE '%page=eng-bom%'
                                         AND (user_id = :uid OR target_role IN ('engineering','Engineering','eng') OR link LIKE '%page=eng-bom%')");
            $stmt_bom->execute(['uid' => $uid]);
            $pending_bom = (int)$stmt_bom->fetchColumn();

            $stmt_pl = $pdo->prepare("SELECT COUNT(*) FROM notifications
                                      WHERE is_read = 0
                                        AND link LIKE '%page=eng-partlist%'
                                        AND (user_id = :uid OR target_role IN ('engineering','Engineering','eng') OR link LIKE '%page=eng-partlist%')");
            $stmt_pl->execute(['uid' => $uid]);
            $pending_partlist = (int)$stmt_pl->fetchColumn();

            $bom_link = $pdo->query("SELECT link FROM notifications WHERE is_read = 0 AND link LIKE '%page=eng-bom%' ORDER BY created_at DESC LIMIT 1")->fetchColumn();
            $pl_link = $pdo->query("SELECT link FROM notifications WHERE is_read = 0 AND link LIKE '%page=eng-partlist%' ORDER BY created_at DESC LIMIT 1")->fetchColumn();
            $bom_link = $bom_link ?: 'index.php?page=eng-bom';
            $pl_link = $pl_link ?: 'index.php?page=eng-partlist&view=active';
        } catch (Exception $e) { dash_error('engineering', $e); }
    ?>
    <div class="row mb-4">
        <div class="col-md-4"><div class="card h-100 border-start border-4 border-info shadow-sm"><div class="card-body"><small class="text-uppercase fw-bold text-muted">Bill of Materials</small><h3 class="fw-bold text-dark mt-2"><?= $total_bom ?> Resep</h3></div></div></div>
        <div class="col-md-4"><div class="card h-100 border-start border-4 border-primary shadow-sm"><div class="card-body"><small class="text-uppercase fw-bold text-muted">Partlist &amp; Drawing</small><h3 class="fw-bold text-dark mt-2"><?= $total_partlists ?></h3><span class="badge bg-success rounded-pill"><?= $spk_with_drawing ?> Drawing</span></div></div></div>
        <div class="col-md-4"><div class="card h-100 border-start border-4 border-success shadow-sm"><div class="card-body"><small class="text-uppercase fw-bold text-muted">Total Items</small><h3 class="fw-bold text-dark mt-2"><?= $total_items ?></h3></div></div></div>
    </div>
    <div class="row mb-4">
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Status Mesin</h6>
                </div>
                <div class="card-body d-flex justify-content-center align-items-center">
                    <div style="height: 250px; width: 100%; position: relative;"><canvas id="machineChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="row g-3">
                <div class="col-12">
                    <a href="<?= dash_e(dash_safe_link($bom_link, 'index.php?page=eng-bom')) ?>" class="text-decoration-none">
                        <div class="card shadow-sm border-start border-4 border-warning h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase small text-muted fw-bold">Permintaan Baru BOM</div>
                                    <div class="display-6 fw-bold text-dark"><?= $pending_bom ?></div>
                                </div>
                                <i class="bi bi-diagram-3 fs-1 text-warning"></i>
                            </div>
                            <div class="card-footer bg-white text-end small text-muted">Klik untuk buka permintaan</div>
                        </div>
                    </a>
                </div>
                <div class="col-12">
                    <a href="<?= dash_e(dash_safe_link($pl_link, 'index.php?page=eng-partlist&view=active')) ?>" class="text-decoration-none">
                        <div class="card shadow-sm border-start border-4 border-info h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase small text-muted fw-bold">Permintaan Baru Partlist</div>
                                    <div class="display-6 fw-bold text-dark"><?= $pending_partlist ?></div>
                                </div>
                                <i class="bi bi-file-earmark-ruled fs-1 text-info"></i>
                            </div>
                            <div class="card-footer bg-white text-end small text-muted">Klik untuk buka permintaan</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script>
    const ctxMach = document.getElementById('machineChart').getContext('2d');
    new Chart(ctxMach, { type: 'doughnut', data: { labels: ['Active', 'Maintenance', 'Broken'], datasets: [{ data: [<?= $active_machines ?>, <?= isset($machine_stats['maintenance'])?$machine_stats['maintenance']:0 ?>, <?= isset($machine_stats['broken'])?$machine_stats['broken']:0 ?>], backgroundColor: ['#1cc88a', '#f6c23e', '#e74a3b'], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '70%' } });
    </script>
    <?php break; ?>


    <?php 
    // ------------------------------------------------------------------------
    // 7. DASHBOARD PPIC
    // ------------------------------------------------------------------------
    case (strpos($role, 'ppic') !== false):
        $low_stock = 0;
        $active_spk = 0;
        $labels_spk = [];
        $data_spk = [];
        try {
            $low_stock = $pdo->query("SELECT COUNT(*) FROM items WHERE current_stock <= min_stock AND item_type='raw_material'")->fetchColumn();
            $active_spk = $pdo->query("SELECT COUNT(*) FROM spk WHERE status IN ('released','in_production')")->fetchColumn();
            $sql_spk_stat = "SELECT status, COUNT(*) as total FROM spk GROUP BY status";
            $spk_stats = $pdo->query($sql_spk_stat)->fetchAll(PDO::FETCH_KEY_PAIR);
            $labels_spk = array_keys($spk_stats); $data_spk = array_values($spk_stats);
        } catch (Exception $e) { dash_error('ppic', $e); }
    ?>
    <div class="row mb-4">
        <div class="col-md-3"><div class="card text-white bg-danger mb-3"><div class="card-body"><h1><?= $low_stock ?></h1><small>Material Stok Menipis</small></div></div></div>
        <div class="col-md-3"><div class="card text-white bg-success mb-3"><div class="card-body"><h1><?= $active_spk ?></h1><small>SPK Sedang Jalan</small></div></div></div>
        <div class="col-md-6"><div class="card shadow border-info"><div class="card-header bg-info text-white">Aksi Cepat</div><div class="card-body d-grid gap-2"><a href="index.php?page=ppic-inventory" class="btn btn-outline-dark text-start"><i class="bi bi-boxes me-2"></i> Cek Inventory Control</a><a href="index.php?page=ppic-mps" class="btn btn-outline-dark text-start"><i class="bi bi-calendar-week me-2"></i> Lihat Jadwal MPS</a></div></div></div>
    </div>
    <div class="card shadow h-100"><div class="card-header bg-white fw-bold text-dark">Status SPK (Produksi)</div><div class="card-body d-flex justify-content-center"><div style="height: 250px; width: 100%; position: relative;"><canvas id="ppicSpkChart"></canvas></div></div></div>
    <script>
    new Chart(document.getElementById('ppicSpkChart'), { type: 'pie', data: { labels: <?= json_encode($labels_spk) ?>, datasets: [{ data: <?= json_encode($data_spk) ?>, backgroundColor: ['#858796', '#f6c23e', '#1cc88a', '#36b9cc', '#e74a3b'], borderWidth: 1 }] }, options: { maintainAspectRatio: false } });
    </script>
    <?php break; ?>


    <?php 
    // ------------------------------------------------------------------------
    // 8. DASHBOARD PRODUKSI (SPV/LEADER/PROD_*/SUPERVISOR)
    // ------------------------------------------------------------------------
    case (strpos($role, 'prod') !== false || strpos($role, 'spv') !== false || strpos($role, 'leader') !== false || strpos($role, 'supervisor') !== false):
        // Pengecualian: Jika role mengandung 'operator', tampilkan dashboard personal biasa (di default)
        if (strpos($role, 'operator') !== false) {
             goto default_dashboard; 
        }

        $qty_good_today = 0;
        $qty_reject_today = 0;
        $efficiency = 0;
        $spk_running = 0;
        $live_tasks = [];
        $spk_pending = 0;
        $total_pending = 0;
        $lbl_prod = [];
        $val_prod = [];
        try {
            $today = date('Y-m-d');
            $sql_output = "SELECT SUM(qty_good) as good, SUM(qty_reject) as reject FROM production_assignments WHERE DATE(end_time) = ?";
            $stmt_out = $pdo->prepare($sql_output); $stmt_out->execute([$today]); $output = $stmt_out->fetch(PDO::FETCH_ASSOC);
            $qty_good_today = $output['good'] ?? 0;
            $qty_reject_today = $output['reject'] ?? 0;
            $total_output = $qty_good_today + $qty_reject_today;
            $efficiency = ($total_output > 0) ? round(($qty_good_today / $total_output) * 100) : 0;
            $spk_running = $pdo->query("SELECT COUNT(*) FROM spk WHERE status = 'in_production'")->fetchColumn();
            
            $sql_live = "SELECT pa.*, u.fullname, s.spk_number, s.project_name
                         FROM production_assignments pa 
                         JOIN users u ON pa.operator_id = u.id 
                         JOIN spk s ON pa.spk_id = s.id
                         WHERE pa.status = 'in_progress'";
            $live_tasks = $pdo->query($sql_live)->fetchAll();

            $spk_pending = $pdo->query("SELECT COUNT(*) FROM spk WHERE status = 'waiting_mgr'")->fetchColumn();
            $total_pending = $spk_pending;

            $sql_chart_prod = "SELECT DATE(end_time) as prod_date, SUM(qty_good) as total_good FROM production_assignments WHERE status='completed' AND end_time >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(end_time) ORDER BY prod_date ASC";
            $prod_chart_data = $pdo->query($sql_chart_prod)->fetchAll(PDO::FETCH_ASSOC);
            $lbl_prod = []; $val_prod = []; foreach($prod_chart_data as $p) { $lbl_prod[] = date('d/m', strtotime($p['prod_date'])); $val_prod[] = $p['total_good']; }
        } catch (Exception $e) { dash_error('production', $e); }
    ?>
    
    <?php if($total_pending > 0): ?>
    <div class="alert alert-warning border-start border-5 border-warning shadow-sm d-flex justify-content-between align-items-center mb-4">
        <div><h5 class="alert-heading fw-bold mb-1"><i class="bi bi-bell-fill"></i> Perhatian: <?= $total_pending ?> Dokumen Menunggu Persetujuan</h5></div>
        <div class="d-flex gap-2">
            <?php if($spk_pending > 0): ?><a href="index.php?page=ppic-spk" class="btn btn-dark btn-sm fw-bold">SPK Pending (<?= $spk_pending ?>)</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-3"><div class="card h-100 bg-success text-white shadow-sm"><div class="card-body"><small class="text-uppercase fw-bold text-white-50">Output Hari Ini</small><h2 class="fw-bold mb-0"><?= $qty_good_today ?> <span class="fs-6">Unit</span></h2><small>+ <?= $qty_reject_today ?> Reject (<?= $efficiency ?>% Yield)</small></div></div></div>
        <div class="col-md-3"><div class="card h-100 border-start border-4 border-warning shadow-sm"><div class="card-body"><small class="text-uppercase fw-bold text-muted">SPK Berjalan</small><h3 class="fw-bold text-dark"><?= $spk_running ?></h3></div></div></div>
        <div class="col-md-3"><div class="card h-100 border-start border-4 border-primary shadow-sm"><div class="card-body"><small class="text-uppercase fw-bold text-muted">Operator Aktif</small><h3 class="fw-bold text-dark"><?= count($live_tasks) ?></h3></div></div></div>
        <div class="col-md-3"><div class="card h-100 bg-white shadow-sm border-0"><div class="card-body d-flex flex-column gap-2 justify-content-center"><a href="index.php?page=prod-task" class="btn btn-outline-primary btn-sm fw-bold text-start"><i class="bi bi-list-check me-2"></i> Bagi Tugas (Assign)</a><a href="index.php?page=prod-report" class="btn btn-outline-dark btn-sm fw-bold text-start"><i class="bi bi-file-bar-graph me-2"></i> Laporan Harian</a></div></div></div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-white"><h6 class="m-0 fw-bold text-primary"><i class="bi bi-broadcast"></i> Live Monitoring</h6></div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0 small align-middle">
                        <thead class="table-light"><tr><th>Operator</th><th>Proses</th><th>Project</th><th>Mulai</th></tr></thead>
                        <tbody>
                            <?php if(empty($live_tasks)): ?><tr><td colspan="4" class="text-center py-4 text-muted">Tidak ada aktivitas.</td></tr><?php else: foreach($live_tasks as $lt): ?>
                            <tr><td class="fw-bold text-primary"><?= dash_e($lt['fullname'] ?? '-') ?></td><td><?= dash_e($lt['process_name'] ?? '-') ?></td><td><?= dash_e($lt['spk_number'] ?? '-') ?><br><small class="text-muted"><?= dash_e(dash_truncate($lt['project_name'] ?? '', 20)) ?></small></td><td class="fw-bold"><?= date('H:i', strtotime($lt['start_time'])) ?></td></tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4"><div class="card shadow h-100"><div class="card-header bg-white fw-bold text-success">Output 7 Hari Terakhir</div><div class="card-body"><div style="height: 250px; position: relative; width: 100%;"><canvas id="prodOutputChart"></canvas></div></div></div></div>
    </div>
    <script>
    new Chart(document.getElementById('prodOutputChart'), { type: 'bar', data: { labels: <?= json_encode($lbl_prod) ?>, datasets: [{ label: 'Unit Good', data: <?= json_encode($val_prod) ?>, backgroundColor: '#198754', borderRadius: 4 }] }, options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } } });
    </script>
    <?php break; ?>


    <?php
    // ------------------------------------------------------------------------
    // 9. DASHBOARD QUALITY CONTROL
    // ------------------------------------------------------------------------
    case (strpos($role, 'qc') !== false):
        $inc_pending = 0;
        $prod_pending = 0;
        $inc_today = 0;
        $prod_today = 0;
        $total_ins = 0;
        $ncr_open = 0;
        try {
            $inc_pending = $pdo->query("SELECT COUNT(*) FROM goods_receipts WHERE status='qc_pending'")->fetchColumn();
            $prod_pending = $pdo->query("SELECT COUNT(*) FROM spk s LEFT JOIN qc_production qp ON s.id = qp.spk_id WHERE s.status IN ('completed', 'released', 'in_production') AND qp.id IS NULL")->fetchColumn();
            $today = date('Y-m-d');
            $inc_today = $pdo->query("SELECT COUNT(*) FROM qc_incoming WHERE qc_date = '$today'")->fetchColumn();
            $prod_today = $pdo->query("SELECT COUNT(*) FROM qc_production WHERE qc_date = '$today'")->fetchColumn();
            $total_ins = $inc_today + $prod_today;
            $ncr_open = $pdo->query("SELECT COUNT(*) FROM ncr WHERE status != 'closed'")->fetchColumn();
        } catch (Exception $e) { dash_error('qc', $e); }
    ?>
    <div class="row mb-4">
        <div class="col-md-3"><div class="card h-100 border-start border-4 border-primary shadow-sm"><div class="card-body"><small class="text-uppercase fw-bold text-muted">Antrian Incoming</small><h3 class="fw-bold text-dark mt-2"><?= $inc_pending ?> <span class="fs-6 text-muted">GR</span></h3><a href="index.php?page=qc-incoming" class="stretched-link small text-decoration-none">Periksa &rarr;</a></div></div></div>
        <div class="col-md-3"><div class="card h-100 border-start border-4 border-info shadow-sm"><div class="card-body"><small class="text-uppercase fw-bold text-muted">Antrian Produksi</small><h3 class="fw-bold text-dark mt-2"><?= $prod_pending ?> <span class="fs-6 text-muted">SPK</span></h3><a href="index.php?page=qc-production" class="stretched-link small text-decoration-none">Periksa &rarr;</a></div></div></div>
        <div class="col-md-3"><div class="card h-100 bg-success text-white shadow-sm"><div class="card-body"><small class="text-uppercase fw-bold text-white-50">Inspeksi Hari Ini</small><h3 class="fw-bold mt-2 mb-0"><?= $total_ins ?> <span class="fs-6">Item</span></h3></div></div></div>
        <div class="col-md-3"><div class="card h-100 border-start border-4 border-danger shadow-sm"><div class="card-body"><small class="text-uppercase fw-bold text-muted">NCR Open (Reject)</small><h3 class="fw-bold text-danger mt-2"><?= $ncr_open ?> <span class="fs-6 text-muted">Kasus</span></h3><a href="index.php?page=qc-ncr" class="stretched-link small text-decoration-none text-danger">Tindak Lanjut &rarr;</a></div></div></div>
    </div>
    <?php break; ?>


    <?php 
    // ------------------------------------------------------------------------
    // 10. DASHBOARD EXECUTIVE / ADMIN / MANAGER / OWNER
    // ------------------------------------------------------------------------
    case (in_array($role, ['executive', 'manager', 'owner'], true)):
        $omzet = 0;
        $spending = 0;
        $profit = 0;
        $spk_active = 0;
        $lbls = [];
        $vals = [];
        try {
            $omzet = $pdo->query("SELECT SUM(grand_total) FROM invoices WHERE status NOT IN ('cancelled','draft')")->fetchColumn() ?: 0;
            $spending = $pdo->query("SELECT SUM(grand_total) FROM purchase_orders WHERE status NOT IN ('cancelled','draft')")->fetchColumn() ?: 0;
            $profit = $omzet - $spending;
            $spk_active = $pdo->query("SELECT COUNT(*) FROM spk WHERE status IN ('released','in_production')")->fetchColumn();
            $sql_chart = "SELECT DATE_FORMAT(invoice_date, '%M') as month, SUM(grand_total) as total FROM invoices WHERE status!='cancelled' AND invoice_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(invoice_date, '%Y-%m') ORDER BY invoice_date ASC";
            $chart_data = $pdo->query($sql_chart)->fetchAll(PDO::FETCH_ASSOC);
            $lbls = []; $vals = []; foreach($chart_data as $d){ $lbls[]=$d['month']; $vals[]=$d['total']; }
        } catch (Exception $e) { dash_error('executive', $e); }
    ?>
    <div class="row mb-4">
        <div class="col-md-3"><div class="card border-start border-4 border-success shadow-sm h-100"><div class="card-body"><small class="text-uppercase fw-bold text-muted">Total Revenue</small><h3 class="fw-bold mt-2 text-success">Rp <?= number_format($omzet/1000000, 0, ',', '.') ?> Jt</h3></div></div></div>
        <div class="col-md-3"><div class="card border-start border-4 border-danger shadow-sm h-100"><div class="card-body"><small class="text-uppercase fw-bold text-muted">Total Spending</small><h3 class="fw-bold mt-2 text-danger">Rp <?= number_format($spending/1000000, 0, ',', '.') ?> Jt</h3></div></div></div>
        <div class="col-md-3"><div class="card border-start border-4 border-primary shadow-sm h-100"><div class="card-body"><small class="text-uppercase fw-bold text-muted">Gross Profit</small><h3 class="fw-bold mt-2 text-primary">Rp <?= number_format($profit/1000000, 0, ',', '.') ?> Jt</h3></div></div></div>
        <div class="col-md-3"><div class="card border-start border-4 border-warning shadow-sm h-100"><div class="card-body"><small class="text-uppercase fw-bold text-muted">Produksi Aktif</small><h3 class="fw-bold mt-2 text-dark"><?= $spk_active ?> Batch</h3></div></div></div>
    </div>

    <div class="card shadow mb-4"><div class="card-header bg-white fw-bold text-primary">Tren Penjualan (6 Bulan)</div><div class="card-body"><div style="height:300px; position:relative; width:100%"><canvas id="execChart"></canvas></div></div></div>
    <script>new Chart(document.getElementById('execChart'), { type: 'line', data: { labels: <?= json_encode($lbls) ?>, datasets: [{ label: 'Omzet', data: <?= json_encode($vals) ?>, borderColor: '#4e73df', fill: true, backgroundColor: 'rgba(78, 115, 223, 0.05)' }] }, options: { maintainAspectRatio: false } });</script>
    <?php break; ?>


    <?php 
    // ------------------------------------------------------------------------
    // 11. HRD DASHBOARD (DIPINDAHKAN KE BAWAH AGAR RAPI)
    // ------------------------------------------------------------------------
    case (strpos($role, 'hrd') !== false):
        // ... (Kode HRD Tetap)
        $total_emp = 0;
        $hadir = 0;
        $telat = 0;
        $cuti = 0;
        $total_hadir = 0;
        $percent_att = 0;
        $recent_att = [];
        try {
            $today = date('Y-m-d');
            $total_emp = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id != 1")->fetchColumn();

            $stmt_hadir = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = ? AND status='present'");
            $stmt_hadir->execute([$today]);
            $hadir = (int)$stmt_hadir->fetchColumn();

            $stmt_telat = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = ? AND status='late'");
            $stmt_telat->execute([$today]);
            $telat = (int)$stmt_telat->fetchColumn();

            $stmt_cuti = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = ? AND status='leave'");
            $stmt_cuti->execute([$today]);
            $cuti = (int)$stmt_cuti->fetchColumn();
            $total_hadir = $hadir + $telat;
            $percent_att = ($total_emp > 0) ? round(($total_hadir / $total_emp) * 100) : 0;
            $stmt_recent = $pdo->prepare("SELECT a.*, u.fullname
                                          FROM attendance a
                                          JOIN users u ON a.user_id = u.id
                                          WHERE a.date = ?
                                          ORDER BY a.clock_in DESC
                                          LIMIT 5");
            $stmt_recent->execute([$today]);
            $recent_att = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            dash_error('hrd', $e);
        }
    ?>
    <div class="row mb-4">
        <div class="col-md-3"><div class="card h-100 bg-primary text-white shadow-sm"><div class="card-body"><small class="text-uppercase fw-bold text-white-50">Total Karyawan</small><h3 class="fw-bold mt-2 mb-0"><?= $total_emp ?></h3></div></div></div>
        <div class="col-md-3"><div class="card h-100 border-start border-4 border-success shadow-sm"><div class="card-body"><div class="d-flex justify-content-between mb-2"><div class="text-secondary small fw-bold">KEHADIRAN</div><span class="badge bg-success"><?= $percent_att ?>%</span></div><h3 class="fw-bold text-dark"><?= $total_hadir ?></h3></div></div></div>
        <div class="col-md-3"><div class="card h-100 border-start border-4 border-warning shadow-sm"><div class="card-body"><small class="text-uppercase fw-bold text-muted">Terlambat</small><h3 class="fw-bold mt-2 text-warning"><?= $telat ?></h3></div></div></div>
        <div class="col-md-3"><div class="card h-100 border-start border-4 border-danger shadow-sm"><div class="card-body"><small class="text-uppercase fw-bold text-muted">Cuti / Izin</small><h3 class="fw-bold mt-2 text-danger"><?= $cuti ?></h3></div></div></div>
    </div>
    <div class="card shadow h-100"><div class="card-header bg-white d-flex justify-content-between"><h6 class="m-0 fw-bold text-secondary">Aktivitas Absensi Terkini</h6><a href="index.php?page=hrd-attendance" class="small text-decoration-none">Lihat Semua &rarr;</a></div><div class="card-body p-0"><table class="table table-hover align-middle mb-0 small"><thead class="table-light"><tr><th>Nama</th><th>Jam Masuk</th><th>Status</th></tr></thead><tbody><?php if(empty($recent_att)): ?><tr><td colspan="3" class="text-center py-4 text-muted">Belum ada data.</td></tr><?php else: foreach($recent_att as $att): ?><tr><td class="fw-bold"><?= clean($att['fullname']) ?></td><td><?= date('H:i', strtotime($att['clock_in'])) ?></td><td><span class="badge <?= (($att['status'] ?? '') === 'late') ? 'bg-warning text-dark' : 'bg-success' ?>"><?= dash_e(strtoupper((string)($att['status'] ?? ''))) ?></span></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
    <?php break; ?>

    <?php 
    // ------------------------------------------------------------------------
    // 12. DEFAULT (Operator, dll)
    // ------------------------------------------------------------------------
    default_dashboard:
    default: ?>
    <?php
    $is_operator_role = ($role == 'operator' || strpos($role, 'op_') !== false);
    $ncr_waiting = [];
    if ($is_operator_role) {
        try {
            $stmt_ncr = $pdo->prepare("SELECT ncr.id, ncr.ncr_number, ncr.qty_reject, ncr.status, i.item_name, i.item_code
                                       FROM ncr
                                       JOIN items i ON ncr.item_id = i.id
                                       WHERE ncr.status = 'waiting_responsible' AND ncr.operator_id = ?
                                       ORDER BY ncr.id DESC");
            $stmt_ncr->execute([$user_id]);
            $ncr_waiting = $stmt_ncr->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $ncr_waiting = [];
            error_log('Dashboard [default-ncr-waiting] ' . $e->getMessage());
        }
    }
    ?>
    <div class="row justify-content-center mt-5">
        <div class="col-md-8">
            <?php if($is_operator_role && !empty($ncr_waiting)): ?>
            <div class="card shadow mb-4 border-start border-4 border-danger">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-danger"><i class="bi bi-exclamation-triangle"></i> NCR Menunggu Penanggung Jawab</h6>
                    <span class="badge bg-danger"><?= count($ncr_waiting) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>No. NCR</th>
                                    <th>Barang</th>
                                    <th class="text-center">Qty Reject</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ncr_waiting as $row): ?>
                                <tr>
                                    <td class="fw-bold"><?= clean($row['ncr_number']) ?></td>
                                    <td><?= clean($row['item_code']) ?> - <?= clean($row['item_name']) ?></td>
                                    <td class="text-center text-danger fw-bold"><?= $row['qty_reject'] + 0 ?></td>
                                    <td class="text-center">
                                        <a href="index.php?page=qc-ncr&action=sign-resp&id=<?= (int)$row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-success" onclick="return confirm('Setuju dan tanda tangan sebagai penanggung jawab?')">
                                            <i class="bi bi-check-lg"></i> Setuju
                                        </a>
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#appealNcrModal_<?= (int)$row['id'] ?>">
                                            <i class="bi bi-chat-left-text"></i> Banding
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php foreach ($ncr_waiting as $row): ?>
            <div class="modal fade" id="appealNcrModal_<?= (int)$row['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="index.php?page=qc-ncr&action=appeal&id=<?= (int)$row['id'] ?>">
                            <div class="modal-header bg-danger text-white">
                                <h6 class="modal-title">Banding NCR <?= clean($row['ncr_number']) ?></h6>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                <label class="form-label">Alasan Banding</label>
                                <textarea name="appeal_note" class="form-control" rows="3" required placeholder="Jelaskan alasan banding..."></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-danger">Kirim Banding</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php
            $ncr_unassigned = [];
            if (has_permission('qc_ncr_manage')) {
                try {
                    $stmt_un = $pdo->query("SELECT ncr.id, ncr.ncr_number, ncr.qty_reject, i.item_name, i.item_code
                                            FROM ncr
                                            JOIN items i ON ncr.item_id = i.id
                                            WHERE ncr.status = 'waiting_responsible' AND (ncr.operator_id IS NULL OR ncr.operator_id = 0)
                                            ORDER BY ncr.id DESC");
                    $ncr_unassigned = $stmt_un->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $ncr_unassigned = [];
                    error_log('Dashboard [default-ncr-unassigned] ' . $e->getMessage());
                }
            }

            $operator_list = [];
            if (has_permission('qc_ncr_manage')) {
                try {
                    $stmt_ops = $pdo->query("SELECT u.id, u.fullname, r.role_name
                                             FROM users u
                                             JOIN roles r ON u.role_id = r.id
                                             WHERE r.role_slug = 'operator' OR r.role_slug LIKE 'op_%'
                                             ORDER BY u.fullname ASC");
                    $operator_list = $stmt_ops->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $operator_list = [];
                    error_log('Dashboard [default-operator-list] ' . $e->getMessage());
                }
            }
            ?>

            <?php if(!empty($ncr_unassigned)): ?>
            <div class="card shadow mb-4 border-start border-4 border-warning">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-warning"><i class="bi bi-person-plus"></i> NCR Belum Ada Penanggung Jawab</h6>
                    <span class="badge bg-warning text-dark"><?= count($ncr_unassigned) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>No. NCR</th>
                                    <th>Barang</th>
                                    <th class="text-center">Qty Reject</th>
                                    <th class="text-center">Penanggung Jawab</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ncr_unassigned as $row): ?>
                                <tr>
                                    <td class="fw-bold"><?= clean($row['ncr_number']) ?></td>
                                    <td><?= clean($row['item_code']) ?> - <?= clean($row['item_name']) ?></td>
                                    <td class="text-center text-danger fw-bold"><?= $row['qty_reject'] + 0 ?></td>
                                    <td class="text-center">
                                        <form method="POST" action="index.php?page=qc-ncr&action=assign-resp&id=<?= (int)$row['id'] ?>" class="d-flex justify-content-center gap-2">
                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                            <select name="operator_id" class="form-select form-select-sm" required style="max-width: 220px;">
                                                <option value="">-- Pilih Operator --</option>
                                                <?php foreach ($operator_list as $op): ?>
                                                    <option value="<?= (int)$op['id'] ?>"><?= clean($op['fullname']) ?> (<?= clean($op['role_name']) ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Assign</button>
                                        </form>
                                    </td>
                                    <td class="text-center">
                                        <a href="index.php?page=qc-ncr&action=edit&id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-secondary">Detail</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card shadow p-5 text-center">
                <img src="https://cdn-icons-png.flaticon.com/512/9320/9320495.png" alt="Work" style="max-width: 120px; opacity: 0.8; margin: 0 auto;">
                <h3 class="mt-4 fw-bold text-secondary">Dashboard Personal</h3>
                <p class="text-muted">Silakan pilih menu di samping untuk mulai bekerja.</p>
                <?php if($is_operator_role): ?>
                    <a href="index.php?page=prod-operator" class="btn btn-primary btn-lg mt-3 px-5 shadow"><i class="bi bi-qr-code-scan"></i> Masuk Panel Operator</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php break; ?>

<?php endswitch; ?>

<?php render_footer(); ?>
