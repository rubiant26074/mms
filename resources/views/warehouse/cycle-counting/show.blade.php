@extends('layouts.mms')

@section('title', 'Detail Cycle Counting')

@section('content')
<div class="card shadow-sm mb-3">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <strong>Detail Session: {{ $session->session_number }}</strong>
        <div>
            <a href="{{ route('warehouse.cycle_counting.print', $session) }}" target="_blank" class="btn btn-sm btn-outline-dark me-1"><i class="bi bi-printer"></i> Print</a>
            <a href="{{ route('warehouse.cycle_counting.index') }}" class="btn btn-sm btn-secondary">Kembali</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><small class="text-muted">Tanggal</small><div class="fw-bold">{{ optional($session->count_date)->format('d/m/Y') }}</div></div>
            <div class="col-md-3"><small class="text-muted">Area</small><div class="fw-bold">{{ $session->count_area ?: '-' }}</div></div>
            <div class="col-md-2"><small class="text-muted">Status</small><div class="fw-bold">{{ strtoupper($session->status) }}</div></div>
            <div class="col-md-4"><small class="text-muted">Dibuat oleh</small><div class="fw-bold">{{ $session->creator?->fullname ?: '-' }}</div></div>
        </div>
        @if($session->notes)<div class="mt-2"><small class="text-muted">Catatan</small><div>{{ $session->notes }}</div></div>@endif
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead class="table-light"><tr><th>Item</th><th class="text-end">System</th><th class="text-end">Counted</th><th class="text-end">Variance</th><th>Reason</th><th>Catatan</th></tr></thead>
                <tbody>
                @forelse($session->items as $line)
                    <tr>
                        <td><strong>{{ $line->item?->item_code }}</strong> - {{ $line->item?->item_name }}</td>
                        <td class="text-end">{{ number_format($line->system_qty, 4, ',', '.') }} {{ $line->item?->unit }}</td>
                        <td class="text-end">{{ number_format($line->counted_qty, 4, ',', '.') }} {{ $line->item?->unit }}</td>
                        <td class="text-end fw-bold {{ $line->variance_qty == 0.0 ? '' : ($line->variance_qty > 0 ? 'text-success' : 'text-danger') }}">{{ number_format($line->variance_qty, 4, ',', '.') }} {{ $line->item?->unit }}</td>
                        <td>{{ $line->reason ?: '-' }}</td>
                        <td>{{ $line->notes ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada item.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($session->status === 'draft')
    <form method="POST" action="{{ route('warehouse.cycle_counting.post', $session) }}" onsubmit="return confirm('Post hasil cycle count ini? Stok sistem akan diupdate ke nilai counted.');">
        @csrf
        <button type="submit" class="btn btn-success"><i class="bi bi-check2-square"></i> Post Penyesuaian Stok</button>
    </form>
@endif
@endsection
