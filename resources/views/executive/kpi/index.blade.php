@extends('layouts.mms')

@section('title', 'Executive Dashboard')

@section('content')
<div class="row mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold"><i class="bi bi-speedometer2"></i> Executive Dashboard</h3>
        <p class="text-muted">Ringkasan performa bisnis secara real-time.</p>
    </div>
    <div class="col-md-4 text-end">
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm"><i class="bi bi-printer"></i> Print Laporan</button>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-start border-4 border-success h-100">
            <div class="card-body">
                <small class="text-muted fw-bold text-uppercase">Total Penjualan</small>
                <h3 class="fw-bold text-dark mt-2">Rp {{ number_format($totalSales, 0, ',', '.') }}</h3>
                <small class="text-success"><i class="bi bi-graph-up-arrow"></i> Invoice Terbit</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-start border-4 border-danger h-100">
            <div class="card-body">
                <small class="text-muted fw-bold text-uppercase">Pembelian Material</small>
                <h3 class="fw-bold text-dark mt-2">Rp {{ number_format($totalPurchases, 0, ',', '.') }}</h3>
                <small class="text-danger"><i class="bi bi-cart"></i> PO ke Vendor</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-start border-4 border-warning h-100">
            <div class="card-body">
                <small class="text-muted fw-bold text-uppercase">Produksi Berjalan</small>
                <h3 class="fw-bold text-dark mt-2">{{ number_format($activeSpk) }} <span class="fs-6 text-muted">Batch</span></h3>
                <small class="text-warning"><i class="bi bi-gear-wide-connected"></i> SPK On Progress</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-start border-4 border-info h-100">
            <div class="card-body">
                <small class="text-muted fw-bold text-uppercase">Total User</small>
                <h3 class="fw-bold text-dark mt-2">{{ number_format($totalUsers) }} <span class="fs-6 text-muted">Akun</span></h3>
                <small class="text-info"><i class="bi bi-people"></i> Aktif</small>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-8 mb-4">
        <div class="card shadow h-100">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-graph-up"></i> Tren Penjualan (6 Bulan)</h6>
            </div>
            <div class="card-body">
                <div style="height: 300px; position: relative; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card shadow h-100">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-dark"><i class="bi bi-pie-chart"></i> Status Produksi</h6>
            </div>
            <div class="card-body">
                <div style="height: 250px; position: relative;">
                    <canvas id="spkChart"></canvas>
                </div>
                <div class="mt-4 text-center small">
                    <span class="me-2"><i class="bi bi-circle-fill" style="color:#858796"></i> Draft</span>
                    <span class="me-2"><i class="bi bi-circle-fill" style="color:#f6c23e"></i> Proses</span>
                    <span class="me-2"><i class="bi bi-circle-fill" style="color:#1cc88a"></i> Selesai</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-info"><i class="bi bi-trophy"></i> Top 5 Produk Terlaris</h6>
            </div>
            <div class="card-body">
                <div style="height: 300px; position: relative; width: 100%;">
                    <canvas id="productChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between">
                <h6 class="m-0 font-weight-bold text-secondary"><i class="bi bi-clock-history"></i> Log Aktivitas Terakhir</h6>
                <a href="{{ route('executive.logs.index') }}" class="small text-decoration-none">Lihat Semua &rarr;</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($recentLogs as $log)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold text-dark">{{ $log->user_name }}</span>
                                <span class="text-muted small"> : {{ $log->action }}</span><br>
                                <small class="text-muted" style="font-size:11px;">{{ $log->description }}</small>
                            </div>
                            <span class="badge bg-light text-dark border">{{ $log->created_at ? \Illuminate\Support\Carbon::parse($log->created_at)->format('d M H:i') : '-' }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-center text-muted py-4">Belum ada log aktivitas.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.font.family = "'Inter', sans-serif";

const salesChart = document.getElementById('salesChart');
if (salesChart) {
    new Chart(salesChart, {
        type: 'line',
        data: {
            labels: @json($salesLabels),
            datasets: [{
                label: 'Total Penjualan (Rp)',
                data: @json($salesValues),
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                pointRadius: 4,
                pointBackgroundColor: '#4e73df',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true }, x: { grid: { display: false } } } }
    });
}

const spkChart = document.getElementById('spkChart');
if (spkChart) {
    new Chart(spkChart, {
        type: 'doughnut',
        data: {
            labels: ['Draft', 'On Process', 'Completed'],
            datasets: [{ data: [{{ $spkDraft }}, {{ $spkProcess }}, {{ $spkDone }}], backgroundColor: ['#858796', '#f6c23e', '#1cc88a'], hoverBorderColor: '#fff', borderWidth: 2 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, cutout: '75%' }
    });
}

const productChart = document.getElementById('productChart');
if (productChart) {
    new Chart(productChart, {
        type: 'bar',
        data: {
            labels: @json($topProductLabels),
            datasets: [{ label: 'Unit Terjual', data: @json($topProductValues), backgroundColor: '#36b9cc', borderRadius: 4, barPercentage: 0.6 }]
        },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
    });
}
</script>
@endpush
