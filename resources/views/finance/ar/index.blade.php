@extends('layouts.mms')

@section('title', 'Accounts Receivable')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-receipt-cutoff"></i> Invoice Penjualan</h3>
        <p class="text-muted mb-0">Penagihan ke customer dan Faktur Pajak.</p>
    </div>
    <div class="text-md-end"><a href="{{ route('finance.ar.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Buat Invoice Baru</a></div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Cari No. Invoice / FP / Customer..." value="{{ $search }}"></div>
            <div class="col-md-3"><select name="status" class="form-select"><option value="">- Semua Status -</option>@foreach(['draft'=>'Draft','unpaid'=>'Unpaid','partial'=>'Partial','paid'=>'Paid','cancelled'=>'Cancelled'] as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('finance.ar.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4"><div class="card bg-primary text-white shadow-sm"><div class="card-body"><h6>Total Piutang (Outstanding)</h6><h3 class="fw-bold mb-0">Rp {{ number_format((float) $totalOutstanding, 0, ',', '.') }}</h3></div></div></div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>No. Invoice</th><th>Tanggal</th><th>Customer</th><th>Jatuh Tempo</th><th class="text-end">Total Tagihan</th><th class="text-end">Sisa Tagihan</th><th class="text-center">Status</th><th class="text-center" width="220">Aksi</th></tr></thead>
                <tbody>
                @forelse($invoices as $invoice)
                    @php
                        $remaining = $invoice->grand_total - $invoice->paid_amount;
                        $badge = match($invoice->status) {'draft'=>'bg-secondary','unpaid'=>'bg-danger','partial'=>'bg-warning text-dark','paid'=>'bg-success','cancelled'=>'bg-dark',default=>'bg-light text-dark'};
                    @endphp
                    <tr>
                        <td><strong>{{ $invoice->invoice_number }}</strong>@if($invoice->tax_invoice_number)<br><small class="text-muted">FP: {{ $invoice->tax_invoice_number }}</small>@endif</td>
                        <td>{{ optional($invoice->invoice_date)->format('d/m/y') }}</td>
                        <td>{{ $invoice->customer?->name }}</td>
                        <td>{{ optional($invoice->due_date)->format('d/m/y') }} @if($invoice->status !== 'paid' && $invoice->due_date?->isPast())<i class="bi bi-exclamation-circle-fill text-danger"></i>@endif</td>
                        <td class="text-end">Rp {{ number_format($invoice->grand_total, 0, ',', '.') }}</td>
                        <td class="text-end fw-bold text-danger">Rp {{ number_format($remaining, 0, ',', '.') }}</td>
                        <td class="text-center"><span class="badge {{ $badge }}">{{ strtoupper($invoice->status) }}</span></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="{{ route('finance.ar.print', $invoice) }}" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer"></i></a>
                                <a href="{{ route('finance.ar.print_tax', $invoice) }}" target="_blank" class="btn btn-sm btn-outline-secondary font-monospace fw-bold">FP</a>
                                @if(in_array($invoice->status, ['unpaid', 'partial'], true))<a href="{{ route('finance.ar.payment', $invoice) }}" class="btn btn-sm btn-success"><i class="bi bi-cash-coin"></i></a>@endif
                                @if($invoice->status === 'unpaid' && $invoice->paid_amount == 0)<form method="POST" action="{{ route('finance.ar.unpost', $invoice) }}" onsubmit="return confirm('Batalkan Posting? Jurnal akan dihapus dan status kembali ke Draft.')">@csrf<button class="btn btn-sm btn-danger"><i class="bi bi-arrow-counterclockwise"></i></button></form>@endif
                                @if($invoice->status === 'draft')
                                    <a href="{{ route('finance.ar.edit', $invoice) }}" class="btn btn-sm btn-warning text-dark"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" action="{{ route('finance.ar.post', $invoice) }}" onsubmit="return confirm('Posting Invoice? Data tidak bisa diubah lagi.')">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-send"></i></button></form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada invoice.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
