@extends('layouts.mms')

@section('title', 'Penerimaan Barang')

@section('content')
@include('partials.alerts')
@php $badges = ['draft'=>'bg-secondary','qc_pending'=>'bg-warning text-dark','approved'=>'bg-success','rejected'=>'bg-danger']; @endphp
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-box-seam"></i> Penerimaan Barang</h3>
        <p class="text-muted">Input kedatangan material dari Supplier (PO) atau Customer (Consignment).</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="{{ route('warehouse.receipts.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Terima Barang Baru</a>
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="gr-filter-form">
            <div class="col-md-4"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="text" name="search" class="form-control" placeholder="Cari No. GR / Supplier / Cust / No. SJ..." value="{{ $search }}" autocomplete="off"></div></div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    @foreach(['draft'=>'Draft','qc_pending'=>'QC Pending','approved'=>'Approved','rejected'=>'Rejected'] as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('warehouse.receipts.index') }}" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light"><tr><th>No. GR</th><th>Tgl Terima</th><th>Sumber (Supplier/Cust)</th><th>Info Logistik</th><th>Referensi</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse($receipts as $row)
                @php
                    $isConsignment = $row->receipt_type === 'consignment';
                    $source = $isConsignment ? ($row->customer?->name ?: '-') : ($row->purchaseOrder?->supplier?->name ?: '-');
                    $ref = $isConsignment ? '-' : 'PO: ' . ($row->purchaseOrder?->po_number ?: '-');
                @endphp
                <tr>
                    <td><strong>{{ $row->gr_number }}</strong></td>
                    <td>{{ optional($row->gr_date)->format('d/m/Y') }}</td>
                    <td>@if($isConsignment)<span class="badge bg-info text-dark mb-1">CONSIGNMENT</span><br>{{ $source }}@else<strong>{{ $source }}</strong>@endif</td>
                    <td><small class="d-block text-muted">SJ: {{ $row->delivery_note_number }}</small><small class="d-block text-muted"><i class="bi bi-truck"></i> {{ $row->vehicle_number }} ({{ $row->driver_name }})</small></td>
                    <td>{{ $ref }}</td>
                    <td><span class="badge {{ $badges[$row->status] ?? 'bg-light text-dark' }}">{{ strtoupper(str_replace('_', ' ', $row->status)) }}</span></td>
                    <td>
                        <div class="btn-group">
                            <a href="{{ route('warehouse.receipts.edit', $row) }}" class="btn btn-sm btn-warning text-white"><i class="bi bi-pencil"></i></a>
                            @if($row->status === 'draft')
                                <form method="POST" action="{{ route('warehouse.receipts.destroy', $row) }}" onsubmit="return confirm('Hapus data penerimaan ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-5 text-muted">Data penerimaan belum ada.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
(function(){const form=document.getElementById('gr-filter-form');const search=form?.querySelector('input[name="search"]');const status=form?.querySelector('select[name="status"]');let t;search?.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>form.requestSubmit?form.requestSubmit():form.submit(),400)});status?.addEventListener('change',()=>form.requestSubmit?form.requestSubmit():form.submit());})();
</script>
@endpush
@endsection
