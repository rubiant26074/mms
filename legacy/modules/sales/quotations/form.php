<?php
// modules/sales/quotations/form.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('sales_quotation_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=sales-quote';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$data = [
    'quote_number' => 'AUTO',
    'quote_date' => date('Y-m-d'),
    'payment_terms' => 'Net 30 Days', // Default
    'ppn_percent' => 11,
    'tax_included' => 0,
    'customer_id' => '',
    'subtotal' => 0,
    'tax_amount' => 0,
    'discount_amount' => 0,
    'grand_total' => 0,
    'notes' => '',
    'attachment' => '',
    'status' => 'draft',
    'revision_version' => 0
];
$items = [];

// LOAD DATA EDIT
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
    $stmt->execute([$id]);
    $fetch = $stmt->fetch();
    if(!$fetch) die("Quotation tidak ditemukan");
    $data = $fetch;
    if(!isset($data['payment_terms'])) $data['payment_terms'] = '';
    if (!isset($data['ppn_percent']) || (float)$data['ppn_percent'] <= 0) $data['ppn_percent'] = 11;
    if (!isset($data['tax_included'])) $data['tax_included'] = 0;
    if (!in_array($data['status'], ['draft', 'rejected'], true)) {
        echo "<script>alert('Quotation tidak bisa diubah pada status saat ini.'); window.location='index.php?page=sales-quote';</script>";
        exit;
    }

    // Ambil item
    $stmt_items = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
    $stmt_items->execute([$id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
}

// PROSES SIMPAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        $error = "Permintaan tidak valid (CSRF). Silakan muat ulang halaman.";
    } else {
    $cust_id = $_POST['customer_id'];
    $date = $_POST['quote_date'];
    $notes = $_POST['notes'];
    
    // Logic Payment Terms (Dropdown atau Manual)
    $pay_select = $_POST['payment_terms_select'];
    $pay_manual = $_POST['payment_terms_manual'];
    $payment_terms = ($pay_select === 'Manual') ? $pay_manual : $pay_select;
    $ppn_percent = isset($_POST['ppn_percent']) ? (float)$_POST['ppn_percent'] : 11.0;
    if ($ppn_percent <= 0 || $ppn_percent > 100) $ppn_percent = 11.0;
    $tax_mode = (string)($_POST['tax_mode'] ?? 'exclude');
    $tax_included = ($tax_mode === 'include') ? 1 : 0;
    $cust_id_int = (int)$cust_id;

    // Autonumber item quotation harus aman terhadap master barang (items.item_code).
    $stmt_cust_code = $pdo->prepare("SELECT customer_code FROM customers WHERE id = ? LIMIT 1");
    $stmt_cust_code->execute([$cust_id_int]);
    $customer_code = (string)($stmt_cust_code->fetchColumn() ?: '');
    $item_code_prefix_raw = trim($customer_code) !== '' ? trim($customer_code) : ('CUST-' . $cust_id_int);
    $item_code_prefix = preg_replace('/[^A-Za-z0-9\\-]/', '', strtoupper($item_code_prefix_raw));
    if ($item_code_prefix === '') $item_code_prefix = 'CUST';
    $stmt_item_code_exists = $pdo->prepare("SELECT 1 FROM items WHERE item_code = ? LIMIT 1");
    $stmt_last_master_item_code = $pdo->prepare("SELECT item_code FROM items WHERE item_code LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt_last_master_item_code->execute([$item_code_prefix . '-%']);
    $last_master_item_code = (string)($stmt_last_master_item_code->fetchColumn() ?: '');
    $next_item_seq = 1;
    if ($last_master_item_code !== '') {
        $parts = explode('-', $last_master_item_code);
        $last_num = (int)end($parts);
        if ($last_num > 0) {
            $next_item_seq = $last_num + 1;
        }
    }
    $used_item_codes = [];
    $master_item_code_exists = static function (string $code) use ($stmt_item_code_exists): bool {
        if ($code === '') return false;
        $stmt_item_code_exists->execute([$code]);
        return (bool)$stmt_item_code_exists->fetchColumn();
    };
    $next_safe_item_code = static function () use (&$next_item_seq, $item_code_prefix, &$used_item_codes, $master_item_code_exists): string {
        while (true) {
            $candidate = $item_code_prefix . '-' . str_pad((string)$next_item_seq, 4, '0', STR_PAD_LEFT);
            $next_item_seq++;
            if (isset($used_item_codes[$candidate])) {
                continue;
            }
            if ($master_item_code_exists($candidate)) {
                continue;
            }
            $used_item_codes[$candidate] = true;
            return $candidate;
        }
    };

    // Kalkulasi: wajib dihitung ulang di server (hindari manipulasi hidden input).
    $disc_raw = (string)($_POST['discount_amount'] ?? '0');
    $disc = (float)str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $disc_raw));
    if ($disc < 0) $disc = 0;
    
    // Array Item
    $codes = $_POST['item_code'] ?? [];
    $names = $_POST['item_name'] ?? [];
    $mats  = $_POST['material'] ?? [];
    $owns  = $_POST['ownership'] ?? []; 
    $units = $_POST['unit'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $prices = $_POST['price'] ?? [];

    $rows = [];
    $subtotal = 0.0;
    $tax = 0.0;
    $grand = 0.0;
    $total_dpp_items = 0.0;
    $total_gross_input = 0.0;
    $row_count = count($codes);
    for ($i = 0; $i < $row_count; $i++) {
        $code = trim((string)($codes[$i] ?? ''));
        $name = trim((string)($names[$i] ?? ''));
        $material = trim((string)($mats[$i] ?? ''));
        $own = isset($owns[$i]) ? trim((string)$owns[$i]) : 'internal';
        $unit = trim((string)($units[$i] ?? ''));
        $qty = (float)($qtys[$i] ?? 0);
        $price_input = (float)($prices[$i] ?? 0);
        // Untuk quotation: harga item disimpan apa adanya. Mode include hanya menambah PPN di total.
        $price = $price_input;

        if ($code === '' && $name === '') continue;
        if ($qty <= 0 || $price_input < 0) continue;
        if ($own !== 'customer') $own = 'internal';

        $code = preg_replace('/[^A-Za-z0-9\\-]/', '', strtoupper($code));
        if ($code === '' || isset($used_item_codes[$code]) || $master_item_code_exists($code)) {
            $code = $next_safe_item_code();
        } else {
            $used_item_codes[$code] = true;
        }

        $sub = $qty * $price;
        $line_gross_input = $qty * $price_input;
        $total_dpp_items += $sub;
        $total_gross_input += $line_gross_input;
        $rows[] = [
            'code' => $code,
            'name' => $name,
            'material' => $material,
            'own' => $own,
            'unit' => $unit,
            'qty' => $qty,
            'price' => $price,
            'sub' => $sub
        ];
    }
    if (!empty($rows)) {
        if ($tax_included) {
            // Sesuai kebutuhan user: Include PPN = hitung PPN dari subtotal total (aggregate), bukan ekstrak.
            if ($disc > $total_dpp_items) $disc = $total_dpp_items;
            $subtotal = max(0, $total_dpp_items - $disc);
            $tax = $subtotal * ($ppn_percent / 100);
            $grand = $subtotal + $tax;
        } else {
            if ($disc > $total_dpp_items) $disc = $total_dpp_items;
            $subtotal = max(0, $total_dpp_items - $disc);
            // Samakan dengan SO: mode exclude menyimpan subtotal apa adanya, PPN total = 0.
            $tax = 0.0;
            $grand = $subtotal;
        }
    }

    // Upload Logic
    $attachment_path = $data['attachment'];
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $filename = $_FILES['attachment']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($_FILES['attachment']['size'] > $max_size) {
            $error = 'Ukuran lampiran maksimal 5MB.';
        } elseif (!in_array($ext, $allowed, true)) {
            $error = 'Format lampiran tidak diizinkan.';
        } else {
            $new_name = "quote_" . time() . "_" . rand(100,999) . "." . $ext;
            $upload_dir = "uploads/quotations/";

            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $target = $upload_dir . $new_name;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
                $attachment_path = $target;
            } else {
                $error = 'Gagal mengunggah lampiran.';
            }
        }
    }

    if (isset($error)) {
        $data['ppn_percent'] = $ppn_percent;
        $data['tax_included'] = $tax_included;
        $data['discount_amount'] = $disc;
        $data['subtotal'] = $subtotal;
        $data['tax_amount'] = $tax;
        $data['grand_total'] = $grand;
        // Jangan lanjut simpan jika ada error validasi/upload.
    } elseif (empty($rows)) {
        $error = 'Minimal 1 baris item quotation harus diisi.';
    } else {
        try {
            $pdo->beginTransaction();

        if (!$is_edit) {
            $ym = date('ym');
            $stmt_no = $pdo->query("SELECT COUNT(*) FROM quotations WHERE quote_number LIKE 'QT-$ym-%'");
            $count = $stmt_no->fetchColumn() + 1;
            $quote_number = "QT-" . $ym . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO quotations (quote_number, customer_id, quote_date, payment_terms, ppn_percent, tax_included, subtotal, discount_amount, tax_amount, grand_total, status, notes, attachment, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?)";
            $pdo->prepare($sql)->execute([$quote_number, $cust_id, $date, $payment_terms, $ppn_percent, $tax_included, $subtotal, $disc, $tax, $grand, $notes, $attachment_path, $_SESSION['user_id']]);
            $quote_id = $pdo->lastInsertId();
        } else {
            $sql = "UPDATE quotations SET customer_id=?, quote_date=?, payment_terms=?, ppn_percent=?, tax_included=?, subtotal=?, discount_amount=?, tax_amount=?, grand_total=?, notes=?, attachment=? WHERE id=?";
            $pdo->prepare($sql)->execute([$cust_id, $date, $payment_terms, $ppn_percent, $tax_included, $subtotal, $disc, $tax, $grand, $notes, $attachment_path, $id]);
            $quote_id = $id;
            $pdo->prepare("DELETE FROM quotation_items WHERE quotation_id=?")->execute([$id]);
        }

        // SIMPAN DETAIL
        $stmt_det = $pdo->prepare("INSERT INTO quotation_items (quotation_id, item_code_manual, item_name_manual, material_manual, ownership, unit_manual, qty, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($rows as $r) {
            $stmt_det->execute([$quote_id, $r['code'], $r['name'], $r['material'], $r['own'], $r['unit'], $r['qty'], $r['price'], $r['sub']]);
        }

        $pdo->commit();
        echo "<script>alert('Penawaran berhasil disimpan!'); window.location='index.php?page=sales-quote';</script>";
        exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Terjadi kesalahan saat menyimpan quotation.";
        }
    }
    }
}

