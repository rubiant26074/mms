<?php
// modules/sales/orders/index.php
if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('sales_view')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=dashboard';</script>";
    exit;
}

render_header("Sales Order (SO)");
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
if (function_exists('mms_ensure_sales_orders_fulfillment_source_column')) {
    mms_ensure_sales_orders_fulfillment_source_column($pdo);
}
if (function_exists('mms_ensure_sales_orders_client_signature_columns')) {
    mms_ensure_sales_orders_client_signature_columns($pdo);
}
$send_so_ttd_wa = static function (PDO $pdo_conn, int $so_id): array {
    try {
        $stmt_so = $pdo_conn->prepare("SELECT so.so_number, c.name AS cust_name, c.phone AS cust_phone
                                       FROM sales_orders so
                                       JOIN customers c ON c.id = so.customer_id
                                       WHERE so.id = ?
                                       LIMIT 1");
        $stmt_so->execute([$so_id]);
        $so = $stmt_so->fetch(PDO::FETCH_ASSOC);
        if (!$so) return ['ok' => false, 'error' => 'SO tidak ditemukan.'];
        if (empty($so['cust_phone'])) return ['ok' => false, 'error' => 'Nomor HP customer kosong.'];
        if (!function_exists('build_public_so_url') || !function_exists('send_wa_fonte')) {
            return ['ok' => false, 'error' => 'Helper WA/Public link SO tidak tersedia.'];
        }
        $public_link = build_public_so_url($so_id, 24 * 14);
        if ($public_link === '') return ['ok' => false, 'error' => 'Link public SO tidak tersedia.'];
        $msg = "Yth. {$so['cust_name']},\n";
        $msg .= "Sales Order {$so['so_number']} kami kirim untuk konfirmasi.\n";
        $msg .= "Silakan buka link berikut dari HP/Tablet untuk melihat SO dan tanda tangan pada area TTD Customer (klik tombol TTD):\n{$public_link}\n";
        $msg .= "Terima kasih.";
        return send_wa_fonte($so['cust_phone'], $msg, $public_link);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
};

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';

// --- LOGIKA ACTION WORKFLOW ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    $mutation_actions = ['submit', 'approve', 'reject', 'cancel', 'mark_sent', 'resend_sign_wa'];
    if (in_array($action, $mutation_actions, true)) {
        $csrf_req = $_GET['csrf'] ?? $_POST['csrf'] ?? '';
        if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
            echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=sales-so';</script>";
            exit;
        }
        if ($id <= 0) {
            echo "<script>alert('Request tidak valid.'); window.location='index.php?page=sales-so';</script>";
            exit;
        }
    }

    // 1. SUBMIT (Staff mengajukan ke Manager)
    if ($action == 'submit') {
        if (!has_permission('sales_so_manage')) {
            echo "<script>alert('Akses Ditolak!'); window.location='index.php?page=sales-so';</script>";
            exit;
        }
        $pdo->prepare("UPDATE sales_orders SET status='waiting_approval' WHERE id=? AND status='draft'")->execute([$id]);
        
        // Trigger Notifikasi ke Manager
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'sales.so.submit',
                'Approval SO Baru',
                "SO #$id diajukan dan menunggu persetujuan Anda.",
                "index.php?page=sales-so&status=waiting_approval",
                'warning',
                ['permission_slug' => 'sales_so_approve']
            );
        }

        echo "<script>alert('Sales Order diajukan untuk Approval.'); window.location='index.php?page=sales-so';</script>";
    }

    // 2. APPROVE (Manager menyetujui)
    if ($action == 'approve') {
        if (!has_permission('sales_so_approve')) {
            echo "<script>alert('Akses Ditolak! Anda tidak memiliki izin Manager.'); window.location='index.php?page=sales-so';</script>";
            exit;
        }

        // Update status jadi Confirmed & Catat Siapa yang approve
        $stmt = $pdo->prepare("UPDATE sales_orders SET status='confirmed', approved_by=?, approved_at=NOW() WHERE id=? AND status='waiting_approval'");
        $stmt->execute([$_SESSION['user_id'], $id]);
        
        if ($stmt->rowCount() > 0) {
            // --- TRIGGER NOTIFIKASI BALIK KE SALES STAFF ---
            if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'sales.so.approve',
                    'SO Disetujui',
                    "Sales Order #$id telah disetujui. Lanjutkan pembuatan SPK Produksi.",
                    "index.php?page=ppic-spk&action=create&so_id=$id",
                    'success',
                    ['permission_slug' => 'ppic_spk_manage']
                );
            }

            echo "<script>alert('Sales Order berhasil di-Approve (Confirmed)! Siap untuk produksi.'); window.location='index.php?page=sales-so';</script>";
        } else {
            echo "<script>alert('Gagal Approve. Pastikan status SO adalah Waiting Approval.'); window.location='index.php?page=sales-so';</script>";
        }
    }

    // 3. REJECT (Manager menolak, kembali ke Draft untuk revisi)
    if ($action == 'reject') {
        if (!has_permission('sales_so_approve')) {
            echo "<script>alert('Akses Ditolak!'); window.location='index.php?page=sales-so';</script>";
            exit;
        }
        $pdo->prepare("UPDATE sales_orders SET status='rejected' WHERE id=? AND status='waiting_approval'")->execute([$id]);
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'sales.so.reject',
                'SO Ditolak',
                "Sales Order #$id ditolak dan perlu revisi.",
                "index.php?page=sales-so&action=edit&id=$id",
                'danger',
                ['permission_slug' => 'sales_so_manage']
            );
        }
        echo "<script>alert('Sales Order ditolak (Rejected). Silakan revisi.'); window.location='index.php?page=sales-so';</script>";
    }
    
    // 4. CANCEL (Batalkan Order Total)
    if ($action == 'cancel') {
        if (!has_permission('sales_so_manage')) {
             echo "<script>alert('Akses Ditolak!'); window.location='index.php?page=sales-so';</script>";
             exit;
        }
        $stmt_status = $pdo->prepare("SELECT status FROM sales_orders WHERE id = ? LIMIT 1");
        $stmt_status->execute([$id]);
        $cur_status = (string)$stmt_status->fetchColumn();
        if (!in_array($cur_status, ['draft', 'waiting_approval', 'confirmed'], true)) {
            echo "<script>alert('SO tidak bisa dibatalkan pada status saat ini.'); window.location='index.php?page=sales-so';</script>";
            exit;
        }
        $pdo->prepare("UPDATE sales_orders SET status='cancelled' WHERE id=?")->execute([$id]);
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'sales.so.cancel',
                'SO Dibatalkan',
                "Sales Order #$id dibatalkan.",
                "index.php?page=sales-so",
                'warning',
                ['permission_slug' => 'sales_so_approve']
            );
        }
        echo "<script>alert('Sales Order dibatalkan.'); window.location='index.php?page=sales-so';</script>";
    }

    // 5. Submit ke Client (WA TTD)
    if ($action == 'mark_sent') {
        if (!has_permission('sales_so_manage')) {
            echo "<script>alert('Akses Ditolak!'); window.location='index.php?page=sales-so';</script>";
            exit;
        }
        $stmt_upd = $pdo->prepare("UPDATE sales_orders SET sent_to_client_at=NOW(), sent_to_client_by=? WHERE id=? AND status IN ('confirmed','in_production','delivered','completed')");
        $stmt_upd->execute([$_SESSION['user_id'] ?? null, $id]);
        $wa_res = $send_so_ttd_wa($pdo, $id);
        $wa_note = empty($wa_res['ok'])
            ? "\\nWA customer gagal dikirim: " . addslashes((string)($wa_res['error'] ?? 'unknown'))
            : "\\nWA customer berhasil dikirim (Fonte).";
        echo "<script>alert('SO dikirim ke client untuk TTD.{$wa_note}'); window.location='index.php?page=sales-so';</script>";
    }

    // 6. Resend WA TTD SO
    if ($action == 'resend_sign_wa') {
        if (!has_permission('sales_so_manage')) {
            echo "<script>alert('Akses Ditolak!'); window.location='index.php?page=sales-so';</script>";
            exit;
        }
        $stmt_chk = $pdo->prepare("SELECT status, client_signed_at FROM sales_orders WHERE id=? LIMIT 1");
        $stmt_chk->execute([$id]);
        $so_chk = $stmt_chk->fetch(PDO::FETCH_ASSOC);
        $allowed = ['confirmed','in_production','delivered','completed'];
        if (!$so_chk || !in_array((string)($so_chk['status'] ?? ''), $allowed, true)) {
            echo "<script>alert('Resend WA TTD hanya untuk SO yang sudah approved/aktif.'); window.location='index.php?page=sales-so';</script>";
        } elseif (!empty($so_chk['client_signed_at'])) {
            echo "<script>alert('TTD client sudah ada. WA TTD tidak perlu dikirim ulang.'); window.location='index.php?page=sales-so';</script>";
        } else {
            $wa_res = $send_so_ttd_wa($pdo, $id);
            $wa_msg = empty($wa_res['ok'])
                ? "WA customer gagal dikirim: " . addslashes((string)($wa_res['error'] ?? 'unknown'))
                : "WA customer berhasil dikirim ulang (Fonte).";
            echo "<script>alert('{$wa_msg}'); window.location='index.php?page=sales-so';</script>";
        }
    }
}
?>

