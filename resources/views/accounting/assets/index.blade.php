@extends('layouts.mms')

@section('title', 'Fixed Asset Management')

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6"><h3 class="fw-bold"><i class="bi bi-building-gear"></i> Aktiva Tetap</h3><p class="text-muted">Manajemen Aset & Penyusutan Otomatis.</p></div>
    <div class="col-md-6 text-end">
        <a href="{{ route('accounting.assets.print', ['status' => $status, 'search' => $search]) }}" target="_blank" class="btn btn-outline-dark me-2"><i class="bi bi-printer"></i> Print</a>
        <form method="POST" action="{{ route('accounting.assets.depreciate') }}" class="d-inline" onsubmit="return confirm('Jalankan proses penyusutan untuk bulan ini?')">@csrf<button class="btn btn-warning me-2"><i class="bi bi-calculator"></i> Run Depresiasi Bulan Ini</button></form>
        <a href="{{ route('accounting.assets.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah Aset</a>
    </div>
</div>
<div class="card shadow-sm mb-4 border-start border-4 border-primary"><div class="card-body py-3"><form method="GET" class="row g-2 align-items-center" id="asset-filter-form"><div class="col-md-4"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="text" name="search" class="form-control" placeholder="Cari Kode / Nama / Kategori..." value="{{ $search }}" autocomplete="off"></div></div><div class="col-md-3"><select name="status" class="form-select"><option value="">- Semua Status -</option>@foreach(['active','sold','disposed'] as $opt)<option value="{{ $opt }}" @selected($status === $opt)>{{ ucfirst($opt) }}</option>@endforeach</select></div><div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div><div class="col-md-1"><a href="{{ route('accounting.assets.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a></div></form></div></div>
<div class="card shadow-sm"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Kode</th><th>Nama Aset</th><th>Kategori</th><th>Tgl Beli</th><th class="text-end">Harga Perolehan</th><th class="text-end">Nilai Buku</th><th class="text-end">Susut/Bulan</th><th class="text-center">Aksi</th></tr></thead><tbody>
@forelse($assets as $asset)
@php $base = max(1, (float)$asset->acquisition_cost - (float)$asset->salvage_value); $progress = round(((float)$asset->accumulated_depreciation / $base) * 100); @endphp
<tr><td class="fw-bold">{{ $asset->asset_code }}</td><td>{{ $asset->asset_name }}</td><td><span class="badge bg-secondary">{{ strtoupper($asset->category) }}</span></td><td>{{ optional($asset->acquisition_date)->format('d/m/Y') }}</td><td class="text-end">Rp {{ number_format((float)$asset->acquisition_cost, 0, ',', '.') }}</td><td class="text-end fw-bold text-primary">Rp {{ number_format((float)$asset->book_value, 0, ',', '.') }}<div class="progress mt-1" style="height:3px;"><div class="progress-bar bg-info" style="width: {{ min(100, $progress) }}%"></div></div></td><td class="text-end text-muted small">Rp {{ number_format((float)$asset->monthly_depreciation, 0, ',', '.') }}</td><td class="text-center"><a href="{{ route('accounting.assets.edit', $asset) }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a><form method="POST" action="{{ route('accounting.assets.destroy', $asset) }}" class="d-inline" onsubmit="return confirm('Hapus aset?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button></form></td></tr>
@empty
<tr><td colspan="8" class="text-center py-5 text-muted">Belum ada data aset.</td></tr>
@endforelse
</tbody></table></div></div></div>
@endsection

@push('scripts')
<script>
(function(){const form=document.getElementById('asset-filter-form');if(!form)return;const submit=()=>form.requestSubmit?form.requestSubmit():form.submit();let t;form.querySelector('input[name="search"]')?.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(submit,400)});form.querySelector('select[name="status"]')?.addEventListener('change',submit);})();
</script>
@endpush
