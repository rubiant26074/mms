<?php
// modules/engineering/items/index.php
render_header("Master Barang");

// Cek Izin Lihat Harga
$can_see_price = has_permission('item_price_view');
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';

// Filter & Search
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$search_key  = isset($_GET['search']) ? clean($_GET['search']) : '';

// Build Query
$sql = "SELECT * FROM items WHERE 1=1";
$params = [];

if (!empty($filter_type)) {
    $sql .= " AND item_type = ?";
    $params[] = $filter_type;
}
if (!empty($search_key)) {
    $sql .= " AND (item_code LIKE ? OR item_name LIKE ?)";
    $params[] = "%$search_key%";
    $params[] = "%$search_key%";
}
$sql .= " ORDER BY item_code ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-box-seam"></i> Master Barang</h3>
        <p class="text-muted">Database Material, Sparepart, dan Finish Good.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=eng-items&action=create" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-lg"></i> Tambah Barang
        </a>
        <button type="button" class="btn btn-outline-success shadow-sm ms-2" data-bs-toggle="modal" data-bs-target="#importItemsModal">
            <i class="bi bi-file-earmark-excel"></i> Import Excel
        </button>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="item-filter-form">
            <input type="hidden" name="page" value="eng-items">
            
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari Kode atau Nama Barang..." value="<?= $search_key ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-3">
                <select name="filter_type" class="form-select">
                    <option value="">- Semua Tipe -</option>
                    <option value="raw_material" <?= $filter_type=='raw_material'?'selected':'' ?>>Raw Material</option>
                    <option value="wip" <?= $filter_type=='wip'?'selected':'' ?>>WIP (Setengah Jadi)</option>
                    <option value="finish_good" <?= $filter_type=='finish_good'?'selected':'' ?>>Finish Good</option>
                    <option value="consumable" <?= $filter_type=='consumable'?'selected':'' ?>>Consumable</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=eng-items" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- TABEL DATA -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Kode Barang</th>
                        <th>Nama Barang / Spesifikasi</th>
                        <th>Tipe / Owner</th>
                        <th class="text-center">Stok</th>
                        <th class="text-center">Satuan</th>
                        
                        <!-- Kolom Harga (Hanya muncul jika punya izin) -->
                        <?php if($can_see_price): ?>
                            <th class="text-end">Harga Dasar</th>
                        <?php endif; ?>

                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($items)): ?>
                        <tr><td colspan="<?= $can_see_price ? 7 : 6 ?>" class="text-center py-5 text-muted">Data tidak ditemukan.</td></tr>
                    <?php else: foreach($items as $row): 
                        // Badge Tipe
                        $badge = match($row['item_type']) { 
                            'raw_material'=>'bg-primary', 
                            'finish_good'=>'bg-success', 
                            'wip'=>'bg-warning text-dark', 
                            'consumable'=>'bg-secondary',
                            default=>'bg-light text-dark' 
                        };
                        $type_label = ucwords(str_replace('_', ' ', $row['item_type']));
                        
                        // Badge Owner
                        $own_badge = ($row['ownership'] == 'customer') 
                            ? '<span class="badge bg-info text-dark border ms-1">Consignment</span>' 
                            : '';

                        // Logic Stok Alert
                        $stk_class = ($row['current_stock'] <= $row['min_stock']) ? 'text-danger fw-bold' : 'text-dark fw-bold';
                        $alert_icon = ($row['current_stock'] <= $row['min_stock']) ? '<i class="bi bi-exclamation-circle-fill ms-1" title="Stok Menipis"></i>' : '';
                        $drawing_link_raw = (string)($row['drawing_file'] ?? '');
                        $drawing_link_safe = preg_match('/^(uploads\/|https?:\/\/)/i', $drawing_link_raw) ? $drawing_link_raw : '';
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold text-primary"><?= clean($row['item_code']) ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div><?= clean($row['item_name']) ?></div>
                                
                                <!-- Indikator Drawing -->
                                <?php if(!empty($drawing_link_safe)): ?>
                                    <a href="<?= clean($drawing_link_safe) ?>" target="_blank" rel="noopener noreferrer" class="ms-2 text-danger" title="Lihat Drawing (PDF/IMG)">
                                        <i class="bi bi-file-earmark-pdf-fill fs-5"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php if(!empty($row['description'])): ?>
                                <small class="text-muted d-block text-truncate" style="max-width: 250px;"><?= clean($row['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $badge ?>"><?= $type_label ?></span>
                            <?= $own_badge ?>
                        </td>
                        <td class="text-center <?= $stk_class ?>">
                            <?= $row['current_stock'] + 0 ?> <?= $alert_icon ?>
                        </td>
                        <td class="text-center"><?= clean($row['unit']) ?></td>
                        
                        <!-- Cell Harga -->
                        <?php if($can_see_price): ?>
                            <td class="text-end text-success fw-bold">
                                Rp <?= number_format($row['base_price'], 0, ',', '.') ?>
                            </td>
                        <?php endif; ?>

                        <td class="text-center">
                            <div class="btn-group">
                                <a href="index.php?page=eng-items&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning text-dark" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="index.php?page=eng-items&action=delete&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus barang <?= clean($row['item_code']) ?>?')" title="Hapus"><i class="bi bi-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL IMPORT EXCEL -->
<div class="modal fade" id="importItemsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-file-earmark-excel"></i> Import Master Barang (Excel)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small mb-3">
                    <strong>Format kolom (header):</strong>
                    <code>item_code, item_name, item_type, ownership, qc_type, unit, base_price, min_stock, description, customer_code, customer_name, drawing_file</code>
                    <br>Header boleh pakai variasi kecil (spasi/kapital). Jika tanpa header, urutan kolom harus sesuai.
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">File Excel (.xlsx/.xls/.csv)</label>
                        <input type="file" id="itemsExcelFile" class="form-control" accept=".xlsx,.xls,.csv">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mode Import</label>
                        <select id="itemsImportMode" class="form-select">
                            <option value="skip">Skip jika kode sudah ada</option>
                            <option value="update">Update jika kode sudah ada</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="button" id="btnItemsImport" class="btn btn-success">
                            <i class="bi bi-upload"></i> Mulai Import
                        </button>
                    </div>
                </div>
                <div class="mt-2">
                    <a href="assets/templates/master_barang_template.xls" class="btn btn-sm btn-outline-primary" download>
                        <i class="bi bi-download"></i> Download Template Excel
                    </a>
                </div>
                <div id="itemsImportResult" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('item-filter-form');
    if (!form) return;

    const search = form.querySelector('input[name="search"]');
    const type = form.querySelector('select[name="filter_type"]');
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
    if (type) {
        type.addEventListener('change', submit);
    }
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function normalizeHeader(val) {
    return String(val || '').toLowerCase().trim().replace(/\s+/g, '_');
}
function mapRowToItem(row, headerMap) {
    const obj = {};
    for (const [key, idx] of Object.entries(headerMap)) {
        obj[key] = row[idx] !== undefined ? row[idx] : '';
    }
    return obj;
}
function detectHeaderRow(row) {
    const headers = row.map(normalizeHeader);
    return headers.includes('item_code') && headers.includes('item_name');
}

document.getElementById('btnItemsImport')?.addEventListener('click', function () {
    const fileInput = document.getElementById('itemsExcelFile');
    const mode = document.getElementById('itemsImportMode')?.value || 'skip';
    const resultBox = document.getElementById('itemsImportResult');
    if (!fileInput.files || !fileInput.files[0]) {
        alert('Pilih file Excel terlebih dahulu.');
        return;
    }
    if (typeof XLSX === 'undefined') {
        alert('Library Excel gagal dimuat. Cek koneksi internet lalu refresh halaman.');
        return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
        try {
            const data = e.target.result;
            const workbook = XLSX.read(data, { type: 'array' });
            const sheetName = workbook.SheetNames[0];
            const ws = workbook.Sheets[sheetName];
            const rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });

            if (!rows || rows.length === 0) {
                alert('File Excel kosong.');
                return;
            }

            const headerRow = rows[0];
            const hasHeader = detectHeaderRow(headerRow);
            const headerMap = {};
            if (hasHeader) {
                headerRow.forEach((h, idx) => {
                    const key = normalizeHeader(h);
                    if (key) headerMap[key] = idx;
                });
            } else {
                const defaultCols = [
                    'item_code','item_name','item_type','ownership','qc_type','unit','base_price','min_stock','description','customer_code','customer_name','drawing_file'
                ];
                defaultCols.forEach((k, i) => headerMap[k] = i);
            }

            const items = [];
            const startIndex = hasHeader ? 1 : 0;
            for (let i = startIndex; i < rows.length; i++) {
                const r = rows[i] || [];
                const rowObj = mapRowToItem(r, headerMap);
                if (!rowObj.item_code && !rowObj.item_name) continue;
                items.push(rowObj);
            }

            if (items.length === 0) {
                alert('Tidak ada baris data yang valid.');
                return;
            }

            fetch('index.php?page=eng-items&action=import_ajax', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ rows: items, mode, csrf: '<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>' })
            })
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success') {
                    resultBox.innerHTML = `<div class="alert alert-danger">${res.message || 'Gagal import.'}</div>`;
                    return;
                }
                let html = `<div class="alert alert-success">Import selesai. Inserted: <b>${res.inserted}</b>, Updated: <b>${res.updated}</b>, Skipped: <b>${res.skipped}</b>.</div>`;
                if (res.errors && res.errors.length) {
                    html += `<div class="alert alert-warning"><div class="fw-bold mb-1">Catatan:</div><ul class="mb-0">${res.errors.map(e => `<li>${escapeHtml(e)}</li>`).join('')}</ul></div>`;
                }
                resultBox.innerHTML = html;
                fileInput.value = '';
            })
            .catch(err => {
                resultBox.innerHTML = `<div class="alert alert-danger">Error: ${err}</div>`;
            });
        } catch (err) {
            alert('Gagal membaca file Excel: ' + (err && err.message ? err.message : err));
        }
    };
    reader.readAsArrayBuffer(fileInput.files[0]);
});
</script>

<?php render_footer(); ?>
