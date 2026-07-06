@extends('layouts.mms')

@section('title', 'Purchase Requests')

@php
    $badges = ['draft' => 'bg-secondary', 'submitted' => 'bg-warning text-dark', 'approved' => 'bg-success', 'partial' => 'bg-info text-dark', 'processed' => 'bg-dark', 'rejected' => 'bg-danger'];
@endphp

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-cart-check"></i> Purchase Requests (PR)</h3>
        <p class="text-muted">Manajemen permintaan pembelian material produksi.</p>
    </div>
    <div class="col-md-6 text-end"><a href="{{ route('ppic.purchase_requests.create') }}" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Buat PR Baru</a></div>
</div>
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="pr-filter-form">
            <div class="col-md-4"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="text" name="search" class="form-control" placeholder="Cari No. PR / Keperluan / Pembuat..." value="{{ $search }}" autocomplete="off"></div></div>
            <div class="col-md-3"><select name="status" class="form-select"><option value="">- Semua Status -</option>@foreach(['draft','submitted','approved','partial','processed','rejected'] as $opt)<option value="{{ $opt }}" @selected($status === $opt)>{{ ucwords(str_replace('_',' ',$opt)) }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('ppic.purchase_requests.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>
<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light"><tr><th>No. PR</th><th>Tanggal</th><th>Tujuan / Keperluan</th><th>Status</th><th class="text-center" width="240">Aksi</th></tr></thead>
            <tbody>
            @forelse($prs as $row)
                <tr>
                    <td><strong>{{ $row->pr_number }}</strong></td>
                    <td>{{ optional($row->pr_date)->format('d/m/Y') ?: '-' }}</td>
                    <td><div class="fw-bold text-dark">{{ $row->notes ?: '-' }}</div><small class="text-muted fst-italic">Oleh: {{ $row->creator?->fullname ?: 'System' }}</small></td>
                    <td><span class="badge {{ $badges[$row->status] ?? 'bg-secondary' }}">{{ ucwords(str_replace('_',' ',$row->status)) }}</span></td>
                    <td class="text-center"><div class="btn-group btn-group-sm">
                        <a href="{{ route('ppic.purchase_requests.print', $row) }}" target="_blank" class="btn btn-outline-dark"><i class="bi bi-printer"></i></a>
                        @if(in_array($row->status, ['draft','rejected'], true))
                            <a href="{{ route('ppic.purchase_requests.edit', $row) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('ppic.purchase_requests.workflow', [$row, 'submit']) }}" onsubmit="return confirm('Ajukan PR ini?')">@csrf<button class="btn btn-outline-success"><i class="bi bi-send"></i></button></form>
                        @endif
                        @if(in_array($row->status, ['draft','rejected'], true) || auth()->user()?->role?->role_slug === 'admin')
                            <form method="POST" action="{{ route('ppic.purchase_requests.destroy', $row) }}" onsubmit="return confirm('Hapus data PR ini secara permanen?')">@csrf @method('DELETE')<button class="btn btn-danger"><i class="bi bi-trash"></i></button></form>
                        @endif
                        @if($row->status === 'submitted' && auth()->user()?->hasPermission('ppic_pr_approve'))
                            <form method="POST" action="{{ route('ppic.purchase_requests.workflow', [$row, 'approve']) }}">@csrf<button class="btn btn-primary">Approve</button></form>
                        @endif
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada data PR.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@push('scripts')
<script>
(function(){const form=document.getElementById('pr-filter-form');const search=form?.querySelector('input[name="search"]');const status=form?.querySelector('select[name="status"]');let t;search?.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>form.requestSubmit?form.requestSubmit():form.submit(),400)});status?.addEventListener('change',()=>form.requestSubmit?form.requestSubmit():form.submit());})();
</script>
@endpush
@endsection
