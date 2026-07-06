<?php
// modules/production/operator/index.php

// 1. CEK AKSES
if (!has_permission('prod_operator_access') && !has_permission('prod_view')) {
    render_header("Akses Ditolak");
    echo "<div class='alert alert-danger text-center mt-5'>Anda tidak memiliki akses sebagai Operator.</div>";
    render_footer();
    exit;
}

$force_view_only = (isset($_GET['mode']) && $_GET['mode'] === 'view');
$is_view_only = has_permission('prod_view') && (!has_permission('prod_operator_access') || $force_view_only);
$view_operator_id = (int)($_GET['operator_id'] ?? 0);

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

    if (
        strcasecmp($part_process, $route_process) === 0 ||
        stripos($part_process, $route_process) !== false ||
        (strlen($part_process) >= 3 && stripos($route_process, $part_process) !== false)
    ) {
        return true;
    }

    $part_kw = normalize_process_keywords($part_process);
    $route_kw = normalize_process_keywords($route_process);
    if (empty($part_kw) || empty($route_kw)) return false;

    return count(array_intersect($part_kw, $route_kw)) > 0;
}

function operator_machine_bucket($process_name) {
    $p = strtolower(trim((string)$process_name));
    if ($p === '') return 'Other';
    if (strpos($p, 'fibre laser') !== false || strpos($p, 'fiber laser') !== false) return 'Fibre Laser';
    if (strpos($p, 'co laser') !== false) return 'CO Laser';
    if (strpos($p, 'acrylic') !== false && strpos($p, 'bending') !== false) return 'Acrylic Bending';
    if (strpos($p, 'metal bending') !== false || (strpos($p, 'bending') !== false && strpos($p, 'acrylic') === false)) return 'Metal Bending';
    if (strpos($p, 'welding') !== false || preg_match('/\blas\b/', $p)) return 'Welding';
    return 'Other';
}

function get_ncr_target_qty(PDO $pdo, $process_name) {
    $process_name = (string)$process_name;
    if (!preg_match('/NCR-\d{4}-\d{4}/', $process_name, $m)) {
        return 0;
    }
    $stmt = $pdo->prepare("SELECT qty_reject FROM ncr WHERE ncr_number = ? LIMIT 1");
    $stmt->execute([$m[0]]);
    $qty = $stmt->fetchColumn();
    return $qty !== false ? (float)$qty : 0;
}

// Pastikan tabel log progres partlist tersedia.
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS production_partlist_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assignment_id INT NOT NULL,
        spk_id INT NOT NULL,
        partlist_id INT NOT NULL,
        qty_done DECIMAL(10,2) NOT NULL DEFAULT 0,
        progress_state ENUM('progress','done','pending') NOT NULL DEFAULT 'progress',
        notes TEXT NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_assign (assignment_id),
        INDEX idx_spk (spk_id),
        INDEX idx_part (partlist_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Jangan hentikan operasi panel jika auto-create tabel gagal.
}

