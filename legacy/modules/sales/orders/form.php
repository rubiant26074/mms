<?php
// modules/sales/orders/form.php

if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('sales_so_manage')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=sales-so';</script>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quote_id = isset($_GET['quote_id']) ? (int)$_GET['quote_id'] : 0;
$is_edit = $id > 0;
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
if (function_exists('mms_ensure_sales_orders_fulfillment_source_column')) {
    mms_ensure_sales_orders_fulfillment_source_column($pdo);
}

// Default Data
$data = [
    'so_number' => 'AUTO',
    'so_date' => date('Y-m-d'),
    'customer_id' => '',
    'cust_po_number' => '',
    'cust_po_date' => date('Y-m-d'),
    'delivery_date' => date('Y-m-d', strtotime('+3 days')),
    'payment_terms' => 'Net 30 Days',
    'fulfillment_source' => 'spk',
    'ppn_percent' => 11,
    'tax_included' => 0,
    'discount_amount' => 0,
    'notes' => '',
    'status' => 'draft'
];
$items = [];

// --- 1. LOAD DATA (EDIT / DARI QUOTATION) ---
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM sales_orders WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    if (!isset($data['ppn_percent']) || (float)$data['ppn_percent'] <= 0) $data['ppn_percent'] = 11;
    if (!isset($data['tax_included'])) $data['tax_included'] = 0;
    if (empty($quote_id) && !empty($data['quotation_id'])) {
        $quote_id = (string)$data['quotation_id'];
    }
    
    $stmt_items = $pdo->prepare("SELECT soi.*,
                                        COALESCE(i.item_name, soi.item_name_manual, '') AS item_name,
                                        COALESCE(i.item_code, soi.item_code_manual, '') AS item_code,
                                        COALESCE(i.unit, soi.unit_manual, '') AS unit,
                                        COALESCE(soi.material_manual, i.description, '') AS material,
                                        COALESCE(i.base_price, 0) AS hpp
                                 FROM sales_order_items soi
                                 LEFT JOIN items i ON soi.item_id = i.id
                                 WHERE soi.sales_order_id = ?");
    $stmt_items->execute([$id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
} elseif ($quote_id) {
    $stmt_q = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
    $stmt_q->execute([$quote_id]);
    $quote = $stmt_q->fetch();
    if($quote) {
        $data['customer_id'] = $quote['customer_id'];
        $data['payment_terms'] = $quote['payment_terms'] ?? 'Net 30 Days';
        $data['ppn_percent'] = isset($quote['ppn_percent']) ? (float)$quote['ppn_percent'] : 11.0;
        if ($data['ppn_percent'] <= 0) $data['ppn_percent'] = 11.0;
        $data['tax_included'] = !empty($quote['tax_included']) ? 1 : 0;
        $data['discount_amount'] = max(0, (float)($quote['discount_amount'] ?? 0));
        // Normalisasi kolom agar view selalu punya key: item_id, item_code, item_name, unit, qty, unit_price
        $stmt_qi = $pdo->prepare("SELECT 
                                    qi.item_id,
                                    COALESCE(i.item_code, qi.item_code_manual, '') AS item_code,
                                    COALESCE(i.item_name, qi.item_name_manual, '') AS item_name,
                                    COALESCE(i.unit, qi.unit_manual, '') AS unit,
                                    COALESCE(qi.material_manual, i.description, '') AS material,
                                    COALESCE(i.base_price, 0) AS hpp,
                                    qi.qty,
                                    qi.unit_price
                                  FROM quotation_items qi
                                  LEFT JOIN items i ON i.id = qi.item_id
                                  WHERE qi.quotation_id = ?");
        $stmt_qi->execute([$quote_id]);
        $items = $stmt_qi->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!isset($data['ppn_percent']) || (float)$data['ppn_percent'] <= 0) {
    $data['ppn_percent'] = 11;
}
$data['tax_included'] = !empty($data['tax_included']) ? 1 : 0;
$data['discount_amount'] = max(0, (float)($data['discount_amount'] ?? 0));
$data['fulfillment_source'] = function_exists('mms_normalize_sales_order_fulfillment_source')
    ? mms_normalize_sales_order_fulfillment_source($data['fulfillment_source'] ?? 'spk')
    : 'spk';

if ($data['tax_included']) {
    $factor = 1 + ((float)$data['ppn_percent'] / 100);
    foreach ($items as &$item) {
        $item['unit_price'] = (float)($item['unit_price'] ?? 0) * $factor;
    }
    unset($item);
}

$customers = $pdo->query("SELECT id, name, customer_code FROM customers ORDER BY name ASC")->fetchAll();

render_header($is_edit ? "Edit Sales Order" : "Buat Sales Order");
?>

<div class="container-fluid">
    <form method="POST" action="modules/sales/orders/save.php" id="formSO">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="quote_id" value="<?= (int)$quote_id ?>">

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">SALES ORDER</h5>
                <span class="badge bg-light text-dark fw-bold"><?= $esc($data['so_number']) ?></span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 border-end">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="customerSelect" class="form-select select2" onchange="loadCustomerItems(this.value)" required>
                                <option value="">-- Pilih Customer --</option>
                                <?php foreach($customers as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id']==(int)$data['customer_id']?'selected':'' ?>>
                                        <?= $esc($c['customer_code']) ?> - <?= $esc($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">PO Customer Number</label>
                            <input type="text" name="cust_po_number" class="form-control" value="<?= $esc($data['cust_po_number']) ?>" placeholder="Masukkan No. PO">
                        </div>
                    </div>
                    <div class="col-md-6 ps-4">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Tgl SO</label>
                                <input type="date" name="so_date" class="form-control" value="<?= $esc($data['so_date']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Est. Delivery</label>
                                <input type="date" name="delivery_date" class="form-control" value="<?= $esc($data['delivery_date']) ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Payment Terms</label>
                            <select name="payment_terms" class="form-select">
                                <option value="Cash" <?= $data['payment_terms']=='Cash'?'selected':'' ?>>Cash</option>
                                <option value="Net 14 Days" <?= $data['payment_terms']=='Net 14 Days'?'selected':'' ?>>Net 14 Days</option>
                                <option value="Net 30 Days" <?= $data['payment_terms']=='Net 30 Days'?'selected':'' ?>>Net 30 Days</option>
                                <option value="Net 60 Days" <?= $data['payment_terms']=='Net 60 Days'?'selected':'' ?>>Net 60 Days</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Sumber Pemenuhan</label>
                            <select name="fulfillment_source" class="form-select">
                                <?php
                                    $so_fulfillment = $data['fulfillment_source'] ?? 'spk';
                                    $opt_spk = function_exists('mms_sales_order_fulfillment_label') ? mms_sales_order_fulfillment_label('spk') : 'Produksi / SPK';
                                    $opt_fg  = function_exists('mms_sales_order_fulfillment_label') ? mms_sales_order_fulfillment_label('fg_stock') : 'FG Stock (Tanpa SPK Baru)';
                                ?>
                                <option value="spk" <?= $so_fulfillment === 'spk' ? 'selected' : '' ?>><?= $esc($opt_spk) ?></option>
                                <option value="fg_stock" <?= $so_fulfillment === 'fg_stock' ? 'selected' : '' ?>><?= $esc($opt_fg) ?></option>
                            </select>
                            <div class="form-text">Pilih <strong>FG Stock</strong> jika order dipenuhi dari stok barang jadi existing (tidak membuat SPK baru).</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Mode PPN</label>
                            <select name="tax_mode" id="taxModeSelect" class="form-select" onchange="onTaxModeChanged()">
                                <option value="exclude" <?= (int)$data['tax_included'] === 0 ? 'selected' : '' ?>>Exclude PPN (harga belum PPN)</option>
                                <option value="include" <?= (int)$data['tax_included'] === 1 ? 'selected' : '' ?>>Include PPN (harga sudah PPN)</option>
                            </select>
                            <input type="hidden" name="ppn_percent" id="ppnPercentInput" value="<?= rtrim(rtrim(number_format((float)$data['ppn_percent'], 2, '.', ''), '0'), '.') ?>">
                            <small class="text-muted">Tarif PPN: <?= rtrim(rtrim(number_format((float)$data['ppn_percent'], 2, '.', ''), '0'), '.') ?>%</small>
                            <div id="taxModeHint" class="form-text"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white fw-bold">Daftar Barang Jadi (Finish Goods)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="bg-light text-center small">
                            <tr>
                                <th width="30%">Pilih Barang (Re-Order)</th>
                                <th width="15%">Material</th>
                                <th width="10%">Qty</th>
                                <th width="10%">Unit</th>
                                <th width="15%">Harga Satuan</th>
                                <th width="15%">Subtotal</th>
                                <th width="5%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="soItems">
                            <?php if(!empty($items)): foreach($items as $index => $item): ?>
                                <tr>
                                    <td>
                                        <select name="item_id[]" class="form-select item-select" onchange="updateRowInfo(this)" required>
                                            <option 
                                                value="<?= (int)($item['item_id'] ?? 0) ?>"
                                                data-item-code="<?= clean($item['item_code'] ?? '') ?>"
                                                data-item-name="<?= clean($item['item_name'] ?? '') ?>"
                                                data-material="<?= clean($item['material'] ?? '') ?>"
                                                data-unit="<?= clean($item['unit'] ?? '') ?>"
                                                data-price="<?= (float)($item['unit_price'] ?? 0) ?>"
                                                data-hpp="<?= (float)($item['hpp'] ?? 0) ?>">
                                                <?= clean($item['item_code'] ?? '') ?> - <?= clean($item['item_name'] ?? '') ?>
                                            </option>
                                        </select>
                                        <input type="hidden" name="item_code[]" class="item-code-hidden" value="<?= clean($item['item_code'] ?? '') ?>">
                                        <input type="hidden" name="item_name[]" class="item-name-hidden" value="<?= clean($item['item_name'] ?? '') ?>">
                                    </td>
                                    <td><input type="text" name="material[]" class="form-control text-start material" value="<?= clean($item['material'] ?? '') ?>" placeholder="Material"></td>
                                    <td><input type="number" name="qty[]" class="form-control text-center qty" value="<?= (float)($item['qty'] ?? 0) ?>" oninput="calculateAll()" required></td>
                                    <td><input type="text" name="unit[]" class="form-control text-center bg-light unit" value="<?= clean($item['unit'] ?? '') ?>" readonly></td>
                                    <td>
                                        <input type="number" name="price[]" class="form-control text-end price" value="<?= (float)($item['unit_price'] ?? 0) ?>" data-hpp="<?= (float)($item['hpp'] ?? 0) ?>" oninput="calculateAll()" required>
                                        <div class="form-text small text-muted hpp-hint"></div>
                                    </td>
                                    <td><input type="text" class="form-control text-end bg-light subtotal" readonly></td>
                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); calculateAll()">X</button></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">
                <button type="button" class="btn btn-success btn-sm fw-bold" onclick="addItemRow()">+ Tambah Baris (Re-Order)</button>
                <small class="ms-3 text-muted fst-italic">*Pilih Customer terlebih dahulu untuk memunculkan daftar barang miliknya.</small>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-md-7">
                <label class="form-label fw-bold">Catatan SO</label>
                <textarea name="notes" class="form-control" rows="4" placeholder="Keterangan pengiriman, spek khusus, dll..."><?= $esc($data['notes']) ?></textarea>
            </div>
            <div class="col-md-5">
                <div class="card bg-light shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span id="labelSubtotal" class="fw-bold">0</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Diskon:</span>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                name="discount_amount"
                                id="disc"
                                class="form-control form-control-sm text-end"
                                style="max-width: 180px;"
                                value="<?= (float)$data['discount_amount'] ?>">
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>PPN (<span id="ppnLabelPct"><?= rtrim(rtrim(number_format((float)$data['ppn_percent'], 2, '.', ''), '0'), '.') ?></span>%):</span>
                            <span id="labelTax" class="fw-bold">0</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-primary">GRAND TOTAL:</h5>
                            <h5 id="labelGrandTotal" class="mb-0 fw-bold text-primary">0</h5>
                        </div>
                    </div>
                </div>
                <div class="mt-4 text-end">
                    <a href="index.php?page=sales-so" class="btn btn-secondary px-4 me-2">Batal</a>
                    <button type="submit" class="btn btn-primary px-5 fw-bold shadow">SIMPAN SALES ORDER</button>
                </div>
            </div>
        </div>
    </form>
</div>



<script>
let availableItems = [];
let previousTaxMode = null;
const escHtml = (v) => String(v ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

// 1. Filter Barang berdasarkan Customer (Re-Order Logic)
async function loadCustomerItems(customerId) {
    if (!customerId) return;
    try {
        const res = await fetch(`modules/sales/orders/get_items_by_customer.php?customer_id=${encodeURIComponent(customerId)}`);
        availableItems = await res.json();
        
        // Refresh semua dropdown barang yang ada
        document.querySelectorAll('.item-select').forEach(select => {
            const currentVal = select.value;
            fillSelect(select);
            select.value = currentVal;
        });
    } catch (e) { console.error("Error loading items", e); }
}

function fillSelect(select) {
    const prevOpt = select.options[select.selectedIndex];
    const prevVal = select.value || '';
    const prevText = prevOpt ? prevOpt.text : '';
    const prevCode = prevOpt ? (prevOpt.getAttribute('data-item-code') || '') : '';
    const row = select.closest('tr');
    const prevUnit = row ? (row.querySelector('.unit')?.value || '') : '';
    const prevPrice = row ? (row.querySelector('.price')?.value || 0) : 0;
    const prevMaterial = row ? (row.querySelector('.material')?.value || '') : '';
    const prevHpp = row ? (row.querySelector('.price')?.getAttribute('data-hpp') || 0) : 0;

    let html = '<option value="">-- Pilih Barang --</option>';
    availableItems.forEach(item => {
        const id = escHtml(item.id);
        const code = escHtml(item.item_code || '');
        const name = escHtml(item.item_name || '');
        const material = escHtml(item.material || '');
        const unit = escHtml(item.unit || '');
        const price = escHtml(item.price || 0);
        const hpp = escHtml(item.hpp || 0);
        html += `<option value="${id}" data-item-code="${code}" data-item-name="${name}" data-material="${material}" data-unit="${unit}" data-price="${price}" data-hpp="${hpp}">${code} - ${name}</option>`;
    });
    select.innerHTML = html;

    // 1) Coba kembalikan by item_id
    if (prevVal) {
        const byId = Array.from(select.options).find(o => o.value === String(prevVal));
        if (byId) {
            select.value = String(prevVal);
            return;
        }
    }

    // 2) Coba cocokkan by item_code (untuk data dari quotation manual/join)
    if (prevCode) {
        const byCode = Array.from(select.options).find(o => (o.getAttribute('data-item-code') || '') === prevCode);
        if (byCode) {
            select.value = byCode.value;
            return;
        }
    }

    // 3) Jika tidak ada di daftar customer, tetap tampilkan opsi lama agar tidak hilang
    if (prevText && prevVal) {
        const legacy = document.createElement('option');
        legacy.value = prevVal;
        legacy.text = `${prevText} (Dari Quotation)`;
        legacy.setAttribute('data-item-code', prevCode);
        const splitName = prevText.includes(' - ') ? prevText.split(' - ').slice(1).join(' - ') : prevText;
        legacy.setAttribute('data-item-name', splitName);
        legacy.setAttribute('data-material', prevMaterial);
        legacy.setAttribute('data-unit', prevUnit);
        legacy.setAttribute('data-price', prevPrice);
        legacy.setAttribute('data-hpp', prevHpp);
        legacy.selected = true;
        select.appendChild(legacy);
    }
}

function addItemRow() {
    const tbody = document.getElementById('soItems');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select name="item_id[]" class="form-select item-select" onchange="updateRowInfo(this)" required></select></td>
        <td><input type="text" name="material[]" class="form-control text-start material" value="" placeholder="Material"></td>
        <td><input type="number" name="qty[]" class="form-control text-center qty" value="1" oninput="calculateAll()" required></td>
        <td><input type="text" name="unit[]" class="form-control text-center bg-light unit" readonly></td>
        <td>
            <input type="number" name="price[]" class="form-control text-end price" value="0" oninput="calculateAll()" required>
            <div class="form-text small text-muted hpp-hint"></div>
        </td>
        <td><input type="text" class="form-control text-end bg-light subtotal" readonly></td>
        <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); calculateAll()">X</button></td>
    `;
    // Sisipkan hidden manual code/name agar tetap tersimpan walau item_id kosong
    const firstCell = tr.querySelector('td');
    firstCell.insertAdjacentHTML('beforeend', `
        <input type="hidden" name="item_code[]" class="item-code-hidden" value="">
        <input type="hidden" name="item_name[]" class="item-name-hidden" value="">
    `);
    tbody.appendChild(tr);
    fillSelect(tr.querySelector('.item-select'));
    bindPriceHint(tr);
}

function updateRowInfo(select) {
    const opt = select.options[select.selectedIndex];
    if (!opt) return;
    const row = select.closest('tr');
    const ppnRate = getPpnRate();
    const taxMode = getTaxMode();
    const factor = 1 + (ppnRate / 100);
    const basePrice = parseFloat(opt.getAttribute('data-price') || '0') || 0;
    const displayPrice = taxMode === 'include' ? (basePrice * factor) : basePrice;
    row.querySelector('.unit').value = opt.getAttribute('data-unit') || '';
    row.querySelector('.price').value = displayPrice;
    row.querySelector('.price').setAttribute('data-hpp', opt.getAttribute('data-hpp') || 0);
    const codeHidden = row.querySelector('.item-code-hidden');
    const nameHidden = row.querySelector('.item-name-hidden');
    const materialInput = row.querySelector('.material');
    if (codeHidden) codeHidden.value = opt.getAttribute('data-item-code') || '';
    if (nameHidden) {
        const nm = opt.getAttribute('data-item-name') || '';
        if (nm) {
            nameHidden.value = nm;
        } else {
            const txt = opt.text || '';
            nameHidden.value = txt.includes(' - ') ? txt.split(' - ').slice(1).join(' - ') : txt;
        }
    }
    if (materialInput && String(materialInput.value || '').trim() === '') {
        materialInput.value = opt.getAttribute('data-material') || '';
    }
    bindPriceHint(row);
    calculateAll();
}

function bindPriceHint(row) {
    const priceInput = row.querySelector('.price');
    const hint = row.querySelector('.hpp-hint');
    if (!priceInput || !hint) return;
    const show = () => {
        const hpp = parseFloat(priceInput.getAttribute('data-hpp') || '0');
        if (hpp > 0) {
            hint.innerText = `HPP ref: Rp ${hpp.toLocaleString('id-ID')}`;
        } else {
            hint.innerText = 'HPP ref: belum diisi';
        }
    };
    priceInput.addEventListener('focus', show);
    priceInput.addEventListener('click', show);
}

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
        ? 'Harga item yang diinput sudah termasuk PPN. Sistem akan mengekstrak DPP otomatis.'
        : 'Harga item yang diinput belum termasuk PPN. Nilai PPN pada total SO ditampilkan 0.';
}

function onTaxModeChanged() {
    const mode = getTaxMode();
    const ppnRate = getPpnRate();
    const factor = 1 + (ppnRate / 100);

    if (previousTaxMode && previousTaxMode !== mode) {
        document.querySelectorAll('#soItems tr .price').forEach(input => {
            const val = parseFloat(input.value) || 0;
            if (previousTaxMode === 'exclude' && mode === 'include') {
                input.value = val * factor;
            } else if (previousTaxMode === 'include' && mode === 'exclude') {
                input.value = val / factor;
            }
        });
    }

    previousTaxMode = mode;
    calculateAll();
}

function calculateAll() {
    let totalBrutoDisplay = 0;
    document.querySelectorAll('#soItems tr').forEach(row => {
        const q = parseFloat(row.querySelector('.qty').value) || 0;
        const p = parseFloat(row.querySelector('.price').value) || 0;
        const sub = q * p;
        row.querySelector('.subtotal').value = sub.toLocaleString('id-ID');
        totalBrutoDisplay += sub;
    });

    const ppnRate = getPpnRate();
    const factor = 1 + (ppnRate / 100);
    const mode = getTaxMode();
    updateTaxModeHint(mode);
    const discInput = document.getElementById('disc');
    let discount = parseFloat(discInput ? discInput.value : '0') || 0;
    if (discount < 0) discount = 0;
    if (discount > totalBrutoDisplay) {
        discount = totalBrutoDisplay;
        if (discInput) discInput.value = discount;
    }

    let dpp = 0;
    let tax = 0;
    let grand = 0;
    if (mode === 'include') {
        const grossAfterDiscount = Math.max(0, totalBrutoDisplay - discount);
        dpp = grossAfterDiscount / factor;
        tax = grossAfterDiscount - dpp;
        grand = grossAfterDiscount;
    } else {
        dpp = Math.max(0, totalBrutoDisplay - discount);
        tax = 0;
        grand = dpp;
    }

    document.getElementById('labelSubtotal').innerText = "Rp " + dpp.toLocaleString('id-ID');
    document.getElementById('labelTax').innerText = "Rp " + tax.toLocaleString('id-ID');
    document.getElementById('labelGrandTotal').innerText = "Rp " + grand.toLocaleString('id-ID');
    const ppnLabel = document.getElementById('ppnLabelPct');
    if (ppnLabel) {
        ppnLabel.innerText = String(ppnRate).replace(/\.0+$/, '');
    }
}

window.onload = () => {
    const cId = document.getElementById('customerSelect').value;
    if(cId) loadCustomerItems(cId).then(() => calculateAll());
    if(document.querySelectorAll('#soItems tr').length === 0) addItemRow();
    document.querySelectorAll('#soItems tr').forEach(row => bindPriceHint(row));
    const disc = document.getElementById('disc');
    if (disc) disc.addEventListener('input', calculateAll);
    previousTaxMode = getTaxMode();
    calculateAll();
};
</script>

<?php render_footer(); ?>
