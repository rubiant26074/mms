<?php
// modules/tv/production.php
// Shop Floor TV Dashboard - Produksi + QC (operator friendly)

$comp = function_exists('get_company_profile') ? get_company_profile() : [];
$company_name = (string)($comp['company_name'] ?? 'PRODUCTION FLOOR');
$logo_path = (string)($comp['logo_path'] ?? '');
$logo_abs = ($logo_path !== '' && function_exists('mms_abs_path')) ? mms_abs_path($logo_path) : $logo_path;
$logo_url = ($logo_path !== '' && function_exists('mms_asset_url')) ? mms_asset_url($logo_path, true) : $logo_path;
$running_text = trim((string)($comp['running_text'] ?? ''));
if ($running_text === '') {
    $running_text = 'UTAMAKAN KESELAMATAN DAN KESEHATAN KERJA (K3) - SAFETY FIRST - GUNAKAN APD LENGKAP - JAGA KUALITAS DAN DISIPLIN PROSES';
}

$header_logo = '<i class="bi bi-hdd-rack-fill" style="font-size:2.5rem;color:#fbbf24"></i>';
if (!empty($logo_abs) && is_file($logo_abs) && !empty($logo_url)) {
    $header_logo = '<img src="' . htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8') . '" alt="Logo" style="height:60px;width:auto;background:rgba(255,255,255,.95);padding:5px 7px;border-radius:10px;">';
}
try {
    $pdo->exec("ALTER TABLE production_assignments ADD COLUMN machine_id INT NULL AFTER operator_id");
} catch (Exception $e) {
    // Abaikan jika kolom sudah ada.
}

