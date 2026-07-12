@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Barang' : 'Tambah Barang')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white"><h5 class="mb-0">{{ $isEdit ? 'Edit Barang' : 'Tambah Barang Baru' }}</h5></div>
            <div class="card-body">
                @include('partials.alerts')
                <form method="POST" enctype="multipart/form-data" action="{{ $isEdit ? route('warehouse.items.update', $item) : route('warehouse.items.store') }}">
                    @csrf
                    @if($isEdit) @method('PUT') @endif
                    <div class="row bg-light p-3 rounded mb-3 border">
                        <div class="col-12 mb-2 fw-bold text-primary">Klasifikasi Barang</div>
                        <div class="col-md-6 mb-3"><label>Kepemilikan <span class="text-danger">*</span></label><select name="ownership" id="ownership" class="form-select"><option value="internal" @selected(old('ownership', $item->ownership) === 'internal')>Internal (Milik Kita)</option><option value="customer" @selected(old('ownership', $item->ownership) === 'customer')>Consignment (Milik Customer)</option></select></div>
                        <div class="col-md-6 mb-3"><label>Customer</label><select name="customer_id" id="customerId" class="form-select"><option value="">-- Pilih Customer --</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((int) old('customer_id', $item->customer_id) === $customer->id)>{{ $customer->name }}{{ $customer->customer_code ? " ({$customer->customer_code})" : '' }}</option>@endforeach</select><div class="form-text">Pilih customer untuk generate kode barang otomatis.</div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label>Kode Barang <span class="text-danger">*</span></label><input type="text" name="item_code" id="itemCode" class="form-control fw-bold bg-light" value="{{ old('item_code', $item->item_code) }}" {{ $isEdit ? '' : 'readonly' }} required placeholder="Otomatis (Pilih Customer)"></div>
                        <div class="col-md-6 mb-3"><label>Nama Barang <span class="text-danger">*</span></label><input type="text" name="item_name" class="form-control" value="{{ old('item_name', $item->item_name) }}" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label>Tipe Barang</label><select name="item_type" id="itemType" class="form-select"><option value="finish_good" @selected(old('item_type', $item->item_type) === 'finish_good')>Finish Good</option><option value="wip" @selected(old('item_type', $item->item_type) === 'wip')>Work In Progress</option><option value="raw_material" @selected(old('item_type', $item->item_type) === 'raw_material')>Raw Material</option><option value="consumable" @selected(old('item_type', $item->item_type) === 'consumable')>Consumable</option></select></div>
                        <div class="col-md-6 mb-3"><label class="text-primary fw-bold">Standar QC</label><select name="qc_type" class="form-select border-primary fw-bold"><option value="general" @selected(old('qc_type', $item->qc_type) === 'general')>General (Umum)</option><option value="sheet_metal" @selected(old('qc_type', $item->qc_type) === 'sheet_metal')>Sheet Metal Process (Laser/Bend/Weld)</option><option value="plate" @selected(old('qc_type', $item->qc_type) === 'plate')>Plate / Sheet</option><option value="coating" @selected(old('qc_type', $item->qc_type) === 'coating')>Coating / Paint</option><option value="machining" @selected(old('qc_type', $item->qc_type) === 'machining')>Machining / Bubut</option><option value="consumable" @selected(old('qc_type', $item->qc_type) === 'consumable')>Consumable</option></select></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label>Satuan</label><input type="text" name="unit" class="form-control" value="{{ old('unit', $item->unit) }}" required placeholder="Pcs, Kg, Set"></div>
                        <div class="col-md-4 mb-3"><label>Min. Stok</label><input type="number" name="min_stock" class="form-control" value="{{ old('min_stock', $item->min_stock ?: 0) }}"></div>
                        @if($canSeePrice)<div class="col-md-4 mb-3"><label class="text-success fw-bold">Harga Dasar (Rp)</label><input type="number" name="base_price" class="form-control border-success fw-bold text-end" min="0" step="0.01" value="{{ old('base_price', $item->base_price ?: 0) }}"></div>@endif
                    </div>
                    <div class="mb-3"><label class="fw-bold text-danger"><i class="bi bi-file-earmark-pdf"></i> Upload Drawing (PDF/IMG)</label><input type="file" name="drawing_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png"><div class="form-text small">Max 5MB.</div>@if($item->drawing_file)<div class="mt-2"><a href="{{ asset($item->drawing_file) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Lihat Drawing Saat Ini</a></div>@endif</div>
                    <div class="mb-3"><label>Deskripsi / Spesifikasi</label><textarea name="description" class="form-control" rows="3">{{ old('description', $item->description) }}</textarea></div>
                    <div class="d-flex justify-content-between mt-4"><a href="{{ route('warehouse.items.index') }}" class="btn btn-secondary">Batal</a><button class="btn btn-primary px-5">Simpan Data</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const isEdit = @json($isEdit);
    const code = document.getElementById('itemCode');
    const customer = document.getElementById('customerId');
    const ownership = document.getElementById('ownership');
    const type = document.getElementById('itemType');
    function generateCode() {
        if (isEdit) return;
        if (!customer.value && ownership.value === 'customer' && type.value !== 'consumable') { code.value = ''; return; }
        const url = new URL('{{ route('warehouse.items.generate_code') }}', window.location.origin);
        url.searchParams.set('customer_id', customer.value);
        url.searchParams.set('type', ownership.value);
        url.searchParams.set('item_type', type.value);
        fetch(url).then(r => r.json()).then(data => { code.value = data.status === 'success' ? data.code : 'ERROR'; }).catch(() => code.value = 'CONN ERR');
    }
    customer?.addEventListener('change', generateCode);
    ownership?.addEventListener('change', generateCode);
    type?.addEventListener('change', generateCode);
    generateCode();
})();
</script>
@endpush
@endsection
