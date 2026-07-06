<?php
// modules/sales/orders/print.php

// 1. KONEKSI & CONFIG
if (!function_exists('get_company_profile')) {
    if (file_exists('../../../config/database.php')) {
        require_once '../../../config/database.php';
        require_once '../../../config/functions.php';
    } elseif (file_exists('config/database.php')) {
        require_once 'config/database.php';
        require_once 'config/functions.php';
    } else {
        die("Error loading configuration.");
    }
}

// Mode public dipakai oleh modules/public/sales_order.php setelah token tervalidasi.
$is_public_mode = defined('MMS_PUBLIC_SO_MODE') && MMS_PUBLIC_SO_MODE === true;
if (
    !$is_public_mode &&
    (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('sales_so_manage'))
) {
    die("Akses ditolak.");
}

if (!isset($_GET['id'])) die("Error: ID tidak ditemukan.");
$id = (int)$_GET['id'];
if (function_exists('mms_ensure_sales_orders_client_signature_columns')) {
    mms_ensure_sales_orders_client_signature_columns($pdo);
}

// 2. DATA HEADER SO (Ambil Path Signature)
$sql = "SELECT so.*, c.name as cust_name, c.address as cust_addr, c.phone as cust_phone, c.pic as cust_pic,
               u_create.fullname as sales_name, u_create.signature_path as sales_sig,
               u_mgr.fullname as mgr_name, u_mgr.signature_path as mgr_sig
        FROM sales_orders so
        JOIN customers c ON so.customer_id = c.id
        LEFT JOIN users u_create ON so.created_by = u_create.id
        LEFT JOIN users u_mgr ON so.approved_by = u_mgr.id
        WHERE so.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) die("Sales Order tidak ditemukan.");

