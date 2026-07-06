<?php
// modules/accounting/assets/index.php
render_header("Fixed Asset Management");
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';

$print_params = ['page' => 'acc-assets', 'action' => 'print'];
if ($filter_status !== '') $print_params['status'] = $filter_status;
if ($search_key !== '') $print_params['search'] = $search_key;
$print_url = 'index.php?' . http_build_query($print_params);

// ACTION: RUN DEPRECIATION (Hitung Penyusutan Bulan Ini)
if (isset($_GET['action']) && $_GET['action'] == 'depreciate') {
    if (!has_permission('acc_asset_manage')) die("Akses Ditolak");
    $csrf_req = $_GET['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=acc-assets';</script>";
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Ambil semua aset aktif yang nilai bukunya masih > nilai sisa
        $stmt = $pdo->query("SELECT * FROM fixed_assets WHERE status='active' AND book_value > salvage_value");
        $assets = $stmt->fetchAll();
        $count = 0;
        $today = date('Y-m-d');

        // Akun COA Default (Harusnya ambil dari settings, ini hardcode contoh)
        $coa_beban_penyusutan = get_coa_id('5-1003'); // Beban Penyusutan (Buat di COA jika belum ada)
        $coa_akumulasi = get_coa_id('1-1401'); // Akumulasi Penyusutan (Buat di COA)

        foreach ($assets as $asset) {
            // Cek apakah bulan ini sudah disusutkan?
            $cek = $pdo->prepare("SELECT id FROM asset_depreciations WHERE asset_id=? AND MONTH(depreciation_date) = MONTH(?) AND YEAR(depreciation_date) = YEAR(?)");
            $cek->execute([$asset['id'], $today, $today]);
            
            if ($cek->rowCount() == 0) {
                // Hitung nilai susut
                $amount = $asset['monthly_depreciation'];
                
                // Jangan sampai minus (Nilai buku tidak boleh < residu)
                if (($asset['book_value'] - $amount) < $asset['salvage_value']) {
                    $amount = $asset['book_value'] - $asset['salvage_value'];
                }

                if ($amount > 0) {
                    // 1. Catat History
                    $pdo->prepare("INSERT INTO asset_depreciations (asset_id, depreciation_date, amount) VALUES (?, ?, ?)")
                        ->execute([$asset['id'], $today, $amount]);
                    
                    // 2. Update Master Aset
                    $pdo->prepare("UPDATE fixed_assets SET accumulated_depreciation = accumulated_depreciation + ?, book_value = book_value - ? WHERE id = ?")
                        ->execute([$amount, $amount, $asset['id']]);

                    // 3. Auto Jurnal (Jika COA tersedia)
                    if ($coa_beban_penyusutan && $coa_akumulasi) {
                        $jurnal_items = [
                            ['coa_id' => $coa_beban_penyusutan, 'debit' => $amount, 'credit' => 0],
                            ['coa_id' => $coa_akumulasi, 'debit' => 0, 'credit' => $amount]
                        ];
                        create_journal($today, $asset['asset_code'], "Penyusutan Aset Bln ".date('m'), $jurnal_items, 'general');
                    }
                    $count++;
                }
            }
        }
        
        $pdo->commit();
        echo "<script>alert('Proses Depresiasi Selesai! $count aset telah disusutkan.'); window.location='index.php?page=acc-assets';</script>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Error: ".$e->getMessage()."');</script>";
    }
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-building-gear"></i> Aktiva Tetap</h3>
        <p class="text-muted">Manajemen Aset & Penyusutan Otomatis.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="<?= htmlspecialchars($print_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-outline-dark me-2">
            <i class="bi bi-printer"></i> Print
        </a>
        <a href="index.php?page=acc-assets&action=depreciate&csrf=<?= urlencode($csrf) ?>" class="btn btn-warning me-2" onclick="return confirm('Jalankan proses penyusutan untuk bulan ini?')">
            <i class="bi bi-calculator"></i> Run Depresiasi Bulan Ini
        </a>
        <a href="index.php?page=acc-assets&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Tambah Aset
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="asset-filter-form">
            <input type="hidden" name="page" value="acc-assets">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari Kode / Nama / Kategori..." value="<?= $search_key ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="active" <?= $filter_status=='active'?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= $filter_status=='inactive'?'selected':'' ?>>Inactive</option>
                    <option value="disposed" <?= $filter_status=='disposed'?'selected':'' ?>>Disposed</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=acc-assets" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Kode</th>
                        <th>Nama Aset</th>
                        <th>Kategori</th>
                        <th>Tgl Beli</th>
                        <th class="text-end">Harga Perolehan</th>
                        <th class="text-end">Nilai Buku (Saat Ini)</th>
                        <th class="text-end">Susut/Bulan</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT * FROM fixed_assets WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (asset_code LIKE ? OR asset_name LIKE ? OR category LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY id DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    while ($row = $stmt->fetch()):
                        $progress = ($row['acquisition_cost'] > 0) ? round(($row['accumulated_depreciation'] / ($row['acquisition_cost'] - $row['salvage_value'])) * 100) : 0;
                    ?>
                    <tr>
                        <td class="fw-bold"><?= clean($row['asset_code']) ?></td>
                        <td><?= clean($row['asset_name']) ?></td>
                        <td><span class="badge bg-secondary"><?= strtoupper($row['category']) ?></span></td>
                        <td><?= date('d/m/Y', strtotime($row['acquisition_date'])) ?></td>
                        <td class="text-end">Rp <?= number_format($row['acquisition_cost'], 0, ',', '.') ?></td>
                        <td class="text-end fw-bold text-primary">
                            Rp <?= number_format($row['book_value'], 0, ',', '.') ?>
                            <div class="progress mt-1" style="height: 3px;">
                                <div class="progress-bar bg-info" style="width: <?= $progress ?>%"></div>
                            </div>
                        </td>
                        <td class="text-end text-muted small">Rp <?= number_format($row['monthly_depreciation'], 0, ',', '.') ?></td>
                        <td class="text-center">
                            <a href="index.php?page=acc-assets&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
                            <a href="index.php?page=acc-assets&action=delete&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus aset?')"><i class="bi bi-trash"></i></a>
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
    const form = document.getElementById('asset-filter-form');
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
