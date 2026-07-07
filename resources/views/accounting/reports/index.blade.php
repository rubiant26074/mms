@extends('layouts.mms')

@section('title', 'Laporan Keuangan')

@section('content')
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end" id="report-filter-form">
            <div class="col-md-3">
                <label class="form-label fw-bold">Jenis Laporan</label>
                <select name="type" class="form-select bg-light fw-bold text-primary">
                    <option value="pl" @selected($reportType === 'pl')>Laba Rugi (Profit & Loss)</option>
                    <option value="bs" @selected($reportType === 'bs')>Neraca (Balance Sheet)</option>
                </select>
            </div>
            @if($reportType === 'pl')
                <div class="col-md-3"><label class="form-label">Dari Tanggal</label><input type="date" name="start_date" class="form-control" value="{{ $startDate }}"></div>
            @endif
            <div class="col-md-3"><label class="form-label">{{ $reportType === 'pl' ? 'Sampai Tanggal' : 'Per Tanggal (As of)' }}</label><input type="date" name="end_date" class="form-control" value="{{ $endDate }}"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Tampilkan</button></div>
            <div class="col-md-1"><a href="{{ route('accounting.reports.print', ['type' => $reportType, 'start_date' => $startDate, 'end_date' => $endDate]) }}" target="_blank" class="btn btn-outline-dark w-100"><i class="bi bi-printer"></i></a></div>
        </form>
    </div>
</div>

@if($reportType === 'pl')
<div class="row justify-content-center">
    <div class="col-md-9">
        <div class="card shadow border-top border-4 border-primary">
            <div class="card-header bg-white text-center py-4"><h4 class="mb-1 fw-bold">LAPORAN LABA RUGI</h4><p class="text-muted mb-0">Periode: {{ $periodLabel }}</p></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <tr class="table-success"><td colspan="2" class="fw-bold ps-3">PENDAPATAN (REVENUE)</td></tr>
                    @forelse($data['revenue'] as $row)<tr><td class="ps-4">{{ $row->account_code }} - {{ $row->account_name }}</td><td class="text-end pe-4">{{ number_format($row->balance_rev, 0, ',', '.') }}</td></tr>@empty<tr><td colspan="2" class="ps-4 text-muted fst-italic py-3"><i class="bi bi-info-circle"></i> Belum ada pendapatan. Pastikan Invoice sudah di-<strong>POSTING</strong>.</td></tr>@endforelse
                    <tr class="fw-bold bg-light"><td class="text-end pe-3">Total Pendapatan</td><td class="text-end pe-4 text-success">{{ number_format($totalRevenue, 0, ',', '.') }}</td></tr>
                    <tr class="table-danger border-top"><td colspan="2" class="fw-bold ps-3">BEBAN (EXPENSES)</td></tr>
                    @forelse($data['expense'] as $row)<tr><td class="ps-4">{{ $row->account_code }} - {{ $row->account_name }}</td><td class="text-end pe-4">{{ number_format($row->balance_exp, 0, ',', '.') }}</td></tr>@empty<tr><td colspan="2" class="ps-4 text-muted fst-italic">Belum ada beban tercatat.</td></tr>@endforelse
                    <tr class="fw-bold bg-light"><td class="text-end pe-3">Total Beban</td><td class="text-end pe-4 text-danger">{{ number_format($totalExpense, 0, ',', '.') }}</td></tr>
                    <tr class="table-primary border-top border-3 border-dark"><td class="fw-bold fs-5 ps-3">LABA / (RUGI) BERSIH</td><td class="text-end fw-bold fs-5 pe-4">{{ number_format($netIncome, 0, ',', '.') }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@else
<div class="row">
    <div class="col-md-6 mb-3"><div class="card shadow h-100 border-top border-4 border-success"><div class="card-header bg-white text-center py-3"><h5 class="mb-0 fw-bold text-success">AKTIVA (ASSETS)</h5><small class="text-muted">{{ $periodLabel }}</small></div><div class="card-body p-0"><table class="table table-hover mb-0">@foreach($data['asset'] as $row)<tr><td class="ps-3">{{ $row->account_code }} - {{ $row->account_name }}</td><td class="text-end pe-3">{{ number_format($row->balance_asset, 0, ',', '.') }}</td></tr>@endforeach</table></div><div class="card-footer bg-light fw-bold d-flex justify-content-between px-3"><span>TOTAL ASSET</span><span>{{ number_format($totalAsset, 0, ',', '.') }}</span></div></div></div>
    <div class="col-md-6 mb-3"><div class="card shadow h-100 border-top border-4 border-danger"><div class="card-header bg-white text-center py-3"><h5 class="mb-0 fw-bold text-danger">PASIVA (LIABILITY + EQUITY)</h5><small class="text-muted">{{ $periodLabel }}</small></div><div class="card-body p-0"><table class="table table-hover mb-0"><tr class="table-warning"><td colspan="2" class="fw-bold ps-3">KEWAJIBAN (LIABILITY)</td></tr>@foreach($data['liability'] as $row)<tr><td class="ps-4">{{ $row->account_code }} - {{ $row->account_name }}</td><td class="text-end pe-3">{{ number_format($row->balance_passiva, 0, ',', '.') }}</td></tr>@endforeach<tr class="fw-bold bg-light"><td class="text-end pe-3">Total Kewajiban</td><td class="text-end pe-3">{{ number_format($totalLiability, 0, ',', '.') }}</td></tr><tr class="table-info border-top"><td colspan="2" class="fw-bold ps-3">MODAL (EQUITY)</td></tr>@foreach($data['equity'] as $row)<tr><td class="ps-4">{{ $row->account_code }} - {{ $row->account_name }}</td><td class="text-end pe-3">{{ number_format($row->balance_passiva, 0, ',', '.') }}</td></tr>@endforeach<tr class="fw-bold bg-light"><td class="text-end pe-3">Total Modal</td><td class="text-end pe-3">{{ number_format($totalEquity, 0, ',', '.') }}</td></tr></table></div><div class="card-footer bg-light fw-bold d-flex justify-content-between px-3 border-top border-3 border-dark"><span>TOTAL PASIVA</span><span>{{ number_format($totalLiability + $totalEquity, 0, ',', '.') }}</span></div></div></div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('report-filter-form');
    if (!form) return;
    const submit = () => form.requestSubmit ? form.requestSubmit() : form.submit();
    form.querySelector('select[name="type"]')?.addEventListener('change', submit);
    form.querySelector('input[name="start_date"]')?.addEventListener('change', submit);
    form.querySelector('input[name="end_date"]')?.addEventListener('change', submit);
})();
</script>
@endpush