// Data Customer
$customers = $pdo->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll();

// Siapkan nomor item awal per prefix customer berdasarkan master barang (items.item_code)
$item_code_max_by_prefix = [];
try {
    $stmt_item_codes = $pdo->query("SELECT item_code FROM items WHERE item_code IS NOT NULL AND item_code <> ''");
    while ($code_row = $stmt_item_codes->fetch(PDO::FETCH_ASSOC)) {
        $raw_code = strtoupper(trim((string)($code_row['item_code'] ?? '')));
        if ($raw_code === '') continue;
        if (!preg_match('/^(.+)-(\d+)$/', $raw_code, $m)) continue;
        $prefix = trim((string)$m[1]);
        $num = (int)$m[2];
        if ($prefix === '' || $num <= 0) continue;
        if (!isset($item_code_max_by_prefix[$prefix]) || $num > $item_code_max_by_prefix[$prefix]) {
            $item_code_max_by_prefix[$prefix] = $num;
        }
    }
} catch (Throwable $e) {
    // Fallback diam-diam: generator UI tetap jalan dari 1, server-side tetap validasi ulang.
}

$item_code_next_by_prefix = [];
foreach ($customers as $c) {
    $cust_id_map = (int)($c['id'] ?? 0);
    $cust_code_map = trim((string)($c['customer_code'] ?? ''));
    $prefix_raw_map = $cust_code_map !== '' ? $cust_code_map : ('CUST-' . $cust_id_map);
    $prefix_map = preg_replace('/[^A-Za-z0-9\\-]/', '', strtoupper($prefix_raw_map));
    if ($prefix_map === '') $prefix_map = 'CUST';
    $item_code_next_by_prefix[$prefix_map] = (($item_code_max_by_prefix[$prefix_map] ?? 0) + 1);
}
$item_code_next_by_prefix_json = json_encode($item_code_next_by_prefix, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($item_code_next_by_prefix_json)) {
    $item_code_next_by_prefix_json = '{}';
}