// 3. DATA ITEMS (support item manual dari quotation)
$stmt_items = $pdo->prepare("SELECT soi.*,
                                    COALESCE(i.item_name, soi.item_name_manual, '') AS item_name,
                                    COALESCE(i.item_code, soi.item_code_manual, '') AS item_code,
                                    COALESCE(i.unit, soi.unit_manual, '') AS unit,
                                    COALESCE(soi.material_manual, i.description, '') AS material
                             FROM sales_order_items soi
                             LEFT JOIN items i ON soi.item_id = i.id
                             WHERE soi.sales_order_id = ?");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// 4. IDENTITAS PERUSAHAAN
$comp = get_company_profile();
$logo_path_final = file_exists($comp['logo_path']) ? $comp['logo_path'] : '../../../' . $comp['logo_path'];
$ppn_percent = isset($data['ppn_percent']) && $data['ppn_percent'] !== null ? (float)$data['ppn_percent'] : 11;
$is_tax_included = !empty($data['tax_included']);
$tax_amount = $is_tax_included
    ? (isset($data['tax_amount']) && $data['tax_amount'] !== null
        ? (float)$data['tax_amount']
        : ((float)$data['subtotal'] * ($ppn_percent / 100)))
    : 0.0;
$grand_total_display = $is_tax_included
    ? (float)($data['grand_total'] ?? 0)
    : (float)($data['subtotal'] ?? 0);
$tax_mode_label = !empty($data['tax_included']) ? 'INCLUDE PPN' : 'EXCLUDE PPN';
$client_sig_exists = !empty($data['client_signature_path']);
$client_sign_allowed = $is_public_mode && in_array((string)($data['status'] ?? ''), ['confirmed', 'in_production', 'delivered', 'completed'], true);

// FUNGSI PERBAIKAN TANDA TANGAN
function get_sig_img($path) {
    if (empty($path)) return '<div style="height: 60px;"></div>';
    
    // Coba beberapa kemungkinan path folder
    $paths_to_check = [
        $path,                  // Path langsung
        '../../../' . $path,    // Mundur 3 folder (dari modules/sales/orders/)
        '../../' . $path        // Mundur 2 folder
    ];

    foreach ($paths_to_check as $p) {
        if (file_exists($p)) {
            return '<img src="'.clean($p).'" style="height: 60px; max-width: 120px; object-fit: contain; display: block; margin: 0 auto; margin-bottom: -5px;">';
        }
    }
    
    // Jika file tidak ditemukan secara fisik, tampilkan teks debug kecil untuk admin (opsional)
    return '<div style="height: 60px; color: #ccc; font-size: 8px;">File Not Found</div>';
}
function get_so_client_sig_img($path) {
    if (empty($path)) return '<div style="height: 60px;"></div>';
    $paths_to_check = [$path, '../../../' . $path, '../../' . $path];
    foreach ($paths_to_check as $p) {
        if (file_exists($p)) {
            return '<img src="'.clean($p).'" style="height: 60px; max-width: 140px; object-fit: contain; display: block; margin: 0 auto; margin-bottom: -5px;">';
        }
    }
    return '<div style="height: 60px;"></div>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sales Order - <?= clean($data['so_number']) ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: auto; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .doc-title { font-size: 24px; font-weight: bold; color: #555; letter-spacing: 2px; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .item-table th { background: #f8f9fa; border-bottom: 1px solid #000; border-top: 1px solid #000; padding: 8px; text-align: left; }
        .item-table td { border-bottom: 1px solid #eee; padding: 8px; }
        .total-box { width: 45%; margin-left: auto; }
        .total-row { display: flex; justify-content: space-between; padding: 3px 0; }
        .total-final { border-top: 2px solid #000; margin-top: 5px; padding-top: 5px; font-size: 13px; font-weight: bold; }
        .signature-section { margin-top: 30px; display: flex; justify-content: space-between; text-align: center; }
        .sig-box { width: 30%; }
        .sig-line { border-top: 1px solid #000; margin-top: 5px; padding-top: 3px; font-weight: bold; font-size: 10px; }
        .page-footer { border-top: 1px solid #ccc; padding-top: 10px; text-align: center; margin-top: 20px; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }
        .client-sign-trigger { border: 1px dashed #0d6efd; background: #f8fbff; color: #0d6efd; border-radius: 6px; padding: 8px 10px; font-size: 10px; cursor: pointer; }
        .client-sign-trigger:hover { background: #eef5ff; }
        .sign-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 12px; }
        .sign-overlay.open { display: flex; }
        .sign-card { width: min(680px, 100%); background: #fff; border-radius: 10px; box-shadow: 0 20px 50px rgba(0,0,0,.25); overflow: hidden; }
        .sign-head { display:flex; justify-content:space-between; align-items:center; padding:10px 12px; background:#0d6efd; color:#fff; }
        .sign-body { padding: 12px; }
        .sign-canvas-wrap { border:1px solid #cfd6de; border-radius:8px; overflow:hidden; background:#fff; }
        #clientSignCanvas { width:100%; height:220px; display:block; touch-action:none; background:#fff; }
        .sign-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:10px; flex-wrap: wrap; }
        .sign-actions button, .sign-actions input { font-size: 12px; }
        .sign-note { font-size:10px; color:#555; margin-top:6px; }
        @media print { .no-print { display: none; } .box { border: none; } }
    </style>
</head>
<body<?= $is_public_mode ? '' : ' onload="window.print()"' ?>>
    <div class="box">
        <div class="no-print" style="text-align:center; margin-bottom: 10px;">
            <?php if ($is_public_mode): ?>
                <button onclick="window.print()" style="padding: 8px 15px; cursor: pointer;">Cetak / Simpan PDF</button>
                <?php if ($client_sign_allowed && !$client_sig_exists): ?>
                    <button type="button" id="openClientSignBtnTop" style="padding: 8px 15px; cursor:pointer; background:#0d6efd; color:#fff; border:1px solid #0d6efd; border-radius:4px;">TTD Customer</button>
                <?php endif; ?>
            <?php else: ?>
                <button onclick="window.print()" style="padding: 8px 15px; cursor: pointer;">Cetak PDF</button>
            <?php endif; ?>
        </div>
        <div class="doc-content">
            <div class="header">
                <div class="header-left">
                    <img src="<?= clean($logo_path_final) ?>" style="max-height: 60px;">
                </div>
                <div class="header-right">
                    <div class="doc-title">SALES ORDER</div>
                    <div style="font-size: 14px; font-weight: bold; color: #333; margin-top: 5px;"><?= clean($data['so_number']) ?></div>
                </div>
            </div>

            <table class="info-table">
                <tr>
                    <td width="55%"><strong>Customer:</strong><br><strong><?= strtoupper(clean($data['cust_name'])) ?></strong><br><?= nl2br(clean($data['cust_addr'])) ?></td>
                    <td width="45%" align="right">
                        <strong>Tanggal SO :</strong> <?= date('d F Y', strtotime($data['so_date'])) ?><br>
                        <strong>Est. Delivery :</strong> <?= !empty($data['delivery_date']) ? date('d F Y', strtotime($data['delivery_date'])) : '-' ?><br>
                        <strong>Salesman :</strong> <?= clean($data['sales_name']) ?><br>
                        <strong>Mode PPN :</strong> <?= clean($tax_mode_label) ?>
                    </td>
                </tr>
            </table>

            <table class="item-table">
                <thead>
                    <tr>
                        <th width="5%" class="text-center">No</th>
                        <th width="40%">Kode / Deskripsi Barang</th>
                        <th width="20%">Material</th>
                        <th width="8%" class="text-center">Qty</th>
                        <th width="8%" class="text-center">Unit</th>
                        <th width="12%" class="text-right">Harga Satuan (Rp)</th>
                        <th width="12%" class="text-right">Total (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no=1; foreach($items as $item): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td>
                            <?php if (!empty($item['item_code'])): ?>
                                <div><strong><?= clean($item['item_code']) ?></strong></div>
                            <?php endif; ?>
                            <div><?= clean($item['item_name']) ?></div>
                        </td>
                        <td><?= clean($item['material'] ?? '-') ?></td>
                        <td class="text-center"><?= $item['qty'] + 0 ?></td>
                        <td class="text-center"><?= clean($item['unit']) ?></td>
                        <td class="text-right"><?= number_format((float)$item['unit_price'], 0, ',', '.') ?></td>
                        <td class="text-right"><?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total-box">
                <div class="total-row"><span>Subtotal :</span><span>Rp <?= number_format($data['subtotal'], 0, ',', '.') ?></span></div>
                <?php if ((float)($data['discount_amount'] ?? 0) > 0): ?>
                    <div class="total-row"><span>Diskon :</span><span>- Rp <?= number_format((float)$data['discount_amount'], 0, ',', '.') ?></span></div>
                <?php endif; ?>
                <div class="total-row"><span>PPN (<?= rtrim(rtrim(number_format($ppn_percent, 2, '.', ''), '0'), '.') ?>%) :</span><span>Rp <?= number_format($tax_amount, 0, ',', '.') ?></span></div>
                <div class="total-row total-final"><span>GRAND TOTAL :</span><span>Rp <?= number_format($grand_total_display, 0, ',', '.') ?></span></div>
            </div>

            <div class="signature-section">
                <div class="sig-box">
                    Dikonfirmasi Oleh,
                    <?php if ($client_sig_exists): ?>
                        <?= get_so_client_sig_img($data['client_signature_path']) ?>
                    <?php elseif ($client_sign_allowed): ?>
                        <div style="height: 60px; display:flex; align-items:center; justify-content:center;">
                            <button type="button" id="openClientSignBtnInline" class="client-sign-trigger">Klik Tombol TTD</button>
                        </div>
                    <?php else: ?>
                        <div style="height: 60px;"></div>
                    <?php endif; ?>
                    <div class="sig-line">
                        Customer<br><small>(Tanda Tangan & Stempel)</small>
                        <?php if (!empty($data['client_signed_name']) || !empty($data['client_signed_at'])): ?>
                            <br><small style="font-weight:normal;color:#444;">
                                <?= clean($data['client_signed_name'] ?: 'Customer') ?>
                                <?php if (!empty($data['client_signed_at'])): ?> | <?= date('d/m/Y H:i', strtotime((string)$data['client_signed_at'])) ?><?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="sig-box">
                    Mengetahui,
                    <?= get_sig_img($data['mgr_sig']) ?>
                    <div class="sig-line"><?= clean($data['mgr_name'] ?: 'Manager') ?><br><small>Manager</small></div>
                </div>
                <div class="sig-box">
                    Disiapkan Oleh,
                    <?= get_sig_img($data['sales_sig']) ?>
                    <div class="sig-line"><?= clean($data['sales_name']) ?><br><small>Sales Admin</small></div>
                </div>
            </div>
        </div>

        <div class="page-footer">
            <span class="footer-comp-name"><?= strtoupper(clean($comp['company_name'] ?? 'MMS SYSTEM')) ?></span>
            <span class="footer-addr"><?= clean($comp['address'] ?? '-') ?></span>
        </div>
    </div>
    <?php if ($is_public_mode && $client_sign_allowed && !$client_sig_exists): ?>
    <div id="clientSignOverlay" class="sign-overlay no-print" aria-hidden="true">
        <div class="sign-card">
            <div class="sign-head">
                <strong>Tanda Tangan Customer (SO)</strong>
                <button type="button" id="closeClientSignBtn" style="background:transparent;border:0;color:#fff;font-size:20px;line-height:1;cursor:pointer;">&times;</button>
            </div>
            <div class="sign-body">
                <label style="display:block; font-size:12px; margin-bottom:6px;">Nama Penanda Tangan (opsional)</label>
                <input type="text" id="clientSignerName" value="<?= clean($data['cust_pic'] ?: $data['cust_name']) ?>" style="width:100%; padding:8px; border:1px solid #cfd6de; border-radius:6px; margin-bottom:10px;">
                <div class="sign-canvas-wrap"><canvas id="clientSignCanvas"></canvas></div>
                <div class="sign-note">Gunakan jari atau stylus untuk tanda tangan di area putih.</div>
                <div class="sign-actions">
                    <button type="button" id="clearClientSignBtn" style="padding:8px 12px; border:1px solid #adb5bd; background:#fff; border-radius:6px; cursor:pointer;">Ulangi</button>
                    <button type="button" id="saveClientSignBtn" style="padding:8px 12px; border:1px solid #198754; background:#198754; color:#fff; border-radius:6px; cursor:pointer;">Simpan TTD</button>
                </div>
                <div id="clientSignMsg" style="font-size:12px; margin-top:8px;"></div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        const overlay = document.getElementById('clientSignOverlay');
        const openTop = document.getElementById('openClientSignBtnTop');
        const openInline = document.getElementById('openClientSignBtnInline');
        const closeBtn = document.getElementById('closeClientSignBtn');
        const clearBtn = document.getElementById('clearClientSignBtn');
        const saveBtn = document.getElementById('saveClientSignBtn');
        const canvas = document.getElementById('clientSignCanvas');
        const signerInput = document.getElementById('clientSignerName');
        const msg = document.getElementById('clientSignMsg');
        if (!overlay || !canvas) return;
        const ctx = canvas.getContext('2d');
        let drawing = false, moved = false;

        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const rect = canvas.getBoundingClientRect();
            canvas.width = Math.max(1, Math.floor(rect.width * ratio));
            canvas.height = Math.max(1, Math.floor(rect.height * ratio));
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.lineWidth = 2.2; ctx.strokeStyle = '#111';
            ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, rect.width, rect.height);
        }
        function pos(e){ const r = canvas.getBoundingClientRect(); const src = (e && e.touches && e.touches[0]) ? e.touches[0] : ((e && e.changedTouches && e.changedTouches[0]) ? e.changedTouches[0] : e); const x = src && typeof src.clientX === 'number' ? src.clientX : 0; const y = src && typeof src.clientY === 'number' ? src.clientY : 0; return {x:x-r.left, y:y-r.top}; }
        function start(e){ drawing = true; const p=pos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); if(e.cancelable)e.preventDefault(); }
        function move(e){ if(!drawing) return; const p=pos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); moved = true; if(e.cancelable)e.preventDefault(); }
        function end(e){ if(!drawing) return; drawing=false; ctx.closePath(); if(e&&e.cancelable)e.preventDefault(); }
        function openPad(){ overlay.classList.add('open'); overlay.setAttribute('aria-hidden','false'); setTimeout(resizeCanvas, 10); }
        function closePad(){ overlay.classList.remove('open'); overlay.setAttribute('aria-hidden','true'); if(msg) msg.textContent=''; }
        function clearPad(){ moved=false; resizeCanvas(); if(msg) msg.textContent=''; }
        async function savePad() {
            if (!moved) { if (msg) { msg.style.color='#b42318'; msg.textContent='Silakan tanda tangan terlebih dahulu.'; } return; }
            saveBtn.disabled = true;
            if (msg) { msg.style.color='#555'; msg.textContent='Menyimpan tanda tangan...'; }
            try {
                const fd = new FormData();
                fd.append('action','save_client_signature');
                fd.append('signed_name', signerInput ? signerInput.value : '');
                fd.append('signature_data', canvas.toDataURL('image/png'));
                const res = await fetch(window.location.href, { method:'POST', body:fd, credentials:'same-origin' });
                const json = await res.json();
                if (!res.ok || !json || json.status !== 'success') throw new Error((json && json.message) ? json.message : 'Gagal menyimpan tanda tangan.');
                if (msg) { msg.style.color='#177245'; msg.textContent = json.message || 'Tanda tangan tersimpan.'; }
                setTimeout(() => window.location.reload(), 600);
            } catch (err) {
                if (msg) { msg.style.color='#b42318'; msg.textContent = err.message || 'Gagal menyimpan tanda tangan.'; }
            } finally {
                saveBtn.disabled = false;
            }
        }

        [openTop, openInline].forEach(btn => btn && btn.addEventListener('click', openPad));
        closeBtn && closeBtn.addEventListener('click', closePad);
        clearBtn && clearBtn.addEventListener('click', clearPad);
        saveBtn && saveBtn.addEventListener('click', savePad);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) closePad(); });
        window.addEventListener('resize', () => { if (overlay.classList.contains('open')) resizeCanvas(); });
        if (window.PointerEvent) {
            canvas.addEventListener('pointerdown', start);
            canvas.addEventListener('pointermove', move);
            window.addEventListener('pointerup', end);
            canvas.addEventListener('pointerleave', end);
        }
        canvas.addEventListener('mousedown', start);
        window.addEventListener('mousemove', move);
        window.addEventListener('mouseup', end);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end, { passive: false });
        canvas.addEventListener('touchcancel', end, { passive: false });
    })();
    </script>
    <?php endif; ?>
</body>
</html>
