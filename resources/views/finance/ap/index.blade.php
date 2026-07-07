@extends('layouts.mms')

@section('title', 'Accounts Payable')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
    <div><h3 class="fw-bold mb-1"><i class="bi bi-wallet2"></i> Tagihan Supplier (AP)</h3><p class="text-muted mb-0">Manajemen hutang usaha kepada vendor.</p></div>
    <div class="text-md-end"><a href="{{ route('finance.ap.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Input Tagihan Baru</a></div>
</div>
<div class="card shadow-sm mb-4 border-start border-4 border-primary"><div class="card-body py-3"><form method="GET" class="row g-2 align-items-center"><div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Cari No. Bill / Supplier / Inv Supplier / PO..." value="{{ $search }}"></div><div class="col-md-3"><select name="status" class="form-select"><option value="">- Semua Status -</option>@foreach(['draft'=>'Draft','unpaid'=>'Unpaid','partial'=>'Partial','paid'=>'Paid','cancelled'=>'Cancelled'] as $key=>$label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></div><div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div><div class="col-md-1"><a href="{{ route('finance.ap.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a></div></form></div></div>
<div class="row mb-4"><div class="col-md-4"><div class="card bg-danger text-white shadow-sm"><div class="card-body"><h6>Total Hutang (Outstanding)</h6><h3 class="fw-bold mb-0">Rp {{ number_format((float) $totalOutstanding, 0, ',', '.') }}</h3></div></div></div></div>
<div class="card shadow-sm"><div class="card-body"><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>No. Bill</th><th>Inv Supplier</th><th>Supplier</th><th>Jatuh Tempo</th><th class="text-end">Total</th><th class="text-end">Sisa</th><th class="text-center">Status</th><th class="text-center" width="220">Aksi</th></tr></thead><tbody>
@forelse($bills as $bill)
@php($remaining = $bill->grand_total - $bill->paid_amount)
@php($badge = match($bill->status) {'draft'=>'bg-secondary','unpaid'=>'bg-danger','partial'=>'bg-warning text-dark','paid'=>'bg-success','cancelled'=>'bg-dark',default=>'bg-light text-dark'})
<tr><td><strong>{{ $bill->bill_number }}</strong>@if($bill->purchaseOrder)<br><small class="text-muted">Ref PO: {{ $bill->purchaseOrder->po_number }}</small>@endif</td><td>{{ $bill->supplier_inv_number }}</td><td>{{ $bill->supplier?->name }}</td><td>{{ optional($bill->due_date)->format('d/m/Y') }}</td><td class="text-end">Rp {{ number_format($bill->grand_total, 0, ',', '.') }}</td><td class="text-end fw-bold text-danger">Rp {{ number_format($remaining, 0, ',', '.') }}</td><td class="text-center"><span class="badge {{ $badge }}">{{ strtoupper($bill->status) }}</span></td><td class="text-center"><div class="btn-group">
@if(in_array($bill->status, ['unpaid','partial'], true))<a href="{{ route('finance.ap.payment', $bill) }}" class="btn btn-sm btn-success"><i class="bi bi-cash-stack"></i></a>@endif
@if($bill->status === 'unpaid' && $bill->paid_amount == 0)<form method="POST" action="{{ route('finance.ap.unpost', $bill) }}" onsubmit="return confirm('Batalkan Posting?')">@csrf<button class="btn btn-sm btn-danger"><i class="bi bi-arrow-counterclockwise"></i></button></form>@endif
@if($bill->status === 'draft')<a href="{{ route('finance.ap.edit', $bill) }}" class="btn btn-sm btn-warning text-dark"><i class="bi bi-pencil"></i></a><form method="POST" action="{{ route('finance.ap.post', $bill) }}" onsubmit="return confirm('Posting Tagihan?')">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-send"></i></button></form><form method="POST" action="{{ route('finance.ap.destroy', $bill) }}" onsubmit="return confirm('Hapus data?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button></form>@endif
</div></td></tr>
@empty<tr><td colspan="8" class="text-center text-muted py-4">Belum ada tagihan supplier.</td></tr>@endforelse
</tbody></table></div></div></div>
@endsection
