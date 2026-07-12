<h4 class="fw-bold text-dark mb-3 mt-4"><i class="bi bi-tools me-2 text-primary"></i> Panel Engineering & Pengembangan Produk</h4>
<div class="row g-4 mb-4">
    <!-- KPI 1 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Total Items / Master Bahan</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['total_items'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-box"></i> Raw, semi-finished, dan finished goods</small>
            </div>
        </div>
    </div>
    <!-- KPI 2 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Bill of Materials (BOM) Aktif</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['active_boms'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-diagram-3"></i> Formula struktur produk terdaftar</small>
            </div>
        </div>
    </div>
    <!-- KPI 3 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-info">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Mesin Pabrik Aktif</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['active_machines'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-hdd-rack"></i> Unit mesin status siap operasional</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary"><i class="bi bi-lightning-charge"></i> Tindakan Cepat Engineering</h6></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('engineering.boms.index') }}" class="btn btn-outline-primary"><i class="bi bi-diagram-3-fill me-1"></i> Kelola Struktur BOM</a>
            <a href="{{ route('engineering.partlists.index') }}" class="btn btn-outline-primary"><i class="bi bi-list-check me-1"></i> Daftar Part List</a>
        </div>
    </div>
</div>
