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

@includeFirst([
    'dashboard.roles.' . $dashboardType,
    'dashboard.roles.default'
], ['stats' => $stats, 'roleStats' => $roleStats])

@if(in_array($dashboardType, ['admin', 'sales', 'ppic', 'manager'], true))
<!-- Section 1: Dashboard Analytics & Graphs -->
<div class="row g-4 mb-4 mt-2">
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
