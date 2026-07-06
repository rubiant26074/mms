<?php
// modules/accounting/coa/form.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('acc_coa_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=acc-coa';</script>";
    exit;
}

$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = $id ? true : false;
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$data = ['account_code'=>'', 'account_name'=>'', 'account_type'=>'asset', 'normal_balance'=>'debit', 'opening_balance'=>0];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM coa WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    if(!$data) die("Akun tidak ditemukan");
}

// PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    } else {
        $code = clean($_POST['account_code']);
        $name = clean($_POST['account_name']);
        $type = clean($_POST['account_type']);
        $norm = clean($_POST['normal_balance']);
        $open = clean($_POST['opening_balance']);

        try {
            if ($is_edit) {
                // Cek duplikat
                $check = $pdo->prepare("SELECT COUNT(*) FROM coa WHERE account_code = ? AND id != ?");
                $check->execute([$code, $id]);
                if($check->fetchColumn() > 0) throw new Exception("Kode Akun sudah digunakan!");

                $sql = "UPDATE coa SET account_code=?, account_name=?, account_type=?, normal_balance=?, opening_balance=? WHERE id=?";
                $pdo->prepare($sql)->execute([$code, $name, $type, $norm, $open, $id]);
            } else {
                $check = $pdo->prepare("SELECT COUNT(*) FROM coa WHERE account_code = ?");
                $check->execute([$code]);
                if($check->fetchColumn() > 0) throw new Exception("Kode Akun sudah digunakan!");

                // Saldo awal masuk ke current balance juga
                $sql = "INSERT INTO coa (account_code, account_name, account_type, normal_balance, opening_balance, current_balance) VALUES (?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$code, $name, $type, $norm, $open, $open]);
            }
            echo "<script>alert('Data Akun tersimpan!'); window.location='index.php?page=acc-coa';</script>";
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

render_header($is_edit ? "Edit Akun" : "Tambah Akun Baru");
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">Form Chart of Accounts</div>
            <div class="card-body">
                <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label>Kode Akun <span class="text-danger">*</span></label>
                        <input type="text" name="account_code" class="form-control fw-bold" value="<?= $data['account_code'] ?>" required placeholder="Contoh: 1-1100">
                    </div>
                    <div class="mb-3">
                        <label>Nama Akun <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" class="form-control" value="<?= $data['account_name'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Tipe Akun</label>
                        <select name="account_type" class="form-select">
                            <option value="asset" <?= $data['account_type']=='asset'?'selected':'' ?>>ASSET (Harta)</option>
                            <option value="liability" <?= $data['account_type']=='liability'?'selected':'' ?>>LIABILITY (Kewajiban)</option>
                            <option value="equity" <?= $data['account_type']=='equity'?'selected':'' ?>>EQUITY (Modal)</option>
                            <option value="revenue" <?= $data['account_type']=='revenue'?'selected':'' ?>>REVENUE (Pendapatan)</option>
                            <option value="expense" <?= $data['account_type']=='expense'?'selected':'' ?>>EXPENSE (Beban)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Saldo Normal</label>
                        <select name="normal_balance" class="form-select">
                            <option value="debit" <?= $data['normal_balance']=='debit'?'selected':'' ?>>Debit</option>
                            <option value="credit" <?= $data['normal_balance']=='credit'?'selected':'' ?>>Credit</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Saldo Awal (Opening)</label>
                        <input type="number" name="opening_balance" class="form-control" value="<?= $data['opening_balance'] ?>" step="0.01">
                        <div class="form-text">Masukkan nilai saldo awal jika migrasi data lama.</div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php?page=acc-coa" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan Akun</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