try {
    $pdo->exec("ALTER TABLE production_partlist_progress 
                ADD COLUMN progress_state ENUM('progress','done','pending') NOT NULL DEFAULT 'progress' 
                AFTER qty_done");
} catch (Exception $e) {
    // Abaikan jika kolom sudah ada.
}

// Hardening struktur lama: beberapa instalasi lokal punya kolom PK tanpa AUTO_INCREMENT.
try {
    $pdo->exec("ALTER TABLE production_logs MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
} catch (Exception $e) {
    // Abaikan jika sudah benar atau tidak punya hak ALTER.
}
try {
    $pdo->exec("ALTER TABLE production_partlist_progress MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
} catch (Exception $e) {
    // Abaikan jika sudah benar atau tidak punya hak ALTER.
}
try {
    $pdo->exec("ALTER TABLE production_assignments ADD COLUMN machine_id INT NULL AFTER operator_id");
} catch (Exception $e) {
    // Abaikan jika kolom sudah ada.
}

// ============================================================================
// LOGIC ACTION (PROSES DATA)
// ============================================================================
if (isset($_GET['action'])) {
    if ($is_view_only) {
        render_header("Akses Ditolak");
        echo "<div class='alert alert-danger text-center mt-5'>Mode lihat saja. Tidak dapat mengeksekusi tindakan operator.</div>";
        render_footer();
        exit;
    }
    $action = $_GET['action'];
    $uid = $_SESSION['user_id'];
    
    // A. TOMBOL START (DENGAN VALIDASI ITR)
    if ($action == 'start' && isset($_GET['id'])) {
        $id = $_GET['id'];

        // --- VALIDASI MATERIAL ISSUE (FIX POIN 5) ---
        // 1. Ambil SPK ID dari tugas ini
        $stmt_task = $pdo->prepare("SELECT spk_id, process_name, machine_id FROM production_assignments WHERE id = ?");
        $stmt_task->execute([$id]);
        $task_start = $stmt_task->fetch(PDO::FETCH_ASSOC);
        $spk_id = $task_start['spk_id'] ?? null;
        $start_proc_name = (string)($task_start['process_name'] ?? '');
        $start_machine_id = (int)($task_start['machine_id'] ?? 0);

        // Validasi machine assignment untuk proses yang menggunakan mesin.
        if (operator_machine_bucket($start_proc_name) !== 'Other' && $start_machine_id <= 0) {
            echo "<script>alert('GAGAL MULAI: Mesin belum dipilih pada penugasan untuk proses " . addslashes($start_proc_name) . ". Silakan hubungi Supervisor/Manager Produksi untuk assign mesin terlebih dahulu.'); window.location='index.php?page=prod-operator';</script>";
            exit;
        }

        if ($spk_id) {
            // 2. Cek apakah ada ITR yang 'approved' untuk SPK ini
            $stmt_itr = $pdo->prepare("SELECT COUNT(*) FROM material_issues WHERE spk_id = ? AND status = 'approved'");
            $stmt_itr->execute([$spk_id]);
            $itr_exists = $stmt_itr->fetchColumn();

            // 3. Jika belum ada ITR Approved, blokir
            if ($itr_exists == 0) {
                echo "<script>alert('GAGAL MULAI: Material belum dikeluarkan/disetujui oleh Gudang (ITR belum Approved). Silakan hubungi Supervisor atau Gudang.'); window.location='index.php?page=prod-operator';</script>";
                exit;
            }
        }
        // ---------------------------------------------

        // Jika valid, update status jadi in_progress
        $pdo->prepare("UPDATE production_assignments SET status='in_progress', start_time=NOW() WHERE id=? AND operator_id=?")
            ->execute([$id, $uid]);
        
        // Catat Log
        $pdo->prepare("INSERT INTO production_logs (assignment_id, activity, operator_id) VALUES (?, 'start', ?)")
            ->execute([$id, $uid]);
            
        echo "<script>window.location='index.php?page=prod-operator';</script>";
        exit;
    }

    // B. TOMBOL HOLD
    if ($action == 'hold' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $id = $_POST['task_id'];
        $reason = $_POST['reason'] . " - " . $_POST['notes'];
        $pdo->prepare("UPDATE production_assignments SET status='hold' WHERE id=? AND operator_id=?")
            ->execute([$id, $uid]);
        $pdo->prepare("INSERT INTO production_logs (assignment_id, activity, operator_id, notes) VALUES (?, 'hold', ?, ?)")
            ->execute([$id, $uid, $reason]);
        echo "<script>window.location='index.php?page=prod-operator';</script>";
        exit;
    }

    // C. TOMBOL RESUME
    if ($action == 'resume' && isset($_GET['id'])) {
        $id = $_GET['id'];
        $pdo->prepare("UPDATE production_assignments SET status='in_progress' WHERE id=? AND operator_id=?")
            ->execute([$id, $uid]);
        $pdo->prepare("INSERT INTO production_logs (assignment_id, activity, operator_id) VALUES (?, 'resume', ?)")
            ->execute([$id, $uid]);
        echo "<script>window.location='index.php?page=prod-operator';</script>";
        exit;
    }

    // D. TOMBOL FINISH (UPDATED: Auto Complete SPK & Notif QC)
    if ($action == 'finish' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $id = (int)($_POST['task_id'] ?? 0);
        $qtyG = (float)($_POST['qty_good'] ?? 0);
        $qtyR = (float)($_POST['qty_reject'] ?? 0);
        $note = trim((string)($_POST['notes'] ?? ''));
        $reject_enabled = isset($_POST['reject_enabled']) && $_POST['reject_enabled'] === '1';
        if (!$reject_enabled) {
            $qtyR = 0;
        } elseif ($qtyR <= 0) {
            echo "<script>alert('Isi Qty Rusak (NG) jika memilih Ada Reject.'); window.location='index.php?page=prod-operator';</script>";
            exit;
        }

        $stmt_task = $pdo->prepare("SELECT id, spk_id, process_name FROM production_assignments WHERE id = ? AND operator_id = ?");
        $stmt_task->execute([$id, $uid]);
        $task_row = $stmt_task->fetch();
        if (!$task_row) {
            echo "<script>alert('Tugas tidak ditemukan.'); window.location='index.php?page=prod-operator';</script>";
            exit;
        }

        $stmt_pl = $pdo->prepare("SELECT id, qty, process FROM spk_partlists WHERE spk_id = ? ORDER BY id ASC");
        $stmt_pl->execute([$task_row['spk_id']]);
        $all_partlists = $stmt_pl->fetchAll();

        $task_parts = [];
        $task_proc = trim((string)$task_row['process_name']);
        foreach ($all_partlists as $part) {
            if (process_name_matches($part['process'], $task_proc)) {
                $task_parts[] = $part;
            }
        }

        if (empty($task_parts) && !empty($all_partlists)) {
            // Jika tidak ada partlist yang cocok dengan proses task,
            // gunakan semua partlist agar task tetap bisa diselesaikan.
            $task_parts = $all_partlists;
        }

        if (empty($task_parts)) {
            echo "<script>alert('Tidak bisa selesai: partlist untuk proses ini belum tersedia.'); window.location='index.php?page=prod-operator';</script>";
            exit;
        }

        $part_ids = [];
        foreach ($task_parts as $p) {
            $part_ids[] = (int)$p['id'];
        }
        $in_list = implode(',', $part_ids);
        $progress_map = [];
        if (!empty($in_list)) {
            $stmt_prog = $pdo->prepare("SELECT partlist_id, qty_done, progress_state FROM production_partlist_progress WHERE assignment_id = ? AND partlist_id IN ($in_list) ORDER BY id ASC");
            $stmt_prog->execute([$id]);
            while ($pr = $stmt_prog->fetch()) {
                $pid = (int)$pr['partlist_id'];
                if (!isset($progress_map[$pid])) {
                    $progress_map[$pid] = ['qty_done' => 0, 'last_state' => 'progress'];
                }
                $progress_map[$pid]['qty_done'] += (float)$pr['qty_done'];
                $progress_map[$pid]['last_state'] = $pr['progress_state'] ?: 'progress';
            }
        }

        $ncr_target_qty = get_ncr_target_qty($pdo, $task_row['process_name']);
        $unfinished_count = 0;
        foreach ($task_parts as $part) {
            $pid = (int)$part['id'];
            $base_target = (float)$part['qty'];
            $target = $ncr_target_qty > 0 ? min($base_target, $ncr_target_qty) : $base_target;
            $done_qty = isset($progress_map[$pid]) ? (float)$progress_map[$pid]['qty_done'] : 0;
            $last_state = isset($progress_map[$pid]) ? $progress_map[$pid]['last_state'] : 'progress';
            $is_done = ($last_state === 'done') || ($done_qty >= $target && $target > 0);
            if (!$is_done) $unfinished_count++;
        }

        if ($unfinished_count > 0) {
            echo "<script>alert('Tidak bisa selesai: masih ada partlist yang belum selesai. Silakan update checklist partlist terlebih dahulu.'); window.location='index.php?page=prod-operator';</script>";
            exit;
        }
        
        // 1. Update Task jadi Completed
        $pdo->prepare("UPDATE production_assignments SET status='completed', end_time=NOW(), qty_good=?, qty_reject=?, notes=? WHERE id=? AND operator_id=?")
            ->execute([$qtyG, $qtyR, $note, $id, $uid]);
        
        // 2. Catat Log
        $pdo->prepare("INSERT INTO production_logs (assignment_id, activity, operator_id) VALUES (?, 'finish', ?)")
            ->execute([$id, $uid]);

        // 3. CEK APAKAH INI TUGAS TERAKHIR?
        // Ambil SPK ID dan Nomor SPK dari tugas ini
        $stmt_get_spk = $pdo->prepare("SELECT pa.spk_id, s.spk_number 
                                       FROM production_assignments pa 
                                       JOIN spk s ON pa.spk_id = s.id 
                                       WHERE pa.id = ?");
        $stmt_get_spk->execute([$id]);
        $task_data = $stmt_get_spk->fetch();
        
        if ($task_data) {
            $spk_id = $task_data['spk_id'];
            $spk_num = $task_data['spk_number'];

            // Hitung sisa tugas yang BELUM completed untuk SPK ini
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM production_assignments WHERE spk_id = ? AND status != 'completed'");
            $stmt_check->execute([$spk_id]);
            $remaining = $stmt_check->fetchColumn();

            // Jika sisa 0, berarti semua tugas selesai -> Update Status SPK jadi 'completed' & Kirim Notif
            if ($remaining == 0) {
                $pdo->prepare("UPDATE spk SET status='completed' WHERE id = ?")->execute([$spk_id]);

                // --- TRIGGER NOTIFIKASI KE QC ---
                if (function_exists('notify_workflow_event')) {
                    notify_workflow_event(
                        'prod.operator.finish_spk.' . (int)$spk_id,
                        'Produksi Selesai',
                        'SPK ' . $spk_num . ' telah selesai diproduksi sepenuhnya. Barang siap untuk inspeksi QC.',
                        'index.php?page=qc-production&action=inspect&spk_id=' . (int)$spk_id,
                        'success',
                        ['permission_slug' => 'qc_production_manage']
                    );
                }
            }
        }

        echo "<script>alert('Pekerjaan Selesai! Terima kasih.'); window.location='index.php?page=prod-operator';</script>";
        exit;
    }

    // E. LOG CHECKLIST PROGRES PART (PER JAM / HARI)
    if ($action == 'part_progress' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $task_id = (int)($_POST['task_id'] ?? 0);
        $part_id = (int)($_POST['partlist_id'] ?? 0);
        $qty_done = (float)($_POST['qty_done'] ?? 0);
        $note = trim($_POST['notes'] ?? '');

        if ($task_id > 0 && $part_id > 0 && $qty_done > 0) {
            // Pastikan assignment milik operator yang login.
            $stmt_task = $pdo->prepare("SELECT spk_id, process_name FROM production_assignments WHERE id = ? AND operator_id = ?");
            $stmt_task->execute([$task_id, $uid]);
            $task_row = $stmt_task->fetch();

            if ($task_row) {
                $spk_id = (int)$task_row['spk_id'];
                $task_proc = trim((string)$task_row['process_name']);
                $ncr_target_qty = get_ncr_target_qty($pdo, $task_row['process_name']);
                $allow_mismatch = false;

                // Jika tidak ada satupun process partlist yang match dengan task,
                // izinkan update (untuk kasus warning mismatch di UI).
                $stmt_pl_all = $pdo->prepare("SELECT process FROM spk_partlists WHERE spk_id = ? ORDER BY id ASC");
                $stmt_pl_all->execute([$spk_id]);
                $all_pl = $stmt_pl_all->fetchAll();
                if (!empty($all_pl)) {
                    $has_match = false;
                    foreach ($all_pl as $pl) {
                        if (process_name_matches($pl['process'], $task_proc)) {
                            $has_match = true;
                            break;
                        }
                    }
                    if (!$has_match) {
                        $allow_mismatch = true;
                    }
                }

                $stmt_part = $pdo->prepare("SELECT id, process, qty FROM spk_partlists WHERE id = ? AND spk_id = ?");
                $stmt_part->execute([$part_id, $spk_id]);
                $part = $stmt_part->fetch();

                if ($part && (process_name_matches($part['process'], $task_proc) || $allow_mismatch)) {
                    // Cek agar qty tidak melebihi target.
                    $base_target_qty = (float)($part['qty'] ?? 0);
                    $target_qty = $ncr_target_qty > 0 ? min($base_target_qty, $ncr_target_qty) : $base_target_qty;
                    $already = 0;
                    if ($target_qty > 0 && $qty_done > 0) {
                        $stmt_sum = $pdo->prepare("SELECT COALESCE(SUM(qty_done),0) FROM production_partlist_progress WHERE assignment_id = ? AND partlist_id = ?");
                        $stmt_sum->execute([$task_id, $part_id]);
                        $already = (float)$stmt_sum->fetchColumn();
                        if ($already + $qty_done > $target_qty) {
                            echo "<script>alert('Qty melebihi target part (Target: {$target_qty}). Mohon koreksi.'); window.location='index.php?page=prod-operator';</script>";
                            exit;
                        }
                    }
                    // Status otomatis berdasarkan target vs total qty.
                    if ($target_qty > 0) {
                        $progress_state = (($already + $qty_done) >= $target_qty) ? 'done' : 'pending';
                    } else {
                        $progress_state = 'progress';
                    }
                    $stmt_ins = $pdo->prepare("INSERT INTO production_partlist_progress (assignment_id, spk_id, partlist_id, qty_done, progress_state, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt_ins->execute([$task_id, $spk_id, $part_id, $qty_done, $progress_state, $note, $uid]);
                }
            }
        }

        echo "<script>window.location='index.php?page=prod-operator';</script>";
        exit;
    }
}

// ============================================================================
// QUERY DATA TAMPILAN
// ============================================================================
$user_id = $_SESSION['user_id'];
$operator_scope_id = $is_view_only ? $view_operator_id : $user_id;
$selected_operator_name = '';

// Data dropdown operator untuk mode view-only
$operator_options = [];
if ($is_view_only) {
    $sql_op = "SELECT u.id, u.fullname
               FROM users u
               JOIN roles r ON u.role_id = r.id
               WHERE r.role_slug = 'operator' OR r.role_slug LIKE 'op_%'
               ORDER BY u.fullname ASC";
    $operator_options = $pdo->query($sql_op)->fetchAll();
}

// Query aktif: 1 assignment = 1 baris (hindari duplikasi saat SO punya banyak item)
$sql_active = "SELECT pa.*,
                      GREATEST(TIMESTAMPDIFF(SECOND, COALESCE(pa.start_time, pa.created_at), NOW()), 0) AS runtime_seconds,
                      m.machine_name AS assigned_machine_name,
                      m.machine_code AS assigned_machine_code,
                      s.spk_number, s.project_name, s.drawing_link, so.so_number, c.name as customer_name,
                      COALESCE(soi_sum.item_count, 0) as item_count,
                      COALESCE(soi_sum.item_names, '-') as item_name,
                      COALESCE(soi_sum.item_codes, '-') as item_code,
                      COALESCE(soi_sum.unit_label, 'unit') as unit,
                      COALESCE(soi_sum.target_qty, 0) as target_qty
               FROM production_assignments pa
               JOIN spk s ON pa.spk_id = s.id
               LEFT JOIN machines m ON m.id = pa.machine_id
               LEFT JOIN sales_orders so ON s.sales_order_id = so.id
               LEFT JOIN customers c ON so.customer_id = c.id
               LEFT JOIN (
                   SELECT soi.sales_order_id,
                          COUNT(*) as item_count,
                          GROUP_CONCAT(COALESCE(i.item_name, '-') ORDER BY soi.id SEPARATOR ', ') as item_names,
                          GROUP_CONCAT(COALESCE(i.item_code, '-') ORDER BY soi.id SEPARATOR ', ') as item_codes,
                          SUM(COALESCE(soi.qty, 0)) as target_qty,
                          CASE 
                              WHEN COUNT(DISTINCT COALESCE(i.unit, '')) = 1 THEN MAX(COALESCE(i.unit, 'unit'))
                              ELSE 'unit'
                          END as unit_label
                   FROM sales_order_items soi
                   LEFT JOIN items i ON soi.item_id = i.id
                   GROUP BY soi.sales_order_id
               ) soi_sum ON soi_sum.sales_order_id = so.id
               WHERE pa.operator_id = ? AND pa.status IN ('in_progress', 'hold')
               LIMIT 1";
$stmt_active = $pdo->prepare($sql_active);
$stmt_active->execute([$operator_scope_id]);
$active_task = $stmt_active->fetch();
if ($active_task) {
    $ncr_target_qty = get_ncr_target_qty($pdo, $active_task['process_name']);
    if ($ncr_target_qty > 0) {
        $active_task['target_qty'] = $ncr_target_qty;
    }
}

$sql_queue = "SELECT pa.*,
                     m.machine_name AS assigned_machine_name,
                     m.machine_code AS assigned_machine_code,
                     s.spk_number, s.project_name, s.drawing_link, so.so_number, c.name as customer_name,
                     COALESCE(soi_sum.item_count, 0) as item_count,
                     COALESCE(soi_sum.item_names, '-') as item_name,
                     COALESCE(soi_sum.unit_label, 'unit') as unit,
                     COALESCE(soi_sum.target_qty, 0) as target_qty
              FROM production_assignments pa
              JOIN spk s ON pa.spk_id = s.id
              LEFT JOIN machines m ON m.id = pa.machine_id
              LEFT JOIN sales_orders so ON s.sales_order_id = so.id
              LEFT JOIN customers c ON so.customer_id = c.id
              LEFT JOIN (
                   SELECT soi.sales_order_id,
                          COUNT(*) as item_count,
                          GROUP_CONCAT(COALESCE(i.item_name, '-') ORDER BY soi.id SEPARATOR ', ') as item_names,
                          SUM(COALESCE(soi.qty, 0)) as target_qty,
                          CASE 
                              WHEN COUNT(DISTINCT COALESCE(i.unit, '')) = 1 THEN MAX(COALESCE(i.unit, 'unit'))
                              ELSE 'unit'
                          END as unit_label
                   FROM sales_order_items soi
                   LEFT JOIN items i ON soi.item_id = i.id
                   GROUP BY soi.sales_order_id
              ) soi_sum ON soi_sum.sales_order_id = so.id
              WHERE pa.operator_id = ? AND pa.status = 'assigned'
              ORDER BY s.deadline_date ASC";
$stmt_queue = $pdo->prepare($sql_queue);
$stmt_queue->execute([$operator_scope_id]);
$queue_tasks = $stmt_queue->fetchAll();
if (!empty($queue_tasks)) {
    foreach ($queue_tasks as $i => $qt) {
        $ncr_target_qty = get_ncr_target_qty($pdo, $qt['process_name']);
        if ($ncr_target_qty > 0) {
            $queue_tasks[$i]['target_qty'] = $ncr_target_qty;
        }
    }
}

$active_partlists = [];
$active_partlist_warning = '';
$active_progress = [];
$active_part_state = [];
$active_progress_logs = [];
$active_parts_total = 0;
$active_parts_done = 0;
$active_parts_unfinished = 0;
if ($active_task) {
    $ncr_target_qty = get_ncr_target_qty($pdo, $active_task['process_name']);
    // FIX: Ambil semua partlist lalu filter fleksibel di PHP (agar 'Laser' match 'Fibre Laser')
    $stmt_pl = $pdo->prepare("SELECT id, item_no, drawing_no, part_name, qty, process 
                              FROM spk_partlists 
                              WHERE spk_id = ?
                              ORDER BY id ASC");
    $stmt_pl->execute([$active_task['spk_id']]);
    $all_partlists = $stmt_pl->fetchAll();
    
    $proc_target = trim($active_task['process_name']);
    foreach ($all_partlists as $part) {
        $p_proc = trim((string)$part['process']);
        if (process_name_matches($p_proc, $proc_target)) {
            $active_partlists[] = $part;
        }
    }
    if (empty($active_partlists) && !empty($all_partlists)) {
        $active_partlists = $all_partlists;
        $active_partlist_warning = 'Partlist ada, tapi proses tidak cocok dengan task. Menampilkan semua partlist untuk SPK ini.';
    }

    $stmt_prog = $pdo->prepare("SELECT pp.partlist_id, pp.qty_done, pp.progress_state
                                FROM production_partlist_progress pp
                                WHERE pp.assignment_id = ?
                                ORDER BY pp.id ASC");
    $stmt_prog->execute([$active_task['id']]);
    foreach ($stmt_prog->fetchAll() as $pr) {
        $pid = (int)$pr['partlist_id'];
        if (!isset($active_progress[$pid])) {
            $active_progress[$pid] = 0;
            $active_part_state[$pid] = 'progress';
        }
        $active_progress[$pid] += (float)$pr['qty_done'];
        $active_part_state[$pid] = $pr['progress_state'] ?: 'progress';
    }

    $active_parts_total = count($active_partlists);
    foreach ($active_partlists as $part) {
        $pid = (int)$part['id'];
        $base_target = (float)$part['qty'];
        $target = $ncr_target_qty > 0 ? min($base_target, $ncr_target_qty) : $base_target;
        $done_qty = $active_progress[$pid] ?? 0;
        $last_state = $active_part_state[$pid] ?? 'progress';
        $is_done = ($last_state === 'done') || ($done_qty >= $target && $target > 0);
        if ($is_done) {
            $active_parts_done++;
        }
    }
    $active_parts_unfinished = max(0, $active_parts_total - $active_parts_done);
    if ($active_parts_total === 0) {
        $active_parts_unfinished = 1; // blok finish jika partlist belum tersedia
    }

    $stmt_log = $pdo->prepare("SELECT pp.created_at, pp.qty_done, pp.progress_state, pp.notes, pl.part_name, pl.drawing_no
                               FROM production_partlist_progress pp
                               JOIN spk_partlists pl ON pl.id = pp.partlist_id
                               WHERE pp.assignment_id = ?
                               ORDER BY pp.created_at DESC
                               LIMIT 20");
    $stmt_log->execute([$active_task['id']]);
    $active_progress_logs = $stmt_log->fetchAll();
}

render_header("Operator Panel");
?>
<style>
.operator-panel-wrap {
    width: 100%;
    max-width: 1400px;
}

.operator-checklist-table {
    table-layout: fixed;
    width: 100%;
}

.operator-checklist-table th,
.operator-checklist-table td {
    font-size: 0.9rem;
    vertical-align: middle;
}

.operator-checklist-table th:nth-child(1),
.operator-checklist-table td:nth-child(1) {
    width: 48px;
}

.operator-checklist-table th:nth-child(2),
.operator-checklist-table td:nth-child(2) {
    width: 32%;
}

.operator-checklist-table td:nth-child(2) {
    word-break: break-word;
}

.operator-checklist-table input.form-control-sm {
    min-width: 72px;
}

.op-runtime-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: .35rem .65rem;
    border-radius: 999px;
    background: rgba(13, 110, 253, 0.08);
    border: 1px solid rgba(13, 110, 253, 0.2);
    color: #0d6efd;
    font-weight: 700;
}
.op-runtime-badge i { color: #0d6efd; }

.operator-checklist-table .btn.btn-sm {
    white-space: nowrap;
}

@media (max-width: 991.98px) {
    .operator-checklist-table th,
    .operator-checklist-table td {
        font-size: 0.82rem;
        padding: 0.4rem 0.35rem;
    }

    .operator-checklist-table th:nth-child(2),
    .operator-checklist-table td:nth-child(2) {
        width: 28%;
    }
}
</style>
<?php

if (has_permission('prod_view') && !has_permission('prod_operator_access')) {
    ?>
    <div class="container py-3">
        <div class="alert <?= $is_view_only ? 'alert-info' : 'alert-secondary' ?> d-flex justify-content-between align-items-center">
            <div>
                <?= $is_view_only ? 'Mode lihat saja aktif. Tidak bisa mengeksekusi tindakan operator.' : 'Mode lihat antrian operator.' ?>
            </div>
            <?php if ($is_view_only): ?>
                <a class="btn btn-outline-primary btn-sm" href="index.php?page=prod-operator">Kembali</a>
            <?php else: ?>
                <a class="btn btn-primary btn-sm" href="index.php?page=prod-operator&mode=view">Mode Lihat Operator</a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

if ($is_view_only) {
    foreach ($operator_options as $opt) {
        if ((int)$opt['id'] === (int)$operator_scope_id) {
            $selected_operator_name = $opt['fullname'];
            break;
        }
    }
    ?>
    <div class="container py-3">
        <div class="card shadow-sm mb-4">
            <div class="card-body d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                <div>
                    <h6 class="mb-1">Mode Lihat Saja</h6>
                    <div class="text-muted small">Pilih operator untuk melihat antrian dan progress. Tidak bisa eksekusi tindakan.</div>
                </div>
                <form method="GET" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="page" value="prod-operator">
                    <select name="operator_id" class="form-select" required>
                        <option value="">Pilih Operator</option>
                        <?php foreach ($operator_options as $opt): ?>
                            <option value="<?= (int)$opt['id'] ?>" <?= ((int)$opt['id'] === (int)$operator_scope_id) ? 'selected' : '' ?>>
                                <?= clean($opt['fullname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" type="submit">Tampilkan</button>
                </form>
            </div>
        </div>
        <?php if (!$operator_scope_id): ?>
            <div class="alert alert-warning text-center">
                Pilih operator terlebih dahulu untuk melihat antrian tugas.
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>

<?php if (!$is_view_only || $operator_scope_id): ?>
<div class="row justify-content-center">
    <div class="col-12 operator-panel-wrap">
        
        <!-- === SECTION 1: TUGAS AKTIF === -->
        <?php if ($active_task): ?>
            <?php if($active_task['status'] == 'in_progress'): ?>
                <!-- TAMPILAN SEDANG KERJA -->
                <div class="card shadow border-primary mb-4">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h5 class="mb-0 animate-pulse"><i class="bi bi-gear-wide-connected bi-spin"></i> SEDANG DIKERJAKAN</h5>
                    </div>
                    <div class="card-body text-center p-4">
                        <h2 class="text-primary fw-bold mb-1"><?= $active_task['process_name'] ?></h2>
                        <h5 class="text-muted mb-4"><?= $active_task['spk_number'] ?></h5>
                        
                        <div class="bg-light p-3 rounded mb-4 text-start border position-relative">
                            <span class="position-absolute top-0 end-0 badge bg-success m-2 fs-6">
                                Target: <?= $active_task['target_qty'] + 0 ?> <?= $active_task['unit'] ?>
                            </span>
                            <div class="mb-2">
                                <small class="text-muted text-uppercase fw-bold">Project</small><br>
                                <span class="fs-5"><?= $active_task['project_name'] ?: ($active_task['customer_name'] ?? '-') ?></span>
                            </div>
                            <div>
                                <small class="text-muted text-uppercase fw-bold">Item / Barang</small><br>
                                <?php if (($active_task['item_count'] ?? 0) > 1): ?>
                                    <span class="fs-5">Multi Item (<?= (int)$active_task['item_count'] ?> jenis)</span>
                                    <div class="small text-muted mt-1"><?= clean($active_task['item_name']) ?></div>
                                <?php else: ?>
                                    <span class="fs-5"><?= clean($active_task['item_name']) ?></span>
                                    <div class="small text-muted mt-1">Kode: <?= clean($active_task['item_code']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($active_task['drawing_link'])): ?>
                                <div class="mt-2">
                                    <a href="<?= htmlspecialchars($active_task['drawing_link']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-link-45deg"></i> Buka Link Gambar
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <button type="button" class="btn btn-warning w-100 py-3 fw-bold fs-5 shadow-sm" <?= $is_view_only ? 'disabled' : 'data-bs-toggle="modal" data-bs-target="#holdModal"' ?>>
                                    <i class="bi bi-pause-circle-fill"></i> HOLD
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="button" class="btn btn-success w-100 py-3 fw-bold fs-5 shadow-sm" <?= ($active_parts_unfinished > 0 || $is_view_only) ? 'disabled' : 'data-bs-toggle="modal" data-bs-target="#finishModal"' ?>>
                                    <i class="bi bi-check-circle-fill"></i> SELESAI
                                </button>
                            </div>
                        </div>
                        <?php if ($active_parts_unfinished > 0): ?>
                            <small class="text-danger d-block mt-2">Checklist partlist belum selesai semua. Lengkapi dulu sebelum menutup tugas.</small>
                        <?php endif; ?>
                        <?php if (!empty($active_task['assigned_machine_name']) || !empty($active_task['assigned_machine_code'])): ?>
                            <div class="mt-3">
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-cpu"></i>
                                    Mesin: <?= clean(trim(((string)($active_task['assigned_machine_code'] ?? '')) !== '' ? (($active_task['assigned_machine_code'] ?? '') . ' - ' . ($active_task['assigned_machine_name'] ?? '')) : ($active_task['assigned_machine_name'] ?? '-'))) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($active_task['start_time'])): ?>
                            <div class="mt-3">
                                <span class="op-runtime-badge js-op-runtime" data-elapsed-seconds="<?= (int)($active_task['runtime_seconds'] ?? 0) ?>">
                                    <i class="bi bi-stopwatch"></i>
                                    <span>Run 00:00:00</span>
                                </span>
                            </div>
                        <?php endif; ?>
                        <small class="text-muted mt-3 d-block">Waktu Mulai: <?= date('d/m H:i', strtotime($active_task['start_time'])) ?></small>
                    </div>
                </div>
            
            <?php elseif($active_task['status'] == 'hold'): ?>
                <!-- TAMPILAN SEDANG HOLD -->
                <div class="card shadow border-warning mb-4">
                    <div class="card-header bg-warning text-dark text-center py-3">
                        <h5 class="mb-0"><i class="bi bi-pause-circle"></i> PEKERJAAN DITUNDA (HOLD)</h5>
                    </div>
                    <div class="card-body text-center p-4">
                        <h3 class="text-dark fw-bold"><?= $active_task['process_name'] ?></h3>
                        <p class="text-muted"><?= $active_task['spk_number'] ?></p>
                        
                        <div class="alert alert-warning text-start">
                            <strong>Status:</strong> Sedang di-Hold<br>
                            Silakan klik <strong>LANJUT</strong> untuk meneruskan pekerjaan.
                        </div>
                        <?php if (!empty($active_task['assigned_machine_name']) || !empty($active_task['assigned_machine_code'])): ?>
                            <div class="mb-3">
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-cpu"></i>
                                    Mesin: <?= clean(trim(((string)($active_task['assigned_machine_code'] ?? '')) !== '' ? (($active_task['assigned_machine_code'] ?? '') . ' - ' . ($active_task['assigned_machine_name'] ?? '')) : ($active_task['assigned_machine_name'] ?? '-'))) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($active_task['start_time'])): ?>
                            <div class="mb-3">
                                <span class="op-runtime-badge js-op-runtime" data-elapsed-seconds="<?= (int)($active_task['runtime_seconds'] ?? 0) ?>">
                                    <i class="bi bi-stopwatch"></i>
                                    <span>Run 00:00:00</span>
                                </span>
                            </div>
                        <?php endif; ?>

                        <a href="index.php?page=prod-operator&action=resume&id=<?= $active_task['id'] ?>" class="btn btn-primary w-100 py-3 fw-bold fs-5 shadow-sm <?= $is_view_only ? 'disabled' : '' ?>" <?= $is_view_only ? 'aria-disabled="true" onclick="return false;"' : '' ?>>
                            <i class="bi bi-play-circle-fill"></i> LANJUT KERJA (RESUME)
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <?php if ($active_task): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light fw-bold">
                <i class="bi bi-list-check"></i> Checklist Partlist - <?= $active_task['process_name'] ?>
                <?php if (!empty($active_partlists)): ?>
                    <span class="badge bg-success ms-2"><?= $active_parts_done ?> Selesai</span>
                    <span class="badge bg-warning text-dark ms-1"><?= max(0, $active_parts_unfinished) ?> Belum</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($active_partlist_warning)): ?>
                    <div class="alert alert-warning mb-0 rounded-0 small">
                        <i class="bi bi-exclamation-triangle"></i> <?= $active_partlist_warning ?>
                    </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 operator-checklist-table">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Part</th>
                                <th class="text-center">Target</th>
                                <th class="text-center">Done</th>
                                <th class="text-center">Status</th>
                                <?php if ($active_task['status'] == 'in_progress' && !$is_view_only): ?>
                                    <th class="text-center">Qty Tambah</th>
                                    <th class="text-center">Status</th>
                                    <th>Catatan</th>
                                    <th class="text-center">Aksi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($active_partlists)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">Belum ada partlist untuk proses ini.</td></tr>
                            <?php else: foreach ($active_partlists as $idx => $part): 
                                $pid = (int)$part['id'];
                                $done = $active_progress[$pid] ?? 0;
                                $last_state = $active_part_state[$pid] ?? 'progress';
                                $is_done = ($last_state === 'done') || ($done >= (float)$part['qty'] && (float)$part['qty'] > 0);
                            ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td>
                                        <strong><?= clean($part['part_name']) ?></strong><br>
                                        <small class="text-muted">No: <?= clean($part['item_no']) ?> | DWG: <?= clean($part['drawing_no']) ?></small>
                                    </td>
                                    <?php
                                        $ncr_target_qty = isset($ncr_target_qty) ? (float)$ncr_target_qty : 0;
                                        $base_target = (float)$part['qty'];
                                        $display_target = $ncr_target_qty > 0 ? min($base_target, $ncr_target_qty) : $base_target;
                                    ?>
                                    <td class="text-center"><?= $display_target + 0 ?></td>
                                    <td class="text-center fw-bold <?= $is_done ? 'text-success' : 'text-primary' ?>"><?= $done + 0 ?></td>
                                    <td class="text-center">
                                        <?php if ($is_done): ?>
                                            <span class="badge bg-success">SELESAI</span>
                                        <?php elseif ($last_state === 'pending'): ?>
                                            <span class="badge bg-danger">BELUM SELESAI</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">ON PROGRESS</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($active_task['status'] == 'in_progress' && !$is_view_only): ?>
                                        <td class="text-center">
                                            <input type="number" step="0.01" min="0" name="qty_done" class="form-control form-control-sm text-center" value="0" form="pl_form_<?= $pid ?>">
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary">Auto</span>
                                        </td>
                                        <td>
                                            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Opsional" form="pl_form_<?= $pid ?>">
                                        </td>
                                        <td class="text-center">
                                            <form id="pl_form_<?= $pid ?>" method="POST" action="index.php?page=prod-operator&action=part_progress">
                                                <input type="hidden" name="task_id" value="<?= $active_task['id'] ?>">
                                                <input type="hidden" name="partlist_id" value="<?= $pid ?>">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-plus-circle"></i> Update
                                                </button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (!empty($active_partlists) && $active_task['status'] == 'in_progress' && !$is_view_only): ?>
            <div class="card-footer bg-white">
                <small class="text-muted d-block">Status part ditentukan otomatis berdasarkan qty vs target.</small>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($active_task && !empty($active_progress_logs)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-clock-history"></i> Histori Progress Part (Per Jam/Hari)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu</th>
                                <th>Part</th>
                                <th class="text-center">Qty Update</th>
                                <th class="text-center">Status</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_progress_logs as $log): ?>
                            <tr>
                                <td class="small"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                                <td>
                                    <strong><?= clean($log['part_name']) ?></strong><br>
                                    <small class="text-muted">DWG: <?= clean($log['drawing_no']) ?></small>
                                </td>
                                <td class="text-center fw-bold text-primary"><?= $log['qty_done'] + 0 ?></td>
                                <td class="text-center">
                                    <?php if (($log['progress_state'] ?? '') === 'done'): ?>
                                        <span class="badge bg-success">SELESAI</span>
                                    <?php elseif (($log['progress_state'] ?? '') === 'pending'): ?>
                                        <span class="badge bg-danger">BELUM</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">PROGRESS</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= clean($log['notes'] ?: '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- === SECTION 2: ANTRIAN TUGAS === -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="text-muted fw-bold mb-0"><i class="bi bi-list-task"></i> <?= $is_view_only ? 'Antrian Tugas Operator' : 'Antrian Tugas Anda' ?></h6>
            <span class="badge bg-secondary"><?= count($queue_tasks) ?> Pending</span>
        </div>
        <?php if ($is_view_only && $selected_operator_name): ?>
            <div class="small text-muted mb-2">Operator: <?= clean($selected_operator_name) ?></div>
        <?php endif; ?>
        
        <?php if (count($queue_tasks) > 0): ?>
            <?php foreach($queue_tasks as $task): ?>
            <div class="card shadow-sm mb-3 border-start border-4 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold text-dark mb-1"><?= $task['process_name'] ?></h5>
                            <span class="badge bg-light text-dark border"><?= $task['spk_number'] ?></span>
                        </div>
                        <?php 
                            $task_machine_bucket = operator_machine_bucket($task['process_name'] ?? '');
                            $machine_required = ($task_machine_bucket !== 'Other');
                            $has_machine_assigned = !empty($task['machine_id']);
                            $can_start_task = (!$machine_required || $has_machine_assigned);
                        ?>
                        <?php if(!$active_task && !$is_view_only): ?>
                            <?php if ($can_start_task): ?>
                                <a href="index.php?page=prod-operator&action=start&id=<?= $task['id'] ?>" class="btn btn-primary btn-lg shadow-sm" onclick="return confirm('Mulai kerjakan tugas ini?')">
                                    <i class="bi bi-play-fill"></i> MULAI
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-danger btn-lg shadow-sm" disabled title="Mesin belum dipilih saat assignment">
                                    <i class="bi bi-exclamation-triangle"></i> PILIH MESIN DULU
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn btn-light text-muted" disabled><i class="bi bi-lock"></i></button>
                        <?php endif; ?>
                    </div>
                    <hr class="my-2">
                    <div class="row">
                        <div class="col-12 mb-1">
                            <small class="text-muted">Target:</small> <span class="badge bg-info text-dark"><?= $task['target_qty'] + 0 ?> <?= $task['unit'] ?></span>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Item:</small>
                            <?php if (($task['item_count'] ?? 0) > 1): ?>
                                Multi Item (<?= (int)$task['item_count'] ?> jenis) - <?= clean($task['item_name']) ?>
                            <?php else: ?>
                                <?= clean($task['item_name']) ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($task['assigned_machine_name']) || !empty($task['assigned_machine_code'])): ?>
                        <div class="col-12 mt-1">
                            <small class="text-muted">Mesin:</small>
                            <span class="badge bg-light text-dark border">
                                <?= clean(trim(((string)($task['assigned_machine_code'] ?? '')) !== '' ? (($task['assigned_machine_code'] ?? '') . ' - ' . ($task['assigned_machine_name'] ?? '')) : ($task['assigned_machine_name'] ?? '-'))) ?>
                            </span>
                        </div>
                        <?php elseif (operator_machine_bucket($task['process_name'] ?? '') !== 'Other'): ?>
                        <div class="col-12 mt-1">
                            <small class="text-muted">Mesin:</small>
                            <span class="badge bg-danger">Belum dipilih</span>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($task['drawing_link'])): ?>
                        <div class="col-12 mt-2">
                            <a href="<?= htmlspecialchars($task['drawing_link']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-link-45deg"></i> Link Gambar
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-light text-center py-5 border border-dashed">
                <h5 class="text-muted">Tidak ada tugas antrian.</h5>
            </div>
        <?php endif; ?>

    </div>
</div>
<?php endif; ?>

<!-- ================= MODALS ================= -->

<?php if ($active_task && !$is_view_only): ?>
<!-- 1. MODAL HOLD -->
<div class="modal fade" id="holdModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="index.php?page=prod-operator&action=hold">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title text-dark">Tunda Pekerjaan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="task_id" value="<?= $active_task['id'] ?>">
                    <div class="mb-3">
                        <label>Alasan Hold</label>
                        <select name="reason" class="form-select form-select-lg" required>
                            <option value="Istirahat">Istirahat / Makan</option>
                            <option value="Mesin Rusak">Mesin Rusak</option>
                            <option value="Material Kurang">Material Kurang</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Catatan Tambahan</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning fw-bold w-50">Hold</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. MODAL FINISH -->
<div class="modal fade" id="finishModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="index.php?page=prod-operator&action=finish">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Selesaikan Pekerjaan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="task_id" value="<?= $active_task['id'] ?>">
                    
                    <div class="alert alert-info small py-2 mb-3">
                        <i class="bi bi-info-circle"></i> Input jumlah <strong>UNIT / SET</strong> barang jadi (Sesuai SPK). <br>
                        <strong>Target: <?= $active_task['target_qty'] + 0 ?> <?= $active_task['unit'] ?></strong>
                    </div>
                    <?php if ($active_parts_unfinished > 0): ?>
                    <div class="alert alert-warning small py-2 mb-3">
                        <i class="bi bi-exclamation-triangle"></i> Masih ada partlist yang belum selesai. Tugas tidak bisa ditutup sebelum checklist lengkap.
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="fw-bold text-success">Qty Bagus (OK)</label>
                            <div class="input-group">
                                <input type="number" name="qty_good" class="form-control form-control-lg border-success text-success fw-bold" value="<?= $active_task['target_qty'] + 0 ?>" min="0" step="0.01" required>
                                <span class="input-group-text"><?= $active_task['unit'] ?></span>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="fw-bold text-danger">Qty Rusak (NG)</label>
                            <div class="input-group">
                                <input type="number" id="qtyRejectInput" name="qty_reject" class="form-control form-control-lg border-danger text-danger fw-bold" value="0" min="0" step="0.01" disabled>
                                <span class="input-group-text"><?= $active_task['unit'] ?></span>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="rejectToggle">
                                <label class="form-check-label small text-muted" for="rejectToggle">
                                    Ada Reject
                                </label>
                            </div>
                            <input type="hidden" name="reject_enabled" id="rejectEnabled" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Catatan Operator</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Keterangan jika ada reject..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-bold w-50" <?= ($active_parts_unfinished > 0 ? 'disabled' : '') ?>>Selesai</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php render_footer(); ?>
<script>
(function () {
    const toggle = document.getElementById('rejectToggle');
    const input = document.getElementById('qtyRejectInput');
    const hidden = document.getElementById('rejectEnabled');
    if (!toggle || !input || !hidden) return;

    const sync = () => {
        if (toggle.checked) {
            input.disabled = false;
            if (input.value === '' || input.value === '0') input.value = '';
            hidden.value = '1';
            input.focus();
        } else {
            input.value = '0';
            input.disabled = true;
            hidden.value = '0';
        }
    };

    toggle.addEventListener('change', sync);
    sync();
})();

(function () {
    const els = Array.from(document.querySelectorAll('.js-op-runtime'));
    if (!els.length) return;
    const pad = (n) => String(n).padStart(2, '0');
    let tick = 0;
    const render = () => {
        els.forEach((el) => {
            const base = parseInt(el.getAttribute('data-elapsed-seconds') || '0', 10);
            const textEl = el.querySelector('span');
            if (!textEl) return;
            let secs = (Number.isFinite(base) ? base : 0) + tick;
            if (!Number.isFinite(secs) || secs < 0) secs = 0;
            const hh = Math.floor(secs / 3600);
            const mm = Math.floor((secs % 3600) / 60);
            const ss = secs % 60;
            textEl.textContent = `Run ${pad(hh)}:${pad(mm)}:${pad(ss)}`;
        });
    };
    render();
    setInterval(() => {
        tick += 1;
        render();
    }, 1000);
})();
</script>
