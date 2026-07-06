@extends('layouts.mms')

@section('title', 'Sales Order')

@php
    $badge = [
        'draft' => 'bg-secondary',
        'waiting_approval' => 'bg-warning text-dark',
        'confirmed' => 'bg-success',
        'in_production' => 'bg-primary',
        'delivered' => 'bg-info text-dark',
        'completed' => 'bg-dark',
        'cancelled' => 'bg-danger',
        'rejected' => 'bg-danger',
    ];
@endphp

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-receipt"></i> Sales Order (SO)</h3>
        <p class="text-muted">Daftar Sales Order</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="{{ route('sales.orders.create') }}" class="btn btn-primary shadow-sm"><i class="bi bi-plus-circle"></i> Buat Sales Order</a>
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="so-filter-form">
            <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Cari SO / customer / PO..." value="{{ $search }}" autocomplete="off"></div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    @foreach(['draft','waiting_approval','confirmed','in_production','delivered','completed','cancelled','rejected'] as $opt)
                        <option value="{{ $opt }}" @selected($status === $opt)>{{ strtoupper(str_replace('_', ' ', $opt)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('sales.orders.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>No. SO</th><th>Customer</th><th>PO Customer</th><th>Delivery</th><th class="text-end">Total</th><th>Status</th><th class="text-center" width="260">Aksi</th></tr></thead>
                <tbody>
                @forelse($salesOrders as $row)
                    <tr>
                        <td><strong class="text-primary">{{ $row->so_number }}</strong><br><small class="text-muted">{{ optional($row->so_date)->format('d/m/Y') }} - {{ $row->items_count }} item</small></td>
                        <td>{{ $row->customer?->name ?? '-' }}</td>
                        <td>{{ $row->cust_po_number ?: '-' }}</td>
                        <td>{{ optional($row->delivery_date)->format('d/m/Y') ?: '-' }}</td>
                        <td class="text-end fw-bold">Rp {{ number_format((float) $row->grand_total, 0, ',', '.') }}</td>
                        <td><span class="badge {{ $badge[$row->status] ?? 'bg-secondary' }}">{{ strtoupper(str_replace('_', ' ', $row->status)) }}</span></td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm flex-wrap">
                                <a href="{{ route('sales.orders.print', $row) }}" target="_blank" class="btn btn-outline-dark"><i class="bi bi-printer"></i></a>
                                @if(in_array($row->status, ['draft','rejected'], true))
                                    <a href="{{ route('sales.orders.edit', $row) }}" class="btn btn-warning text-dark"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" action="{{ route('sales.orders.workflow', [$row, 'submit']) }}" onsubmit="return confirm('Ajukan SO ini ke Manager?')">@csrf<button class="btn btn-success"><i class="bi bi-send"></i></button></form>
                                    <form method="POST" action="{{ route('sales.orders.destroy', $row) }}" onsubmit="return confirm('Hapus Sales Order ini?')">@csrf @method('DELETE')<button class="btn btn-danger"><i class="bi bi-trash"></i></button></form>
                                @endif
                                @if($row->status === 'waiting_approval')
                                    <form method="POST" action="{{ route('sales.orders.workflow', [$row, 'approve']) }}">@csrf<button class="btn btn-success"><i class="bi bi-check-lg"></i></button></form>
                                    <form method="POST" action="{{ route('sales.orders.workflow', [$row, 'reject']) }}">@csrf<button class="btn btn-danger"><i class="bi bi-x-lg"></i></button></form>
                                @endif
                                @if(in_array($row->status, ['draft','waiting_approval','confirmed'], true))
                                    <form method="POST" action="{{ route('sales.orders.workflow', [$row, 'cancel']) }}" onsubmit="return confirm('Batalkan SO?')">@csrf<button class="btn btn-outline-danger"><i class="bi bi-slash-circle"></i></button></form>
                                @endif
                                @if(in_array($row->status, ['confirmed','in_production','delivered','completed'], true))
                                    <form method="POST" action="{{ route('sales.orders.workflow', [$row, 'mark_sent']) }}" onsubmit="return confirm('Kirim SO ke client untuk TTD?')">@csrf<button class="btn btn-primary"><i class="bi bi-whatsapp"></i></button></form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada data Sales Order.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const form = document.getElementById('so-filter-form');
    const search = form?.querySelector('input[name="search"]');
    const status = form?.querySelector('select[name="status"]');
    let t;
    search?.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => form.requestSubmit ? form.requestSubmit() : form.submit(), 400); });
    status?.addEventListener('change', () => form.requestSubmit ? form.requestSubmit() : form.submit());
})();
</script>
@endpush
@endsection
