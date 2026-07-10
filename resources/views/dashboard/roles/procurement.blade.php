<h4 class="fw-bold text-dark mb-3 mt-4"><i class="bi bi-bag-check me-2 text-primary"></i> Panel Pembelian & Procurement</h4>
<div class="row g-4 mb-4">
    <!-- KPI 1 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Total Pengeluaran PO</div>
                <h3 class="fw-bold text-dark">IDR {{ number_format($roleStats['total_purchase_spend'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-cash-coin"></i> Total dana belanja material</small>
            </div>
        </div>
    </div>
    <!-- KPI 2 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">RFQ Terbuka (Open)</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['open_rfq'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-clipboard2-data"></i> Request penawaran harga aktif</small>
            </div>
        </div>
    </div>
    <!-- KPI 3 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-info">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Purchase Order Pending</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['pending_po'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-file-earmark-text"></i> Menunggu konfirmasi supplier</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary"><i class="bi bi-lightning-charge"></i> Tindakan Cepat Procurement</h6></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('procurement.suppliers.index') }}" class="btn btn-outline-primary"><i class="bi bi-truck me-1"></i> Data Suppliers</a>
            <a href="{{ route('procurement.rfqs.index') }}" class="btn btn-outline-primary"><i class="bi bi-clipboard2-data-fill me-1"></i> Permintaan Penawaran (RFQ)</a>
            <a href="{{ route('procurement.orders.index') }}" class="btn btn-outline-primary"><i class="bi bi-receipt-cutoff me-1"></i> Transaksi Purchase Order (PO)</a>
        </div>
    </div>
</div>
