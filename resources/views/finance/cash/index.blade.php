@extends('layouts.mms')

@section('title', 'Cash / Chasier')

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-cash-stack"></i> Cash / Chasier</h3>
        <p class="text-muted">Kelola pemasukan dan pengeluaran umum di luar transaksi SO/Invoice.</p>
    </div>
    <div class="col-md-6 text-end">
        @if(auth()->user()?->hasPermission('fin_ap_manage'))
            <a href="{{ route('finance.cash.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Input Transaksi Kas</a>
        @endif
    </div>
</div>

<div class="row mb-4 g-3">
    <div class="col-md-3"><div class="card border-start border-4 border-success shadow-sm h-100"><div class="card-body"><div class="small text-muted text-uppercase fw-bold">Pemasukan Posted (Bulan Ini)</div><h5 class="fw-bold mt-2 mb-0 text-success">Rp {{ number_format($summary['income_month'], 0, ',', '.') }}</h5></div></div></div>
    <div class="col-md-3"><div class="card border-start border-4 border-danger shadow-sm h-100"><div class="card-body"><div class="small text-muted text-uppercase fw-bold">Pengeluaran Posted (Bulan Ini)</div><h5 class="fw-bold mt-2 mb-0 text-danger">Rp {{ number_format($summary['expense_month'], 0, ',', '.') }}</h5></div></div></div>
    <div class="col-md-3"><div class="card border-start border-4 border-primary shadow-sm h-100"><div class="card-body"><div class="small text-muted text-uppercase fw-bold">Saldo Bersih (Bulan Ini)</div><h5 class="fw-bold mt-2 mb-0 {{ $summary['balance_month'] >= 0 ? 'text-primary' : 'text-danger' }}">Rp {{ number_format($summary['balance_month'], 0, ',', '.') }}</h5></div></div></div>
    <div class="col-md-3"><div class="card border-start border-4 border-warning shadow-sm h-100"><div class="card-body"><div class="small text-muted text-uppercase fw-bold">Draft Menunggu Posting</div><h5 class="fw-bold mt-2 mb-0 text-warning">Rp {{ number_format($summary['draft'], 0, ',', '.') }}</h5></div></div></div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-info">
    <div class="card-header bg-light"><strong><i class="bi bi-clipboard-data"></i> Laporan Rekap Kas</strong></div>
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-center mb-3">
            <div class="col-md-2"><label class="form-label mb-1 small text-muted">Periode Dari</label><input type="date" name="rekap_from" class="form-control" value="{{ $report['from'] }}"></div>
            <div class="col-md-2"><label class="form-label mb-1 small text-muted">Sampai</label><input type="date" name="rekap_to" class="form-control" value="{{ $report['to'] }}"></div>
            <div class="col-md-4"><label class="form-label mb-1 small text-muted">Akun Kas / Bank</label><select name="rekap_cash_coa" class="form-select"><option value="0">Semua Akun Kas/Bank</option>@foreach($report['cash_accounts'] as $opt)<option value="{{ $opt->id }}" @selected($report['cash_coa_id'] === $opt->id)>{{ $opt->account_code }} - {{ $opt->account_name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label mb-1 small text-muted d-block">&nbsp;</label><button class="btn btn-info text-white w-100">Tampilkan Rekap</button></div>
            <div class="col-md-2"><label class="form-label mb-1 small text-muted d-block">&nbsp;</label><button type="submit" name="action" value="print" formtarget="_blank" formaction="{{ route('finance.cash.print') }}" class="btn btn-outline-dark w-100"><i class="bi bi-printer"></i> Print Rekap</button></div>
        </form>

        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Saldo Awal</div><div class="fw-bold fs-5">Rp {{ number_format($report['opening'], 0, ',', '.') }}</div></div></div>
            <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Mutasi Masuk</div><div class="fw-bold fs-5 text-success">Rp {{ number_format($report['income'], 0, ',', '.') }}</div></div></div>
            <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Mutasi Keluar</div><div class="fw-bold fs-5 text-danger">Rp {{ number_format($report['expense'], 0, ',', '.') }}</div></div></div>
            <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Saldo Akhir</div><div class="fw-bold fs-5 {{ $report['closing'] >= 0 ? 'text-primary' : 'text-danger' }}">Rp {{ number_format($report['closing'], 0, ',', '.') }}</div></div></div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light"><tr><th>Tanggal</th><th class="text-end">Pemasukan</th><th class="text-end">Pengeluaran</th><th class="text-end">Net</th><th class="text-end">Saldo Berjalan</th></tr></thead>
                <tbody>
                    @php $running = $report['opening']; @endphp
                    @forelse($report['rows'] as $row)
                        @php $net = (float) $row->income_amount - (float) $row->expense_amount; $running += $net; @endphp
                        <tr><td>{{ \Illuminate\Support\Carbon::parse($row->expense_date)->format('d/m/Y') }}</td><td class="text-end text-success">Rp {{ number_format((float) $row->income_amount, 0, ',', '.') }}</td><td class="text-end text-danger">Rp {{ number_format((float) $row->expense_amount, 0, ',', '.') }}</td><td class="text-end {{ $net >= 0 ? 'text-primary' : 'text-danger' }}">Rp {{ number_format($net, 0, ',', '.') }}</td><td class="text-end {{ $running >= 0 ? 'text-primary' : 'text-danger' }}">Rp {{ number_format($running, 0, ',', '.') }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">Belum ada transaksi posted di periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="cash-filter-form">
            <div class="col-md-3"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="text" name="search" class="form-control" placeholder="Cari No / Kategori / Deskripsi / Relasi..." value="{{ $search }}" autocomplete="off"></div></div>
            <div class="col-md-2"><input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
            <div class="col-md-2"><input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
            <div class="col-md-2"><select name="trx_type" class="form-select"><option value="">- Semua Jenis -</option><option value="income" @selected($type === 'income')>Pemasukan</option><option value="expense" @selected($type === 'expense')>Pengeluaran</option></select></div>
            <div class="col-md-2"><select name="status" class="form-select"><option value="">- Semua Status -</option>@foreach(['draft'=>'Draft','posted'=>'Posted','cancelled'=>'Cancelled'] as $key=>$label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-1"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>Jenis</th><th>No. Bukti</th><th>Tanggal</th><th>Kategori</th><th>Deskripsi</th><th>Akun</th><th class="text-end">Nominal</th><th class="text-center">Status</th><th class="text-center" width="260">Aksi</th></tr></thead>
                <tbody>
                    @forelse($transactions as $row)
                        @php $typeBadge = $row->transaction_type === 'income' ? 'bg-success' : 'bg-danger'; $statusBadge = match($row->status) {'draft' => 'bg-secondary', 'posted' => 'bg-success', 'cancelled' => 'bg-danger', default => 'bg-light text-dark'}; @endphp
                        <tr>
                            <td class="text-center"><span class="badge {{ $typeBadge }}">{{ strtoupper($row->transaction_type === 'income' ? 'PEMASUKAN' : 'PENGELUARAN') }}</span></td>
                            <td><strong>{{ $row->expense_number }}</strong>@if($row->reference_no)<br><small class="text-muted">Ref: {{ $row->reference_no }}</small>@endif</td>
                            <td>{{ optional($row->expense_date)->format('d/m/Y') }}</td>
                            <td>{{ $row->category }}</td>
                            <td>{{ $row->description }}@if($row->vendor_name)<br><small class="text-muted">{{ $row->vendor_name }}</small>@endif</td>
                            <td><small class="text-muted d-block">Lawan:</small><small class="fw-semibold">{{ trim(($row->counterCoa?->account_code ?: '') . ($row->counterCoa?->account_name ? ' - ' . $row->counterCoa?->account_name : '')) }}</small><small class="text-muted d-block mt-1">Kas/Bank:</small><small class="fw-semibold">{{ trim(($row->cashCoa?->account_code ?: '') . ($row->cashCoa?->account_name ? ' - ' . $row->cashCoa?->account_name : '')) }}</small></td>
                            <td class="text-end fw-bold {{ $row->transaction_type === 'income' ? 'text-success' : 'text-danger' }}">Rp {{ number_format((float) $row->amount, 0, ',', '.') }}</td>
                            <td class="text-center"><span class="badge {{ $statusBadge }}">{{ strtoupper($row->status) }}</span></td>
                            <td class="text-center">
                                @if(auth()->user()?->hasPermission('fin_ap_manage'))
                                    <div class="btn-group">
                                        @if($row->status === 'draft')
                                            <a href="{{ route('finance.cash.edit', $row) }}" class="btn btn-sm btn-warning text-dark"><i class="bi bi-pencil"></i></a>
                                            <form method="POST" action="{{ route('finance.cash.workflow', [$row, 'post']) }}" onsubmit="return confirm('Posting transaksi ini?')">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-send"></i></button></form>
                                            <form method="POST" action="{{ route('finance.cash.destroy', $row) }}" onsubmit="return confirm('Hapus data draft ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button></form>
                                        @elseif($row->status === 'posted')
                                            <form method="POST" action="{{ route('finance.cash.workflow', [$row, 'unpost']) }}" onsubmit="return confirm('Batalkan posting transaksi ini?')">@csrf<button class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-counterclockwise"></i></button></form>
                                            <form method="POST" action="{{ route('finance.cash.workflow', [$row, 'cancel']) }}" onsubmit="return confirm('Batalkan data transaksi ini?')">@csrf<button class="btn btn-sm btn-danger"><i class="bi bi-x-circle"></i></button></form>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted small">Read Only</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">Belum ada data transaksi kas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function(){const form=document.getElementById('cash-filter-form');const search=form?.querySelector('input[name="search"]');let t;search?.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>form.requestSubmit?form.requestSubmit():form.submit(),400)});})();
</script>
@endpush
@endsection
