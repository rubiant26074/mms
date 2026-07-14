@extends('layouts.mms')

@section('title', 'Sales Quotation')

@php
    $badge = [
        'draft' => 'bg-secondary',
        'waiting_approval' => 'bg-warning text-dark',
        'approved' => 'bg-primary',
        'sent' => 'bg-info text-dark',
        'won' => 'bg-success',
        'so_created' => 'bg-success',
        'lost' => 'bg-dark',
        'rejected' => 'bg-danger',
        'revised' => 'bg-secondary text-decoration-line-through',
    ];
@endphp

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-file-earmark-text"></i> Sales Quotation</h3>
        <p class="text-muted">Daftar Penawaran Harga</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="{{ route('sales.quotations.create') }}" class="btn btn-primary shadow-sm"><i class="bi bi-plus-circle"></i> Buat Quotation</a>
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="quotation-filter-form">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Cari nomor quotation / customer..." value="{{ $search }}" autocomplete="off">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    @foreach(['draft','waiting_approval','approved','sent','won','so_created','lost','rejected','revised'] as $opt)
                        <option value="{{ $opt }}" @selected($status === $opt)>{{ strtoupper(str_replace('_', ' ', $opt)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('sales.quotations.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>No. Quotation</th>
                        <th>Customer</th>
                        <th>Tanggal</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-center" width="260">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($quotations as $row)
                    @php $statusView = in_array($row->status, ['won','so_created'], true) && $row->active_so_count > 0 ? 'so_created' : $row->status; @endphp
                    <tr>
                        <td><strong>{{ $row->quote_number }}</strong><br><small class="text-muted">{{ $row->items_count }} item</small></td>
                        <td>{{ $row->customer?->name ?? '-' }}</td>
                        <td>{{ optional($row->quote_date)->format('d/m/Y') }}</td>
                        <td class="text-end fw-bold">Rp {{ number_format((float) $row->grand_total, 0, ',', '.') }}</td>
                        <td><span class="badge {{ $badge[$statusView] ?? 'bg-secondary' }}">{{ strtoupper(str_replace('_', ' ', $statusView)) }}</span></td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm flex-wrap">
                                <a href="{{ route('sales.quotations.print', $row) }}" target="_blank" class="btn btn-outline-dark" title="Cetak"><i class="bi bi-printer"></i></a>
                                @if(in_array($row->status, ['draft','rejected'], true))
                                    <a href="{{ route('sales.quotations.edit', $row) }}" class="btn btn-warning text-dark" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" action="{{ route('sales.quotations.workflow', [$row, 'submit']) }}" onsubmit="return confirm('Ajukan?')">@csrf<button class="btn btn-secondary" title="Ajukan"><i class="bi bi-send"></i></button></form>
                                    <form method="POST" action="{{ route('sales.quotations.destroy', $row) }}" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn btn-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>
                                @endif
                                @if($row->status === 'waiting_approval')
                                    <form method="POST" action="{{ route('sales.quotations.workflow', [$row, 'approve']) }}">@csrf<button class="btn btn-success" title="Approve"><i class="bi bi-check-lg"></i></button></form>
                                    <form method="POST" action="{{ route('sales.quotations.workflow', [$row, 'reject']) }}">@csrf<button class="btn btn-danger" title="Reject"><i class="bi bi-x-lg"></i></button></form>
                                @endif
                                @if(in_array($row->status, ['approved', 'sent', 'won'], true))
                                    <form method="POST" action="{{ route('sales.quotations.workflow', [$row, 'mark_sent']) }}" onsubmit="return confirm('Kirim/Kirim ulang quotation ke client via WhatsApp?')">@csrf<button class="btn btn-primary" title="Kirim WA"><i class="bi bi-whatsapp"></i></button></form>
                                @endif
                                @if($row->status === 'sent')
                                    <form method="POST" action="{{ route('sales.quotations.workflow', [$row, 'won']) }}" onsubmit="return confirm('Won?')">@csrf<button class="btn btn-success fw-bold">Won</button></form>
                                    <form method="POST" action="{{ route('sales.quotations.workflow', [$row, 'lost']) }}" onsubmit="return confirm('Lost?')">@csrf<button class="btn btn-dark">Lost</button></form>
                                    <form method="POST" action="{{ route('sales.quotations.workflow', [$row, 'revise']) }}" onsubmit="return confirm('Buat Revisi?')">@csrf<button class="btn btn-warning text-dark"><i class="bi bi-arrow-repeat"></i> Revise</button></form>
                                @endif
                                @if($statusView === 'won')
                                    @if(auth()->user()?->hasPermission('sales_so_manage'))
                                        <a href="{{ route('sales.orders.create', ['quote_id' => $row->id]) }}" class="btn btn-success fw-bold" title="Buat Sales Order"><i class="bi bi-cart-plus"></i> Buat SO</a>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada data quotation.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const form = document.getElementById('quotation-filter-form');
    const search = form?.querySelector('input[name="search"]');
    const status = form?.querySelector('select[name="status"]');
    let t;
    search?.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => form.requestSubmit ? form.requestSubmit() : form.submit(), 400); });
    status?.addEventListener('change', () => form.requestSubmit ? form.requestSubmit() : form.submit());
})();
</script>
@endpush
@endsection
