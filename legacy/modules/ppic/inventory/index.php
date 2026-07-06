<?php
// modules/ppic/inventory/index.php
render_header("Inventory Control (PPIC)");

// Filter Tipe Barang
$type = isset($_GET['type']) ? $_GET['type'] : '';
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

// Query Dasar
$sql = "SELECT * FROM items WHERE 1=1";
$params = [];

if ($type) {
    $sql .= " AND item_type = ?";
    $params[] = $type;
} else {
    // Default tampilkan Raw Material & WIP (Fokus PPIC)
    $sql .= " AND item_type IN ('raw_material', 'wip', 'consumable')";
}

if ($search) {
    $sql .= " AND (item_name LIKE ? OR item_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY current_stock ASC"; // Urutkan dari stok paling sedikit
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-boxes"></i> Inventory Control</h3>
        <p class="text-muted">Monitoring stok bahan baku dan WIP untuk perencanaan produksi.</p>
    </div>
</div>

<!-- FILTER -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="page" value="ppic-inventory">
            <div class="col-md-3">
                <select name="type" class="form-select" onchange="this.form.submit()">
                    <option value="">- Semua Tipe Material -</option>
                    <option value="raw_material" <?= $type=='raw_material'?'selected':'' ?>>Raw Material</option>
                    <option value="consumable" <?= $type=='consumable'?'selected':'' ?>>Consumable</option>
                    <option value="wip" <?= $type=='wip'?'selected':'' ?>>WIP (Setengah Jadi)</option>
                    <option value="finish_good" <?= $type=='finish_good'?'selected':'' ?>>Finish Good</option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Cari Kode / Nama Barang..." value="<?= $search ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Cari</button>
            </div>
        </form>
    </div>
</div>

<!-- TABEL STOK -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Kode</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Kepemilikan</th>
                        <th class="text-center">Min. Stok</th>
                        <th class="text-center">Stok Saat Ini</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($items)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">Data tidak ditemukan.</td></tr>
                    <?php else: foreach($items as $row): 
                        // Logic Status Stok
                        $stock = floatval($row['current_stock']);
                        $min = floatval($row['min_stock']);
                        
                        if ($stock <= 0) {
                            $status = '<span class="badge bg-dark">KOSONG</span>';
                            $row_class = 'bg-danger bg-opacity-10';
                        } elseif ($stock <= $min) {
                            $status = '<span class="badge bg-danger">KRITIS</span>';
                            $row_class = 'bg-warning bg-opacity-10';
                        } else {
                            $status = '<span class="badge bg-success">AMAN</span>';
                            $row_class = '';
                        }
                        
                        $own_badge = ($row['ownership'] == 'customer') ? '<span class="badge bg-info text-dark">Consignment</span>' : '<span class="badge bg-light text-muted border">Internal</span>';
                    ?>
                    <tr class="<?= $row_class ?>">
                        <td class="fw-bold"><?= clean($row['item_code']) ?></td>
                        <td><?= clean($row['item_name']) ?></td>
                        <td><?= ucwords(str_replace('_',' ',$row['item_type'])) ?></td>
                        <td><?= $own_badge ?></td>
                        <td class="text-center"><?= $min + 0 ?></td>
                        <td class="text-center fw-bold fs-6"><?= $stock + 0 ?> <?= clean($row['unit']) ?></td>
                        <td class="text-center"><?= $status ?></td>
                        <td class="text-center">
                            <a href="index.php?page=ppic-inventory&action=view&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Lihat Kartu Stok">
                                <i class="bi bi-card-list"></i> Kartu Stok
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php render_footer(); ?>