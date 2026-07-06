<?php
// modules/warehouse/return/index.php
render_header("Material Return (Pengembalian)");

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

// ACTION: APPROVE (Terima Barang Kembali)
if (isset($_GET['action']) && $_GET['action'] == 'approve' && isset($_GET['id'])) {
    $csrf_req = $_GET['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=whse-return';</script>";
        exit;
    }
    // Cek akses gudang (gunakan permission stok atau khusus return)
    if (!has_permission('whse_stock')) {
        echo "<script>alert('Akses Ditolak! Hanya Gudang yang bisa approve.'); window.location='index.php?page=whse-return';</script>";
        exit;
    }

    try {
        $pdo->beginTransaction();
        $id = $_GET['id'];

        // 1. Update Status Header
        $stmt = $pdo->prepare("UPDATE material_returns SET status='approved', received_by=?, created_at=NOW() WHERE id=? AND status='request'");
        $stmt->execute([$_SESSION['fullname'], $id]);

        if ($stmt->rowCount() > 0) {
            // 2. Proses Stok
            $stmt_items = $pdo->prepare("SELECT type, item_id, qty FROM material_return_items WHERE return_id = ?");
            $stmt_items->execute([$id]);
            $items = $stmt_items->fetchAll();

            $stmt_stock = $pdo->prepare("UPDATE items SET current_stock = current_stock + ? WHERE id = ?");

            foreach ($items as $item) {
                // Hanya barang UTUH (Intact) yang menambah stok Master Barang
                // Barang Sisa (Waste) hanya dicatat di histori, tidak masuk stok aktif (kecuali punya item khusus scrap)
                if ($item['type'] == 'intact' && !empty($item['item_id'])) {
                    $stmt_stock->execute([$item['qty'], $item['item_id']]);
                }
            }

            if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'whse.return.approve.' . (int)$id,
                    'Pengembalian Material Diterima',
                    "Pengembalian material #$id telah diterima gudang.",
                    "index.php?page=ppic-inventory",
                    'success',
                    ['permission_slug' => 'ppic_view']
                );
            }

            $pdo->commit();
            echo "<script>alert('Pengembalian diterima! Stok material UTUH telah ditambahkan kembali.'); window.location='index.php?page=whse-return';</script>";
        } else {
            $pdo->rollBack();
            echo "<script>alert('Gagal Approve. Status mungkin sudah berubah.');</script>";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Error: ".$e->getMessage()."');</script>";
    }
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-arrow-return-left"></i> Material Return</h3>
        <p class="text-muted">Pengembalian sisa material produksi ke Gudang.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=whse-return&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Buat Pengembalian
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="ret-filter-form">
            <input type="hidden" name="page" value="whse-return">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari No. Retur / SPK / Pengembali / Penerima..." value="<?= $search_key ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="request" <?= $filter_status=='request'?'selected':'' ?>>Request</option>
                    <option value="approved" <?= $filter_status=='approved'?'selected':'' ?>>Approved</option>
                    <option value="rejected" <?= $filter_status=='rejected'?'selected':'' ?>>Rejected</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=whse-return" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>No. Retur</th>
                        <th>Tanggal</th>
                        <th>Ref. SPK</th>
                        <th>Dikembalikan Oleh</th>
                        <th>Diterima Gudang</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT mr.*, s.spk_number 
                            FROM material_returns mr
                            JOIN spk s ON mr.spk_id = s.id
                            WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND mr.status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (mr.ret_number LIKE ? OR s.spk_number LIKE ? OR mr.returned_by LIKE ? OR mr.received_by LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY mr.id DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    while ($row = $stmt->fetch()):
                        $badge = match($row['status']) {
                            'request' => 'bg-warning text-dark',
                            'approved' => 'bg-success',
                            'rejected' => 'bg-danger',
                            default => 'bg-secondary'
                        };
                    ?>
                    <tr>
                        <td><strong><?= clean($row['ret_number']) ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($row['ret_date'])) ?></td>
                        <td><?= clean($row['spk_number']) ?></td>
                        <td><?= clean($row['returned_by']) ?></td>
                        <td><?= clean($row['received_by']) ?></td>
                        <td><span class="badge <?= $badge ?>"><?= strtoupper($row['status']) ?></span></td>
                        <td>
                            <div class="btn-group">
                                <!-- Approve Button (Gudang Only) -->
                                <?php if($row['status'] == 'request' && has_permission('whse_stock')): ?>
                                    <a href="index.php?page=whse-return&action=approve&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-success fw-bold" onclick="return confirm('Terima barang ini dan update stok?')">
                                        <i class="bi bi-check-lg"></i> Terima
                                    </a>
                                <?php endif; ?>
                                
                                <!-- Delete (Draft Only) -->
                                <?php if($row['status'] == 'request'): ?>
                                    <a href="#" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('ret-filter-form');
    if (!form) return;

    const search = form.querySelector('input[name="search"]');
    const status = form.querySelector('select[name="status"]');
    let t;

    const submit = () => {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    };

    if (search) {
        search.addEventListener('input', () => {
            clearTimeout(t);
            t = setTimeout(submit, 400);
        });
    }
    if (status) {
        status.addEventListener('change', submit);
    }
})();
</script>

<?php render_footer(); ?>
