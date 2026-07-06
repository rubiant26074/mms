<?php
// modules/sales/quotations/print.php
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

// Mode public dipakai oleh modules/public/quotation.php setelah token tervalidasi.
$is_public_mode = defined('MMS_PUBLIC_QUOTE_MODE') && MMS_PUBLIC_QUOTE_MODE === true;
if (
    !$is_public_mode &&
    (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('sales_quotation_manage'))
) {
    die("Akses ditolak.");
}

if (!isset($_GET['id'])) die("Error: ID tidak ditemukan.");
$id = (int)$_GET['id'];
if (function_exists('mms_ensure_quotations_client_signature_columns')) {
    mms_ensure_quotations_client_signature_columns($pdo);
}

$sql = "SELECT q.*,
               c.name as cust_name, c.address as cust_addr, c.pic as cust_pic, c.phone as cust_phone,
               u_create.fullname as sales_name, u_create.signature_path as sales_sig,
               u_mgr.fullname as mgr_name, u_mgr.signature_path as mgr_sig
        FROM quotations q
        JOIN customers c ON q.customer_id = c.id
        LEFT JOIN users u_create ON q.created_by = u_create.id
        LEFT JOIN users u_mgr ON q.approved_by = u_mgr.id
        WHERE q.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch();
if (!$data) die("Quotation tidak ditemukan.");
$ppn_percent = isset($data['ppn_percent']) && (float)$data['ppn_percent'] > 0 ? (float)$data['ppn_percent'] : 11.0;
$tax_mode_label = !empty($data['tax_included']) ? 'INCLUDE PPN' : 'EXCLUDE PPN';

$sql_items = "SELECT item_code_manual as item_code, item_name_manual as item_name, material_manual as material, unit_manual as unit, qty, unit_price, subtotal
              FROM quotation_items WHERE quotation_id = ?";
$stmt_items = $pdo->prepare($sql_items);
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll();

$comp = get_company_profile();
$logo_path_final = file_exists($comp['logo_path']) ? $comp['logo_path'] : '../../../' . $comp['logo_path'];
$client_sig_exists = !empty($data['client_signature_path']);
$client_sign_allowed = $is_public_mode && in_array((string)($data['status'] ?? ''), ['approved', 'sent', 'won', 'so_created'], true);

function get_sig_img_quote($path, $allow_if_approved = false) {
    if (empty($path)) {
        return $allow_if_approved
            ? '<div style="height: 60px; font-size: 9px; color: #666; padding-top: 20px;">Digitally Approved</div>'
            : '<div style="height: 60px;"></div>';
    }
    $paths_to_check = [$path, '../../../' . $path, '../../' . $path];
    foreach ($paths_to_check as $p) {
        if (file_exists($p)) {
            return '<img src="' . clean($p) . '" style="height: 60px; max-width: 120px; object-fit: contain; display: block; margin: 0 auto; margin-bottom: -5px;">';
        }
    }
    return '<div style="height: 60px;"></div>';
}

