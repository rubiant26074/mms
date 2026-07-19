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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
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

        /* Select2 Bootstrap 5 Theme overrides for seamless table cell integration */
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px !important;
        }
        .select2-container--bootstrap-5.select2-container--focus .select2-selection,
        .select2-container--bootstrap-5.select2-container--open .select2-selection {
            border-color: #86b7fe !important;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
        }
        .select2-dropdown {
            z-index: 1065 !important;
        }
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

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        (function () {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebarCollapse');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar && toggle) {
                const isMobile = () => window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
                toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
                overlay?.addEventListener('click', () => {
                    if (isMobile()) sidebar.classList.remove('active');
                });
            }

            window.initSearchableSelects = function(scope) {
                if (typeof $ === 'undefined' || typeof $.fn.select2 === 'undefined') return;

                var $context = scope ? $(scope) : $(document.body);
                var $selects = $context.is('select') ? $context : $context.find('select');

                $selects.each(function() {
                    var $select = $(this);
                    if ($select.hasClass('no-search') || $select.data('noSearch') !== undefined || $select.hasClass('select2-hidden-accessible')) {
                        return;
                    }

                    if ($select.find('option').length < 2) {
                        return;
                    }

                    var parentModal = $select.closest('.modal');

                    $select.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        dropdownParent: parentModal.length ? parentModal : $(document.body),
                        language: {
                            noResults: function() {
                                return "Tidak ditemukan hasil";
                            }
                        }
                    });
                });
            };

            $(document).ready(function() {
                window.initSearchableSelects();

                // Observe DOM mutations to auto-initialize searchable selects for dynamically added rows/modals
                const observer = new MutationObserver(function(mutations) {
                    let hasNewSelects = false;
                    for (let m of mutations) {
                        for (let node of m.addedNodes) {
                            if (node.nodeType === 1 && (node.tagName === 'SELECT' || node.querySelector('select'))) {
                                hasNewSelects = true;
                                break;
                            }
                        }
                        if (hasNewSelects) break;
                    }
                    if (hasNewSelects) {
                        setTimeout(window.initSearchableSelects, 50);
                    }
                });
                observer.observe(document.body, { childList: true, subtree: true });
            });

            // Global Scroll Position Restoration across Refreshes & Form Submissions
            (function () {
                const storageKey = 'mms_scroll_pos_' + window.location.pathname;

                const restoreScroll = function() {
                    const savedPos = sessionStorage.getItem(storageKey);
                    if (savedPos !== null && !isNaN(savedPos)) {
                        const posY = parseInt(savedPos, 10);
                        if (posY > 0) {
                            window.scrollTo({ top: posY, left: 0, behavior: 'instant' });
                            setTimeout(function() {
                                window.scrollTo({ top: posY, left: 0, behavior: 'instant' });
                            }, 100);
                        }
                    }
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', restoreScroll);
                } else {
                    restoreScroll();
                }

                let scrollTimer;
                window.addEventListener('scroll', function () {
                    clearTimeout(scrollTimer);
                    scrollTimer = setTimeout(function () {
                        sessionStorage.setItem(storageKey, window.scrollY);
                    }, 100);
                }, { passive: true });

                window.addEventListener('beforeunload', function () {
                    sessionStorage.setItem(storageKey, window.scrollY);
                });

                document.addEventListener('click', function (e) {
                    const link = e.target.closest('#sidebar a, .sidebar a');
                    if (link && link.href) {
                        try {
                            const targetPath = new URL(link.href).pathname;
                            if (targetPath !== window.location.pathname) {
                                sessionStorage.removeItem('mms_scroll_pos_' + targetPath);
                            }
                        } catch (err) {}
                    }
                });
            })();
        })();
    </script>
    @stack('scripts')
</body>
</html>
