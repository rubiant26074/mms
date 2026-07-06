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
                <span id="notifBellBadge" class="badge bg-danger position-absolute top-0 start-100 translate-middle rounded-pill {{ $unreadCount > 0 ? '' : 'd-none' }}" style="font-size:0.6rem;">{{ $unreadCount }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="width: 320px;" id="notifDropdownMenu">
                <li class="d-flex justify-content-between align-items-center p-3 bg-primary text-white fw-bold">
                    <span>Notifikasi</span>
                    <a href="modules/notification/read_all.php?back={{ urlencode('dashboard') }}" class="btn btn-sm btn-light text-primary">Tandai semua</a>
                </li>
                @forelse($notifications as $notification)
                    @php $target = ltrim((string)($notification->link ?: 'dashboard'), '/'); @endphp
                    <li>
                        <a href="modules/notification/handler.php?id={{ $notification->id }}&url={{ urlencode($target) }}" class="dropdown-item py-2 border-bottom {{ $notification->is_read ? '' : 'notif-unread' }}">
                            <div class="fw-bold small">{{ $notification->title ?: 'Info' }}</div>
                            <div class="small text-wrap">{{ $notification->message }}</div>
                            <small class="text-muted">{{ optional($notification->created_at)->format('d M, H:i') }}</small>
                        </a>
                    </li>
                @empty
                    <li class="p-4 text-center text-muted small">Tidak ada notifikasi</li>
                @endforelse
            </ul>
        </div>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown">
                <div class="text-end me-2 d-none d-md-block">
                    <div class="fw-bold small text-dark">{{ $user->fullname }}</div>
                    <span class="badge bg-light text-dark border">{{ $user->role?->role_name }}</span>
                </div>
                @if($user->avatar_path)
                    <img src="{{ asset($user->avatar_path) }}" alt="Avatar" class="rounded-circle" style="width: 34px; height: 34px; object-fit: cover;">
                @else
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i class="bi bi-person"></i>
                    </div>
                @endif
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" style="min-width: 220px;">
                <li class="px-3 pt-2 pb-2">
                    <div class="fw-bold">{{ $user->fullname }}</div>
                    <div class="text-muted small">{{ $user->role?->role_name }}</div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a href="index.php?page=user-settings" class="dropdown-item"><i class="bi bi-gear me-2"></i> User Setting</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">Logout</button>
                    </form>
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
            clockTimeEl.textContent = dayNames[now.getDay()] + ' : ' + pad(now.getHours()) + ' : ' + pad(now.getMinutes()) + ' : ' + pad(now.getSeconds());
            clockDateEl.textContent = pad(now.getDate()) + ' ' + monthNames[now.getMonth()] + ' ' + now.getFullYear();
        };
        renderClock();
        setInterval(renderClock, 1000);
    })();
</script>
