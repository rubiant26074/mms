<?php
// modules/procurement/vendor_rating/index.php
if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('purch_vendor_view')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=dashboard';</script>";
    exit;
}
if (!function_exists('mms_is_dev_feature_enabled') || !mms_is_dev_feature_enabled('purch_vendor_rating')) {
    echo "<script>alert('Modul Vendor Rating belum diaktifkan.'); window.location='index.php?page=dashboard';</script>";
    exit;
}

$action = trim((string)($_GET['action'] ?? 'index'));
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$theme_q = !empty($_GET['theme']) ? '&theme=' . urlencode((string)$_GET['theme']) : '';

if ($action === 'print') {
    require_once __DIR__ . '/print.php';
    return;
}

if (!function_exists('purch_vr_ensure_schema')) {
    function purch_vr_ensure_schema($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS vendor_ratings (
            id INT NOT NULL AUTO_INCREMENT,
            supplier_id INT NOT NULL,
            rating_period CHAR(7) NOT NULL,
            lead_time_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            quality_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            price_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            total_score DECIMAL(5,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_vendor_period (supplier_id, rating_period),
            KEY idx_rating_period (rating_period),
            KEY idx_supplier (supplier_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('purch_vr_redirect')) {
    function purch_vr_redirect($msg, $type = 'success', $extra = []) {
        $params = [
            'page' => 'purch-vendor-rating',
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

if (!function_exists('purch_vr_grade')) {
    function purch_vr_grade($score) {
        $score = (float)$score;
        if ($score >= 85) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 55) return 'C';
        return 'D';
    }
}

try {
    purch_vr_ensure_schema($pdo);
} catch (Exception $e) {
    render_header("Vendor Rating");
    echo "<div class='alert alert-danger m-3'>Gagal menyiapkan tabel Vendor Rating.</div>";
    render_footer();
    exit;
}

if ($action === 'delete' && isset($_GET['id'])) {
    if (!has_permission('purch_vendor_manage')) {
        purch_vr_redirect('Akses ditolak.', 'danger');
    }
    $csrf_req = $_GET['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        purch_vr_redirect('Permintaan tidak valid (CSRF).', 'danger');
    }
    $id = (int)$_GET['id'];
    if ($id > 0) {
        $stmt_del = $pdo->prepare("DELETE FROM vendor_ratings WHERE id = ?");
        $stmt_del->execute([$id]);
    }
    purch_vr_redirect('Data rating berhasil dihapus.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        purch_vr_redirect('Permintaan tidak valid (CSRF).', 'danger');
    }
    if (!has_permission('purch_vendor_manage')) {
        purch_vr_redirect('Akses ditolak.', 'danger');
    }

    $form_action = trim((string)($_POST['form_action'] ?? ''));
    if ($form_action === 'save_rating') {
        $id = (int)($_POST['id'] ?? 0);
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $rating_period = trim((string)($_POST['rating_period'] ?? date('Y-m')));
        $lead = (float)($_POST['lead_time_score'] ?? 0);
        $quality = (float)($_POST['quality_score'] ?? 0);
        $price = (float)($_POST['price_score'] ?? 0);
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($supplier_id <= 0) {
            purch_vr_redirect('Supplier wajib dipilih.', 'danger', ['action' => $id > 0 ? 'edit' : 'create', 'id' => $id]);
        }
        if (!preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $rating_period)) {
            purch_vr_redirect('Periode rating harus format YYYY-MM.', 'danger', ['action' => $id > 0 ? 'edit' : 'create', 'id' => $id]);
        }

        $lead = max(0.0, min(100.0, $lead));
        $quality = max(0.0, min(100.0, $quality));
        $price = max(0.0, min(100.0, $price));
        $total = round(($lead + $quality + $price) / 3, 2);

        try {
            $pdo->beginTransaction();

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE vendor_ratings
                                       SET supplier_id = ?, rating_period = ?, lead_time_score = ?, quality_score = ?, price_score = ?, total_score = ?, notes = ?
                                       WHERE id = ?");
                $stmt->execute([$supplier_id, $rating_period, $lead, $quality, $price, $total, $notes, $id]);
            } else {
                $stmt_existing = $pdo->prepare("SELECT id FROM vendor_ratings WHERE supplier_id = ? AND rating_period = ? LIMIT 1");
                $stmt_existing->execute([$supplier_id, $rating_period]);
                $existing_id = (int)($stmt_existing->fetchColumn() ?: 0);
                if ($existing_id > 0) {
                    $stmt = $pdo->prepare("UPDATE vendor_ratings
                                           SET lead_time_score = ?, quality_score = ?, price_score = ?, total_score = ?, notes = ?
                                           WHERE id = ?");
                    $stmt->execute([$lead, $quality, $price, $total, $notes, $existing_id]);
                    $id = $existing_id;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO vendor_ratings
                        (supplier_id, rating_period, lead_time_score, quality_score, price_score, total_score, notes, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$supplier_id, $rating_period, $lead, $quality, $price, $total, $notes, (int)($_SESSION['user_id'] ?? 0)]);
                    $id = (int)$pdo->lastInsertId();
                }
            }

            $pdo->commit();
            purch_vr_redirect('Vendor rating berhasil disimpan.', 'success', ['period' => $rating_period]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            purch_vr_redirect('Gagal menyimpan rating vendor.', 'danger');
        }
    }
}

$suppliers = $pdo->query("SELECT id, code, name FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

render_header("Vendor Rating");
?>

<div class="row mb-3">
    <div class="col-md-8">
        <h3 class="fw-bold"><i class="bi bi-star-half"></i> Vendor Rating</h3>
        <p class="text-muted mb-0">Penilaian vendor berdasarkan <strong>lead time</strong>, <strong>kualitas</strong>, dan <strong>harga</strong>.</p>
    </div>
    <?php if (has_permission('purch_vendor_manage')): ?>
        <div class="col-md-4 text-end">
            <a href="index.php?page=purch-vendor-rating&action=create<?= $theme_q ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Input Rating
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-<?= $esc($_GET['msg_type'] ?? 'info') ?> py-2"><?= $esc($_GET['msg']) ?></div>
<?php endif; ?>

<?php if ($action === 'create' || ($action === 'edit' && (int)($_GET['id'] ?? 0) > 0)): ?>
    <?php
        $id = (int)($_GET['id'] ?? 0);
        $form = [
            'id' => 0,
            'supplier_id' => '',
            'rating_period' => date('Y-m'),
            'lead_time_score' => 0,
            'quality_score' => 0,
            'price_score' => 0,
            'notes' => '',
        ];

        if ($id > 0) {
            $stmt_edit = $pdo->prepare("SELECT * FROM vendor_ratings WHERE id = ? LIMIT 1");
            $stmt_edit->execute([$id]);
            $row_edit = $stmt_edit->fetch(PDO::FETCH_ASSOC);
            if ($row_edit) {
                $form = $row_edit;
            }
        }
        $total_preview = round(((float)$form['lead_time_score'] + (float)$form['quality_score'] + (float)$form['price_score']) / 3, 2);
    ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light fw-bold"><?= $action === 'create' ? 'Input Vendor Rating' : 'Edit Vendor Rating' ?></div>
        <div class="card-body">
            <?php if (!has_permission('purch_vendor_manage')): ?>
                <div class="alert alert-warning mb-0">Anda tidak memiliki akses untuk input/edit rating.</div>
            <?php elseif (empty($suppliers)): ?>
                <div class="alert alert-warning mb-0">Master supplier kosong. Tambahkan vendor terlebih dulu di menu Vendor List.</div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
                    <input type="hidden" name="form_action" value="save_rating">
                    <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="form-label">Vendor</label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">-- Pilih Vendor --</option>
                                <?php foreach ($suppliers as $sp): ?>
                                    <option value="<?= (int)$sp['id'] ?>" <?= (int)$sp['id'] === (int)$form['supplier_id'] ? 'selected' : '' ?>>
                                        <?= $esc($sp['code']) ?> - <?= $esc($sp['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Periode</label>
                            <input type="month" name="rating_period" class="form-control" value="<?= $esc($form['rating_period']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Total Skor</label>
                            <input type="text" id="total_score_preview" class="form-control fw-bold text-primary" value="<?= number_format($total_preview, 2, '.', '') ?>" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Lead Time (0-100)</label>
                            <input type="number" min="0" max="100" step="0.01" name="lead_time_score" id="lead_time_score" class="form-control score-input" value="<?= $esc($form['lead_time_score']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Kualitas (0-100)</label>
                            <input type="number" min="0" max="100" step="0.01" name="quality_score" id="quality_score" class="form-control score-input" value="<?= $esc($form['quality_score']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Harga (0-100)</label>
                            <input type="number" min="0" max="100" step="0.01" name="price_score" id="price_score" class="form-control score-input" value="<?= $esc($form['price_score']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Catatan Evaluasi</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Keterangan evaluasi vendor"><?= $esc($form['notes']) ?></textarea>
                    </div>

                    <div class="text-end">
                        <a href="index.php?page=purch-vendor-rating<?= $theme_q ?>" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan Rating</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function () {
        const totalEl = document.getElementById('total_score_preview');
        const inputs = document.querySelectorAll('.score-input');
        if (!totalEl || !inputs.length) return;

        const recalc = () => {
            let sum = 0;
            let count = 0;
            inputs.forEach((el) => {
                const v = parseFloat(el.value || '0');
                if (!Number.isNaN(v)) {
                    sum += v;
                    count++;
                }
            });
            const avg = count > 0 ? (sum / count) : 0;
            totalEl.value = avg.toFixed(2);
        };

        inputs.forEach((el) => el.addEventListener('input', recalc));
    })();
    </script>
<?php endif; ?>

<?php
    $period_filter = trim((string)($_GET['period'] ?? date('Y-m')));
    $supplier_filter = (int)($_GET['supplier_id'] ?? 0);

    $sql = "SELECT vr.*, s.code AS supplier_code, s.name AS supplier_name
            FROM vendor_ratings vr
            JOIN suppliers s ON s.id = vr.supplier_id
            WHERE 1=1";
    $params = [];
    if ($period_filter !== '') {
        $sql .= " AND vr.rating_period = ?";
        $params[] = $period_filter;
    }
    if ($supplier_filter > 0) {
        $sql .= " AND vr.supplier_id = ?";
        $params[] = $supplier_filter;
    }
    $sql .= " ORDER BY vr.rating_period DESC, vr.total_score DESC, s.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $avg_score = 0.0;
    if (!empty($rows)) {
        $avg_score = array_sum(array_map(static fn($r) => (float)$r['total_score'], $rows)) / count($rows);
    }
?>

<div class="card shadow-sm mb-3 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="page" value="purch-vendor-rating">
            <?php if (!empty($_GET['theme'])): ?><input type="hidden" name="theme" value="<?= $esc($_GET['theme']) ?>"><?php endif; ?>
            <div class="col-md-3">
                <input type="month" name="period" class="form-control" value="<?= $esc($period_filter) ?>">
            </div>
            <div class="col-md-5">
                <select name="supplier_id" class="form-select">
                    <option value="">Semua Vendor</option>
                    <?php foreach ($suppliers as $sp): ?>
                        <option value="<?= (int)$sp['id'] ?>" <?= (int)$sp['id'] === $supplier_filter ? 'selected' : '' ?>>
                            <?= $esc($sp['code']) ?> - <?= $esc($sp['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-2">
                <a href="index.php?page=purch-vendor-rating<?= $theme_q ?>" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 bg-light">
            <div class="card-body">
                <div class="small text-muted">Jumlah Penilaian</div>
                <div class="h4 mb-0"><?= count($rows) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 bg-light">
            <div class="card-body">
                <div class="small text-muted">Rata-rata Skor</div>
                <div class="h4 mb-0"><?= number_format($avg_score, 2, ',', '.') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4 text-md-end mt-2 mt-md-0">
        <a href="index.php?page=purch-vendor-rating&action=print&period=<?= urlencode($period_filter) ?>&supplier_id=<?= (int)$supplier_filter ?><?= $theme_q ?>" target="_blank" class="btn btn-outline-dark">
            <i class="bi bi-printer"></i> Print
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Periode</th>
                        <th>Vendor</th>
                        <th class="text-end">Lead Time</th>
                        <th class="text-end">Kualitas</th>
                        <th class="text-end">Harga</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Grade</th>
                        <th>Catatan</th>
                        <?php if (has_permission('purch_vendor_manage')): ?><th class="text-center">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="<?= has_permission('purch_vendor_manage') ? '9' : '8' ?>" class="text-center text-muted py-4">Belum ada data vendor rating.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <?php
                            $grade = purch_vr_grade($r['total_score']);
                            $badge = match($grade) {
                                'A' => 'bg-success',
                                'B' => 'bg-primary',
                                'C' => 'bg-warning text-dark',
                                default => 'bg-danger',
                            };
                        ?>
                        <tr>
                            <td><?= $esc($r['rating_period']) ?></td>
                            <td><strong><?= $esc($r['supplier_code']) ?></strong> - <?= $esc($r['supplier_name']) ?></td>
                            <td class="text-end"><?= number_format((float)$r['lead_time_score'], 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format((float)$r['quality_score'], 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format((float)$r['price_score'], 2, ',', '.') ?></td>
                            <td class="text-end fw-bold"><?= number_format((float)$r['total_score'], 2, ',', '.') ?></td>
                            <td class="text-center"><span class="badge <?= $badge ?>"><?= $grade ?></span></td>
                            <td><?= $esc($r['notes'] ?: '-') ?></td>
                            <?php if (has_permission('purch_vendor_manage')): ?>
                                <td class="text-center">
                                    <a href="index.php?page=purch-vendor-rating&action=edit&id=<?= (int)$r['id'] ?><?= $theme_q ?>" class="btn btn-sm btn-warning text-dark">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="index.php?page=purch-vendor-rating&action=delete&id=<?= (int)$r['id'] ?>&csrf=<?= urlencode($csrf) ?><?= $theme_q ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Hapus data rating ini?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php render_footer(); ?>