$ppn_percent_view = isset($data['ppn_percent']) ? (float)$data['ppn_percent'] : 11.0;
if ($ppn_percent_view <= 0 || $ppn_percent_view > 100) $ppn_percent_view = 11.0;
$data['ppn_percent'] = $ppn_percent_view;
    $data['tax_included'] = !empty($data['tax_included']) ? 1 : 0;

$display_discount = (float)($data['discount_amount'] ?? 0);
foreach ($items as &$it) {
    $it['unit_price'] = (float)($it['unit_price'] ?? 0);
    $it['subtotal'] = (float)($it['qty'] ?? 0) * (float)$it['unit_price'];
}
unset($it);

$title_text = $is_edit ? "Edit Penawaran" : "Buat Penawaran";
if ($data['revision_version'] > 0) $title_text .= " (Revisi R" . $data['revision_version'] . ")";

render_header($title_text);
?>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $esc($error) ?></div><?php endif; ?>

    <div class="row">
        <!-- HEADER FORM -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white d-flex justify-content-between">
                    <span>Info Penawaran</span>
                    <?php if($data['revision_version'] > 0): ?>
                        <span class="badge bg-warning text-dark">R<?= (int)$data['revision_version'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <label>No. Quotation</label>
                        <input type="text" class="form-control fw-bold" value="<?= $esc($data['quote_number']) ?>" readonly>
                    </div>
                    
                    <!-- CUSTOMER SELECT WITH ADD BUTTON -->
                    <div class="mb-2">
                        <label>Customer <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select name="customer_id" id="custSelect" class="form-select" required onchange="resetItemNumbers()">
                                <option value="" data-code="">-- Pilih Customer --</option>
                                <?php foreach($customers as $c): 
                                    $c_code = !empty($c['customer_code']) ? $c['customer_code'] : 'CUST';
                                ?>
                                    <option value="<?= (int)$c['id'] ?>" data-code="<?= $esc($c_code) ?>" <?= (int)$c['id']==(int)$data['customer_id']?'selected':'' ?>>
                                        <?= $esc($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#addCustModal" title="Tambah Customer Baru"><i class="bi bi-plus-lg"></i></button>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label>Tanggal</label>
                        <input type="date" name="quote_date" class="form-control" value="<?= $esc($data['quote_date']) ?>" required>
                    </div>

                    <!-- PAYMENT TERMS FIELD (NEW) -->
                    <div class="mb-2">
                        <label>Terms of Payment</label>
                        <?php 
                            $standard_terms = ['Cash', 'Net 14 Days', 'Net 30 Days', 'Net 60 Days'];
                            $is_manual = !in_array($data['payment_terms'], $standard_terms) && !empty($data['payment_terms']);
                            $select_val = $is_manual ? 'Manual' : $data['payment_terms'];
                        ?>
                        <select name="payment_terms_select" id="payTermSelect" class="form-select mb-1" onchange="togglePayTerm()">
                            <option value="Cash" <?= $data['payment_terms']=='Cash'?'selected':'' ?>>Cash / Tunai</option>
                            <option value="Net 14 Days" <?= $data['payment_terms']=='Net 14 Days'?'selected':'' ?>>Net 14 Days</option>
                            <option value="Net 30 Days" <?= $data['payment_terms']=='Net 30 Days'?'selected':'' ?>>Net 30 Days</option>
                            <option value="Net 60 Days" <?= $data['payment_terms']=='Net 60 Days'?'selected':'' ?>>Net 60 Days</option>
                            <option value="Manual" <?= $is_manual?'selected':'' ?>>Isi Manual (Lainnya)</option>
                        </select>
                        <input type="text" name="payment_terms_manual" id="payTermManual" class="form-control <?= $is_manual ? '' : 'd-none' ?>" value="<?= $esc($data['payment_terms']) ?>" placeholder="Contoh: DP 50%, Pelunasan sebelum kirim">
                    </div>

                    <div class="mb-2">
                        <label class="fw-bold">Mode PPN</label>
                        <select name="tax_mode" id="taxModeSelect" class="form-select" onchange="onTaxModeChanged()">
                            <option value="exclude" <?= (int)$data['tax_included'] === 0 ? 'selected' : '' ?>>Exclude PPN (tanpa PPN)</option>
                            <option value="include" <?= (int)$data['tax_included'] === 1 ? 'selected' : '' ?>>Include PPN (subtotal + PPN)</option>
                        </select>
                        <input type="hidden" name="ppn_percent" id="ppnPercentInput" value="<?= rtrim(rtrim(number_format((float)$data['ppn_percent'], 2, '.', ''), '0'), '.') ?>">
                        <small class="text-muted">Tarif PPN: <?= rtrim(rtrim(number_format((float)$data['ppn_percent'], 2, '.', ''), '0'), '.') ?>%</small>
                        <div id="taxModeHint" class="form-text"></div>
                    </div>
                    
                    <div class="mb-2">
                        <label class="fw-bold">Lampiran</label>
                        <input type="file" name="attachment" class="form-control">
                        <div class="form-text small">Max 5MB (PDF/IMG).</div>
                        <?php if (!empty($data['attachment']) && file_exists($data['attachment'])): ?>
                            <div class="mt-1"><a href="<?= $esc($data['attachment']) ?>" target="_blank" class="small text-decoration-none">Lihat Lampiran</a></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-2">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"><?= $esc($data['notes']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- DETAIL ITEMS -->
        <div class="col-md-8">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="card shadow-sm h-100 border-success">
                        <div class="card-header bg-success text-white small py-2">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Paste dari Excel
                        </div>
                        <div class="card-body p-2">
                            <p class="mb-1 small text-muted">Format: <strong>[Nama Barang] | [Material] | [Qty] | [Satuan] | [Harga Satuan]</strong></p>
                            <textarea id="pasteArea" class="form-control border-0" rows="4" placeholder="Klik di sini & Paste (Ctrl+V) data dari Excel..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm h-100 border-primary">
                        <div class="card-header bg-primary text-white small py-2">
                            <i class="bi bi-pencil-square"></i> Isi Manual Item
                        </div>
                        <div class="card-body p-2">
                            <div class="row g-2">
                                <div class="col-12">
                                    <input type="text" id="manualItemName" class="form-control form-control-sm" placeholder="Nama barang">
                                </div>
                                <div class="col-12">
                                    <input type="text" id="manualItemMaterial" class="form-control form-control-sm" placeholder="Material (opsional)">
                                </div>
                                <div class="col-3">
                                    <input type="number" id="manualItemQty" class="form-control form-control-sm text-center" value="1" step="0.01" min="0" data-allow-zero>
                                </div>
                                <div class="col-3">
                                    <input type="text" id="manualItemUnit" class="form-control form-control-sm text-center" value="Pcs" placeholder="Unit">
                                </div>
                                <div class="col-3">
                                    <select id="manualItemOwner" class="form-select form-select-sm">
                                        <option value="internal" selected>Internal</option>
                                        <option value="customer">Customer</option>
                                    </select>
                                </div>
                                <div class="col-3">
                                    <input type="number" id="manualItemPrice" class="form-control form-control-sm text-end" value="0" step="0.01" min="0" placeholder="Harga" data-allow-zero>
                                </div>
                                <div class="col-12 d-grid">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="addManualItem()">
                                        <i class="bi bi-plus-lg"></i> Tambah Item Manual
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light d-flex justify-content-between py-2">
                    <strong>Detail Barang</strong>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addEmptyRow()">+ Baris</button>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0 table-sm text-sm">
                        <thead class="bg-light text-center">
                            <tr>
                                <th width="15%">Kode (Auto)</th>
                                <th width="20%">Nama Barang</th>
                                <th width="15%">Material</th>
                                <th width="14%">Source / Owner</th>
                                <th width="8%">Qty</th>
                                <th width="7%">Unit</th>
                                <th width="14%">Harga</th>
                                <th width="12%">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="qItems">
                            <?php if(!empty($items)): foreach($items as $it): 
                                $row_sub = $it['qty'] * $it['unit_price'];
                                $sel_int = ($it['ownership'] == 'internal') ? 'selected' : '';
                                $sel_cus = ($it['ownership'] == 'customer') ? 'selected' : '';
                            ?>
                            <tr>
                                <td><input type="text" name="item_code[]" class="form-control form-control-sm bg-light" value="<?= $esc($it['item_code_manual']) ?>" readonly></td>
                                <td><input type="text" name="item_name[]" class="form-control form-control-sm" value="<?= $esc($it['item_name_manual']) ?>"></td>
                                <td><input type="text" name="material[]" class="form-control form-control-sm" value="<?= clean($it['material_manual'] ?? '') ?>"></td>
                                <td>
                                    <select name="ownership[]" class="form-select form-select-sm" style="font-size:0.85rem;">
                                        <option value="internal" <?= $sel_int ?>>Internal (Kita)</option>
                                        <option value="customer" <?= $sel_cus ?>>Customer (Titip)</option>
                                    </select>
                                </td>
                                <td><input type="number" name="qty[]" class="form-control form-control-sm text-center qty" value="<?= $it['qty']+0 ?>" step="0.01" oninput="calcTotal()"></td>
                                <td><input type="text" name="unit[]" class="form-control form-control-sm text-center" value="<?= $esc($it['unit_manual']) ?>"></td>
                                <td><input type="number" name="price[]" class="form-control form-control-sm text-end price" value="<?= $it['unit_price']+0 ?>" step="0.01" oninput="calcTotal()"></td>
                                <td><input type="text" class="form-control form-control-sm text-end subtotal bg-light" value="<?= number_format($row_sub,0,',','.') ?>" readonly></td>
                                <td class="text-center"><button type="button" class="btn btn-danger btn-sm py-0" onclick="removeRow(this)">x</button></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                        <tfoot>
                            <input type="hidden" name="subtotal_hidden" id="inpSubtotal" value="<?= (float)$data['subtotal'] ?>">
                            <input type="hidden" name="tax_amount_hidden" id="inpTax" value="<?= (float)$data['tax_amount'] ?>">
                            <input type="hidden" name="grand_total_hidden" id="inpGrand" value="<?= (float)$data['grand_total'] ?>">

                            <tr><td colspan="7" class="text-end">Subtotal :</td><td class="text-end fw-bold" id="txtSub">0</td><td></td></tr>
                            <tr><td colspan="7" class="text-end">Diskon :</td><td><input type="text" name="discount_amount" id="disc" class="form-control form-control-sm text-end" value="<?= number_format($display_discount,0,',','.') ?>" onkeyup="calcTotal()" data-allow-zero></td><td></td></tr>
                            <tr><td colspan="7" class="text-end">PPN (<span id="ppnLabelPct"><?= rtrim(rtrim(number_format((float)$data['ppn_percent'], 2, '.', ''), '0'), '.') ?></span>%) :</td><td class="text-end" id="txtTax">0</td><td></td></tr>
                            <tr class="bg-primary text-white"><td colspan="7" class="text-end fw-bold">GRAND TOTAL :</td><td class="text-end fw-bold" id="txtGrand">0</td><td></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <div class="text-end mb-5">
                <a href="index.php?page=sales-quote" class="btn btn-secondary">Kembali</a>
                <button type="submit" class="btn btn-primary px-4">Simpan Penawaran</button>
            </div>
        </div>
    </div>
</form>

<!-- MODAL ADD CUSTOMER -->
<div class="modal fade" id="addCustModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-person-vcard"></i> Form Data Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="custAlert" class="alert d-none"></div>
                <div class="row">
                    <div class="col-md-6 border-end">
                        <h6 class="text-primary mb-3">Identitas Perusahaan</h6>
                        <div class="mb-3">
                            <label class="fw-bold">Kode Customer <span class="text-danger">*</span></label>
                            <input type="text" id="newCustCode" class="form-control bg-light fw-bold text-primary" value="AUTO" readonly>
                            <div class="form-text small">Auto Generate (CT-XXX).</div>
                        </div>
                        <div class="mb-3">
                            <label>Nama Perusahaan <span class="text-danger">*</span></label>
                            <input type="text" id="newCustName" class="form-control" placeholder="Contoh: PT. Maju Jaya">
                        </div>
                        <div class="mb-3">
                            <label>NPWP (Tax ID)</label>
                            <input type="text" id="newCustTaxId" class="form-control" placeholder="00.000.000.0-000.000">
                        </div>
                        <div class="mb-3">
                            <label>Alamat Lengkap</label>
                            <textarea id="newCustAddress" class="form-control" rows="3" placeholder="Alamat kirim / tagihan"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6 ps-4">
                        <h6 class="text-primary mb-3">Kontak Person</h6>
                        <div class="mb-3">
                            <label>PIC (Contact Person)</label>
                            <input type="text" id="newCustPIC" class="form-control" placeholder="Nama Kontak">
                        </div>
                        <div class="mb-3">
                            <label>No. Telepon / HP</label>
                            <input type="text" id="newCustPhone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" id="newCustEmail" class="form-control" placeholder="email@perusahaan.com">
                        </div>
                    </div>
                </div>
                <div class="small text-muted mt-2">Isi data minimal Nama Perusahaan, lainnya opsional.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" onclick="saveNewCustomer()">Simpan</button>
            </div>
        </div>
    </div>
</div>

<script>
let previousTaxMode = null;
const itemCodeBaseNextByPrefix = <?= $item_code_next_by_prefix_json ?> || {};
const itemCodeNextByPrefix = { ...itemCodeBaseNextByPrefix };
const escHtml = (v) => String(v ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

// --- Payment Terms Logic ---
function togglePayTerm() {
    const sel = document.getElementById('payTermSelect');
    const manual = document.getElementById('payTermManual');
    if (sel.value === 'Manual') {
        manual.classList.remove('d-none');
        manual.focus();
    } else {
        manual.classList.add('d-none');
    }
}

// --- HELPER: GET CUSTOMER CODE ---
function getCustomerCode() {
    const sel = document.getElementById('custSelect');
    if (sel.selectedIndex === -1) return 'CUST';
    const code = sel.options[sel.selectedIndex].getAttribute('data-code');
    if (code && code.trim() !== '') return code;
    return 'CUST';
}

function getCustomerCodePrefix() {
    const sel = document.getElementById('custSelect');
    let raw = '';
    if (sel && sel.selectedIndex >= 0) {
        raw = String(sel.options[sel.selectedIndex].getAttribute('data-code') || '').trim();
    }
    const custId = sel ? String(sel.value || '').trim() : '';
    if (!raw) {
        raw = custId ? `CUST-${custId}` : 'CUST';
    }
    const cleaned = String(raw || '').toUpperCase().replace(/[^A-Z0-9-]/g, '');
    if (cleaned) return cleaned;
    return custId ? `CUST-${custId}` : 'CUST';
}

function codeExistsInGrid(code, excludeInput = null) {
    const target = String(code || '').trim().toUpperCase();
    if (!target) return false;
    return Array.from(document.querySelectorAll('#qItems input[name="item_code[]"]')).some((input) => {
        if (excludeInput && input === excludeInput) return false;
        return String(input.value || '').trim().toUpperCase() === target;
    });
}

function getNextSafeItemCode(excludeInput = null) {
    const prefix = getCustomerCodePrefix();
    let next = parseInt(itemCodeNextByPrefix[prefix] || itemCodeBaseNextByPrefix[prefix] || 1, 10);
    if (!Number.isFinite(next) || next < 1) next = 1;

    while (true) {
        const candidate = `${prefix}-${String(next).padStart(4, '0')}`;
        if (!codeExistsInGrid(candidate, excludeInput)) {
            itemCodeNextByPrefix[prefix] = next + 1;
            return candidate;
        }
        next++;
    }
}

// --- HELPER: GENERATE ITEM CODE (CT-XXX-0001) ---
function generateItemCode() {
    return getNextSafeItemCode();
}

function resetItemNumbers() {
    const prefix = getCustomerCodePrefix();
    let next = parseInt(itemCodeBaseNextByPrefix[prefix] || 1, 10);
    if (!Number.isFinite(next) || next < 1) next = 1;

    document.querySelectorAll('#qItems tr input[name="item_code[]"]').forEach((input) => {
        while (true) {
            const candidate = `${prefix}-${String(next).padStart(4, '0')}`;
            if (!codeExistsInGrid(candidate, input)) {
                input.value = candidate;
                next++;
                break;
            }
            next++;
        }
    });
    itemCodeNextByPrefix[prefix] = next;
}

// --- AJAX Add Customer ---
function saveNewCustomer() {
    const name = document.getElementById('newCustName').value;
    const alertBox = document.getElementById('custAlert');
    
    if(!name) { alert('Nama wajib diisi'); return; }

    fetch('index.php?page=sales-customers&action=save_ajax', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            csrf: '<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>',
            name: name, 
            address: document.getElementById('newCustAddress').value, 
            phone: document.getElementById('newCustPhone').value, 
            pic: document.getElementById('newCustPIC').value,
            email: document.getElementById('newCustEmail').value,
            tax_id: document.getElementById('newCustTaxId').value
        })
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            const select = document.getElementById('custSelect');
            const option = document.createElement('option');
            option.value = data.data.id; 
            option.text = data.data.name; 
            option.selected = true;
            if (data.data.code) option.setAttribute('data-code', data.data.code);
            
            select.add(option);
            bootstrap.Modal.getInstance(document.getElementById('addCustModal')).hide();
            resetItemNumbers();
            // reset modal fields
            document.getElementById('newCustName').value = '';
            document.getElementById('newCustTaxId').value = '';
            document.getElementById('newCustAddress').value = '';
            document.getElementById('newCustPIC').value = '';
            document.getElementById('newCustPhone').value = '';
            document.getElementById('newCustEmail').value = '';
        } else {
            alert(data.message);
        }
    });
}

