@inject('mms', 'App\Services\MmsContext')
@php
    $company = $company ?? $mms->company();
    $theme = $mms->activeTheme($company);
    $user = auth()->user();
    $logoUrl = $mms->logoUrl($company);
    $notifications = $user ? $mms->notifications($user) : [];
    $unreadCount = $user ? $mms->unreadNotificationCount($user) : 0;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'MMS System')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
    @if($theme['css_path'])
        <link href="{{ asset($theme['css_path']) }}" rel="stylesheet">
    @endif
    <style>
        .sidebar-logo-container { padding: 20px 15px; background: #fff; border-bottom: 1px solid #eee; min-height: 120px; text-align: center; }
        .sidebar-submenu .nav-link { font-size: 0.9em; padding-left: 2.8rem !important; display: flex; align-items: center; gap: 10px; }
        .notif-unread { background-color: #f0f7ff; }
        .sidebar-menu-icon {
            width: 1.45rem;
            height: 1.45rem;
            border-radius: 0.45rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.86rem;
            flex-shrink: 0;
        }
        .tone-blue { color: #0b63f6; background: rgba(11, 99, 246, 0.14); }
        .tone-teal { color: #0f766e; background: rgba(15, 118, 110, 0.14); }
        .tone-green { color: #2b8a3e; background: rgba(43, 138, 62, 0.14); }
        .tone-cyan { color: #0891b2; background: rgba(8, 145, 178, 0.14); }
        .tone-orange { color: #d97706; background: rgba(217, 119, 6, 0.14); }
        .tone-red { color: #dc2626; background: rgba(220, 38, 38, 0.14); }
        .tone-indigo { color: #4f46e5; background: rgba(79, 70, 229, 0.14); }
        .tone-pink { color: #db2777; background: rgba(219, 39, 119, 0.14); }
        .tone-yellow { color: #a16207; background: rgba(161, 98, 7, 0.16); }
        .tone-slate { color: #334155; background: rgba(51, 65, 85, 0.14); }
    </style>
</head>
<body class="{{ $theme['body_class'] }}">
    <div class="wrapper">
        @include('partials.sidebar', ['company' => $company, 'logoUrl' => $logoUrl, 'menus' => $mms->sidebarMenus($user)])
        <div id="sidebarOverlay" aria-hidden="true"></div>
        <div id="content">
            @include('partials.topbar', ['user' => $user, 'notifications' => $notifications, 'unreadCount' => $unreadCount])
            <div class="container-fluid">
                @yield('content')
            </div>
            <footer class="mms-app-footer mt-auto">
                <div class="mms-app-footer-main">Copyright &copy; 2026 {{ $company->company_name ?: 'MMS System' }}</div>
                <div class="mms-app-footer-sub">Manufacturing Management System (Supported by CCT-NET)</div>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebarCollapse');
            const overlay = document.getElementById('sidebarOverlay');
            if (!sidebar || !toggle) return;
            const isMobile = () => window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
            toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
            overlay?.addEventListener('click', () => {
                if (isMobile()) sidebar.classList.remove('active');
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
