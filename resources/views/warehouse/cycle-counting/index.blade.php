@extends('layouts.mms')

@section('title', 'Cycle Counting')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-clipboard2-check"></i> Cycle Counting</h3>
        <p class="text-muted mb-0">Stock opname parsial periodik untuk validasi stok sistem vs stok fisik.</p>
    </div>
    <div class="text-md-end"><a href="{{ route('warehouse.cycle_counting.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Buat Session Count</a></div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4"><input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Cari nomor session / area"></div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="draft" @selected($status === 'draft')>Draft</option>
                    <option value="posted" @selected($status === 'posted')>Posted</option>
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-2"><a href="{{ route('warehouse.cycle_counting.index') }}" class="btn btn-outline-secondary w-100">Reset</a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>No Session</th><th>Tanggal</th><th>Area</th><th class="text-center">Item Counted</th><th class="text-end">Total Variance</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse($sessions as $session)
                    @php($badge = $session->status === 'posted' ? 'bg-success' : 'bg-warning text-dark')
                    <tr>
                        <td><strong>{{ $session->session_number }}</strong><br><small class="text-muted">{{ $session->creator?->fullname ?: '-' }}</small></td>
                        <td>{{ optional($session->count_date)->format('d/m/Y') }}</td>
                        <td>{{ $session->count_area ?: '-' }}</td>
                        <td class="text-center">{{ $session->items_count }}</td>
                        <td class="text-end fw-bold">{{ number_format((float) $session->items_sum_variance_qty, 4, ',', '.') }}</td>
                        <td><span class="badge {{ $badge }}">{{ strtoupper($session->status) }}</span></td>
                        <td>
                            <a href="{{ route('warehouse.cycle_counting.show', $session) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> View</a>
                            <a href="{{ route('warehouse.cycle_counting.print', $session) }}" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer"></i> Print</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada session cycle count.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
