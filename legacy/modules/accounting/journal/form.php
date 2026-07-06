<?php
// modules/accounting/journal/form.php

$id = isset($_GET['id']) ? $_GET['id'] : null; // Jurnal jarang diedit, biasanya dihapus/reverse
$data = [
    'journal_no' => 'AUTO',
    'journal_date' => date('Y-m-d'),
    'reference_no' => '',
    'description' => ''
];

// Load COA (dibutuhkan untuk POST)
$accounts = $pdo->query("SELECT * FROM coa ORDER BY account_code ASC")->fetchAll();

// PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    }
    $date = $_POST['journal_date'];
    $ref = $_POST['reference_no'];
    $desc = $_POST['description'];
    
    $coa_ids = $_POST['coa_id'];
    $debits = $_POST['debit'];
    $credits = $_POST['credit'];
    
    // Validasi Balance
    $total_d = 0;
    $total_c = 0;
    foreach($debits as $d) $total_d += floatval($d);
    foreach($credits as $c) $total_c += floatval($c);
    
    if (!isset($error) && $total_d != $total_c) {
        $error = "Jurnal Tidak Balance! Debit: $total_d, Kredit: $total_c";
    } elseif (!isset($error) && $total_d == 0) {
        $error = "Nominal tidak boleh 0.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Generate No Jurnal: JRN-YYMM-0001
            $ym = date('ym');
            $stmt_no = $pdo->query("SELECT COUNT(*) FROM journals WHERE journal_no LIKE 'JRN-$ym-%'");
            $count = $stmt_no->fetchColumn() + 1;
            $jrn_no = "JRN-" . $ym . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);
            
            // Insert Header
            $sql = "INSERT INTO journals (journal_no, journal_date, reference_no, description, total_debit, total_credit, type, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, 'general', ?)";
            $pdo->prepare($sql)->execute([$jrn_no, $date, $ref, $desc, $total_d, $total_c, $_SESSION['user_id']]);
            $jrn_id = $pdo->lastInsertId();
            
            // Insert Detail & Update Saldo COA
            $sql_det = "INSERT INTO journal_items (journal_id, coa_id, debit, credit) VALUES (?, ?, ?, ?)";
            $stmt_det = $pdo->prepare($sql_det);
            
            $sql_upd_coa = "UPDATE coa SET current_balance = current_balance + ? WHERE id = ?";
            $stmt_upd_coa = $pdo->prepare($sql_upd_coa);
            $coa_norm_map = [];
            foreach ($accounts as $acc) {
                $coa_norm_map[(int)$acc['id']] = $acc['normal_balance'];
            }
            
            for ($i = 0; $i < count($coa_ids); $i++) {
                $d = floatval($debits[$i]);
                $c = floatval($credits[$i]);
                
                if ($d > 0 || $c > 0) {
                    $stmt_det->execute([$jrn_id, $coa_ids[$i], $d, $c]);
                    
                    // Update Saldo COA (Sederhana: Tambah Debit, Kurang Kredit, atau sesuaikan normal balance)
                    // Logic umum: 
                    // Asset/Expense (Normal Debit): +Debit -Credit
                    // Liability/Equity/Revenue (Normal Credit): +Credit -Debit
                    
                    // Untuk mempermudah di fase ini, kita pakai logic sederhana dulu (perlu disempurnakan nanti)
                    // Kita update saldo saja dulu tanpa lihat tipe (bisa negatif)
                    $normal = $coa_norm_map[(int)$coa_ids[$i]] ?? 'debit';
                    $change = ($normal === 'credit') ? ($c - $d) : ($d - $c);
                    $stmt_upd_coa->execute([$change, $coa_ids[$i]]);
                }
            }
            
            $pdo->commit();
            echo "<script>alert('Jurnal berhasil disimpan!'); window.location='index.php?page=acc-journal';</script>";
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Load COA (already loaded above)

render_header("Input Jurnal Umum");
?>

<form method="POST" id="journalForm">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(mms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">Header Jurnal</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label>No. Jurnal</label>
                    <input type="text" class="form-control fw-bold" value="AUTO" readonly>
                </div>
                <div class="col-md-3 mb-3">
                    <label>Tanggal</label>
                    <input type="date" name="journal_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Referensi (No. Bukti)</label>
                    <input type="text" name="reference_no" class="form-control" placeholder="Contoh: BKK-001">
                </div>
            </div>
            <div class="mb-3">
                <label>Keterangan</label>
                <textarea name="description" class="form-control" rows="2" required></textarea>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between">
            <strong>Detail Transaksi</strong>
            <button type="button" class="btn btn-sm btn-success" onclick="addRow()">+ Tambah Baris</button>
        </div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead class="table-light text-center">
                    <tr>
                        <th width="40%">Akun (COA)</th>
                        <th width="25%">Debit</th>
                        <th width="25%">Kredit</th>
                        <th width="10%">Hapus</th>
                    </tr>
                </thead>
                <tbody id="journalItems">
                    <!-- Default 2 Baris (Debit & Kredit) -->
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td class="text-end">TOTAL :</td>
                        <td class="text-end"><span id="totalDebit">0</span></td>
                        <td class="text-end"><span id="totalCredit">0</span></td>
                        <td class="text-center"><span id="balanceStatus" class="badge bg-success">Balance</span></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <div class="text-end mb-5">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> Simpan Jurnal</button>
    </div>
</form>

<script>
const accounts = <?= json_encode($accounts) ?>;

function addRow() {
    let opts = '<option value="">-- Pilih Akun --</option>';
    accounts.forEach(acc => {
        opts += `<option value="${acc.id}">${acc.account_code} - ${acc.account_name}</option>`;
    });

    const row = `
    <tr>
        <td>
            <select name="coa_id[]" class="form-select select-account" required>
                ${opts}
            </select>
        </td>
        <td>
            <input type="number" name="debit[]" class="form-control text-end debit-input" value="0" min="0" oninput="calcTotal()">
        </td>
        <td>
            <input type="number" name="credit[]" class="form-control text-end credit-input" value="0" min="0" oninput="calcTotal()">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); calcTotal()">X</button>
        </td>
    </tr>`;
    document.getElementById('journalItems').insertAdjacentHTML('beforeend', row);
}

function calcTotal() {
    let totD = 0, totC = 0;
    document.querySelectorAll('.debit-input').forEach(i => totD += parseFloat(i.value) || 0);
    document.querySelectorAll('.credit-input').forEach(i => totC += parseFloat(i.value) || 0);
    
    document.getElementById('totalDebit').innerText = new Intl.NumberFormat('id-ID').format(totD);
    document.getElementById('totalCredit').innerText = new Intl.NumberFormat('id-ID').format(totC);
    
    const status = document.getElementById('balanceStatus');
    if(totD === totC && totD > 0) {
        status.className = 'badge bg-success';
        status.innerText = 'Balance';
    } else {
        status.className = 'badge bg-danger';
        status.innerText = 'Not Balance';
    }
}

// Init 2 rows
window.onload = function() {
    addRow(); addRow();
};
</script>

<?php render_footer(); ?>
