<?php
// modules/procurement/suppliers/index.php
if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('purch_vendor_view')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=dashboard';</script>";
    exit;
}

render_header("Vendor List (Master Supplier)");
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

// Filter & Search
$search_key = isset($_GET['search']) ? clean($_GET['search']) : '';
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-shop"></i> Master Supplier</h3>
        <p class="text-muted">Database vendor dan pemasok material.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=purch-vendor&action=create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Tambah Vendor
        </a>
        <button type="button" class="btn btn-outline-success shadow-sm ms-2" data-bs-toggle="modal" data-bs-target="#importVendorModal">
            <i class="bi bi-file-earmark-excel"></i> Import Excel
        </button>
    </div>
</div>

<!-- MODAL IMPORT EXCEL -->
<div class="modal fade" id="importVendorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-file-earmark-excel"></i> Import Master Vendor (Excel)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small mb-3">
                    <strong>Format kolom (header):</strong>
                    <code>code, name, address, phone, email, contact_person, bank_name, bank_number</code>
                    <br>Header boleh pakai variasi kecil (spasi/kapital). Jika tanpa header, urutan kolom harus sesuai.
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">File Excel (.xlsx/.xls/.csv)</label>
                        <input type="file" id="vendorExcelFile" class="form-control" accept=".xlsx,.xls,.csv">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mode Import</label>
                        <select id="vendorImportMode" class="form-select">
                            <option value="skip">Skip jika sudah ada</option>
                            <option value="update">Update jika sudah ada</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="button" id="btnVendorImport" class="btn btn-success">
                            <i class="bi bi-upload"></i> Mulai Import
                        </button>
                    </div>
                </div>
                <div class="mt-2">
                    <a href="assets/templates/master_vendor_template.xls" class="btn btn-sm btn-outline-primary" download>
                        <i class="bi bi-download"></i> Download Template Excel
                    </a>
                </div>
                <div id="vendorImportResult" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- CARD FILTER -->
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="vendor-filter-form">
            <input type="hidden" name="page" value="purch-vendor">
            
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari Kode / Nama / Kontak / Telp / Bank..." value="<?= $esc($search_key) ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=purch-vendor" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
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
                        <th>Kode</th>
                        <th>Nama Vendor</th>
                        <th>Kontak</th>
                        <th>Info Bank</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT * FROM suppliers WHERE 1=1";
                    $params = [];
                    if (!empty($search_key)) {
                        $sql .= " AND (code LIKE ? OR name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR bank_name LIKE ? OR bank_number LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY code ASC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    while ($row = $stmt->fetch()):
                    ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?= clean($row['code']) ?></span></td>
                        <td>
                            <strong><?= clean($row['name']) ?></strong><br>
                            <small class="text-muted"><?= substr(clean($row['address']), 0, 40) ?>...</small>
                        </td>
                        <td>
                            <i class="bi bi-person"></i> <?= clean($row['contact_person']) ?><br>
                            <small><i class="bi bi-telephone"></i> <?= clean($row['phone']) ?></small>
                        </td>
                        <td>
                            <?php if(!empty($row['bank_name'])): ?>
                                <small class="d-block text-muted"><?= clean($row['bank_name']) ?></small>
                                <strong class="small"><?= clean($row['bank_number']) ?></strong>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="index.php?page=purch-vendor&action=edit&id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning text-white" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="index.php?page=purch-vendor&action=delete&id=<?= (int)$row['id'] ?>&csrf=<?= urlencode($csrf) ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Hapus vendor ini?');" title="Hapus">
                               <i class="bi bi-trash"></i>
                            </a>
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
    const form = document.getElementById('vendor-filter-form');
    if (!form) return;

    const search = form.querySelector('input[name="search"]');
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

function normalizeHeaderVendor(val) {
    return String(val || '').toLowerCase().trim().replace(/\s+/g, '_');
}
function mapRowToVendor(row, headerMap) {
    const obj = {};
    for (const [key, idx] of Object.entries(headerMap)) {
        obj[key] = row[idx] !== undefined ? row[idx] : '';
    }
    return obj;
}
function detectHeaderVendor(row) {
    const headers = row.map(normalizeHeaderVendor);
    return headers.includes('name');
}

document.getElementById('btnVendorImport')?.addEventListener('click', function () {
    const fileInput = document.getElementById('vendorExcelFile');
    const mode = document.getElementById('vendorImportMode')?.value || 'skip';
    const resultBox = document.getElementById('vendorImportResult');
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
            const hasHeader = detectHeaderVendor(headerRow);
            const headerMap = {};
            if (hasHeader) {
                headerRow.forEach((h, idx) => {
                    const key = normalizeHeaderVendor(h);
                    if (key) headerMap[key] = idx;
                });
            } else {
                const defaultCols = ['code','name','address','phone','email','contact_person','bank_name','bank_number'];
                defaultCols.forEach((k, i) => headerMap[k] = i);
            }

            const items = [];
            const startIndex = hasHeader ? 1 : 0;
            for (let i = startIndex; i < rows.length; i++) {
                const r = rows[i] || [];
                const rowObj = mapRowToVendor(r, headerMap);
                if (!rowObj.name) continue;
                items.push(rowObj);
            }

            if (items.length === 0) {
                alert('Tidak ada baris data yang valid.');
                return;
            }

            fetch('index.php?page=purch-vendor&action=import_ajax', {
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
