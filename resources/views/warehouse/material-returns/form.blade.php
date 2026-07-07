@extends('layouts.mms')

@section('title', 'Buat Pengembalian Material')

@section('content')
<form method="POST" action="{{ route('warehouse.material_returns.store') }}" id="formReturn">
    @csrf
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">Info Pengembalian</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Referensi SPK <span class="text-danger">*</span></label>
                        <select name="spk_id" class="form-select @error('spk_id') is-invalid @enderror" required>
                            <option value="">-- Pilih SPK --</option>
                            @foreach($spkOptions as $spk)
                                <option value="{{ $spk->id }}" @selected((int) old('spk_id', $return->spk_id) === $spk->id)>{{ $spk->spk_number }} - {{ \Illuminate\Support\Str::limit($spk->project_name, 24) }}</option>
                            @endforeach
                        </select>
                        @error('spk_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3"><label class="form-label">Tanggal Kembali</label><input type="date" name="ret_date" class="form-control" value="{{ old('ret_date', optional($return->ret_date)->format('Y-m-d') ?: now()->toDateString()) }}" required></div>
                    <div class="mb-3"><label class="form-label">Dikembalikan Oleh</label><input type="text" name="returned_by" class="form-control" value="{{ old('returned_by', $return->returned_by) }}" required></div>
                    <div class="mb-3"><label class="form-label">Catatan</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', $return->notes) }}</textarea></div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <strong>Daftar Material Kembali</strong>
                    <button type="button" class="btn btn-sm btn-success" id="addReturnRow"><i class="bi bi-plus-lg"></i> Tambah Item</button>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light"><tr><th width="20%">Tipe</th><th width="40%">Nama Barang</th><th width="15%">Qty</th><th width="15%">Satuan</th><th width="10%">Hapus</th></tr></thead>
                        <tbody id="retItems"></tbody>
                    </table>
                </div>
                @if($errors->any())<div class="card-footer text-danger">{{ $errors->first() }}</div>@endif
                <div class="card-footer bg-white text-end">
                    <a href="{{ route('warehouse.material_returns.index') }}" class="btn btn-secondary me-2">Batal</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Ajukan ke Gudang</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
const returnMaterials = @json($materials);
const oldRows = @json($oldRows);

function materialOptions(selected) {
    return '<option value="">-- Pilih Master Barang --</option>' + returnMaterials.map((item) => {
        const isSelected = String(selected ?? '') === String(item.id) ? ' selected' : '';
        return `<option value="${item.id}" data-unit="${item.unit ?? ''}"${isSelected}>${item.item_code} - ${item.item_name}</option>`;
    }).join('');
}

function addReturnRow(seed = {}) {
    const rowId = `ret_${Date.now()}_${Math.floor(Math.random() * 1000)}`;
    const type = seed.type || 'intact';
    const row = `
        <tr id="${rowId}">
            <td>
                <select name="type[]" class="form-select form-select-sm" onchange="toggleReturnInput('${rowId}', this.value)">
                    <option value="intact"${type === 'intact' ? ' selected' : ''}>Utuh (Master)</option>
                    <option value="waste"${type === 'waste' ? ' selected' : ''}>Sisa / Waste</option>
                </select>
            </td>
            <td>
                <div id="intact_box_${rowId}">
                    <select name="item_id[]" class="form-select form-select-sm" onchange="updateReturnUnit('${rowId}', this)">${materialOptions(seed.item_id)}</select>
                </div>
                <div id="waste_box_${rowId}">
                    <input type="text" name="item_name_manual[]" class="form-control form-control-sm" placeholder="Nama Barang Sisa (Manual)" value="${seed.item_name_manual || ''}">
                </div>
            </td>
            <td><input type="number" name="qty[]" class="form-control form-control-sm" step="0.01" min="0" value="${seed.qty || ''}" required></td>
            <td><input type="text" name="unit[]" id="unit_${rowId}" class="form-control form-control-sm" value="${seed.unit || ''}" placeholder="Unit"></td>
            <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('${rowId}').remove()"><i class="bi bi-trash"></i></button></td>
        </tr>`;
    document.getElementById('retItems').insertAdjacentHTML('beforeend', row);
    toggleReturnInput(rowId, type);
}

function toggleReturnInput(rowId, type) {
    const intactBox = document.getElementById(`intact_box_${rowId}`);
    const wasteBox = document.getElementById(`waste_box_${rowId}`);
    const unitInput = document.getElementById(`unit_${rowId}`);
    intactBox.classList.toggle('d-none', type !== 'intact');
    wasteBox.classList.toggle('d-none', type !== 'waste');
    unitInput.readOnly = type === 'intact';
    if (type === 'waste' && !unitInput.value) {
        unitInput.value = 'Kg';
    }
}

function updateReturnUnit(rowId, select) {
    document.getElementById(`unit_${rowId}`).value = select.options[select.selectedIndex]?.dataset.unit || '';
}

document.getElementById('addReturnRow')?.addEventListener('click', () => addReturnRow());
(oldRows.length ? oldRows : [{}]).forEach((row) => addReturnRow(row));
</script>
@endsection
