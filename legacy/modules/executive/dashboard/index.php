<?php
// modules/executive/dashboard/index.php
render_header("Executive Dashboard");

// Inisialisasi Variabel Default (Agar tidak error jika query gagal)
$total_omzet = 0;
$total_po = 0;
$total_spk_active = 0;
$total_emp = 0;
$chart_labels = [];
$chart_values = [];
$spk_draft = 0;
$spk_process = 0;
$spk_done = 0;
$top_labels = [];
$top_values = [];
$recent_logs = [];

try {
    // ============================================================================
    // 1. DATA SCORECARDS
    // ============================================================================

    // A. Omzet (Invoice tidak cancel & tidak draft)
    $stmt = $pdo->query("SELECT SUM(grand_total) FROM invoices WHERE status NOT IN ('cancelled', 'draft')");
    $total_omzet = $stmt ? ($stmt->fetchColumn() ?: 0) : 0;

    // B. Pengeluaran (PO tidak cancel & tidak draft)
    $stmt = $pdo->query("SELECT SUM(grand_total) FROM purchase_orders WHERE status NOT IN ('cancelled', 'draft')");
    $total_po = $stmt ? ($stmt->fetchColumn() ?: 0) : 0;

    // C. Produksi Aktif (SPK Released/In Production)
    $stmt = $pdo->query("SELECT COUNT(*) FROM spk WHERE status IN ('released','in_production')");
    $total_spk_active = $stmt ? $stmt->fetchColumn() : 0;

    // D. Total Karyawan (FIX: Tabel 'users' bukan 'employees', abaikan admin ID 1)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id != 1");
    $total_emp = $stmt ? $stmt->fetchColumn() : 0;

    // ============================================================================
    // 2. DATA CHART 1: TREN PENJUALAN (6 Bulan)
    // ============================================================================
    // Cek apakah tabel invoices ada datanya
    $sql_chart = "SELECT DATE_FORMAT(invoice_date, '%M %Y') as month_label, SUM(grand_total) as total 
                  FROM invoices 
                  WHERE status NOT IN ('cancelled', 'draft') 
                  AND invoice_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                  GROUP BY DATE_FORMAT(invoice_date, '%Y-%m') 
                  ORDER BY invoice_date ASC";
    $stmt = $pdo->query($sql_chart);
    $sales_data = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach($sales_data as $d) {
        $chart_labels[] = $d['month_label'];
        $chart_values[] = $d['total'];
    }

    // ============================================================================
    // 3. DATA CHART 2: STATUS SPK
    // ============================================================================
    $sql_spk = "SELECT status, COUNT(*) as total FROM spk GROUP BY status";
    $stmt = $pdo->query($sql_spk);
    $spk_stats = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

    $spk_draft = $spk_stats['draft'] ?? 0;
    // Gabung status proses
    $spk_process = ($spk_stats['released'] ?? 0) + ($spk_stats['in_production'] ?? 0) + ($spk_stats['waiting_eng'] ?? 0) + ($spk_stats['waiting_mgr'] ?? 0);
    // Gabung status selesai
    $spk_done = ($spk_stats['completed'] ?? 0) + ($spk_stats['closed'] ?? 0);

    // ============================================================================
    // 4. DATA CHART 3: TOP 5 PRODUK
    // ============================================================================
    $sql_top = "SELECT i.item_name, SUM(soi.qty) as total_sold
                FROM sales_order_items soi
                JOIN sales_orders so ON soi.sales_order_id = so.id
                JOIN items i ON soi.item_id = i.id
                WHERE so.status != 'cancelled'
                GROUP BY i.id
                ORDER BY total_sold DESC
                LIMIT 5";
    $stmt = $pdo->query($sql_top);
    $top_items = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach($top_items as $t) {
        $top_labels[] = $t['item_name'];
        $top_values[] = $t['total_sold'];
    }

    // ============================================================================
    // 5. RECENT LOGS
    // ============================================================================
    // Cek dulu apakah tabel system_logs ada
    $check_log = $pdo->query("SHOW TABLES LIKE 'system_logs'");
    if ($check_log->rowCount() > 0) {
        $stmt_logs = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 5");
        $recent_logs = $stmt_logs ? $stmt_logs->fetchAll(PDO::FETCH_ASSOC) : [];
    }

} catch (Exception $e) {
    // Tampilkan Error jika terjadi masalah Database
    echo "<div class='alert alert-danger m-3'>
            <h5><i class='bi bi-exclamation-triangle'></i> Terjadi Kesalahan Sistem</h5>
            <p>Gagal memuat data dashboard. Detail: " . $e->getMessage() . "</p>
          </div>";
}
?>

<!-- LINK CHART.JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="row mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold"><i class="bi bi-speedometer2"></i> Executive Dashboard</h3>
        <p class="text-muted">Ringkasan performa bisnis secara real-time.</p>
    </div>
    <div class="col-md-4 text-end">
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm"><i class="bi bi-printer"></i> Print Laporan</button>
    </div>
</div>

