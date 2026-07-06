<?php
// modules/production/tasks/manage.php

$spk_id = isset($_GET['spk_id']) ? $_GET['spk_id'] : null;
if (!$spk_id) die("Error: SPK ID required.");

// Hardening schema: pastikan assignment bisa menyimpan machine_id (best effort untuk DB lama).
try {
    $pdo->exec("ALTER TABLE production_assignments ADD COLUMN machine_id INT NULL AFTER operator_id");
} catch (Exception $e) {
    // Abaikan jika kolom sudah ada / tidak punya hak alter.
}

if (!function_exists('prod_task_machine_bucket')) {
    function prod_task_machine_bucket($processName) {
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

// 1. Ambil Data SPK + Referensi SO
$sql = "SELECT spk.*, so.so_number, c.name as customer_name
        FROM spk 
        LEFT JOIN sales_orders so ON spk.sales_order_id = so.id
        LEFT JOIN customers c ON so.customer_id = c.id
        WHERE spk.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$spk_id]);
$spk = $stmt->fetch();
if (!$spk) die("Error: SPK tidak ditemukan.");

// 2. Ambil User Operator (Filter Role Slug)
$sql_op = "SELECT u.id, u.fullname, r.role_name 
           FROM users u 
           JOIN roles r ON u.role_id = r.id 
           WHERE r.role_slug = 'operator' OR r.role_slug LIKE 'op_%' 
           ORDER BY u.fullname ASC";
$operators = $pdo->query($sql_op)->fetchAll();

