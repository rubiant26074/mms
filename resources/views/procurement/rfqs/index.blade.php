@extends('layouts.mms')

@section('title', 'RFQ')

@section('content')
@include('partials.alerts')
@php
    $badges = ['draft'=>'bg-secondary','sent'=>'bg-info text-dark','evaluated'=>'bg-primary','closed'=>'bg-success','cancelled'=>'bg-danger'];
@endphp
<div class="row mb-3">
    <div class="col-md-8">
        <h3 class="fw-bold"><i class="bi bi-clipboard2-data"></i> RFQ (Request for Quotation)</h3>
        <p class="text-muted mb-0">Pembandingan harga vendor sebelum PO.</p>
    </div>
    <div class="col-md-4 text-md-end mt-2 mt-md-0">
        @if(auth()->user()?->hasPermission('purch_po_manage'))
            <a href="{{ route('procurement.rfqs.create') }}" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Buat RFQ</a>
        @endif
    </div>
</div>

<div class="card shadow-sm mb-3 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="rfq-filter-form">
            <div class="col-md-4"><input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Cari nomor RFQ..."></div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    @foreach(['draft','sent','evaluated','closed','cancelled'] as $key)
                        <option value="{{ $key }}" @selected($status === $key)>{{ strtoupper($key) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('procurement.rfqs.index') }}" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>No RFQ</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                    <th class="text-center">Vendor</th>
                    <th class="text-center">Baris</th>
                    <th class="text-end">Estimasi</th>
                    <th class="text-center" width="190">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rfqs as $rfq)
                <tr>
                    <td><strong>{{ $rfq->rfq_number }}</strong></td>
                    <td>{{ optional($rfq->rfq_date)->format('d/m/Y') }}</td>
                    <td><span class="badge {{ $badges[$rfq->status] ?? 'bg-light text-dark' }}">{{ strtoupper($rfq->status) }}</span></td>
                    <td class="text-center">{{ (int) $rfq->vendor_count }}</td>
                    <td class="text-center">{{ (int) $rfq->line_count }}</td>
                    <td class="text-end fw-bold">Rp {{ number_format((float) $rfq->est_total, 0, ',', '.') }}</td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a href="{{ route('procurement.rfqs.show', $rfq) }}" class="btn btn-sm btn-outline-primary" title="Detail"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('procurement.rfqs.print', $rfq) }}" target="_blank" class="btn btn-sm btn-outline-dark" title="Print"><i class="bi bi-printer"></i></a>
                            @if(auth()->user()?->hasPermission('purch_po_manage'))
                                <a href="{{ route('procurement.rfqs.edit', $rfq) }}" class="btn btn-sm btn-warning text-dark" title="Edit"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('procurement.rfqs.destroy', $rfq) }}" onsubmit="return confirm('Hapus RFQ ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada RFQ.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
(function(){const form=document.getElementById('rfq-filter-form');const search=form?.querySelector('input[name="search"]');const status=form?.querySelector('select[name="status"]');let t;search?.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>form.requestSubmit?form.requestSubmit():form.submit(),450)});status?.addEventListener('change',()=>form.requestSubmit?form.requestSubmit():form.submit());})();
</script>
@endpush
@endsection