<style>
.so-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 118px;
    padding: .38rem .75rem;
    border-radius: 999px;
    border: 1px solid transparent;
    font-size: .7rem;
    font-weight: 700;
    line-height: 1;
    letter-spacing: .02em;
    text-transform: uppercase;
    white-space: nowrap;
}
.so-status-badge.is-draft {
    background: #eef2f7;
    border-color: #d8e0ea;
    color: #475569;
}
.so-status-badge.is-waiting {
    background: #fff7db;
    border-color: #f6d365;
    color: #8a5a00;
}
.so-status-badge.is-confirmed {
    background: #e8f1ff;
    border-color: #9fc2ff;
    color: #0b57d0;
}
.so-status-badge.is-production {
    background: #e8f8ff;
    border-color: #86d7f7;
    color: #0f6f8f;
}
.so-status-badge.is-delivered {
    background: #ede9fe;
    border-color: #c4b5fd;
    color: #5b21b6;
}
.so-status-badge.is-completed {
    background: #e8f8ef;
    border-color: #86d6a7;
    color: #177245;
}
.so-status-badge.is-cancelled {
    background: #edf0f3;
    border-color: #c7d0db;
    color: #334155;
}
.so-status-badge.is-rejected {
    background: #ffe9ea;
    border-color: #ffb4bb;
    color: #b42318;
}
.so-status-badge.is-unknown {
    background: #f8fafc;
    border-color: #dbe2ea;
    color: #334155;
}
</style>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-cart-check"></i> Sales Order</h3>
        <p class="text-muted">Order masuk dari customer (Basis Produksi).</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=sales-so&action=create" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-lg"></i> Input SO Manual
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="so-filter-form">
            <input type="hidden" name="page" value="sales-so">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari No. SO / Customer / No. PO..." value="<?= $esc($search_key) ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="draft" <?= $filter_status=='draft'?'selected':'' ?>>Draft</option>
                    <option value="waiting_approval" <?= $filter_status=='waiting_approval'?'selected':'' ?>>Waiting Approval</option>
                    <option value="confirmed" <?= $filter_status=='confirmed'?'selected':'' ?>>Confirmed</option>
                    <option value="in_production" <?= $filter_status=='in_production'?'selected':'' ?>>In Production</option>
                    <option value="delivered" <?= $filter_status=='delivered'?'selected':'' ?>>Delivered</option>
                    <option value="completed" <?= $filter_status=='completed'?'selected':'' ?>>Completed</option>
                    <option value="cancelled" <?= $filter_status=='cancelled'?'selected':'' ?>>Cancelled</option>
                    <option value="rejected" <?= $filter_status=='rejected'?'selected':'' ?>>Rejected</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=sales-so" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
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
                        <th>No. SO</th>
                        <th>Tgl SO</th>
                        <th>Customer</th>
                        <th>No. PO / Est. Kirim</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Pemenuhan</th>
                        <th class="text-center" width="180">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT so.*, COALESCE(so.fulfillment_source, 'spk') AS fulfillment_source, c.name as customer_name 
                            FROM sales_orders so 
                            JOIN customers c ON so.customer_id = c.id 
                            WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        $sql .= " AND so.status = ?";
                        $params[] = $filter_status;
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (so.so_number LIKE ? OR c.name LIKE ? OR so.cust_po_number LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY so.id DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    while ($row = $stmt->fetch()):
                        $status_value = (string)($row['status'] ?? '');
                        $status_badge_class = match($status_value) {
                            'draft' => 'is-draft',
                            'waiting_approval' => 'is-waiting',
                            'confirmed' => 'is-confirmed',
                            'in_production' => 'is-production',
                            'delivered' => 'is-delivered',
                            'completed' => 'is-completed',
                            'cancelled' => 'is-cancelled',
                            'rejected' => 'is-rejected',
                            default => 'is-unknown'
                        };
                        $status_label = strtoupper(str_replace('_', ' ', $status_value));
                        $fulfillment_source = function_exists('mms_normalize_sales_order_fulfillment_source')
                            ? mms_normalize_sales_order_fulfillment_source($row['fulfillment_source'] ?? 'spk')
                            : 'spk';
                        $fulfill_badge = $fulfillment_source === 'fg_stock'
                            ? 'bg-warning text-dark'
                            : 'bg-secondary';
                        $fulfill_label = function_exists('mms_sales_order_fulfillment_label')
                            ? mms_sales_order_fulfillment_label($fulfillment_source)
                            : ($fulfillment_source === 'fg_stock' ? 'FG Stock' : 'SPK');
                        
                        $po_num = !empty($row['cust_po_number']) ? clean($row['cust_po_number']) : '-';
                        $del_date = !empty($row['delivery_date']) ? date('d/m/y', strtotime($row['delivery_date'])) : '-';
                        
                        // Cek Permission
                        $can_manage = has_permission('sales_so_manage');
                        $can_approve = has_permission('sales_so_approve');
                    ?>
                    <tr>
                        <td>
                            <strong class="text-primary"><?= clean($row['so_number']) ?></strong>
                        </td>
                        <td><?= date('d/m/Y', strtotime($row['so_date'])) ?></td>
                        <td><?= clean($row['customer_name']) ?></td>
                        <td>
                            <small class="d-block fw-bold text-dark">PO: <?= $po_num ?></small>
                            <small class="d-block text-muted">Est. Kirim: <?= $del_date ?></small>
                        </td>
                        <td class="text-end fw-bold">Rp <?= number_format($row['grand_total'], 0, ',', '.') ?></td>
                        <td class="text-center">
                            <span class="so-status-badge <?= $status_badge_class ?>"><?= $esc($status_label) ?></span>
                            <?php if (!empty($row['client_signed_at'])): ?>
                                <div><small class="text-success fw-semibold">TTD Client: <?= date('d/m/Y H:i', strtotime((string)$row['client_signed_at'])) ?></small></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= $fulfill_badge ?> rounded-pill px-2" title="<?= $esc($fulfill_label) ?>">
                                <?= $fulfillment_source === 'fg_stock' ? 'FG Stock' : 'SPK' ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <!-- Tombol Print (Selalu Ada) -->
                                <a href="index.php?page=sales-so&action=print&id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="<?= $row['status']=='confirmed' ? 'Cetak SO Resmi' : 'Preview Draft' ?>">
                                    <i class="bi <?= ($row['status']=='confirmed' || $row['status']=='in_production') ? 'bi-printer-fill' : 'bi-file-earmark-pdf' ?>"></i>
                                </a>
                                
                                <!-- 1. Draft/Rejected: Edit, Submit, Hapus -->
                                <?php if(($row['status'] == 'draft' || $row['status'] == 'rejected') && $can_manage): ?>
                                    <a href="index.php?page=sales-so&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning text-dark" title="Edit"><i class="bi bi-pencil"></i></a>
                                    
                                    <a href="index.php?page=sales-so&action=submit&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-success" title="Ajukan Approval" onclick="return confirm('Ajukan SO ini ke Manager?')">
                                        <i class="bi bi-send"></i>
                                    </a>
                                    
                                    <a href="index.php?page=sales-so&action=delete&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus Sales Order ini?')" title="Hapus"><i class="bi bi-trash"></i></a>
                                <?php endif; ?>

                                <!-- 2. Waiting Approval: Approve/Reject (Hanya Manager) -->
                                <?php if($row['status'] == 'waiting_approval' && $can_approve): ?>
                                    <a href="index.php?page=sales-so&action=approve&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" 
                                       class="btn btn-sm btn-primary" 
                                       title="Approve SO"
                                       onclick="return confirm('Approve Sales Order ini? Status akan berubah menjadi Confirmed.')">
                                        <i class="bi bi-check-lg"></i>
                                    </a>
                                    <a href="index.php?page=sales-so&action=reject&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" 
                                       class="btn btn-sm btn-danger" 
                                       title="Reject SO"
                                       onclick="return confirm('Tolak/Reject Sales Order ini?')">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if(in_array((string)$row['status'], ['confirmed','in_production','delivered','completed'], true) && $can_manage): ?>
                                    <a href="index.php?page=sales-so&action=mark_sent&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-primary" title="Submit ke Client (WA TTD)" onclick="return confirm('Kirim SO ke client via WA untuk TTD?')">
                                        <i class="bi bi-send-fill"></i>
                                    </a>
                                    <?php if (empty($row['client_signed_at'])): ?>
                                        <a href="index.php?page=sales-so&action=resend_sign_wa&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-outline-primary" title="Kirim Ulang WA TTD" onclick="return confirm('Kirim ulang WA TTD ke client?')">
                                            <i class="bi bi-send-dash"></i>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
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
    const form = document.getElementById('so-filter-form');
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
