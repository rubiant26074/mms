<?php
// modules/finance/cash/form.php
require_once __DIR__ . '/common.php';

$theme_q = !empty($_GET['theme']) ? '&theme=' . urlencode((string)$_GET['theme']) : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

try {
    fin_cash_ensure_schema($pdo);
} catch (Exception $e) {
    render_header("Cash / Chasier");
    echo "<div class='alert alert-danger m-3'>Gagal menyiapkan tabel Cash/Chasier.</div>";
    render_footer();
    exit;
}

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$is_edit = ($id !== null);

$data = [
    'expense_number' => 'AUTO',
    'transaction_type' => 'expense',
    'coa_id' => null,
    'cash_coa_id' => null,
    'expense_date' => date('Y-m-d'),
    'category' => '',
    'description' => '',
    'amount' => 0,
    'payment_method' => 'Cash',
    'reference_no' => '',
    'vendor_name' => '',
    'notes' => '',
    'status' => 'draft',
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM finance_cash_expenses WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo "<script>alert('Data transaksi tidak ditemukan.'); window.location='index.php?page=fin-cash{$theme_q}';</script>";
        exit;
    }
    if ((string)$row['status'] !== 'draft') {
        echo "<script>alert('Hanya transaksi status draft yang bisa diedit.'); window.location='index.php?page=fin-cash{$theme_q}';</script>";
        exit;
    }
    $data = array_merge($data, $row);
}

$expense_accounts = $pdo->query("SELECT id, account_code, account_name FROM coa WHERE account_type = 'expense' AND (is_active = 1 OR is_active IS NULL) ORDER BY account_code ASC")->fetchAll(PDO::FETCH_ASSOC);
$revenue_accounts = $pdo->query("SELECT id, account_code, account_name FROM coa WHERE account_type = 'revenue' AND (is_active = 1 OR is_active IS NULL) ORDER BY account_code ASC")->fetchAll(PDO::FETCH_ASSOC);
$cash_accounts = $pdo->query("SELECT id, account_code, account_name FROM coa WHERE account_type = 'asset' AND (is_active = 1 OR is_active IS NULL) ORDER BY account_code ASC")->fetchAll(PDO::FETCH_ASSOC);

