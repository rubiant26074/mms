<?php
// modules/warehouse/cycle_counting/index.php
if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('whse_view')) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=dashboard';</script>";
    exit;
}
if (!function_exists('mms_is_dev_feature_enabled') || !mms_is_dev_feature_enabled('whse_cycle_counting')) {
    echo "<script>alert('Modul belum diaktifkan.'); window.location='index.php?page=dashboard';</script>";
    exit;
}

$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$action = trim((string)($_GET['action'] ?? 'index'));

if ($action === 'print') {
    require_once __DIR__ . '/print.php';
    return;
}

if (!function_exists('whse_cc_ensure_schema')) {
    function whse_cc_ensure_schema($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cycle_count_sessions (
            id INT NOT NULL AUTO_INCREMENT,
            session_number VARCHAR(40) NOT NULL,
            count_date DATE NOT NULL,
            count_area VARCHAR(120) NULL,
            status ENUM('draft','posted') NOT NULL DEFAULT 'draft',
            notes TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            posted_by INT NULL,
            posted_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_session_number (session_number),
            KEY idx_count_date (count_date),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cycle_count_session_items (
            id INT NOT NULL AUTO_INCREMENT,
            session_id INT NOT NULL,
            item_id INT NOT NULL,
            system_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
            counted_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
            variance_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
            reason VARCHAR(160) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_session_item (session_id, item_id),
            KEY idx_session (session_id),
            KEY idx_item (item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('whse_cc_redirect')) {
    function whse_cc_redirect($msg, $type = 'success', $extra = []) {
        $params = [
            'page' => 'whse-cycle-counting',
            'msg' => (string)$msg,
            'msg_type' => (string)$type,
        ];
        if (!empty($_GET['theme'])) {
            $params['theme'] = trim((string)$_GET['theme']);
        }
        if (is_array($extra)) {
            foreach ($extra as $k => $v) $params[$k] = $v;
        }
        header('Location: index.php?' . http_build_query($params));
        exit;
    }
}

if (!function_exists('whse_cc_generate_number')) {
    function whse_cc_generate_number($pdo, $count_date) {
        $dt = $count_date !== '' ? strtotime($count_date) : time();
        $ym = date('ym', $dt ?: time());
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cycle_count_sessions WHERE session_number LIKE ?");
        $stmt->execute(["CC-$ym-%"]);
        $seq = (int)$stmt->fetchColumn() + 1;
        return "CC-$ym-" . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }
}

try {
    whse_cc_ensure_schema($pdo);
} catch (Exception $e) {
    render_header("Cycle Counting");
    echo "<div class='alert alert-danger m-3'>Gagal menyiapkan tabel Cycle Counting.</div>";
    render_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        whse_cc_redirect('Permintaan tidak valid (CSRF).', 'danger');
    }

    if (!has_permission('whse_stock')) {
        whse_cc_redirect('Akses ditolak. Hanya tim gudang yang bisa memproses cycle counting.', 'danger');
    }

    $form_action = trim((string)($_POST['form_action'] ?? ''));

    try {
        if ($form_action === 'create_session') {
            $count_date = trim((string)($_POST['count_date'] ?? date('Y-m-d')));
            $count_area = trim((string)($_POST['count_area'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));

            $item_ids = $_POST['item_id'] ?? [];
            $counted_qty = $_POST['counted_qty'] ?? [];
            $reasons = $_POST['reason'] ?? [];
            $line_notes = $_POST['line_notes'] ?? [];

            $line_map = [];
            foreach ($item_ids as $i => $raw_id) {
                $item_id = (int)$raw_id;
                $counted_raw = trim((string)($counted_qty[$i] ?? ''));
                if ($item_id <= 0 || $counted_raw === '') continue;
                $counted = (float)str_replace(',', '.', $counted_raw);
                $line_map[$item_id] = [
                    'counted_qty' => $counted,
                    'reason' => trim((string)($reasons[$i] ?? '')),
                    'line_notes' => trim((string)($line_notes[$i] ?? '')),
                ];
            }
            if (empty($line_map)) {
                throw new RuntimeException('Minimal 1 item harus diisi untuk cycle counting.');
            }

            $pdo->beginTransaction();
            $session_number = whse_cc_generate_number($pdo, $count_date);

            $stmt_head = $pdo->prepare("INSERT INTO cycle_count_sessions
                (session_number, count_date, count_area, status, notes, created_by)
                VALUES (?, ?, ?, 'draft', ?, ?)");
            $stmt_head->execute([$session_number, $count_date, $count_area, $notes, (int)($_SESSION['user_id'] ?? 0)]);
            $session_id = (int)$pdo->lastInsertId();

            $stmt_item = $pdo->prepare("SELECT current_stock FROM items WHERE id = ? LIMIT 1");
            $stmt_line = $pdo->prepare("INSERT INTO cycle_count_session_items
                (session_id, item_id, system_qty, counted_qty, variance_qty, reason, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $saved = 0;
            foreach ($line_map as $item_id => $line) {
                $stmt_item->execute([$item_id]);
                $system_qty = $stmt_item->fetchColumn();
                if ($system_qty === false) continue;
                $system_qty = (float)$system_qty;
                $variance = (float)$line['counted_qty'] - $system_qty;
                $stmt_line->execute([$session_id, $item_id, $system_qty, (float)$line['counted_qty'], $variance, $line['reason'], $line['line_notes']]);
                $saved++;
            }

            if ($saved <= 0) {
                throw new RuntimeException('Tidak ada item valid yang tersimpan.');
            }

            $pdo->commit();
            whse_cc_redirect('Cycle counting berhasil dibuat.', 'success', ['action' => 'view', 'id' => $session_id]);
        }

        if ($form_action === 'post_session') {
            $session_id = (int)($_POST['session_id'] ?? 0);
            if ($session_id <= 0) throw new RuntimeException('Session cycle count tidak valid.');

            $pdo->beginTransaction();

            $stmt_status = $pdo->prepare("SELECT status FROM cycle_count_sessions WHERE id = ? LIMIT 1");
            $stmt_status->execute([$session_id]);
            $status = (string)($stmt_status->fetchColumn() ?: '');
            if ($status !== 'draft') {
                throw new RuntimeException('Session ini sudah diposting atau tidak valid.');
            }

            $stmt_lines = $pdo->prepare("SELECT item_id, counted_qty FROM cycle_count_session_items WHERE session_id = ?");
            $stmt_lines->execute([$session_id]);
            $lines = $stmt_lines->fetchAll(PDO::FETCH_ASSOC);
            if (empty($lines)) {
                throw new RuntimeException('Session tidak memiliki detail item.');
            }

            $stmt_upd_stock = $pdo->prepare("UPDATE items SET current_stock = ? WHERE id = ?");
            foreach ($lines as $ln) {
                $stmt_upd_stock->execute([(float)$ln['counted_qty'], (int)$ln['item_id']]);
            }

            $stmt_post = $pdo->prepare("UPDATE cycle_count_sessions
                                        SET status='posted', posted_by=?, posted_at=NOW()
                                        WHERE id = ?");
            $stmt_post->execute([(int)($_SESSION['user_id'] ?? 0), $session_id]);

            $pdo->commit();
            whse_cc_redirect('Penyesuaian stok berhasil diposting.', 'success', ['action' => 'view', 'id' => $session_id]);
        }

        whse_cc_redirect('Aksi tidak dikenali.', 'danger');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        whse_cc_redirect($e->getMessage(), 'danger');
    }
}

render_header("Cycle Counting");
?>

<div class="row mb-3">
    <div class="col-md-8">
        <h3 class="fw-bold"><i class="bi bi-clipboard2-check"></i> Cycle Counting</h3>
        <p class="text-muted">Stock opname parsial periodik untuk validasi stok sistem vs stok fisik.</p>
    </div>
    <?php if (has_permission('whse_stock')): ?>
        <div class="col-md-4 text-end">
            <a href="index.php?page=whse-cycle-counting&action=create<?= !empty($_GET['theme']) ? '&theme=' . urlencode((string)$_GET['theme']) : '' ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Buat Session Count
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-<?= $esc($_GET['msg_type'] ?? 'info') ?> py-2"><?= $esc($_GET['msg']) ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-4 border-start border-4 border-info">
    <div class="card-header bg-light fw-bold"><i class="bi bi-journal-text"></i> SOP Singkat Per Role</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="fw-bold text-primary mb-2">Checker</div>
                    <ol class="small mb-0 ps-3">
                        <li>Lakukan hitung fisik item per area/rack sesuai jadwal cycle count.</li>
                        <li>Input counted qty sesuai hasil fisik, jangan ubah system qty manual.</li>
                        <li>Isi alasan selisih jika variance tidak nol.</li>
                    </ol>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="fw-bold text-success mb-2">Approver</div>
                    <ol class="small mb-0 ps-3">
                        <li>Review variance item sebelum session diposting.</li>
                        <li>Validasi penyebab selisih dan minta recount jika perlu.</li>
                        <li>Setujui posting hanya jika data fisik sudah final.</li>
                    </ol>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="fw-bold text-dark mb-2">Admin Gudang</div>
                    <ol class="small mb-0 ps-3">
                        <li>Kelola jadwal cycle count periodik per zona.</li>
                        <li>Post session draft untuk update stok sistem ke counted qty.</li>
                        <li>Dokumentasikan histori selisih untuk perbaikan proses gudang.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($action === 'create'): ?>
    <?php
        $item_master = $pdo->query("SELECT id, item_code, item_name, unit, current_stock
                                    FROM items
                                    WHERE item_type IN ('raw_material','consumable','wip','finish_good')
                                    ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
        <input type="hidden" name="form_action" value="create_session">

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white fw-bold">Header Session</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Tanggal Count</label>
                        <input type="date" name="count_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Area / Zona</label>
                        <input type="text" name="count_area" class="form-control" placeholder="Contoh: RM Rack A">
                    </div>
                    <div class="col-md-5 mb-2">
                        <label class="form-label">Catatan Header</label>
                        <input type="text" name="notes" class="form-control" placeholder="Catatan umum sesi counting">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <strong>Item Counting</strong>
                <button type="button" class="btn btn-sm btn-success" id="btnAddRow"><i class="bi bi-plus"></i> Tambah Baris</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" id="ccTable">
                        <thead class="table-light">
                            <tr>
                                <th width="36%">Item</th>
                                <th width="12%">System Qty</th>
                                <th width="12%">Counted Qty</th>
                                <th width="12%">Variance</th>
                                <th width="14%">Reason</th>
                                <th width="10%">Catatan</th>
                                <th width="4%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="ccRows"></tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="index.php?page=whse-cycle-counting<?= !empty($_GET['theme']) ? '&theme=' . urlencode((string)$_GET['theme']) : '' ?>" class="btn btn-secondary me-2">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Session</button>
            </div>
        </div>
    </form>

    <script>
    (function () {
        const rowsWrap = document.getElementById('ccRows');
        const addBtn = document.getElementById('btnAddRow');
        if (!rowsWrap || !addBtn) return;

        const itemOptions = `<?php foreach ($item_master as $it): ?><option value="<?= (int)$it['id'] ?>" data-stock="<?= (float)$it['current_stock'] ?>" data-unit="<?= $esc($it['unit']) ?>"><?= $esc($it['item_code']) ?> - <?= $esc($it['item_name']) ?></option><?php endforeach; ?>`;

        const createRow = () => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <select name="item_id[]" class="form-select cc-item">
                        <option value="">-- Pilih Item --</option>${itemOptions}
                    </select>
                </td>
                <td><input type="number" class="form-control cc-system text-end" value="0" readonly></td>
                <td><input type="number" step="0.0001" name="counted_qty[]" class="form-control cc-counted text-end" value=""></td>
                <td><input type="number" class="form-control cc-variance text-end" value="0" readonly></td>
                <td>
                    <select name="reason[]" class="form-select">
                        <option value="">-</option>
                        <option value="selisih_uom">Selisih UOM</option>
                        <option value="damage_scrap">Damage/Scrap</option>
                        <option value="miss_issue">Issue belum tercatat</option>
                        <option value="miss_receive">Receive belum tercatat</option>
                        <option value="other">Lainnya</option>
                    </select>
                </td>
                <td><input type="text" name="line_notes[]" class="form-control"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-danger cc-remove">x</button></td>
            `;

            const itemSel = tr.querySelector('.cc-item');
            const systemEl = tr.querySelector('.cc-system');
            const countedEl = tr.querySelector('.cc-counted');
            const varianceEl = tr.querySelector('.cc-variance');

            const calc = () => {
                const sys = parseFloat(systemEl.value || '0') || 0;
                const cnt = parseFloat(countedEl.value || '0') || 0;
                varianceEl.value = (cnt - sys).toFixed(4);
            };

            itemSel.addEventListener('change', () => {
                const opt = itemSel.options[itemSel.selectedIndex];
                systemEl.value = parseFloat(opt?.getAttribute('data-stock') || '0').toFixed(4);
                calc();
            });
            countedEl.addEventListener('input', calc);
            tr.querySelector('.cc-remove').addEventListener('click', () => tr.remove());

            rowsWrap.appendChild(tr);
        };

        addBtn.addEventListener('click', createRow);
        createRow();
    })();
    </script>

<?php elseif ($action === 'view' && (int)($_GET['id'] ?? 0) > 0): ?>
    <?php
        $id = (int)$_GET['id'];
        $stmt_head = $pdo->prepare("SELECT s.*, u.fullname AS created_name, p.fullname AS posted_name
                                    FROM cycle_count_sessions s
                                    LEFT JOIN users u ON u.id = s.created_by
                                    LEFT JOIN users p ON p.id = s.posted_by
                                    WHERE s.id = ? LIMIT 1");
        $stmt_head->execute([$id]);
        $head = $stmt_head->fetch(PDO::FETCH_ASSOC);

        if (!$head) {
            echo "<div class='alert alert-warning'>Session tidak ditemukan.</div>";
        } else {
            $stmt_line = $pdo->prepare("SELECT l.*, i.item_code, i.item_name, i.unit
                                        FROM cycle_count_session_items l
                                        JOIN items i ON i.id = l.item_id
                                        WHERE l.session_id = ?
                                        ORDER BY i.item_name ASC");
            $stmt_line->execute([$id]);
            $lines = $stmt_line->fetchAll(PDO::FETCH_ASSOC);
    ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <strong>Detail Session: <?= $esc($head['session_number']) ?></strong>
                <div>
                    <a href="index.php?page=whse-cycle-counting&action=print&id=<?= (int)$head['id'] ?><?= !empty($_GET['theme']) ? '&theme=' . urlencode((string)$_GET['theme']) : '' ?>" target="_blank" class="btn btn-sm btn-outline-dark me-1">
                        <i class="bi bi-printer"></i> Print
                    </a>
                    <a href="index.php?page=whse-cycle-counting<?= !empty($_GET['theme']) ? '&theme=' . urlencode((string)$_GET['theme']) : '' ?>" class="btn btn-sm btn-secondary">Kembali</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3"><small class="text-muted">Tanggal</small><div class="fw-bold"><?= date('d/m/Y', strtotime((string)$head['count_date'])) ?></div></div>
                    <div class="col-md-3"><small class="text-muted">Area</small><div class="fw-bold"><?= $esc($head['count_area'] ?: '-') ?></div></div>
                    <div class="col-md-2"><small class="text-muted">Status</small><div class="fw-bold"><?= strtoupper($esc($head['status'])) ?></div></div>
                    <div class="col-md-4"><small class="text-muted">Dibuat oleh</small><div class="fw-bold"><?= $esc($head['created_name'] ?: '-') ?></div></div>
                </div>
                <?php if (!empty($head['notes'])): ?>
                    <div class="mt-2"><small class="text-muted">Catatan</small><div><?= $esc($head['notes']) ?></div></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th class="text-end">System</th>
                                <th class="text-end">Counted</th>
                                <th class="text-end">Variance</th>
                                <th>Reason</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lines)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada item.</td></tr>
                            <?php else: foreach ($lines as $ln): ?>
                                <?php $var = (float)$ln['variance_qty']; ?>
                                <tr>
                                    <td><strong><?= $esc($ln['item_code']) ?></strong> - <?= $esc($ln['item_name']) ?></td>
                                    <td class="text-end"><?= number_format((float)$ln['system_qty'], 4, ',', '.') ?> <?= $esc($ln['unit']) ?></td>
                                    <td class="text-end"><?= number_format((float)$ln['counted_qty'], 4, ',', '.') ?> <?= $esc($ln['unit']) ?></td>
                                    <td class="text-end fw-bold <?= $var == 0.0 ? '' : ($var > 0 ? 'text-success' : 'text-danger') ?>">
                                        <?= number_format($var, 4, ',', '.') ?> <?= $esc($ln['unit']) ?>
                                    </td>
                                    <td><?= $esc($ln['reason'] ?: '-') ?></td>
                                    <td><?= $esc($ln['notes'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($head['status'] === 'draft' && has_permission('whse_stock')): ?>
            <form method="POST" onsubmit="return confirm('Post hasil cycle count ini? Stok sistem akan diupdate ke nilai counted.');">
                <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
                <input type="hidden" name="form_action" value="post_session">
                <input type="hidden" name="session_id" value="<?= (int)$head['id'] ?>">
                <button type="submit" class="btn btn-success"><i class="bi bi-check2-square"></i> Post Penyesuaian Stok</button>
            </form>
        <?php endif; ?>
    <?php } ?>

<?php else: ?>
    <?php
        $status_filter = trim((string)($_GET['status'] ?? ''));
        $search = trim((string)($_GET['search'] ?? ''));

        $sql = "SELECT s.*,
                       u.fullname AS created_name,
                       p.fullname AS posted_name,
                       COUNT(si.id) AS line_count,
                       COALESCE(SUM(si.variance_qty), 0) AS total_variance
                FROM cycle_count_sessions s
                LEFT JOIN users u ON u.id = s.created_by
                LEFT JOIN users p ON p.id = s.posted_by
                LEFT JOIN cycle_count_session_items si ON si.session_id = s.id
                WHERE 1=1";
        $params = [];
        if ($status_filter !== '') {
            $sql .= " AND s.status = ?";
            $params[] = $status_filter;
        }
        if ($search !== '') {
            $sql .= " AND (s.session_number LIKE ? OR s.count_area LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        $sql .= " GROUP BY s.id ORDER BY s.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="page" value="whse-cycle-counting">
                <?php if (!empty($_GET['theme'])): ?><input type="hidden" name="theme" value="<?= $esc($_GET['theme']) ?>"><?php endif; ?>
                <div class="col-md-4"><input type="text" name="search" class="form-control" value="<?= $esc($search) ?>" placeholder="Cari nomor session / area"></div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="posted" <?= $status_filter === 'posted' ? 'selected' : '' ?>>Posted</option>
                    </select>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
                <div class="col-md-2"><a href="index.php?page=whse-cycle-counting<?= !empty($_GET['theme']) ? '&theme=' . urlencode((string)$_GET['theme']) : '' ?>" class="btn btn-outline-secondary w-100">Reset</a></div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>No Session</th>
                            <th>Tanggal</th>
                            <th>Area</th>
                            <th class="text-center">Item Counted</th>
                            <th class="text-end">Total Variance</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sessions)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada session cycle count.</td></tr>
                        <?php else: foreach ($sessions as $s): ?>
                            <?php $badge = $s['status'] === 'posted' ? 'bg-success' : 'bg-warning text-dark'; ?>
                            <tr>
                                <td><strong><?= $esc($s['session_number']) ?></strong><br><small class="text-muted"><?= $esc($s['created_name'] ?: '-') ?></small></td>
                                <td><?= date('d/m/Y', strtotime((string)$s['count_date'])) ?></td>
                                <td><?= $esc($s['count_area'] ?: '-') ?></td>
                                <td class="text-center"><?= (int)$s['line_count'] ?></td>
                                <td class="text-end fw-bold"><?= number_format((float)$s['total_variance'], 4, ',', '.') ?></td>
                                <td><span class="badge <?= $badge ?>"><?= strtoupper($esc($s['status'])) ?></span></td>
                                <td>
                                    <a href="index.php?page=whse-cycle-counting&action=view&id=<?= (int)$s['id'] ?><?= !empty($_GET['theme']) ? '&theme=' . urlencode((string)$_GET['theme']) : '' ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <a href="index.php?page=whse-cycle-counting&action=print&id=<?= (int)$s['id'] ?><?= !empty($_GET['theme']) ? '&theme=' . urlencode((string)$_GET['theme']) : '' ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                                        <i class="bi bi-printer"></i> Print
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php render_footer(); ?>
