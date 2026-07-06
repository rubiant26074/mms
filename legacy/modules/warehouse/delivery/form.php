<?php
// modules/warehouse/delivery/form.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('whse_sj_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=whse-sj';</script>";
    exit;
}

$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = $id ? true : false;
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

$data = [
    'dn_number' => 'AUTO',
    'dn_date' => date('Y-m-d'),
    'sales_order_id' => '',
    'driver_name' => '',
    'vehicle_number' => '',
    'status' => 'draft',
    'notes' => ''
];
$items = [];

// LOAD DATA EDIT
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM delivery_notes WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    if(!$data) die("Data tidak ditemukan");

    $stmt_items = $pdo->prepare("SELECT dni.*, i.item_name, i.item_code, i.unit, i.current_stock 
                                 FROM delivery_note_items dni 
                                 JOIN items i ON dni.item_id = i.id 
                                 WHERE dni.delivery_note_id = ?");
    $stmt_items->execute([$id]);
    $items = $stmt_items->fetchAll();
}

// PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    } else {
    $so_id = (int)($_POST['sales_order_id'] ?? 0);
    $date = $_POST['dn_date'];
    $driver = $_POST['driver_name'];
    $veh = $_POST['vehicle_number'];
    $notes = $_POST['notes'];
    
    $item_ids = $_POST['item_id'];
    $qty_sents = $_POST['qty_sent'];

    if ($so_id <= 0) {
        $error = "Sales Order tidak valid.";
    } else {
        $stmt_gate = $pdo->prepare(
            "SELECT so.so_number,
                    EXISTS(
                        SELECT 1
                        FROM spk s
                        WHERE s.sales_order_id = so.id
                          AND s.status = 'closed'
                    ) AS spk_closed
             FROM sales_orders so
             WHERE so.id = ?
             LIMIT 1"
        );
        $stmt_gate->execute([$so_id]);
        $so_gate = $stmt_gate->fetch(PDO::FETCH_ASSOC);

        if (!$so_gate) {
            $error = "Sales Order tidak ditemukan.";
        } elseif ((int)($so_gate['spk_closed'] ?? 0) !== 1) {
            $error = "SO {$so_gate['so_number']} belum selesai QC/SPK (status SPK harus CLOSED).";
        }
    }

    try {
        if (isset($error)) {
            throw new Exception($error);
        }

        $pdo->beginTransaction();

        if (!$is_edit) {
            $ym = date('ym');
            $stmt_no = $pdo->query("SELECT COUNT(*) FROM delivery_notes WHERE dn_number LIKE 'DN-$ym-%'");
            $count = $stmt_no->fetchColumn() + 1;
            $dn_number = "DN-" . $ym . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO delivery_notes (dn_number, sales_order_id, dn_date, driver_name, vehicle_number, status, notes, created_by) VALUES (?, ?, ?, ?, ?, 'draft', ?, ?)";
            $pdo->prepare($sql)->execute([$dn_number, $so_id, $date, $driver, $veh, $notes, $_SESSION['user_id']]);
            $dn_id = $pdo->lastInsertId();
        } else {
            $sql = "UPDATE delivery_notes SET sales_order_id=?, dn_date=?, driver_name=?, vehicle_number=?, notes=? WHERE id=?";
            $pdo->prepare($sql)->execute([$so_id, $date, $driver, $veh, $notes, $id]);
            $dn_id = $id;
            $pdo->prepare("DELETE FROM delivery_note_items WHERE delivery_note_id=?")->execute([$id]);
        }

        $stmt_ins = $pdo->prepare("INSERT INTO delivery_note_items (delivery_note_id, item_id, qty_sent) VALUES (?, ?, ?)");
        
        for ($i = 0; $i < count($item_ids); $i++) {
            $qty = floatval($qty_sents[$i]);
            if ($qty > 0) {
                $stmt_ins->execute([$dn_id, $item_ids[$i], $qty]);
            }
        }

        $pdo->commit();
        echo "<script>alert('Surat Jalan disimpan (Draft). Silakan Approve untuk memotong stok.'); window.location='index.php?page=whse-sj';</script>";
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error: " . $e->getMessage();
    }
    }
}

// LOAD LIST SO yang masih punya sisa qty kirim.
// Tetap tampilkan SO yang sedang dipilih saat edit agar form tetap konsisten.
$selected_so_id = (int)($data['sales_order_id'] ?? 0);
$sql_so = "SELECT 
                so.id, 
                so.so_number, 
                c.name as cust_name,
                SUM(
                    GREATEST(
                        (soi.qty - COALESCE(sent.sent_qty, 0)),
                        0
                    )
                ) AS remaining_qty
           FROM sales_orders so 
           JOIN customers c ON so.customer_id = c.id
           JOIN sales_order_items soi ON soi.sales_order_id = so.id
           LEFT JOIN (
                SELECT 
                    dn.sales_order_id, 
                    dni.item_id, 
                    SUM(dni.qty_sent) AS sent_qty
                FROM delivery_notes dn
                JOIN delivery_note_items dni ON dni.delivery_note_id = dn.id
                WHERE dn.status IN ('approved', 'sent')
                GROUP BY dn.sales_order_id, dni.item_id
           ) sent ON sent.sales_order_id = so.id AND sent.item_id = soi.item_id
           WHERE (
                (
                    so.status IN ('confirmed', 'in_production', 'delivered', 'completed')
                    AND EXISTS (
                        SELECT 1
                        FROM spk s
                        WHERE s.sales_order_id = so.id
                          AND s.status = 'closed'
                    )
                )
                OR so.id = ?
           )
           GROUP BY so.id, so.so_number, c.name
           HAVING (remaining_qty > 0 OR so.id = ?)
           ORDER BY so.id DESC";
