@extends('layouts.mms')

@section('title', 'SPK Produksi')

@php
    $badges = [
        'draft' => 'bg-secondary',
        'waiting_eng' => 'bg-warning text-dark',
        'preliminary' => 'bg-warning text-dark',
        'waiting_mgr' => 'bg-info',
        'final' => 'bg-info',
        'released' => 'bg-primary',
        'in_production' => 'bg-success',
        'completed' => 'bg-secondary',
        'closed' => 'bg-dark',
        'rejected' => 'bg-danger',
    ];
@endphp

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-clipboard-data"></i> Surat Perintah Kerja (SPK)</h3>
        <p class="text-muted">Workflow: PPIC -> Engineering -> Prod Manager -> Prod Supervisor</p>
    </div>
    <div class="col-md-6 text-end"><a href="{{ route('ppic.spk.create') }}" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Buat SPK Baru</a></div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="spk-filter-form">
            <div class="col-md-4"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="text" name="search" class="form-control" placeholder="Cari No SPK / Customer / SO..." value="{{ $search }}" autocomplete="off"></div></div>
            <div class="col-md-3"><select name="status" class="form-select"><option value="">- Semua Status -</option>@foreach(['draft','waiting_eng','preliminary','waiting_mgr','final','released','in_production','completed','closed'] as $opt)<option value="{{ $opt }}" @selected($status === $opt)>{{ strtoupper(str_replace('_', ' ', $opt)) }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('ppic.spk.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light"><tr><th>No. SPK</th><th>Target</th><th>Customer / Project</th><th>Status</th><th class="text-center" width="320">Aksi</th></tr></thead>
            <tbody>
            @forelse($spks as $row)
                <tr>
                    <td><strong>{{ $row->spk_number }}</strong></td>
                    <td>{{ optional($row->deadline_date)->format('d M Y') }}</td>
                    <td><div class="fw-bold">{{ $row->salesOrder?->customer?->name ?: '-' }}</div><small class="text-muted">Ref SO: {{ $row->salesOrder?->so_number ?: '-' }}</small></td>
                    <td><span class="badge {{ $badges[$row->status] ?? 'bg-secondary' }}">{{ strtoupper($row->status) }}</span></td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm flex-wrap">
                            <a href="{{ route('ppic.spk.print', $row) }}" target="_blank" class="btn btn-outline-dark"><i class="bi bi-printer"></i></a>
                            @if(in_array($row->status, ['draft','preliminary'], true))
                                <a href="{{ route('ppic.spk.edit', $row) }}" class="btn btn-warning text-dark"><i class="bi bi-pencil"></i></a>
                            @endif
                            @if($row->status === 'draft')
                                <form method="POST" action="{{ route('ppic.spk.workflow', [$row, 'submit']) }}">@csrf<button class="btn btn-outline-success"><i class="bi bi-send"></i></button></form>
                            @endif
                            @if(in_array($row->status, ['waiting_mgr','final'], true) && in_array(auth()->user()?->role?->role_slug, ['manager','admin'], true))
                                <form method="POST" action="{{ route('ppic.spk.workflow', [$row, 'approve_mgr']) }}" onsubmit="return confirm('Rilis SPK ke produksi?')">@csrf<button class="btn btn-success fw-bold">RILIS MANAGER</button></form>
                            @endif
                            @if($row->status === 'released' && in_array(auth()->user()?->role?->role_slug, ['supervisor','admin'], true))
                                <form method="POST" action="{{ route('ppic.spk.workflow', [$row, 'receive_spv']) }}">@csrf<button class="btn btn-warning fw-bold">TERIMA WS</button></form>
                            @endif
                            @if(auth()->user()?->role?->role_slug === 'admin')
                                <form method="POST" action="{{ route('ppic.spk.destroy', $row) }}" onsubmit="return confirm('Hapus SPK {{ $row->spk_number }}?')">@csrf @method('DELETE')<button class="btn btn-danger"><i class="bi bi-trash"></i></button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada data SPK.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const form = document.getElementById('spk-filter-form');
    const search = form?.querySelector('input[name="search"]');
    const status = form?.querySelector('select[name="status"]');
    let t;
    search?.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => form.requestSubmit ? form.requestSubmit() : form.submit(), 400); });
    status?.addEventListener('change', () => form.requestSubmit ? form.requestSubmit() : form.submit());
})();
</script>
@endpush
@endsection
