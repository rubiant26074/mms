@extends('layouts.mms')

@section('title', 'Dashboard')

@section('content')
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card dashboard-hero border-0 shadow-sm bg-white border-start border-5 border-primary">
            <div class="card-body p-4 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold mb-1 text-primary">Halo, {{ $user->fullname }}!</h2>
                    <p class="mb-0 text-muted">
                        Login sebagai: <strong>{{ $user->role?->role_name }}</strong>
                        <span class="badge bg-secondary ms-2 small">Slug: {{ $user->role?->role_slug }}</span>
                    </p>
                </div>
                <div class="text-end d-none d-md-block">
                    <h5 class="mb-0 text-dark">{{ now()->translatedFormat('d F Y') }}</h5>
                    <small class="text-success"><i class="bi bi-circle-fill" style="font-size: 8px;"></i> System Online</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card kpi-card h-100 bg-dark text-white shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="fw-bold mb-0">{{ number_format($stats['users']) }}</h3>
                    <small class="text-white-50 text-uppercase">Total Users</small>
                </div>
                <i class="bi bi-people-fill fs-1 text-secondary"></i>
            </div>
            <div class="card-footer bg-dark border-top border-secondary p-2 text-center">
                <a href="{{ route('admin.users.index') }}" class="text-white text-decoration-none small">Manage Users &rarr;</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card h-100 bg-secondary text-white shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="fw-bold mb-0">{{ number_format($stats['roles']) }}</h3>
                    <small class="text-white-50 text-uppercase">Roles / Jabatan</small>
                </div>
                <i class="bi bi-shield-lock-fill fs-1 text-dark"></i>
            </div>
            <div class="card-footer bg-secondary border-top border-dark p-2 text-center">
                <a href="{{ route('admin.roles.index') }}" class="text-white text-decoration-none small">Access Control &rarr;</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card h-100 border-start border-4 border-info shadow-sm">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Sales Order</div>
                <h3 class="fw-bold text-dark">{{ number_format($stats['sales_orders']) }}</h3>
                <small class="text-info"><i class="bi bi-receipt"></i> Dokumen aktif/history</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card h-100 border-start border-4 border-success shadow-sm">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">SPK Aktif</div>
                <h3 class="fw-bold text-dark">{{ number_format($stats['spk_active']) }}</h3>
                <small class="text-success"><i class="bi bi-clipboard-check"></i> Produksi berjalan</small>
            </div>
        </div>
    </div>
</div>

