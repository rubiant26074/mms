<?php
// Global modals and JS footer partial.
?>
<!-- Global Modal (Alert/Confirm) -->
<div class="modal fade" id="appAlertModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="appAlertTitle">Informasi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="appAlertMessage">...</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="appConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="appConfirmTitle">Konfirmasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="appConfirmMessage">...</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="appConfirmOk">Lanjutkan</button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const alertModalEl = document.getElementById('appAlertModal');
        const confirmModalEl = document.getElementById('appConfirmModal');
        if (!alertModalEl || !confirmModalEl || typeof bootstrap === 'undefined') return;

        const alertModal = new bootstrap.Modal(alertModalEl);
        const confirmModal = new bootstrap.Modal(confirmModalEl);
        const alertTitle = document.getElementById('appAlertTitle');
        const alertMsg = document.getElementById('appAlertMessage');
        const confirmTitle = document.getElementById('appConfirmTitle');
        const confirmMsg = document.getElementById('appConfirmMessage');
        const confirmOk = document.getElementById('appConfirmOk');

        let confirmAction = null;
        const showConfirm = (message, onYes) => {
            confirmTitle.textContent = 'Konfirmasi';
            confirmMsg.textContent = message || 'Apakah Anda yakin?';
            confirmAction = typeof onYes === 'function' ? onYes : null;
            confirmModal.show();
        };

        confirmOk.addEventListener('click', () => {
            confirmModal.hide();
            if (confirmAction) {
                const fn = confirmAction;
                confirmAction = null;
                fn();
            }
        });

        window.appConfirm = showConfirm;
        window.appAlert = (message, title) => {
            alertTitle.textContent = title || 'Informasi';
            alertMsg.textContent = message || '';
            alertModal.show();
        };

        // Override browser dialogs
        window.alert = (message) => window.appAlert(message || '');
        window.confirm = (message) => {
            showConfirm(message || '', null);
            return false; // prevent default flow
        };

        // Intercept inline onclick confirm
        const extractMessage = (code) => {
            const m = code && code.match(/confirm\((['"])(.*?)\1\)/);
            return m && m[2] ? m[2] : 'Apakah Anda yakin?';
        };

        document.addEventListener('click', function (e) {
            const el = e.target.closest('a,button,input[type=submit]');
            if (!el) return;
            const onclick = el.getAttribute('onclick');
            if (!onclick || onclick.indexOf('confirm(') === -1) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            const msg = extractMessage(onclick);
            showConfirm(msg, () => {
                if (el.tagName === 'A' && el.href) {
                    window.location = el.href;
                    return;
                }
                if ((el.tagName === 'BUTTON' || el.tagName === 'INPUT') && el.form) {
                    el.form.submit();
                }
            });
        }, true);

        document.addEventListener('submit', function (e) {
            const form = e.target;
            const onsubmit = form.getAttribute('onsubmit');
            if (!onsubmit || onsubmit.indexOf('confirm(') === -1) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            const msg = extractMessage(onsubmit);
            showConfirm(msg, () => form.submit());
        }, true);
    })();
</script>
<script>
    (function () {
        const badge = document.getElementById('notifBellBadge');
        const listWrap = document.getElementById('notifListWrap');
        const markAllBtn = document.getElementById('notifMarkAllBtn');
        if (!badge || !listWrap || !markAllBtn) return;

        const esc = (val) => {
            const div = document.createElement('div');
            div.textContent = String(val ?? '');
            return div.innerHTML;
        };

        const fmtDate = (input) => {
            if (!input) return '';
            const d = new Date(String(input).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return esc(input);
            const dd = String(d.getDate()).padStart(2, '0');
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const mm = months[d.getMonth()] || '';
            const hh = String(d.getHours()).padStart(2, '0');
            const mi = String(d.getMinutes()).padStart(2, '0');
            return `${dd} ${mm}, ${hh}:${mi}`;
        };

        const setBadge = (count) => {
            const n = Number(count) || 0;
            badge.textContent = String(n);
            badge.classList.toggle('d-none', n <= 0);
        };

        const buildItemHtml = (n) => {
            const rowClass = Number(n.is_read) === 1 ? '' : 'notif-unread';
            const rawLink = String(n.link || 'index.php').replace(/^\/+/, '');
            const href = `modules/notification/handler.php?id=${encodeURIComponent(n.id)}&url=${encodeURIComponent(rawLink)}`;
            return `<li><a href="${href}" class="dropdown-item py-2 border-bottom ${rowClass}">
                <div class="fw-bold small">${esc(n.title || 'Info')}</div>
                <div class="small text-wrap">${esc(n.message || '')}</div>
                <small class="text-muted">${fmtDate(n.created_at)}</small>
            </a></li>`;
        };

        const renderList = (items) => {
            if (!Array.isArray(items) || items.length === 0) {
                listWrap.innerHTML = '<li class="p-4 text-center text-muted small">Tidak ada notifikasi</li>';
                return;
            }
            listWrap.innerHTML = items.map(buildItemHtml).join('');
        };

        const refreshNotif = async () => {
            try {
                const res = await fetch('modules/notification/poll.php', { cache: 'no-store' });
                const data = await res.json();
                if (!data || data.status !== 'success') return;
                setBadge(data.unread_count ?? data.unread ?? 0);
                renderList(data.items || []);
            } catch (e) {
                // silent
            }
        };

        markAllBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                await fetch(markAllBtn.getAttribute('href'), { cache: 'no-store' });
                await refreshNotif();
            } catch (err) {
                window.location.href = markAllBtn.getAttribute('href');
            }
        });

        refreshNotif();
        setInterval(refreshNotif, 15000);
    })();
</script>
<script>
    (function () {
        const allowZeroAttr = 'data-allow-zero';
        const cleanValue = (val) => String(val ?? '').trim();
        const toNumber = (val) => {
            const raw = cleanValue(val).replace(',', '.');
            if (raw === '') return NaN;
            return Number(raw);
        };

        const initNumberInputs = () => {
            document.querySelectorAll('input[type="number"]').forEach((input) => {
                if (!input.name) return;
                if (input.hasAttribute(allowZeroAttr)) return;
                if (cleanValue(input.value) === '0') input.value = '';
                input.addEventListener('focus', () => {
                    if (cleanValue(input.value) === '0') input.value = '';
                });
            });
        };

        const validateForms = (e) => {
            const form = e.target;
            if (!form || form.nodeName !== 'FORM') return;
            const inputs = form.querySelectorAll('input[type="number"]');
            for (const input of inputs) {
                if (!input.name) continue;
                if (input.hasAttribute(allowZeroAttr)) continue;
                if (input.disabled || input.readOnly) continue;
                if (input.offsetParent === null) continue;
                const val = cleanValue(input.value);
                const num = toNumber(val);
                if (val === '' || num === 0) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    window.appAlert('Input angka tidak boleh 0 atau kosong. Silakan isi nilai yang benar.', 'Validasi');
                    input.focus();
                    return;
                }
            }
        };

        document.addEventListener('DOMContentLoaded', initNumberInputs);
        document.addEventListener('submit', validateForms, true);
    })();
</script>