<!-- 1. ROW SCORECARDS -->
<div class="row mb-4">
    <!-- Omzet -->
    <div class="col-md-3">
        <div class="card shadow-sm border-start border-4 border-success h-100">
            <div class="card-body">
                <small class="text-muted fw-bold text-uppercase">Total Penjualan</small>
                <h3 class="fw-bold text-dark mt-2">Rp <?= number_format($total_omzet, 0, ',', '.') ?></h3>
                <small class="text-success"><i class="bi bi-graph-up-arrow"></i> Invoice Terbit</small>
            </div>
        </div>
    </div>
    <!-- Pengeluaran PO -->
    <div class="col-md-3">
        <div class="card shadow-sm border-start border-4 border-danger h-100">
            <div class="card-body">
                <small class="text-muted fw-bold text-uppercase">Pembelian Material</small>
                <h3 class="fw-bold text-dark mt-2">Rp <?= number_format($total_po, 0, ',', '.') ?></h3>
                <small class="text-danger"><i class="bi bi-cart"></i> PO ke Vendor</small>
            </div>
        </div>
    </div>
    <!-- Produksi Aktif -->
    <div class="col-md-3">
        <div class="card shadow-sm border-start border-4 border-warning h-100">
            <div class="card-body">
                <small class="text-muted fw-bold text-uppercase">Produksi Berjalan</small>
                <h3 class="fw-bold text-dark mt-2"><?= number_format($total_spk_active) ?> <span class="fs-6 text-muted">Batch</span></h3>
                <small class="text-warning"><i class="bi bi-gear-wide-connected"></i> SPK On Progress</small>
            </div>
        </div>
    </div>
    <!-- Karyawan -->
    <div class="col-md-3">
        <div class="card shadow-sm border-start border-4 border-info h-100">
            <div class="card-body">
                <small class="text-muted fw-bold text-uppercase">Total User</small>
                <h3 class="fw-bold text-dark mt-2"><?= number_format($total_emp) ?> <span class="fs-6 text-muted">Akun</span></h3>
                <small class="text-info"><i class="bi bi-people"></i> Aktif</small>
            </div>
        </div>
    </div>
</div>

<!-- 2. ROW GRAFIK UTAMA -->
<div class="row mb-4">
    <!-- CHART 1: SALES TREND -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow h-100">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-graph-up"></i> Tren Penjualan (6 Bulan)</h6>
            </div>
            <div class="card-body">
                <!-- WRAPPER HEIGHT FIX: Membungkus canvas dengan div height tertentu agar tidak melar -->
                <div style="height: 300px; position: relative; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- CHART 2: SPK STATUS -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow h-100">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-dark"><i class="bi bi-pie-chart"></i> Status Produksi</h6>
            </div>
            <div class="card-body">
                <div style="height: 250px; position: relative;">
                    <canvas id="spkChart"></canvas>
                </div>
                <div class="mt-4 text-center small">
                    <span class="me-2"><i class="bi bi-circle-fill" style="color:#858796"></i> Draft</span>
                    <span class="me-2"><i class="bi bi-circle-fill" style="color:#f6c23e"></i> Proses</span>
                    <span class="me-2"><i class="bi bi-circle-fill" style="color:#1cc88a"></i> Selesai</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. ROW DETAIL -->
<div class="row">
    <!-- CHART 3: TOP PRODUCTS -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-info"><i class="bi bi-trophy"></i> Top 5 Produk Terlaris</h6>
            </div>
            <div class="card-body">
                 <!-- WRAPPER HEIGHT FIX -->
                 <div style="height: 300px; position: relative; width: 100%;">
                    <canvas id="productChart"></canvas>
                 </div>
            </div>
        </div>
    </div>

    <!-- RECENT ACTIVITY LOGS -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between">
                <h6 class="m-0 font-weight-bold text-secondary"><i class="bi bi-clock-history"></i> Log Aktivitas Terakhir</h6>
                <a href="index.php?page=exec-logs" class="small text-decoration-none">Lihat Semua &rarr;</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (empty($recent_logs)): ?>
                        <li class="list-group-item text-center text-muted py-4">Belum ada log aktivitas.</li>
                    <?php else: foreach($recent_logs as $log): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold text-dark"><?= clean($log['user_name']) ?></span> 
                                <span class="text-muted small"> : <?= clean($log['action']) ?></span><br>
                                <small class="text-muted" style="font-size:11px;"><?= clean($log['description']) ?></small>
                            </div>
                            <span class="badge bg-light text-dark border"><?= date('d M H:i', strtotime($log['created_at'])) ?></span>
                        </li>
                    <?php endforeach; endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Chart Global Config
Chart.defaults.font.family = "'Inter', sans-serif";

// 1. CONFIG SALES CHART
const ctxSales = document.getElementById('salesChart');
if(ctxSales) {
    new Chart(ctxSales, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Total Penjualan (Rp)',
                data: <?= json_encode($chart_values) ?>,
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                pointRadius: 4,
                pointBackgroundColor: '#4e73df',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // Penting agar ikut tinggi wrapper
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [2, 2] } },
                x: { grid: { display: false } }
            }
        }
    });
}

// 2. CONFIG SPK CHART
const ctxSpk = document.getElementById('spkChart');
if(ctxSpk) {
    new Chart(ctxSpk, {
        type: 'doughnut',
        data: {
            labels: ['Draft', 'On Process', 'Completed'],
            datasets: [{
                data: [<?= $spk_draft ?>, <?= $spk_process ?>, <?= $spk_done ?>],
                backgroundColor: ['#858796', '#f6c23e', '#1cc88a'],
                hoverBorderColor: "#fff",
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            cutout: '75%'
        }
    });
}

// 3. CONFIG PRODUCT CHART
const ctxProd = document.getElementById('productChart');
if(ctxProd) {
    new Chart(ctxProd, {
        type: 'bar',
        data: {
            labels: <?= json_encode($top_labels) ?>,
            datasets: [{
                label: 'Unit Terjual',
                data: <?= json_encode($top_values) ?>,
                backgroundColor: '#36b9cc',
                borderRadius: 4,
                barPercentage: 0.6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false, // Penting agar ikut tinggi wrapper
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } }
        }
    });
}
</script>

<?php render_footer(); ?>