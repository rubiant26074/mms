<?php
// modules/engineering/boms/index.php
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

// 1. Ambil Notifikasi Request BOM dari PPIC yang belum dibaca
// PERBAIKAN: Mengganti u.full_name menjadi u.fullname sesuai struktur database Anda
try {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $role = strtolower((string)($_SESSION['role'] ?? ($_SESSION['role_name'] ?? '')));
    $stmt_req = $pdo->prepare("
        SELECT n.*, u.fullname as sender_name 
        FROM notifications n
        JOIN users u ON n.sender_id = u.id
        WHERE n.is_read = 0
          AND (
                n.user_id = :uid
                OR LOWER(n.target_role) = :role
              )
        ORDER BY n.created_at DESC
    ");
    $stmt_req->execute(['uid' => $uid, 'role' => $role]);
    $pending_requests = $stmt_req->fetchAll();
} catch (PDOException $e) {
    // Failsafe jika tabel atau kolom belum sinkron
    $pending_requests = [];
    error_log("BOM Index Error: " . $e->getMessage());
}

render_header("Bill of Materials (BOM)");

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';
?>

<?php if (count($pending_requests) > 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm border-start border-5 border-warning">
            <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-megaphone-fill me-2"></i> PERMINTAAN BOM DARI PPIC</span>
                <span class="badge bg-dark rounded-pill"><?= count($pending_requests) ?> Pending</span>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($pending_requests as $req): ?>
                <?php
                    $req_link_raw = (string)($req['link'] ?? '');
                    $req_link = (strpos($req_link_raw, 'index.php?') === 0) ? $req_link_raw : 'index.php?page=eng-bom';
                ?>
                <div class="list-group-item d-flex justify-content-between align-items-center py-3 bg-light">
                    <div>
                        <div class="fw-bold text-primary mb-1"><?= htmlspecialchars($req['message']) ?></div>
                        <small class="text-muted">
                            <i class="bi bi-person-circle"></i> Oleh: <?= htmlspecialchars($req['sender_name']) ?> 
                            <span class="mx-2">|</span> 
                            <i class="bi bi-clock"></i> <?= date('d M Y, H:i', strtotime($req['created_at'])) ?>
                        </small>
                    </div>
                    <div>
                        <a href="<?= clean($req_link) ?>&notif_id=<?= (int)$req['id'] ?>" class="btn btn-sm btn-primary px-3 shadow-sm">
                            <i class="bi bi-hammer me-1"></i> Kerjakan Sekarang
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-diagram-3"></i> Bill of Materials</h3>
        <p class="text-muted">Komposisi bahan baku untuk produksi (Resep).</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=eng-bom&action=create" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-lg"></i> Buat BOM Baru
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="bom-filter-form">
            <input type="hidden" name="page" value="eng-bom">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari Kode BOM / Item / Kode Item..." value="<?= $search_key ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="active" <?= $filter_status=='active'?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= $filter_status=='inactive'?'selected':'' ?>>Inactive</option>
                    <option value="locked" <?= $filter_status=='locked'?'selected':'' ?>>Locked</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=eng-bom" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Kode BOM</th>
                        <th>Barang Jadi (Finish Good)</th>
                        <th>Output</th>
                        <th>Jml Komponen</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Query untuk mengambil data BOM beserta nama barang jadi
                    $sql = "SELECT b.*, i.item_name, i.item_code, i.unit,
                            (SELECT COUNT(*) FROM bom_details WHERE bom_id = b.id) as total_components
                            FROM boms b 
                            JOIN items i ON b.item_id = i.id 
                            WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND b.status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (b.bom_code LIKE ? OR i.item_name LIKE ? OR i.item_code LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY b.id DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    while ($row = $stmt->fetch()):
                        $badge = match($row['status']) {
                            'active' => 'bg-success',
                            'inactive' => 'bg-secondary',
                            'locked' => 'bg-danger', 
                            default => 'bg-light text-dark border'
                        };
                    ?>
                    <tr>
                        <td><strong><?= clean($row['bom_code']) ?></strong></td>
                        <td>
                            <div class="fw-bold text-dark"><?= clean($row['item_name']) ?></div>
                            <small class="text-muted"><?= clean($row['item_code']) ?></small>
                        </td>
                        <td><?= $row['qty_result'] + 0 ?> <span class="small text-muted"><?= clean($row['unit']) ?></span></td>
                        <td><span class="badge bg-info text-dark px-2"><?= $row['total_components'] ?> Items</span></td>
                        <td><span class="badge <?= $badge ?>"><?= strtoupper($row['status']) ?></span></td>
                        <td class="text-center">
                            <a href="index.php?page=eng-bom&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning text-white shadow-sm" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            
                            <?php if($row['status'] != 'locked'): ?>
                                <a href="index.php?page=eng-bom&action=delete&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Hapus BOM ini?')" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </a>
                            <?php endif; ?>
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
    const form = document.getElementById('bom-filter-form');
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
