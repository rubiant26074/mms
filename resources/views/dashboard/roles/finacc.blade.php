<h4 class="fw-bold text-dark mb-3 mt-4"><i class="bi bi-cash-coin me-2 text-primary"></i> Panel Keuangan & Accounting (Fin-Acc)</h4>
<div class="row g-4 mb-4">
    <!-- KPI 1 -->
    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Estimasi Kas & Bank Aktif</div>
                <h3 class="fw-bold text-dark">IDR {{ number_format($roleStats['cash_balance'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-bank"></i> Saldo gabungan akun kas & bank</small>
            </div>
        </div>
    </div>
    <!-- KPI 2 -->
    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Jurnal Umum Pending (Draft)</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['unposted_journals'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-journal-text"></i> Jurnal pembukuan belum diposting</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary"><i class="bi bi-lightning-charge"></i> Tindakan Cepat Fin-Acc</h6></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('finance.cash.index') }}" class="btn btn-outline-primary"><i class="bi bi-bank me-1"></i> Transaksi Kas & Bank</a>
            <a href="{{ route('finance.ar.index') }}" class="btn btn-outline-primary"><i class="bi bi-receipt me-1"></i> Accounts Receivable (AR)</a>
            <a href="{{ route('finance.ap.index') }}" class="btn btn-outline-primary"><i class="bi bi-wallet2 me-1"></i> Accounts Payable (AP)</a>
            <a href="{{ route('accounting.coa.index') }}" class="btn btn-outline-primary"><i class="bi bi-list-columns-reverse me-1"></i> Bagan Akun (COA)</a>
            <a href="{{ route('accounting.journal.index') }}" class="btn btn-outline-primary"><i class="bi bi-journal-text me-1"></i> Jurnal Umum</a>
            <a href="{{ route('accounting.reports.index') }}" class="btn btn-outline-primary"><i class="bi bi-file-earmark-bar-graph me-1"></i> Laporan Keuangan</a>
        </div>
    </div>
</div>