// --- PASTE EXCEL LOGIC ---
document.getElementById('pasteArea').addEventListener('paste', function(e) {
    e.preventDefault();
    var rows = (e.clipboardData || window.clipboardData).getData('Text').split('\n');
    rows.forEach(function(row) {
        if(row.trim() !== '') {
            var cols = row.split('\t');
            var name = cols[0] || '';
            var material = '';
            var qty = 1;
            var unit = 'Pcs';
            var price = 0;

            if (cols.length >= 5) {
                material = cols[1] || '';
                qty = (cols[2] || 1).toString().replace(',', '.').replace(/[^0-9.]/g, '');
                unit = cols[3] || 'Pcs';
                price = (cols[4] || 0).toString().replace(/[^0-9]/g, '');
            } else {
                qty = (cols[1] || 1).toString().replace(',', '.').replace(/[^0-9.]/g, '');
                unit = cols[2] || 'Pcs';
                price = (cols[3] || 0).toString().replace(/[^0-9]/g, '');
            }
            
            var code = generateItemCode();
            addRowData(code, name, material, qty, unit, price, 'internal');
        }
    });
    this.value = ''; calcTotal();
});

function addEmptyRow() { 
    addRowData(generateItemCode(), '', '', 1, 'Pcs', 0, 'internal'); 
}

