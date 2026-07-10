<h4 class="fw-bold text-dark mb-3 mt-4"><i class="bi bi-calendar2-week me-2 text-primary"></i> Panel Perencanaan & Kontrol Produksi (PPIC)</h4>
<div class="row g-4 mb-4">
    <!-- KPI 1 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">SPK Aktif (Produksi)</div>
                <h3 class="fw-bold text-dark">{{ number_format($stats['spk_active'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-clipboard-check"></i> Surat perintah kerja sedang berjalan</small>
            </div>
        </div>
    </div>
    <!-- KPI 2 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-danger">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Item Di Bawah Stok Minimum</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['low_stock_items'] ?? 0) }}</h3>
                <small class="text-danger"><i class="bi bi-exclamation-triangle"></i> Membutuhkan re-order segera</small>
            </div>
        </div>
    </div>
    <!-- KPI 3 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Purchase Request (PR) Pending</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['pending_pr'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-cart-plus"></i> Permintaan pembelian belum diproses</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary"><i class="bi bi-lightning-charge"></i> Tindakan Cepat PPIC</h6></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('ppic.spk.index') }}" class="btn btn-outline-primary"><i class="bi bi-clipboard-check me-1"></i> Kelola SPK</a>
            <a href="{{ route('ppic.mps.index') }}" class="btn btn-outline-primary"><i class="bi bi-calendar-event me-1"></i> Penjadwalan MPS</a>
            <a href="{{ route('ppic.purchase_requests.index') }}" class="btn btn-outline-primary"><i class="bi bi-cart-plus me-1"></i> Permintaan Pembelian (PR)</a>
            <a href="{{ route('ppic.inventory.index') }}" class="btn btn-outline-primary"><i class="bi bi-box-seam me-1"></i> Stok & Inventory</a>
        </div>
    </div>
</div>
