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

<div class="alert alert-success border-0 shadow-sm">
    Dashboard ini berjalan penuh di Laravel dan sudah tidak bergantung pada renderer PHP native lama.
</div>
@endsection