function addManualItem() {
    const nameEl = document.getElementById('manualItemName');
    const materialEl = document.getElementById('manualItemMaterial');
    const qtyEl = document.getElementById('manualItemQty');
    const unitEl = document.getElementById('manualItemUnit');
    const priceEl = document.getElementById('manualItemPrice');
    const ownerEl = document.getElementById('manualItemOwner');

    const name = (nameEl?.value || '').trim();
    const material = (materialEl?.value || '').trim();
    const qty = parseFloat(qtyEl?.value || '0') || 0;
    const unit = (unitEl?.value || 'Pcs').trim() || 'Pcs';
    const price = parseFloat(priceEl?.value || '0') || 0;
    const owner = (ownerEl?.value === 'customer') ? 'customer' : 'internal';

    if (!name) {
        alert('Nama barang wajib diisi untuk input manual.');
        nameEl?.focus();
        return;
    }
    if (qty <= 0) {
        alert('Qty harus lebih dari 0.');
        qtyEl?.focus();
        return;
    }
    if (price < 0) {
        alert('Harga tidak boleh minus.');
        priceEl?.focus();
        return;
    }

    addRowData(generateItemCode(), name, material, qty, unit, price, owner);
    if (nameEl) nameEl.value = '';
    if (materialEl) materialEl.value = '';
    if (qtyEl) qtyEl.value = '1';
    if (unitEl) unitEl.value = 'Pcs';
    if (priceEl) priceEl.value = '0';
    if (ownerEl) ownerEl.value = 'internal';
    nameEl?.focus();
    calcTotal();
}

