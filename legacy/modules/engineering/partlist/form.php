<?php
// modules/engineering/partlist/form.php
if (!function_exists('is_logged_in') || !is_logged_in() || !has_permission('eng_view')) {
    echo "<script>alert('Akses ditolak.'); window.location='index.php?page=eng-partlist';</script>";
    exit;
}
$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$spk_id = isset($_GET['spk_id']) ? (int)$_GET['spk_id'] : 0;
if ($spk_id <= 0) die("Error: SPK ID Required");

// 1. Load Data SPK & Partlist Existing
$stmt = $pdo->prepare("SELECT * FROM spk WHERE id=?"); 
$stmt->execute([$spk_id]); 
$spk = $stmt->fetch();
if (!$spk) die("Error: Data SPK tidak ditemukan.");

$existing_parts = $pdo->prepare("SELECT * FROM spk_partlists WHERE spk_id=? ORDER BY id ASC"); 
$existing_parts->execute([$spk_id]); 
$parts = $existing_parts->fetchAll();

// 2. Logika Simpan & Approval Engineering
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_req = $_POST['csrf'] ?? '';
    if (!function_exists('verify_mms_csrf_token') || !verify_mms_csrf_token($csrf_req)) {
        die("Permintaan tidak valid (CSRF).");
    }

    $spk_id = isset($_POST['spk_id']) ? (int)$_POST['spk_id'] : $spk_id;
    if ($spk_id <= 0) die("Error: SPK ID invalid");

    $draw_link = $_POST['drawing_link'];
    $is_approve = isset($_POST['approve_now']) ? true : false;
    
    $nos = $_POST['no'] ?? [];
    $draw_nos = $_POST['drawing_no'] ?? [];
    $names = $_POST['part_name'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $mats = $_POST['material'] ?? [];
    $thks = $_POST['thickness'] ?? [];
    $lens = $_POST['length'] ?? [];
    $wids = $_POST['width'] ?? [];
    $procs = $_POST['process'] ?? [];
    $notes = $_POST['notes'] ?? [];

    try {
        $pdo->beginTransaction();

        // Pastikan parent SPK tetap ada saat transaksi berjalan.
        $stmt_spk_lock = $pdo->prepare("SELECT id FROM spk WHERE id=? FOR UPDATE");
        $stmt_spk_lock->execute([$spk_id]);
        if (!$stmt_spk_lock->fetchColumn()) {
            throw new Exception("SPK tidak ditemukan atau sudah dihapus. Silakan refresh halaman.");
        }
        
        // Update Link Drawing di Header
        $pdo->prepare("UPDATE spk SET drawing_link=? WHERE id=?")->execute([$draw_link, $spk_id]);

        // Reset & Insert Partlist Baru
        $pdo->prepare("DELETE FROM spk_partlists WHERE spk_id=?")->execute([$spk_id]);
        $stmt_ins = $pdo->prepare("INSERT INTO spk_partlists (spk_id, item_no, drawing_no, part_name, qty, material, thickness, length, width, process, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        for($i=0; $i<count($names); $i++) {
            if(!empty($names[$i])) {
                $stmt_ins->execute([$spk_id, $nos[$i], $draw_nos[$i], $names[$i], $qtys[$i], $mats[$i], $thks[$i], $lens[$i], $wids[$i], $procs[$i], $notes[$i]]);
            }
        }

        // Logic Approval (Kolom 2 Signature)
        if ($is_approve && has_permission('eng_partlist_approve')) {
            $pdo->prepare("UPDATE spk SET status='final', approved_by_eng=?, approved_at_eng=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $spk_id]);
            if (function_exists('notify_workflow_event')) {
                notify_workflow_event(
                    'engineering.partlist.final.' . (int)$spk_id,
                    'Partlist Final - Menunggu Rilis Manager',
                    "Engineering selesai membuat partlist untuk SPK {$spk['spk_number']}. Mohon approval/rilis General Manager / Manager Produksi.",
                    "index.php?page=ppic-spk&action=approve_mgr&id=" . (int)$spk_id,
                    'success',
                    [
                        'permission_slug' => 'ppic_spk_approve_mgr',
                        'target_roles' => ['manager'],
                        'include_admin' => false,
                        'ttl_seconds' => 86400,
                    ]
                );
            }
            echo "<script>alert('Partlist Approved! SPK Status: FINAL'); window.location='index.php?page=eng-partlist';</script>";
        } else {
            echo "<script>alert('Draft Partlist Berhasil Disimpan.'); window.location='index.php?page=eng-partlist';</script>";
        }
        
        $pdo->commit();
        exit;
    } catch (Exception $e) { 
        if ($pdo->inTransaction()) $pdo->rollBack(); 
        echo "<script>alert('Gagal menyimpan partlist. Silakan cek input.'); window.location='index.php?page=eng-partlist&action=create&spk_id=" . (int)$spk_id . "';</script>";
        exit;
    }
}

render_header("Input Partlist & Drawing");
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h4 class="fw-bold">Input Partlist & Drawing</h4>
        <p class="text-muted">SPK: <?= $esc($spk['spk_number']) ?> | Status: <span class="badge bg-info text-dark"><?= strtoupper($esc($spk['status'])) ?></span></p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=eng-partlist" class="btn btn-secondary shadow-sm">
            <i class="bi bi-arrow-left"></i> Kembali ke Daftar
        </a>
    </div>
</div>

<form method="POST">
    <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
    <input type="hidden" name="spk_id" value="<?= (int)$spk_id ?>">
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-5">
                    <label class="fw-bold mb-1">Link Drawing (Cloud Storage)</label>
                    <input type="text" name="drawing_link" class="form-control" value="<?= $esc($spk['drawing_link']) ?>" placeholder="https://drive.google.com/...">
                </div>
                <div class="col-md-7">
                    <div class="alert alert-info py-2 small mb-1">
                        <strong>Tips:</strong> Gunakan import file Excel melalui modal agar format partlist konsisten.
                    </div>
                    <div class="row g-1 mb-1">
                        <div class="col-md-6 d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#importPartlistModal">
                                <i class="bi bi-file-earmark-excel"></i> Import Excel
                            </button>
                        </div>
                        <div class="col-md-6 d-flex gap-1">
                            <button type="button" id="btnClearRows" class="btn btn-sm btn-outline-danger w-100">
                                <i class="bi bi-trash"></i> Kosongkan
                            </button>
                        </div>
                    </div>
                    <textarea id="pasteArea" class="form-control form-control-sm" rows="2" placeholder="Paste Data Excel Di Sini..."></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-sm text-center mb-0" style="font-size:11px;">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th width="40">NO</th>
                            <th width="110">NO DWG</th>
                            <th>NAMA PART</th>
                            <th width="50">QTY</th>
                            <th>MATERIAL</th>
                            <th width="50">TEBAL</th>
                            <th width="60">P</th>
                            <th width="60">L</th>
                            <th>PROSES</th>
                            <th>KET</th>
                            <th width="30">#</th>
                        </tr>
                    </thead>
                    <tbody id="plItems">
                        <?php foreach($parts as $p): ?>
                        <tr>
                            <td><input type="text" name="no[]" value="<?= $esc($p['item_no']) ?>" class="form-control form-control-sm text-center"></td>
                            <td><input type="text" name="drawing_no[]" value="<?= $esc($p['drawing_no']) ?>" class="form-control form-control-sm"></td>
                            <td><input type="text" name="part_name[]" value="<?= $esc($p['part_name']) ?>" class="form-control form-control-sm"></td>
                            <td><input type="number" name="qty[]" value="<?= $esc($p['qty']) ?>" class="form-control form-control-sm text-center" data-allow-zero></td>
                            <td><input type="text" name="material[]" value="<?= $esc($p['material']) ?>" class="form-control form-control-sm"></td>
                            <td><input type="text" name="thickness[]" value="<?= $esc($p['thickness']) ?>" class="form-control form-control-sm text-center"></td>
                            <td><input type="number" name="length[]" value="<?= $esc($p['length']) ?>" class="form-control form-control-sm text-center" data-allow-zero></td>
                            <td><input type="number" name="width[]" value="<?= $esc($p['width']) ?>" class="form-control form-control-sm text-center" data-allow-zero></td>
                            <td><input type="text" name="process[]" value="<?= $esc($p['process']) ?>" class="form-control form-control-sm"></td>
                            <td><input type="text" name="notes[]" value="<?= $esc($p['notes']) ?>" class="form-control form-control-sm"></td>
                            <td><button type="button" class="btn btn-danger btn-sm p-0 px-1" onclick="this.closest('tr').remove()">×</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <a href="index.php?page=eng-partlist" class="btn btn-outline-secondary">Batal & Kembali</a>
            <div>
                <button type="submit" class="btn btn-primary">Simpan Draft</button>
                <?php if(has_permission('eng_partlist_approve')): ?>
                    <button type="submit" name="approve_now" value="1" class="btn btn-success fw-bold">Approve (Final)</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<!-- MODAL IMPORT EXCEL -->
<div class="modal fade" id="importPartlistModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-file-earmark-excel"></i> Import Partlist & Drawing (Excel)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small mb-3">
                    <strong>Format kolom (urutan):</strong>
                    <code>no, drawing_no, part_name, qty, material, thickness, length, width, process, notes</code>
                    <br>Header boleh ada / tidak ada. File mendukung <code>.xlsx</code>, <code>.xls</code>, <code>.csv</code>.
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">File Excel</label>
                        <input type="file" id="partlistExcelFile" class="form-control" accept=".xlsx,.xls,.csv">
                    </div>
                    <div class="col-md-4 d-grid">
                        <button type="button" id="btnPartlistImport" class="btn btn-success">
                            <i class="bi bi-upload"></i> Mulai Import
                        </button>
                    </div>
                </div>
                <div class="mt-2">
                    <a href="assets/templates/partlist_drawing_template.xls" class="btn btn-sm btn-outline-primary" download>
                        <i class="bi bi-download"></i> Download Template Excel
                    </a>
                </div>
                <div id="partlistImportResult" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

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

const pasteArea = document.getElementById('pasteArea');
if (pasteArea) {
    pasteArea.addEventListener('paste', function(e) {
        e.preventDefault();
        var clipboardData = (e.clipboardData || window.clipboardData).getData('Text');
        var rows = clipboardData.split('\n');
        rows.forEach(function(row) {
            if (row.trim() !== '') {
                var cols = row.split('\t');
                addRow(cols[0]||'', cols[1]||'', cols[2]||'', cols[3]||'', cols[4]||'', cols[5]||'', cols[6]||'', cols[7]||'', cols[8]||'', cols[9]||'');
            }
        });
    });
}

document.getElementById('btnClearRows').addEventListener('click', function() {
    if (typeof window.appConfirm === 'function') {
        window.appConfirm('Kosongkan semua baris partlist di tabel?', () => {
            document.getElementById('plItems').innerHTML = '';
        });
    } else if (confirm('Kosongkan semua baris partlist di tabel?')) {
        document.getElementById('plItems').innerHTML = '';
    }
});

document.getElementById('btnPartlistImport').addEventListener('click', function() {
    const fileInput = document.getElementById('partlistExcelFile');
    const resultBox = document.getElementById('partlistImportResult');
    if (!fileInput.files || !fileInput.files[0]) {
        resultBox.innerHTML = '<div class="alert alert-danger mb-0">Pilih file Excel terlebih dahulu.</div>';
        return;
    }
    if (typeof XLSX === 'undefined') {
        resultBox.innerHTML = '<div class="alert alert-danger mb-0">Library Excel gagal dimuat. Cek koneksi internet lalu refresh halaman.</div>';
        return;
    }

    resultBox.innerHTML = '';
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = e.target.result;
            const workbook = XLSX.read(data, { type: 'array' });
            const sheetName = workbook.SheetNames[0];
            const ws = workbook.Sheets[sheetName];
            const rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });

            if (!rows || rows.length === 0) {
                resultBox.innerHTML = '<div class="alert alert-danger mb-0">File Excel kosong.</div>';
                return;
            }

            const startIndex = detectHeaderRow(rows[0]) ? 1 : 0;
            let imported = 0;

            for (let i = startIndex; i < rows.length; i++) {
                const r = rows[i] || [];
                if (isRowEmpty(r)) continue;
                addRow(
                    normalizeCell(r[0]),
                    normalizeCell(r[1]),
                    normalizeCell(r[2]),
                    normalizeCell(r[3]),
                    normalizeCell(r[4]),
                    normalizeCell(r[5]),
                    normalizeCell(r[6]),
                    normalizeCell(r[7]),
                    normalizeCell(r[8]),
                    normalizeCell(r[9])
                );
                imported++;
            }

            resultBox.innerHTML = '<div class="alert alert-success mb-0">Import selesai. <b>' + imported + '</b> baris ditambahkan.</div>';
            fileInput.value = '';
        } catch (err) {
            resultBox.innerHTML = '<div class="alert alert-danger mb-0">Gagal membaca file Excel: ' + (err && err.message ? err.message : err) + '</div>';
        }
    };
    reader.readAsArrayBuffer(fileInput.files[0]);
});

