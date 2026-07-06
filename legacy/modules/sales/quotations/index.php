<?php
// modules/sales/quotations/index.php
if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('sales_quotation_view')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=dashboard';</script>";
    exit;
}

render_header("Sales Quotation");
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
if (function_exists('mms_ensure_quotations_client_signature_columns')) {
    mms_ensure_quotations_client_signature_columns($pdo);
}
$send_quote_ttd_wa = static function (PDO $pdo_conn, int $quote_id): array {
    try {
        $stmt_q = $pdo_conn->prepare("SELECT q.quote_number, c.name as cust_name, c.phone as cust_phone
                                      FROM quotations q
                                      JOIN customers c ON c.id = q.customer_id
                                      WHERE q.id = ?
                                      LIMIT 1");
        $stmt_q->execute([$quote_id]);
        $q = $stmt_q->fetch(PDO::FETCH_ASSOC);
        if (!$q) return ['ok' => false, 'error' => 'Quotation tidak ditemukan.'];
        if (empty($q['cust_phone'])) return ['ok' => false, 'error' => 'Nomor HP customer kosong.'];
        if (!function_exists('build_public_quote_url') || !function_exists('send_wa_fonte')) {
            return ['ok' => false, 'error' => 'Helper WA/Public link tidak tersedia.'];
        }
        $public_link = build_public_quote_url($quote_id, 24 * 14);
        if ($public_link === '') return ['ok' => false, 'error' => 'Link public quotation tidak tersedia.'];

        $msg = "Yth. {$q['cust_name']},\n";
        $msg .= "Penawaran {$q['quote_number']} kami kirim untuk konfirmasi.\n";
        $msg .= "Silakan buka link berikut dari HP/Tablet untuk melihat penawaran dan tanda tangan pada area TTD Customer (klik tombol TTD):\n{$public_link}\n";
        $msg .= "Terima kasih.";
        return send_wa_fonte($q['cust_phone'], $msg, $public_link);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
};

// Filter & Search
$filter_status = isset($_GET['status']) ? clean($_GET['status']) : '';
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';

// LOGIK ACTION
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    $mutation_actions = ['submit', 'approve', 'reject', 'mark_sent', 'resend_sign_wa', 'won', 'lost', 'revise'];
    if (in_array($action, $mutation_actions, true)) {
        $csrf_req = $_GET['csrf'] ?? $_POST['csrf'] ?? '';
        if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
            echo "<script>alert('Permintaan tidak valid (CSRF).'); window.location='index.php?page=sales-quote';</script>";
            exit;
        }
        if ($id <= 0) {
            echo "<script>alert('Request tidak valid.'); window.location='index.php?page=sales-quote';</script>";
            exit;
        }
    }
    
    // 1. Submit for Approval
    if ($action == 'submit' && has_permission('sales_quotation_manage')) {
        $pdo->prepare("UPDATE quotations SET status='waiting_approval' WHERE id=? AND status='draft'")->execute([$id]);
        
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'sales.quotation.submit',
                'Approval Quotation Baru',
                "Quotation #$id diajukan dan menunggu persetujuan Anda.",
                "index.php?page=sales-quote&status=waiting_approval",
                'warning',
                ['permission_slug' => 'sales_quotation_approve']
            );
        }

        echo "<script>alert('Diajukan untuk approval.'); window.location='index.php?page=sales-quote';</script>";
    }
    
    // 2. Approve (UPDATED: Simpan approved_by)
    if ($action == 'approve' && has_permission('sales_quotation_approve')) {
        // Simpan ID user yang sedang login (Manager) ke kolom approved_by
        $stmt = $pdo->prepare("UPDATE quotations SET status='approved', approved_by=? WHERE id=? AND status='waiting_approval'");
        $stmt->execute([$_SESSION['user_id'], $id]);

        if ($stmt->rowCount() > 0) {
            // Notifikasi Balik ke Staff
            if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'sales.quotation.approve',
                    'Quotation Disetujui',
                    "Quotation #$id telah disetujui oleh Manager.",
                    "index.php?page=sales-quote&action=print&id=$id",
                    'success',
                    ['permission_slug' => 'sales_quotation_manage']
                );
            }
            echo "<script>alert('Approved.'); window.location='index.php?page=sales-quote';</script>";
        } else {
            echo "<script>alert('Gagal Approve. Status mungkin sudah berubah.'); window.location='index.php?page=sales-quote';</script>";
        }
    }
    
    // 3. Reject
    if ($action == 'reject' && has_permission('sales_quotation_approve')) {
        $pdo->prepare("UPDATE quotations SET status='rejected' WHERE id=? AND status='waiting_approval'")->execute([$id]);
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'sales.quotation.reject',
                'Quotation Ditolak',
                "Quotation #$id ditolak.",
                "index.php?page=sales-quote&action=edit&id=$id",
                'danger',
                ['permission_slug' => 'sales_quotation_manage']
            );
        }
        echo "<script>alert('Rejected.'); window.location='index.php?page=sales-quote';</script>";
    }

    // 4. Mark Sent
    if ($action == 'mark_sent' && has_permission('sales_quotation_manage')) {
        $stmt_sent = $pdo->prepare("UPDATE quotations SET status='sent', sent_to_client_at=NOW(), sent_to_client_by=? WHERE id=? AND status='approved'");
        $stmt_sent->execute([$_SESSION['user_id'] ?? null, $id]);
        $wa_note = '';
        if ($stmt_sent->rowCount() > 0) {
            $wa_res = $send_quote_ttd_wa($pdo, $id);
            $wa_note = empty($wa_res['ok'])
                ? "\\nWA customer gagal dikirim: " . addslashes((string)($wa_res['error'] ?? 'unknown'))
                : "\\nWA customer berhasil dikirim (Fonte).";
        }
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'sales.quotation.sent',
                'Quotation Terkirim',
                "Quotation #$id sudah dikirim ke customer.",
                "index.php?page=sales-quote&action=print&id=$id",
                'info',
                ['permission_slug' => 'sales_quotation_approve']
            );
        }
        echo "<script>alert('Status: Sent to Customer.{$wa_note}'); window.location='index.php?page=sales-quote';</script>";
    }

    // 4B. Resend WA TTD
    if ($action == 'resend_sign_wa' && has_permission('sales_quotation_manage')) {
        $stmt_chk = $pdo->prepare("SELECT status, client_signed_at FROM quotations WHERE id=? LIMIT 1");
        $stmt_chk->execute([$id]);
        $qchk = $stmt_chk->fetch(PDO::FETCH_ASSOC);
        if (!$qchk || (string)($qchk['status'] ?? '') !== 'sent') {
            echo "<script>alert('Resend WA hanya untuk quotation status SENT.'); window.location='index.php?page=sales-quote';</script>";
        } elseif (!empty($qchk['client_signed_at'])) {
            echo "<script>alert('TTD client sudah ada. WA TTD tidak perlu dikirim ulang.'); window.location='index.php?page=sales-quote';</script>";
        } else {
            $wa_res = $send_quote_ttd_wa($pdo, $id);
            $wa_note = empty($wa_res['ok'])
                ? "WA customer gagal dikirim: " . addslashes((string)($wa_res['error'] ?? 'unknown'))
                : "WA customer berhasil dikirim ulang (Fonte).";
            echo "<script>alert('{$wa_note}'); window.location='index.php?page=sales-quote';</script>";
        }
    }

    // 5. Won
    if ($action == 'won' && has_permission('sales_quotation_manage')) {
        $pdo->prepare("UPDATE quotations SET status='won' WHERE id=? AND status='sent'")->execute([$id]);
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'sales.quotation.won',
                'Quotation Won',
                "Quotation #$id dinyatakan WON. Lanjutkan pembuatan SO.",
                "index.php?page=sales-so&action=create&quote_id=$id",
                'success',
                ['permission_slug' => 'sales_so_manage']
            );
        }
        echo "<script>alert('Selamat! Quotation WON.'); window.location='index.php?page=sales-quote';</script>";
    }

    // 6. Lost
    if ($action == 'lost' && has_permission('sales_quotation_manage')) {
        $pdo->prepare("UPDATE quotations SET status='lost' WHERE id=? AND status='sent'")->execute([$id]);
        if (function_exists('notify_workflow_event')) {
            notify_workflow_event(
                'sales.quotation.lost',
                'Quotation Lost',
                "Quotation #$id dinyatakan LOST.",
                "index.php?page=sales-quote&action=edit&id=$id",
                'warning',
                ['permission_slug' => 'sales_quotation_approve']
            );
        }
        echo "<script>alert('Status: Lost.'); window.location='index.php?page=sales-quote';</script>";
    }

    // 7. Revise
    if ($action == 'revise' && has_permission('sales_quotation_manage')) {
        // ... (Logika revisi sama seperti sebelumnya) ...
        try {
            $uid = (int)($_SESSION['user_id'] ?? 0);
            if ($uid <= 0) {
                throw new Exception("Sesi login tidak valid. Silakan login ulang.");
            }

            $pdo->beginTransaction();
            $stmt_old = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_q = $stmt_old->fetch(PDO::FETCH_ASSOC);

            if (!$old_q) throw new Exception("Data tidak ditemukan.");

            $old_number = $old_q['quote_number'];
            $new_ver = intval($old_q['revision_version']) + 1;
            $base_number = preg_replace('/-R\d+$/', '', $old_number); 
            $new_number = $base_number . "-R" . $new_ver;
            $parent_id = ($old_q['revision_of']) ? $old_q['revision_of'] : $id;

            $sql_new = "INSERT INTO quotations (quote_number, revision_version, revision_of, customer_id, quote_date, payment_terms, ppn_percent, tax_included, subtotal, discount_amount, tax_amount, grand_total, status, notes, attachment, created_by) 
                        VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?)";
            
            $pdo->prepare($sql_new)->execute([
                $new_number, $new_ver, $parent_id, $old_q['customer_id'],
                $old_q['payment_terms'] ?? 'Net 30 Days',
                $old_q['ppn_percent'] ?? 11,
                $old_q['tax_included'] ?? 0,
                $old_q['subtotal'], $old_q['discount_amount'], $old_q['tax_amount'], $old_q['grand_total'],
                "Revisi dari " . $old_number . "\n" . $old_q['notes'], 
                $old_q['attachment'], $uid
            ]);
            $new_id = $pdo->lastInsertId();

            $stmt_items = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
            $stmt_items->execute([$id]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            $sql_ins_item = "INSERT INTO quotation_items (quotation_id, item_id, item_code_manual, item_name_manual, material_manual, ownership, unit_manual, qty, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_new_item = $pdo->prepare($sql_ins_item);

            foreach ($items as $item) {
                $stmt_new_item->execute([
                    $new_id, $item['item_id'], $item['item_code_manual'], $item['item_name_manual'], 
                    ($item['material_manual'] ?? ''), $item['ownership'], $item['unit_manual'], $item['qty'], $item['unit_price'], $item['subtotal']
                ]);
            }

            $pdo->prepare("UPDATE quotations SET status='revised' WHERE id=?")->execute([$id]);
            if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'sales.quotation.revise',
                    'Quotation Direvisi',
                    "Revisi quotation dibuat dari #$id menjadi $new_number.",
                    "index.php?page=sales-quote&action=edit&id=$new_id",
                    'info',
                    ['permission_slug' => 'sales_quotation_manage']
                );
            }
            $pdo->commit();
            echo "<script>alert('Revisi Berhasil Dibuat ($new_number).'); window.location='index.php?page=sales-quote&action=edit&id=$new_id';</script>";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "<script>alert('Gagal membuat revisi quotation. Silakan coba lagi.'); window.location='index.php?page=sales-quote';</script>";
        }
    }
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-file-earmark-text"></i> Penawaran Harga</h3>
        <p class="text-muted">Kelola penawaran harga kepada customer.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=sales-quote&action=create" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-lg"></i> Buat Penawaran Baru
        </a>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="quote-filter-form">
            <input type="hidden" name="page" value="sales-quote">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari No. Quotation / Customer..." value="<?= $esc($search_key) ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="draft" <?= $filter_status=='draft'?'selected':'' ?>>Draft</option>
                    <option value="waiting_approval" <?= $filter_status=='waiting_approval'?'selected':'' ?>>Waiting Approval</option>
                    <option value="approved" <?= $filter_status=='approved'?'selected':'' ?>>Approved</option>
                    <option value="sent" <?= $filter_status=='sent'?'selected':'' ?>>Sent</option>
                    <option value="won" <?= $filter_status=='won'?'selected':'' ?>>Won</option>
                    <option value="so_created" <?= $filter_status=='so_created'?'selected':'' ?>>SO Created</option>
                    <option value="lost" <?= $filter_status=='lost'?'selected':'' ?>>Lost</option>
                    <option value="rejected" <?= $filter_status=='rejected'?'selected':'' ?>>Rejected</option>
                    <option value="revised" <?= $filter_status=='revised'?'selected':'' ?>>Revised</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=sales-quote" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
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
                        <th>No. Quotation</th>
                        <th>Versi</th>
                        <th>Tanggal</th>
                        <th>Customer</th>
                        <th>Total Nilai</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT q.*, c.name as customer_name,
                                   (
                                       SELECT COUNT(*)
                                       FROM sales_orders so
                                       WHERE so.quotation_id = q.id
                                         AND so.status <> 'cancelled'
                                   ) AS active_so_count
                            FROM quotations q 
                            JOIN customers c ON q.customer_id = c.id 
                            WHERE 1=1";
                    $params = [];
                    if (!empty($filter_status)) {
                        if ($filter_status === 'so_created') {
                            $sql .= " AND EXISTS (
                                        SELECT 1
                                        FROM sales_orders so
                                        WHERE so.quotation_id = q.id
                                          AND so.status <> 'cancelled'
                                     )";
                        } elseif ($filter_status === 'won') {
                            $sql .= " AND q.status = 'won' AND NOT EXISTS (
                                        SELECT 1
                                        FROM sales_orders so
                                        WHERE so.quotation_id = q.id
                                          AND so.status <> 'cancelled'
                                     )";
                        } else {
                            $sql .= " AND q.status = ?";
                            $params[] = $filter_status;
                        }
                    }
                    if (!empty($search_key)) {
                        $sql .= " AND (q.quote_number LIKE ? OR c.name LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY q.id DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    while ($row = $stmt->fetch()):
                        $active_so_count = (int)($row['active_so_count'] ?? 0);
                        $status_view = (in_array((string)$row['status'], ['won', 'so_created'], true) && $active_so_count > 0)
                            ? 'so_created'
                            : (string)$row['status'];
                        $status_badge = match($status_view) {
                            'draft' => 'bg-secondary',
                            'waiting_approval' => 'bg-warning text-dark',
                            'approved' => 'bg-primary',
                            'sent' => 'bg-info text-dark',
                            'won' => 'bg-success',
                            'so_created' => 'bg-success',
                            'lost' => 'bg-dark',
                            'rejected' => 'bg-danger',
                            'revised' => 'bg-secondary text-decoration-line-through',
                            default => 'bg-light text-dark'
                        };
                        $ver_label = ($row['revision_version'] > 0) ? '<span class="badge bg-dark">R'.$row['revision_version'].'</span>' : '<span class="badge bg-light text-dark border">ORI</span>';
                    ?>
                    <tr>
                        <td>
                            <strong><?= clean($row['quote_number']) ?></strong>
                            <?php if(!empty($row['attachment']) && file_exists($row['attachment'])): ?>
                                <a href="<?= clean($row['attachment']) ?>" target="_blank" rel="noopener noreferrer" class="text-primary ms-2"><i class="bi bi-paperclip"></i></a>
                            <?php endif; ?>
                        </td>
                        <td><?= $ver_label ?></td>
                        <td><?= date('d/m/Y', strtotime($row['quote_date'])) ?></td>
                        <td><?= clean($row['customer_name']) ?></td>
                        <td>Rp <?= number_format($row['grand_total'], 0, ',', '.') ?></td>
                        <td>
                            <span class="badge <?= $status_badge ?>"><?= strtoupper(str_replace('_', ' ', $status_view)) ?></span>
                            <?php if (!empty($row['client_signed_at'])): ?>
                                <div><small class="text-success fw-semibold">TTD Client: <?= date('d/m/Y H:i', strtotime((string)$row['client_signed_at'])) ?></small></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="index.php?page=sales-quote&action=print&id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Cetak PDF"><i class="bi bi-printer"></i></a>

                                <?php if($row['status'] == 'draft' || $row['status'] == 'rejected'): ?>
                                    <a href="index.php?page=sales-quote&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning text-white"><i class="bi bi-pencil"></i></a>
                                    <a href="index.php?page=sales-quote&action=submit&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-secondary" onclick="return confirm('Ajukan?')"><i class="bi bi-send"></i></a>
                                    <a href="index.php?page=sales-quote&action=delete&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')"><i class="bi bi-trash"></i></a>
                                <?php endif; ?>

                                <?php if($row['status'] == 'waiting_approval' && has_permission('sales_quotation_approve')): ?>
                                    <a href="index.php?page=sales-quote&action=approve&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i></a>
                                    <a href="index.php?page=sales-quote&action=reject&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-danger"><i class="bi bi-x-lg"></i></a>
                                <?php endif; ?>

                                <?php if($row['status'] == 'approved'): ?>
                                    <a href="index.php?page=sales-quote&action=mark_sent&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-primary" onclick="return confirm('Submit quotation ke client dan kirim WA otomatis untuk TTD customer?')" title="Submit ke Client (WA Fonte)">
                                        <i class="bi bi-send-fill"></i> Submit Client
                                    </a>
                                <?php endif; ?>

                                <?php if($row['status'] == 'sent'): ?>
                                    <?php if (empty($row['client_signed_at'])): ?>
                                        <a href="index.php?page=sales-quote&action=resend_sign_wa&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-outline-primary" title="Kirim Ulang WA TTD" onclick="return confirm('Kirim ulang WA TTD ke client?')">
                                            <i class="bi bi-send-dash"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="index.php?page=sales-quote&action=won&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-success fw-bold" onclick="return confirm('Won?')">Won</a>
                                    <a href="index.php?page=sales-quote&action=lost&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-dark" onclick="return confirm('Lost?')">Lost</a>
                                    <a href="index.php?page=sales-quote&action=revise&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-warning text-dark fw-bold" onclick="return confirm('Buat Revisi?')" title="Buat Revisi"><i class="bi bi-arrow-repeat"></i> Revise</a>
                                <?php endif; ?>

                                <?php if(in_array((string)$row['status'], ['won', 'so_created'], true) && $active_so_count <= 0 && has_permission('sales_so_manage')): ?>
                                    <a href="index.php?page=sales-so&action=create&quote_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-cart-plus"></i> SO</a>
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
    const form = document.getElementById('quote-filter-form');
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
