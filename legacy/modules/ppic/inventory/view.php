<?php
// modules/ppic/inventory/view.php

$id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$id) die("Error: ID Item not found.");

// 1. Ambil Info Barang
$stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) die("Barang tidak ditemukan.");

// 2. Query Kartu Stok (UNION Masuk & Keluar)
// Menggabungkan data dari Goods Receipt (Masuk) dan Material Issue (Keluar)
// Diurutkan berdasarkan tanggal
$sql_history = "
    SELECT 'IN' as type, gr.gr_date as date, gr.gr_number as doc_no, 
           gri.qty_received as qty, s.name as party, 'Penerimaan Supplier/Cust' as description
    FROM goods_receipt_items gri
    JOIN goods_receipts gr ON gri.goods_receipt_id = gr.id
    LEFT JOIN purchase_orders po ON gr.purchase_order_id = po.id
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    WHERE gri.item_id = ? AND gr.status = 'approved'

    UNION ALL

    SELECT 'OUT' as type, mi.itr_date as date, mi.itr_number as doc_no,
           mii.qty_issued as qty, CONCAT('Produksi: ', spk.spk_number) as party, mi.notes as description
    FROM material_issue_items mii
    JOIN material_issues mi ON mii.material_issue_id = mi.id
    JOIN spk ON mi.spk_id = spk.id
    WHERE mii.item_id = ?

    ORDER BY date DESC, doc_no DESC
    LIMIT 100
";

$stmt_hist = $pdo->prepare($sql_history);
$stmt_hist->execute([$id, $id]);
$history = $stmt_hist->fetchAll();

render_header("Kartu Stok: " . $item['item_code']);
?>

<div class="row mb-3">
    <div class="col-md-8">
        <a href="index.php?page=ppic-inventory" class="btn btn-secondary mb-3"><i class="bi bi-arrow-left"></i> Kembali</a>
        <h4 class="fw-bold text-primary"><?= $item['item_name'] ?></h4>
        <span class="badge bg-dark"><?= $item['item_code'] ?></span>
        <span class="badge bg-secondary"><?= ucwords(str_replace('_',' ', $item['item_type'])) ?></span>
        
        <?php if($item['ownership']=='customer'): ?>
            <span class="badge bg-info text-dark">CONSIGNMENT</span>
        <?php endif; ?>
    </div>
    <div class="col-md-4 text-end">
        <div class="card border-primary text-center">
            <div class="card-body py-2">
                <small class="text-muted">Stok Saat Ini</small>
                <h2 class="fw-bold text-primary mb-0"><?= $item['current_stock'] + 0 ?> <span class="fs-6 text-muted"><?= $item['unit'] ?></span></h2>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light">
        <strong>Riwayat Mutasi (100 Transaksi Terakhir)</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped mb-0">
                <thead class="table-dark text-center">
                    <tr>
                        <th>Tanggal</th>
                        <th>Dokumen</th>
                        <th>Tipe</th>
                        <th>Keterangan / Pihak</th>
                        <th>Masuk</th>
                        <th>Keluar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($history)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada transaksi mutasi.</td></tr>
                    <?php else: foreach($history as $row): 
                        $in = ($row['type'] == 'IN') ? $row['qty'] : 0;
                        $out = ($row['type'] == 'OUT') ? $row['qty'] : 0;
                        $badge = ($row['type'] == 'IN') ? '<span class="badge bg-success">IN</span>' : '<span class="badge bg-danger">OUT</span>';
                    ?>
                    <tr>
                        <td class="text-center"><?= date('d/m/Y', strtotime($row['date'])) ?></td>
                        <td class="text-center fw-bold"><?= $row['doc_no'] ?></td>
                        <td class="text-center"><?= $badge ?></td>
                        <td>
                            <strong><?= $row['party'] ?></strong>
                            <?php if($row['description']): ?><br><small class="text-muted"><?= $row['description'] ?></small><?php endif; ?>
                        </td>
                        <td class="text-end text-success fw-bold"><?= $in > 0 ? '+'.($in+0) : '-' ?></td>
                        <td class="text-end text-danger fw-bold"><?= $out > 0 ? '-'.($out+0) : '-' ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php render_footer(); ?>