function detectHeaderRow(firstRow) {
    const text = (firstRow || []).join(' ').toLowerCase();
    return text.includes('dwg') || text.includes('part') || text.includes('material') || text.includes('proses') || text.includes('qty');
}

function isRowEmpty(row) {
    for (let i = 0; i < row.length; i++) {
        if (String(row[i] ?? '').trim() !== '') return false;
    }
    return true;
}

function normalizeCell(v) {
    if (v === null || v === undefined) return '';
    return String(v).trim();
}

function addRow(no, dwg, name, qty, mat, thk, p, l, proc, note) {
    const safeNo = escapeHtml(no);
    const safeDwg = escapeHtml(dwg);
    const safeName = escapeHtml(name);
    const safeQty = escapeHtml(qty);
    const safeMat = escapeHtml(mat);
    const safeThk = escapeHtml(thk);
    const safeP = escapeHtml(p);
    const safeL = escapeHtml(l);
    const safeProc = escapeHtml(proc);
    const safeNote = escapeHtml(note);

    let html = `<tr>
        <td><input type="text" name="no[]" value="${safeNo}" class="form-control form-control-sm text-center"></td>
        <td><input type="text" name="drawing_no[]" value="${safeDwg}" class="form-control form-control-sm"></td>
        <td><input type="text" name="part_name[]" value="${safeName}" class="form-control form-control-sm"></td>
        <td><input type="number" name="qty[]" value="${safeQty}" class="form-control form-control-sm text-center" data-allow-zero></td>
        <td><input type="text" name="material[]" value="${safeMat}" class="form-control form-control-sm"></td>
        <td><input type="text" name="thickness[]" value="${safeThk}" class="form-control form-control-sm text-center"></td>
        <td><input type="number" name="length[]" value="${safeP}" class="form-control form-control-sm text-center" data-allow-zero></td>
        <td><input type="number" name="width[]" value="${safeL}" class="form-control form-control-sm text-center" data-allow-zero></td>
        <td><input type="text" name="process[]" value="${safeProc}" class="form-control form-control-sm"></td>
        <td><input type="text" name="notes[]" value="${safeNote}" class="form-control form-control-sm"></td>
        <td><button type="button" class="btn btn-danger btn-sm p-0 px-1" onclick="this.closest('tr').remove()">×</button></td>
    </tr>`;
    document.getElementById('plItems').insertAdjacentHTML('beforeend', html);
}
</script>
<?php render_footer(); ?>
