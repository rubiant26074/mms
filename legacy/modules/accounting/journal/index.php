<?php
// modules/accounting/journal/index.php
render_header("Jurnal Umum");

// Filter & Search
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$print_params = ['page' => 'acc-journal', 'action' => 'print'];
if ($search_key !== '') $print_params['search'] = $search_key;
if ($start_date !== '') $print_params['start_date'] = $start_date;
if ($end_date !== '') $print_params['end_date'] = $end_date;
$print_url = 'index.php?' . http_build_query($print_params);
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-journal-text"></i> Jurnal Umum</h3>
        <p class="text-muted">Pencatatan transaksi keuangan (Debit/Kredit).</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="<?= htmlspecialchars($print_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-outline-dark me-2">
            <i class="bi bi-printer"></i> Print
        </a>
        <a href="index.php?page=acc-journal&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Buat Jurnal Manual
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end" id="journal-filter-form">
            <input type="hidden" name="page" value="acc-journal">
            
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Pencarian</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="No Jurnal / Ref / Akun / Ket..." value="<?= $search_key ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Dari</label>
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Sampai</label>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=acc-journal" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle font-sm">
                <thead class="table-light text-center">
                    <tr>
                        <th>Tgl</th>
                        <th>No. Jurnal / Ref</th>
                        <th>Akun (COA)</th>
                        <th>Keterangan</th>
                        <th>Debit</th>
                        <th>Kredit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Query Join Header & Detail untuk tampilan Jurnal Klasik
                    $sql = "SELECT j.journal_date, j.journal_no, j.reference_no, j.description, 
                                   ji.debit, ji.credit, c.account_name, c.account_code
                            FROM journal_items ji
                            JOIN journals j ON ji.journal_id = j.id
                            JOIN coa c ON ji.coa_id = c.id
                            WHERE 1=1";
                    $params = [];
                    if (!empty($start_date)) {
                        $sql .= " AND j.journal_date >= ?";
                        $params[] = $start_date;
                    }
                    if (!empty($end_date)) {
                        $sql .= " AND j.journal_date <= ?";
                        $params[] = $end_date;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (j.journal_no LIKE ? OR j.reference_no LIKE ? OR j.description LIKE ? OR c.account_name LIKE ? OR c.account_code LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY j.journal_date DESC, j.id DESC, ji.credit ASC"; // Credit biasanya di bawah
                            
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    // Grouping logic untuk tampilan rapi
                    $current_journal = '';
                    
                    while ($row = $stmt->fetch()):
                        $is_new = ($current_journal != $row['journal_no']);
                        $current_journal = $row['journal_no'];
                        
                        // Style untuk baris kredit (menjorok ke dalam)
                        $indent = ($row['credit'] > 0) ? 'style="padding-left: 30px;"' : 'class="fw-bold"';
                    ?>
                    <tr>
                        <?php if($is_new): ?>
                            <td class="bg-light"><?= date('d/m/Y', strtotime($row['journal_date'])) ?></td>
                            <td class="bg-light">
                                <strong><?= $row['journal_no'] ?></strong><br>
                                <small class="text-muted"><?= $row['reference_no'] ?></small>
                            </td>
                        <?php else: ?>
                            <td class="border-0"></td>
                            <td class="border-0"></td>
                        <?php endif; ?>
                        
                        <td <?= $indent ?>>
                            <?= $row['account_code'] ?> - <?= $row['account_name'] ?>
                        </td>
                        
                        <?php if($is_new): ?>
                            <td><?= $row['description'] ?></td>
                        <?php else: ?>
                            <td class="border-0"></td>
                        <?php endif; ?>

                        <td class="text-end"><?= $row['debit'] > 0 ? number_format($row['debit'],0,',','.') : '-' ?></td>
                        <td class="text-end"><?= $row['credit'] > 0 ? number_format($row['credit'],0,',','.') : '-' ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('journal-filter-form');
    if (!form) return;

    const search = form.querySelector('input[name="search"]');
    const start = form.querySelector('input[name="start_date"]');
    const end = form.querySelector('input[name="end_date"]');
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
    if (start) start.addEventListener('change', submit);
    if (end) end.addEventListener('change', submit);
})();
</script>

<?php render_footer(); ?>
