<h4 class="fw-bold text-dark mb-3 mt-4"><i class="bi bi-bar-chart-line me-2 text-primary"></i> Panel Executive & General Manager</h4>
<div class="row g-4 mb-4">
    <!-- KPI 1 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Total Sales Orders (PO Masuk)</div>
                <h3 class="fw-bold text-dark">{{ number_format($stats['sales_orders'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-receipt"></i> Akumulasi dokumen pesanan penjualan</small>
            </div>
        </div>
    </div>
    <!-- KPI 2 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Total SPK Produksi Aktif</div>
                <h3 class="fw-bold text-dark">{{ number_format($stats['spk_active'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-clipboard-check"></i> Surat perintah kerja sedang diproses</small>
            </div>
        </div>
    </div>
    <!-- KPI 3 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-info">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Total Pengguna Terdaftar</div>
                <h3 class="fw-bold text-dark">{{ number_format($stats['users'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-people"></i> Pengguna terdaftar dalam sistem</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary"><i class="bi bi-lightning-charge"></i> Tindakan Cepat Executive</h6></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('executive.kpi.index') }}" class="btn btn-outline-primary"><i class="bi bi-trophy-fill me-1"></i> Dashboard KPI Utama</a>
            <a href="{{ route('executive.logs.index') }}" class="btn btn-outline-primary"><i class="bi bi-terminal me-1"></i> Log Aktivitas Sistem</a>
        </div>
    </div>
</div>
