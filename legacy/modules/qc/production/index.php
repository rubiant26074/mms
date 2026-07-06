<?php
// modules/qc/production/index.php
if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('qc_production_view')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=dashboard';</script>";
    exit;
}

render_header("QC Final Production");
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-clipboard-check"></i> QC Hasil Produksi</h3>
        <p class="text-muted">Inspeksi akhir barang jadi (Finish Good) sebelum masuk gudang.</p>
    </div>
</div>

<!-- 1. PENDING INSPECTION -->
<div class="card shadow-sm mb-4 border-start border-4 border-warning">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-bold text-warning"><i class="bi bi-hourglass-split"></i> Menunggu Inspeksi (Ready for QC)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No. SPK</th>
                        <th>Tgl Deadline</th>
                        <th>Project</th>
                        <th>Barang Jadi</th>
                        <th>Status Prod</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Tampilkan SPK yang siap QC:
                    // - status completed/closed
                    // - belum pernah QC, atau QC terakhir status NG (termasuk data lama anomali)
                    $sql = "SELECT spk.*, COALESCE(spk.project_name, '-') as project_name,
                                   (SELECT GROUP_CONCAT(i.item_name SEPARATOR ', ')
                                    FROM sales_order_items soi
                                    JOIN items i ON soi.item_id = i.id
                                    WHERE soi.sales_order_id = spk.sales_order_id
                                   ) as product_name,
                                   qlast.id as last_qc_id,
                                   qlast.status as last_qc_status,
                                   qlast.qty_reject as last_qc_reject
                            FROM spk
                            LEFT JOIN (
                                SELECT q1.*
                                FROM qc_production q1
                                JOIN (
                                    SELECT spk_id, MAX(id) AS max_id
                                    FROM qc_production
                                    GROUP BY spk_id
                                ) q2 ON q2.max_id = q1.id
                            ) qlast ON qlast.spk_id = spk.id
                            WHERE spk.status IN ('completed', 'closed')
                            AND (
                                qlast.id IS NULL
                                OR qlast.status = 'ng'
                                OR (
                                    COALESCE(qlast.qty_reject, 0) > 0
                                    AND (qlast.status IS NULL OR qlast.status = '' OR qlast.status = 'completed')
                                )
                            )
                            ORDER BY spk.deadline_date ASC";
                    
                    $stmt = $pdo->query($sql);
                    
                    if ($stmt->rowCount() == 0):
                    ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Tidak ada barang produksi selesai yang menunggu QC.</td></tr>
                    <?php else: while ($row = $stmt->fetch()): 
                        $status_badge = 'bg-success'; // Karena filter cuma completed, pasti sukses
                    ?>
                    <tr>
                        <td><strong><?= clean($row['spk_number']) ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($row['deadline_date'])) ?></td>
                        <td><?= clean($row['project_name']) ?></td>
                        <td><?= clean($row['product_name']) ?></td>
                        <td><span class="badge <?= $status_badge ?>"><?= strtoupper(str_replace('_', ' ', $row['status'])) ?></span></td>
                        <td class="text-center">
                            <a href="index.php?page=qc-production&action=inspect&spk_id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-primary shadow-sm">
                                <i class="bi bi-search"></i> Inspect
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 2. HISTORY QC -->
<div class="card shadow-sm border-start border-4 border-success">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-bold text-success"><i class="bi bi-check-all"></i> Riwayat QC Selesai</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No. QC</th>
                        <th>Tgl QC</th>
                        <th>No. SPK</th>
                        <th>Barang</th>
                        <th>Hasil (OK / NG)</th>
                        <th>Inspector</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_hist = "SELECT qc.*, spk.spk_number, u.fullname as inspector,
                                   (SELECT GROUP_CONCAT(i.item_name SEPARATOR ', ')
                                    FROM sales_order_items soi
                                    JOIN items i ON soi.item_id = i.id
                                    LEFT JOIN spk s_sub ON s_sub.id = qc.spk_id
                                    WHERE soi.sales_order_id = s_sub.sales_order_id
                                   ) as product_name
                                 FROM qc_production qc
                                 JOIN (
                                     SELECT spk_id, MAX(id) AS max_id
                                     FROM qc_production
                                     GROUP BY spk_id
                                 ) qlast ON qlast.max_id = qc.id
                                 JOIN spk ON qc.spk_id = spk.id
                                 JOIN users u ON qc.inspector_id = u.id
                                 ORDER BY qc.id DESC LIMIT 10";
                    $stmt_hist = $pdo->query($sql_hist);
                    
                    while($h = $stmt_hist->fetch()):
                    ?>
                    <tr>
                        <td><strong><?= clean($h['qc_number']) ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($h['qc_date'])) ?></td>
                        <td><?= clean($h['spk_number']) ?></td>
                        <td><?= clean($h['product_name']) ?></td>
                        <td>
                            <span class="badge bg-success"><?= $h['qty_pass'] + 0 ?> OK</span>
                            <?php if($h['qty_reject'] > 0): ?>
                                <span class="badge bg-danger ms-1"><?= $h['qty_reject'] + 0 ?> NG</span>
                            <?php endif; ?>
                        </td>
                        <td><?= clean($h['inspector']) ?></td>
                        <td class="text-center">
                            <a href="index.php?page=qc-production&action=print&qc_id=<?= (int)$h['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                                <i class="bi bi-printer"></i> Print Verifikasi
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php render_footer(); ?>
