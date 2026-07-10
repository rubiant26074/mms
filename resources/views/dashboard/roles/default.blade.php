<h4 class="fw-bold text-dark mb-3 mt-4"><i class="bi bi-grid-fill me-2 text-primary"></i> Ringkasan MMS System</h4>
<div class="row g-4 mb-4">
    <!-- KPI 1 -->
    <div class="col-md-3">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Total Users</div>
                <h3 class="fw-bold text-dark">{{ number_format($stats['users'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-people"></i> Pengguna terdaftar</small>
            </div>
        </div>
    </div>
    <!-- KPI 2 -->
    <div class="col-md-3">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Roles / Jabatan</div>
                <h3 class="fw-bold text-dark">{{ number_format($stats['roles'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-shield-lock"></i> Hak akses dikonfigurasi</small>
            </div>
        </div>
    </div>
    <!-- KPI 3 -->
    <div class="col-md-3">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-info">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Sales Order</div>
                <h3 class="fw-bold text-dark">{{ number_format($stats['sales_orders'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-receipt"></i> Dokumen PO masuk</small>
            </div>
        </div>
    </div>
    <!-- KPI 4 -->
    <div class="col-md-3">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">SPK Aktif</div>
                <h3 class="fw-bold text-dark">{{ number_format($stats['spk_active'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-clipboard-check"></i> Produksi sedang berjalan</small>
            </div>
        </div>
    </div>
</div>
