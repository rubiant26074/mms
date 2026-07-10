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

<!-- Section 1: Dashboard Analytics & Graphs -->
<div class="row g-4 mb-4">
    <!-- Chart 1: Tren Sales Order -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-graph-up text-primary me-2"></i> Tren Pemesanan Sales Order (6 Bulan Terakhir)</h6>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 320px;">
                    <canvas id="salesOrderDashboardChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart 2: Status Produksi SPK -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-pie-chart text-success me-2"></i> Distribusi Status Produksi (SPK)</h6>
            </div>
            <div class="card-body d-flex flex-column justify-content-center">
                <div style="position: relative; height: 220px;" class="mb-3">
                    <canvas id="spkDashboardChart"></canvas>
                </div>
                <div class="text-center small text-muted">
                    <span>Membantu melacak status pengerjaan SPK aktif di pabrik.</span>
                </div>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = "#858796";

        // 1. Sales Order Chart
        const ctxSales = document.getElementById('salesOrderDashboardChart');
        if (ctxSales) {
            new Chart(ctxSales, {
                type: 'line',
                data: {
                    labels: {!! json_encode($salesLabels) !!},
                    datasets: [{
                        label: "Total Sales Orders",
                        lineTension: 0.3,
                        backgroundColor: "rgba(78, 115, 223, 0.05)",
                        borderColor: "rgba(78, 115, 223, 1)",
                        pointRadius: 3,
                        pointBackgroundColor: "rgba(78, 115, 223, 1)",
                        pointBorderColor: "rgba(78, 115, 223, 1)",
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                        pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        data: {!! json_encode($salesValues) !!},
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            }
                        },
                        y: {
                            ticks: {
                                maxTicksLimit: 5,
                                padding: 10,
                            },
                            grid: {
                                color: "rgb(234, 236, 244)",
                                zeroLineColor: "rgb(234, 236, 244)",
                                drawBorder: false,
                                borderDash: [2],
                                zeroLineBorderDash: [2]
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        // 2. SPK Distribution Chart
        const ctxSpk = document.getElementById('spkDashboardChart');
        if (ctxSpk) {
            new Chart(ctxSpk, {
                type: 'doughnut',
                data: {
                    labels: ["Draft", "Dalam Proses", "Selesai"],
                    datasets: [{
                        data: [
                            {{ $spkData['draft'] }},
                            {{ $spkData['process'] }},
                            {{ $spkData['completed'] }}
                        ],
                        backgroundColor: ['#858796', '#36b9cc', '#1cc88a'],
                        hoverBackgroundColor: ['#5a5c69', '#2c9faf', '#17a673'],
                        hoverBorderColor: "rgba(234, 236, 244, 1)",
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 15,
                                padding: 15
                            }
                        }
                    },
                    cutout: '70%',
                }
            });
        }
    });
</script>
@endsection
