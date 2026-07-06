<?php
// modules/engineering/machines/index.php
render_header("Master Mesin");
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-hdd-rack"></i> Master Mesin</h3>
        <p class="text-muted">Database mesin produksi dan status operasional.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=admin-machines&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Tambah Mesin
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="machine-filter-form">
            <input type="hidden" name="page" value="admin-machines">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari Kode / Nama / Tipe / Lokasi..." value="<?= $search_key ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="active" <?= $filter_status=='active'?'selected':'' ?>>Active</option>
                    <option value="maintenance" <?= $filter_status=='maintenance'?'selected':'' ?>>Maintenance</option>
                    <option value="broken" <?= $filter_status=='broken'?'selected':'' ?>>Broken</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=admin-machines" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
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
                        <th>Kode Mesin</th>
                        <th>Nama Mesin</th>
                        <th>Tipe Proses</th>
                        <th>Lokasi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT * FROM machines WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (machine_code LIKE ? OR machine_name LIKE ? OR process_type LIKE ? OR location LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY machine_code ASC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    while ($row = $stmt->fetch()):
                        $badge = match($row['status']) {
                            'active' => 'bg-success',
                            'maintenance' => 'bg-warning text-dark',
                            'broken' => 'bg-danger',
                            default => 'bg-light'
                        };
                    ?>
                    <tr>
                        <td><strong><?= clean($row['machine_code']) ?></strong></td>
                        <td><?= clean($row['machine_name']) ?></td>
                        <td><span class="badge bg-secondary"><?= clean($row['process_type']) ?></span></td>
                        <td><?= clean($row['location']) ?></td>
                        <td><span class="badge <?= $badge ?>"><?= strtoupper($row['status']) ?></span></td>
                        <td>
                            <a href="index.php?page=admin-machines&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning text-white"><i class="bi bi-pencil"></i></a>
                            <a href="index.php?page=admin-machines&action=delete&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus mesin ini?')"><i class="bi bi-trash"></i></a>
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
    const form = document.getElementById('machine-filter-form');
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
