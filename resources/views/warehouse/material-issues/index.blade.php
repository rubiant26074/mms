@extends('layouts.mms')

@section('title', 'Material Issue (ITR)')

@section('content')
@include('partials.alerts')
@php($badges = ['request'=>'bg-warning text-dark','approved'=>'bg-success','rejected'=>'bg-danger'])
<div class="row mb-3">
    <div class="col-md-6"><h3 class="fw-bold"><i class="bi bi-box-arrow-right"></i> Material Issue (ITR)</h3><p class="text-muted">Request Produksi & Transfer Gudang.</p></div>
    <div class="col-md-6 text-md-end"><a href="{{ route('warehouse.material_issues.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Ajukan ITR Baru</a></div>
</div>
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="itr-filter-form">
            <div class="col-md-4"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="text" name="search" class="form-control" placeholder="Cari No. ITR / SPK / Pemohon..." value="{{ $search }}"></div></div>
            <div class="col-md-3"><select name="status" class="form-select"><option value="">- Semua Status -</option>@foreach(['request','approved','rejected'] as $key)<option value="{{ $key }}" @selected($status === $key)>{{ strtoupper($key) }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('warehouse.material_issues.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>
<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light"><tr><th>No. ITR</th><th>Tgl Request</th><th>Ref. SPK</th><th>Pemohon</th><th>Petugas Gudang</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse($issues as $issue)
                <tr>
                    <td><strong>{{ $issue->itr_number }}</strong></td>
                    <td>{{ optional($issue->itr_date)->format('d/m/Y') }}</td>
                    <td><span class="badge bg-light text-dark border">{{ $issue->spk?->spk_number ?: '-' }}</span></td>
                    <td>{{ $issue->received_by ?: '-' }}</td>
                    <td>{{ $issue->issued_by ?: '-' }}</td>
                    <td><span class="badge {{ $badges[$issue->status] ?? 'bg-secondary' }}">{{ strtoupper($issue->status) }}</span></td>
                    <td>
                        <div class="btn-group">
                            <a href="{{ route('warehouse.material_issues.print', $issue) }}" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer"></i></a>
                            @if($issue->status === 'request')
                                <form method="POST" action="{{ route('warehouse.material_issues.approve', $issue) }}" onsubmit="return confirm('Setujui permintaan dan potong stok fisik?')">@csrf<button class="btn btn-sm btn-success fw-bold"><i class="bi bi-check-lg"></i> Transfer</button></form>
                                <form method="POST" action="{{ route('warehouse.material_issues.destroy', $issue) }}" onsubmit="return confirm('Batalkan request ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-5">Belum ada data ITR.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@push('scripts')
<script>
(function(){const form=document.getElementById('itr-filter-form');const search=form?.querySelector('input[name="search"]');const status=form?.querySelector('select[name="status"]');let t;search?.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>form.requestSubmit?form.requestSubmit():form.submit(),400)});status?.addEventListener('change',()=>form.requestSubmit?form.requestSubmit():form.submit());})();
</script>
@endpush
@endsection
