<?php
// Top navbar partial. Expects $role_name, $user_avatar, $notif_count, $notif_list.
?>
<nav class="navbar navbar-expand-lg mb-4 px-3 shadow-sm bg-white d-flex justify-content-between">
    <div class="d-flex align-items-center">
        <button type="button" id="sidebarCollapse" class="btn btn-light border"><i class="bi bi-list"></i></button>
        <div class="ms-3 d-flex flex-column" aria-live="polite">
            <div id="topbarClockTime" class="fw-bold fs-5 text-dark lh-1">-</div>
            <div id="topbarClockDate" class="small text-muted mt-1 lh-1">-</div>
        </div>
    </div>
    <div class="navbar-icons d-flex align-items-center">
        <div class="dropdown me-3">
            <a href="#" class="position-relative text-dark" data-bs-toggle="dropdown" id="notifBellToggle">
                <i class="bi bi-bell fs-5"></i>
                <span id="notifBellBadge" class="badge bg-danger position-absolute top-0 start-100 translate-middle rounded-pill <?= $notif_count > 0 ? '' : 'd-none' ?>" style="font-size:0.6rem;"><?= (int)$notif_count ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="width: 320px;" id="notifDropdownMenu">
                <li class="d-flex justify-content-between align-items-center p-3 bg-primary text-white fw-bold">
                    <span>Notifikasi</span>
                    <a href="modules/notification/read_all.php?back=<?= urlencode('index.php?page=' . ($_GET['page'] ?? 'dashboard')) ?>" id="notifMarkAllBtn" class="btn btn-sm btn-light text-primary">Tandai semua</a>
                </li>
                <div id="notifListWrap">
                <?php if(empty($notif_list)): ?>
                    <li class="p-4 text-center text-muted small">Tidak ada notifikasi</li>
                <?php else: foreach($notif_list as $n): $bg = $n['is_read'] ? '' : 'notif-unread'; $target_link = !empty($n['link']) ? ltrim((string)$n['link'], '/') : 'index.php'; ?>
                    <li><a href="modules/notification/handler.php?id=<?= (int)$n['id'] ?>&url=<?= urlencode($target_link) ?>" class="dropdown-item py-2 border-bottom <?= $bg ?>">
                        <div class="fw-bold small"><?= clean($n['title'] ?? 'Info') ?></div>
                        <div class="small text-wrap"><?= clean($n['message']) ?></div>
                        <small class="text-muted"><?= date('d M, H:i', strtotime($n['created_at'])) ?></small>
                    </a></li>
                <?php endforeach; endif; ?>
                </div>
            </ul>
        </div>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown">
                <div class="text-end me-2 d-none d-md-block">
                    <div class="fw-bold small text-dark"><?= clean($_SESSION['fullname']) ?></div>
                    <span class="badge bg-light text-dark border"><?= $role_name ?></span>
                </div>
                <?php if (!empty($user_avatar) && file_exists($user_avatar)): ?>
                    <img src="<?= clean($user_avatar) ?>" alt="Avatar" class="rounded-circle" style="width: 34px; height: 34px; object-fit: cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i class="bi bi-person"></i>
                    </div>
                <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" style="min-width: 220px;">
                <li class="px-3 pt-2 pb-2">
                    <div class="fw-bold"><?= clean($_SESSION['fullname']) ?></div>
                    <div class="text-muted small"><?= $role_name ?></div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a href="index.php?page=user-settings" class="dropdown-item">
                        <i class="bi bi-gear me-2"></i> User Setting
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a href="logout.php" class="btn btn-outline-danger w-100">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<script>
    (function () {
        const clockTimeEl = document.getElementById('topbarClockTime');
        const clockDateEl = document.getElementById('topbarClockDate');
        if (!clockTimeEl || !clockDateEl) return;

        const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const pad = (n) => String(n).padStart(2, '0');

        const renderClock = () => {
            const now = new Date();
            const day = dayNames[now.getDay()] || '-';
            const date = pad(now.getDate()) + ' ' + (monthNames[now.getMonth()] || '-') + ' ' + now.getFullYear();
            const hh = pad(now.getHours());
            const mm = pad(now.getMinutes());
            const ss = pad(now.getSeconds());
            clockTimeEl.textContent = day + ' : ' + hh + ' : ' + mm + ' : ' + ss;
            clockDateEl.textContent = date;
        };

        renderClock();
        setInterval(renderClock, 1000);
    })();
</script>
