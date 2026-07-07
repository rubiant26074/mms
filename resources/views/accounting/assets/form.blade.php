@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Aset' : 'Registrasi Aset Tetap')

@section('content')
<div class="row justify-content-center"><div class="col-md-8"><div class="card shadow"><div class="card-header bg-primary text-white">Form Aset Tetap</div><div class="card-body">@include('partials.alerts')
<form method="POST" action="{{ $isEdit ? route('accounting.assets.update', $asset) : route('accounting.assets.store') }}">@csrf @if($isEdit) @method('PUT') @endif
<div class="row"><div class="col-md-6 mb-3"><label>Nama Aset</label><input type="text" name="asset_name" class="form-control" value="{{ old('asset_name', $asset->asset_name) }}" required></div><div class="col-md-6 mb-3"><label>Kategori</label><select name="category" class="form-select">@foreach(['machinery'=>'Mesin & Peralatan','vehicle'=>'Kendaraan','building'=>'Bangunan','electronic'=>'Elektronik / Komputer','equipment'=>'Inventaris Kantor'] as $value=>$label)<option value="{{ $value }}" @selected(old('category', $asset->category) === $value)>{{ $label }}</option>@endforeach</select></div></div>
<div class="row"><div class="col-md-6 mb-3"><label>Tanggal Perolehan</label><input type="date" name="acquisition_date" class="form-control" value="{{ old('acquisition_date', optional($asset->acquisition_date)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" @readonly($isEdit) required></div><div class="col-md-6 mb-3"><label>Harga Perolehan (Rp)</label><input type="text" name="acquisition_cost" class="form-control fw-bold" value="{{ old('acquisition_cost', number_format((float)$asset->acquisition_cost,0,',','.')) }}" onkeyup="formatRibuan(this)" @readonly($isEdit) required></div></div>
<div class="row bg-light p-3 rounded border mb-3"><div class="col-12 mb-2 fw-bold text-primary">Parameter Penyusutan (Garis Lurus)</div><div class="col-md-6 mb-3"><label>Umur Ekonomis (Tahun)</label><input type="number" name="useful_life_years" class="form-control" value="{{ old('useful_life_years', $asset->useful_life_years) }}" @readonly($isEdit) required></div><div class="col-md-6 mb-3"><label>Nilai Sisa / Residu (Rp)</label><input type="text" name="salvage_value" class="form-control" value="{{ old('salvage_value', number_format((float)$asset->salvage_value,0,',','.')) }}" onkeyup="formatRibuan(this)" @readonly($isEdit)><div class="form-text">Nilai aset di akhir umur ekonomis.</div></div></div>
<div class="mb-3"><label>Catatan</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', $asset->notes) }}</textarea></div>
<div class="text-end"><a href="{{ route('accounting.assets.index') }}" class="btn btn-secondary me-2">Batal</a><button class="btn btn-primary">Simpan Aset</button></div>
</form></div></div></div></div>
@endsection

@push('scripts')
<script>function formatRibuan(input){let value=input.value.replace(/[^0-9]/g,'');input.value=new Intl.NumberFormat('id-ID').format(value);}</script>
@endpush
