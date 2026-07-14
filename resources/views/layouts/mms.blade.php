@inject('mms', 'App\Services\MmsContext')
@php
    $company = $company ?? $mms->company();
    $theme = $mms->activeTheme($company);
    $user = auth()->user();
    $logoUrl = $mms->logoUrl($company);
    $notifications = $user ? $mms->notifications($user) : [];
    $unreadCount = $user ? $mms->unreadNotificationCount($user) : 0;
    $isAndroidApp = str_contains(request()->userAgent(), 'MMS-Android-App');
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
            width: 1.5rem;
            height: 1.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .tone-blue { color: #0b63f6; }
        .tone-teal { color: #0f766e; }
        .tone-green { color: #2b8a3e; }
        .tone-cyan { color: #0891b2; }
        .tone-orange { color: #d97706; }
        .tone-red { color: #dc2626; }
        .tone-indigo { color: #4f46e5; }
        .tone-pink { color: #db2777; }
        .tone-yellow { color: #a16207; }
        .tone-slate { color: #334155; }
    </style>
</head>
<body class="{{ $theme['body_class'] }}">
    <div class="wrapper">
        @if(!$isAndroidApp)
            @include('partials.sidebar', ['company' => $company, 'logoUrl' => $logoUrl, 'menus' => $mms->sidebarMenus($user)])
            <div id="sidebarOverlay" aria-hidden="true"></div>
        @endif
        <div id="content" @if($isAndroidApp) style="padding: 0; margin: 0; width: 100%; min-height: 100vh; display: flex; flex-direction: column;" @endif>
            @if(!$isAndroidApp)
                @include('partials.topbar', ['user' => $user, 'notifications' => $notifications, 'unreadCount' => $unreadCount])
            @endif
            <div class="container-fluid @if($isAndroidApp) py-3 @endif">
                @yield('content')
            </div>
            @if(!$isAndroidApp)
                <footer class="mms-app-footer mt-auto">
                    <div class="mms-app-footer-main">Copyright &copy; 2026 {{ $company->company_name ?: 'MMS System' }}</div>
                    <div class="mms-app-footer-sub">Manufacturing Management System (Supported by CCT-NET)</div>
                </footer>
            @endif
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
