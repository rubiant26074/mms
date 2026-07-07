@extends('layouts.mms')

@section('title', 'System Audit Logs')

@php
    $moduleBadges = [
        'AUTH' => 'bg-dark',
        'SALES' => 'bg-success',
        'FINANCE' => 'bg-warning text-dark',
        'PROD' => 'bg-primary',
        'WHSE' => 'bg-info text-dark',
    ];
    $moduleOptions = [
        'AUTH' => 'Login/Logout',
        'SALES' => 'Sales',
        'PPIC' => 'PPIC',
        'PROD' => 'Production',
        'WHSE' => 'Warehouse',
        'FINANCE' => 'Finance',
    ];
@endphp

@section('content')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-activity"></i> Audit Logs</h3>
        <p class="text-muted">Rekam jejak aktivitas pengguna dalam sistem.</p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <select name="module" class="form-select">
                    <option value="">- Semua Modul -</option>
                    @foreach($moduleOptions as $value => $label)
                        <option value="{{ $value }}" @selected($module === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Cari User / Aktivitas..." value="{{ $search }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light">
                    <tr>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Modul</th>
                        <th>Aksi</th>
                        <th>Deskripsi</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ $log->created_at ? \Illuminate\Support\Carbon::parse($log->created_at)->format('d/m/y H:i:s') : '-' }}</td>
                            <td class="fw-bold">{{ $log->user_name }}</td>
                            <td>{{ $log->role }}</td>
                            <td><span class="badge {{ $moduleBadges[$log->module] ?? 'bg-secondary' }}">{{ $log->module }}</span></td>
                            <td><strong>{{ $log->action }}</strong></td>
                            <td>{{ $log->description }}</td>
                            <td class="text-muted small">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada data log.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer small text-muted">
        Menampilkan {{ $logs->count() }} aktivitas terakhir.
    </div>
</div>
@endsection
