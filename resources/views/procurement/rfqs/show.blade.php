@extends('layouts.mms')

@section('title', 'Detail RFQ')

@section('content')
@include('partials.alerts')
@php
    $badges = ['draft'=>'bg-secondary','sent'=>'bg-info text-dark','evaluated'=>'bg-primary','closed'=>'bg-success','cancelled'=>'bg-danger'];
@endphp
<div class="card shadow-sm mb-3">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <strong>{{ $rfq->rfq_number }}</strong>
        <div>
            <a href="{{ route('procurement.rfqs.print', $rfq) }}" target="_blank" class="btn btn-sm btn-outline-dark me-1"><i class="bi bi-printer"></i> Print</a>
            @if(auth()->user()?->hasPermission('purch_po_manage'))
                <a href="{{ route('procurement.rfqs.edit', $rfq) }}" class="btn btn-sm btn-warning text-dark me-1"><i class="bi bi-pencil"></i> Edit</a>
            @endif
            <a href="{{ route('procurement.rfqs.index') }}" class="btn btn-sm btn-secondary">Kembali</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><small class="text-muted">Tanggal</small><div class="fw-bold">{{ optional($rfq->rfq_date)->format('d/m/Y') }}</div></div>
            <div class="col-md-3"><small class="text-muted">Due</small><div class="fw-bold">{{ optional($rfq->due_date)->format('d/m/Y') ?: '-' }}</div></div>
            <div class="col-md-3"><small class="text-muted">Status</small><div><span class="badge {{ $badges[$rfq->status] ?? 'bg-light text-dark' }}">{{ strtoupper($rfq->status) }}</span></div></div>
            <div class="col-md-3"><small class="text-muted">Created By</small><div class="fw-bold">{{ $rfq->creator?->fullname ?: '-' }}</div></div>
        </div>
        @if($rfq->notes)<div class="mt-2"><small class="text-muted">Catatan</small><div>{{ $rfq->notes }}</div></div>@endif
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body p-0 table-responsive">
        <table class="table table-bordered table-sm mb-0">
            <thead class="table-light"><tr><th>Item</th><th class="text-end">Qty</th><th>Unit</th><th>Vendor</th><th class="text-end">Harga</th><th class="text-end">Lead</th><th class="text-end">Subtotal</th><th>Flag</th></tr></thead>
            <tbody>
            @forelse($rfq->quotes->sortBy([['item_name','asc'],['unit_price','asc']]) as $line)
                @php
                    $key = trim((string) $line->item_name).'|'.trim((string) $line->unit);
                    $isBest = (float) $line->unit_price <= (float) ($bestPrices[$key] ?? 0);
                    $subtotal = (float) $line->qty * (float) $line->unit_price;
                @endphp
                <tr>
                    <td>{{ $line->item_name }}</td>
                    <td class="text-end">{{ number_format((float) $line->qty, 4, ',', '.') }}</td>
                    <td>{{ $line->unit }}</td>
                    <td>{{ $line->supplier?->code }} - {{ $line->supplier?->name }}</td>
                    <td class="text-end">{{ number_format((float) $line->unit_price, 2, ',', '.') }}</td>
                    <td class="text-end">{{ $line->lead_time_days !== null ? $line->lead_time_days.' hari' : '-' }}</td>
                    <td class="text-end fw-bold">{{ number_format($subtotal, 2, ',', '.') }}</td>
                    <td>{!! $isBest ? '<span class="badge bg-success">Best Price</span>' : '-' !!}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-3">Belum ada data quote.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light fw-bold">Ringkasan Vendor & Konversi ke PO</div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light"><tr><th>Vendor</th><th class="text-center">Baris Quote</th><th class="text-center">Best Price Hit</th><th class="text-end">Estimasi Nilai</th><th class="text-center">Aksi</th></tr></thead>
            <tbody>
            @forelse($vendorSummary as $summary)
                <tr>
                    <td>{{ $summary['supplier_code'] }} - {{ $summary['supplier_name'] }}</td>
                    <td class="text-center">{{ $summary['line_count'] }}</td>
                    <td class="text-center">{{ $summary['best_count'] }}</td>
                    <td class="text-end fw-bold">{{ number_format((float) $summary['total'], 2, ',', '.') }}</td>
                    <td class="text-center">
                        @if(auth()->user()?->hasPermission('purch_po_manage') && ! in_array($rfq->status, ['closed', 'cancelled'], true))
                            <form method="POST" action="{{ route('procurement.rfqs.convert_po', $rfq) }}" onsubmit="return confirm('Buat Draft PO dari RFQ ini untuk vendor {{ $summary['supplier_name'] }}?')">
                                @csrf
                                <input type="hidden" name="supplier_id" value="{{ $summary['supplier_id'] }}">
                                <button class="btn btn-sm btn-success"><i class="bi bi-arrow-repeat"></i> Convert ke Draft PO</button>
                            </form>
                        @else
                            <span class="text-muted small">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-3">Belum ada ringkasan vendor.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