$stmt_so = $pdo->prepare($sql_so);
$stmt_so->execute([$selected_so_id, $selected_so_id]);
$sales_orders = $stmt_so->fetchAll();

render_header($is_edit ? "Edit Surat Jalan" : "Buat Surat Jalan");
?>

<form method="POST">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">Info Pengiriman</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>No. SJ</label>
                        <input type="text" class="form-control fw-bold" value="<?= $data['dn_number'] ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Referensi SO <span class="text-danger">*</span></label>
                        <select name="sales_order_id" id="soSelect" class="form-select" required onchange="loadSOItems(this.value)">
                            <option value="">-- Pilih Sales Order --</option>
                            <?php foreach($sales_orders as $so): 
                                $selected = $so['id'] == $data['sales_order_id'] ? 'selected' : '';
                            ?>
                                <option value="<?= $so['id'] ?>" <?= $selected ?>>
                                    <?= $so['so_number'] ?> - <?= $so['cust_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Tanggal Kirim</label>
                        <input type="date" name="dn_date" class="form-control" value="<?= $data['dn_date'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Driver / Kurir</label>
                        <input type="text" name="driver_name" class="form-control" value="<?= $data['driver_name'] ?>">
                    </div>
                    <div class="mb-3">
                        <label>Plat Nomor</label>
                        <input type="text" name="vehicle_number" class="form-control" value="<?= $data['vehicle_number'] ?>">
                    </div>
                    <div class="mb-3">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"><?= $data['notes'] ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">
                    <strong>Barang yang Dikirim</strong>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0 table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Barang</th>
                                <th>Stok Gudang</th>
                                <th>Qty Order (SO)</th>
                                <th width="150">Qty Kirim</th>
                                <th>Satuan</th>
                            </tr>
                        </thead>
                        <tbody id="sjItems">
                            <?php if(!empty($items)): foreach($items as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= $item['item_name'] ?></strong>
                                    <input type="hidden" name="item_id[]" value="<?= $item['item_id'] ?>">
                                </td>
                                <td><?= $item['current_stock'] + 0 ?></td>
                                <td>-</td> <!-- Qty SO tidak disimpan di detail DN, load via ajax kalo mau -->
                                <td>
                                    <input type="number" name="qty_sent[]" class="form-control fw-bold border-success" value="<?= $item['qty_sent'] + 0 ?>" step="0.01" required>
                                </td>
                                <td><?= $item['unit'] ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">Pilih SO untuk memuat barang...</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="text-end">
                <a href="index.php?page=whse-sj" class="btn btn-secondary me-2">Batal</a>
                <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-save"></i> Simpan SJ</button>
            </div>
        </div>
    </div>
</form>

<script>
function loadSOItems(soId) {
    const tbody = document.getElementById('sjItems');
    if(!soId) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted">Pilih SO...</td></tr>';
        return;
    }

    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5"><div class="spinner-border text-primary"></div> Memuat data...</td></tr>';

    fetch('modules/warehouse/delivery/get_so_items.php?so_id=' + soId)
        .then(res => res.json())
        .then(data => {
            let rows = '';
            data.forEach(item => {
                // Warning jika stok kurang
                let stockClass = parseFloat(item.current_stock) < parseFloat(item.qty) ? 'text-danger fw-bold' : 'text-success';
                let maxQty = Math.min(parseFloat(item.current_stock), parseFloat(item.qty));
                
                rows += `
                <tr>
                    <td>
                        <strong>${item.item_name}</strong> <small class="text-muted">(${item.item_code})</small>
                        <input type="hidden" name="item_id[]" value="${item.item_id}">
                    </td>
                    <td class="${stockClass}">${parseFloat(item.current_stock)}</td>
                    <td class="text-center">${parseFloat(item.qty)}</td>
                    <td>
                        <input type="number" name="qty_sent[]" class="form-control fw-bold border-success" value="${maxQty}" step="0.01" min="0" max="${maxQty}" required>
                    </td>
                    <td>${item.unit}</td>
                </tr>`;
            });
            if (!rows) {
                rows = '<tr><td colspan="5" class="text-center py-5 text-muted">Semua item SO sudah terpenuhi pengirimannya.</td></tr>';
            }
            tbody.innerHTML = rows;
        });
}

<?php if(!$is_edit && !empty($data['sales_order_id'])): ?>
window.onload = function() { loadSOItems(<?= $data['sales_order_id'] ?>); };
<?php endif; ?>
</script>

<?php render_footer(); ?>
