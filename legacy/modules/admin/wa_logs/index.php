<?php
// modules/admin/wa_logs/index.php

render_header("WA Logs (Fonte)");

if ($_SESSION['role'] !== 'admin') {
    echo "<div class='alert alert-danger m-4'>Akses ditolak.</div>";
    render_footer();
    exit;
}

$status = trim((string)($_GET['status'] ?? ''));
$date_from = trim((string)($_GET['date_from'] ?? ''));
$date_to = trim((string)($_GET['date_to'] ?? ''));
$limit = (int)($_GET['limit'] ?? 100);
if (!in_array($limit, [50, 100, 200, 500], true)) $limit = 100;

$table_exists = false;
try {
    $table_exists = $pdo->query("SHOW TABLES LIKE 'wa_message_logs'")->rowCount() > 0;
} catch (Exception $e) {
    $table_exists = false;
}

$rows = [];
if ($table_exists) {
    $where = [];
    $params = [];

    if (in_array($status, ['success', 'failed'], true)) {
        $where[] = "w.status = ?";
        $params[] = $status;
    }
    if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $where[] = "DATE(w.created_at) >= ?";
        $params[] = $date_from;
    }
    if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $where[] = "DATE(w.created_at) <= ?";
        $params[] = $date_to;
    }

    $sql = "SELECT w.*, u.fullname AS created_by_name
            FROM wa_message_logs w
            LEFT JOIN users u ON u.id = w.created_by";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $sql .= " ORDER BY w.id DESC LIMIT " . (int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-whatsapp"></i> WA Message Logs</h3>
        <p class="text-muted">Audit pengiriman WhatsApp otomatis (Fonte).</p>
    </div>
</div>

<?php if (!$table_exists): ?>
    <div class="alert alert-warning">
        Tabel log WA belum tersedia. Jalankan migration: <code>database/migrations/20260211_07_wa_message_logs.sql</code>
    </div>
<?php else: ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="page" value="admin-wa-logs">
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        <option value="success" <?= $status === 'success' ? 'selected' : '' ?>>Success</option>
                        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sampai</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Limit</label>
                    <select name="limit" class="form-select">
                        <?php foreach ([50, 100, 200, 500] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu</th>
                            <th>Status</th>
                            <th>Nomor</th>
                            <th>Pesan</th>
                            <th>Media URL</th>
                            <th>Dibuat Oleh</th>
                            <th>Error / Response</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada log WA.</td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i:s', strtotime($r['created_at'])) ?></td>
                                <td>
                                    <?php if ($r['status'] === 'success'): ?>
                                        <span class="badge bg-success">SUCCESS</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">FAILED</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($r['recipient_phone'] ?? '-') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($r['recipient_phone_raw'] ?? '-') ?></small>
                                </td>
                                <td style="max-width: 260px;"><div class="text-truncate" title="<?= htmlspecialchars($r['message_text']) ?>"><?= htmlspecialchars($r['message_text']) ?></div></td>
                                <td style="max-width: 220px;">
                                    <?php if (!empty($r['media_url'])): ?>
                                        <a href="<?= htmlspecialchars($r['media_url']) ?>" target="_blank" class="text-decoration-none">Lihat Link</a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($r['created_by_name'] ?? '-') ?></td>
                                <td style="max-width: 320px;">
                                    <?php if (!empty($r['error_message'])): ?>
                                        <div class="text-danger small"><?= htmlspecialchars($r['error_message']) ?></div>
                                    <?php elseif (!empty($r['provider_response'])): ?>
                                        <div class="small text-muted text-truncate" title="<?= htmlspecialchars($r['provider_response']) ?>"><?= htmlspecialchars($r['provider_response']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
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