$machines = [];
$machine_options_by_bucket = [];
try {
    $machines = $pdo->query("SELECT id, machine_name, machine_code, process_type, status
                             FROM machines
                             ORDER BY process_type ASC, machine_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $machines = [];
}
foreach ($machines as $mc) {
    $bucket = prod_task_machine_bucket($mc['process_type'] ?? '');
    if (!isset($machine_options_by_bucket[$bucket])) {
        $machine_options_by_bucket[$bucket] = [];
    }
    $machine_options_by_bucket[$bucket][] = $mc;
}

// 3. PROSES GENERATE / UPDATE TUGAS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $processes = $_POST['process_name']; // Array
    $ops = $_POST['operator_id']; // Array
    $machine_ids = $_POST['machine_id'] ?? []; // Array
    
    try {
        $pdo->beginTransaction();
        
        // Prepare Statements
        // Ambil ID dan Status saat ini
        $stmt_check = $pdo->prepare("SELECT id, status, operator_id, machine_id FROM production_assignments WHERE spk_id = ? AND process_name = ?");
        
        // Query Insert Baru
        $stmt_insert = $pdo->prepare("INSERT INTO production_assignments (spk_id, process_name, operator_id, machine_id, status) VALUES (?, ?, ?, ?, 'assigned')");
        
        // Query Update 1: Ubah Operator DAN Status (Jika masih pending/assigned)
        $stmt_update_full = $pdo->prepare("UPDATE production_assignments SET operator_id = ?, machine_id = ?, status = 'assigned' WHERE id = ?");
        
        // Query Update 2: Ubah Operator SAJA (Jika sudah jalan/selesai, status jangan direset)
        $stmt_update_op_only = $pdo->prepare("UPDATE production_assignments SET operator_id = ?, machine_id = ? WHERE id = ?");

        for ($i = 0; $i < count($processes); $i++) {
            $proc_name = $processes[$i];
            $op_id = !empty($ops[$i]) ? $ops[$i] : null;
            $machine_id = !empty($machine_ids[$i]) ? (int)$machine_ids[$i] : null;

            if ($op_id) {
                // Cek apakah tugas sudah ada di database
                $stmt_check->execute([$spk_id, $proc_name]);
                $exist = $stmt_check->fetch();

                if ($exist) {
                    // LOGIKA PERBAIKAN DI SINI:
                    // Cek status saat ini. 
                    // Jika sudah 'in_progress', 'hold', atau 'completed', JANGAN ubah status jadi 'assigned' lagi.
                    $current_status = $exist['status'];
                    $prev_op_id = $exist['operator_id'];
                    $prev_machine_id = (int)($exist['machine_id'] ?? 0);

                    if (in_array($current_status, ['in_progress', 'hold', 'completed'])) {
                        // Hanya update operatornya saja (jika diganti), status tetap.
                        $stmt_update_op_only->execute([$op_id, $machine_id, $exist['id']]);
                    } else {
                        // Jika masih 'pending' atau 'assigned', boleh di-reset statusnya ke 'assigned'
                        $stmt_update_full->execute([$op_id, $machine_id, $exist['id']]);
                    }
                    
                    // NOTIFIKASI: Hanya kirim jika operator berubah atau penugasan ulang
                    if ($prev_op_id != $op_id || $prev_machine_id !== (int)$machine_id) {
                        if (function_exists('notify_workflow_event')) {
                            notify_workflow_event(
                                'prod.task.reassign.' . $spk_id . '.' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $proc_name)),
                                'Tugas Produksi (Re-Assign)',
                                "Anda mendapatkan tugas: " . $proc_name . " untuk SPK " . $spk['spk_number'],
                                'index.php?page=prod-operator',
                                'info',
                                ['user_ids' => [(int)$op_id], 'include_admin' => false, 'exclude_sender' => false]
                            );
                        }
                    }

                } else {
                    // Insert Baru (Status otomatis Assigned)
                    $stmt_insert->execute([$spk_id, $proc_name, $op_id, $machine_id]);
                    
                    // NOTIFIKASI TUGAS BARU
                    if (function_exists('notify_workflow_event')) {
                        notify_workflow_event(
                            'prod.task.assign.' . $spk_id . '.' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $proc_name)),
                            'Tugas Produksi Baru',
                            "Anda mendapatkan tugas: " . $proc_name . " untuk SPK " . $spk['spk_number'],
                            'index.php?page=prod-operator',
                            'info',
                            ['user_ids' => [(int)$op_id], 'include_admin' => false, 'exclude_sender' => false]
                        );
                    }
                }
            }
        }
        
        // Update Status SPK jadi In Production jika belum (dan belum completed)
        if ($spk['status'] == 'released') {
             $pdo->prepare("UPDATE spk SET status='in_production' WHERE id=?")->execute([$spk_id]);
        }

        $pdo->commit();
        echo "<script>alert('Tugas berhasil didistribusikan dan notifikasi dikirim!'); window.location='index.php?page=prod-task';</script>";
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error: " . $e->getMessage();
    }
}

// 4. Load Tugas yang Sudah Ada untuk Tampilan
$existing_tasks = [];
$stmt_tasks = $pdo->prepare("SELECT * FROM production_assignments WHERE spk_id = ?");
$stmt_tasks->execute([$spk_id]);
while($row = $stmt_tasks->fetch()) {
    // Simpan Operator ID dan Status untuk ditampilkan di tabel
    $existing_tasks[$row['process_name']] = [
        'operator_id' => $row['operator_id'],
        'machine_id' => $row['machine_id'] ?? null,
        'status' => $row['status']
    ];
}

// Daftar Proses dari SPK
$proc_list = !empty($spk['required_processes']) ? explode(',', $spk['required_processes']) : [];

// Tambahkan proses NCR (Repair/Scrap) jika ada task NCR di assignment
$extra_proc = [];
$stmt_extra = $pdo->prepare("SELECT process_name FROM production_assignments WHERE spk_id = ? AND process_name LIKE 'NCR %' ORDER BY id ASC");
$stmt_extra->execute([$spk_id]);
while ($row = $stmt_extra->fetch()) {
    $extra_proc[] = $row['process_name'];
}
foreach ($extra_proc as $p) {
    if (!in_array($p, $proc_list, true)) {
        $proc_list[] = $p;
    }
}