if (empty($data['coa_id'])) {
    $data['coa_id'] = ((string)$data['transaction_type'] === 'income')
        ? fin_cash_revenue_coa_id($pdo)
        : fin_cash_expense_coa_id($pdo);
}
if (empty($data['cash_coa_id'])) {
    $data['cash_coa_id'] = fin_cash_default_cash_coa_id($pdo, (string)$data['payment_method']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    }

    $transaction_type = strtolower(trim((string)($_POST['transaction_type'] ?? 'expense')));
    $coa_id = (int)($_POST['coa_id'] ?? 0);
    $cash_coa_id = (int)($_POST['cash_coa_id'] ?? 0);
    $expense_date = trim((string)($_POST['expense_date'] ?? ''));
    $category = clean($_POST['category'] ?? '');
    $description = clean($_POST['description'] ?? '');
    $payment_method = trim((string)($_POST['payment_method'] ?? 'Cash'));
    $reference_no = clean($_POST['reference_no'] ?? '');
    $vendor_name = clean($_POST['vendor_name'] ?? '');
    $notes = clean($_POST['notes'] ?? '');
    $amount_raw = (string)($_POST['amount'] ?? '0');
    $amount = (float)str_replace(',', '.', preg_replace('/[^\d,.\-]/', '', $amount_raw));

    if (!in_array($transaction_type, ['expense', 'income'], true)) {
        $transaction_type = 'expense';
    }

    $allowed_payment = ['Cash', 'Transfer Bank', 'E-Wallet', 'Lainnya'];
    if (!in_array($payment_method, $allowed_payment, true)) {
        $payment_method = 'Cash';
    }

    if (!isset($error)) {
        if ($expense_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expense_date)) {
            $error = "Tanggal transaksi tidak valid.";
        } elseif ($category === '') {
            $error = "Kategori wajib diisi.";
        } elseif ($description === '') {
            $error = "Deskripsi wajib diisi.";
        } elseif ($amount <= 0) {
            $error = "Nominal transaksi harus lebih dari 0.";
        }
    }

    if (!isset($error)) {
        $allowed_counter_type = ($transaction_type === 'income') ? ['revenue'] : ['expense'];
        if (!fin_cash_is_coa_type_valid($pdo, $coa_id, $allowed_counter_type)) {
            $error = "Akun lawan tidak valid untuk jenis transaksi yang dipilih.";
        } elseif (!fin_cash_is_coa_type_valid($pdo, $cash_coa_id, ['asset'])) {
            $error = "Akun kas/bank tidak valid.";
        }
    }

    $data['transaction_type'] = $transaction_type;
    $data['coa_id'] = $coa_id;
    $data['cash_coa_id'] = $cash_coa_id;
    $data['expense_date'] = $expense_date !== '' ? $expense_date : $data['expense_date'];
    $data['category'] = $category;
    $data['description'] = $description;
    $data['payment_method'] = $payment_method;
    $data['reference_no'] = $reference_no;
    $data['vendor_name'] = $vendor_name;
    $data['notes'] = $notes;
    $data['amount'] = $amount;

    if (!isset($error)) {
        try {
            $pdo->beginTransaction();

            if ($is_edit) {
                $stmt_chk = $pdo->prepare("SELECT status FROM finance_cash_expenses WHERE id = ? FOR UPDATE");
                $stmt_chk->execute([$id]);
                $status = (string)$stmt_chk->fetchColumn();
                if ($status !== 'draft') {
                    throw new Exception("Hanya transaksi status draft yang bisa diubah.");
                }

                $sql = "UPDATE finance_cash_expenses
                        SET transaction_type = ?, coa_id = ?, cash_coa_id = ?, expense_date = ?, category = ?, description = ?, amount = ?,
                            payment_method = ?, reference_no = ?, vendor_name = ?, notes = ?, updated_at = NOW()
                        WHERE id = ?";
                $pdo->prepare($sql)->execute([
                    $transaction_type,
                    $coa_id,
                    $cash_coa_id,
                    $expense_date,
                    $category,
                    $description,
                    $amount,
                    $payment_method,
                    ($reference_no !== '' ? $reference_no : null),
                    ($vendor_name !== '' ? $vendor_name : null),
                    ($notes !== '' ? $notes : null),
                    $id
                ]);
            } else {
                $expense_number = '';
                for ($i = 0; $i < 5; $i++) {
                    $candidate = fin_cash_generate_number($pdo, $expense_date, $transaction_type);
                    $stmt_dup = $pdo->prepare("SELECT COUNT(*) FROM finance_cash_expenses WHERE expense_number = ?");
                    $stmt_dup->execute([$candidate]);
                    if ((int)$stmt_dup->fetchColumn() === 0) {
                        $expense_number = $candidate;
                        break;
                    }
                }
                if ($expense_number === '') {
                    throw new Exception("Gagal generate nomor transaksi. Silakan ulangi.");
                }

                $sql = "INSERT INTO finance_cash_expenses
                        (expense_number, transaction_type, coa_id, cash_coa_id, expense_date, category, description, amount, payment_method, reference_no, vendor_name, status, notes, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?)";
                $pdo->prepare($sql)->execute([
                    $expense_number,
                    $transaction_type,
                    $coa_id,
                    $cash_coa_id,
                    $expense_date,
                    $category,
                    $description,
                    $amount,
                    $payment_method,
                    ($reference_no !== '' ? $reference_no : null),
                    ($vendor_name !== '' ? $vendor_name : null),
                    ($notes !== '' ? $notes : null),
                    (int)($_SESSION['user_id'] ?? 0) ?: null,
                ]);
            }

            $pdo->commit();
            echo "<script>alert('Data transaksi berhasil disimpan.'); window.location='index.php?page=fin-cash{$theme_q}';</script>";
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Gagal simpan: " . $e->getMessage();
        }
    }
}

render_header($is_edit ? "Edit Transaksi Cash/Chasier" : "Input Transaksi Cash/Chasier");
?>