if (!function_exists('tvp_scalar')) {
    function tvp_scalar(PDO $pdo, $sql, array $params = [], $default = 0) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $val = $stmt->fetchColumn();
            return ($val === false || $val === null) ? $default : $val;
        } catch (Throwable $e) {
            return $default;
        }
    }
}
if (!function_exists('tvp_rows')) {
    function tvp_rows(PDO $pdo, $sql, array $params = []) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
if (!function_exists('tvp_num')) {
    function tvp_num($n) {
        return number_format((float)$n, 0, ',', '.');
    }
}
if (!function_exists('tvp_num_short')) {
    function tvp_num_short($n) {
        $n = (float)$n;
        $abs = abs($n);
        if ($abs >= 1000000) return number_format($n / 1000000, 1, ',', '.') . 'M';
        if ($abs >= 1000) return number_format($n / 1000, 1, ',', '.') . 'K';
        return number_format($n, 0, ',', '.');
    }
}
if (!function_exists('tvp_pct')) {
    function tvp_pct($num, $den, $default = 0.0) {
        $den = (float)$den;
        if ($den <= 0) return (float)$default;
        return round(((float)$num / $den) * 100, 1);
    }
}
if (!function_exists('tvp_upper')) {
    function tvp_upper($text) {
        if (function_exists('mb_strtoupper')) return mb_strtoupper((string)$text, 'UTF-8');
        return strtoupper((string)$text);
    }
}
if (!function_exists('tvp_machine_process_bucket')) {
    function tvp_machine_process_bucket($processName) {
        $p = strtolower(trim((string)$processName));
        if ($p === '') return 'Other';
        if (strpos($p, 'fibre laser') !== false || strpos($p, 'fiber laser') !== false) return 'Fibre Laser';
        if (strpos($p, 'co laser') !== false) return 'CO Laser';
        if (strpos($p, 'acrylic') !== false && strpos($p, 'bending') !== false) return 'Acrylic Bending';
        if (strpos($p, 'metal bending') !== false || (strpos($p, 'bending') !== false && strpos($p, 'acrylic') === false)) return 'Metal Bending';
        if (strpos($p, 'welding') !== false || preg_match('/\blas\b/', $p)) return 'Welding';
        return 'Other';
    }
}

$today = date('Y-m-d');

// Output summary hari ini (berdasarkan task produksi selesai hari ini)
$out = tvp_rows(
    $pdo,
    "SELECT COALESCE(SUM(qty_good),0) AS good_qty,
            COALESCE(SUM(qty_reject),0) AS reject_qty,
            COUNT(*) AS task_done
     FROM production_assignments
     WHERE status='completed' AND DATE(COALESCE(end_time, created_at)) = CURDATE()"
);
$outRow = $out[0] ?? ['good_qty' => 0, 'reject_qty' => 0, 'task_done' => 0];
$good_today = (float)$outRow['good_qty'];
$reject_today = (float)$outRow['reject_qty'];
$total_today = $good_today + $reject_today;
$yield_today = tvp_pct($good_today, $total_today, 100);
$task_done_today = (int)$outRow['task_done'];

$assignment_status_rows = tvp_rows($pdo, "SELECT status, COUNT(*) AS cnt FROM production_assignments GROUP BY status");
$assign_dist = ['pending' => 0, 'assigned' => 0, 'in_progress' => 0, 'hold' => 0, 'completed' => 0];
foreach ($assignment_status_rows as $r) {
    $st = strtolower((string)($r['status'] ?? ''));
    if (isset($assign_dist[$st])) $assign_dist[$st] = (int)($r['cnt'] ?? 0);
}

$active_jobs = tvp_rows(
    $pdo,
    "SELECT pa.id, pa.process_name, pa.status, pa.qty_input, pa.qty_good, pa.qty_reject,
            pa.start_time, pa.created_at,
            spk.spk_number, spk.project_name, spk.deadline_date, spk.priority,
            u.fullname AS operator_name
     FROM production_assignments pa
     LEFT JOIN spk ON spk.id = pa.spk_id
     LEFT JOIN users u ON u.id = pa.operator_id
     WHERE pa.status IN ('in_progress','hold','assigned')
     ORDER BY FIELD(pa.status,'in_progress','hold','assigned'), COALESCE(pa.start_time, pa.created_at) ASC, pa.id ASC
     LIMIT 12"
);

$running_jobs_machine_rows = tvp_rows(
    $pdo,
    "SELECT pa.id, pa.machine_id, pa.process_name, pa.start_time, pa.created_at,
            GREATEST(TIMESTAMPDIFF(SECOND, COALESCE(pa.start_time, pa.created_at), NOW()), 0) AS runtime_seconds,
            spk.spk_number,
            u.fullname AS operator_name
     FROM production_assignments pa
     LEFT JOIN spk ON spk.id = pa.spk_id
     LEFT JOIN users u ON u.id = pa.operator_id
     WHERE pa.status = 'in_progress'
     ORDER BY COALESCE(pa.start_time, pa.created_at) ASC, pa.id ASC
     LIMIT 200"
);

$recent_done_jobs = tvp_rows(
    $pdo,
    "SELECT pa.process_name, pa.qty_good, pa.qty_reject, pa.end_time,
            spk.spk_number, spk.project_name,
            u.fullname AS operator_name
     FROM production_assignments pa
     LEFT JOIN spk ON spk.id = pa.spk_id
     LEFT JOIN users u ON u.id = pa.operator_id
     WHERE pa.status='completed'
     ORDER BY COALESCE(pa.end_time, pa.created_at) DESC, pa.id DESC
     LIMIT 12"
);

$output_by_process_rows = tvp_rows(
    $pdo,
    "SELECT COALESCE(process_name,'(Tanpa Proses)') AS process_name,
            COALESCE(SUM(qty_good),0) AS good_qty,
            COALESCE(SUM(qty_reject),0) AS reject_qty
     FROM production_assignments
     WHERE status='completed' AND DATE(COALESCE(end_time, created_at)) = CURDATE()
     GROUP BY process_name
     ORDER BY good_qty DESC, process_name ASC
     LIMIT 8"
);
// Machine data
$target_processes = ['Fibre Laser', 'CO Laser', 'Metal Bending', 'Acrylic Bending', 'Welding', 'Other'];
$in_clause = "'" . implode("','", array_map(static fn($v) => str_replace("'", "''", $v), $target_processes)) . "'";
$machines = tvp_rows($pdo, "SELECT * FROM machines WHERE process_type IN ($in_clause) ORDER BY process_type, machine_name ASC");
$machine_counts = ['active' => 0, 'idle' => 0, 'maintenance' => 0, 'broken' => 0];
$running_slots_by_bucket = [];
foreach ($target_processes as $tp) {
    $running_slots_by_bucket[$tp] = 0;
}
$running_job_slots_by_machine_id = [];
$running_job_slots_by_bucket = [];
foreach ($target_processes as $tp) {
    $running_job_slots_by_bucket[$tp] = [];
}
foreach ($running_jobs_machine_rows as $rj) {
    $machineId = (int)($rj['machine_id'] ?? 0);
    if ($machineId > 0) {
        if (!isset($running_job_slots_by_machine_id[$machineId])) {
            $running_job_slots_by_machine_id[$machineId] = [];
        }
        $running_job_slots_by_machine_id[$machineId][] = [
            'process_name' => (string)($rj['process_name'] ?? ''),
            'spk_number' => (string)($rj['spk_number'] ?? ''),
            'operator_name' => (string)($rj['operator_name'] ?? ''),
            'start_at' => (string)($rj['start_time'] ?: $rj['created_at'] ?: ''),
            'runtime_seconds' => (int)($rj['runtime_seconds'] ?? 0),
        ];
        continue;
    }
    $bucket = tvp_machine_process_bucket($rj['process_name'] ?? '');
    if (!isset($running_job_slots_by_bucket[$bucket])) {
        $running_job_slots_by_bucket[$bucket] = [];
    }
    $running_slots_by_bucket[$bucket] = (int)($running_slots_by_bucket[$bucket] ?? 0) + 1;
    $running_job_slots_by_bucket[$bucket][] = [
        'process_name' => (string)($rj['process_name'] ?? ''),
        'spk_number' => (string)($rj['spk_number'] ?? ''),
        'operator_name' => (string)($rj['operator_name'] ?? ''),
        'start_at' => (string)($rj['start_time'] ?: $rj['created_at'] ?: ''),
        'runtime_seconds' => (int)($rj['runtime_seconds'] ?? 0),
    ];
}
foreach ($machines as &$m) {
    $masterStatus = strtolower((string)($m['status'] ?? 'active'));
    $displayStatus = 'idle';
    if ($masterStatus === 'broken') {
        $displayStatus = 'broken';
    } elseif ($masterStatus === 'maintenance') {
        $displayStatus = 'maintenance';
    } else {
        $machineId = (int)($m['id'] ?? 0);
        $directSlot = null;
        if ($machineId > 0 && !empty($running_job_slots_by_machine_id[$machineId])) {
            $directSlot = array_shift($running_job_slots_by_machine_id[$machineId]);
        }
        if (is_array($directSlot)) {
            $displayStatus = 'active';
            $m['tv_running_process'] = (string)($directSlot['process_name'] ?? '');
            $m['tv_running_spk'] = (string)($directSlot['spk_number'] ?? '');
            $m['tv_running_operator'] = (string)($directSlot['operator_name'] ?? '');
            $m['tv_running_start_at'] = (string)($directSlot['start_at'] ?? '');
            $m['tv_running_runtime_seconds'] = (int)($directSlot['runtime_seconds'] ?? 0);
        } else {
        $bucket = tvp_machine_process_bucket($m['process_type'] ?? 'Other');
        $quota = (int)($running_slots_by_bucket[$bucket] ?? 0);
        if ($quota > 0) {
            $displayStatus = 'active';
            $running_slots_by_bucket[$bucket] = $quota - 1;
            $slot = null;
            if (!empty($running_job_slots_by_bucket[$bucket])) {
                $slot = array_shift($running_job_slots_by_bucket[$bucket]);
            }
            if (is_array($slot)) {
                $m['tv_running_process'] = (string)($slot['process_name'] ?? '');
                $m['tv_running_spk'] = (string)($slot['spk_number'] ?? '');
                $m['tv_running_operator'] = (string)($slot['operator_name'] ?? '');
                $m['tv_running_start_at'] = (string)($slot['start_at'] ?? '');
                $m['tv_running_runtime_seconds'] = (int)($slot['runtime_seconds'] ?? 0);
            }
        } else {
            $displayStatus = 'idle';
        }
        }
    }
    $m['tv_display_status'] = $displayStatus;
    if (isset($machine_counts[$displayStatus])) {
        $machine_counts[$displayStatus]++;
    }
}
$m = null;
$machine_total = count($machines);
$machine_slides = array_chunk($machines, 6);
if (empty($machine_slides)) $machine_slides = [[]];

// QC summary & queue
$qc_today_row = tvp_rows(
    $pdo,
    "SELECT COUNT(*) AS qc_count,
            SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS qc_ok_docs,
            SUM(CASE WHEN status='ng' THEN 1 ELSE 0 END) AS qc_ng_docs,
            COALESCE(SUM(qty_pass),0) AS qty_pass,
            COALESCE(SUM(qty_reject),0) AS qty_reject
     FROM qc_production
     WHERE qc_date = CURDATE()"
)[0] ?? [];
$qc_today_count = (int)($qc_today_row['qc_count'] ?? 0);
$qc_today_ok_docs = (int)($qc_today_row['qc_ok_docs'] ?? 0);
$qc_today_ng_docs = (int)($qc_today_row['qc_ng_docs'] ?? 0);
$qc_today_pass_qty = (float)($qc_today_row['qty_pass'] ?? 0);
$qc_today_reject_qty = (float)($qc_today_row['qty_reject'] ?? 0);

$qc_latest_map = [];
$qc_latest_rows = tvp_rows(
    $pdo,
    "SELECT q1.*
     FROM qc_production q1
     JOIN (SELECT spk_id, MAX(id) AS max_id FROM qc_production GROUP BY spk_id) q2 ON q1.id = q2.max_id"
);
foreach ($qc_latest_rows as $q) {
    $qc_latest_map[(int)$q['spk_id']] = $q;
}

$spk_qc_monitor = tvp_rows(
    $pdo,
    "SELECT id, spk_number, project_name, status, deadline_date, priority
     FROM spk
     WHERE status IN ('released','in_production','completed')
     ORDER BY FIELD(status,'completed','in_production','released'),
              FIELD(priority,'urgent','normal'),
              id DESC
     LIMIT 14"
);

$qc_queue_rows = [];
$qc_waiting_count = 0;
$qc_ng_followup_count = 0;
foreach ($spk_qc_monitor as $s) {
    $sid = (int)$s['id'];
    $spk_st = strtolower((string)($s['status'] ?? ''));
    $q = $qc_latest_map[$sid] ?? null;
    $qc_st = strtolower((string)($q['status'] ?? ''));

    if ($spk_st === 'completed') {
        if ($q === null || $qc_st === '' || $qc_st === 'draft') {
            $label = 'MENUNGGU QC';
            $tone = 'wait';
            $qc_waiting_count++;
        } elseif ($qc_st === 'ng') {
            $label = 'QC NG - FOLLOW UP';
            $tone = 'danger';
            $qc_ng_followup_count++;
        } elseif ($qc_st === 'completed') {
            $label = 'QC SELESAI';
            $tone = 'ok';
        } else {
            $label = 'QC ' . tvp_upper($qc_st);
            $tone = 'wait';
        }
    } elseif ($spk_st === 'in_production') {
        $label = 'PROSES PRODUKSI';
        $tone = 'run';
        if ($q && $qc_st === 'ng') {
            $label = 'PRODUKSI ULANG / CEK NG';
            $tone = 'danger';
            $qc_ng_followup_count++;
        }
    } else {
        $label = 'READY PRODUKSI';
        $tone = 'idle';
    }

    $qc_queue_rows[] = [
        'spk_number' => (string)($s['spk_number'] ?? '-'),
        'project_name' => (string)($s['project_name'] ?? '-'),
        'deadline_date' => (string)($s['deadline_date'] ?? ''),
        'priority' => (string)($s['priority'] ?? 'normal'),
        'spk_status' => (string)($s['status'] ?? ''),
        'qc_label' => $label,
        'tone' => $tone,
    ];
}

// Alerts / status line
$alert_parts = [];
$bg_alert = 'ok';
if ($machine_counts['broken'] > 0) {
    $alert_parts[] = 'MESIN DOWN: ' . $machine_counts['broken'];
    $bg_alert = 'danger';
}
if ($machine_counts['maintenance'] > 0) {
    $alert_parts[] = 'MAINTENANCE: ' . $machine_counts['maintenance'];
    if ($bg_alert !== 'danger') $bg_alert = 'warn';
}
if ((int)$assign_dist['in_progress'] <= 0) {
    $alert_parts[] = 'TIDAK ADA JOB RUNNING';
    if ($bg_alert === 'ok') $bg_alert = 'idle';
}
if ($qc_ng_followup_count > 0) {
    $alert_parts[] = 'QC NG FOLLOW UP: ' . $qc_ng_followup_count;
    $bg_alert = 'danger';
}
if ($qc_waiting_count > 0) {
    $alert_parts[] = 'ANTRIAN QC: ' . $qc_waiting_count;
    if ($bg_alert === 'ok') $bg_alert = 'warn';
}
if (empty($alert_parts)) {
    $alert_parts[] = 'PRODUKSI & QC BERJALAN NORMAL';
}
$status_text = implode(' | ', $alert_parts);

// chart datasets
$proc_labels = [];
$proc_good = [];
$proc_ng = [];
foreach ($output_by_process_rows as $r) {
    $proc_labels[] = (string)($r['process_name'] ?? '-');
    $proc_good[] = (float)($r['good_qty'] ?? 0);
    $proc_ng[] = (float)($r['reject_qty'] ?? 0);
}
if (empty($proc_labels)) {
    $proc_labels = ['Belum Ada Output'];
    $proc_good = [0];
    $proc_ng = [0];
}

$assign_chart_labels = ['Pending', 'Assigned', 'Running', 'Hold', 'Completed'];
$assign_chart_values = [
    (int)$assign_dist['pending'],
    (int)$assign_dist['assigned'],
    (int)$assign_dist['in_progress'],
    (int)$assign_dist['hold'],
    (int)$assign_dist['completed'],
];
$qc_chart_labels = ['QC OK Today', 'QC NG Today'];
$qc_chart_values = [(int)$qc_today_ok_docs, (int)$qc_today_ng_docs];

$json = static fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
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
        :root {
            --bg-1: #090f1a;
            --bg-2: #111c2f;
            --panel: rgba(20, 32, 53, 0.82);
            --line: rgba(148, 163, 184, 0.16);
            --ink: #eef5ff;
            --muted: #aebfd4;
            --cyan: #38bdf8;
            --green: #22c55e;
            --amber: #f59e0b;
            --red: #ef4444;
            --violet: #a855f7;
            --slate: #64748b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 12px 12px 72px;
            color: var(--ink);
            font-family: 'Segoe UI', Tahoma, sans-serif;
            overflow: hidden;
            background:
                radial-gradient(circle at 12% -8%, rgba(56,189,248,0.16), transparent 36%),
                radial-gradient(circle at 100% 0%, rgba(245,158,11,0.1), transparent 30%),
                linear-gradient(180deg, var(--bg-1), var(--bg-2));
        }
        .glass { background: var(--panel); border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 14px 26px rgba(2,8,23,.28); backdrop-filter: blur(8px); }
        .top-wrap { display:grid; grid-template-columns: 1.3fr .9fr; gap:10px; margin-bottom:10px; }
        .top-main, .top-side { padding: 12px 14px; }
        .top-main { display:flex; align-items:center; gap:12px; min-height:88px; }
        .title-main { margin:0; font-size: clamp(1.2rem, 1.6vw, 1.9rem); font-weight: 800; line-height: 1.05; letter-spacing: .02em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .title-sub { color: var(--muted); font-size: .92rem; margin-top: 4px; }
        .slide-badge {
            display: inline-flex;
            align-items: center;
            margin-left: 8px;
            padding: .12rem .45rem;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, .22);
            background: rgba(255,255,255,.04);
            color: #dbeafe;
            font-size: .66rem;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
        }
        .live-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--red); display:inline-block; animation: blink 1s infinite; margin: 0 7px; }
        @keyframes blink { 50% { opacity: .24; } }
        .top-side { display:grid; grid-template-columns: 1fr 1fr; gap:8px; }
        .clock-box, .mini-box { border:1px solid var(--line); border-radius:12px; background: rgba(255,255,255,.03); padding: 9px 10px; }
        .clock-time { font-size: 1.8rem; font-weight: 800; line-height:1; }
        .clock-date { color: var(--muted); font-size: .85rem; margin-top: 4px; }
        .mini-grid { display:grid; grid-template-columns: 1fr 1fr; gap:8px; }
        .mini-label { color: var(--muted); font-size: .67rem; text-transform: uppercase; letter-spacing: .08em; }
        .mini-value { font-size: 1rem; font-weight: 700; }

        .kpi-grid { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:10px; margin-bottom:10px; }
        .kpi-card { padding: 11px 12px; min-height: 92px; position:relative; overflow:hidden; }
        .kpi-card::after { content:""; position:absolute; width:110px; height:110px; right:-25px; top:-34px; border-radius:50%; background: radial-gradient(circle, rgba(255,255,255,.14), transparent 70%); }
        .kpi-label { font-size: .71rem; text-transform: uppercase; letter-spacing: .08em; color: #ccdbef; }
        .kpi-value { font-size: 1.48rem; font-weight: 800; line-height:1.1; }
        .kpi-meta { margin-top:6px; color:#d8e6f8; font-size:.79rem; }
        .tone-a { background: linear-gradient(135deg, rgba(59,130,246,.18), rgba(56,189,248,.14)); }
        .tone-b { background: linear-gradient(135deg, rgba(34,197,94,.14), rgba(20,184,166,.14)); }
        .tone-c { background: linear-gradient(135deg, rgba(168,85,247,.14), rgba(99,102,241,.13)); }
        .tone-d { background: linear-gradient(135deg, rgba(245,158,11,.14), rgba(249,115,22,.12)); }

        .main-grid { display:grid; grid-template-columns: 1.42fr .98fr; gap:10px; height: calc(100vh - 236px); }
        .left-col, .right-col { display:grid; gap:10px; min-height:0; }
        .left-col { grid-template-rows: .82fr 1.18fr; }
        .right-col { grid-template-rows: .9fr 1.1fr; }
        .panel { padding: 12px 13px; min-height:0; display:flex; flex-direction:column; }
        .panel-head { display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:8px; }
        .panel-head h4 { margin:0; font-size:.9rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#dbeafe; }
        .panel-head small { color: var(--muted); }
        .chart-wrap { position:relative; flex:1 1 auto; min-height:130px; }
        .split-2 { display:grid; grid-template-columns: 1fr 1fr; gap:10px; min-height:0; }
        .mini-panel { border:1px solid var(--line); border-radius:12px; background: rgba(255,255,255,.025); padding:9px; display:flex; flex-direction:column; min-height:0; }
        .mini-panel h5 { margin:0 0 7px 0; font-size:.75rem; text-transform:uppercase; letter-spacing:.08em; color:#dbeafe; }

        .machine-carousel { flex:1 1 auto; min-height:0; }
        .machine-grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; }
        .machine-card { border:1px solid var(--line); border-radius:12px; background: rgba(255,255,255,.025); overflow:hidden; min-height:126px; }
        .machine-head { padding:8px 10px; font-size:.82rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; display:flex; justify-content:space-between; align-items:center; gap:6px; }
        .machine-head.active { background: rgba(34,197,94,.18); color:#bbf7d0; }
        .machine-head.idle { background: rgba(148,163,184,.14); color:#dbeafe; }
        .machine-head.maintenance { background: rgba(245,158,11,.18); color:#fde68a; }
        .machine-head.broken { background: rgba(239,68,68,.2); color:#fecaca; }
        .machine-body { padding: 10px; display:grid; grid-template-columns: auto 1fr; gap:10px; align-items:center; }
        .machine-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; border:1px solid rgba(148,163,184,.2); }
        .machine-icon.active { color:#86efac; background: rgba(34,197,94,.08); }
        .machine-icon.idle { color:#cbd5e1; background: rgba(148,163,184,.08); }
        .machine-icon.maintenance { color:#fde68a; background: rgba(245,158,11,.08); }
        .machine-icon.broken { color:#fca5a5; background: rgba(239,68,68,.08); }
        .machine-status { font-size:1rem; font-weight:800; line-height:1; }
        .machine-status-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .run-beacon {
            width: 11px;
            height: 11px;
            border-radius: 50%;
            background: #ef4444;
            border: 2px solid #ffffff;
            box-shadow: 0 0 0 0 rgba(239,68,68,.7);
            animation: runBeaconPulse 1s infinite;
            flex: 0 0 auto;
        }
        @keyframes runBeaconPulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239,68,68,.7); opacity: 1; }
            50% { background:#ffffff; border-color:#ef4444; opacity: .95; }
            70% { box-shadow: 0 0 0 8px rgba(239,68,68,0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239,68,68,0); opacity: 1; }
        }
        .machine-meta { margin-top:4px; color:var(--muted); font-size:.74rem; }
        .machine-runtime {
            margin-top: 5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: .14rem .45rem;
            border-radius: 999px;
            border: 1px solid rgba(248,113,113,.28);
            background: rgba(239,68,68,.08);
            color: #fee2e2;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .03em;
        }
        .machine-runtime i { color:#fecaca; }
        .machine-tag { display:inline-block; margin-top:5px; padding:.12rem .42rem; border-radius:999px; border:1px solid rgba(148,163,184,.2); background:rgba(255,255,255,.03); color:#dbeafe; font-size:.66rem; }

        .table-tv { width:100%; border-collapse: collapse; font-size: .79rem; }
        .table-tv th, .table-tv td { border-bottom: 1px solid rgba(148,163,184,.12); padding: 5px 4px; vertical-align: middle; }
        .table-tv th { color:#b8c9de; font-size:.66rem; text-transform:uppercase; letter-spacing:.08em; }
        .table-tv td { color:#edf5ff; }
        .right { text-align:right; }
        .nowrap { white-space: nowrap; }
        .muted { color: var(--muted); }
        .badge-soft { display:inline-block; padding:.14rem .45rem; border-radius:999px; border:1px solid rgba(148,163,184,.22); background: rgba(255,255,255,.03); font-size:.66rem; }
        .st-run { color:#7dd3fc; border-color: rgba(56,189,248,.3); background: rgba(56,189,248,.1); }
        .st-hold { color:#fde68a; border-color: rgba(245,158,11,.3); background: rgba(245,158,11,.1); }
        .st-assigned { color:#c4b5fd; border-color: rgba(168,85,247,.3); background: rgba(168,85,247,.1); }
        .q-ok { color:#86efac; border-color: rgba(34,197,94,.3); background: rgba(34,197,94,.1); }
        .q-wait { color:#fde68a; border-color: rgba(245,158,11,.3); background: rgba(245,158,11,.1); }
        .q-danger { color:#fca5a5; border-color: rgba(239,68,68,.3); background: rgba(239,68,68,.1); }
        .q-idle { color:#cbd5e1; border-color: rgba(148,163,184,.25); background: rgba(148,163,184,.08); }

        .footer-fixed { position: fixed; left:0; right:0; bottom:0; z-index:999; }
        .status-line { font-size: 1rem; font-weight: 800; letter-spacing: .06em; text-align:center; padding:8px 10px; }
        .status-line.ok { background:#0f5132; color:#d1fae5; }
        .status-line.warn { background:#7c5a10; color:#fef3c7; }
        .status-line.danger { background:#7f1d1d; color:#fee2e2; }
        .status-line.idle { background:#334155; color:#e2e8f0; }
        .marquee-line { background: rgba(0,0,0,.92); color:#facc15; border-top:2px solid #facc15; padding:5px 0; }
        .marquee-line marquee { font-family:'Courier New', monospace; font-weight:700; font-size:.94rem; }

        /* Slide mode (1: Produksi, 2: Mesin, 3: QC) */
        body.tv-slide-production #panelMachine,
        body.tv-slide-production #panelQc,
        body.tv-slide-machine #panelProduction,
        body.tv-slide-machine #panelJobs,
        body.tv-slide-machine #panelQc,
        body.tv-slide-qc #panelMachine,
        body.tv-slide-qc #panelProduction,
        body.tv-slide-qc #panelJobs {
            display: none !important;
        }
        body.tv-slide-machine .right-col,
        body.tv-slide-qc .left-col {
            display: none !important;
        }
        body.tv-slide-machine .main-grid,
        body.tv-slide-qc .main-grid {
            grid-template-columns: 1fr;
        }
        body.tv-slide-production .left-col,
        body.tv-slide-production .right-col,
        body.tv-slide-machine .left-col,
        body.tv-slide-qc .right-col {
            grid-template-rows: 1fr;
        }
        body.tv-slide-machine #panelMachine,
        body.tv-slide-qc #panelQc {
            height: 100%;
        }
        body.tv-slide-machine .machine-carousel {
            min-height: 0;
        }

        @media (max-width: 1400px) {
            body { overflow:auto; }
            .top-wrap, .main-grid { grid-template-columns: 1fr; height:auto; }
            .top-side, .kpi-grid, .split-2 { grid-template-columns: repeat(2, 1fr); }
            .left-col, .right-col { grid-template-rows: auto; }
            .machine-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 900px) {
            .kpi-grid, .split-2, .top-side { grid-template-columns: 1fr; }
            .machine-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="tv-slide-production">
<div class="top-wrap">
    <div class="glass top-main">
        <div><?= $header_logo ?></div>
        <div style="min-width:0; flex:1 1 auto;">
            <h1 class="title-main"><?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="title-sub">TV Produksi Shop Floor - Produksi & QC <span class="live-dot"></span> Live Monitor Operator <span id="tvProdSlideBadge" class="slide-badge">Slide Produksi</span></div>
        </div>
    </div>
    <div class="glass top-side">
        <div class="clock-box">
            <div id="tvProdClock" class="clock-time">--:--:--</div>
            <div id="tvProdDate" class="clock-date">-</div>
            <div class="muted" style="font-size:.74rem; margin-top:4px;">Auto refresh 90 detik</div>
        </div>
        <div class="mini-grid">
            <div class="mini-box"><div class="mini-label">Mesin Running</div><div class="mini-value"><?= tvp_num($machine_counts['active']) ?>/<?= tvp_num($machine_total) ?></div></div>
            <div class="mini-box"><div class="mini-label">Mesin Down</div><div class="mini-value" style="color:<?= $machine_counts['broken'] > 0 ? '#fca5a5' : '#86efac' ?>"><?= tvp_num($machine_counts['broken']) ?></div></div>
            <div class="mini-box"><div class="mini-label">Job Running</div><div class="mini-value"><?= tvp_num($assign_dist['in_progress']) ?></div></div>
            <div class="mini-box"><div class="mini-label">Antrian QC</div><div class="mini-value"><?= tvp_num($qc_waiting_count) ?></div></div>
        </div>
    </div>
</div>

<div class="kpi-grid">
    <div class="glass kpi-card tone-a"><div class="kpi-label">Output Good Hari Ini</div><div class="kpi-value"><?= tvp_num_short($good_today) ?></div><div class="kpi-meta">Task selesai <?= tvp_num($task_done_today) ?></div></div>
    <div class="glass kpi-card tone-d"><div class="kpi-label">Reject / NG Hari Ini</div><div class="kpi-value"><?= tvp_num_short($reject_today) ?></div><div class="kpi-meta">QC NG doc hari ini <?= tvp_num($qc_today_ng_docs) ?></div></div>
    <div class="glass kpi-card tone-b"><div class="kpi-label">Yield Produksi Hari Ini</div><div class="kpi-value"><?= number_format($yield_today, 1, ',', '.') ?>%</div><div class="kpi-meta">Good vs total output</div></div>
    <div class="glass kpi-card tone-c"><div class="kpi-label">QC Pass Qty Hari Ini</div><div class="kpi-value"><?= tvp_num_short($qc_today_pass_qty) ?></div><div class="kpi-meta">QC docs <?= tvp_num($qc_today_count) ?> | NG <?= tvp_num($qc_today_reject_qty) ?></div></div>
    <div class="glass kpi-card tone-c"><div class="kpi-label">Job Production Queue</div><div class="kpi-value"><?= tvp_num($assign_dist['assigned'] + $assign_dist['pending']) ?></div><div class="kpi-meta">Assigned <?= tvp_num($assign_dist['assigned']) ?> | Pending <?= tvp_num($assign_dist['pending']) ?></div></div>
    <div class="glass kpi-card tone-d"><div class="kpi-label">Job Hold</div><div class="kpi-value"><?= tvp_num($assign_dist['hold']) ?></div><div class="kpi-meta">Perlu follow up supervisor / material / mesin</div></div>
    <div class="glass kpi-card tone-a"><div class="kpi-label">SPK Aktif</div><div class="kpi-value"><?= tvp_num(tvp_scalar($pdo, "SELECT COUNT(*) FROM spk WHERE status IN ('released','in_production')", [], 0)) ?></div><div class="kpi-meta">SPK ready/wait <?= tvp_num($spk_qc_monitor ? count(array_filter($spk_qc_monitor, fn($x)=>in_array(strtolower((string)($x['spk_status'] ?? $x['status'] ?? '')), ['released','in_production'], true))) : 0) ?></div></div>
    <div class="glass kpi-card tone-b"><div class="kpi-label">SPK Menunggu / Proses QC</div><div class="kpi-value"><?= tvp_num($qc_waiting_count + $qc_ng_followup_count) ?></div><div class="kpi-meta">Waiting QC <?= tvp_num($qc_waiting_count) ?> | NG follow up <?= tvp_num($qc_ng_followup_count) ?></div></div>
</div>

<div id="tvProdMainGrid" class="main-grid">
    <div class="left-col">
        <div id="panelMachine" class="glass panel">
            <div class="panel-head"><h4><i class="bi bi-cpu me-2"></i>Status Mesin Shop Floor</h4><small>Slide otomatis 10 detik / halaman</small></div>
            <div id="machineCarousel" class="carousel slide machine-carousel" data-bs-ride="carousel" data-bs-interval="10000">
                <div class="carousel-inner h-100">
                    <?php foreach ($machine_slides as $idx => $slide): ?>
                        <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?> h-100">
                            <div class="machine-grid">
                                <?php if (empty($slide)): ?>
                                    <div class="machine-card" style="grid-column: 1 / -1;"><div class="machine-body"><div class="machine-status">DATA MESIN BELUM TERSEDIA</div><div class="machine-meta">Silakan cek master mesin</div></div></div>
                                <?php else: foreach ($slide as $m):
                                    $mStatus = strtolower((string)($m['tv_display_status'] ?? $m['status'] ?? 'idle'));
                                    if (!in_array($mStatus, ['active','idle','maintenance','broken'], true)) $mStatus = 'idle';
                                    $statusLabel = $mStatus === 'active'
                                        ? 'RUNNING'
                                        : ($mStatus === 'idle'
                                            ? 'STANDBY'
                                            : ($mStatus === 'maintenance' ? 'MAINTENANCE' : 'DOWN / RUSAK'));
                                    $icon = $mStatus === 'active'
                                        ? 'bi-gear-wide-connected'
                                        : ($mStatus === 'idle'
                                            ? 'bi-pause-circle'
                                            : ($mStatus === 'maintenance' ? 'bi-tools' : 'bi-exclamation-triangle-fill'));
                                    $runningStartAt = (string)($m['tv_running_start_at'] ?? '');
                                    $runningRuntimeSeconds = (int)($m['tv_running_runtime_seconds'] ?? 0);
                                    if ($runningRuntimeSeconds < 0) $runningRuntimeSeconds = 0;
                                    $runningStartTs = $runningStartAt !== '' ? strtotime($runningStartAt) : false;
                                ?>
                                    <div class="machine-card">
                                        <div class="machine-head <?= $mStatus ?>">
                                            <span><?= htmlspecialchars((string)($m['machine_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span style="font-size:.68rem;"><?= htmlspecialchars((string)($m['machine_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="machine-body">
                                            <div class="machine-icon <?= $mStatus ?>"><i class="bi <?= $icon ?> <?= $mStatus === 'active' ? 'bi-spin' : '' ?>"></i></div>
                                            <div>
                                                <div class="machine-status-row">
                                                    <?php if ($mStatus === 'active'): ?><span class="run-beacon" aria-hidden="true"></span><?php endif; ?>
                                                    <div class="machine-status" style="color:<?= $mStatus === 'active' ? '#86efac' : ($mStatus === 'idle' ? '#cbd5e1' : ($mStatus === 'maintenance' ? '#fde68a' : '#fca5a5')) ?>"><?= $statusLabel ?></div>
                                                </div>
                                                <div class="machine-meta"><i class="bi bi-diagram-3"></i> <?= htmlspecialchars((string)($m['process_type'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                                                <div class="machine-meta"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars((string)($m['location'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php if ($mStatus === 'active' && $runningStartTs): ?>
                                                    <div
                                                        class="machine-runtime js-machine-runtime"
                                                        data-elapsed-seconds="<?= (int)$runningRuntimeSeconds ?>"
                                                        title="Mulai <?= htmlspecialchars(date('d/m/Y H:i:s', (int)$runningStartTs), ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="bi bi-broadcast-pin"></i>
                                                        <span>Run 00:00:00</span>
                                                    </div>
                                                    <?php if (!empty($m['tv_running_spk'])): ?>
                                                        <div class="machine-meta"><i class="bi bi-clipboard-data"></i> <?= htmlspecialchars((string)$m['tv_running_spk'], ENT_QUOTES, 'UTF-8') ?><?= !empty($m['tv_running_operator']) ? ' | ' . htmlspecialchars((string)$m['tv_running_operator'], ENT_QUOTES, 'UTF-8') : '' ?></div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if (!empty($m['capacity_per_hour'])): ?><span class="machine-tag">Cap/Jam: <?= tvp_num_short($m['capacity_per_hour']) ?></span><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div id="panelProduction" class="glass panel">
            <div class="panel-head"><h4><i class="bi bi-bar-chart-line me-2"></i>Output per Proses & Distribusi Job</h4><small>Ringkasan produksi hari ini</small></div>
            <div class="split-2" style="flex:1 1 auto; min-height:0;">
                <div class="mini-panel"><h5>Output per Proses (Today)</h5><div class="chart-wrap"><canvas id="prodOutputChart"></canvas></div></div>
                <div class="mini-panel"><h5>Status Job Produksi</h5><div class="chart-wrap"><canvas id="prodAssignChart"></canvas></div></div>
            </div>
        </div>
    </div>

    <div class="right-col">
        <div id="panelJobs" class="glass panel">
            <div class="panel-head"><h4><i class="bi bi-list-task me-2"></i>Job Produksi Aktif (Operator)</h4><small>Running / Hold / Assigned</small></div>
            <div class="split-2" style="flex:1 1 auto; min-height:0;">
                <div class="mini-panel">
                    <h5>Job Aktif</h5>
                    <table class="table-tv">
                        <thead><tr><th>SPK / Proses</th><th>Status</th><th>Operator</th><th class="right">Qty</th></tr></thead>
                        <tbody>
                        <?php if (empty($active_jobs)): ?>
                            <tr><td colspan="4" class="muted">Tidak ada job aktif saat ini.</td></tr>
                        <?php else: foreach ($active_jobs as $r):
                            $st = strtolower((string)($r['status'] ?? ''));
                            $cls = $st === 'in_progress' ? 'st-run' : ($st === 'hold' ? 'st-hold' : 'st-assigned');
                        ?>
                            <tr>
                                <td>
                                    <strong class="nowrap"><?= htmlspecialchars((string)($r['spk_number'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <div class="muted" style="font-size:.67rem;"><?= htmlspecialchars((string)($r['process_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td class="nowrap"><span class="badge-soft <?= $cls ?>"><?= htmlspecialchars(tvp_upper(str_replace('_',' ', $st)), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars((string)($r['operator_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="right"><?= tvp_num($r['qty_input'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mini-panel">
                    <h5>Job Selesai Terakhir</h5>
                    <table class="table-tv">
                        <thead><tr><th>SPK / Proses</th><th class="right">Good</th><th class="right">NG</th><th>Jam</th></tr></thead>
                        <tbody>
                        <?php if (empty($recent_done_jobs)): ?>
                            <tr><td colspan="4" class="muted">Belum ada job selesai.</td></tr>
                        <?php else: foreach ($recent_done_jobs as $r): ?>
                            <tr>
                                <td>
                                    <strong class="nowrap"><?= htmlspecialchars((string)($r['spk_number'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <div class="muted" style="font-size:.67rem;"><?= htmlspecialchars((string)($r['process_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td class="right" style="color:#86efac;"><?= tvp_num($r['qty_good'] ?? 0) ?></td>
                                <td class="right" style="color:#fca5a5;"><?= tvp_num($r['qty_reject'] ?? 0) ?></td>
                                <td class="nowrap"><?= !empty($r['end_time']) ? htmlspecialchars(date('H:i', strtotime((string)$r['end_time'])), ENT_QUOTES, 'UTF-8') : '-' ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="panelQc" class="glass panel">
            <div class="panel-head"><h4><i class="bi bi-shield-check me-2"></i>Monitor QC Produksi</h4><small>Queue QC + status hari ini</small></div>
            <div class="split-2" style="flex:1 1 auto; min-height:0;">
                <div class="mini-panel">
                    <h5>Antrian SPK / QC</h5>
                    <table class="table-tv">
                        <thead><tr><th>SPK</th><th>QC Status</th><th>Deadline</th></tr></thead>
                        <tbody>
                        <?php if (empty($qc_queue_rows)): ?>
                            <tr><td colspan="3" class="muted">Tidak ada SPK untuk dimonitor.</td></tr>
                        <?php else: foreach ($qc_queue_rows as $r):
                            $toneCls = $r['tone'] === 'danger' ? 'q-danger' : ($r['tone'] === 'ok' ? 'q-ok' : ($r['tone'] === 'run' ? 'st-run' : ($r['tone'] === 'wait' ? 'q-wait' : 'q-idle')));
                        ?>
                            <tr>
                                <td>
                                    <strong class="nowrap"><?= htmlspecialchars($r['spk_number'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <div class="muted" style="font-size:.67rem;"><?= htmlspecialchars(tvp_upper((string)$r['spk_status']), ENT_QUOTES, 'UTF-8') ?><?= ($r['priority'] === 'urgent') ? ' | URGENT' : '' ?></div>
                                </td>
                                <td class="nowrap"><span class="badge-soft <?= $toneCls ?>"><?= htmlspecialchars($r['qc_label'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="nowrap"><?= !empty($r['deadline_date']) ? htmlspecialchars(date('d/m', strtotime((string)$r['deadline_date'])), ENT_QUOTES, 'UTF-8') : '-' ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mini-panel">
                    <h5>QC Today Summary</h5>
                    <div class="chart-wrap" style="min-height:120px;"><canvas id="qcChart"></canvas></div>
                    <table class="table-tv" style="margin-top:6px;">
                        <tbody>
                            <tr><td>Total Dokumen QC Hari Ini</td><td class="right"><strong><?= tvp_num($qc_today_count) ?></strong></td></tr>
                            <tr><td>QC OK (Dokumen)</td><td class="right" style="color:#86efac;"><?= tvp_num($qc_today_ok_docs) ?></td></tr>
                            <tr><td>QC NG (Dokumen)</td><td class="right" style="color:#fca5a5;"><?= tvp_num($qc_today_ng_docs) ?></td></tr>
                            <tr><td>Qty Pass Hari Ini</td><td class="right"><?= tvp_num_short($qc_today_pass_qty) ?></td></tr>
                            <tr><td>Qty Reject Hari Ini</td><td class="right"><?= tvp_num_short($qc_today_reject_qty) ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="footer-fixed">
    <div class="status-line <?= htmlspecialchars($bg_alert, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($status_text, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="marquee-line"><marquee behavior="scroll" direction="left" scrollamount="9"><i class="bi bi-cone-striped me-2"></i> <?= htmlspecialchars(tvp_upper($running_text), ENT_QUOTES, 'UTF-8') ?> | MACHINE RUNNING <?= tvp_num($machine_counts['active']) ?>/<?= tvp_num($machine_total) ?> | JOB RUNNING <?= tvp_num($assign_dist['in_progress']) ?> | HOLD <?= tvp_num($assign_dist['hold']) ?> | QC WAITING <?= tvp_num($qc_waiting_count) ?> | QC NG FOLLOW UP <?= tvp_num($qc_ng_followup_count) ?></marquee></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const procLabels = <?= $json($proc_labels) ?>;
const procGood = <?= $json($proc_good) ?>;
const procNg = <?= $json($proc_ng) ?>;
const assignLabels = <?= $json($assign_chart_labels) ?>;
const assignValues = <?= $json($assign_chart_values) ?>;
const qcLabels = <?= $json($qc_chart_labels) ?>;
const qcValues = <?= $json($qc_chart_values) ?>;

Chart.defaults.color = '#dbeafe';
Chart.defaults.font.family = "Segoe UI, Tahoma, sans-serif";

new Chart(document.getElementById('prodOutputChart'), {
    type: 'bar',
    data: {
        labels: procLabels,
        datasets: [
            { label: 'Good', data: procGood, backgroundColor: '#22c55e', borderRadius: 7 },
            { label: 'NG', data: procNg, backgroundColor: '#ef4444', borderRadius: 7 }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#eaf2ff', boxWidth: 12 } } },
        scales: {
            x: { grid: { display: false }, ticks: { color: '#bfd0e6', font: { size: 10 } } },
            y: { grid: { color: 'rgba(148,163,184,.12)' }, ticks: { color: '#bfd0e6' } }
        }
    }
});

new Chart(document.getElementById('prodAssignChart'), {
    type: 'doughnut',
    data: {
        labels: assignLabels,
        datasets: [{
            data: assignValues,
            backgroundColor: ['#64748b', '#a855f7', '#38bdf8', '#f59e0b', '#22c55e'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: { legend: { labels: { color: '#eaf2ff', boxWidth: 10, font: { size: 10 } } } }
    }
});

new Chart(document.getElementById('qcChart'), {
    type: 'doughnut',
    data: {
        labels: qcLabels,
        datasets: [{ data: qcValues, backgroundColor: ['#22c55e', '#ef4444'], borderWidth: 0 }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: { legend: { labels: { color: '#eaf2ff', boxWidth: 10, font: { size: 10 } } } }
    }
});

(function () {
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

(function () {
    const body = document.body;
    const badge = document.getElementById('tvProdSlideBadge');
    if (!body) return;
    const slides = [
        { cls: 'tv-slide-production', label: 'Slide Produksi' },
        { cls: 'tv-slide-machine', label: 'Slide Mesin' },
        { cls: 'tv-slide-qc', label: 'Slide QC' }
    ];
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

(function () {
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