/**
 * Cocokkan nama proses route sheet vs proses pada partlist dengan toleransi format.
 * Contoh: "Fibre Laser" <-> "Laser-Bend", "Metal Bending" <-> "Laser-Bend-Weld".
 */
function normalize_process_keywords($value) {
    $value = strtolower(trim((string)$value));
    if ($value === '') return [];

    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    $tokens = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY);
    $keywords = [];

    foreach ($tokens as $t) {
        if (in_array($t, ['fibre', 'fiber', 'laser', 'cut', 'cutting', 'l'])) {
            $keywords['laser'] = true;
            continue;
        }
        if (in_array($t, ['bending', 'bended', 'bend', 'forming', 'fold', 'press', 'pressbrake', 'b'])) {
            $keywords['bend'] = true;
            continue;
        }
        if (in_array($t, ['welding', 'weld', 'spot', 'mig', 'tig', 'w'])) {
            $keywords['weld'] = true;
            continue;
        }
        if (in_array($t, ['punch', 'punching'])) {
            $keywords['punch'] = true;
            continue;
        }
        if (in_array($t, ['shear', 'shearing'])) {
            $keywords['shear'] = true;
            continue;
        }
        if (in_array($t, ['drill', 'drilling'])) {
            $keywords['drill'] = true;
            continue;
        }
        if (in_array($t, ['tap', 'tapping'])) {
            $keywords['tap'] = true;
            continue;
        }
        if (in_array($t, ['cnc', 'machining', 'milling', 'mill', 'mac'])) {
            $keywords['machining'] = true;
            continue;
        }
        if (in_array($t, ['assembly', 'assembling', 'assy'])) {
            $keywords['assembly'] = true;
            continue;
        }
        if (in_array($t, ['paint', 'painting', 'powder', 'coating', 'cot', 'plating', 'plt', 'anodize', 'anodizing'])) {
            $keywords['finish'] = true;
            continue;
        }
        if (in_array($t, ['grind', 'grinding', 'polish', 'polishing', 'deburr', 'debur', 'finishing'])) {
            $keywords['finish'] = true;
            continue;
        }
        if (in_array($t, ['metal', 'process', 'sheet', 'plate', 'part'])) {
            continue;
        }
        $keywords[$t] = true;
    }

    return array_keys($keywords);
}

function process_name_matches($part_process, $route_process) {
    $part_process = trim((string)$part_process);
    $route_process = trim((string)$route_process);
    if ($part_process === '' || $route_process === '') return false;

    // Fast path: existing flexible substring matching.
    if (
        strcasecmp($part_process, $route_process) === 0 ||
        stripos($part_process, $route_process) !== false ||
        (strlen($part_process) >= 3 && stripos($route_process, $part_process) !== false)
    ) {
        return true;
    }

    // Token-based fallback for mixed naming style (e.g. "Fibre Laser" vs "Laser-Bend").
    $part_kw = normalize_process_keywords($part_process);
    $route_kw = normalize_process_keywords($route_process);
    if (empty($part_kw) || empty($route_kw)) return false;

    return count(array_intersect($part_kw, $route_kw)) > 0;
}

