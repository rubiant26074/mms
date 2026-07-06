<?php
// modules/warehouse/batch_expiry/index.php
if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('whse_view')) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=dashboard';</script>";
    exit;
}
if (!function_exists('mms_is_dev_feature_enabled') || !mms_is_dev_feature_enabled('whse_batch_expiry')) {
    echo "<script>alert('Modul belum diaktifkan.'); window.location='index.php?page=dashboard';</script>";
    exit;
}

$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$action = trim((string)($_GET['action'] ?? ''));

if ($action === 'print') {
    require_once __DIR__ . '/print.php';
    return;
}

if (!function_exists('whse_batch_ensure_schema')) {
    function whse_batch_ensure_schema($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS warehouse_batches (
            id INT NOT NULL AUTO_INCREMENT,
            item_id INT NOT NULL,
            batch_number VARCHAR(120) NOT NULL,
            mfg_date DATE NULL,
            expiry_date DATE NULL,
            qty_available DECIMAL(18,4) NOT NULL DEFAULT 0,
            unit VARCHAR(30) NOT NULL DEFAULT '',
            source_doc VARCHAR(120) NULL,
            notes TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_item_batch (item_id, batch_number),
            KEY idx_expiry (expiry_date),
            KEY idx_item (item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS warehouse_batch_movements (
            id INT NOT NULL AUTO_INCREMENT,
            batch_id INT NOT NULL,
            movement_date DATE NOT NULL,
            movement_type ENUM('in','out','adjust') NOT NULL DEFAULT 'in',
            qty DECIMAL(18,4) NOT NULL DEFAULT 0,
            ref_doc VARCHAR(120) NULL,
            notes TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_batch_date (batch_id, movement_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('whse_batch_redirect')) {
    function whse_batch_redirect($msg, $type = 'success') {
        $params = [
            'page' => 'whse-batch-expiry',
            'msg' => (string)$msg,
            'msg_type' => (string)$type,
        ];
        if (!empty($_GET['theme'])) {
            $params['theme'] = trim((string)$_GET['theme']);
        }
        header('Location: index.php?' . http_build_query($params));
        exit;
    }
}

try {
    whse_batch_ensure_schema($pdo);
} catch (Exception $e) {
    render_header("Batch & Expiry Tracking");
    echo "<div class='alert alert-danger m-3'>Gagal menyiapkan tabel Batch Tracking.</div>";
    render_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission('whse_stock')) {
        whse_batch_redirect('Akses ditolak. Hanya tim gudang yang dapat mengubah data batch.', 'danger');
    }

    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        whse_batch_redirect('Permintaan tidak valid (CSRF).', 'danger');
    }

    $form_action = trim((string)($_POST['form_action'] ?? ''));

    try {
        if ($form_action === 'create_batch') {
            $item_id = (int)($_POST['item_id'] ?? 0);
            $batch_number = strtoupper(trim((string)($_POST['batch_number'] ?? '')));
            $mfg_date = trim((string)($_POST['mfg_date'] ?? ''));
            $expiry_date = trim((string)($_POST['expiry_date'] ?? ''));
            $qty = (float)($_POST['qty'] ?? 0);
            $source_doc = trim((string)($_POST['source_doc'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));

            if ($item_id <= 0 || $batch_number === '' || $qty <= 0) {
                throw new RuntimeException('Data batch belum lengkap (item, batch, qty wajib diisi).');
            }

            $stmt_item = $pdo->prepare("SELECT id, unit FROM items WHERE id = ? LIMIT 1");
            $stmt_item->execute([$item_id]);
            $item = $stmt_item->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                throw new RuntimeException('Item tidak ditemukan.');
            }
            $unit = (string)($item['unit'] ?? '');

            $pdo->beginTransaction();

            $stmt_exist = $pdo->prepare("SELECT id FROM warehouse_batches WHERE item_id = ? AND batch_number = ? LIMIT 1");
            $stmt_exist->execute([$item_id, $batch_number]);
            $batch_id = (int)($stmt_exist->fetchColumn() ?: 0);

            if ($batch_id > 0) {
                $stmt_upd = $pdo->prepare("UPDATE warehouse_batches
                                           SET qty_available = qty_available + ?,
                                               mfg_date = COALESCE(NULLIF(?, ''), mfg_date),
                                               expiry_date = COALESCE(NULLIF(?, ''), expiry_date),
                                               source_doc = ?,
                                               notes = ?,
                                               is_active = 1,
                                               unit = ?
                                           WHERE id = ?");
                $stmt_upd->execute([$qty, $mfg_date, $expiry_date, $source_doc, $notes, $unit, $batch_id]);
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO warehouse_batches
                    (item_id, batch_number, mfg_date, expiry_date, qty_available, unit, source_doc, notes, is_active, created_by)
                    VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, 1, ?)");
                $stmt_ins->execute([$item_id, $batch_number, $mfg_date, $expiry_date, $qty, $unit, $source_doc, $notes, (int)($_SESSION['user_id'] ?? 0)]);
                $batch_id = (int)$pdo->lastInsertId();
            }

            $stmt_mv = $pdo->prepare("INSERT INTO warehouse_batch_movements
                (batch_id, movement_date, movement_type, qty, ref_doc, notes, created_by)
                VALUES (?, CURDATE(), 'in', ?, ?, ?, ?)");
            $stmt_mv->execute([$batch_id, $qty, $source_doc, ($notes !== '' ? $notes : 'Input batch awal'), (int)($_SESSION['user_id'] ?? 0)]);

            $pdo->commit();
            whse_batch_redirect('Batch berhasil disimpan.');
        }

        if ($form_action === 'move_batch') {
            $batch_id = (int)($_POST['batch_id'] ?? 0);
            $movement_type = trim((string)($_POST['movement_type'] ?? 'out'));
            $qty_input = (float)($_POST['qty'] ?? 0);
            $movement_date = trim((string)($_POST['movement_date'] ?? date('Y-m-d')));
            $ref_doc = trim((string)($_POST['ref_doc'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));
            if ($movement_date === '') $movement_date = date('Y-m-d');

            if ($batch_id <= 0) throw new RuntimeException('Batch belum dipilih.');
            if (!in_array($movement_type, ['in', 'out', 'adjust'], true)) {
                throw new RuntimeException('Tipe mutasi tidak valid.');
            }
            if ($movement_type !== 'adjust' && $qty_input <= 0) {
                throw new RuntimeException('Qty mutasi harus lebih dari 0.');
            }
            if ($movement_type === 'adjust' && abs($qty_input) < 0.0001) {
                throw new RuntimeException('Qty adjust tidak boleh 0.');
            }

            $stmt_batch = $pdo->prepare("SELECT qty_available FROM warehouse_batches WHERE id = ? LIMIT 1");
            $stmt_batch->execute([$batch_id]);
            $current_qty = (float)($stmt_batch->fetchColumn() ?? -1);
            if ($current_qty < 0) {
                throw new RuntimeException('Batch tidak ditemukan.');
            }

            $delta = 0.0;
            if ($movement_type === 'in') $delta = abs($qty_input);
            if ($movement_type === 'out') $delta = -abs($qty_input);
            if ($movement_type === 'adjust') $delta = $qty_input;

            $new_qty = $current_qty + $delta;
            if ($new_qty < 0) {
                throw new RuntimeException('Stok batch tidak cukup untuk mutasi OUT/ADJUST.');
            }

            $pdo->beginTransaction();

            $stmt_upd = $pdo->prepare("UPDATE warehouse_batches SET qty_available = ?, is_active = ? WHERE id = ?");
            $stmt_upd->execute([$new_qty, ($new_qty > 0 ? 1 : 0), $batch_id]);

            $stmt_mv = $pdo->prepare("INSERT INTO warehouse_batch_movements
                (batch_id, movement_date, movement_type, qty, ref_doc, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_mv->execute([$batch_id, $movement_date, $movement_type, $delta, $ref_doc, $notes, (int)($_SESSION['user_id'] ?? 0)]);

            $pdo->commit();
            whse_batch_redirect('Mutasi batch berhasil disimpan.');
        }

        whse_batch_redirect('Aksi tidak dikenali.', 'danger');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        whse_batch_redirect($e->getMessage(), 'danger');
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$expiry_filter = trim((string)($_GET['expiry'] ?? 'all'));

$summary = [
    'total_batches' => 0,
    'near_expiry' => 0,
    'expired' => 0,
    'total_qty' => 0,
];
try {
    $summary_sql = "SELECT
        COUNT(*) AS total_batches,
        SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired,
        SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS near_expiry,
        COALESCE(SUM(qty_available), 0) AS total_qty
        FROM warehouse_batches
        WHERE qty_available > 0";
    $summary_stmt = $pdo->query($summary_sql);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: $summary;
} catch (Exception $e) {}

$items = $pdo->query("SELECT id, item_code, item_name, unit
                      FROM items
                      WHERE item_type IN ('raw_material', 'consumable', 'finish_good')
                      ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$batch_options = $pdo->query("SELECT b.id, b.batch_number, b.qty_available,
                                     COALESCE(NULLIF(b.unit, ''), i.unit) AS unit_name,
                                     i.item_code, i.item_name
                              FROM warehouse_batches b
                              JOIN items i ON i.id = b.item_id
                              WHERE b.qty_available > 0 AND b.is_active = 1
                              ORDER BY i.item_name ASC, b.expiry_date ASC")->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT b.*, i.item_code, i.item_name, COALESCE(NULLIF(b.unit, ''), i.unit) AS unit_name
        FROM warehouse_batches b
        JOIN items i ON i.id = b.item_id
        WHERE 1=1 AND b.qty_available > 0";
$params = [];
if ($search !== '') {
    $sql .= " AND (i.item_code LIKE ? OR i.item_name LIKE ? OR b.batch_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($expiry_filter === 'expired') {
    $sql .= " AND b.expiry_date IS NOT NULL AND b.expiry_date < CURDATE()";
} elseif ($expiry_filter === 'near') {
    $sql .= " AND b.expiry_date IS NOT NULL AND b.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
} elseif ($expiry_filter === 'no_expiry') {
    $sql .= " AND b.expiry_date IS NULL";
} elseif ($expiry_filter === 'safe') {
    $sql .= " AND b.expiry_date IS NOT NULL AND b.expiry_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}
$sql .= " ORDER BY (b.expiry_date IS NULL) ASC, b.expiry_date ASC, b.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$recent_moves = $pdo->query("SELECT m.*, b.batch_number, i.item_code, i.item_name, COALESCE(NULLIF(b.unit, ''), i.unit) AS unit_name
                             FROM warehouse_batch_movements m
                             JOIN warehouse_batches b ON b.id = m.batch_id
                             JOIN items i ON i.id = b.item_id
                             ORDER BY m.id DESC
                             LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);

render_header("Batch & Expiry Tracking");
?>

<div class="row mb-3">
    <div class="col-md-8">
        <h3 class="fw-bold"><i class="bi bi-upc-scan"></i> Batch & Expiry Tracking</h3>
        <p class="text-muted">Kontrol batch number dan tanggal kedaluwarsa untuk raw material / finish good.</p>
    </div>
    <div class="col-md-4 text-end">
        <a href="index.php?page=whse-batch-expiry&action=print&search=<?= urlencode($search) ?>&expiry=<?= urlencode($expiry_filter) ?><?= !empty($_GET['theme']) ? '&theme=' . urlencode((string)$_GET['theme']) : '' ?>" target="_blank" class="btn btn-outline-dark">
            <i class="bi bi-printer"></i> Print
        </a>
    </div>
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
                        <li>Verifikasi fisik label batch, item, dan qty saat barang datang.</li>
                        <li>Isi batch number, MFG/Expiry, serta referensi dokumen asal.</li>
                        <li>Pastikan data near expiry / expired ditandai dan dilaporkan.</li>
                    </ol>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="fw-bold text-success mb-2">Approver</div>
                    <ol class="small mb-0 ps-3">
                        <li>Review mutasi batch IN/OUT/ADJUST dari checker.</li>
                        <li>Pastikan qty tidak melebihi stok batch dan referensi valid.</li>
                        <li>Konfirmasi eskalasi untuk batch expired / near expiry kritis.</li>
                    </ol>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="fw-bold text-dark mb-2">Admin Gudang</div>
                    <ol class="small mb-0 ps-3">
                        <li>Pastikan master batch konsisten (tidak duplikasi item+batch).</li>
                        <li>Audit riwayat mutasi harian dan cocokkan ke dokumen operasional.</li>
                        <li>Koordinasikan koreksi data jika ditemukan selisih pencatatan.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Batch Aktif</small><h4 class="mb-0"><?= (int)$summary['total_batches'] ?></h4></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Near Expiry (<=30 hari)</small><h4 class="mb-0 text-warning"><?= (int)$summary['near_expiry'] ?></h4></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Expired</small><h4 class="mb-0 text-danger"><?= (int)$summary['expired'] ?></h4></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Total Qty Batch</small><h4 class="mb-0"><?= number_format((float)$summary['total_qty'], 2, ',', '.') ?></h4></div></div></div>
</div>

<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white fw-bold">Input Batch Baru / Incoming</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
                    <input type="hidden" name="form_action" value="create_batch">

                    <div class="mb-2">
                        <label class="form-label">Item</label>
                        <select name="item_id" class="form-select" required>
                            <option value="">-- Pilih Item --</option>
                            <?php foreach ($items as $it): ?>
                                <option value="<?= (int)$it['id'] ?>"><?= $esc($it['item_code']) ?> - <?= $esc($it['item_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Batch Number</label>
                            <input type="text" name="batch_number" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Qty Masuk</label>
                            <input type="number" step="0.0001" min="0.0001" name="qty" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">MFG Date</label>
                            <input type="date" name="mfg_date" class="form-control">
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Ref Dokumen</label>
                        <input type="text" name="source_doc" class="form-control" placeholder="Contoh: GR-2602-0004">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Batch</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white fw-bold">Mutasi Batch (IN / OUT / ADJUST)</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
                    <input type="hidden" name="form_action" value="move_batch">
                    <div class="mb-2">
                        <label class="form-label">Pilih Batch</label>
                        <select name="batch_id" class="form-select" required>
                            <option value="">-- Pilih Batch Aktif --</option>
                            <?php foreach ($batch_options as $bo): ?>
                                <option value="<?= (int)$bo['id'] ?>">
                                    <?= $esc($bo['item_code']) ?> | <?= $esc($bo['batch_number']) ?> | Qty: <?= number_format((float)$bo['qty_available'], 2, ',', '.') ?> <?= $esc($bo['unit_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Tipe</label>
                            <select name="movement_type" class="form-select">
                                <option value="out">OUT</option>
                                <option value="in">IN</option>
                                <option value="adjust">ADJUST (+/-)</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Qty</label>
                            <input type="number" step="0.0001" name="qty" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Tanggal</label>
                            <input type="date" name="movement_date" value="<?= date('Y-m-d') ?>" class="form-control">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Ref Dokumen</label>
                        <input type="text" name="ref_doc" class="form-control" placeholder="ITR / SJ / Penyesuaian">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-dark"><i class="bi bi-arrow-left-right"></i> Simpan Mutasi</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="page" value="whse-batch-expiry">
            <?php if (!empty($_GET['theme'])): ?><input type="hidden" name="theme" value="<?= $esc($_GET['theme']) ?>"><?php endif; ?>
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Cari item code / nama / batch..." value="<?= $esc($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="expiry" class="form-select">
                    <option value="all" <?= $expiry_filter === 'all' ? 'selected' : '' ?>>Semua Expiry</option>
                    <option value="expired" <?= $expiry_filter === 'expired' ? 'selected' : '' ?>>Expired</option>
                    <option value="near" <?= $expiry_filter === 'near' ? 'selected' : '' ?>>Near Expiry (&lt;= 30 hari)</option>
                    <option value="safe" <?= $expiry_filter === 'safe' ? 'selected' : '' ?>>Safe (&gt; 30 hari)</option>
                    <option value="no_expiry" <?= $expiry_filter === 'no_expiry' ? 'selected' : '' ?>>Tanpa Expiry</option>
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-2"><a href="index.php?page=whse-batch-expiry<?= !empty($_GET['theme']) ? '&theme=' . urlencode((string)$_GET['theme']) : '' ?>" class="btn btn-outline-secondary w-100">Reset</a></div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th>Batch</th>
                        <th>MFG</th>
                        <th>Expiry</th>
                        <th class="text-end">Qty Available</th>
                        <th>Status</th>
                        <th>Ref</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data batch.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <?php
                            $expiry = (string)($r['expiry_date'] ?? '');
                            $status_badge = '<span class="badge bg-secondary">NO EXPIRY</span>';
                            if ($expiry !== '') {
                                if ($expiry < date('Y-m-d')) {
                                    $status_badge = '<span class="badge bg-danger">EXPIRED</span>';
                                } elseif ($expiry <= date('Y-m-d', strtotime('+30 days'))) {
                                    $status_badge = '<span class="badge bg-warning text-dark">NEAR EXPIRY</span>';
                                } else {
                                    $status_badge = '<span class="badge bg-success">SAFE</span>';
                                }
                            }
                        ?>
                        <tr>
                            <td>
                                <strong><?= $esc($r['item_code']) ?></strong><br>
                                <small class="text-muted"><?= $esc($r['item_name']) ?></small>
                            </td>
                            <td class="fw-bold"><?= $esc($r['batch_number']) ?></td>
                            <td><?= !empty($r['mfg_date']) ? date('d/m/Y', strtotime((string)$r['mfg_date'])) : '-' ?></td>
                            <td><?= !empty($r['expiry_date']) ? date('d/m/Y', strtotime((string)$r['expiry_date'])) : '-' ?></td>
                            <td class="text-end fw-bold"><?= number_format((float)$r['qty_available'], 2, ',', '.') ?> <?= $esc($r['unit_name']) ?></td>
                            <td><?= $status_badge ?></td>
                            <td><?= $esc($r['source_doc'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold">Riwayat Mutasi Batch Terakhir</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Item / Batch</th>
                        <th>Tipe</th>
                        <th class="text-end">Qty</th>
                        <th>Ref</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_moves)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">Belum ada mutasi.</td></tr>
                    <?php else: foreach ($recent_moves as $mv): ?>
                        <?php
                            $qty_val = (float)$mv['qty'];
                            $qty_class = $qty_val < 0 ? 'text-danger' : 'text-success';
                        ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime((string)$mv['movement_date'])) ?></td>
                            <td><strong><?= $esc($mv['item_code']) ?></strong> - <?= $esc($mv['batch_number']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= strtoupper((string)$mv['movement_type']) ?></span></td>
                            <td class="text-end fw-bold <?= $qty_class ?>"><?= number_format($qty_val, 2, ',', '.') ?> <?= $esc($mv['unit_name']) ?></td>
                            <td><?= $esc($mv['ref_doc'] ?: '-') ?></td>
                            <td><?= $esc($mv['notes'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php render_footer(); ?>
