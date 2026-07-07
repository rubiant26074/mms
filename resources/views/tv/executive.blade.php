<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>TV Executive - MMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root{--bg1:#071426;--bg2:#0c1f3b;--panel:rgba(14,27,49,.78);--line:rgba(148,163,184,.14);--ink:#ecf4ff;--mut:#a5b8cd;--c1:#38bdf8;--c2:#22c55e;--c3:#f59e0b;--c4:#a855f7;--danger:#ef4444}
        *{box-sizing:border-box}body{margin:0;padding:12px 12px 68px;color:var(--ink);font-family:'Segoe UI',Tahoma,sans-serif;overflow:hidden;background:radial-gradient(circle at 10% -5%,rgba(56,189,248,.18),transparent 38%),radial-gradient(circle at 100% 0,rgba(34,197,94,.12),transparent 34%),linear-gradient(180deg,var(--bg1),var(--bg2))}
        .cardx{background:var(--panel);border:1px solid var(--line);border-radius:14px;box-shadow:0 14px 28px rgba(2,8,23,.28);backdrop-filter:blur(8px)}
        .top{display:grid;grid-template-columns:1.25fr .95fr;gap:10px;margin-bottom:10px}.headA,.headB{padding:12px 14px}.headA{display:flex;align-items:center;gap:12px;min-height:88px}.ttl{font-weight:800;font-size:clamp(1.2rem,1.5vw,1.8rem);line-height:1.06;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:0}.sub{color:var(--mut);font-size:.9rem}.live{display:inline-block;width:9px;height:9px;background:#ef4444;border-radius:50%;animation:b 1s infinite;margin:0 7px}@keyframes b{50%{opacity:.25}}
        .headB{display:grid;grid-template-columns:1fr 1fr;gap:8px}.clock{padding:8px 10px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.03)}.tm{font-size:1.8rem;font-weight:800;line-height:1}.dt{color:var(--mut);font-size:.85rem;margin-top:4px}.chip{padding:8px 10px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.02)}.chip .l{font-size:.68rem;color:var(--mut);text-transform:uppercase;letter-spacing:.08em}.chip .v{font-size:1rem;font-weight:700}
        .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:10px}.k{padding:11px 12px;min-height:90px;position:relative;overflow:hidden}.k:after{content:'';position:absolute;right:-24px;top:-35px;width:110px;height:110px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.14),transparent 70%)}.kl{font-size:.72rem;color:#c7d8ea;text-transform:uppercase;letter-spacing:.08em}.kv{font-size:1.45rem;font-weight:800;line-height:1.1}.km{font-size:.8rem;color:#d6e5f6;margin-top:6px}.t1{background:linear-gradient(135deg,rgba(59,130,246,.2),rgba(56,189,248,.14))}.t2{background:linear-gradient(135deg,rgba(20,184,166,.18),rgba(34,197,94,.14))}.t3{background:linear-gradient(135deg,rgba(168,85,247,.16),rgba(99,102,241,.14))}.t4{background:linear-gradient(135deg,rgba(245,158,11,.15),rgba(249,115,22,.14))}
        .main{display:grid;grid-template-columns:1.45fr .95fr;gap:10px;height:calc(100vh - 233px)}.colL,.colR{display:grid;gap:10px;min-height:0}.colL{grid-template-rows:1.05fr .95fr}.colR{grid-template-rows:.8fr 1fr}.box{padding:12px 13px;display:flex;flex-direction:column;min-height:0}.hd{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px}.hd h4{margin:0;font-size:.9rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#dbeafe}.hd small{color:var(--mut)}.cv{position:relative;flex:1;min-height:140px}
        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px;min-height:0}.mini{padding:9px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.02);display:flex;flex-direction:column}.mini h5{margin:0 0 7px 0;font-size:.76rem;color:#dbeafe;text-transform:uppercase;letter-spacing:.08em}.mini .cv{min-height:110px}
        .tbl{width:100%;border-collapse:collapse;font-size:.8rem}.tbl th,.tbl td{padding:5px 4px;border-bottom:1px solid rgba(148,163,184,.12)}.tbl th{font-size:.66rem;text-transform:uppercase;letter-spacing:.08em;color:#b9cae0}.tbl td{color:#edf5ff}.r{text-align:right}.nw{white-space:nowrap}.pill{display:inline-block;padding:.15rem .45rem;border-radius:999px;font-size:.66rem;border:1px solid rgba(148,163,184,.22);background:rgba(255,255,255,.04)}.fg{color:#fcd34d;border-color:rgba(245,158,11,.3);background:rgba(245,158,11,.12)}.spk{color:#bfdbfe;border-color:rgba(59,130,246,.3);background:rgba(59,130,246,.12)}
        .alerts{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:8px}.al{padding:8px 9px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.025)}.al .n{font-size:1.05rem;font-weight:800;line-height:1}.al .t{font-size:.7rem;color:#d6e3f2;margin-top:5px;line-height:1.15}.al.warn .n{color:var(--c3)}.al.danger .n{color:var(--danger)}.al.ok .n{color:var(--c2)}
        .ft{position:fixed;left:0;right:0;bottom:0;background:rgba(0,0,0,.9);color:#facc15;border-top:2px solid #facc15;padding:5px 0;z-index:999}.ft marquee{font-family:'Courier New',monospace;font-weight:700;font-size:.96rem}
        @media (max-width:1400px){body{overflow:auto}.top,.main{grid-template-columns:1fr;height:auto}.headB{grid-template-columns:1fr 1fr}.kpis{grid-template-columns:repeat(2,1fr)}.colL,.colR{grid-template-rows:auto}.alerts{grid-template-columns:repeat(2,1fr)}}
    </style>
</head>
<body>
@php
    $fmtRp = function ($n) {
        $n = (float) $n;
        $a = abs($n);
        if ($a >= 1000000000) return 'Rp ' . number_format($n / 1000000000, 1, ',', '.') . ' M';
        if ($a >= 1000000) return 'Rp ' . number_format($n / 1000000, 1, ',', '.') . ' Jt';
        return 'Rp ' . number_format($n, 0, ',', '.');
    };
    $fmtNo = function ($n) {
        $n = (float) $n;
        $a = abs($n);
        if ($a >= 1000000) return number_format($n / 1000000, 1, ',', '.') . 'M';
        if ($a >= 1000) return number_format($n / 1000, 1, ',', '.') . 'K';
        return number_format($n, 0, ',', '.');
    };
@endphp
<div class="top">
    <div class="cardx headA">
        <div>
            @if($company->logo_path)
                <img src="{{ asset($company->logo_path) }}" alt="Logo" style="height:66px;width:auto;background:rgba(255,255,255,.95);padding:6px 8px;border-radius:10px">
            @else
                <i class="bi bi-bar-chart-line-fill" style="font-size:2.6rem;color:#67e8f9"></i>
            @endif
        </div>
        <div style="min-width:0;flex:1"><h1 class="ttl">{{ $companyName }}</h1><div class="sub">TV Executive Dashboard - Overall Business <span class="live"></span> Realtime Snapshot</div></div>
    </div>
    <div class="cardx headB">
        <div class="clock"><div id="clk" class="tm">--:--:--</div><div id="dte" class="dt">-</div><div class="sub" style="font-size:.75rem;margin-top:4px">Auto refresh 180s</div></div>
        <div class="grid2" style="grid-template-columns:1fr 1fr;gap:8px"><div class="chip"><div class="l">Machine</div><div class="v">{{ $k['machine_run'] }}/{{ $k['machine_total'] }}</div></div><div class="chip"><div class="l">Down</div><div class="v" style="color:{{ $k['machine_down'] > 0 ? '#fca5a5' : '#86efac' }}">{{ $k['machine_down'] }}</div></div><div class="chip"><div class="l">PR Open</div><div class="v">{{ $k['pr_open'] }}</div></div><div class="chip"><div class="l">PO Open</div><div class="v">{{ $k['po_open'] }}</div></div></div>
    </div>
</div>

<div class="kpis">
    <div class="cardx k t1"><div class="kl">Sales Booking Bulan Ini</div><div class="kv">{{ $fmtRp($k['so_booking']) }}</div><div class="km">SO aktif {{ $k['so_active'] }} | FG Stock {{ $k['so_fg'] }}</div></div>
    <div class="cardx k t2"><div class="kl">Invoicing & Collection</div><div class="kv">{{ $fmtRp($k['inv_month']) }}</div><div class="km">Collection {{ $fmtRp($k['col_month']) }} | AR {{ $fmtRp($k['ar_out']) }}</div></div>
    <div class="cardx k t3"><div class="kl">Produksi Berjalan</div><div class="kv">{{ $k['spk_active'] }} SPK</div><div class="km">Task running {{ $k['prod_run'] }} | Output today {{ $fmtNo($k['prod_out_today']) }}</div></div>
    <div class="cardx k t4"><div class="kl">Warehouse & Delivery</div><div class="kv">{{ $k['dn_today'] }} SJ Today</div><div class="km">Low stock {{ $k['low_stock'] }} | Near expiry {{ $k['near_exp'] }}</div></div>
    <div class="cardx k t2"><div class="kl">AR / AP Outstanding</div><div class="kv">{{ $fmtRp($k['ar_out'] + $k['ap_out']) }}</div><div class="km">AR {{ $fmtRp($k['ar_out']) }} | AP {{ $fmtRp($k['ap_out']) }}</div></div>
    <div class="cardx k t1"><div class="kl">Purchasing Bulan Ini</div><div class="kv">{{ $fmtRp($k['po_month']) }}</div><div class="km">PR open {{ $k['pr_open'] }} | PO open {{ $k['po_open'] }}</div></div>
    <div class="cardx k t3"><div class="kl">SPK & QC Queue</div><div class="kv">{{ $k['spk_wait'] }} Waiting</div><div class="km">QC NG 7 hari {{ $k['qc_ng7'] }} | SPK aktif {{ $k['spk_active'] }}</div></div>
    <div class="cardx k t4"><div class="kl">Cash / Kas Bulan Ini</div><div class="kv">{{ $fmtRp($k['cash_in'] - $k['cash_out']) }}</div><div class="km">In {{ $fmtRp($k['cash_in']) }} | Out {{ $fmtRp($k['cash_out']) }}</div></div>
</div>

<div class="main">
    <div class="colL">
        <div class="cardx box"><div class="hd"><h4><i class="bi bi-graph-up-arrow me-2"></i>Trend 6 Bulan (SO / Invoice / Collection)</h4><small>Overall Finansial</small></div><div class="cv"><canvas id="cTrend"></canvas></div></div>
        <div class="cardx box"><div class="hd"><h4><i class="bi bi-speedometer2 me-2"></i>Executive Radar</h4><small>Alert lintas modul</small></div><div class="alerts">@foreach($opsAlerts as $a)<div class="al {{ $a[2] }}"><div class="n">{{ number_format((int) $a[1],0,',','.') }}</div><div class="t">{{ $a[0] }}</div></div>@endforeach</div><div class="grid2" style="flex:1;min-height:0"><div class="mini"><h5>Daily Pulse Today</h5><div class="cv"><canvas id="cPulse"></canvas></div></div><div class="mini"><h5>Status SO</h5><div class="cv"><canvas id="cSO"></canvas></div></div></div></div>
    </div>
    <div class="colR">
        <div class="cardx box"><div class="hd"><h4><i class="bi bi-pie-chart me-2"></i>Status SPK & Machine Health</h4><small>Produksi</small></div><div class="grid2" style="flex:1;min-height:0"><div class="mini"><h5>Status SPK</h5><div class="cv"><canvas id="cSPK"></canvas></div></div><div class="mini"><h5>Machine Health</h5><div class="cv"><canvas id="cMach"></canvas></div></div></div></div>
        <div class="cardx box"><div class="hd"><h4><i class="bi bi-table me-2"></i>Recent SO + AR Due</h4><small>Sales / Finance</small></div><div class="grid2" style="flex:1;min-height:0"><div class="mini"><h5>Recent Sales Orders</h5><table class="tbl"><thead><tr><th>SO</th><th>Status</th><th>Fulfill</th><th class="r">Total</th></tr></thead><tbody>@forelse($recentSO as $r)@php($ff = (($r->fulfillment_source ?? 'spk') === 'fg_stock') ? 'fg_stock' : 'spk')<tr><td class="nw"><strong>{{ $r->so_number }}</strong><div style="color:#a5b8cd;font-size:.66rem">{{ \Illuminate\Support\Carbon::parse($r->so_date)->format('d/m') }}</div></td><td class="nw">{{ strtoupper(str_replace('_',' ',(string) $r->status)) }}</td><td class="nw"><span class="pill {{ $ff === 'fg_stock' ? 'fg' : 'spk' }}">{{ $ff === 'fg_stock' ? 'FG' : 'SPK' }}</span></td><td class="r">{{ $fmtRp($r->grand_total ?? 0) }}</td></tr>@empty<tr><td colspan="4">Tidak ada data</td></tr>@endforelse</tbody></table></div><div class="mini"><h5>AR Due / Overdue</h5><table class="tbl"><thead><tr><th>Invoice</th><th>Due</th><th class="r">OS</th></tr></thead><tbody>@forelse($arDue as $r)<tr><td>{{ $r->invoice_number }}</td><td class="nw" style="color:{{ \Illuminate\Support\Carbon::parse($r->due_date)->isPast() ? '#fca5a5' : '#cbd5e1' }}">{{ \Illuminate\Support\Carbon::parse($r->due_date)->format('d/m') }}</td><td class="r">{{ $fmtRp($r->os ?? 0) }}</td></tr>@empty<tr><td colspan="3">Tidak ada AR outstanding</td></tr>@endforelse</tbody></table></div></div></div>
    </div>
</div>
<div class="ft"><marquee behavior="scroll" direction="left" scrollamount="9"><i class="bi bi-broadcast me-2"></i> {{ strtoupper($runningText) }} | SO Active {{ $k['so_active'] }} | SPK Active {{ $k['spk_active'] }} | AR Overdue {{ $k['ar_overdue'] }} | Low Stock {{ $k['low_stock'] }} | Near Expiry {{ $k['near_exp'] }}</marquee></div>
<script>
const M=@json($labels), SOv=@json($serSO), IVv=@json($serINV), CLv=@json($serCOL);
const pulseL=@json(array_keys($pulse)), pulseV=@json(array_values($pulse)), soDist=@json($soDist), spkDist=@json($spkDist);
const machineVals=[{{ (int) $k['machine_run'] }},{{ (int) $k['machine_maint'] }},{{ (int) $k['machine_down'] }}];
const tickColor='#bcd0e5', gridColor='rgba(148,163,184,.12)';
const rup=(n)=>'Rp '+(Number(n||0)/1e6).toLocaleString('id-ID',{maximumFractionDigits:1})+' Jt';
new Chart(document.getElementById('cTrend'),{type:'line',data:{labels:M,datasets:[{label:'SO',data:SOv,borderColor:'#38bdf8',borderWidth:3,tension:.35,pointRadius:2},{label:'Invoice',data:IVv,borderColor:'#22c55e',borderWidth:3,tension:.35,pointRadius:2},{label:'Collection',data:CLv,borderColor:'#f59e0b',borderWidth:3,tension:.35,pointRadius:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:'#eaf2ff'}},tooltip:{callbacks:{label:(c)=>`${c.dataset.label}: ${rup(c.raw)}`}}},scales:{x:{grid:{display:false},ticks:{color:tickColor}},y:{grid:{color:gridColor},ticks:{color:tickColor,callback:(v)=>(v/1e6).toLocaleString('id-ID')+' Jt'}}}}});
new Chart(document.getElementById('cPulse'),{type:'bar',data:{labels:pulseL,datasets:[{data:pulseV,backgroundColor:['#38bdf8','#3b82f6','#22c55e','#14b8a6','#f59e0b','#a855f7','#fb7185'],borderRadius:7}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{color:tickColor,font:{size:10}}},y:{grid:{color:gridColor},ticks:{color:tickColor}}}}});
function donut(el,rows,fallback){const map={'draft':'#94a3b8','waiting_approval':'#f59e0b','confirmed':'#3b82f6','in_production':'#06b6d4','delivered':'#14b8a6','completed':'#22c55e','cancelled':'#ef4444','rejected':'#dc2626','waiting_eng':'#f59e0b','preliminary':'#a855f7','waiting_mgr':'#f97316','final':'#c084fc','released':'#38bdf8','closed':'#16a34a'};let labels=[],vals=[],colors=[];(rows||[]).forEach(r=>{const s=String(r.status||'unknown');labels.push(s.replaceAll('_',' ').toUpperCase());vals.push(Number(r.cnt||0));colors.push(map[s]||'#64748b');});if(!labels.length){labels=[fallback];vals=[1];colors=['#334155'];}new Chart(document.getElementById(el),{type:'doughnut',data:{labels,datasets:[{data:vals,backgroundColor:colors,borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:'#eaf2ff',boxWidth:10,font:{size:10}}}},cutout:'60%'}});}
donut('cSO',soDist,'SO'); donut('cSPK',spkDist,'SPK');
new Chart(document.getElementById('cMach'),{type:'doughnut',data:{labels:['Running','Maint','Down'],datasets:[{data:machineVals,backgroundColor:['#22c55e','#f59e0b','#ef4444'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:'#eaf2ff',boxWidth:10}}},cutout:'62%'}}});
(()=>{const T=document.getElementById('clk'),D=document.getElementById('dte'),H=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'],B=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'],p=n=>String(n).padStart(2,'0');const f=()=>{const n=new Date();T.textContent=`${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`;D.textContent=`${H[n.getDay()]}, ${p(n.getDate())} ${B[n.getMonth()]} ${n.getFullYear()}`;};f();setInterval(f,1000);})();
setTimeout(()=>location.reload(),180000);
</script>
</body>
</html>
