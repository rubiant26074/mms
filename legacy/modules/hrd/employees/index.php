<?php
// modules/hrd/employees/index.php
render_header("Master Data Karyawan");

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-person-vcard"></i> Data Karyawan</h3>
        <p class="text-muted">Database pegawai, kontak, dan status kepegawaian.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=hrd-employees&action=create" class="btn btn-primary">
            <i class="bi bi-person-plus-fill"></i> Tambah Karyawan
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="emp-filter-form">
            <input type="hidden" name="page" value="hrd-employees">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari NIK / Nama / Departemen / Telp..." value="<?= $search_key ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="permanent" <?= $filter_status=='permanent'?'selected':'' ?>>Permanent</option>
                    <option value="contract" <?= $filter_status=='contract'?'selected':'' ?>>Contract</option>
                    <option value="probation" <?= $filter_status=='probation'?'selected':'' ?>>Probation</option>
                    <option value="resigned" <?= $filter_status=='resigned'?'selected':'' ?>>Resigned</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=hrd-employees" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
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
                        <th>NIK / Nama</th>
                        <th>Departemen / Jabatan</th>
                        <th>Kontak</th>
                        <th>Status</th>
                        <th>Tgl Masuk</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT u.*, r.role_name 
                            FROM users u 
                            LEFT JOIN roles r ON u.role_id = r.id 
                            WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND u.employee_status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (u.nik LIKE ? OR u.fullname LIKE ? OR u.department LIKE ? OR u.phone LIKE ? OR r.role_name LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY u.fullname ASC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    while ($row = $stmt->fetch()):
                        $badge = match($row['employee_status']) {
                            'permanent' => 'bg-success',
                            'contract' => 'bg-info text-dark',
                            'probation' => 'bg-warning text-dark',
                            'resigned' => 'bg-danger',
                            default => 'bg-secondary'
                        };
                    ?>
                    <tr>
                        <td>
                            <strong><?= clean($row['fullname']) ?></strong><br>
                            <small class="text-muted">NIK: <?= clean($row['nik'] ?? '-') ?></small>
                        </td>
                        <td>
                            <div class="fw-bold"><?= clean($row['department'] ?? '-') ?></div>
                            <small class="text-muted"><?= clean($row['role_name']) ?></small>
                        </td>
                        <td>
                            <i class="bi bi-telephone"></i> <?= clean($row['phone'] ?? '-') ?><br>
                            <small class="text-muted"><?= substr(clean($row['address'] ?? '-'), 0, 20) ?>...</small>
                        </td>
                        <td><span class="badge <?= $badge ?>"><?= strtoupper($row['employee_status'] ?? 'Unknown') ?></span></td>
                        <td><?= !empty($row['join_date']) ? date('d/m/Y', strtotime($row['join_date'])) : '-' ?></td>
                        <td class="text-center">
                            <a href="index.php?page=hrd-employees&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning text-dark" title="Edit Data"><i class="bi bi-pencil-square"></i></a>
                            
                            <?php if($row['id'] != $_SESSION['user_id']): // Tidak bisa hapus diri sendiri ?>
                                <a href="index.php?page=hrd-employees&action=delete&id=<?= $row['id'] ?>&csrf=<?= urlencode(mms_csrf_token()) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus data karyawan ini? User login juga akan terhapus.')" title="Hapus"><i class="bi bi-trash"></i></a>
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
    const form = document.getElementById('emp-filter-form');
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
