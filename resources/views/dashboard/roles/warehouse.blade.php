<h4 class="fw-bold text-dark mb-3 mt-4"><i class="bi bi-box-seam me-2 text-primary"></i> Panel Logistik & Manajemen Gudang (Warehouse)</h4>
<div class="row g-4 mb-4">
    <!-- KPI 1 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Penerimaan Barang Pending</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['pending_receipts'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-box-arrow-in-down"></i> Kedatangan PO siap diperiksa</small>
            </div>
        </div>
    </div>
    <!-- KPI 2 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-info">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Pengeluaran Bahan Pending</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['pending_issues'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-box-arrow-up"></i> Permintaan bahan ke lantai produksi</small>
            </div>
        </div>
    </div>
    <!-- KPI 3 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Surat Jalan Hari Ini</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['sj_today'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-truck"></i> Pengiriman barang ke customer</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary"><i class="bi bi-lightning-charge"></i> Tindakan Cepat Gudang</h6></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('warehouse.receipts.index') }}" class="btn btn-outline-primary"><i class="bi bi-box-arrow-in-down me-1"></i> Penerimaan Barang</a>
            <a href="{{ route('warehouse.material_issues.index') }}" class="btn btn-outline-primary"><i class="bi bi-box-arrow-up me-1"></i> Pengeluaran Bahan (Issue)</a>
            <a href="{{ route('warehouse.delivery_notes.index') }}" class="btn btn-outline-primary"><i class="bi bi-truck me-1"></i> Surat Jalan (Delivery)</a>
            <a href="{{ route('warehouse.material_returns.index') }}" class="btn btn-outline-primary"><i class="bi bi-arrow-return-left me-1"></i> Retur Material</a>
        </div>
    </div>
</div>