function addRowData(code, name, material, qty, unit, price, ownership = 'internal') {
    const safeCode = escHtml(code);
    const safeName = escHtml(name);
    const safeMat = escHtml(material || '');
    const safeUnit = escHtml(unit);
    const safeQty = Number.isFinite(parseFloat(qty)) ? parseFloat(qty) : 0;
    const safePrice = Number.isFinite(parseFloat(price)) ? parseFloat(price) : 0;
    const safeOwner = ownership === 'customer' ? 'customer' : 'internal';
    const internalSelected = safeOwner === 'internal' ? 'selected' : '';
    const customerSelected = safeOwner === 'customer' ? 'selected' : '';

    const row = `<tr>
        <td><input type="text" name="item_code[]" class="form-control form-control-sm bg-light" value="${safeCode}" readonly></td>
        <td><input type="text" name="item_name[]" class="form-control form-control-sm" value="${safeName}"></td>
        <td><input type="text" name="material[]" class="form-control form-control-sm" value="${safeMat}"></td>
        <td>
            <select name="ownership[]" class="form-select form-select-sm" style="font-size:0.85rem;">
                <option value="internal" ${internalSelected}>Internal</option>
                <option value="customer" ${customerSelected}>Customer</option>
            </select>
        </td>
        <td><input type="number" name="qty[]" class="form-control form-control-sm text-center qty" value="${safeQty}" step="0.01" oninput="calcTotal()"></td>
        <td><input type="text" name="unit[]" class="form-control form-control-sm text-center" value="${safeUnit}"></td>
        <td><input type="number" name="price[]" class="form-control form-control-sm text-end price" value="${safePrice}" step="0.01" oninput="calcTotal()"></td>
        <td><input type="text" class="form-control form-control-sm text-end subtotal bg-light" value="0" readonly></td>
        <td class="text-center"><button type="button" class="btn btn-danger btn-sm py-0" onclick="removeRow(this)">x</button></td>
    </tr>`;
    document.getElementById('qItems').insertAdjacentHTML('beforeend', row);
    resetItemNumbers();
}