@if($user->hasPermission('user_view') || $user->hasPermission('role_manage') || $user->hasPermission('admin_reset_db'))
    <h4 class="fw-bold text-dark mb-3 mt-4"><i class="bi bi-sliders me-2 text-primary"></i> Panel Kontrol Administrator</h4>
    <div class="row g-4 mb-4">
        <!-- 1. Pengelolaan Pengguna & Hak Akses -->
        <div class="col-md-6 col-xl-3">
            <div class="card h-100 shadow-sm border-0 border-top border-4 border-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="background-color: rgba(11, 99, 246, 0.12); color: #0b63f6; width: 40px; height: 40px;">
                            <i class="bi bi-people-fill fs-5"></i>
                        </div>
                        <h5 class="card-title mb-0 fw-bold" style="font-size: 0.95rem;">Pengguna & Akses</h5>
                    </div>
                    <p class="card-text text-muted small mb-4">Kelola akun pengguna, peran jabatan, dan hak perizinan sistem.</p>
                    <div class="list-group list-group-flush small">
                        <a href="{{ route('admin.users.index') }}" class="list-group-item list-group-item-action border-0 px-0 d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-chevron-right me-1 text-primary"></i> Kelola Users</span>
                            <span class="badge bg-light text-dark border">{{ $stats['users'] }}</span>
                        </a>
                        <a href="{{ route('admin.roles.index') }}" class="list-group-item list-group-item-action border-0 px-0 d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-chevron-right me-1 text-primary"></i> Peran & Jabatan (Roles)</span>
                            <span class="badge bg-light text-dark border">{{ $stats['roles'] }}</span>
                        </a>
                        <a href="{{ route('admin.roles.permissions') }}" class="list-group-item list-group-item-action border-0 px-0">
                            <i class="bi bi-chevron-right me-1 text-primary"></i> Hak Akses (Permissions)
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Profil & Data Master -->
        <div class="col-md-6 col-xl-3">
            <div class="card h-100 shadow-sm border-0 border-top border-4 border-success">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="background-color: rgba(43, 138, 62, 0.12); color: #2b8a3e; width: 40px; height: 40px;">
                            <i class="bi bi-building fs-5"></i>
                        </div>
                        <h5 class="card-title mb-0 fw-bold" style="font-size: 0.95rem;">Profil & Data Master</h5>
                    </div>
                    <p class="card-text text-muted small mb-4">Konfigurasi profil instansi, logo, tema UI, serta daftar mesin pabrik.</p>
                    <div class="list-group list-group-flush small">
                        <a href="{{ route('admin.company.edit') }}" class="list-group-item list-group-item-action border-0 px-0">
                            <i class="bi bi-chevron-right me-1 text-success"></i> Profil Perusahaan
                        </a>
                        <a href="{{ route('admin.machines.index') }}" class="list-group-item list-group-item-action border-0 px-0">
                            <i class="bi bi-chevron-right me-1 text-success"></i> Kelola Mesin Produksi
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Kustomisasi & Setup -->
        <div class="col-md-6 col-xl-3">
            <div class="card h-100 shadow-sm border-0 border-top border-4 border-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="background-color: rgba(217, 119, 6, 0.12); color: #d97706; width: 40px; height: 40px;">
                            <i class="bi bi-gear-wide-connected fs-5"></i>
                        </div>
                        <h5 class="card-title mb-0 fw-bold" style="font-size: 0.95rem;">Kustomisasi & Setup</h5>
                    </div>
                    <p class="card-text text-muted small mb-4">Atur kustomisasi tata letak menu sidebar dan inisialisasi setup awal.</p>
                    <div class="list-group list-group-flush small">
                        <a href="{{ route('admin.menu.index') }}" class="list-group-item list-group-item-action border-0 px-0">
                            <i class="bi bi-chevron-right me-1 text-warning"></i> Kustomisasi Menu
                        </a>
                        <a href="{{ route('admin.setup.index') }}" class="list-group-item list-group-item-action border-0 px-0">
                            <i class="bi bi-chevron-right me-1 text-warning"></i> Setup Wizard Awal
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Pemeliharaan & Utilitas -->
        <div class="col-md-6 col-xl-3">
            <div class="card h-100 shadow-sm border-0 border-top border-4 border-danger">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="background-color: rgba(220, 38, 38, 0.12); color: #dc2626; width: 40px; height: 40px;">
                            <i class="bi bi-shield-fill-check fs-5"></i>
                        </div>
                        <h5 class="card-title mb-0 fw-bold" style="font-size: 0.95rem;">Pemeliharaan Sistem</h5>
                    </div>
                    <p class="card-text text-muted small mb-4">Perawatan database, backup data, log pengiriman WA, dan konsol utilitas.</p>
                    <div class="list-group list-group-flush small">
                        <a href="{{ route('admin.backup.index') }}" class="list-group-item list-group-item-action border-0 px-0">
                            <i class="bi bi-chevron-right me-1 text-danger"></i> Database Backup
                        </a>
                        <a href="{{ route('admin.reset.index') }}" class="list-group-item list-group-item-action border-0 px-0">
                            <i class="bi bi-chevron-right me-1 text-danger"></i> Reset Transaksi
                        </a>
                        <a href="{{ route('admin.wa_logs.index') }}" class="list-group-item list-group-item-action border-0 px-0">
                            <i class="bi bi-chevron-right me-1 text-danger"></i> WhatsApp Logs
                        </a>
                        <a href="{{ route('admin.system.index') }}" class="list-group-item list-group-item-action border-0 px-0">
                            <i class="bi bi-chevron-right me-1 text-danger"></i> System Utility (Artisan)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@endsection
