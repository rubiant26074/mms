<?php
// modules/warehouse/receive/index.php
render_header("Penerimaan Barang (Incoming)");

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-box-seam"></i> Penerimaan Barang</h3>
        <p class="text-muted">Input kedatangan material dari Supplier (PO) atau Customer (Consignment).</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=whse-receive&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Terima Barang Baru
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="gr-filter-form">
            <input type="hidden" name="page" value="whse-receive">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari No. GR / Supplier / Cust / No. SJ..." value="<?= $search_key ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="draft" <?= $filter_status=='draft'?'selected':'' ?>>Draft</option>
                    <option value="qc_pending" <?= $filter_status=='qc_pending'?'selected':'' ?>>QC Pending</option>
                    <option value="approved" <?= $filter_status=='approved'?'selected':'' ?>>Approved</option>
                    <option value="rejected" <?= $filter_status=='rejected'?'selected':'' ?>>Rejected</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=whse-receive" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
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
                        <th>No. GR</th>
                        <th>Tgl Terima</th>
                        <th>Sumber (Supplier/Cust)</th>
                        <th>Info Logistik</th>
                        <th>Referensi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // QUERY UTAMA (FIXED)
                    // Gunakan LEFT JOIN agar data yang PO-nya NULL (Consignment) tetap muncul
                    $sql = "SELECT gr.*, 
                                   po.po_number, 
                                   s.name as supplier_name,
                                   c.name as customer_name
                            FROM goods_receipts gr
                            LEFT JOIN purchase_orders po ON gr.purchase_order_id = po.id
                            LEFT JOIN suppliers s ON po.supplier_id = s.id
                            LEFT JOIN customers c ON gr.customer_id = c.id
                            WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND gr.status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (gr.gr_number LIKE ? OR s.name LIKE ? OR c.name LIKE ? OR po.po_number LIKE ? OR gr.delivery_note_number LIKE ? OR gr.vehicle_number LIKE ? OR gr.driver_name LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY gr.id DESC";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    while ($row = $stmt->fetch()):
                        $badge = match($row['status']) {
                            'draft' => 'bg-secondary',
                            'qc_pending' => 'bg-warning text-dark',
                            'approved' => 'bg-success',
                            'rejected' => 'bg-danger',
                            default => 'bg-light'
                        };
                        
                        // Tentukan Tampilan Sumber
                        if ($row['receipt_type'] == 'consignment') {
                            $source = '<span class="badge bg-info text-dark mb-1">CONSIGNMENT</span><br>' . clean($row['customer_name']);
                            $ref = '-';
                        } else {
                            $source = '<strong>'.clean($row['supplier_name']).'</strong>';
                            $ref = 'PO: ' . clean($row['po_number']);
                        }
                    ?>
                    <tr>
                        <td><strong><?= clean($row['gr_number']) ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($row['gr_date'])) ?></td>
                        <td><?= $source ?></td>
                        <td>
                            <small class="d-block text-muted">SJ: <?= clean($row['delivery_note_number']) ?></small>
                            <small class="d-block text-muted">
                                <i class="bi bi-truck"></i> <?= clean($row['vehicle_number']) ?> (<?= clean($row['driver_name']) ?>)
                            </small>
                        </td>
                        <td><?= $ref ?></td>
                        <td><span class="badge <?= $badge ?>"><?= strtoupper(str_replace('_',' ',$row['status'])) ?></span></td>
                        <td>
                            <a href="index.php?page=whse-receive&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning text-white"><i class="bi bi-pencil"></i></a>
                            <?php if($row['status'] == 'draft'): ?>
                                <a href="index.php?page=whse-receive&action=delete&id=<?= $row['id'] ?>&csrf=<?= urlencode(mms_csrf_token()) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus data penerimaan ini?')"><i class="bi bi-trash"></i></a>
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
    const form = document.getElementById('gr-filter-form');
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
