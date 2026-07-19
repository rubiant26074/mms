@extends('layouts.mms')

@section('title', $isEdit ? 'Edit BOM' : 'Buat BOM')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="bi bi-diagram-3"></i> {{ $isEdit ? 'Edit BOM' : 'Buat BOM Baru' }}</h5></div>
    <div class="card-body">
        @include('partials.alerts')
        @if(!$isEdit && !empty($salesOrders))
        <div class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center justify-content-between">
            <div>
                <i class="bi bi-info-circle me-1"></i> <strong>Tarik dari Sales Order:</strong> Pilih Sales Order untuk mempermudah pemilihan Item Finish Good yang siap dibuatkan BOM.
            </div>
            <div style="min-width: 320px;">
                <select id="so_pull_picker" class="form-select form-select-sm bg-white fw-semibold">
                    <option value="">-- Pilih dari SO Approved --</option>
                    @foreach($salesOrders as $so)
                        @foreach($so->items as $soItem)
                            @if($soItem->item_id)
                                <option value="{{ $soItem->item_id }}" @selected($selectedItemId == $soItem->item_id || old('item_id', $bom->item_id) == $soItem->item_id)>
                                    {{ $so->so_number }} | {{ $so->customer?->name }} - {{ $soItem->item?->item_name ?: $soItem->item_name_manual }}
                                </option>
                            @endif
                        @endforeach
                    @endforeach
                </select>
            </div>
        </div>
        @endif
        <form method="POST" action="{{ $isEdit ? route('engineering.boms.update', $bom) : route('engineering.boms.store') }}">
            @csrf
            @if($isEdit) @method('PUT') @endif
            <div class="row g-3 mb-3">
                <div class="col-md-3"><label>Kode BOM</label><input type="text" class="form-control bg-light fw-bold" value="{{ $bom->bom_code ?: 'AUTO' }}" readonly></div>
                <div class="col-md-5"><label>Item Hasil <span class="text-danger">*</span></label><select name="item_id" class="form-select fw-bold border-primary" required><option value="">-- Pilih Finish Good / WIP --</option>@foreach($fgItems as $item)<option value="{{ $item->id }}" @selected((int) old('item_id', $bom->item_id) === $item->id)>{{ $item->item_code }} - {{ $item->item_name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label>Qty Hasil</label><input type="number" step="any" min="0.0001" name="qty_result" class="form-control fw-bold" value="{{ old('qty_result', $bom->qty_result ?: 1) }}" required></div>
                <div class="col-md-2"><label>Status</label><select name="status" class="form-select"><option value="active" @selected(old('status', $bom->status ?: 'active') === 'active')>Active</option><option value="inactive" @selected(old('status', $bom->status) === 'inactive')>Inactive</option><option value="locked" @selected(old('status', $bom->status) === 'locked')>Locked</option></select></div>
            </div>
            <div class="mb-3"><label>Catatan</label><input type="text" name="notes" class="form-control" value="{{ old('notes', $bom->notes) }}"></div>
            <div class="table-responsive mb-3">
                <table class="table table-bordered" id="bomTable">
                    <thead class="table-light"><tr><th>Material / Komponen</th><th width="140">Qty Needed</th><th width="120">Waste %</th><th>Notes</th><th width="60"></th></tr></thead>
                    <tbody>
                    @forelse($details as $detail)
                        <tr>
                            <td><select name="material_id[]" class="form-select" required><option value="">-- Pilih Material --</option>@foreach($materials as $mat)<option value="{{ $mat->id }}" @selected((int) $detail->material_id === $mat->id)>{{ $mat->item_code }} - {{ $mat->item_name }} ({{ $mat->unit }})</option>@endforeach</select></td>
                            <td><input type="number" step="any" min="0" name="qty_needed[]" class="form-control text-end fw-bold" value="{{ $detail->qty + 0 }}"></td>
                            <td><input type="number" step="0.01" min="0" name="waste_percent[]" class="form-control text-end" value="{{ $detail->waste_percent + 0 }}"></td>
                            <td><input type="text" name="detail_notes[]" class="form-control" value="{{ $detail->notes }}"></td>
                            <td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-row">X</button></td>
                        </tr>
                    @empty
                        <tr>
                            <td><select name="material_id[]" class="form-select" required><option value="">-- Pilih Material --</option>@foreach($materials as $mat)<option value="{{ $mat->id }}">{{ $mat->item_code }} - {{ $mat->item_name }} ({{ $mat->unit }})</option>@endforeach</select></td>
                            <td><input type="number" step="any" min="0" name="qty_needed[]" class="form-control text-end fw-bold" placeholder="0.00"></td>
                            <td><input type="number" step="0.01" min="0" name="waste_percent[]" class="form-control text-end" value="0"></td>
                            <td><input type="text" name="detail_notes[]" class="form-control"></td>
                            <td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-row">X</button></td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <button type="button" id="addRow" class="btn btn-success btn-sm mb-3">+ Tambah Material</button>
            <div class="text-end"><a href="{{ route('engineering.boms.index') }}" class="btn btn-light border px-4 me-2">Batal</a><button class="btn btn-primary px-5">Simpan BOM</button></div>
        </form>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const tbody = document.querySelector('#bomTable tbody');
    document.getElementById('addRow')?.addEventListener('click', () => {
        const row = tbody.querySelector('tr').cloneNode(true);
        row.querySelectorAll('input').forEach(input => input.value = input.name === 'waste_percent[]' ? '0' : '');
        row.querySelectorAll('select').forEach(select => select.value = '');
        tbody.appendChild(row);
    });
    document.addEventListener('click', e => { if (e.target.closest('.remove-row') && tbody.querySelectorAll('tr').length > 1) e.target.closest('tr').remove(); });

    const soPicker = document.getElementById('so_pull_picker');
    const itemSelect = document.querySelector('select[name="item_id"]');

    function syncSoPicker() {
        if (!soPicker || !itemSelect) return;
        const val = soPicker.value;
        if (val) {
            itemSelect.value = val;
        }
    }

    soPicker?.addEventListener('change', syncSoPicker);
    if (soPicker && soPicker.value) {
        syncSoPicker();
    }
})();
</script>
@endpush
@endsection