// Ringkasan Partlist per Proses untuk referensi Supervisor
$partlist_summary = [];
if (!empty($proc_list)) {
    // Ambil progres real operator per partlist per proses (akumulasi qty + status terakhir)
    $progress_by_part = [];
    try {
        $stmt_prog = $pdo->prepare("SELECT pa.process_name, pp.partlist_id, pp.qty_done, pp.progress_state 
                                    FROM production_partlist_progress pp
                                    JOIN production_assignments pa ON pp.assignment_id = pa.id
                                    WHERE pp.spk_id = ?
                                    ORDER BY pp.id ASC");
        $stmt_prog->execute([$spk_id]);
        while ($pr = $stmt_prog->fetch(PDO::FETCH_ASSOC)) {
            $proc_key = strtolower(trim((string)$pr['process_name']));
            $pid = (int)$pr['partlist_id'];
            if ($proc_key === '') continue;
            if (!isset($progress_by_part[$proc_key])) $progress_by_part[$proc_key] = [];
            if (!isset($progress_by_part[$proc_key][$pid])) {
                $progress_by_part[$proc_key][$pid] = ['qty_done' => 0, 'last_state' => 'progress'];
            }
            $progress_by_part[$proc_key][$pid]['qty_done'] += (float)$pr['qty_done'];
            $progress_by_part[$proc_key][$pid]['last_state'] = !empty($pr['progress_state']) ? $pr['progress_state'] : 'progress';
        }
    } catch (Exception $e) {
        // Jika tabel progress belum ada, tetap tampilkan partlist tanpa progress.
        $progress_by_part = [];
    }

    // Ambil SEMUA partlist untuk SPK ini (Matching di PHP agar lebih fleksibel)
    $stmt_all_pl = $pdo->prepare("SELECT * FROM spk_partlists WHERE spk_id = ?");
    $stmt_all_pl->execute([$spk_id]);
    $all_parts = $stmt_all_pl->fetchAll(PDO::FETCH_ASSOC);
    $partlist_meta = [
        'total_parts' => 0,
        'total_qty' => 0,
        'unmapped_parts' => 0,
        'unmapped_qty' => 0
    ];
    foreach ($all_parts as $part) {
        $partlist_meta['total_parts'] += 1;
        $qty = (float)($part['qty'] ?? 0);
        $partlist_meta['total_qty'] += $qty;
        $p_proc = trim((string)($part['process'] ?? ''));
        if ($p_proc === '') {
            $partlist_meta['unmapped_parts'] += 1;
            $partlist_meta['unmapped_qty'] += $qty;
        }
    }

    foreach ($proc_list as $proc_raw) {
        $proc_name = trim($proc_raw);
        if ($proc_name === '') continue;
        $proc_key = strtolower($proc_name);
        
        $count = 0;
        $qty = 0;
        $done_parts = 0;
        $done_qty_total = 0;
        $matched_items = [];
        
        foreach ($all_parts as $part) {
            $p_proc = trim((string)$part['process']);
            if (process_name_matches($p_proc, $proc_name)) {
                $count++;
                $target_qty = (float)$part['qty'];
                $qty += $target_qty;

                $pid = (int)$part['id'];
                $done_qty = isset($progress_by_part[$proc_key][$pid]) ? (float)$progress_by_part[$proc_key][$pid]['qty_done'] : 0;
                $last_state = isset($progress_by_part[$proc_key][$pid]) ? $progress_by_part[$proc_key][$pid]['last_state'] : 'progress';
                $is_done = ($last_state === 'done') || ($done_qty >= $target_qty && $target_qty > 0);
                if ($is_done) $done_parts++;
                $done_qty_total += min($done_qty, $target_qty);

                $part['_progress_qty'] = $done_qty;
                $part['_progress_state'] = $last_state;
                $part['_is_done'] = $is_done;
                $matched_items[] = $part;
            }
        }

        $progress_pct = ($qty > 0) ? min(100, round(($done_qty_total / $qty) * 100)) : 0;
        
        $partlist_summary[$proc_name] = [
            'parts' => $count,
            'qty' => $qty,
            'done_parts' => $done_parts,
            'done_qty' => $done_qty_total,
            'remaining_parts' => max(0, $count - $done_parts),
            'progress_pct' => $progress_pct,
            'items' => $matched_items
        ];
    }
}

render_header("Atur Tugas Operator");
?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-primary text-white">Info SPK</div>
            <div class="card-body">
                <strong>No. SPK:</strong><br><?= $spk['spk_number'] ?><br><br>
                <strong>Ref. SO:</strong><br><?= $spk['so_number'] ?: '-' ?><br><br>
                <strong>Customer:</strong><br><?= $spk['customer_name'] ?: '-' ?><br><br>
                <strong>Deadline:</strong><br><?= date('d M Y', strtotime($spk['deadline_date'])) ?>
                <?php if (!empty($spk['drawing_link'])): ?>
                    <br><br><strong>Drawing Link:</strong><br>
                    <a href="<?= htmlspecialchars($spk['drawing_link']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                        <i class="bi bi-link-45deg"></i> Buka Drawing
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Distribusi Tugas</h5>
                <small class="text-muted">Hanya menampilkan Operator Produksi</small>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if(empty($proc_list)): ?>
                        <div class="alert alert-warning">Tidak ada proses (Route Sheet) yang didefinisikan di SPK ini.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nama Proses</th>
                                        <th>Pilih Operator / PIC</th>
                                        <th>Mesin</th>
                                        <th>Partlist</th>
                                        <th>Status Saat Ini</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($proc_list as $proc): 
                                        $proc = trim($proc);
                                        // Ambil data existing
                                        $task_data = isset($existing_tasks[$proc]) ? $existing_tasks[$proc] : null;
                                        $current_op = $task_data ? $task_data['operator_id'] : '';
                                        $current_machine = $task_data ? (int)($task_data['machine_id'] ?? 0) : 0;
                                        $current_st = $task_data ? $task_data['status'] : '';
                                        $machine_bucket = prod_task_machine_bucket($proc);
                                        $machine_candidates = $machine_options_by_bucket[$machine_bucket] ?? [];
                                        
                                        // Badge Status
                                        $status_badge = '<span class="badge bg-secondary">Belum Dibagi</span>';
                                        if ($current_st) {
                                            $bg = match($current_st) {
                                                'assigned' => 'bg-info text-dark',
                                                'in_progress' => 'bg-primary',
                                                'completed' => 'bg-success',
                                                'hold' => 'bg-warning text-dark',
                                                default => 'bg-secondary'
                                            };
                                            $status_badge = '<span class="badge '.$bg.'">'.strtoupper($current_st).'</span>';
                                        }
                                        
                                        // Jika sudah completed, disable dropdown agar tidak sengaja diganti
                                        $is_locked = ($current_st == 'completed') ? 'disabled' : '';
                                    ?>
                                    <tr>
                                        <td class="fw-bold">
                                            <?= $proc ?>
                                            <input type="hidden" name="process_name[]" value="<?= $proc ?>">
                                        </td>
                                        <td>
                                            <select name="operator_id[]" class="form-select" <?= $is_locked ?>>
                                                <option value="">-- Pilih Operator --</option>
                                                <?php foreach($operators as $op): 
                                                    $selected = ($op['id'] == $current_op) ? 'selected' : '';
                                                ?>
                                                    <option value="<?= $op['id'] ?>" <?= $selected ?>>
                                                        <?= $op['fullname'] ?> (<?= $op['role_name'] ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if($is_locked): ?>
                                                <!-- Kirim nilai hidden karena select disabled tidak terkirim -->
                                                <input type="hidden" name="operator_id[]" value="<?= $current_op ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <select name="machine_id[]" class="form-select" <?= $is_locked ?>>
                                                <option value="">-- Pilih Mesin --</option>
                                                <?php foreach($machine_candidates as $mc): 
                                                    $mid = (int)($mc['id'] ?? 0);
                                                    $selectedMachine = ($mid === (int)$current_machine) ? 'selected' : '';
                                                    $mcStatus = strtolower((string)($mc['status'] ?? 'active'));
                                                    $statusSuffix = $mcStatus === 'maintenance' ? ' [MAINT]' : ($mcStatus === 'broken' ? ' [DOWN]' : '');
                                                ?>
                                                    <option value="<?= $mid ?>" <?= $selectedMachine ?>>
                                                        <?= clean(($mc['machine_code'] ?? '') !== '' ? ($mc['machine_code'] . ' - ' . $mc['machine_name']) : ($mc['machine_name'] ?? 'Mesin')) ?><?= $statusSuffix ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted">Proses: <?= clean($machine_bucket) ?></small>
                                            <?php if($is_locked): ?>
                                                <input type="hidden" name="machine_id[]" value="<?= (int)$current_machine ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-center">
                                            <?php
                                            $pl = $partlist_summary[$proc] ?? ['parts' => 0, 'qty' => 0, 'done_parts' => 0, 'done_qty' => 0, 'progress_pct' => 0, 'items' => []];
                                            if ($pl['parts'] > 0) {
                                                echo '<div class="fw-bold">'.$pl['parts'].' Items</div>';
                                                echo '<div class="text-muted mb-1">Total Qty: '.($pl['qty'] + 0).'</div>';
                                                echo '<div class="mb-1"><span class="badge '.($pl['done_parts'] >= $pl['parts'] ? 'bg-success' : 'bg-warning text-dark').'">'.$pl['done_parts'].'/'.$pl['parts'].' Selesai</span></div>';
                                                echo '<div class="progress mb-1" style="height:6px;"><div class="progress-bar '.($pl['progress_pct'] == 100 ? 'bg-success' : 'bg-primary').'" style="width: '.$pl['progress_pct'].'%"></div></div>';
                                                echo '<small class="text-muted">'.$pl['progress_pct'].'%</small><br>';
                                                echo '<button type="button" class="btn btn-sm btn-outline-primary py-0" style="font-size: 0.7rem;" data-bs-toggle="modal" data-bs-target="#modalPL_'.md5($proc).'">';
                                                echo '<i class="bi bi-eye"></i> Lihat Detail';
                                                echo '</button>';
                                            } elseif (!empty($partlist_meta['total_parts'])) {
                                                if (!empty($partlist_meta['unmapped_parts'])) {
                                                    echo '<div class="fw-bold text-warning">Partlist ada, proses belum diisi</div>';
                                                    echo '<div class="text-muted">Unmapped: '.$partlist_meta['unmapped_parts'].' items (Qty '.$partlist_meta['unmapped_qty'].')</div>';
                                                } else {
                                                    echo '<div class="fw-bold text-warning">Partlist ada, proses tidak cocok</div>';
                                                    echo '<div class="text-muted">Cek penamaan proses di Partlist</div>';
                                                }
                                            } else {
                                                echo '<span class="text-muted">Belum ada partlist</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center"><?= $status_badge ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-end mt-3">
                            <a href="index.php?page=prod-task" class="btn btn-secondary">Kembali</a>
                            <button type="submit" class="btn btn-primary px-4 fw-bold">
                                <i class="bi bi-save"></i> Simpan Penugasan
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODALS PARTLIST DETAIL -->
<?php if(!empty($proc_list)): foreach($proc_list as $proc): 
    $proc = trim($proc);
    $pl = $partlist_summary[$proc] ?? ['parts' => 0, 'items' => []];
    if($pl['parts'] > 0):
?>
<div class="modal fade" id="modalPL_<?= md5($proc) ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold">Partlist: <?= $proc ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-sm table-striped mb-0" style="font-size: 0.85rem;">
                    <thead class="table-light">
                        <tr><th>No</th><th>Part Name</th><th>Dwg No</th><th>Material</th><th class="text-center">Qty</th><th class="text-center">Done</th><th class="text-center">Status</th><th>Ket</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($pl['items'] as $item): ?>
                        <tr>
                            <td><?= $item['item_no'] ?></td>
                            <td><?= $item['part_name'] ?></td>
                            <td><?= $item['drawing_no'] ?></td>
                            <td><?= $item['material'] ?> <?= $item['thickness'] ?>mm</td>
                            <td class="text-center fw-bold"><?= $item['qty']+0 ?></td>
                            <td class="text-center"><?= (float)($item['_progress_qty'] ?? 0) + 0 ?></td>
                            <td class="text-center">
                                <?php if (!empty($item['_is_done'])): ?>
                                    <span class="badge bg-success">SELESAI</span>
                                <?php elseif (($item['_progress_state'] ?? 'progress') === 'pending'): ?>
                                    <span class="badge bg-danger">BELUM</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">PROGRESS</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $item['notes'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; endforeach; endif; ?>

<?php render_footer(); ?>
