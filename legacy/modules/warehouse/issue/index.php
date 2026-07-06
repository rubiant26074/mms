<?php
// modules/warehouse/issue/index.php
if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('whse_stock')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=whse-issue';</script>";
    exit;
}

render_header("Material Issue (ITR)");

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

// ACTION: APPROVE & TRANSFER (Gudang Only)
if (isset($_GET['action']) && $_GET['action'] == 'approve' && isset($_GET['id'])) {
    $csrf_req = $_GET['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=whse-issue';</script>";
        exit;
    }
    if (!has_permission('whse_stock')) {
        echo "<script>alert('Akses Ditolak! Hanya Gudang yang bisa approve.'); window.location='index.php?page=whse-issue';</script>";
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        $id = (int)$_GET['id'];
        if ($id <= 0) {
            throw new RuntimeException('invalid_id');
        }

        // 1. Update Status ITR -> approved, isi issued_by dengan user login (Gudang)
        $stmt = $pdo->prepare("UPDATE material_issues SET status='approved', issued_by=?, created_at=NOW() WHERE id=? AND status='request'");
        $stmt->execute([$_SESSION['fullname'], $id]);

        if ($stmt->rowCount() > 0) {
            // 2. POTONG STOK MASTER BARANG (Poin 6.2)
            $stmt_items = $pdo->prepare("SELECT item_id, qty_issued FROM material_issue_items WHERE material_issue_id = ?");
            $stmt_items->execute([$id]);
            $items = $stmt_items->fetchAll();

            $stmt_stock = $pdo->prepare("UPDATE items SET current_stock = current_stock - ? WHERE id = ?");
            
            // Siapkan cek stok menipis
            $stmt_chk_stock = $pdo->prepare("SELECT item_name, current_stock, min_stock, unit FROM items WHERE id = ?");

            foreach ($items as $item) {
                // Potong Stok
                $stmt_stock->execute([$item['qty_issued'], $item['item_id']]);
                
                // Cek Stok Menipis & Notif ke PPIC
                $stmt_chk_stock->execute([$item['item_id']]);
                $itm_data = $stmt_chk_stock->fetch();
                if ($itm_data && $itm_data['current_stock'] <= $itm_data['min_stock']) {
                     if (function_exists('notify_workflow_event')) {
                        notify_workflow_event(
                            'whse.itr.low_stock.' . (int)$item['item_id'],
                            'Stok Menipis',
                            "Stok ".$itm_data['item_name']." menipis (" . ($itm_data['current_stock']+0) . " " . $itm_data['unit'] . ").",
                            'index.php?page=ppic-inventory',
                            'danger',
                            ['permission_slug' => 'ppic_pr_manage', 'ttl_seconds' => 900]
                        );
                     }
                }
            }

            // 3. Notifikasi ke Produksi (Bahwa barang sudah ready/ditransfer)
            if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'whse.itr.approve.' . (int)$id,
                    'Material Ready',
                    "ITR telah disetujui Gudang. Material siap diambil dan Produksi bisa dimulai.",
                    'index.php?page=prod-task',
                    'success',
                    ['permission_slug' => 'prod_view']
                );
            }

            $pdo->commit();
            echo "<script>alert('Permintaan disetujui! Stok fisik telah dipotong.'); window.location='index.php?page=whse-issue';</script>";
        } else {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "<script>alert('Gagal Approve. Status mungkin sudah berubah.'); window.location='index.php?page=whse-issue';</script>";
        }
	    } catch (Exception $e) {
	        if ($pdo->inTransaction()) {
	            $pdo->rollBack();
	        }
	        echo "<script>alert('Terjadi kesalahan saat approve ITR.'); window.location='index.php?page=whse-issue';</script>";
	    }
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-box-arrow-right"></i> Material Issue (ITR)</h3>
        <p class="text-muted">Request Produksi & Transfer Gudang.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=whse-issue&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Ajukan ITR Baru
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="itr-filter-form">
            <input type="hidden" name="page" value="whse-issue">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari No. ITR / SPK / Pemohon / Petugas..." value="<?= $search_key ?>" autocomplete="off">
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
                <a href="index.php?page=whse-issue" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
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
                        <th>No. ITR</th>
                        <th>Tgl Request</th>
                        <th>Ref. SPK</th>
                        <th>Pemohon (Prod)</th>
                        <th>Petugas Gudang</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT mi.*, s.spk_number, s.project_name
                            FROM material_issues mi
                            JOIN spk s ON mi.spk_id = s.id
                            WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND mi.status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (mi.itr_number LIKE ? OR s.spk_number LIKE ? OR mi.received_by LIKE ? OR mi.issued_by LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY mi.id DESC";
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
                        <td><strong><?= clean($row['itr_number']) ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($row['itr_date'])) ?></td>
                        <td>
                            <span class="badge bg-light text-dark border"><?= clean($row['spk_number']) ?></span>
                        </td>
                        <td><?= clean($row['received_by']) ?></td>
                        <td><?= clean($row['issued_by']) ?></td>
                        <td><span class="badge <?= $badge ?>"><?= strtoupper($row['status']) ?></span></td>
                        <td>
                            <div class="btn-group">
                                <a href="index.php?page=whse-issue&action=print&id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Cetak"><i class="bi bi-printer"></i></a>
                                
                                <!-- Tombol Approve (Hanya untuk Gudang & Status Request) -->
                                <?php if($row['status'] == 'request' && has_permission('whse_stock')): ?>
                                    <a href="index.php?page=whse-issue&action=approve&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-success fw-bold" onclick="return confirm('Setujui permintaan dan potong stok fisik?')">
                                        <i class="bi bi-check-lg"></i> Transfer
                                    </a>
                                <?php endif; ?>

                                <!-- Delete hanya jika masih request -->
                                <?php if($row['status'] == 'request'): ?>
                                    <a href="index.php?page=whse-issue&action=delete&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Batalkan request ini?')"><i class="bi bi-trash"></i></a>
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
    const form = document.getElementById('itr-filter-form');
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
