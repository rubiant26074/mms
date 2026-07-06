@extends('layouts.mms')

@section('title', 'Engineering Part List')

@php
    $badges = ['preliminary' => 'bg-warning text-dark', 'waiting_eng' => 'bg-info text-dark', 'waiting_mgr' => 'bg-primary', 'final' => 'bg-success', 'released' => 'bg-success', 'in_production' => 'bg-dark', 'completed' => 'bg-secondary', 'closed' => 'bg-secondary'];
    $statusOptions = $view === 'archive'
        ? ['closed' => 'Closed']
        : ['preliminary' => 'Preliminary', 'waiting_eng' => 'Waiting Eng', 'waiting_mgr' => 'Waiting Mgr', 'final' => 'Final', 'released' => 'Released', 'in_production' => 'In Production', 'completed' => 'Completed'];
@endphp

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-list-check"></i> Engineering Part List</h3>
        <p class="text-muted">Pembuatan partlist drawing berdasarkan SPK.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="{{ route('engineering.partlists.index', ['view' => 'active']) }}" class="btn btn-sm {{ $view === 'active' ? 'btn-primary' : 'btn-outline-primary' }}">Active</a>
        <a href="{{ route('engineering.partlists.index', ['view' => 'archive']) }}" class="btn btn-sm {{ $view === 'archive' ? 'btn-primary' : 'btn-outline-primary' }}">Archive</a>
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="partlist-filter-form">
            <input type="hidden" name="view" value="{{ $view }}">
            <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Cari SPK / SO..." value="{{ $search }}" autocomplete="off"></div>
            <div class="col-md-3"><select name="status" class="form-select"><option value="">Semua Status</option>@foreach($statusOptions as $val => $label)<option value="{{ $val }}" @selected($status === $val)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('engineering.partlists.index', ['view' => $view]) }}" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-4">SPK</th><th>Sales Order</th><th>Project</th><th class="text-center">Part</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
            <tbody>
            @forelse($spks as $spk)
                <tr>
                    <td class="ps-4 fw-bold">{{ $spk->spk_number }}<br><small class="text-muted">{{ optional($spk->spk_date)->format('d/m/Y') }}</small></td>
                    <td><strong class="text-primary">{{ $spk->salesOrder?->so_number ?: '-' }}</strong></td>
                    <td>{{ $spk->project_name ?: '-' }}</td>
                    <td class="text-center"><span class="badge bg-info text-dark">{{ $spk->partlists_count }} part</span></td>
                    <td><span class="badge {{ $badges[$spk->status] ?? 'bg-secondary' }}">{{ strtoupper($spk->status) }}</span></td>
                    <td class="text-center">
                        @if($spk->status === 'closed')
                            <a href="{{ route('engineering.partlists.print', $spk) }}" target="_blank" class="btn btn-sm btn-secondary"><i class="bi bi-printer"></i> Lihat Arsip</a>
                        @elseif($spk->status === 'waiting_mgr')
                            <form method="POST" action="{{ route('engineering.partlists.approve', $spk) }}" onsubmit="return confirm('Approve Partlist ini menjadi FINAL?')" class="d-inline">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Approve</button></form>
                        @else
                            <a href="{{ route('engineering.partlists.create', ['spk_id' => $spk->id]) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square"></i> Part List</a>
                            <a href="{{ route('engineering.partlists.print', $spk) }}" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer"></i></a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-5 text-muted">{{ $view === 'archive' ? 'Belum ada data arsip partlist.' : 'Belum ada data partlist aktif.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const form = document.getElementById('partlist-filter-form');
    const search = form?.querySelector('input[name="search"]');
    const status = form?.querySelector('select[name="status"]');
    let t;
    search?.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => form.requestSubmit ? form.requestSubmit() : form.submit(), 400); });
    status?.addEventListener('change', () => form.requestSubmit ? form.requestSubmit() : form.submit());
})();
</script>
@endpush
@endsection