function get_quote_client_sig_img($path) {
    if (empty($path)) return '<div style="height: 60px;"></div>';
    $paths_to_check = [$path, '../../../' . $path, '../../' . $path];
    foreach ($paths_to_check as $p) {
        if (file_exists($p)) {
            return '<img src="' . clean($p) . '" style="height: 60px; max-width: 140px; object-fit: contain; display: block; margin: 0 auto; margin-bottom: -5px;">';
        }
    }
    return '<div style="height: 60px;"></div>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Quotation - <?= clean($data['quote_number']) ?></title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .doc-title { font-size: 24px; font-weight: bold; color: #555; letter-spacing: 2px; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { vertical-align: top; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .item-table th { background: #f8f9fa; border-bottom: 1px solid #000; border-top: 1px solid #000; padding: 8px; text-align: left; }
        .item-table td { border-bottom: 1px solid #eee; padding: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-box { width: 45%; margin-left: auto; }
        .total-row { display: flex; justify-content: space-between; padding: 3px 0; }
        .total-final { border-top: 2px solid #000; margin-top: 5px; padding-top: 5px; font-size: 13px; font-weight: bold; }
        .signature-section { margin-top: 30px; margin-bottom: 24px; display: flex; justify-content: space-between; text-align: center; }
        .sig-box { width: 30%; }
        .sig-line { border-top: 1px solid #000; margin-top: 5px; padding-top: 3px; font-weight: bold; font-size: 10px; }
        .page-footer { border-top: 1px solid #ccc; padding-top: 10px; margin-top: 12px; text-align: center; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }
        .notes-box { background: #f9f9f9; border: 1px solid #eee; padding: 10px; border-radius: 5px; font-size: 10px; }
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
    <div class="no-print" style="text-align: center; margin-bottom: 15px;">
        <?php if ($is_public_mode): ?>
            <button onclick="window.print()" style="padding: 8px 15px; cursor: pointer;">Cetak / Simpan PDF</button>
            <?php if ($client_sign_allowed && !$client_sig_exists): ?>
                <button type="button" id="openClientSignBtnTop" style="padding: 8px 15px; cursor: pointer; background:#0d6efd; color:#fff; border:1px solid #0d6efd; border-radius:4px;">TTD Customer</button>
            <?php endif; ?>
        <?php else: ?>
            <button onclick="window.print()" style="padding: 8px 15px; cursor: pointer;">Cetak PDF</button>
        <?php endif; ?>
    </div>
    <div class="box">
        <div class="header">
            <div>
                <img src="<?= clean($logo_path_final) ?>" alt="Logo" style="max-height: 60px;">
            </div>
            <div style="text-align:right;">
                <div class="doc-title">QUOTATION</div>
                <div style="font-size: 14px; font-weight: bold; color: #333; margin-top: 5px;"><?= clean($data['quote_number']) ?></div>
                <?php if ((int)$data['revision_version'] > 0): ?>
                    <div style="font-size: 11px; color: #b91c1c; font-weight: bold;">REVISI R<?= (int)$data['revision_version'] ?></div>
                <?php endif; ?>
            </div>
        </div>

        <table class="info-table">
            <tr>
                <td width="55%">
                    <strong>Kepada Yth:</strong><br>
                    <strong><?= strtoupper(clean($data['cust_name'])) ?></strong><br>
                    Attn: <?= clean($data['cust_pic'] ?: '-') ?><br>
                    Telp: <?= clean($data['cust_phone'] ?: '-') ?><br>
                    <?= nl2br(clean($data['cust_addr'] ?: '-')) ?>
                </td>
                <td width="45%" align="right">
                    <strong>Tanggal :</strong> <?= date('d F Y', strtotime($data['quote_date'])) ?><br>
                    <strong>Valid Hingga :</strong> <?= date('d F Y', strtotime($data['quote_date'] . ' +14 days')) ?><br>
                    <strong>Salesman :</strong> <?= clean($data['sales_name'] ?: '-') ?><br>
                    <strong>Terms of Payment :</strong> <?= clean($data['payment_terms'] ?: '-') ?><br>
                    <strong>Mode PPN :</strong> <?= clean($tax_mode_label) ?>
                </td>
            </tr>
        </table>

        <table class="item-table">
            <thead>
                <tr>
                    <th width="5%" class="text-center">No</th>
                    <th>Deskripsi Barang</th>
                    <th width="15%">Material</th>
                    <th width="10%" class="text-center">Qty</th>
                    <th width="8%" class="text-center">Unit</th>
                    <th width="13%" class="text-right">Harga (Rp)</th>
                    <th width="15%" class="text-right">Total (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($items as $item): ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td>
                        <strong><?= clean($item['item_name']) ?></strong>
                        <?php if (!empty($item['item_code'])): ?>
                            <br><small style="color:#666;">Kode: <?= clean($item['item_code']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= clean($item['material'] ?? '-') ?></td>
                    <td class="text-center"><?= $item['qty'] + 0 ?></td>
                    <td class="text-center"><?= clean($item['unit']) ?></td>
                    <td class="text-right"><?= number_format($item['unit_price'], 0, ',', '.') ?></td>
                    <td class="text-right"><?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="display:flex; gap:16px;">
            <div style="width:55%;">
                <?php if (!empty($data['notes'])): ?>
                    <div class="notes-box">
                        <strong>Catatan / Instruksi Khusus:</strong><br>
                        <?= nl2br(clean($data['notes'])) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="total-box">
                <div class="total-row"><span>Subtotal :</span><span>Rp <?= number_format($data['subtotal'], 0, ',', '.') ?></span></div>
                <?php if ((float)$data['discount_amount'] > 0): ?>
                    <div class="total-row"><span>Diskon :</span><span>- Rp <?= number_format($data['discount_amount'], 0, ',', '.') ?></span></div>
                <?php endif; ?>
                <div class="total-row"><span>PPN (<?= rtrim(rtrim(number_format($ppn_percent, 2, '.', ''), '0'), '.') ?>%) :</span><span>Rp <?= number_format($data['tax_amount'], 0, ',', '.') ?></span></div>
                <div class="total-row total-final"><span>GRAND TOTAL :</span><span>Rp <?= number_format($data['grand_total'], 0, ',', '.') ?></span></div>
            </div>
        </div>

        <div class="signature-section">
            <div class="sig-box">
                Dikonfirmasi Oleh,
                <?php if ($client_sig_exists): ?>
                    <?= get_quote_client_sig_img($data['client_signature_path']) ?>
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
                <?php
                    $approved = in_array($data['status'], ['approved', 'sent', 'won', 'so_created'], true);
                    echo get_sig_img_quote($data['mgr_sig'], $approved);
                ?>
                <div class="sig-line"><?= clean($data['mgr_name'] ?: 'Sales Manager') ?><br><small>Manager</small></div>
            </div>
            <div class="sig-box">
                Disiapkan Oleh,
                <?= get_sig_img_quote($data['sales_sig'], false) ?>
                <div class="sig-line"><?= clean($data['sales_name'] ?: 'Sales Admin') ?><br><small>Sales Admin</small></div>
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
                    <strong>Tanda Tangan Customer</strong>
                    <button type="button" id="closeClientSignBtn" style="background:transparent;border:0;color:#fff;font-size:20px;line-height:1;cursor:pointer;">&times;</button>
                </div>
                <div class="sign-body">
                    <label style="display:block; font-size:12px; margin-bottom:6px;">Nama Penanda Tangan (opsional)</label>
                    <input type="text" id="clientSignerName" value="<?= clean($data['cust_pic'] ?: $data['cust_name']) ?>" style="width:100%; padding:8px; border:1px solid #cfd6de; border-radius:6px; margin-bottom:10px;">
                    <div class="sign-canvas-wrap">
                        <canvas id="clientSignCanvas"></canvas>
                    </div>
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
            let drawing = false;
            let moved = false;

            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const rect = canvas.getBoundingClientRect();
                canvas.width = Math.max(1, Math.floor(rect.width * ratio));
                canvas.height = Math.max(1, Math.floor(rect.height * ratio));
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.lineWidth = 2.2;
                ctx.strokeStyle = '#111';
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, rect.width, rect.height);
            }

            function getPos(e) {
                const rect = canvas.getBoundingClientRect();
                const src = (e && e.touches && e.touches[0])
                    ? e.touches[0]
                    : ((e && e.changedTouches && e.changedTouches[0]) ? e.changedTouches[0] : e);
                const clientX = src && typeof src.clientX === 'number' ? src.clientX : 0;
                const clientY = src && typeof src.clientY === 'number' ? src.clientY : 0;
                return { x: clientX - rect.left, y: clientY - rect.top };
            }

            function startDraw(e) {
                drawing = true;
                const p = getPos(e);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                if (e.cancelable) e.preventDefault();
            }

            function moveDraw(e) {
                if (!drawing) return;
                const p = getPos(e);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                moved = true;
                if (e.cancelable) e.preventDefault();
            }

            function endDraw(e) {
                if (!drawing) return;
                drawing = false;
                ctx.closePath();
                if (e && e.cancelable) e.preventDefault();
            }

            function openPad() {
                overlay.classList.add('open');
                overlay.setAttribute('aria-hidden', 'false');
                setTimeout(() => {
                    resizeCanvas();
                }, 10);
            }

            function closePad() {
                overlay.classList.remove('open');
                overlay.setAttribute('aria-hidden', 'true');
                if (msg) msg.textContent = '';
            }

            function clearPad() {
                moved = false;
                resizeCanvas();
                if (msg) msg.textContent = '';
            }

            async function savePad() {
                if (!moved) {
                    if (msg) { msg.style.color = '#b42318'; msg.textContent = 'Silakan tanda tangan terlebih dahulu.'; }
                    return;
                }
                saveBtn.disabled = true;
                if (msg) { msg.style.color = '#555'; msg.textContent = 'Menyimpan tanda tangan...'; }
                try {
                    const fd = new FormData();
                    fd.append('action', 'save_client_signature');
                    fd.append('signed_name', signerInput ? signerInput.value : '');
                    fd.append('signature_data', canvas.toDataURL('image/png'));

                    const res = await fetch(window.location.href, {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin'
                    });
                    const json = await res.json();
                    if (!res.ok || !json || json.status !== 'success') {
                        throw new Error((json && json.message) ? json.message : 'Gagal menyimpan tanda tangan.');
                    }
                    if (msg) { msg.style.color = '#177245'; msg.textContent = json.message || 'Tanda tangan tersimpan.'; }
                    setTimeout(() => window.location.reload(), 600);
                } catch (err) {
                    if (msg) { msg.style.color = '#b42318'; msg.textContent = err.message || 'Gagal menyimpan tanda tangan.'; }
                } finally {
                    saveBtn.disabled = false;
                }
            }

            [openTop, openInline].forEach((btn) => btn && btn.addEventListener('click', openPad));
            closeBtn && closeBtn.addEventListener('click', closePad);
            clearBtn && clearBtn.addEventListener('click', clearPad);
            saveBtn && saveBtn.addEventListener('click', savePad);
            overlay.addEventListener('click', (e) => { if (e.target === overlay) closePad(); });
            window.addEventListener('resize', () => { if (overlay.classList.contains('open')) resizeCanvas(); });

            if (window.PointerEvent) {
                canvas.addEventListener('pointerdown', startDraw);
                canvas.addEventListener('pointermove', moveDraw);
                window.addEventListener('pointerup', endDraw);
                canvas.addEventListener('pointerleave', endDraw);
            }
            canvas.addEventListener('mousedown', startDraw);
            window.addEventListener('mousemove', moveDraw);
            window.addEventListener('mouseup', endDraw);
            canvas.addEventListener('touchstart', startDraw, { passive: false });
            canvas.addEventListener('touchmove', moveDraw, { passive: false });
            canvas.addEventListener('touchend', endDraw, { passive: false });
            canvas.addEventListener('touchcancel', endDraw, { passive: false });
        })();
        </script>
    <?php endif; ?>
</body>
</html>
