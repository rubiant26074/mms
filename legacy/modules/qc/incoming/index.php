<?php
// modules/qc/incoming/index.php
if (!function_exists('render_header')) {
    if (file_exists('../../config/database.php')) {
        require_once '../../config/database.php';
        require_once '../../config/functions.php';
    } elseif (file_exists('../../../config/database.php')) {
        require_once '../../../config/database.php';
        require_once '../../../config/functions.php';
    }
}

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('qc_incoming_view')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=dashboard';</script>";
    exit;
}

render_header("QC Incoming");
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

// --- LOGIKA ACTION (APPROVE QC) ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = (string)$_GET['action'];
    if (in_array($action, ['approve', 'handover'], true)) {
        $csrf_req = $_GET['csrf'] ?? '';
        if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
            echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=qc-incoming';</script>";
            exit;
        }
    }
    if ($id <= 0) {
        echo "<script>alert('Request tidak valid.'); window.location='index.php?page=qc-incoming';</script>";
        exit;
    }
    
    // APPROVE QC (Hanya Manager)
    if ($action == 'approve') {
        if (!has_permission('qc_incoming_approve')) {
            echo "<script>alert('Akses Ditolak!'); window.location='index.php?page=qc-incoming';</script>";
            exit;
        }
        try {
            $pdo->beginTransaction();
            
            // 1. Update QC Status menjadi Completed & Catat Approver
            $stmt = $pdo->prepare("UPDATE qc_incoming SET status='completed', approved_by=?, approved_at=NOW() WHERE id=? AND status='waiting_approval'");
            $stmt->execute([$_SESSION['user_id'], $id]);
            
            if ($stmt->rowCount() > 0) {
                // 2. Ambil Data QC untuk tahu GR ID dan Keputusan Akhir
                $get_gr = $pdo->prepare("SELECT goods_receipt_id, final_decision, qc_number FROM qc_incoming WHERE id=?");
                $get_gr->execute([$id]);
                $qc_data = $get_gr->fetch();
                
                // 3. Update Status GR Gudang
                // Jika Rejected Total -> GR status 'rejected'
                // Jika Accepted/Partial -> GR status 'approved' (Stok Sah Masuk)
                $gr_status = ($qc_data['final_decision'] == 'rejected') ? 'rejected' : 'approved';
                
                $pdo->prepare("UPDATE goods_receipts SET status=? WHERE id=?")->execute([$gr_status, $qc_data['goods_receipt_id']]);

                // 4. --- UPDATE STOK MASTER BARANG (CRITICAL FIX) ---
                $updated_count = 0;
                if ($gr_status == 'approved') {
                    // Ambil detail item dan Qty Good dari tabel QC Items
                    $sql_items = "SELECT item_id, qty_good FROM qc_incoming_items WHERE qc_incoming_id = ?";
                    $stmt_items = $pdo->prepare($sql_items);
                    $stmt_items->execute([$id]);
                    $qc_items = $stmt_items->fetchAll();

                    // Query Update Stok (+ Tambah)
                    $stmt_stock = $pdo->prepare("UPDATE items SET current_stock = current_stock + ? WHERE id = ?");
                    
                    foreach ($qc_items as $item) {
                        $qty_add = floatval($item['qty_good']);
                        $item_id = $item['item_id'];

                        // Hanya update jika ada barang bagus
                        if ($qty_add > 0) {
                            $stmt_stock->execute([$qty_add, $item_id]);
                            $updated_count++;
                        }
                    }
                }
                
                $pdo->commit();
                
                // TRIGGER NOTIFIKASI KE GUDANG
                if (function_exists('notify_workflow_event')) {
                    notify_workflow_event(
                        'qc.incoming.approve.' . (int)$id,
                        'QC Selesai - Siap Serah Terima',
                        "QC #{$qc_data['qc_number']} telah di-approve. Silakan lakukan penerimaan fisik barang.",
                        "index.php?page=qc-incoming",
                        'info',
                        ['permission_slug' => 'whse_view']
                    );
                }

                // Pesan Sukses
                $msg = "QC Berhasil di-Approve! Status GR: " . strtoupper($gr_status);
                if ($updated_count > 0) {
                    $msg .= "\\nStok Master Barang telah bertambah untuk $updated_count item.";
                }
                
                echo "<script>alert('$msg'); window.location='index.php?page=qc-incoming';</script>";
            } else {
                $pdo->rollBack();
                echo "<script>alert('Gagal Approve. Status QC mungkin sudah berubah atau Anda tidak memiliki akses.'); window.location='index.php?page=qc-incoming';</script>";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "<script>alert('Terjadi kesalahan sistem saat approve QC.'); window.location='index.php?page=qc-incoming';</script>";
        }
    }

    // HANDOVER GUDANG (Terima Barang dari QC)
    if ($action == 'handover') {
        if (!has_permission('whse_view')) {
            echo "<script>alert('Akses Ditolak!'); window.location='index.php?page=qc-incoming';</script>";
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE qc_incoming SET handover_by=?, handover_at=NOW() WHERE id=? AND status='completed' AND handover_by IS NULL");
            $stmt->execute([$_SESSION['user_id'], $id]);
            
            if ($stmt->rowCount() > 0) {
                if (function_exists('notify_workflow_event')) {
                    notify_workflow_event(
                        'qc.incoming.handover.' . (int)$id,
                        'Serah Terima QC Incoming',
                        "QC Incoming #$id telah diterima gudang.",
                        "index.php?page=whse-receive",
                        'success',
                        ['permission_slug' => 'qc_incoming_manage']
                    );
                }
                echo "<script>alert('Serah terima berhasil! Tanda tangan gudang telah dicatat.'); window.location='index.php?page=qc-incoming';</script>";
            } else {
                echo "<script>alert('Gagal. Barang mungkin sudah diterima sebelumnya.'); window.location='index.php?page=qc-incoming';</script>";
            }
        } catch (Exception $e) {
            echo "<script>alert('Terjadi kesalahan saat serah terima.'); window.location='index.php?page=qc-incoming';</script>";
        }
    }
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-shield-check"></i> QC Incoming Material</h3>
        <p class="text-muted">Pemeriksaan kualitas material masuk dari Supplier/Customer.</p>
    </div>
</div>

<!-- 1. PENDING INSPECTION (Tugas Inspector) -->
<div class="card shadow-sm mb-4 border-start border-4 border-warning">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-bold text-warning"><i class="bi bi-hourglass-split"></i> Pending Inspection (Menunggu Pemeriksaan)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No. GR</th>
                        <th>Tgl Terima</th>
                        <th>Sumber</th>
                        <th>Item Masuk</th>
                        <th>Penerima</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT gr.*, 
                                   po.po_number, s.name as supp_name, c.name as cust_name,
                                   (SELECT COUNT(*) FROM goods_receipt_items WHERE goods_receipt_id = gr.id) as total_items
                            FROM goods_receipts gr
                            LEFT JOIN purchase_orders po ON gr.purchase_order_id = po.id
                            LEFT JOIN suppliers s ON po.supplier_id = s.id
                            LEFT JOIN customers c ON gr.customer_id = c.id
                            WHERE gr.status = 'qc_pending'
                            ORDER BY gr.gr_date ASC";
                    $stmt = $pdo->query($sql);
                    
                    if ($stmt->rowCount() == 0):
                    ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Tidak ada barang yang menunggu QC.</td></tr>
                    <?php else: while ($row = $stmt->fetch()): 
                        $source = ($row['receipt_type'] == 'consignment') ? '<span class="badge bg-info text-dark">Consignment</span><br>' . clean($row['cust_name']) : '<strong>'.clean($row['supp_name']).'</strong>';
                    ?>
                    <tr>
                        <td><strong><?= clean($row['gr_number']) ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($row['gr_date'])) ?></td>
                        <td><?= $source ?></td>
                        <td><span class="badge bg-info text-dark"><?= $row['total_items'] ?> Items</span></td>
                        <td><?= clean($row['received_by']) ?></td>
                        <td class="text-center">
                            <a href="index.php?page=qc-incoming&action=inspect&gr_id=<?= $row['id'] ?>" class="btn btn-sm btn-primary shadow-sm">
                                <i class="bi bi-search"></i> Periksa (Inspect)
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 2. WAITING APPROVAL (Tugas Manager QC) -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-person-check-fill"></i> Menunggu Approval Manager</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No. QC</th>
                        <th>Ref. GR</th>
                        <th>Inspector</th>
                        <th>Keputusan Inspector</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_app = "SELECT qc.*, gr.gr_number, u.fullname as inspector
                                FROM qc_incoming qc
                                JOIN goods_receipts gr ON qc.goods_receipt_id = gr.id
                                JOIN users u ON qc.inspector_id = u.id
                                WHERE qc.status = 'waiting_approval' 
                                ORDER BY qc.id DESC";
                    $stmt_app = $pdo->query($sql_app);
                    
                    if ($stmt_app->rowCount() == 0):
                    ?>
                        <tr><td colspan="5" class="text-center py-3 text-muted">Tidak ada QC menunggu approval.</td></tr>
                    <?php else: while ($a = $stmt_app->fetch()): 
                        $badge = ($a['final_decision'] == 'accepted') ? 'bg-success' : (($a['final_decision'] == 'rejected') ? 'bg-danger' : 'bg-warning text-dark');
                    ?>
                    <tr>
                        <td><strong><?= clean($a['qc_number']) ?></strong></td>
                        <td><?= clean($a['gr_number']) ?></td>
                        <td><?= clean($a['inspector']) ?></td>
                        <td><span class="badge <?= $badge ?>"><?= strtoupper($a['final_decision']) ?></span></td>
                        <td class="text-center">
                            <a href="index.php?page=qc-incoming&action=print&id=<?= $a['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Lihat Detail"><i class="bi bi-eye"></i></a>
                            
                            <?php if(has_permission('qc_incoming_approve')): ?>
                                <a href="index.php?page=qc-incoming&action=approve&id=<?= (int)$a['id'] ?>&csrf=<?= urlencode($csrf) ?>" 
                                   class="btn btn-sm btn-success fw-bold ms-1" 
                                   onclick="return confirm('Approve Hasil QC ini? Stok barang bagus akan otomatis ditambahkan ke Gudang.')">
                                    <i class="bi bi-check-circle"></i> Approve
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 3. MENUNGGU SERAH TERIMA GUDANG (QC OK -> Warehouse) -->
<div class="card shadow-sm mb-4 border-start border-4 border-info">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-bold text-info"><i class="bi bi-box-seam"></i> Menunggu Serah Terima Gudang</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No. QC</th>
                        <th>Ref. GR</th>
                        <th>Tgl QC</th>
                        <th>Status QC</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_ho = "SELECT qc.*, gr.gr_number 
                               FROM qc_incoming qc
                               JOIN goods_receipts gr ON qc.goods_receipt_id = gr.id
                               WHERE qc.status = 'completed' AND qc.handover_by IS NULL
                               ORDER BY qc.id DESC";

                    try {
                        $stmt_ho = $pdo->query($sql_ho);
                    } catch (PDOException $e) {
                        $stmt_ho = false; // Mencegah crash jika kolom belum ada
                    }
                    
                    if (!$stmt_ho || $stmt_ho->rowCount() == 0):
                    ?>
                        <tr><td colspan="5" class="text-center py-3 text-muted">Tidak ada barang menunggu serah terima.</td></tr>
                    <?php elseif ($stmt_ho): while ($h = $stmt_ho->fetch()): ?>
                    <tr>
                        <td><strong><?= clean($h['qc_number']) ?></strong></td>
                        <td><?= clean($h['gr_number']) ?></td>
                        <td><?= date('d/m/Y', strtotime($h['qc_date'])) ?></td>
                        <td><span class="badge bg-success">QC OK</span></td>
                        <td class="text-center">
                            <a href="index.php?page=qc-incoming&action=handover&id=<?= (int)$h['id'] ?>&csrf=<?= urlencode($csrf) ?>" 
                               class="btn btn-sm btn-info text-white fw-bold"
                               onclick="return confirm('Konfirmasi penerimaan barang fisik dari QC ke Gudang?')">
                                <i class="bi bi-hand-thumbs-up"></i> Terima Barang
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 4. RIWAYAT QC & TANDA TERIMA -->
<div class="card shadow-sm border-start border-4 border-success">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-bold text-success"><i class="bi bi-check-all"></i> Riwayat QC & Tanda Terima</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>No. QC</th>
                        <th>Tgl QC</th>
                        <th>No. GR</th>
                        <th>Inspector</th>
                        <th>Keputusan</th>
                        <th class="text-center">Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_hist = "SELECT qc.*, gr.gr_number, u.fullname as inspector
                                 FROM qc_incoming qc
                                 JOIN goods_receipts gr ON qc.goods_receipt_id = gr.id
                                 JOIN users u ON qc.inspector_id = u.id
                                 WHERE qc.status = 'completed' AND qc.handover_by IS NOT NULL
                                 ORDER BY qc.id DESC LIMIT 10";
                    $stmt_hist = $pdo->query($sql_hist);
                    
                    while($h = $stmt_hist->fetch()):
                        $badge = ($h['final_decision'] == 'accepted') ? 'bg-success' : 'bg-danger';
                    ?>
                    <tr>
                        <td><?= clean($h['qc_number']) ?></td>
                        <td><?= date('d/m/Y', strtotime($h['qc_date'])) ?></td>
                        <td><?= clean($h['gr_number']) ?></td>
                        <td><?= clean($h['inspector']) ?></td>
                        <td><span class="badge <?= $badge ?>"><?= strtoupper($h['final_decision']) ?></span></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="index.php?page=qc-incoming&action=print&id=<?= $h['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Laporan QC"><i class="bi bi-file-earmark-text"></i> QC</a>
                                <a href="modules/qc/incoming/print_receipt.php?id=<?= $h['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Tanda Terima"><i class="bi bi-receipt"></i> Receipt</a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php render_footer(); ?>
