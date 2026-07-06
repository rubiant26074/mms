<?php
if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('purch_po')) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=dashboard';</script>";
    exit;
}
if (!function_exists('mms_is_dev_feature_enabled') || !mms_is_dev_feature_enabled('purch_rfq')) {
    echo "<script>alert('Modul RFQ belum diaktifkan.'); window.location='index.php?page=dashboard';</script>";
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

if (!function_exists('purch_rfq_ensure_schema')) {
    function purch_rfq_ensure_schema($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_rfqs (
            id INT NOT NULL AUTO_INCREMENT,
            rfq_number VARCHAR(40) NOT NULL,
            rfq_date DATE NOT NULL,
            due_date DATE NULL,
            status ENUM('draft','sent','evaluated','closed','cancelled') NOT NULL DEFAULT 'draft',
            notes TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_rfq_number (rfq_number),
            KEY idx_rfq_date (rfq_date),
            KEY idx_rfq_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_rfq_quotes (
            id INT NOT NULL AUTO_INCREMENT,
            rfq_id INT NOT NULL,
            item_id INT NULL,
            item_name VARCHAR(200) NOT NULL,
            specification TEXT NULL,
            qty DECIMAL(10,4) NOT NULL DEFAULT 0,
            unit VARCHAR(30) NOT NULL DEFAULT '',
            supplier_id INT NOT NULL,
            unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
            lead_time_days INT NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_rfq (rfq_id),
            KEY idx_supplier (supplier_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
if (!function_exists('purch_rfq_redirect')) {
    function purch_rfq_redirect($msg, $type = 'success', $extra = []) {
        $params = ['page' => 'purch-rfq', 'msg' => (string)$msg, 'msg_type' => (string)$type];
        if (!empty($_GET['theme'])) $params['theme'] = trim((string)$_GET['theme']);
        foreach ((array)$extra as $k => $v) $params[$k] = $v;
        header('Location: index.php?' . http_build_query($params));
        exit;
    }
}
if (!function_exists('purch_rfq_generate_number')) {
    function purch_rfq_generate_number($pdo, $rfq_date) {
        $ym = date('ym', strtotime($rfq_date ?: date('Y-m-d')));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_rfqs WHERE rfq_number LIKE ?");
        $stmt->execute(["RFQ-$ym-%"]);
        return "RFQ-$ym-" . str_pad((string)((int)$stmt->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
    }
}
if (!function_exists('purch_rfq_status_badge')) {
    function purch_rfq_status_badge($status) {
        return match((string)$status) {
            'draft' => 'bg-secondary',
            'sent' => 'bg-info text-dark',
            'evaluated' => 'bg-primary',
            'closed' => 'bg-success',
            'cancelled' => 'bg-danger',
            default => 'bg-light text-dark',
        };
    }
}
if (!function_exists('purch_rfq_generate_po_number')) {
    function purch_rfq_generate_po_number($pdo, $po_date) {
        $ym = date('ym', strtotime($po_date ?: date('Y-m-d')));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE po_number LIKE ?");
        $stmt->execute(["PO-$ym-%"]);
        return "PO-$ym-" . str_pad((string)((int)$stmt->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
    }
}

try {
    purch_rfq_ensure_schema($pdo);
} catch (Exception $e) {
    render_header("RFQ");
    echo "<div class='alert alert-danger m-3'>Gagal menyiapkan tabel RFQ.</div>";
    render_footer();
    exit;
}

if ($action === 'delete' && isset($_GET['id'])) {
    if (!has_permission('purch_po_manage')) purch_rfq_redirect('Akses ditolak.', 'danger');
    if (!verify_mms_csrf_token($_GET['csrf'] ?? '')) purch_rfq_redirect('Permintaan tidak valid (CSRF).', 'danger');
    $id = (int)$_GET['id'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM purchase_rfq_quotes WHERE rfq_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM purchase_rfqs WHERE id=?")->execute([$id]);
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        purch_rfq_redirect('Gagal menghapus RFQ.', 'danger');
    }
    purch_rfq_redirect('RFQ berhasil dihapus.');
}

if ($action === 'convert_po' && isset($_GET['id'])) {
    if (!has_permission('purch_po_manage')) purch_rfq_redirect('Akses ditolak.', 'danger');
    if (!verify_mms_csrf_token($_GET['csrf'] ?? '')) purch_rfq_redirect('Permintaan tidak valid (CSRF).', 'danger');

    $rfq_id = (int)($_GET['id'] ?? 0);
    $supplier_id = (int)($_GET['supplier_id'] ?? 0);
    if ($rfq_id <= 0 || $supplier_id <= 0) {
        purch_rfq_redirect('Parameter konversi RFQ tidak valid.', 'danger');
    }

    $stmt_h = $pdo->prepare("SELECT * FROM purchase_rfqs WHERE id = ? LIMIT 1");
    $stmt_h->execute([$rfq_id]);
    $rfq = $stmt_h->fetch(PDO::FETCH_ASSOC);
    if (!$rfq) purch_rfq_redirect('RFQ tidak ditemukan.', 'danger');
    if (in_array((string)$rfq['status'], ['cancelled', 'closed'], true)) {
        purch_rfq_redirect('RFQ berstatus closed/cancelled tidak bisa dikonversi.', 'danger', ['action' => 'view', 'id' => $rfq_id]);
    }

    $stmt_q = $pdo->prepare("SELECT q.*, s.name AS supplier_name
                             FROM purchase_rfq_quotes q
                             JOIN suppliers s ON s.id = q.supplier_id
                             WHERE q.rfq_id = ? AND q.supplier_id = ?
                             ORDER BY q.id ASC");
    $stmt_q->execute([$rfq_id, $supplier_id]);
    $quotes = $stmt_q->fetchAll(PDO::FETCH_ASSOC);
    if (empty($quotes)) {
        purch_rfq_redirect('Quote vendor untuk RFQ ini tidak ditemukan.', 'danger', ['action' => 'view', 'id' => $rfq_id]);
    }

    $supplier_name = trim((string)($quotes[0]['supplier_name'] ?? 'Vendor'));
    $stmt_item = $pdo->prepare("SELECT id FROM items WHERE item_name = ? LIMIT 1");
    $stmt_item_like = $pdo->prepare("SELECT id FROM items WHERE item_name LIKE ? ORDER BY id ASC LIMIT 1");
    $po_lines = [];
    $missing = [];
    foreach ($quotes as $q) {
        $qty = (float)$q['qty'];
        if ($qty <= 0) continue;

        $item_id = (int)($q['item_id'] ?? 0);
        if ($item_id <= 0) {
            $item_name = trim((string)($q['item_name'] ?? ''));
            if ($item_name !== '') {
                $stmt_item->execute([$item_name]);
                $item_id = (int)($stmt_item->fetchColumn() ?: 0);
                if ($item_id <= 0) {
                    $stmt_item_like->execute(['%' . $item_name . '%']);
                    $item_id = (int)($stmt_item_like->fetchColumn() ?: 0);
                }
            }
        }

        if ($item_id <= 0) {
            $missing[] = trim((string)($q['item_name'] ?? 'Item tanpa nama'));
            continue;
        }

        $po_lines[] = [
            'item_id' => $item_id,
            'qty' => $qty,
            'unit_price' => max(0.0, (float)$q['unit_price']),
            'notes' => trim((string)($q['notes'] ?? '')) . (isset($q['lead_time_days']) && $q['lead_time_days'] !== null && $q['lead_time_days'] !== '' ? ' | Lead: ' . (int)$q['lead_time_days'] . ' hari' : ''),
        ];
    }

    if (!empty($missing)) {
        $missing = array_values(array_unique(array_filter($missing)));
        $sample = implode(', ', array_slice($missing, 0, 3));
        $more = count($missing) > 3 ? ' + lainnya' : '';
        purch_rfq_redirect("Konversi gagal. Item belum terdaftar di master: {$sample}{$more}", 'danger', ['action' => 'view', 'id' => $rfq_id]);
    }
    if (empty($po_lines)) {
        purch_rfq_redirect('Tidak ada baris quote valid untuk dikonversi.', 'danger', ['action' => 'view', 'id' => $rfq_id]);
    }

    try {
        $pdo->beginTransaction();

        $po_date = date('Y-m-d');
        $delivery_date = !empty($rfq['due_date']) ? (string)$rfq['due_date'] : date('Y-m-d', strtotime('+7 days'));
        $po_number = purch_rfq_generate_po_number($pdo, $po_date);
        $ppn_percent = 11.0;
        $discount_amount = 0.0;
        $status = 'draft';
        $notes_header = "Auto-convert dari RFQ {$rfq['rfq_number']} (Vendor: {$supplier_name})";
        if (!empty($rfq['notes'])) $notes_header .= "\n" . trim((string)$rfq['notes']);

        $stmt_po = $pdo->prepare("INSERT INTO purchase_orders
            (po_number, purchase_request_id, supplier_id, po_date, delivery_date, payment_terms, ppn_percent, discount_amount, status, notes, created_by)
            VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_po->execute([
            $po_number, $supplier_id, $po_date, $delivery_date, 'Net 30 Days',
            $ppn_percent, $discount_amount, $status, $notes_header, (int)($_SESSION['user_id'] ?? 0)
        ]);
        $po_id = (int)$pdo->lastInsertId();

        $stmt_poi = $pdo->prepare("INSERT INTO purchase_order_items
            (purchase_order_id, item_id, qty, unit_price, subtotal, notes, pr_item_id)
            VALUES (?, ?, ?, ?, ?, ?, NULL)");
        $total_bruto = 0.0;
        foreach ($po_lines as $ln) {
            $sub = (float)$ln['qty'] * (float)$ln['unit_price'];
            $total_bruto += $sub;
            $stmt_poi->execute([$po_id, $ln['item_id'], $ln['qty'], $ln['unit_price'], $sub, $ln['notes']]);
        }

        $dpp = $total_bruto - $discount_amount;
        $tax = $dpp * ($ppn_percent / 100.0);
        $grand = $dpp + $tax;
        $pdo->prepare("UPDATE purchase_orders SET subtotal = ?, tax_amount = ?, grand_total = ? WHERE id = ?")
            ->execute([$total_bruto, $tax, $grand, $po_id]);

        if (in_array((string)$rfq['status'], ['draft', 'sent'], true)) {
            $pdo->prepare("UPDATE purchase_rfqs SET status = 'evaluated' WHERE id = ?")->execute([$rfq_id]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        purch_rfq_redirect('Gagal convert RFQ ke Draft PO.', 'danger', ['action' => 'view', 'id' => $rfq_id]);
    }

    $po_url = 'index.php?page=purch-po&action=edit&id=' . $po_id;
    if (!empty($_GET['theme'])) $po_url .= '&theme=' . urlencode((string)$_GET['theme']);
    header('Location: ' . $po_url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save_rfq') {
    if (!verify_mms_csrf_token($_POST['csrf'] ?? '')) purch_rfq_redirect('Permintaan tidak valid (CSRF).', 'danger');
    if (!has_permission('purch_po_manage')) purch_rfq_redirect('Akses ditolak.', 'danger');

    $id = (int)($_POST['id'] ?? 0);
    $rfq_number = trim((string)($_POST['rfq_number'] ?? ''));
    $rfq_date = trim((string)($_POST['rfq_date'] ?? date('Y-m-d')));
    $due_date = trim((string)($_POST['due_date'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'draft'));
    if (!in_array($status, ['draft', 'sent', 'evaluated', 'closed', 'cancelled'], true)) $status = 'draft';
    if ($rfq_number === '') $rfq_number = purch_rfq_generate_number($pdo, $rfq_date);

    $item_names = $_POST['item_name'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $units = $_POST['unit'] ?? [];
    $supplier_ids = $_POST['supplier_id'] ?? [];
    $prices = $_POST['unit_price'] ?? [];
    $leads = $_POST['lead_time_days'] ?? [];
    $notes = $_POST['line_notes'] ?? [];

    $rows = [];
    $max = max(count($item_names), count($qtys), count($units), count($supplier_ids), count($prices));
    for ($i = 0; $i < $max; $i++) {
        $item_name = trim((string)($item_names[$i] ?? ''));
        $qty = (float)str_replace(',', '.', (string)($qtys[$i] ?? '0'));
        $unit = trim((string)($units[$i] ?? 'Unit'));
        $supplier_id = (int)($supplier_ids[$i] ?? 0);
        $price = (float)str_replace(',', '.', (string)($prices[$i] ?? '0'));
        $lead = trim((string)($leads[$i] ?? ''));
        $note = trim((string)($notes[$i] ?? ''));
        if ($item_name === '' || $qty <= 0 || $supplier_id <= 0) continue;
        $rows[] = [
            'item_name' => $item_name,
            'qty' => $qty,
            'unit' => ($unit !== '' ? $unit : 'Unit'),
            'supplier_id' => $supplier_id,
            'unit_price' => max(0.0, $price),
            'lead_time_days' => ($lead === '' ? null : (int)$lead),
            'notes' => $note,
        ];
    }
    if (empty($rows)) {
        purch_rfq_redirect('Minimal 1 baris quote harus diisi.', 'danger', ['action' => $id > 0 ? 'edit' : 'create', 'id' => $id]);
    }

    try {
        $pdo->beginTransaction();
        if ($id > 0) {
            $pdo->prepare("UPDATE purchase_rfqs SET rfq_number=?, rfq_date=?, due_date=?, status=?, notes=? WHERE id=?")
                ->execute([$rfq_number, $rfq_date, ($due_date !== '' ? $due_date : null), $status, trim((string)($_POST['notes'] ?? '')), $id]);
            $rfq_id = $id;
            $pdo->prepare("DELETE FROM purchase_rfq_quotes WHERE rfq_id=?")->execute([$rfq_id]);
        } else {
            $pdo->prepare("INSERT INTO purchase_rfqs (rfq_number, rfq_date, due_date, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$rfq_number, $rfq_date, ($due_date !== '' ? $due_date : null), $status, trim((string)($_POST['notes'] ?? '')), (int)($_SESSION['user_id'] ?? 0)]);
            $rfq_id = (int)$pdo->lastInsertId();
        }
        $stmt = $pdo->prepare("INSERT INTO purchase_rfq_quotes (rfq_id, item_name, qty, unit, supplier_id, unit_price, lead_time_days, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($rows as $r) {
            $stmt->execute([$rfq_id, $r['item_name'], $r['qty'], $r['unit'], $r['supplier_id'], $r['unit_price'], $r['lead_time_days'], $r['notes']]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        purch_rfq_redirect('Gagal menyimpan RFQ.', 'danger');
    }
    purch_rfq_redirect('RFQ berhasil disimpan.', 'success', ['action' => 'view', 'id' => $rfq_id]);
}

$suppliers = $pdo->query("SELECT id, code, name FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
render_header("RFQ (Request for Quotation)");
?>

<div class="row mb-3">
    <div class="col-md-8">
        <h3 class="fw-bold"><i class="bi bi-clipboard2-data"></i> RFQ (Request for Quotation)</h3>
        <p class="text-muted mb-0">Pembandingan harga vendor sebelum PO.</p>
    </div>
    <?php if (has_permission('purch_po_manage')): ?>
        <div class="col-md-4 text-end">
            <a href="index.php?page=purch-rfq&action=create<?= $theme_q ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Buat RFQ</a>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($_GET['msg'])): ?><div class="alert alert-<?= $esc($_GET['msg_type'] ?? 'info') ?> py-2"><?= $esc($_GET['msg']) ?></div><?php endif; ?>

<?php if ($action === 'create' || ($action === 'edit' && (int)($_GET['id'] ?? 0) > 0)): ?>
<?php
    $id = (int)($_GET['id'] ?? 0);
    $form = ['id' => 0, 'rfq_number' => '', 'rfq_date' => date('Y-m-d'), 'due_date' => '', 'status' => 'draft', 'notes' => ''];
    $lines = [];
    if ($id > 0) {
        $stmt_h = $pdo->prepare("SELECT * FROM purchase_rfqs WHERE id=? LIMIT 1");
        $stmt_h->execute([$id]);
        if ($h = $stmt_h->fetch(PDO::FETCH_ASSOC)) $form = $h;
        $stmt_l = $pdo->prepare("SELECT * FROM purchase_rfq_quotes WHERE rfq_id=? ORDER BY id ASC");
        $stmt_l->execute([$id]);
        $lines = $stmt_l->fetchAll(PDO::FETCH_ASSOC);
    }
?>
<form method="POST">
    <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
    <input type="hidden" name="form_action" value="save_rfq">
    <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2"><label class="form-label">Nomor RFQ</label><input type="text" name="rfq_number" class="form-control" value="<?= $esc($form['rfq_number']) ?>" placeholder="Auto jika kosong"></div>
                <div class="col-md-3 mb-2"><label class="form-label">Tanggal RFQ</label><input type="date" name="rfq_date" class="form-control" value="<?= $esc($form['rfq_date']) ?>" required></div>
                <div class="col-md-3 mb-2"><label class="form-label">Batas Penawaran</label><input type="date" name="due_date" class="form-control" value="<?= $esc($form['due_date']) ?>"></div>
                <div class="col-md-3 mb-2"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach (['draft','sent','evaluated','closed','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $s===$form['status']?'selected':'' ?>><?= strtoupper($s) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="mt-2"><label class="form-label">Catatan</label><textarea name="notes" rows="2" class="form-control"><?= $esc($form['notes']) ?></textarea></div>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light d-flex justify-content-between align-items-center"><strong>Quote Vendor</strong><button type="button" class="btn btn-sm btn-success" id="btnAddRow"><i class="bi bi-plus"></i> Tambah</button></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light"><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Vendor</th><th>Harga</th><th>Lead Time</th><th>Catatan</th><th>Aksi</th></tr></thead>
                    <tbody id="rfqRows">
                        <?php foreach ($lines as $ln): ?>
                            <tr>
                                <td><input type="text" name="item_name[]" class="form-control form-control-sm" value="<?= $esc($ln['item_name']) ?>" required></td>
                                <td><input type="number" step="0.0001" name="qty[]" class="form-control form-control-sm text-end" value="<?= $esc($ln['qty']) ?>" required></td>
                                <td><input type="text" name="unit[]" class="form-control form-control-sm" value="<?= $esc($ln['unit']) ?>"></td>
                                <td><select name="supplier_id[]" class="form-select form-select-sm" required><option value="">-- Vendor --</option><?php foreach ($suppliers as $sp): ?><option value="<?= (int)$sp['id'] ?>" <?= (int)$sp['id']===(int)$ln['supplier_id']?'selected':'' ?>><?= $esc($sp['code']) ?> - <?= $esc($sp['name']) ?></option><?php endforeach; ?></select></td>
                                <td><input type="number" step="0.01" min="0" name="unit_price[]" class="form-control form-control-sm text-end" value="<?= $esc($ln['unit_price']) ?>" required></td>
                                <td><input type="number" step="1" min="0" name="lead_time_days[]" class="form-control form-control-sm text-end" value="<?= $esc($ln['lead_time_days']) ?>"></td>
                                <td><input type="text" name="line_notes[]" class="form-control form-control-sm" value="<?= $esc($ln['notes']) ?>"></td>
                                <td class="text-center"><button type="button" class="btn btn-sm btn-danger btn-rem">x</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-end"><a href="index.php?page=purch-rfq<?= $theme_q ?>" class="btn btn-secondary">Batal</a> <button type="submit" class="btn btn-primary">Simpan</button></div>
    </div>
</form>
<script>
(function () {
    const rows = document.getElementById('rfqRows');
    const addBtn = document.getElementById('btnAddRow');
    if (!rows || !addBtn) return;
    const supplierOpt = `<?php foreach ($suppliers as $sp): ?><option value="<?= (int)$sp['id'] ?>"><?= $esc($sp['code']) ?> - <?= $esc($sp['name']) ?></option><?php endforeach; ?>`;
    const bind = (tr) => tr.querySelector('.btn-rem')?.addEventListener('click', () => tr.remove());
    const add = () => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td><input type="text" name="item_name[]" class="form-control form-control-sm" required></td><td><input type="number" step="0.0001" name="qty[]" class="form-control form-control-sm text-end" required></td><td><input type="text" name="unit[]" class="form-control form-control-sm" value="Unit"></td><td><select name="supplier_id[]" class="form-select form-select-sm" required><option value="">-- Vendor --</option>${supplierOpt}</select></td><td><input type="number" step="0.01" min="0" name="unit_price[]" class="form-control form-control-sm text-end" required></td><td><input type="number" step="1" min="0" name="lead_time_days[]" class="form-control form-control-sm text-end"></td><td><input type="text" name="line_notes[]" class="form-control form-control-sm"></td><td class="text-center"><button type="button" class="btn btn-sm btn-danger btn-rem">x</button></td>`;
        rows.appendChild(tr); bind(tr);
    };
    rows.querySelectorAll('tr').forEach(bind);
    if (!rows.querySelector('tr')) add();
    addBtn.addEventListener('click', add);
})();
</script>

<?php elseif ($action === 'view' && (int)($_GET['id'] ?? 0) > 0): ?>
<?php
    $id = (int)$_GET['id'];
    $stmt_h = $pdo->prepare("SELECT r.*, u.fullname AS created_name FROM purchase_rfqs r LEFT JOIN users u ON u.id=r.created_by WHERE r.id=? LIMIT 1");
    $stmt_h->execute([$id]);
    $head = $stmt_h->fetch(PDO::FETCH_ASSOC);
    if (!$head) {
        echo "<div class='alert alert-warning'>RFQ tidak ditemukan.</div>";
    } else {
        $stmt_l = $pdo->prepare("SELECT q.*, s.code AS supplier_code, s.name AS supplier_name
                                 FROM purchase_rfq_quotes q
                                 JOIN suppliers s ON s.id=q.supplier_id
                                 WHERE q.rfq_id=?
                                 ORDER BY q.item_name ASC, q.unit_price ASC");
        $stmt_l->execute([$id]);
        $lines = $stmt_l->fetchAll(PDO::FETCH_ASSOC);

        $best = [];
        foreach ($lines as $ln) {
            $k = trim((string)$ln['item_name']) . '|' . trim((string)$ln['unit']);
            $p = (float)$ln['unit_price'];
            if (!isset($best[$k]) || $p < $best[$k]) $best[$k] = $p;
        }

        $vendor_summary = [];
        foreach ($lines as $ln) {
            $sid = (int)$ln['supplier_id'];
            if (!isset($vendor_summary[$sid])) {
                $vendor_summary[$sid] = [
                    'supplier_id' => $sid,
                    'supplier_code' => (string)($ln['supplier_code'] ?? ''),
                    'supplier_name' => (string)($ln['supplier_name'] ?? ''),
                    'line_count' => 0,
                    'best_count' => 0,
                    'total' => 0.0,
                ];
            }
            $k = trim((string)$ln['item_name']) . '|' . trim((string)$ln['unit']);
            $is_best = ((float)$ln['unit_price'] <= (float)($best[$k] ?? 0));
            $vendor_summary[$sid]['line_count']++;
            if ($is_best) $vendor_summary[$sid]['best_count']++;
            $vendor_summary[$sid]['total'] += ((float)$ln['qty'] * (float)$ln['unit_price']);
        }
?>
<div class="card shadow-sm mb-3">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <strong><?= $esc($head['rfq_number']) ?></strong>
        <div>
            <a href="index.php?page=purch-rfq&action=print&id=<?= (int)$head['id'] ?><?= $theme_q ?>" target="_blank" class="btn btn-sm btn-outline-dark me-1"><i class="bi bi-printer"></i> Print</a>
            <?php if(has_permission('purch_po_manage')): ?>
                <a href="index.php?page=purch-rfq&action=edit&id=<?= (int)$head['id'] ?><?= $theme_q ?>" class="btn btn-sm btn-warning text-dark me-1"><i class="bi bi-pencil"></i> Edit</a>
            <?php endif; ?>
            <a href="index.php?page=purch-rfq<?= $theme_q ?>" class="btn btn-sm btn-secondary">Kembali</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><small class="text-muted">Tanggal</small><div class="fw-bold"><?= date('d/m/Y', strtotime((string)$head['rfq_date'])) ?></div></div>
            <div class="col-md-3"><small class="text-muted">Due</small><div class="fw-bold"><?= !empty($head['due_date']) ? date('d/m/Y', strtotime((string)$head['due_date'])) : '-' ?></div></div>
            <div class="col-md-3"><small class="text-muted">Status</small><div><span class="badge <?= purch_rfq_status_badge($head['status']) ?>"><?= strtoupper($esc($head['status'])) ?></span></div></div>
            <div class="col-md-3"><small class="text-muted">Created By</small><div class="fw-bold"><?= $esc($head['created_name'] ?: '-') ?></div></div>
        </div>
        <?php if(!empty($head['notes'])): ?>
            <div class="mt-2"><small class="text-muted">Catatan</small><div><?= $esc($head['notes']) ?></div></div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Item</th><th class="text-end">Qty</th><th>Unit</th><th>Vendor</th><th class="text-end">Harga</th><th class="text-end">Lead</th><th class="text-end">Subtotal</th><th>Flag</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($lines)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">Belum ada data quote.</td></tr>
                <?php else: foreach ($lines as $ln):
                    $k=trim((string)$ln['item_name']).'|'.trim((string)$ln['unit']);
                    $is_best=((float)$ln['unit_price'] <= (float)($best[$k] ?? 0));
                    $sub=(float)$ln['qty']*(float)$ln['unit_price'];
                ?>
                    <tr>
                        <td><?= $esc($ln['item_name']) ?></td>
                        <td class="text-end"><?= number_format((float)$ln['qty'], 4, ',', '.') ?></td>
                        <td><?= $esc($ln['unit']) ?></td>
                        <td><?= $esc($ln['supplier_code']) ?> - <?= $esc($ln['supplier_name']) ?></td>
                        <td class="text-end"><?= number_format((float)$ln['unit_price'], 2, ',', '.') ?></td>
                        <td class="text-end"><?= ($ln['lead_time_days'] !== null && $ln['lead_time_days'] !== '') ? (int)$ln['lead_time_days'] . ' hari' : '-' ?></td>
                        <td class="text-end fw-bold"><?= number_format($sub, 2, ',', '.') ?></td>
                        <td><?= $is_best ? '<span class="badge bg-success">Best Price</span>' : '-' ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light fw-bold">Ringkasan Vendor & Konversi ke PO</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Vendor</th>
                        <th class="text-center">Baris Quote</th>
                        <th class="text-center">Best Price Hit</th>
                        <th class="text-end">Estimasi Nilai</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($vendor_summary)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">Belum ada ringkasan vendor.</td></tr>
                <?php else:
                    uasort($vendor_summary, static fn($a, $b) => $a['total'] <=> $b['total']);
                    foreach ($vendor_summary as $vs):
                ?>
                    <tr>
                        <td><?= $esc($vs['supplier_code']) ?> - <?= $esc($vs['supplier_name']) ?></td>
                        <td class="text-center"><?= (int)$vs['line_count'] ?></td>
                        <td class="text-center"><?= (int)$vs['best_count'] ?></td>
                        <td class="text-end fw-bold"><?= number_format((float)$vs['total'], 2, ',', '.') ?></td>
                        <td class="text-center">
                            <?php if (has_permission('purch_po_manage') && !in_array((string)$head['status'], ['closed', 'cancelled'], true)): ?>
                                <a href="index.php?page=purch-rfq&action=convert_po&id=<?= (int)$head['id'] ?>&supplier_id=<?= (int)$vs['supplier_id'] ?>&csrf=<?= urlencode($csrf) ?><?= $theme_q ?>"
                                   class="btn btn-sm btn-success"
                                   onclick="return confirm('Buat Draft PO dari RFQ ini untuk vendor <?= $esc($vs['supplier_name']) ?>?')">
                                    <i class="bi bi-arrow-repeat"></i> Convert ke Draft PO
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<?php else: ?>
<?php
    $status = trim((string)($_GET['status'] ?? ''));
    $search = trim((string)($_GET['search'] ?? ''));
    $sql = "SELECT r.*, COUNT(q.id) AS line_count, COUNT(DISTINCT q.supplier_id) AS vendor_count, COALESCE(SUM(q.qty*q.unit_price),0) AS est_total FROM purchase_rfqs r LEFT JOIN purchase_rfq_quotes q ON q.rfq_id=r.id WHERE 1=1";
    $prm = [];
    if ($status !== '') { $sql .= " AND r.status=?"; $prm[] = $status; }
    if ($search !== '') { $sql .= " AND (r.rfq_number LIKE ? OR r.notes LIKE ?)"; $prm[] = "%$search%"; $prm[] = "%$search%"; }
    $sql .= " GROUP BY r.id ORDER BY r.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($prm);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="card shadow-sm mb-3 border-start border-4 border-primary"><div class="card-body py-3"><form method="GET" class="row g-2 align-items-center"><input type="hidden" name="page" value="purch-rfq"><?php if(!empty($_GET['theme'])): ?><input type="hidden" name="theme" value="<?= $esc($_GET['theme']) ?>"><?php endif; ?><div class="col-md-4"><input type="text" name="search" class="form-control" value="<?= $esc($search) ?>" placeholder="Cari nomor RFQ..."></div><div class="col-md-3"><select name="status" class="form-select"><option value="">Semua Status</option><?php foreach(['draft','sent','evaluated','closed','cancelled'] as $s): ?><option value="<?= $s ?>" <?= $s===$status?'selected':'' ?>><?= strtoupper($s) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div><div class="col-md-2"><a href="index.php?page=purch-rfq<?= $theme_q ?>" class="btn btn-outline-secondary w-100">Reset</a></div></form></div></div>
<div class="card shadow-sm"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead class="table-light"><tr><th>No RFQ</th><th>Tanggal</th><th>Status</th><th class="text-center">Vendor</th><th class="text-center">Baris</th><th class="text-end">Estimasi</th><th class="text-center">Aksi</th></tr></thead><tbody><?php if(empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada RFQ.</td></tr><?php else: foreach($rows as $r): ?><tr><td><strong><?= $esc($r['rfq_number']) ?></strong></td><td><?= date('d/m/Y', strtotime((string)$r['rfq_date'])) ?></td><td><span class="badge <?= purch_rfq_status_badge($r['status']) ?>"><?= strtoupper($esc($r['status'])) ?></span></td><td class="text-center"><?= (int)$r['vendor_count'] ?></td><td class="text-center"><?= (int)$r['line_count'] ?></td><td class="text-end fw-bold"><?= number_format((float)$r['est_total'], 2, ',', '.') ?></td><td class="text-center"><a href="index.php?page=purch-rfq&action=view&id=<?= (int)$r['id'] ?><?= $theme_q ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a> <a href="index.php?page=purch-rfq&action=print&id=<?= (int)$r['id'] ?><?= $theme_q ?>" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer"></i></a> <?php if(has_permission('purch_po_manage')): ?><a href="index.php?page=purch-rfq&action=edit&id=<?= (int)$r['id'] ?><?= $theme_q ?>" class="btn btn-sm btn-warning text-dark"><i class="bi bi-pencil"></i></a> <a href="index.php?page=purch-rfq&action=delete&id=<?= (int)$r['id'] ?>&csrf=<?= urlencode($csrf) ?><?= $theme_q ?>" onclick="return confirm('Hapus RFQ ini?')" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></a><?php endif; ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div></div>
<?php endif; ?>

<?php render_footer(); ?>