function removeRow(btn) { btn.closest('tr').remove(); resetItemNumbers(); calcTotal(); }

function getTaxMode() {
    const el = document.getElementById('taxModeSelect');
    return el ? el.value : 'exclude';
}

function getPpnRate() {
    const el = document.getElementById('ppnPercentInput');
    const v = parseFloat(el ? el.value : '11');
    return Number.isFinite(v) && v > 0 ? v : 11;
}

function updateTaxModeHint(mode) {
    const hint = document.getElementById('taxModeHint');
    if (!hint) return;
    hint.innerText = mode === 'include'
        ? 'PPN dihitung dari subtotal total, lalu ditambahkan ke grand total.'
        : 'Harga item yang diinput belum termasuk PPN. Nilai PPN total mengikuti pola SO (0).';
}

function onTaxModeChanged() {
    const mode = getTaxMode();
    previousTaxMode = mode;
    calcTotal();
}

function calcTotal() {
    let brutoDisplay = 0;
    document.querySelectorAll('#qItems tr').forEach(row => {
        let q = parseFloat(row.querySelector('.qty').value) || 0;
        let p = parseFloat(row.querySelector('.price').value) || 0;
        let sub = q * p;
        row.querySelector('.subtotal').value = new Intl.NumberFormat('id-ID').format(sub);
        brutoDisplay += sub;
    });

    const discInput = document.getElementById('disc');
    let discDisplay = parseFloat((discInput ? discInput.value : '0').replace(/\./g, '')) || 0;
    if (discDisplay > brutoDisplay) {
        discDisplay = brutoDisplay;
        if (discInput) {
            discInput.value = new Intl.NumberFormat('id-ID').format(discDisplay);
        }
    }

    const ppnRate = getPpnRate();
    const mode = getTaxMode();
    updateTaxModeHint(mode);

    let dpp = 0;
    let tax = 0;
    let grand = 0;
    if (mode === 'include') {
        dpp = Math.max(0, brutoDisplay - discDisplay);
        tax = dpp * (ppnRate / 100);
        grand = dpp + tax;
    } else {
        dpp = Math.max(0, brutoDisplay - discDisplay);
        tax = 0;
        grand = dpp;
    }

    document.getElementById('inpSubtotal').value = dpp;
    document.getElementById('inpTax').value = tax;
    document.getElementById('inpGrand').value = grand;
    document.getElementById('txtSub').innerText = "Rp " + new Intl.NumberFormat('id-ID').format(dpp);
    document.getElementById('txtTax').innerText = "Rp " + new Intl.NumberFormat('id-ID').format(tax);
    document.getElementById('txtGrand').innerText = "Rp " + new Intl.NumberFormat('id-ID').format(grand);
    const ppnLabel = document.getElementById('ppnLabelPct');
    if (ppnLabel) {
        ppnLabel.innerText = String(ppnRate).replace(/\.0+$/, '');
    }
}
window.addEventListener('load', () => {
    previousTaxMode = getTaxMode();
    resetItemNumbers();
    calcTotal();
});
</script>

<?php render_footer(); ?>
