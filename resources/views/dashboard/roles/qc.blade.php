<h4 class="fw-bold text-dark mb-3 mt-4"><i class="bi bi-shield-check me-2 text-primary"></i> Panel Penjaminan Mutu & Quality Control (QC)</h4>
<div class="row g-4 mb-4">
    <!-- KPI 1 -->
    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Inspeksi Masuk Hari Ini (Incoming QC)</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['qc_inspections_today'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-check-circle"></i> Pengecekan barang masuk supplier</small>
            </div>
        </div>
    </div>
    <!-- KPI 2 -->
    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-danger">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Kasus NCR Masih Terbuka (Open)</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['open_ncr'] ?? 0) }}</h3>
                <small class="text-danger"><i class="bi bi-exclamation-octagon"></i> Non-Conformance Report aktif</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary"><i class="bi bi-lightning-charge"></i> Tindakan Cepat QC</h6></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('qc.incoming.index') }}" class="btn btn-outline-primary"><i class="bi bi-box-arrow-in-down me-1"></i> Incoming QC</a>
            <a href="{{ route('qc.production.index') }}" class="btn btn-outline-primary"><i class="bi bi-clipboard-check me-1"></i> Production QC</a>
            <a href="{{ route('qc.ncr.index') }}" class="btn btn-outline-primary"><i class="bi bi-exclamation-octagon me-1"></i> Kasus NCR</a>
        </div>
    </div>
</div>
