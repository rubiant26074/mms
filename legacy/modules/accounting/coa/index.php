<?php
// modules/accounting/coa/index.php
$action = isset($_GET['action']) ? clean($_GET['action']) : '';

if ($action === 'reconcile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission('acc_coa_manage')) {
        echo "<script>alert('Akses ditolak.'); window.location='index.php?page=acc-coa';</script>";
        exit;
    }
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=acc-coa';</script>";
        exit;
    }
    try {
        $updated = function_exists('reconcile_coa_current_balances') ? reconcile_coa_current_balances() : 0;
        echo "<script>alert('Rekonsiliasi saldo COA selesai. Akun diperbarui: " . (int)$updated . "'); window.location='index.php?page=acc-coa';</script>";
        exit;
    } catch (Exception $e) {
        echo "<script>alert('Gagal rekonsiliasi: " . clean($e->getMessage()) . "'); window.location='index.php?page=acc-coa';</script>";
        exit;
    }
}

render_header("Chart of Accounts (COA)");
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

// Filter & Search
$filter_type = isset($_GET['type']) ? clean($_GET['type']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-list-columns-reverse"></i> Chart of Accounts</h3>
        <p class="text-muted">Daftar Akun Perkiraan untuk Jurnal Akuntansi.</p>
    </div>
    <div class="col-md-6 text-end">
        <form method="POST" action="index.php?page=acc-coa&action=reconcile" class="d-inline">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-outline-dark me-2" onclick="return confirm('Rekonsiliasi semua saldo COA dari mutasi jurnal?')">
                <i class="bi bi-arrow-repeat"></i> Rekonsiliasi Saldo
            </button>
        </form>
        <a href="index.php?page=acc-coa&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Tambah Akun
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="coa-filter-form">
            <input type="hidden" name="page" value="acc-coa">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari Kode / Nama Akun..." value="<?= $search_key ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="">- Semua Kategori -</option>
                    <option value="asset" <?= $filter_type=='asset'?'selected':'' ?>>Asset</option>
                    <option value="liability" <?= $filter_type=='liability'?'selected':'' ?>>Liability</option>
                    <option value="equity" <?= $filter_type=='equity'?'selected':'' ?>>Equity</option>
                    <option value="revenue" <?= $filter_type=='revenue'?'selected':'' ?>>Revenue</option>
                    <option value="expense" <?= $filter_type=='expense'?'selected':'' ?>>Expense</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=acc-coa" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
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
                        <th>Kode Akun</th>
                        <th>Nama Akun</th>
                        <th>Kategori</th>
                        <th>Saldo Normal</th>
                        <th class="text-end">Saldo Saat Ini</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT * FROM coa WHERE 1=1";
                    $params = [];
                    if (!empty($filter_type)) {
                        $sql .= " AND account_type = ?";
                        $params[] = $filter_type;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (account_code LIKE ? OR account_name LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY account_code ASC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    while ($row = $stmt->fetch()):
                        $badge = match($row['account_type']) {
                            'asset' => 'bg-primary',
                            'liability' => 'bg-warning text-dark',
                            'equity' => 'bg-info text-dark',
                            'revenue' => 'bg-success',
                            'expense' => 'bg-danger',
                            default => 'bg-light'
                        };
                    ?>
                    <tr>
                        <td><strong><?= clean($row['account_code']) ?></strong></td>
                        <td><?= clean($row['account_name']) ?></td>
                        <td><span class="badge <?= $badge ?>"><?= strtoupper($row['account_type']) ?></span></td>
                        <td><?= strtoupper($row['normal_balance']) ?></td>
                        <td class="text-end fw-bold">Rp <?= number_format($row['current_balance'], 0, ',', '.') ?></td>
                        <td class="text-center">
                            <a href="index.php?page=acc-coa&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning text-white"><i class="bi bi-pencil"></i></a>
                            <a href="index.php?page=acc-coa&action=delete&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus akun ini?')"><i class="bi bi-trash"></i></a>
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
    const form = document.getElementById('coa-filter-form');
    if (!form) return;

    const search = form.querySelector('input[name="search"]');
    const type = form.querySelector('select[name="type"]');
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
    if (type) {
        type.addEventListener('change', submit);
    }
})();
</script>

<?php render_footer(); ?>