<form method="POST">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(mms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $esc($error) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">Info Transaksi</div>
                <div class="card-body">
                    <div class="mb-2">
                        <label>No. Bukti</label>
                        <input type="text" class="form-control fw-bold" value="<?= $esc($data['expense_number']) ?>" readonly>
                    </div>
                    <div class="mb-2">
                        <label>Jenis Transaksi <span class="text-danger">*</span></label>
                        <select name="transaction_type" id="transactionType" class="form-select" required>
                            <option value="expense" <?= ((string)$data['transaction_type'] === 'expense' ? 'selected' : '') ?>>Pengeluaran</option>
                            <option value="income" <?= ((string)$data['transaction_type'] === 'income' ? 'selected' : '') ?>>Pemasukan</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label>Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="expense_date" class="form-control" value="<?= $esc($data['expense_date']) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label>Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="category" class="form-control" value="<?= $esc($data['category']) ?>" required placeholder="Contoh: Operasional / Pendapatan Lain">
                    </div>
                    <div class="mb-2">
                        <label>Metode Pembayaran</label>
                        <select name="payment_method" class="form-select">
                            <?php
                            $methods = ['Cash', 'Transfer Bank', 'E-Wallet', 'Lainnya'];
                            foreach ($methods as $method):
                            ?>
                                <option value="<?= $esc($method) ?>" <?= ((string)$data['payment_method'] === $method ? 'selected' : '') ?>><?= $esc($method) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label>Nominal (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control text-end" value="<?= $esc((string)((float)$data['amount'])) ?>" min="0" step="0.01" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">Akun & Detail</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Akun Lawan <span class="text-danger">*</span></label>
                            <select name="coa_id" id="counterCoaSelect" class="form-select" required data-selected="<?= (int)$data['coa_id'] ?>">
                                <option value="">-- Pilih Akun --</option>
                            </select>
                            <small class="text-muted">Pengeluaran: akun expense, Pemasukan: akun revenue.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Akun Kas / Bank <span class="text-danger">*</span></label>
                            <select name="cash_coa_id" class="form-select" required>
                                <option value="">-- Pilih Akun Kas/Bank --</option>
                                <?php foreach ($cash_accounts as $acc): ?>
                                    <option value="<?= (int)$acc['id'] ?>" <?= ((int)$data['cash_coa_id'] === (int)$acc['id'] ? 'selected' : '') ?>>
                                        <?= $esc($acc['account_code'] . ' - ' . $acc['account_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label id="counterpartyLabel">Relasi (Vendor / Sumber)</label>
                            <input type="text" name="vendor_name" class="form-control" value="<?= $esc($data['vendor_name']) ?>" placeholder="Opsional">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>No. Referensi</label>
                            <input type="text" name="reference_no" class="form-control" value="<?= $esc($data['reference_no']) ?>" placeholder="Contoh: INV-001 / Bukti Transfer">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label id="descriptionLabel">Deskripsi Transaksi <span class="text-danger">*</span></label>
                        <textarea name="description" rows="4" class="form-control" required><?= $esc($data['description']) ?></textarea>
                    </div>
                    <div class="mb-0">
                        <label>Catatan Internal</label>
                        <textarea name="notes" rows="2" class="form-control"><?= $esc($data['notes']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-5">
        <a href="index.php?page=fin-cash<?= $theme_q ?>" class="btn btn-secondary">Kembali</a>
        <button type="submit" class="btn btn-primary px-4">Simpan Draft</button>
    </div>
</form>

<script>
(function () {
    const trx = document.getElementById('transactionType');
    const cp = document.getElementById('counterpartyLabel');
    const desc = document.getElementById('descriptionLabel');
    const counterSelect = document.getElementById('counterCoaSelect');
    if (!trx || !cp || !desc || !counterSelect) return;

    const expenseAccounts = <?= json_encode(array_map(static fn($a) => ['id' => (int)$a['id'], 'label' => $a['account_code'] . ' - ' . $a['account_name']], $expense_accounts), JSON_UNESCAPED_UNICODE) ?>;
    const revenueAccounts = <?= json_encode(array_map(static fn($a) => ['id' => (int)$a['id'], 'label' => $a['account_code'] . ' - ' . $a['account_name']], $revenue_accounts), JSON_UNESCAPED_UNICODE) ?>;

    const refillCounterCoa = () => {
        const selected = parseInt(counterSelect.getAttribute('data-selected') || counterSelect.value || '0', 10);
        const source = (trx.value === 'income') ? revenueAccounts : expenseAccounts;
        counterSelect.innerHTML = '<option value="">-- Pilih Akun --</option>';
        source.forEach(acc => {
            const opt = document.createElement('option');
            opt.value = String(acc.id);
            opt.textContent = acc.label;
            if (selected > 0 && selected === acc.id) opt.selected = true;
            counterSelect.appendChild(opt);
        });
        counterSelect.removeAttribute('data-selected');
    };

    const sync = () => {
        if (trx.value === 'income') {
            cp.textContent = 'Relasi (Pelanggan / Sumber Dana)';
            desc.innerHTML = 'Deskripsi Pemasukan <span class="text-danger">*</span>';
        } else {
            cp.textContent = 'Relasi (Vendor / Penerima)';
            desc.innerHTML = 'Deskripsi Pengeluaran <span class="text-danger">*</span>';
        }
        refillCounterCoa();
    };

    trx.addEventListener('change', sync);
    sync();
})();
</script>

<?php render_footer(); ?>
