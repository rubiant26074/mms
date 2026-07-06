<?php
// modules/admin/reset/index.php
render_header("System Reset");

if (!has_permission('admin_reset_db')) {
    echo "<div class='alert alert-danger'>Akses Ditolak!</div>";
    render_footer();
    exit;
}

$success = null;
$error = null;
$reset_log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_mms_csrf_token($csrf)) {
        $error = "Token keamanan tidak valid. Silakan refresh halaman dan coba lagi.";
    } else {
        $password = (string)($_POST['admin_password'] ?? '');
        $reset_items = isset($_POST['reset_items']);

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $error = "Password Admin salah! Tindakan dibatalkan.";
        } else {
            try {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                $reset_log[] = "FK OFF";

                $tables = [
                    // SALES
                    'quotations', 'quotation_items',
                    'sales_orders', 'sales_order_items',
                    // PPIC & PROD
                    'spk', 'spk_materials',
                    'purchase_requests', 'purchase_request_items',
                    'production_assignments', 'production_logs', 'production_partlist_progress',
                    // PROCUREMENT
                    'purchase_orders', 'purchase_order_items',
                    // WAREHOUSE
                    'goods_receipts', 'goods_receipt_items',
                    'material_issues', 'material_issue_items',
                    'delivery_notes', 'delivery_note_items',
                    // QC
                    'qc_incoming', 'qc_incoming_items',
                    'qc_production', 'ncr',
                    // FINANCE & ACCOUNTING
                    'invoices', 'invoice_payments',
                    'supplier_bills', 'supplier_payments',
                    'journals', 'journal_items',
                    // HRD & LOGS
                    'attendance', 'payrolls',
                    'system_logs'
                ];

                foreach ($tables as $table) {
                    $check = $pdo->query("SHOW TABLES LIKE '$table'");
                    if ($check && $check->rowCount() > 0) {
                        $pdo->exec("TRUNCATE TABLE `$table`");
                        $reset_log[] = "TRUNCATE $table OK";
                    } else {
                        $reset_log[] = "SKIP $table (not found)";
                    }
                }

                if ($reset_items) {
                    $pdo->exec("UPDATE items SET current_stock = 0");
                    $pdo->exec("UPDATE coa SET current_balance = opening_balance");
                    $reset_log[] = "RESET items.current_stock OK";
                    $reset_log[] = "RESET coa.current_balance OK";
                }

                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $reset_log[] = "FK ON";

                $success = "Database Transaksi berhasil di-reset bersih! Sistem siap untuk Go Live.";
            } catch (Exception $e) {
                try {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                } catch (Exception $inner) {
                    // ignore
                }
                $error = "Gagal Reset: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill"></i> Factory Reset / Pembersihan Data</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= $success ?></div>
                    <?php if (!empty($reset_log)): ?>
                        <details class="mt-2">
                            <summary class="small text-muted">Detail reset</summary>
                            <pre class="small mb-0"><?= htmlspecialchars(implode("\n", $reset_log), ENT_QUOTES, 'UTF-8') ?></pre>
                        </details>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><i class="bi bi-x-circle"></i> <?= $error ?></div>
                    <?php if (!empty($reset_log)): ?>
                        <details class="mt-2">
                            <summary class="small text-muted">Detail reset</summary>
                            <pre class="small mb-0"><?= htmlspecialchars(implode("\n", $reset_log), ENT_QUOTES, 'UTF-8') ?></pre>
                        </details>
                    <?php endif; ?>
                <?php endif; ?>

                <p class="text-dark">Fitur ini digunakan untuk <strong>menghapus seluruh data transaksi</strong> (Penawaran, SO, SPK, PO, Penerimaan, QC, Invoice, Jurnal, Gaji) untuk memulai sistem dari nol (misal: setelah testing selesai).</p>

                <div class="alert alert-warning">
                    <strong>Data yang AKAN DIHAPUS PERMANEN:</strong>
                    <ul class="mb-0 small">
                        <li>Semua Quotation, Sales Order</li>
                        <li>Semua SPK, Purchase Request, Purchase Order</li>
                        <li>Semua Data Penerimaan Barang, ITR, Surat Jalan, QC</li>
                        <li>Semua Invoice, Tagihan Supplier, Pembayaran, Jurnal</li>
                        <li>Data Absensi & Gaji Karyawan</li>
                        <li>Log Aktivitas Sistem</li>
                    </ul>
                </div>

                <div class="alert alert-info">
                    <strong>Data yang AMAN (Tidak Dihapus):</strong>
                    <ul class="mb-0 small">
                        <li>User, Role, Hak Akses</li>
                        <li>Identitas Perusahaan</li>
                        <li>Master Customer & Supplier</li>
                        <li>Master Barang, Mesin, BOM (Data master aman)</li>
                        <li>Chart of Accounts (COA)</li>
                    </ul>
                </div>

                <form method="POST" id="resetForm" action="index.php?page=admin-reset">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(mms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="confirm_reset" value="1">
                    <div class="form-check mb-3 p-3 border rounded bg-light">
                        <input class="form-check-input" type="checkbox" name="reset_items" id="resetItems" value="1">
                        <label class="form-check-label fw-bold" for="resetItems">
                            Nol-kan Stok Barang & Saldo Akun?
                        </label>
                        <div class="form-text text-danger">Centang jika ingin stok barang kembali ke 0 dan saldo akun kembali ke saldo awal.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Konfirmasi Password Admin:</label>
                        <input type="password" name="admin_password" class="form-control" required placeholder="Masukkan password Anda saat ini...">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger btn-lg fw-bold" id="resetSubmit">
                            <i class="bi bi-trash3-fill"></i> RESET DATABASE SEKARANG
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
