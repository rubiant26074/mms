<?php
// modules/admin/backup/index.php
render_header("Backup Database");

if (!has_permission('admin_reset_db')) { // Gunakan permission admin level tinggi
    echo "<div class='alert alert-danger'>Akses Ditolak.</div>";
    render_footer();
    exit;
}

$csrf = function_exists('mms_csrf_token') ? mms_csrf_token() : '';
$flash_ok = isset($_GET['ok']) ? trim((string)$_GET['ok']) : '';
$flash_err = isset($_GET['err']) ? trim((string)$_GET['err']) : '';
$restore_token_seed = bin2hex(random_bytes(8));
?>

<div class="row justify-content-center">
    <div class="col-md-10">
        <?php if ($flash_ok !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($flash_ok, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($flash_err !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($flash_err, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row justify-content-center g-4">
    <div class="col-lg-5">
        <div class="card shadow text-center border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-database-down"></i> Backup Data Sistem</h5>
            </div>
            <div class="card-body py-5">
                <i class="bi bi-cloud-download display-1 text-primary mb-3"></i>
                <h4 class="card-title">Download Database (.sql)</h4>
                <p class="card-text text-muted">
                    Fitur ini akan mendownload seluruh struktur dan data database aplikasi MMS Anda.<br>
                    Simpan file ini di tempat aman sebagai cadangan (backup).
                </p>
                <hr>
                <form action="modules/admin/backup/process.php" method="POST">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" name="backup" class="btn btn-primary btn-lg px-5 fw-bold shadow">
                        <i class="bi bi-download me-2"></i> DOWNLOAD BACKUP SEKARANG
                    </button>
                </form>
            </div>
            <div class="card-footer text-muted small">
                Format file: <strong>.sql</strong> (Kompatibel dengan phpMyAdmin / MySQL Workbench)
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-database-up"></i> Restore Database</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning small mb-3">
                    <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Peringatan</div>
                    Restore akan <strong>menimpa data database saat ini</strong>. Lakukan backup terlebih dahulu sebelum restore.
                </div>

                <form id="restoreDbForm" action="modules/admin/backup/process.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="restore_token" id="restoreTokenInput" value="<?= htmlspecialchars($restore_token_seed, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">File Backup (.sql / .sql.gz)</label>
                        <input type="file" name="restore_file" class="form-control" accept=".sql,.gz,.sql.gz" required>
                        <div class="form-text">Gunakan file backup dari MMS / phpMyAdmin. Format umum: <code>.sql</code> atau <code>.sql.gz</code>.</div>
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="wipeBeforeRestore" name="wipe_before_restore" checked>
                        <label class="form-check-label" for="wipeBeforeRestore">
                            Kosongkan database terlebih dahulu sebelum restore <span class="text-success fw-bold">(Disarankan)</span>
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="confirmRestoreCheck" name="confirm_restore" required>
                        <label class="form-check-label" for="confirmRestoreCheck">
                            Saya memahami proses restore akan mengubah data aktif.
                        </label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Ketik konfirmasi</label>
                        <input type="text" name="confirm_text" id="confirmRestoreText" class="form-control" placeholder="Ketik: RESTORE" required>
                        <div class="form-text">Untuk keamanan, ketik <code>RESTORE</code> (huruf besar).</div>
                    </div>

                    <button type="submit" name="restore" class="btn btn-danger w-100 fw-bold">
                        <i class="bi bi-upload me-2"></i> RESTORE DATABASE
                    </button>
                </form>

                <div id="restoreProgressWrap" class="mt-3 d-none">
                    <div class="border rounded p-3 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="fw-bold text-dark"><i class="bi bi-arrow-repeat me-1"></i> Proses Restore Database</div>
                            <div class="fw-bold text-primary" id="restoreProgressPct">0%</div>
                        </div>
                        <div class="progress" role="progressbar" aria-label="Restore progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                            <div id="restoreProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%;">0%</div>
                        </div>
                        <div id="restoreProgressText" class="small text-muted mt-2">Menunggu mulai restore...</div>
                        <div id="restoreProgressDetail" class="small text-secondary mt-1"></div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-muted small">
                Restore dijalankan ke database aktif aplikasi saat ini.
            </div>
        </div>
    </div>
</div>

<script>
function confirmRestoreDb() {
    const txt = document.getElementById('confirmRestoreText');
    if (!txt) return true;
    if (String(txt.value || '').trim() !== 'RESTORE') {
        alert('Konfirmasi restore tidak valid. Ketik RESTORE (huruf besar).');
        txt.focus();
        return false;
    }
    return confirm('Lanjutkan restore database sekarang? Data aktif saat ini akan berubah.');
}

(function () {
    const form = document.getElementById('restoreDbForm');
    const tokenInput = document.getElementById('restoreTokenInput');
    const wrap = document.getElementById('restoreProgressWrap');
    const bar = document.getElementById('restoreProgressBar');
    const pct = document.getElementById('restoreProgressPct');
    const txt = document.getElementById('restoreProgressText');
    const detail = document.getElementById('restoreProgressDetail');
    if (!form || !tokenInput || !wrap || !bar || !pct || !txt || !detail || typeof XMLHttpRequest === 'undefined') return;

    let pollTimer = null;
    let uploadPct = 0;
    let lastServerPct = 0;
    let busy = false;

    const genToken = () => 'rst' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
    const formatDuration = (sec) => {
        sec = Math.max(0, Math.round(Number(sec || 0)));
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        if (h > 0) return `${h}j ${String(m).padStart(2, '0')}m ${String(s).padStart(2, '0')}dtk`;
        if (m > 0) return `${m}m ${String(s).padStart(2, '0')}dtk`;
        return `${s}dtk`;
    };
    const setProgress = (n, message, detailText) => {
        const v = Math.max(0, Math.min(100, Math.round(Number(n || 0))));
        bar.style.width = v + '%';
        bar.textContent = v + '%';
        bar.parentElement?.setAttribute('aria-valuenow', String(v));
        pct.textContent = v + '%';
        if (message) txt.textContent = message;
        if (typeof detailText === 'string') detail.textContent = detailText;
    };
    const setBarState = (mode) => {
        bar.classList.remove('bg-success', 'bg-danger');
        if (mode === 'done') {
            bar.classList.remove('progress-bar-animated');
            bar.classList.add('bg-success');
        } else if (mode === 'error') {
            bar.classList.remove('progress-bar-animated');
            bar.classList.add('bg-danger');
        } else {
            bar.classList.add('progress-bar-animated');
        }
    };
    const setFormBusy = (state) => {
        busy = !!state;
        form.querySelectorAll('input,button').forEach((el) => {
            if (el === tokenInput) return;
            if (state) el.setAttribute('disabled', 'disabled');
            else el.removeAttribute('disabled');
        });
    };

    const pollProgress = (token) => {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(() => {
            fetch(`modules/admin/backup/process.php?action=progress&token=${encodeURIComponent(token)}&format=json`, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (!data || data.ok !== true || !data.progress) return;
                const p = data.progress;
                const serverPct = Math.max(0, Math.min(100, Number(p.percent || 0)));
                lastServerPct = Math.max(lastServerPct, serverPct);
                const shown = Math.max(lastServerPct, Math.min(20, uploadPct * 0.2));
                const detailParts = [];
                if (typeof p.total_statements !== 'undefined') {
                    const processed = Number(p.processed || 0);
                    detailParts.push(`Statement: ${processed}/${Number(p.total_statements || 0)} | Executed: ${Number(p.executed || 0)} | Skipped: ${Number(p.skipped || 0)}`);
                }
                const startedTs = Number(p.started_at_ts || 0);
                const updatedTs = Number(p.updated_at_ts || 0);
                if (startedTs > 0 && updatedTs >= startedTs) {
                    const elapsed = updatedTs - startedTs;
                    let etaText = 'ETA: menghitung...';
                    if (serverPct > 0 && serverPct < 100) {
                        const eta = elapsed * ((100 - serverPct) / serverPct);
                        etaText = `ETA: ${formatDuration(eta)}`;
                    } else if (serverPct >= 100) {
                        etaText = 'ETA: selesai';
                    }
                    detailParts.push(`Durasi: ${formatDuration(elapsed)} | ${etaText}`);
                }
                if (p.error && p.error_log) {
                    detailParts.push(`Log error: ${p.error_log}`);
                }
                setProgress(shown, p.message || 'Restore berjalan...', detailParts.join(' | '));
                if (p.done) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                    if (p.error) {
                        setBarState('error');
                        setProgress(100, p.message || 'Restore gagal.', detailParts.join(' | '));
                        setFormBusy(false);
                    } else {
                        setBarState('done');
                        setProgress(100, p.message || 'Restore selesai.', detailParts.join(' | '));
                    }
                }
            })
            .catch(() => { /* silent, retry on next poll */ });
        }, 500);
    };

    form.addEventListener('submit', function (e) {
        if (busy) {
            e.preventDefault();
            return;
        }
        if (!confirmRestoreDb()) {
            e.preventDefault();
            return;
        }
        e.preventDefault();

        const token = genToken();
        tokenInput.value = token;
        uploadPct = 0;
        lastServerPct = 0;
        wrap.classList.remove('d-none');
        setBarState('run');
        setProgress(0, 'Menyiapkan upload file restore...', '');
        setFormBusy(true);
        pollProgress(token);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.onprogress = function (evt) {
            if (!evt.lengthComputable) return;
            uploadPct = (evt.loaded / evt.total) * 100;
            const p = Math.max(lastServerPct, Math.min(20, uploadPct * 0.2));
            setProgress(p, 'Upload file restore ke server...', `Upload: ${Math.round(uploadPct)}%`);
        };

        xhr.onerror = function () {
            if (pollTimer) clearInterval(pollTimer);
            setBarState('error');
            setProgress(100, 'Gagal menghubungi server saat restore.', '');
            setFormBusy(false);
        };

        xhr.onload = function () {
            let res = null;
            try { res = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
            if (!res || res.ok !== true) {
                if (pollTimer) clearInterval(pollTimer);
                const msg = (res && res.message) ? res.message : ('Restore gagal (HTTP ' + xhr.status + ').');
                setBarState('error');
                const errDetail = (res && res.error_log) ? ('Log error: ' + res.error_log) : '';
                setProgress(Math.max(lastServerPct, 100), msg, errDetail);
                setFormBusy(false);
                return;
            }
            setBarState('done');
            setProgress(100, res.message || 'Restore selesai.', '');
            setTimeout(() => {
                window.location.href = 'index.php?page=admin-backup&ok=' + encodeURIComponent(res.message || 'Restore berhasil.');
            }, 900);
        };

        xhr.send(new FormData(form));
    });
})();
</script>

<?php render_footer(); ?>
