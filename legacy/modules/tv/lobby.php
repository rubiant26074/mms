<?php
// modules/tv/lobby.php
// Tidak menggunakan render_header standar karena butuh Full Screen Layout khusus

// 1. Ambil Identitas Perusahaan
$comp = get_company_profile();
$company_name = isset($comp['company_name']) ? $comp['company_name'] : 'EXECUTIVE LOBBY';
$logo_path = isset($comp['logo_path']) ? $comp['logo_path'] : '';
$logo_abs = $logo_path !== '' ? mms_abs_path($logo_path) : '';
$logo_url = $logo_path !== '' ? mms_asset_url($logo_path, true) : '';
$running_text = trim($comp['running_text'] ?? '');
if ($running_text === '') {
    $running_text = 'SELAMAT DATANG DI DASHBOARD EXECUTIVE MMS - SAFETY FIRST - KERJA BERKUALITAS, TEPAT WAKTU, DAN AMAN';
}
if (function_exists('mms_ensure_sales_orders_fulfillment_source_column')) {
    mms_ensure_sales_orders_fulfillment_source_column($pdo);
}

// Logic Logo: Jika ada file logo, tampilkan gambar. Jika tidak, pakai icon default.
$header_logo = '<i class="bi bi-building text-accent" style="font-size: 3.5rem;"></i>';
if (!empty($logo_abs) && is_file($logo_abs) && $logo_url !== '') {
    // Tambahkan background putih transparan agar logo terlihat jelas di background gelap
    $header_logo = '<img src="'.htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8').'" alt="Logo" style="height: 80px; width: auto; background-color: rgba(255,255,255,0.95); padding: 5px; border-radius: 10px;">';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Dashboard - Live</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg0:#071226; --bg1:#0a1a31; --panel:#14233f; --panel2:#0f1e39;
            --line: rgba(148,163,184,.18); --muted:#94a3b8; --text:#e2e8f0;
            --cyan:#22d3ee; --green:#22c55e; --amber:#f59e0b; --blue:#3b82f6;
        }
        body {
            background:
                radial-gradient(circle at 12% 8%, rgba(34,211,238,.10), transparent 38%),
                radial-gradient(circle at 88% 12%, rgba(59,130,246,.12), transparent 42%),
                linear-gradient(155deg, var(--bg0), var(--bg1) 60%, #09162b);
            color: var(--text);
            font-family: 'Segoe UI', sans-serif;
            overflow: hidden;
            padding-bottom: 90px;
        }
        .card {
            background: linear-gradient(180deg, rgba(20,35,63,.92), rgba(12,24,45,.95));
            border: 1px solid var(--line);
            color: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 26px rgba(2,6,23,.28), inset 0 1px 0 rgba(255,255,255,.03);
            backdrop-filter: blur(6px);
        }
        .text-accent { color: #67e8f9; }
        .text-success-light { color: #86efac; }
        .text-soft { color: var(--muted); }
        .header-time { font-size: clamp(1.7rem, 2vw, 2.4rem); font-weight: 800; letter-spacing: .5px; }
        .live-pill {
            display:inline-flex; align-items:center; gap:8px;
            border-radius:999px; padding:4px 10px;
            background: rgba(34,197,94,.10); border:1px solid rgba(34,197,94,.28);
            color:#bbf7d0; font-size:.85rem; font-weight:700;
        }
        .live-dot { height: 10px; width: 10px; background-color: #ef4444; border-radius: 50%; display: inline-block; animation: blink 1s infinite; box-shadow:0 0 12px rgba(239,68,68,.6); }
        @keyframes blink { 50% { opacity: .2; } }
        .footer-fixed {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 999;
            background: rgba(0,0,0,.95); border-top: 3px solid #facc15; color: #facc15;
            padding: 8px 0; font-family: 'Courier New', Courier, monospace;
        }
        .company-title {
            font-size: clamp(1.45rem, 2.3vw, 2.45rem);
            line-height: 1.06; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;
        }
        .kpi-label { color: var(--muted); font-size: .78rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; }
        .kpi-value { font-weight: 800; line-height: 1; }
        .kpi-sub { color: #cbd5e1; font-size: .88rem; }
        .progress.bg-dark { background-color: rgba(2,6,23,.55)!important; border: 1px solid rgba(148,163,184,.12); }
        .table.table-dark { --bs-table-bg: transparent; --bs-table-striped-bg: rgba(148,163,184,.04); --bs-table-border-color: rgba(148,163,184,.10); }
        .table.table-dark th { color: var(--muted); font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; border-bottom-color: rgba(148,163,184,.16); }
        .so-code { color:#a5f3fc; font-weight:700; }
        .stage-pill {
            display:inline-block; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            border-radius:999px; padding:4px 10px; font-size:.75rem; font-weight:700; border:1px solid rgba(148,163,184,.18);
            background: rgba(148,163,184,.08); color:#e2e8f0;
        }
        .stage-pill.success { background: rgba(34,197,94,.12); color:#bbf7d0; border-color: rgba(34,197,94,.22); }
        .stage-pill.warning { background: rgba(245,158,11,.12); color:#fde68a; border-color: rgba(245,158,11,.22); }
        .stage-pill.info { background: rgba(6,182,212,.12); color:#bae6fd; border-color: rgba(6,182,212,.22); }
        .stage-pill.primary { background: rgba(59,130,246,.12); color:#bfdbfe; border-color: rgba(59,130,246,.22); }
        .chart-card-title { color:#cbd5e1; font-weight:700; letter-spacing:.03em; text-transform: uppercase; font-size:.9rem; }
        .chart-card-sub { color: var(--muted); font-size:.78rem; }
        .chart-wrap { position: relative; height: 100%; min-height: 230px; }
        .mini-chart-wrap { position: relative; height: 230px; }
        .summary-list .list-group-item {
            background: rgba(148,163,184,.04); border-color: rgba(148,163,184,.08); color: #e2e8f0;
            display:flex; justify-content:space-between; align-items:center; padding:.55rem .75rem;
        }
        .summary-dot { width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:8px; }
    </style>
</head>
<body class="p-4">

<?php
// Data Fetching
$today = date('Y-m-d');
$month = date('m');
$year = date('Y');

// Sales Bulan Ini
$stmt = $pdo->prepare("SELECT SUM(grand_total) FROM sales_orders WHERE status IN ('confirmed', 'in_production', 'completed') AND MONTH(so_date) = ? AND YEAR(so_date) = ?");
$stmt->execute([$month, $year]);
$sales_month = $stmt->fetchColumn() ?: 0;
$target = 500000000;
$pct = ($target > 0) ? round(($sales_month / $target) * 100) : 0;

// Order Hari Ini
$so_today = $pdo->query("SELECT COUNT(*) FROM sales_orders WHERE so_date = '$today'")->fetchColumn();

// Pengiriman Hari Ini
$sj_today = $pdo->query("SELECT COUNT(*) FROM delivery_notes WHERE dn_date = '$today'")->fetchColumn();

// Recent Orders (5 Terakhir) + Status Proses
$recent_so = $pdo->query("SELECT so.id, so.so_number, so.status as so_status,
                                 COALESCE(so.fulfillment_source, 'spk') as fulfillment_source,
                                 c.name as customer_name, so.grand_total,
                                 s.id as spk_id, s.spk_number, s.status as spk_status
                          FROM sales_orders so
                          JOIN customers c ON so.customer_id=c.id
                          LEFT JOIN spk s ON s.id = (
                              SELECT id FROM spk WHERE sales_order_id = so.id ORDER BY id DESC LIMIT 1
                          )
                          ORDER BY so.id DESC LIMIT 5")->fetchAll();

$spk_ids = [];
foreach ($recent_so as $row) {
    if (!empty($row['spk_id'])) $spk_ids[] = (int)$row['spk_id'];
}

$tasks_by_spk = [];
if (!empty($spk_ids)) {
    $in = implode(',', array_fill(0, count($spk_ids), '?'));
    $sql_tasks = "SELECT spk_id, process_name, status
                  FROM production_assignments
                  WHERE spk_id IN ($in)
                  ORDER BY FIELD(status,'in_progress','assigned','pending','hold','completed'), id DESC";
    $stmt_tasks = $pdo->prepare($sql_tasks);
    $stmt_tasks->execute($spk_ids);
    while ($t = $stmt_tasks->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int)$t['spk_id'];
        if (!isset($tasks_by_spk[$sid])) $tasks_by_spk[$sid] = [];
        $tasks_by_spk[$sid][] = $t;
    }
}

$qc_by_spk = [];
if (!empty($spk_ids)) {
    $in = implode(',', array_fill(0, count($spk_ids), '?'));
    $sql_qc = "SELECT qp.*
               FROM qc_production qp
               JOIN (SELECT spk_id, MAX(id) as max_id FROM qc_production GROUP BY spk_id) qmax
                 ON qp.id = qmax.max_id
               WHERE qp.spk_id IN ($in)";
    $stmt_qc = $pdo->prepare($sql_qc);
    $stmt_qc->execute($spk_ids);
    while ($q = $stmt_qc->fetch(PDO::FETCH_ASSOC)) {
        $qc_by_spk[(int)$q['spk_id']] = $q;
    }
}

function tv_upper($text) {
    if (function_exists('mb_strtoupper')) return mb_strtoupper((string)$text, 'UTF-8');
    return strtoupper((string)$text);
}

function tv_current_stage(array $row, array $task_rows = [], ?array $qc_row = null) {
    $spk_status = strtolower((string)($row['spk_status'] ?? ''));
    $so_status = strtolower((string)($row['so_status'] ?? ''));
    $fulfillment_source = function_exists('mms_normalize_sales_order_fulfillment_source')
        ? mms_normalize_sales_order_fulfillment_source($row['fulfillment_source'] ?? 'spk')
        : 'spk';

    if ($so_status === 'draft' || $so_status === 'waiting_approval') {
        return 'DEPT SALES - MENUNGGU APPROVAL';
    }
    if ($so_status === 'completed') {
        return 'SELESAI / CLOSED';
    }
    if ($so_status === 'delivered') {
        return 'DEPT GUDANG - PENGIRIMAN';
    }
    if ($fulfillment_source === 'fg_stock') {
        return 'DEPT GUDANG - FG STOCK / SIAP KIRIM';
    }
    if ($spk_status === '') {
        return 'DEPT PPIC - MENUNGGU SPK';
    }
    if (in_array($spk_status, ['draft', 'preliminary', 'waiting_eng'], true)) {
        return 'DEPT ENGINEERING - DRAWING & PARTLIST';
    }
    if (in_array($spk_status, ['waiting_mgr', 'final'], true)) {
        return 'DEPT PPIC - APPROVAL';
    }
    if (in_array($spk_status, ['released', 'in_production'], true)) {
        $proc = '';
        foreach ($task_rows as $t) {
            $st = strtolower((string)($t['status'] ?? ''));
            if ($st === 'in_progress') { $proc = (string)$t['process_name']; break; }
        }
        if ($proc === '') {
            foreach ($task_rows as $t) {
                $st = strtolower((string)($t['status'] ?? ''));
                if (in_array($st, ['assigned','pending','hold'], true)) { $proc = (string)$t['process_name']; break; }
            }
        }
        return $proc !== '' ? ('DEPT PRODUKSI - ' . $proc) : 'DEPT PRODUKSI - PERSIAPAN';
    }
    if ($spk_status === 'completed') {
        return 'DEPT QC - INSPECTION QC PRODUKSI';
    }
    if ($spk_status === 'closed') {
        return 'SELESAI / CLOSED';
    }
    if (is_array($qc_row) && !empty($qc_row)) {
        return 'DEPT QC - INSPECTION QC PRODUKSI';
    }
    return 'DEPT PPIC';
}

foreach ($recent_so as &$row) {
    $task_rows = !empty($row['spk_id']) ? ($tasks_by_spk[(int)$row['spk_id']] ?? []) : [];
    $qc_row = !empty($row['spk_id']) ? ($qc_by_spk[(int)$row['spk_id']] ?? null) : null;
    $row['stage_text'] = tv_current_stage($row, $task_rows, $qc_row);
}
unset($row);

function tv_labelize_status($status) {
    $s = trim((string)$status);
    if ($s === '') return '-';
    return ucwords(str_replace('_', ' ', strtolower($s)));
}

function tv_stage_badge_class($stageText) {
    $t = strtolower((string)$stageText);
    if (strpos($t, 'closed') !== false || strpos($t, 'selesai') !== false) return 'success';
    if (strpos($t, 'qc') !== false) return 'warning';
    if (strpos($t, 'produksi') !== false) return 'info';
    if (strpos($t, 'gudang') !== false) return 'primary';
    return 'secondary';
}

$open_so_count = 0;
$completed_month = 0;
$delivery_rate_pct = 0;
$fg_stock_month = 0;
$spk_month = 0;
$month_labels = [];
$month_so_counts = [];
$month_sj_counts = [];
$daily_labels = [];
$daily_so_counts = [];
$daily_sj_counts = [];
$status_labels = [];
$status_counts = [];
$status_colors = ['#22c55e', '#06b6d4', '#f59e0b', '#ef4444', '#8b5cf6', '#3b82f6', '#64748b', '#14b8a6'];

for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime(date('Y-m-01') . " -{$i} month");
    $month_labels[] = date('M y', $ts);
    $month_so_counts[] = 0;
    $month_sj_counts[] = 0;
}
for ($i = 6; $i >= 0; $i--) {
    $ts = strtotime($today . " -{$i} day");
    $daily_labels[] = date('d M', $ts);
    $daily_so_counts[] = 0;
    $daily_sj_counts[] = 0;
}

try {
    $open_so_count = (int)($pdo->query("SELECT COUNT(*) FROM sales_orders WHERE COALESCE(status,'') NOT IN ('completed','closed','cancelled')")->fetchColumn() ?: 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sales_orders WHERE status IN ('completed','closed') AND MONTH(so_date)=? AND YEAR(so_date)=?");
    $stmt->execute([$month, $year]);
    $completed_month = (int)($stmt->fetchColumn() ?: 0);

    $stmt = $pdo->prepare("SELECT COALESCE(fulfillment_source,'spk') src, COUNT(*) total FROM sales_orders WHERE MONTH(so_date)=? AND YEAR(so_date)=? GROUP BY COALESCE(fulfillment_source,'spk')");
    $stmt->execute([$month, $year]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $src = strtolower((string)($r['src'] ?? 'spk'));
        $cnt = (int)($r['total'] ?? 0);
        if ($src === 'fg_stock') $fg_stock_month += $cnt; else $spk_month += $cnt;
    }

    $month_idx = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime(date('Y-m-01') . " -{$i} month");
        $month_idx[date('Y-m', $ts)] = 5 - $i;
    }
    $stmt = $pdo->query("SELECT DATE_FORMAT(so_date,'%Y-%m') ym, COUNT(*) total FROM sales_orders WHERE so_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(so_date,'%Y-%m') ORDER BY ym");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ym = (string)($r['ym'] ?? '');
        if (isset($month_idx[$ym])) $month_so_counts[$month_idx[$ym]] = (int)($r['total'] ?? 0);
    }
    $stmt = $pdo->query("SELECT DATE_FORMAT(dn_date,'%Y-%m') ym, COUNT(*) total FROM delivery_notes WHERE dn_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(dn_date,'%Y-%m') ORDER BY ym");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ym = (string)($r['ym'] ?? '');
        if (isset($month_idx[$ym])) $month_sj_counts[$month_idx[$ym]] = (int)($r['total'] ?? 0);
    }

    $daily_idx = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime($today . " -{$i} day"));
        $daily_idx[$d] = 6 - $i;
    }
    $stmt = $pdo->query("SELECT so_date d, COUNT(*) total FROM sales_orders WHERE so_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY so_date ORDER BY so_date");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = (string)($r['d'] ?? '');
        if (isset($daily_idx[$d])) $daily_so_counts[$daily_idx[$d]] = (int)($r['total'] ?? 0);
    }
    $stmt = $pdo->query("SELECT dn_date d, COUNT(*) total FROM delivery_notes WHERE dn_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY dn_date ORDER BY dn_date");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = (string)($r['d'] ?? '');
        if (isset($daily_idx[$d])) $daily_sj_counts[$daily_idx[$d]] = (int)($r['total'] ?? 0);
    }

    $stmt = $pdo->prepare("SELECT COALESCE(status,'unknown') st, COUNT(*) total FROM sales_orders WHERE MONTH(so_date)=? AND YEAR(so_date)=? GROUP BY COALESCE(status,'unknown') ORDER BY total DESC");
    $stmt->execute([$month, $year]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status_labels[] = tv_labelize_status($r['st'] ?? 'unknown');
        $status_counts[] = (int)($r['total'] ?? 0);
    }
} catch (Throwable $e) {
    // TV lobby tetap tampil meski query ringkasan tambahan gagal.
}

$total_orders_month = array_sum($status_counts);
if ($total_orders_month > 0) {
    $delivery_rate_pct = (int)round(($completed_month / $total_orders_month) * 100);
}
$avg_order_week = count($daily_so_counts) ? round(array_sum($daily_so_counts) / max(1, count($daily_so_counts)), 1) : 0;
$avg_ship_week = count($daily_sj_counts) ? round(array_sum($daily_sj_counts) / max(1, count($daily_sj_counts)), 1) : 0;
$peak_order_day = '-';
if (!empty($daily_so_counts)) {
    $mx = max($daily_so_counts);
    $ix = array_search($mx, $daily_so_counts, true);
    if ($ix !== false && isset($daily_labels[$ix])) $peak_order_day = $daily_labels[$ix] . ' (' . (int)$mx . ' SO)';
}
?>

<div class="container-fluid h-100 d-flex flex-column">
    <div class="row mb-3 align-items-center">
        <div class="col-8 d-flex align-items-center">
            <div class="me-4"><?= $header_logo ?></div>
            <div class="w-100">
                <h1 class="company-title fw-bold mb-1 text-white" style="letter-spacing: 0.4px;"><?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') ?></h1>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-soft fs-5">TV Lobby Monitoring</span>
                    <span class="live-pill"><span class="live-dot"></span> System Online</span>
                </div>
            </div>
        </div>
        <div class="col-4 text-end">
            <div id="clock" class="header-time text-accent"></div>
            <div id="date" class="text-soft" style="font-size:1rem;"></div>
        </div>
    </div>

    <div class="row mb-3 g-3">
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 p-3">
                <div class="card-body p-0">
                    <div class="kpi-label mb-2">Sales Achievement</div>
                    <div class="d-flex justify-content-between align-items-end mb-3">
                        <div class="display-4 fw-bold text-success-light"><?= (int)$pct ?>%</div>
                        <i class="bi bi-graph-up-arrow fs-2 text-success"></i>
                    </div>
                    <div class="progress bg-dark" style="height: 12px;">
                        <div class="progress-bar bg-success" style="width: <?= max(0, min(100, (int)$pct)) ?>%"></div>
                    </div>
                    <div class="kpi-sub mt-2">Pencapaian target bulanan berdasarkan nilai SO</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 p-3">
                <div class="card-body p-0">
                    <div class="kpi-label mb-2">Aktivitas Hari Ini</div>
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2" style="border-color: rgba(148,163,184,.16)!important;">
                        <span class="fs-5"><i class="bi bi-cart-plus text-warning me-1"></i> Order Masuk</span>
                        <span class="fs-2 fw-bold"><?= (int)$so_today ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fs-5"><i class="bi bi-truck text-info me-1"></i> Pengiriman</span>
                        <span class="fs-2 fw-bold"><?= (int)$sj_today ?></span>
                    </div>
                    <div class="kpi-sub">Peak 7 hari: <?= htmlspecialchars($peak_order_day, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 p-3">
                <div class="card-body p-0">
                    <div class="kpi-label mb-2">Progress Fulfillment</div>
                    <div class="d-flex justify-content-between align-items-end mb-2">
                        <div class="display-4 fw-bold text-accent"><?= (int)$delivery_rate_pct ?>%</div>
                        <i class="bi bi-check2-circle fs-2 text-info"></i>
                    </div>
                    <div class="kpi-sub mb-1">SO selesai bulan ini: <strong><?= (int)$completed_month ?></strong> / <?= (int)$total_orders_month ?></div>
                    <div class="kpi-sub">Open SO aktif: <strong><?= (int)$open_so_count ?></strong></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100 p-3">
                <div class="card-body p-0">
                    <div class="kpi-label mb-2">Jalur Pemenuhan (Bulan Ini)</div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fs-5">SPK</span>
                        <span class="fs-3 fw-bold"><?= (int)$spk_month ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fs-5">FG Stock</span>
                        <span class="fs-3 fw-bold text-warning"><?= (int)$fg_stock_month ?></span>
                    </div>
                    <div class="kpi-sub">Total SO bulan ini: <strong><?= (int)($spk_month + $fg_stock_month) ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 flex-grow-1">
        <div class="col-xl-7 d-flex flex-column gap-3">
            <div class="card p-3 flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="chart-card-title">Trend Order & Pengiriman (6 Bulan)</div>
                        <div class="chart-card-sub">Gambaran ritme bisnis dan kemampuan pengiriman</div>
                    </div>
                    <span class="badge text-bg-dark border border-secondary">Live</span>
                </div>
                <div class="chart-wrap"><canvas id="chartMonthlyFlow"></canvas></div>
            </div>
            <div class="card p-3 flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="chart-card-title">Aktivitas 7 Hari Terakhir</div>
                        <div class="chart-card-sub">Perbandingan SO masuk vs pengiriman per hari</div>
                    </div>
                    <span class="badge text-bg-dark border border-secondary">Avg SO <?= htmlspecialchars((string)$avg_order_week, ENT_QUOTES, 'UTF-8') ?>/hari</span>
                </div>
                <div class="chart-wrap"><canvas id="chartDailyActivity"></canvas></div>
            </div>
        </div>

        <div class="col-xl-5 d-flex flex-column gap-3">
            <div class="card p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="chart-card-title">Komposisi Status SO (Bulan Ini)</div>
                        <div class="chart-card-sub">Status order aktif dan selesai</div>
                    </div>
                    <span class="badge text-bg-dark border border-secondary"><?= (int)$total_orders_month ?> SO</span>
                </div>
                <div class="row g-2 align-items-stretch">
                    <div class="col-md-5">
                        <div class="mini-chart-wrap"><canvas id="chartStatusDonut"></canvas></div>
                    </div>
                    <div class="col-md-7">
                        <ul class="list-group summary-list">
                            <?php if (!empty($status_labels)): ?>
                                <?php foreach ($status_labels as $i => $lbl): ?>
                                    <li class="list-group-item">
                                        <span><span class="summary-dot" style="background:<?= htmlspecialchars($status_colors[$i % count($status_colors)], ENT_QUOTES, 'UTF-8') ?>;"></span><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></span>
                                        <strong><?= (int)($status_counts[$i] ?? 0) ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item"><span>Belum ada data bulan ini</span><strong>0</strong></li>
                            <?php endif; ?>
                            <li class="list-group-item"><span>Rata-rata Pengiriman 7 Hari</span><strong><?= htmlspecialchars((string)$avg_ship_week, ENT_QUOTES, 'UTF-8') ?>/hari</strong></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card p-3 flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="chart-card-title">Recent Orders</div>
                        <div class="chart-card-sub">Status terakhir proses SO (tampil untuk tamu/monitoring)</div>
                    </div>
                    <span class="badge text-bg-dark border border-secondary">Last 5</span>
                </div>
                <div class="table-responsive flex-grow-1">
                    <table class="table table-dark table-striped table-sm mb-0">
                        <thead>
                            <tr>
                                <th>No. SO</th>
                                <th>Customer</th>
                                <th>Status SO</th>
                                <th>Status Terakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($recent_so)): foreach ($recent_so as $so): ?>
                            <?php $stageClass = tv_stage_badge_class($so['stage_text'] ?? ''); ?>
                            <tr>
                                <td class="so-code"><?= htmlspecialchars($so['so_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($so['customer_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars(tv_labelize_status($so['so_status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="stage-pill <?= htmlspecialchars($stageClass, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($so['stage_text'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($so['stage_text'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="4" class="text-center text-secondary py-4">Belum ada data Sales Order.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="footer-fixed">
    <marquee behavior="scroll" direction="left" scrollamount="10" class="fs-4 fw-bold">
        <i class="bi bi-broadcast me-2"></i> <?= htmlspecialchars(strtoupper($running_text)) ?>
    </marquee>
</div>

<script>
    const TV_LOBBY = {
        monthLabels: <?= json_encode(array_values($month_labels), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        monthSoCounts: <?= json_encode(array_values($month_so_counts)) ?>,
        monthSjCounts: <?= json_encode(array_values($month_sj_counts)) ?>,
        dailyLabels: <?= json_encode(array_values($daily_labels), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        dailySoCounts: <?= json_encode(array_values($daily_so_counts)) ?>,
        dailySjCounts: <?= json_encode(array_values($daily_sj_counts)) ?>,
        statusLabels: <?= json_encode(array_values($status_labels), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        statusCounts: <?= json_encode(array_values($status_counts)) ?>,
        statusColors: <?= json_encode(array_values($status_colors)) ?>
    };

    // 1. Clock
    function updateTime() {
        const now = new Date();
        document.getElementById('clock').innerText = now.toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit', second:'2-digit'});
        document.getElementById('date').innerText = now.toLocaleDateString('id-ID', {weekday: 'long', day:'numeric', month:'long', year:'numeric'});
    }
    setInterval(updateTime, 1000); updateTime();

    const chartGrid = 'rgba(148,163,184,0.14)';
    const tickColor = '#cbd5e1';
    const labelColor = '#e2e8f0';
    function baseOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: labelColor, usePointStyle: true, pointStyle: 'circle', boxWidth: 10 }
                },
                tooltip: {
                    backgroundColor: 'rgba(15,23,42,0.95)',
                    borderColor: 'rgba(148,163,184,0.2)',
                    borderWidth: 1,
                    titleColor: '#fff',
                    bodyColor: '#e2e8f0'
                }
            },
            scales: {
                x: { grid: { color: 'rgba(148,163,184,0.06)' }, ticks: { color: tickColor } },
                y: { beginAtZero: true, grid: { color: chartGrid }, ticks: { color: tickColor, precision: 0 } }
            }
        };
    }

    // 2. Monthly trend chart (real data)
    const monthlyEl = document.getElementById('chartMonthlyFlow');
    if (monthlyEl) {
        const g = monthlyEl.getContext('2d').createLinearGradient(0, 0, 0, 260);
        g.addColorStop(0, 'rgba(34,211,238,0.28)');
        g.addColorStop(1, 'rgba(34,211,238,0.02)');
        new Chart(monthlyEl, {
            type: 'line',
            data: {
                labels: TV_LOBBY.monthLabels,
                datasets: [
                    {
                        label: 'Sales Order',
                        data: TV_LOBBY.monthSoCounts,
                        borderColor: '#22d3ee',
                        backgroundColor: g,
                        fill: true,
                        borderWidth: 3,
                        tension: .35,
                        pointRadius: 3
                    },
                    {
                        label: 'Pengiriman',
                        data: TV_LOBBY.monthSjCounts,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34,197,94,0.05)',
                        fill: false,
                        borderWidth: 3,
                        tension: .3,
                        pointRadius: 3
                    }
                ]
            },
            options: baseOptions()
        });
    }

    // 3. Daily activity chart (real data)
    const dailyEl = document.getElementById('chartDailyActivity');
    if (dailyEl) {
        const opts = baseOptions();
        new Chart(dailyEl, {
            type: 'bar',
            data: {
                labels: TV_LOBBY.dailyLabels,
                datasets: [
                    {
                        label: 'SO Masuk',
                        data: TV_LOBBY.dailySoCounts,
                        backgroundColor: 'rgba(59,130,246,0.78)',
                        borderColor: '#3b82f6',
                        borderRadius: 8,
                        borderWidth: 1
                    },
                    {
                        label: 'Pengiriman',
                        data: TV_LOBBY.dailySjCounts,
                        backgroundColor: 'rgba(245,158,11,0.78)',
                        borderColor: '#f59e0b',
                        borderRadius: 8,
                        borderWidth: 1
                    }
                ]
            },
            options: opts
        });
    }

    // 4. SO status composition (real data)
    const donutEl = document.getElementById('chartStatusDonut');
    if (donutEl) {
        const hasStatus = Array.isArray(TV_LOBBY.statusCounts) && TV_LOBBY.statusCounts.length > 0;
        new Chart(donutEl, {
            type: 'doughnut',
            data: {
                labels: hasStatus ? TV_LOBBY.statusLabels : ['Belum ada data'],
                datasets: [{
                    data: hasStatus ? TV_LOBBY.statusCounts : [1],
                    backgroundColor: hasStatus ? TV_LOBBY.statusColors.slice(0, TV_LOBBY.statusCounts.length) : ['rgba(148,163,184,0.35)'],
                    borderColor: 'rgba(15,23,42,0.95)',
                    borderWidth: 2,
                    hoverOffset: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,0.95)',
                        borderColor: 'rgba(148,163,184,0.2)',
                        borderWidth: 1,
                        titleColor: '#fff',
                        bodyColor: '#e2e8f0'
                    }
                }
            }
        });
    }

    // 5. Auto Refresh (Every 5 minutes)
    setTimeout(function(){ location.reload(); }, 300000);
</script>
</body>
</html>
