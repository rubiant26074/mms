<?php
// modules/production/tasks/index.php
render_header("Production Task Assignment");
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h3 class="fw-bold text-primary"><i class="bi bi-kanban"></i> Manajemen Tugas</h3>
        <p class="text-muted mb-0">Distribusi tugas operator dan monitoring status SPK.</p>
    </div>
    <div class="col-md-6 text-end">
        <!-- Filter dummy for visuals -->
        <div class="btn-group">
            <button class="btn btn-outline-secondary active">Semua</button>
            <button class="btn btn-outline-secondary">Released</button>
            <button class="btn btn-outline-secondary">In Progress</button>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">No. SPK / Project</th>
                        <th>Deadline</th>
                        <th>Progress Tugas</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT spk.*, so.so_number, c.name as customer_name,
                                   (SELECT COUNT(*) FROM production_assignments WHERE spk_id = spk.id) as assigned,
                                   (SELECT COUNT(*) FROM production_assignments WHERE spk_id = spk.id AND status = 'completed') as completed
                            FROM spk
                            LEFT JOIN sales_orders so ON spk.sales_order_id = so.id
                            LEFT JOIN customers c ON so.customer_id = c.id
                            WHERE spk.status IN ('waiting_mgr', 'final', 'released', 'in_production', 'completed', 'closed')
                               OR EXISTS (
                                   SELECT 1 FROM production_assignments pa 
                                   WHERE pa.spk_id = spk.id AND pa.process_name LIKE 'NCR %'
                               )
                            ORDER BY spk.deadline_date ASC";
                    
                    $stmt = $pdo->query($sql);
                    
                    if ($stmt->rowCount() == 0):
                        echo '<tr><td colspan="5" class="text-center py-5 text-muted">Tidak ada SPK aktif.</td></tr>';
                    else: while ($row = $stmt->fetch()): 
                        // Hitung jumlah proses yang harus dikerjakan (dari route di SPK)
                        $req_proc_arr = array_filter(array_map('trim', explode(',', (string)$row['required_processes'])));
                        $route_needed = count($req_proc_arr);
                        $total_assigned = (int)$row['assigned'];
                        $total_needed = max($route_needed, $total_assigned);
                        $total_done = $row['completed'];
                        
                        // Progress Bar Logic
                        $pct = ($total_needed > 0) ? round(($total_done / $total_needed) * 100) : 0;
                        $color = ($pct == 100) ? 'bg-success' : 'bg-primary';
                        
                        // Status Badge
                        $badge = match($row['status']) {
                            'waiting_mgr' => '<span class="badge bg-danger">WAITING MGR</span>',
                            'final' => '<span class="badge bg-success">FINAL</span>',
                            'released' => '<span class="badge bg-info text-dark">BARU</span>',
                            'in_production' => '<span class="badge bg-warning text-dark">PROSES</span>',
                            'completed' => '<span class="badge bg-success">COMPLETED</span>',
                            'closed' => '<span class="badge bg-dark">CLOSED</span>',
                            default => '<span class="badge bg-secondary">UNKNOWN</span>'
                        };
                    ?>
                    <tr>
                        <td class="ps-4">
                            <strong class="text-primary"><?= clean($row['spk_number']) ?></strong><br>
                            <small class="text-muted fw-bold">
                                <?= clean($row['customer_name'] ?: '-') ?> | SO: <?= clean($row['so_number'] ?: '-') ?>
                            </small>
                        </td>
                        <td>
                            <?php 
                                $diff = ceil((strtotime($row['deadline_date']) - time()) / 86400);
                                $text_class = ($diff < 0) ? 'text-danger fw-bold' : (($diff <= 2) ? 'text-warning fw-bold' : 'text-dark');
                            ?>
                            <div class="<?= $text_class ?>"><?= date('d M Y', strtotime($row['deadline_date'])) ?></div>
                            <small class="text-muted"><?= ($diff < 0) ? 'Terlambat' : $diff . ' Hari lagi' ?></small>
                        </td>
                        <td style="width: 25%;">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>Selesai: <?= $total_done ?> / <?= $total_needed ?> Proses</span>
                                <span class="fw-bold"><?= $pct ?>%</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar <?= $color ?>" role="progressbar" style="width: <?= $pct ?>%"></div>
                            </div>
                        </td>
                        <td><?= $badge ?></td>
                        <td class="text-center">
                            <a href="index.php?page=prod-task&action=manage&spk_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary fw-bold">
                                <i class="bi bi-people-fill me-1"></i> ATUR TUGAS
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php render_footer(); ?>
