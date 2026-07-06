<?php
// modules/sales/customers/index.php
if (
    !function_exists('is_logged_in') || !is_logged_in() ||
    !has_permission('sales_customer_view')
) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=dashboard';</script>";
    exit;
}

render_header("Master Customer");
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

// Filter & Search
$search_key  = isset($_GET['search']) ? clean($_GET['search']) : '';
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-people-fill"></i> Master Customer</h3>
        <p class="text-muted">Database Pelanggan</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=sales-customers&action=create" class="btn btn-primary shadow-sm">
            <i class="bi bi-person-plus-fill"></i> Tambah Customer
        </a>
        <button type="button" class="btn btn-outline-success shadow-sm ms-2" data-bs-toggle="modal" data-bs-target="#importCustomerModal">
            <i class="bi bi-file-earmark-excel"></i> Import Excel
        </button>
    </div>
</div>

<div class="modal fade" id="importCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-file-earmark-excel"></i> Import Master Customer (Excel)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small mb-3">
                    <strong>Format kolom (header):</strong>
                    <code>customer_code, name, address, phone, pic, email, tax_id</code>
                    <br>Header boleh pakai variasi kecil (spasi/kapital). Jika tanpa header, urutan kolom harus sesuai.
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">File Excel (.xlsx/.xls/.csv)</label>
                        <input type="file" id="custExcelFile" class="form-control" accept=".xlsx,.xls,.csv">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mode Import</label>
                        <select id="custImportMode" class="form-select">
                            <option value="skip">Skip jika sudah ada</option>
                            <option value="update">Update jika sudah ada</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="button" id="btnCustImport" class="btn btn-success">
                            <i class="bi bi-upload"></i> Mulai Import
                        </button>
                    </div>
                </div>
                <div class="mt-2">
                    <a href="assets/templates/master_customer_template.xls" class="btn btn-sm btn-outline-primary" download>
                        <i class="bi bi-download"></i> Download Template Excel
                    </a>
                </div>
                <div id="custImportResult" class="mt-3"></div>
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
        <form method="GET" class="row g-2 align-items-center" id="customer-filter-form">
            <input type="hidden" name="page" value="sales-customers">
            
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari Kode / Nama / PIC / Telepon / Email..." value="<?= $esc($search_key) ?>" autocomplete="off">
                </div>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="index.php?page=sales-customers" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
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
                        <th width="15%">Kode</th>
                        <th width="25%">Nama Perusahaan</th>
                        <th width="20%">PIC / Kontak</th>
                        <th width="30%">Alamat / Email</th>
                        <th width="10%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT * FROM customers WHERE 1=1";
                    $params = [];
                    if (!empty($search_key)) {
                        $sql .= " AND (customer_code LIKE ? OR name LIKE ? OR pic LIKE ? OR phone LIKE ? OR email LIKE ?)";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                        $params[] = "%$search_key%";
                    }
                    $sql .= " ORDER BY id DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    if ($stmt->rowCount() == 0) {
                        echo '<tr><td colspan="5" class="text-center py-4 text-muted">Belum ada data customer.</td></tr>';
                    }

                    while ($row = $stmt->fetch()):
                    ?>
                    <tr>
                        <td>
                            <span class="badge bg-secondary"><?= !empty($row['customer_code']) ? clean($row['customer_code']) : '-' ?></span>
                        </td>
                        <td class="fw-bold text-primary"><?= clean($row['name']) ?></td>
                        <td>
                            <strong><?= clean($row['pic']) ?></strong><br>
                            <small class="text-muted"><i class="bi bi-telephone"></i> <?= clean($row['phone']) ?></small>
                        </td>
                        <td>
                            <small class="d-block text-truncate" style="max-width: 250px;" title="<?= clean($row['address']) ?>">
                                <i class="bi bi-geo-alt"></i> <?= clean($row['address']) ?>
                            </small>
                            <?php if(!empty($row['email'])): ?>
                                <small class="text-primary"><i class="bi bi-envelope"></i> <?= clean($row['email']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="index.php?page=sales-customers&action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning text-dark" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="index.php?page=sales-customers&action=delete&id=<?= $row['id'] ?>&csrf=<?= urlencode($csrf) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus customer ini?')" title="Hapus"><i class="bi bi-trash"></i></a>
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
    const form = document.getElementById('customer-filter-form');
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
    return headers.includes('name');
}

document.getElementById('btnCustImport')?.addEventListener('click', function () {
    const fileInput = document.getElementById('custExcelFile');
    const mode = document.getElementById('custImportMode')?.value || 'skip';
    const resultBox = document.getElementById('custImportResult');
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
                const defaultCols = ['customer_code','name','address','phone','pic','email','tax_id'];
                defaultCols.forEach((k, i) => headerMap[k] = i);
            }

            const items = [];
            const startIndex = hasHeader ? 1 : 0;
            for (let i = startIndex; i < rows.length; i++) {
                const r = rows[i] || [];
                const rowObj = mapRowToItem(r, headerMap);
                if (!rowObj.name) continue;
                items.push(rowObj);
            }

            if (items.length === 0) {
                alert('Tidak ada baris data yang valid.');
                return;
            }

            fetch('index.php?page=sales-customers&action=import_ajax', {
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
