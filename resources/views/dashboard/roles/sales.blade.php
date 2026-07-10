<h4 class="fw-bold text-dark mb-3 mt-4"><i class="bi bi-graph-up-arrow me-2 text-primary"></i> Panel Penjualan & Sales Marketing</h4>
<div class="row g-4 mb-4">
    <!-- KPI 1 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Total Pendapatan (Sales Order)</div>
                <h3 class="fw-bold text-dark">IDR {{ number_format($roleStats['total_revenue'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-cash-stack"></i> Nilai kumulatif transaksi aktif</small>
            </div>
        </div>
    </div>
    <!-- KPI 2 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-info">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Quotation Pending (Draft)</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['pending_quotations'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-file-earmark-text"></i> Penawaran yang belum dirilis</small>
            </div>
        </div>
    </div>
    <!-- KPI 3 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Customer Baru Bulan Ini</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['new_customers'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-person-vcard"></i> Pelanggan baru terdaftar</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary"><i class="bi bi-lightning-charge"></i> Tindakan Cepat Sales</h6></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('sales.customers.index') }}" class="btn btn-outline-primary"><i class="bi bi-people-fill me-1"></i> Data Customers</a>
            <a href="{{ route('sales.quotations.index') }}" class="btn btn-outline-primary"><i class="bi bi-file-earmark-text me-1"></i> Surat Penawaran (Quotation)</a>
            <a href="{{ route('sales.orders.index') }}" class="btn btn-outline-primary"><i class="bi bi-receipt me-1"></i> Input Sales Order</a>
        </div>
    </div>
</div>
