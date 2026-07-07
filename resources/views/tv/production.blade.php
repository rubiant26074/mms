<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TV Produksi Shop Floor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root{--bg-1:#090f1a;--bg-2:#111c2f;--panel:rgba(20,32,53,.82);--line:rgba(148,163,184,.16);--ink:#eef5ff;--muted:#aebfd4}
        *{box-sizing:border-box}
        body{margin:0;padding:12px 12px 72px;color:var(--ink);font-family:'Segoe UI',Tahoma,sans-serif;overflow:hidden;background:radial-gradient(circle at 12% -8%,rgba(56,189,248,.16),transparent 36%),radial-gradient(circle at 100% 0%,rgba(245,158,11,.1),transparent 30%),linear-gradient(180deg,var(--bg-1),var(--bg-2))}
        .glass{background:var(--panel);border:1px solid var(--line);border-radius:14px;box-shadow:0 14px 26px rgba(2,8,23,.28);backdrop-filter:blur(8px)}
        .top-wrap{display:grid;grid-template-columns:1.3fr .9fr;gap:10px;margin-bottom:10px}.top-main,.top-side{padding:12px 14px}.top-main{display:flex;align-items:center;gap:12px;min-height:88px}
        .title-main{margin:0;font-size:clamp(1.2rem,1.6vw,1.9rem);font-weight:800;line-height:1.05;letter-spacing:.02em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .title-sub{color:var(--muted);font-size:.92rem;margin-top:4px}.slide-badge{display:inline-flex;align-items:center;margin-left:8px;padding:.12rem .45rem;border-radius:999px;border:1px solid rgba(148,163,184,.22);background:rgba(255,255,255,.04);color:#dbeafe;font-size:.66rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase}
        .live-dot{width:9px;height:9px;border-radius:50%;background:#ef4444;display:inline-block;animation:blink 1s infinite;margin:0 7px}@keyframes blink{50%{opacity:.24}}
        .top-side{display:grid;grid-template-columns:1fr 1fr;gap:8px}.clock-box,.mini-box{border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.03);padding:9px 10px}
        .clock-time{font-size:1.8rem;font-weight:800;line-height:1}.clock-date{color:var(--muted);font-size:.85rem;margin-top:4px}.mini-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
        .mini-label{color:var(--muted);font-size:.67rem;text-transform:uppercase;letter-spacing:.08em}.mini-value{font-size:1rem;font-weight:700}
        .kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:10px}.kpi-card{padding:11px 12px;min-height:92px;position:relative;overflow:hidden}.kpi-card:after{content:"";position:absolute;width:110px;height:110px;right:-25px;top:-34px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.14),transparent 70%)}
        .kpi-label{font-size:.71rem;text-transform:uppercase;letter-spacing:.08em;color:#ccdbef}.kpi-value{font-size:1.48rem;font-weight:800;line-height:1.1}.kpi-meta{margin-top:6px;color:#d8e6f8;font-size:.79rem}.tone-a{background:linear-gradient(135deg,rgba(59,130,246,.18),rgba(56,189,248,.14))}.tone-b{background:linear-gradient(135deg,rgba(34,197,94,.14),rgba(20,184,166,.14))}.tone-c{background:linear-gradient(135deg,rgba(168,85,247,.14),rgba(99,102,241,.13))}.tone-d{background:linear-gradient(135deg,rgba(245,158,11,.14),rgba(249,115,22,.12))}
        .main-grid{display:grid;grid-template-columns:1.42fr .98fr;gap:10px;height:calc(100vh - 236px)}.left-col,.right-col{display:grid;gap:10px;min-height:0}.left-col{grid-template-rows:.82fr 1.18fr}.right-col{grid-template-rows:.9fr 1.1fr}
        .panel{padding:12px 13px;min-height:0;display:flex;flex-direction:column}.panel-head{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px}.panel-head h4{margin:0;font-size:.9rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#dbeafe}.panel-head small{color:var(--muted)}
        .chart-wrap{position:relative;flex:1 1 auto;min-height:130px}.split-2{display:grid;grid-template-columns:1fr 1fr;gap:10px;min-height:0}.mini-panel{border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.025);padding:9px;display:flex;flex-direction:column;min-height:0}.mini-panel h5{margin:0 0 7px 0;font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;color:#dbeafe}
        .machine-carousel{flex:1 1 auto;min-height:0}.machine-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.machine-card{border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.025);overflow:hidden;min-height:126px}
        .machine-head{padding:8px 10px;font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;display:flex;justify-content:space-between;align-items:center;gap:6px}.machine-head.active{background:rgba(34,197,94,.18);color:#bbf7d0}.machine-head.idle{background:rgba(148,163,184,.14);color:#dbeafe}.machine-head.maintenance{background:rgba(245,158,11,.18);color:#fde68a}.machine-head.broken{background:rgba(239,68,68,.2);color:#fecaca}
        .machine-body{padding:10px;display:grid;grid-template-columns:auto 1fr;gap:10px;align-items:center}.machine-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;border:1px solid rgba(148,163,184,.2)}.machine-icon.active{color:#86efac;background:rgba(34,197,94,.08)}.machine-icon.idle{color:#cbd5e1;background:rgba(148,163,184,.08)}.machine-icon.maintenance{color:#fde68a;background:rgba(245,158,11,.08)}.machine-icon.broken{color:#fca5a5;background:rgba(239,68,68,.08)}
        .machine-status{font-size:1rem;font-weight:800;line-height:1}.machine-status-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.run-beacon{width:11px;height:11px;border-radius:50%;background:#ef4444;border:2px solid #fff;box-shadow:0 0 0 0 rgba(239,68,68,.7);animation:runBeaconPulse 1s infinite;flex:0 0 auto}@keyframes runBeaconPulse{0%{transform:scale(1);box-shadow:0 0 0 0 rgba(239,68,68,.7);opacity:1}50%{background:#fff;border-color:#ef4444;opacity:.95}70%{box-shadow:0 0 0 8px rgba(239,68,68,0)}100%{transform:scale(1);box-shadow:0 0 0 0 rgba(239,68,68,0);opacity:1}}
        .machine-meta{margin-top:4px;color:var(--muted);font-size:.74rem}.machine-runtime{margin-top:5px;display:inline-flex;align-items:center;gap:6px;padding:.14rem .45rem;border-radius:999px;border:1px solid rgba(248,113,113,.28);background:rgba(239,68,68,.08);color:#fee2e2;font-size:.68rem;font-weight:700;letter-spacing:.03em}.machine-runtime i{color:#fecaca}.machine-tag{display:inline-block;margin-top:5px;padding:.12rem .42rem;border-radius:999px;border:1px solid rgba(148,163,184,.2);background:rgba(255,255,255,.03);color:#dbeafe;font-size:.66rem}
        .table-tv{width:100%;border-collapse:collapse;font-size:.79rem}.table-tv th,.table-tv td{border-bottom:1px solid rgba(148,163,184,.12);padding:5px 4px;vertical-align:middle}.table-tv th{color:#b8c9de;font-size:.66rem;text-transform:uppercase;letter-spacing:.08em}.table-tv td{color:#edf5ff}.right{text-align:right}.nowrap{white-space:nowrap}.muted{color:var(--muted)}
        .badge-soft{display:inline-block;padding:.14rem .45rem;border-radius:999px;border:1px solid rgba(148,163,184,.22);background:rgba(255,255,255,.03);font-size:.66rem}.st-run{color:#7dd3fc;border-color:rgba(56,189,248,.3);background:rgba(56,189,248,.1)}.st-hold{color:#fde68a;border-color:rgba(245,158,11,.3);background:rgba(245,158,11,.1)}.st-assigned{color:#c4b5fd;border-color:rgba(168,85,247,.3);background:rgba(168,85,247,.1)}.q-ok{color:#86efac;border-color:rgba(34,197,94,.3);background:rgba(34,197,94,.1)}.q-wait{color:#fde68a;border-color:rgba(245,158,11,.3);background:rgba(245,158,11,.1)}.q-danger{color:#fca5a5;border-color:rgba(239,68,68,.3);background:rgba(239,68,68,.1)}.q-idle{color:#cbd5e1;border-color:rgba(148,163,184,.25);background:rgba(148,163,184,.08)}
        .footer-fixed{position:fixed;left:0;right:0;bottom:0;z-index:999}.status-line{font-size:1rem;font-weight:800;letter-spacing:.06em;text-align:center;padding:8px 10px}.status-line.ok{background:#0f5132;color:#d1fae5}.status-line.warn{background:#7c5a10;color:#fef3c7}.status-line.danger{background:#7f1d1d;color:#fee2e2}.status-line.idle{background:#334155;color:#e2e8f0}.marquee-line{background:rgba(0,0,0,.92);color:#facc15;border-top:2px solid #facc15;padding:5px 0}.marquee-line marquee{font-family:'Courier New',monospace;font-weight:700;font-size:.94rem}
        body.tv-slide-production #panelMachine,body.tv-slide-production #panelQc,body.tv-slide-machine #panelProduction,body.tv-slide-machine #panelJobs,body.tv-slide-machine #panelQc,body.tv-slide-qc #panelMachine,body.tv-slide-qc #panelProduction,body.tv-slide-qc #panelJobs{display:none!important}
        body.tv-slide-machine .right-col,body.tv-slide-qc .left-col{display:none!important}body.tv-slide-machine .main-grid,body.tv-slide-qc .main-grid{grid-template-columns:1fr}body.tv-slide-production .left-col,body.tv-slide-production .right-col,body.tv-slide-machine .left-col,body.tv-slide-qc .right-col{grid-template-rows:1fr}body.tv-slide-machine #panelMachine,body.tv-slide-qc #panelQc{height:100%}body.tv-slide-machine .machine-carousel{min-height:0}
        @media (max-width:1400px){body{overflow:auto}.top-wrap,.main-grid{grid-template-columns:1fr;height:auto}.top-side,.kpi-grid,.split-2{grid-template-columns:repeat(2,1fr)}.left-col,.right-col{grid-template-rows:auto}.machine-grid{grid-template-columns:1fr 1fr}}
        @media (max-width:900px){.kpi-grid,.split-2,.top-side{grid-template-columns:1fr}.machine-grid{grid-template-columns:1fr}}
    </style>
</head>
<body class="tv-slide-production">
@php
    $fmtNo = function ($n) {
        $n = (float) $n;
        $a = abs($n);
        if ($a >= 1000000) return number_format($n / 1000000, 1, ',', '.') . 'M';
        if ($a >= 1000) return number_format($n / 1000, 1, ',', '.') . 'K';
        return number_format($n, 0, ',', '.');
    };
@endphp
<div class="top-wrap">
    <div class="glass top-main">
        <div>
            @if($headerLogoMode === 'image' && !empty($company->logo_path))
                <img src="{{ asset($company->logo_path) }}" alt="Logo" style="height:60px;width:auto;background:rgba(255,255,255,.95);padding:5px 7px;border-radius:10px;">
            @else
                <i class="bi bi-hdd-rack-fill" style="font-size:2.5rem;color:#fbbf24"></i>
            @endif
        </div>
        <div style="min-width:0;flex:1 1 auto;">
            <h1 class="title-main">{{ $companyName }}</h1>
            <div class="title-sub">TV Produksi Shop Floor - Produksi & QC <span class="live-dot"></span> Live Monitor Operator <span id="tvProdSlideBadge" class="slide-badge">Slide Produksi</span></div>
        </div>
    </div>
    <div class="glass top-side">
        <div class="clock-box">
            <div id="tvProdClock" class="clock-time">--:--:--</div>
            <div id="tvProdDate" class="clock-date">-</div>
            <div class="muted" style="font-size:.74rem;margin-top:4px;">Auto refresh 90 detik</div>
        </div>
        <div class="mini-grid">
            <div class="mini-box"><div class="mini-label">Mesin Running</div><div class="mini-value">{{ number_format((int) $machineCounts['active'], 0, ',', '.') }}/{{ number_format((int) $machineTotal, 0, ',', '.') }}</div></div>
            <div class="mini-box"><div class="mini-label">Mesin Down</div><div class="mini-value" style="color:{{ $machineCounts['broken'] > 0 ? '#fca5a5' : '#86efac' }}">{{ number_format((int) $machineCounts['broken'], 0, ',', '.') }}</div></div>
            <div class="mini-box"><div class="mini-label">Job Running</div><div class="mini-value">{{ number_format((int) $assignDist['in_progress'], 0, ',', '.') }}</div></div>
            <div class="mini-box"><div class="mini-label">Antrian QC</div><div class="mini-value">{{ number_format((int) $qcWaitingCount, 0, ',', '.') }}</div></div>
        </div>
    </div>
</div>

<div class="kpi-grid">
    <div class="glass kpi-card tone-a"><div class="kpi-label">Output Good Hari Ini</div><div class="kpi-value">{{ $fmtNo($goodToday) }}</div><div class="kpi-meta">Task selesai {{ number_format((int) $taskDoneToday, 0, ',', '.') }}</div></div>
    <div class="glass kpi-card tone-d"><div class="kpi-label">Reject / NG Hari Ini</div><div class="kpi-value">{{ $fmtNo($rejectToday) }}</div><div class="kpi-meta">QC NG doc hari ini {{ number_format((int) $qcTodayNgDocs, 0, ',', '.') }}</div></div>
    <div class="glass kpi-card tone-b"><div class="kpi-label">Yield Produksi Hari Ini</div><div class="kpi-value">{{ number_format((float) $yieldToday, 1, ',', '.') }}%</div><div class="kpi-meta">Good vs total output</div></div>
    <div class="glass kpi-card tone-c"><div class="kpi-label">QC Pass Qty Hari Ini</div><div class="kpi-value">{{ $fmtNo($qcTodayPassQty) }}</div><div class="kpi-meta">QC docs {{ number_format((int) $qcTodayCount, 0, ',', '.') }} | NG {{ number_format((int) $qcTodayRejectQty, 0, ',', '.') }}</div></div>
    <div class="glass kpi-card tone-c"><div class="kpi-label">Job Production Queue</div><div class="kpi-value">{{ number_format((int) ($assignDist['assigned'] + $assignDist['pending']), 0, ',', '.') }}</div><div class="kpi-meta">Assigned {{ number_format((int) $assignDist['assigned'], 0, ',', '.') }} | Pending {{ number_format((int) $assignDist['pending'], 0, ',', '.') }}</div></div>
    <div class="glass kpi-card tone-d"><div class="kpi-label">Job Hold</div><div class="kpi-value">{{ number_format((int) $assignDist['hold'], 0, ',', '.') }}</div><div class="kpi-meta">Perlu follow up supervisor / material / mesin</div></div>
    <div class="glass kpi-card tone-a"><div class="kpi-label">SPK Aktif</div><div class="kpi-value">{{ number_format((int) $spkActiveCount, 0, ',', '.') }}</div><div class="kpi-meta">SPK ready/wait {{ number_format(count(array_filter($qcQueueRows, fn ($r) => in_array(strtolower((string) ($r['spk_status'] ?? '')), ['released', 'in_production'], true))), 0, ',', '.') }}</div></div>
    <div class="glass kpi-card tone-b"><div class="kpi-label">SPK Menunggu / Proses QC</div><div class="kpi-value">{{ number_format((int) ($qcWaitingCount + $qcNgFollowupCount), 0, ',', '.') }}</div><div class="kpi-meta">Waiting QC {{ number_format((int) $qcWaitingCount, 0, ',', '.') }} | NG follow up {{ number_format((int) $qcNgFollowupCount, 0, ',', '.') }}</div></div>
</div>

<div id="tvProdMainGrid" class="main-grid">
    <div class="left-col">
        <div id="panelMachine" class="glass panel">
            <div class="panel-head"><h4><i class="bi bi-cpu me-2"></i>Status Mesin Shop Floor</h4><small>Slide otomatis 10 detik / halaman</small></div>
            <div id="machineCarousel" class="carousel slide machine-carousel" data-bs-ride="carousel" data-bs-interval="10000">
                <div class="carousel-inner h-100">
                    @foreach($machineSlides as $idx => $slide)
                        <div class="carousel-item {{ $idx === 0 ? 'active' : '' }} h-100">
                            <div class="machine-grid">
                                @if(empty($slide))
                                    <div class="machine-card" style="grid-column:1 / -1;"><div class="machine-body"><div class="machine-status">DATA MESIN BELUM TERSEDIA</div><div class="machine-meta">Silakan cek master mesin</div></div></div>
                                @else
                                    @foreach($slide as $m)
                                        @php
                                            $mStatus = strtolower((string) ($m['tv_display_status'] ?? $m['status'] ?? 'idle'));
                                            if (!in_array($mStatus, ['active', 'idle', 'maintenance', 'broken'], true)) $mStatus = 'idle';
                                            $statusLabel = $mStatus === 'active' ? 'RUNNING' : ($mStatus === 'idle' ? 'STANDBY' : ($mStatus === 'maintenance' ? 'MAINTENANCE' : 'DOWN / RUSAK'));
                                            $icon = $mStatus === 'active' ? 'bi-gear-wide-connected' : ($mStatus === 'idle' ? 'bi-pause-circle' : ($mStatus === 'maintenance' ? 'bi-tools' : 'bi-exclamation-triangle-fill'));
                                            $runningStartAt = (string) ($m['tv_running_start_at'] ?? '');
                                            $runningRuntimeSeconds = max(0, (int) ($m['tv_running_runtime_seconds'] ?? 0));
                                            $runningStartTs = $runningStartAt !== '' ? strtotime($runningStartAt) : false;
                                            $statusColor = $mStatus === 'active' ? '#86efac' : ($mStatus === 'idle' ? '#cbd5e1' : ($mStatus === 'maintenance' ? '#fde68a' : '#fca5a5'));
                                        @endphp
                                        <div class="machine-card">
                                            <div class="machine-head {{ $mStatus }}">
                                                <span>{{ $m['machine_name'] ?? '-' }}</span>
                                                <span style="font-size:.68rem;">{{ $m['machine_code'] ?? '' }}</span>
                                            </div>
                                            <div class="machine-body">
                                                <div class="machine-icon {{ $mStatus }}"><i class="bi {{ $icon }} {{ $mStatus === 'active' ? 'bi-spin' : '' }}"></i></div>
                                                <div>
                                                    <div class="machine-status-row">
                                                        @if($mStatus === 'active')<span class="run-beacon" aria-hidden="true"></span>@endif
                                                        <div class="machine-status" style="color:{{ $statusColor }}">{{ $statusLabel }}</div>
                                                    </div>
                                                    <div class="machine-meta"><i class="bi bi-diagram-3"></i> {{ $m['process_type'] ?? '-' }}</div>
                                                    <div class="machine-meta"><i class="bi bi-geo-alt"></i> {{ $m['location'] ?? '-' }}</div>
                                                    @if($mStatus === 'active' && $runningStartTs)
                                                        <div class="machine-runtime js-machine-runtime" data-elapsed-seconds="{{ $runningRuntimeSeconds }}" title="Mulai {{ date('d/m/Y H:i:s', (int) $runningStartTs) }}"><i class="bi bi-broadcast-pin"></i><span>Run 00:00:00</span></div>
                                                        @if(!empty($m['tv_running_spk']))
                                                            <div class="machine-meta"><i class="bi bi-clipboard-data"></i> {{ $m['tv_running_spk'] }}@if(!empty($m['tv_running_operator'])) | {{ $m['tv_running_operator'] }}@endif</div>
                                                        @endif
                                                    @endif
                                                    @if(!empty($m['capacity_per_hour']))<span class="machine-tag">Cap/Jam: {{ $fmtNo($m['capacity_per_hour']) }}</span>@endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div id="panelProduction" class="glass panel">
            <div class="panel-head"><h4><i class="bi bi-bar-chart-line me-2"></i>Output per Proses & Distribusi Job</h4><small>Ringkasan produksi hari ini</small></div>
            <div class="split-2" style="flex:1 1 auto;min-height:0;">
                <div class="mini-panel"><h5>Output per Proses (Today)</h5><div class="chart-wrap"><canvas id="prodOutputChart"></canvas></div></div>
                <div class="mini-panel"><h5>Status Job Produksi</h5><div class="chart-wrap"><canvas id="prodAssignChart"></canvas></div></div>
            </div>
        </div>
    </div>

    <div class="right-col">
        <div id="panelJobs" class="glass panel">
            <div class="panel-head"><h4><i class="bi bi-list-task me-2"></i>Job Produksi Aktif (Operator)</h4><small>Running / Hold / Assigned</small></div>
            <div class="split-2" style="flex:1 1 auto;min-height:0;">
                <div class="mini-panel">
                    <h5>Job Aktif</h5>
                    <table class="table-tv">
                        <thead><tr><th>SPK / Proses</th><th>Status</th><th>Operator</th><th class="right">Qty</th></tr></thead>
                        <tbody>
                        @forelse($activeJobs as $r)
                            @php
                                $st = strtolower((string) ($r->status ?? ''));
                                $cls = $st === 'in_progress' ? 'st-run' : ($st === 'hold' ? 'st-hold' : 'st-assigned');
                            @endphp
                            <tr>
                                <td><strong class="nowrap">{{ $r->spk_number ?? '-' }}</strong><div class="muted" style="font-size:.67rem;">{{ $r->process_name ?? '-' }}</div></td>
                                <td class="nowrap"><span class="badge-soft {{ $cls }}">{{ strtoupper(str_replace('_', ' ', $st)) }}</span></td>
                                <td>{{ $r->operator_name ?? '-' }}</td>
                                <td class="right">{{ number_format((float) ($r->qty_input ?? 0), 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted">Tidak ada job aktif saat ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mini-panel">
                    <h5>Job Selesai Terakhir</h5>
                    <table class="table-tv">
                        <thead><tr><th>SPK / Proses</th><th class="right">Good</th><th class="right">NG</th><th>Jam</th></tr></thead>
                        <tbody>
                        @forelse($recentDoneJobs as $r)
                            <tr>
                                <td><strong class="nowrap">{{ $r->spk_number ?? '-' }}</strong><div class="muted" style="font-size:.67rem;">{{ $r->process_name ?? '-' }}</div></td>
                                <td class="right" style="color:#86efac;">{{ number_format((float) ($r->qty_good ?? 0), 0, ',', '.') }}</td>
                                <td class="right" style="color:#fca5a5;">{{ number_format((float) ($r->qty_reject ?? 0), 0, ',', '.') }}</td>
                                <td class="nowrap">{{ !empty($r->end_time) ? \Illuminate\Support\Carbon::parse($r->end_time)->format('H:i') : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted">Belum ada job selesai.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="panelQc" class="glass panel">
            <div class="panel-head"><h4><i class="bi bi-shield-check me-2"></i>Monitor QC Produksi</h4><small>Queue QC + status hari ini</small></div>
            <div class="split-2" style="flex:1 1 auto;min-height:0;">
                <div class="mini-panel">
                    <h5>Antrian SPK / QC</h5>
                    <table class="table-tv">
                        <thead><tr><th>SPK</th><th>QC Status</th><th>Deadline</th></tr></thead>
                        <tbody>
                        @forelse($qcQueueRows as $r)
                            @php
                                $toneCls = $r['tone'] === 'danger' ? 'q-danger' : ($r['tone'] === 'ok' ? 'q-ok' : ($r['tone'] === 'run' ? 'st-run' : ($r['tone'] === 'wait' ? 'q-wait' : 'q-idle')));
                            @endphp
                            <tr>
                                <td><strong class="nowrap">{{ $r['spk_number'] }}</strong><div class="muted" style="font-size:.67rem;">{{ strtoupper((string) ($r['spk_status'] ?? '')) }}{{ ($r['priority'] ?? '') === 'urgent' ? ' | URGENT' : '' }}</div></td>
                                <td class="nowrap"><span class="badge-soft {{ $toneCls }}">{{ $r['qc_label'] }}</span></td>
                                <td class="nowrap">{{ !empty($r['deadline_date']) ? \Illuminate\Support\Carbon::parse($r['deadline_date'])->format('d/m') : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="muted">Tidak ada SPK untuk dimonitor.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mini-panel">
                    <h5>QC Today Summary</h5>
                    <div class="chart-wrap" style="min-height:120px;"><canvas id="qcChart"></canvas></div>
                    <table class="table-tv" style="margin-top:6px;">
                        <tbody>
                            <tr><td>Total Dokumen QC Hari Ini</td><td class="right"><strong>{{ number_format((int) $qcTodayCount, 0, ',', '.') }}</strong></td></tr>
                            <tr><td>QC OK (Dokumen)</td><td class="right" style="color:#86efac;">{{ number_format((int) $qcTodayOkDocs, 0, ',', '.') }}</td></tr>
                            <tr><td>QC NG (Dokumen)</td><td class="right" style="color:#fca5a5;">{{ number_format((int) $qcTodayNgDocs, 0, ',', '.') }}</td></tr>
                            <tr><td>Qty Pass Hari Ini</td><td class="right">{{ $fmtNo($qcTodayPassQty) }}</td></tr>
                            <tr><td>Qty Reject Hari Ini</td><td class="right">{{ $fmtNo($qcTodayRejectQty) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="footer-fixed">
    <div class="status-line {{ $bgAlert }}">{{ $statusText }}</div>
    <div class="marquee-line"><marquee behavior="scroll" direction="left" scrollamount="9"><i class="bi bi-cone-striped me-2"></i> {{ strtoupper($runningText) }} | MACHINE RUNNING {{ number_format((int) $machineCounts['active'], 0, ',', '.') }}/{{ number_format((int) $machineTotal, 0, ',', '.') }} | JOB RUNNING {{ number_format((int) $assignDist['in_progress'], 0, ',', '.') }} | HOLD {{ number_format((int) $assignDist['hold'], 0, ',', '.') }} | QC WAITING {{ number_format((int) $qcWaitingCount, 0, ',', '.') }} | QC NG FOLLOW UP {{ number_format((int) $qcNgFollowupCount, 0, ',', '.') }}</marquee></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const procLabels = @json($procLabels);
const procGood = @json($procGood);
const procNg = @json($procNg);
const assignLabels = @json($assignChartLabels);
const assignValues = @json($assignChartValues);
const qcLabels = @json($qcChartLabels);
const qcValues = @json($qcChartValues);

Chart.defaults.color = '#dbeafe';
Chart.defaults.font.family = "Segoe UI, Tahoma, sans-serif";

new Chart(document.getElementById('prodOutputChart'), {
    type: 'bar',
    data: { labels: procLabels, datasets: [{ label: 'Good', data: procGood, backgroundColor: '#22c55e', borderRadius: 7 }, { label: 'NG', data: procNg, backgroundColor: '#ef4444', borderRadius: 7 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#eaf2ff', boxWidth: 12 } } }, scales: { x: { grid: { display: false }, ticks: { color: '#bfd0e6', font: { size: 10 } } }, y: { grid: { color: 'rgba(148,163,184,.12)' }, ticks: { color: '#bfd0e6' } } } }
});
new Chart(document.getElementById('prodAssignChart'), {
    type: 'doughnut',
    data: { labels: assignLabels, datasets: [{ data: assignValues, backgroundColor: ['#64748b', '#a855f7', '#38bdf8', '#f59e0b', '#22c55e'], borderWidth: 0 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { labels: { color: '#eaf2ff', boxWidth: 10, font: { size: 10 } } } } }
});
new Chart(document.getElementById('qcChart'), {
    type: 'doughnut',
    data: { labels: qcLabels, datasets: [{ data: qcValues, backgroundColor: ['#22c55e', '#ef4444'], borderWidth: 0 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { labels: { color: '#eaf2ff', boxWidth: 10, font: { size: 10 } } } } }
});
(() => {
    const timeEl = document.getElementById('tvProdClock');
    const dateEl = document.getElementById('tvProdDate');
    const hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const pad = (n) => String(n).padStart(2, '0');
    const render = () => {
        const now = new Date();
        timeEl.textContent = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
        dateEl.textContent = `${hari[now.getDay()]}, ${pad(now.getDate())} ${bulan[now.getMonth()]} ${now.getFullYear()}`;
    };
    render();
    setInterval(render, 1000);
})();
(() => {
    const body = document.body;
    const badge = document.getElementById('tvProdSlideBadge');
    const slides = [{ cls: 'tv-slide-production', label: 'Slide Produksi' }, { cls: 'tv-slide-machine', label: 'Slide Mesin' }, { cls: 'tv-slide-qc', label: 'Slide QC' }];
    let idx = 0;
    const applySlide = () => {
        body.classList.remove('tv-slide-production', 'tv-slide-machine', 'tv-slide-qc');
        const s = slides[idx] || slides[0];
        body.classList.add(s.cls);
        if (badge) badge.textContent = s.label;
    };
    applySlide();
    setInterval(() => {
        idx = (idx + 1) % slides.length;
        applySlide();
    }, 15000);
})();
(() => {
    const els = Array.from(document.querySelectorAll('.js-machine-runtime'));
    if (!els.length) return;
    const pad = (n) => String(n).padStart(2, '0');
    let tickCount = 0;
    const render = () => {
        els.forEach((el) => {
            const baseElapsed = parseInt(el.getAttribute('data-elapsed-seconds') || '0', 10);
            const textEl = el.querySelector('span');
            if (!textEl) return;
            let diff = (Number.isFinite(baseElapsed) ? baseElapsed : 0) + tickCount;
            if (!Number.isFinite(diff) || diff < 0) diff = 0;
            const hh = Math.floor(diff / 3600);
            const mm = Math.floor((diff % 3600) / 60);
            const ss = diff % 60;
            textEl.textContent = `Run ${pad(hh)}:${pad(mm)}:${pad(ss)}`;
        });
    };
    render();
    setInterval(() => {
        tickCount += 1;
        render();
    }, 1000);
})();
setTimeout(() => location.reload(), 90000);
</script>
</body>
</html>
