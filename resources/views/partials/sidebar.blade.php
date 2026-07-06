@inject('mms', 'App\Services\MmsContext')
@php
    $palette = ['tone-blue', 'tone-teal', 'tone-green', 'tone-cyan', 'tone-orange', 'tone-red', 'tone-indigo', 'tone-pink', 'tone-yellow', 'tone-slate'];
    $tone = fn ($seed) => $palette[abs(crc32((string) $seed)) % count($palette)];
    $companyName = trim((string) ($company->company_name ?: 'MMS System'));
    $words = preg_split('/\s+/', $companyName, -1, PREG_SPLIT_NO_EMPTY);
    $companyLine1 = $companyName;
    $companyLine2 = '';
    if (is_array($words) && count($words) > 3) {
        $companyLine1 = implode(' ', array_slice($words, 0, 3));
        $companyLine2 = implode(' ', array_slice($words, 3));
    }
@endphp
<nav id="sidebar" class="d-flex flex-column shadow">
    <div class="sidebar-logo-container">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="Logo" class="img-fluid" style="max-width: 207px; max-height: 92px; object-fit: contain; display: block; margin: 0 auto; border-radius: 10px;">
        @else
            <i class="bi bi-gear-wide-connected" style="font-size: 3rem; color: #2c3e50;"></i>
        @endif
        <h5 class="mt-3 mb-0 text-center text-uppercase" style="font-size: 96%; line-height: 1.25;">
            {{ $companyLine1 }}
            @if($companyLine2 !== '')<br>{{ $companyLine2 }}@endif
        </h5>
        <small class="d-block mt-2 text-center" style="font-size: 72%; line-height: 1.2;">MMS Soft v1.0.0</small>
    </div>
    <ul class="list-unstyled components mb-auto">
        @foreach($menus as $menu)
            @if(isset($menu['submenu']))
                @php
                    $activeParent = collect($menu['submenu'])->contains(fn ($sub) => request()->fullUrlIs($sub['url']) || request()->path() === ltrim(parse_url($sub['url'], PHP_URL_PATH) ?: '', '/'));
                @endphp
                <li>
                    <a href="#{{ $menu['id'] }}" data-bs-toggle="collapse" aria-expanded="{{ $activeParent ? 'true' : 'false' }}" class="dropdown-toggle d-flex align-items-center text-dark">
                        <span class="sidebar-menu-icon {{ $tone($menu['id'] ?? $menu['label']) }} me-2"><i class="bi {{ $menu['icon'] ?? 'bi-circle-fill' }}"></i></span>
                        <span>{{ $menu['label'] }}</span>
                    </a>
                    <ul class="collapse list-unstyled sidebar-submenu {{ $activeParent ? 'show' : '' }}" id="{{ $menu['id'] }}">
                        @foreach($menu['submenu'] as $sub)
                            <li>
                                <a href="{{ $sub['url'] }}" class="nav-link">
                                    <span class="sidebar-menu-icon {{ $tone(($menu['id'] ?? $menu['label']).'-'.($sub['url'] ?? $sub['label'])) }}"><i class="bi {{ $sub['icon'] ?? 'bi-dot' }}"></i></span>
                                    <span>{{ $sub['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @else
                <li>
                    <a href="{{ $menu['url'] }}" class="{{ request()->routeIs('dashboard') && str_contains($menu['url'], 'dashboard') ? 'active fw-bold text-primary' : '' }} text-dark d-flex align-items-center">
                        <span class="sidebar-menu-icon {{ $tone($menu['url'] ?? $menu['label']) }} me-2"><i class="bi {{ $menu['icon'] ?? 'bi-circle-fill' }}"></i></span>
                        <span>{{ $menu['label'] }}</span>
                    </a>
                </li>
            @endif
        @endforeach
    </ul>
</nav>
