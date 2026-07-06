@extends('layouts.mms')

@section('title', 'Purchase Order')

@section('content')
@include('partials.alerts')
@php
    $badges = ['draft'=>'bg-secondary','submitted'=>'bg-warning text-dark','approved_pm'=>'bg-primary','approved_finance'=>'bg-success','sent'=>'bg-info text-dark','completed'=>'bg-success','cancelled'=>'bg-danger'];
@endphp
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-bag-check"></i> Purchase Order</h3>
        <p class="text-muted">Order pembelian material ke Supplier/Vendor.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="{{ route('procurement.orders.create') }}" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Buat PO Baru</a>
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="po-filter-form">
            <div class="col-md-4"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="text" name="search" class="form-control" placeholder="Cari No. PO / Supplier..." value="{{ $search }}" autocomplete="off"></div></div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    @foreach(['draft'=>'Draft','submitted'=>'Submitted','approved_pm'=>'Approved PM','approved_finance'=>'Approved Finance','sent'=>'Sent','completed'=>'Completed','cancelled'=>'Cancelled'] as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('procurement.orders.index') }}" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light"><tr><th>No. PO</th><th>Tanggal</th><th>Supplier</th><th>Status</th><th class="text-end">Total</th><th class="text-center" width="220">Aksi</th></tr></thead>
            <tbody>
            @forelse($orders as $row)
                <tr>
                    <td><strong>{{ $row->po_number }}</strong></td>
                    <td>{{ optional($row->po_date)->format('d/m/Y') }}</td>
                    <td>{{ $row->supplier?->name ?: '-' }}</td>
                    <td>
                        <div><span class="badge {{ $badges[$row->status] ?? 'bg-light text-dark' }}">{{ strtoupper(str_replace('_', ' ', $row->status)) }}</span></div>
                        <div class="text-muted small mt-1">PM: {{ $row->approver?->fullname ?: '-' }} @if($row->approved_at) ({{ $row->approved_at->format('d/m/Y H:i') }}) @endif</div>
                        <div class="text-muted small">FIN: {{ $row->financeApprover?->fullname ?: '-' }} @if($row->finance_approved_at) ({{ $row->finance_approved_at->format('d/m/Y H:i') }}) @endif</div>
                    </td>
                    <td class="text-end fw-bold">Rp {{ number_format((float) $row->grand_total, 0, ',', '.') }}</td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a href="{{ route('procurement.orders.print', $row) }}" target="_blank" class="btn btn-sm btn-outline-dark" title="Cetak PO"><i class="bi bi-printer"></i></a>
                            @if($row->status === 'draft' && auth()->user()?->hasPermission('purch_po_manage'))
                                <a href="{{ route('procurement.orders.edit', $row) }}" class="btn btn-sm btn-warning text-dark" title="Edit"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('procurement.orders.workflow', [$row, 'submit']) }}" onsubmit="return confirm('Ajukan PO ini?')">@csrf<button class="btn btn-sm btn-success" title="Submit"><i class="bi bi-send"></i></button></form>
                            @endif
                            @if(in_array($row->status, ['draft', 'cancelled'], true) && auth()->user()?->hasPermission('purch_po_delete'))
                                <form method="POST" action="{{ route('procurement.orders.destroy', $row) }}" onsubmit="return confirm('Hapus PO ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button></form>
                            @endif
                            @if($row->status === 'submitted' && auth()->user()?->hasPermission('purch_po_approve'))
                                <form method="POST" action="{{ route('procurement.orders.workflow', [$row, 'approve']) }}" onsubmit="return confirm('Approve PO ini?')">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Appv</button></form>
                            @endif
                            @if($row->status === 'approved_pm' && auth()->user()?->hasPermission('purch_po_approve_finance'))
                                <form method="POST" action="{{ route('procurement.orders.workflow', [$row, 'approve_finance']) }}" onsubmit="return confirm('Approve PO ini (Finance)?')">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Finance</button></form>
                            @endif
                            @if($row->status === 'approved_finance' && auth()->user()?->hasPermission('purch_po_manage'))
                                <form method="POST" action="{{ route('procurement.orders.workflow', [$row, 'send_vendor']) }}" onsubmit="return confirm('Konfirmasi PO ke Supplier?')">@csrf<button class="btn btn-sm btn-info text-dark"><i class="bi bi-envelope-paper"></i> Konfirmasi</button></form>
                            @endif
                            @if($row->status === 'sent')
                                <a href="/index.php?page=whse-receive&action=create&po_id={{ $row->id }}" class="btn btn-sm btn-success fw-bold"><i class="bi bi-box-seam"></i> Terima</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-5 text-muted">Data PO belum ada.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
(function(){const form=document.getElementById('po-filter-form');const search=form?.querySelector('input[name="search"]');const status=form?.querySelector('select[name="status"]');let t;search?.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>form.requestSubmit?form.requestSubmit():form.submit(),400)});status?.addEventListener('change',()=>form.requestSubmit?form.requestSubmit():form.submit());})();
</script>
@endpush
@endsection
