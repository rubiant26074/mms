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
        :root { --bg0:#071226; --bg1:#0a1a31; --line: rgba(148,163,184,.18); --muted:#94a3b8; --text:#e2e8f0; }
        body { background: radial-gradient(circle at 12% 8%, rgba(34,211,238,.10), transparent 38%), radial-gradient(circle at 88% 12%, rgba(59,130,246,.12), transparent 42%), linear-gradient(155deg, var(--bg0), var(--bg1) 60%, #09162b); color: var(--text); font-family:'Segoe UI',sans-serif; overflow:hidden; padding:16px 16px 90px; }
        .card { background: linear-gradient(180deg, rgba(20,35,63,.92), rgba(12,24,45,.95)); border:1px solid var(--line); color:#fff; border-radius:16px; box-shadow:0 10px 26px rgba(2,6,23,.28), inset 0 1px 0 rgba(255,255,255,.03); backdrop-filter: blur(6px); }
        .text-accent { color:#67e8f9; } .text-success-light { color:#86efac; } .text-soft { color: var(--muted); }
        .header-time { font-size: clamp(1.7rem, 2vw, 2.4rem); font-weight:800; }
        .live-pill { display:inline-flex; align-items:center; gap:8px; border-radius:999px; padding:4px 10px; background:rgba(34,197,94,.10); border:1px solid rgba(34,197,94,.28); color:#bbf7d0; font-size:.85rem; font-weight:700; }
        .live-dot { height:10px; width:10px; background-color:#ef4444; border-radius:50%; display:inline-block; animation:blink 1s infinite; box-shadow:0 0 12px rgba(239,68,68,.6); }
        @keyframes blink { 50% { opacity:.2; } }
        .footer-fixed { position:fixed; left:0; right:0; bottom:0; z-index:999; background:rgba(0,0,0,.95); border-top:3px solid #facc15; color:#facc15; padding:8px 0; font-family:'Courier New',Courier,monospace; }
        .company-title { font-size: clamp(1.45rem, 2.3vw, 2.45rem); line-height:1.06; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
        .kpi-label { color: var(--muted); font-size:.78rem; text-transform:uppercase; letter-spacing:.08em; font-weight:700; }
        .kpi-sub { color:#cbd5e1; font-size:.88rem; }
        .progress.bg-dark { background-color: rgba(2,6,23,.55)!important; border:1px solid rgba(148,163,184,.12); }
        .table.table-dark { --bs-table-bg: transparent; --bs-table-striped-bg: rgba(148,163,184,.04); --bs-table-border-color: rgba(148,163,184,.10); }
        .table.table-dark th { color: var(--muted); font-size:.78rem; text-transform:uppercase; letter-spacing:.06em; border-bottom-color: rgba(148,163,184,.16); }
        .so-code { color:#a5f3fc; font-weight:700; }
        .stage-pill { display:inline-block; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; border-radius:999px; padding:4px 10px; font-size:.75rem; font-weight:700; border:1px solid rgba(148,163,184,.18); background:rgba(148,163,184,.08); color:#e2e8f0; }
        .stage-pill.success { background: rgba(34,197,94,.12); color:#bbf7d0; border-color: rgba(34,197,94,.22); }
        .stage-pill.warning { background: rgba(245,158,11,.12); color:#fde68a; border-color: rgba(245,158,11,.22); }
        .stage-pill.info { background: rgba(6,182,212,.12); color:#bae6fd; border-color: rgba(6,182,212,.22); }
        .stage-pill.primary { background: rgba(59,130,246,.12); color:#bfdbfe; border-color: rgba(59,130,246,.22); }
        .chart-card-title { color:#cbd5e1; font-weight:700; letter-spacing:.03em; text-transform:uppercase; font-size:.9rem; }
        .chart-card-sub { color: var(--muted); font-size:.78rem; }
        .chart-wrap, .mini-chart-wrap { position:relative; height:100%; min-height:230px; }
        .summary-list .list-group-item { background: rgba(148,163,184,.04); border-color: rgba(148,163,184,.08); color:#e2e8f0; display:flex; justify-content:space-between; align-items:center; padding:.55rem .75rem; }
        .summary-dot { width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:8px; }
    </style>
</head>
<body>
<div class="container-fluid h-100 d-flex flex-column">
    <div class="row mb-3 align-items-center">
        <div class="col-8 d-flex align-items-center">
            <div class="me-4">
                @if($company->logo_path)
                    <img src="{{ asset($company->logo_path) }}" alt="Logo" style="height: 80px; width: auto; background-color: rgba(255,255,255,0.95); padding: 5px; border-radius: 10px;">
                @else
                    <i class="bi bi-building text-accent" style="font-size: 3.5rem;"></i>
                @endif
            </div>
            <div class="w-100">
                <h1 class="company-title fw-bold mb-1 text-white" style="letter-spacing: 0.4px;">{{ $companyName }}</h1>
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
        <div class="col-xl-3 col-md-6"><div class="card h-100 p-3"><div class="card-body p-0"><div class="kpi-label mb-2">Sales Achievement</div><div class="d-flex justify-content-between align-items-end mb-3"><div class="display-4 fw-bold text-success-light">{{ (int) $pct }}%</div><i class="bi bi-graph-up-arrow fs-2 text-success"></i></div><div class="progress bg-dark" style="height: 12px;"><div class="progress-bar bg-success" style="width: {{ max(0, min(100, (int) $pct)) }}%"></div></div><div class="kpi-sub mt-2">Pencapaian target bulanan berdasarkan nilai SO</div></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card h-100 p-3"><div class="card-body p-0"><div class="kpi-label mb-2">Aktivitas Hari Ini</div><div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2" style="border-color: rgba(148,163,184,.16)!important;"><span class="fs-5"><i class="bi bi-cart-plus text-warning me-1"></i> Order Masuk</span><span class="fs-2 fw-bold">{{ $soToday }}</span></div><div class="d-flex justify-content-between align-items-center mb-2"><span class="fs-5"><i class="bi bi-truck text-info me-1"></i> Pengiriman</span><span class="fs-2 fw-bold">{{ $sjToday }}</span></div><div class="kpi-sub">Peak 7 hari: {{ $peakOrderDay }}</div></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card h-100 p-3"><div class="card-body p-0"><div class="kpi-label mb-2">Progress Fulfillment</div><div class="d-flex justify-content-between align-items-end mb-2"><div class="display-4 fw-bold text-accent">{{ $deliveryRatePct }}%</div><i class="bi bi-check2-circle fs-2 text-info"></i></div><div class="kpi-sub mb-1">SO selesai bulan ini: <strong>{{ $completedMonth }}</strong> / {{ $totalOrdersMonth }}</div><div class="kpi-sub">Open SO aktif: <strong>{{ $openSoCount }}</strong></div></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card h-100 p-3"><div class="card-body p-0"><div class="kpi-label mb-2">Jalur Pemenuhan (Bulan Ini)</div><div class="d-flex justify-content-between align-items-center mb-2"><span class="fs-5">SPK</span><span class="fs-3 fw-bold">{{ $spkMonth }}</span></div><div class="d-flex justify-content-between align-items-center mb-2"><span class="fs-5">FG Stock</span><span class="fs-3 fw-bold text-warning">{{ $fgStockMonth }}</span></div><div class="kpi-sub">Total SO bulan ini: <strong>{{ $spkMonth + $fgStockMonth }}</strong></div></div></div></div>
    </div>

    <div class="row g-3 flex-grow-1">
        <div class="col-xl-7 d-flex flex-column gap-3">
            <div class="card p-3 flex-grow-1"><div class="d-flex justify-content-between align-items-center mb-2"><div><div class="chart-card-title">Trend Order & Pengiriman (6 Bulan)</div><div class="chart-card-sub">Gambaran ritme bisnis dan kemampuan pengiriman</div></div><span class="badge text-bg-dark border border-secondary">Live</span></div><div class="chart-wrap"><canvas id="chartMonthlyFlow"></canvas></div></div>
            <div class="card p-3 flex-grow-1"><div class="d-flex justify-content-between align-items-center mb-2"><div><div class="chart-card-title">Aktivitas 7 Hari Terakhir</div><div class="chart-card-sub">Perbandingan SO masuk vs pengiriman per hari</div></div><span class="badge text-bg-dark border border-secondary">Avg SO {{ $avgOrderWeek }}/hari</span></div><div class="chart-wrap"><canvas id="chartDailyActivity"></canvas></div></div>
        </div>

        <div class="col-xl-5 d-flex flex-column gap-3">
            <div class="card p-3">
                <div class="d-flex justify-content-between align-items-center mb-2"><div><div class="chart-card-title">Komposisi Status SO (Bulan Ini)</div><div class="chart-card-sub">Status order aktif dan selesai</div></div><span class="badge text-bg-dark border border-secondary">{{ $totalOrdersMonth }} SO</span></div>
                <div class="row g-2 align-items-stretch">
                    <div class="col-md-5"><div class="mini-chart-wrap"><canvas id="chartStatusDonut"></canvas></div></div>
                    <div class="col-md-7">
                        <ul class="list-group summary-list">
                            @forelse($statusLabels as $i => $label)
                                <li class="list-group-item"><span><span class="summary-dot" style="background:{{ $statusColors[$i % count($statusColors)] }};"></span>{{ $label }}</span><strong>{{ (int) ($statusCounts[$i] ?? 0) }}</strong></li>
                            @empty
                                <li class="list-group-item"><span>Belum ada data bulan ini</span><strong>0</strong></li>
                            @endforelse
                            <li class="list-group-item"><span>Rata-rata Pengiriman 7 Hari</span><strong>{{ $avgShipWeek }}/hari</strong></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card p-3 flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-2"><div><div class="chart-card-title">Recent Orders</div><div class="chart-card-sub">Status terakhir proses SO (tampil untuk tamu/monitoring)</div></div><span class="badge text-bg-dark border border-secondary">Last 5</span></div>
                <div class="table-responsive flex-grow-1">
                    <table class="table table-dark table-striped table-sm mb-0">
                        <thead><tr><th>No. SO</th><th>Customer</th><th>Status SO</th><th>Status Terakhir</th></tr></thead>
                        <tbody>
                        @forelse($recentSo as $so)
                            <tr>
                                <td class="so-code">{{ $so->so_number ?? '-' }}</td>
                                <td>{{ $so->customer_name ?? '-' }}</td>
                                <td><span class="badge bg-secondary">{{ ucwords(str_replace('_', ' ', strtolower((string) ($so->so_status ?? '-')))) }}</span></td>
                                <td><span class="stage-pill {{ $so->stage_class ?? 'secondary' }}" title="{{ $so->stage_text ?? '-' }}">{{ $so->stage_text ?? '-' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">Belum ada data Sales Order.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="footer-fixed">
    <marquee behavior="scroll" direction="left" scrollamount="10" class="fs-4 fw-bold"><i class="bi bi-broadcast me-2"></i> {{ strtoupper($runningText) }}</marquee>
</div>

<script>
const TV_LOBBY = {
    monthLabels: @json(array_values($monthLabels)),
    monthSoCounts: @json(array_values($monthSoCounts)),
    monthSjCounts: @json(array_values($monthSjCounts)),
    dailyLabels: @json(array_values($dailyLabels)),
    dailySoCounts: @json(array_values($dailySoCounts)),
    dailySjCounts: @json(array_values($dailySjCounts)),
    statusLabels: @json(array_values($statusLabels)),
    statusCounts: @json(array_values($statusCounts)),
    statusColors: @json(array_values($statusColors))
};
function updateTime() {
    const now = new Date();
    document.getElementById('clock').innerText = now.toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit', second:'2-digit'});
    document.getElementById('date').innerText = now.toLocaleDateString('id-ID', {weekday: 'long', day:'numeric', month:'long', year:'numeric'});
}
setInterval(updateTime, 1000); updateTime();
const chartGrid = 'rgba(148,163,184,0.14)', tickColor = '#cbd5e1', labelColor = '#e2e8f0';
function baseOptions() {
    return { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ labels:{ color:labelColor, usePointStyle:true, pointStyle:'circle', boxWidth:10 } }, tooltip:{ backgroundColor:'rgba(15,23,42,0.95)', borderColor:'rgba(148,163,184,0.2)', borderWidth:1, titleColor:'#fff', bodyColor:'#e2e8f0' } }, scales:{ x:{ grid:{ color:'rgba(148,163,184,0.06)' }, ticks:{ color:tickColor } }, y:{ beginAtZero:true, grid:{ color:chartGrid }, ticks:{ color:tickColor, precision:0 } } } };
}
const monthlyEl = document.getElementById('chartMonthlyFlow');
if (monthlyEl) {
    const g = monthlyEl.getContext('2d').createLinearGradient(0, 0, 0, 260); g.addColorStop(0, 'rgba(34,211,238,0.28)'); g.addColorStop(1, 'rgba(34,211,238,0.02)');
    new Chart(monthlyEl, { type:'line', data:{ labels:TV_LOBBY.monthLabels, datasets:[{ label:'Sales Order', data:TV_LOBBY.monthSoCounts, borderColor:'#22d3ee', backgroundColor:g, fill:true, borderWidth:3, tension:.35, pointRadius:3 }, { label:'Pengiriman', data:TV_LOBBY.monthSjCounts, borderColor:'#22c55e', backgroundColor:'rgba(34,197,94,0.05)', fill:false, borderWidth:3, tension:.3, pointRadius:3 }] }, options:baseOptions() });
}
const dailyEl = document.getElementById('chartDailyActivity');
if (dailyEl) {
    new Chart(dailyEl, { type:'bar', data:{ labels:TV_LOBBY.dailyLabels, datasets:[{ label:'SO Masuk', data:TV_LOBBY.dailySoCounts, backgroundColor:'rgba(59,130,246,0.78)', borderColor:'#3b82f6', borderRadius:8, borderWidth:1 }, { label:'Pengiriman', data:TV_LOBBY.dailySjCounts, backgroundColor:'rgba(245,158,11,0.78)', borderColor:'#f59e0b', borderRadius:8, borderWidth:1 }] }, options:baseOptions() });
}
const donutEl = document.getElementById('chartStatusDonut');
if (donutEl) {
    const hasStatus = Array.isArray(TV_LOBBY.statusCounts) && TV_LOBBY.statusCounts.length > 0;
    new Chart(donutEl, { type:'doughnut', data:{ labels:hasStatus ? TV_LOBBY.statusLabels : ['Belum ada data'], datasets:[{ data:hasStatus ? TV_LOBBY.statusCounts : [1], backgroundColor:hasStatus ? TV_LOBBY.statusColors.slice(0, TV_LOBBY.statusCounts.length) : ['rgba(148,163,184,0.35)'], borderColor:'rgba(15,23,42,0.95)', borderWidth:2, hoverOffset:5 }] }, options:{ responsive:true, maintainAspectRatio:false, cutout:'62%', plugins:{ legend:{ display:false }, tooltip:{ backgroundColor:'rgba(15,23,42,0.95)', borderColor:'rgba(148,163,184,0.2)', borderWidth:1, titleColor:'#fff', bodyColor:'#e2e8f0' } } } });
}
setTimeout(function () { location.reload(); }, 300000);
</script>
</body>
</html>
