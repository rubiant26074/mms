@extends('layouts.mms')

@section('title', 'Buat Cycle Counting')

@section('content')
<form method="POST" action="{{ route('warehouse.cycle_counting.store') }}">
    @csrf
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-bold">Header Session</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2"><label class="form-label">Tanggal Count</label><input type="date" name="count_date" value="{{ old('count_date', now()->toDateString()) }}" class="form-control" required></div>
                <div class="col-md-4 mb-2"><label class="form-label">Area / Zona</label><input type="text" name="count_area" class="form-control" value="{{ old('count_area') }}" placeholder="Contoh: RM Rack A"></div>
                <div class="col-md-5 mb-2"><label class="form-label">Catatan Header</label><input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Catatan umum sesi counting"></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <strong>Item Counting</strong>
            <button type="button" class="btn btn-sm btn-success" id="btnAddRow"><i class="bi bi-plus"></i> Tambah Baris</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0" id="ccTable">
                    <thead class="table-light"><tr><th width="36%">Item</th><th width="12%">System Qty</th><th width="12%">Counted Qty</th><th width="12%">Variance</th><th width="14%">Reason</th><th width="10%">Catatan</th><th width="4%">Aksi</th></tr></thead>
                    <tbody id="ccRows"></tbody>
                </table>
            </div>
        </div>
        @if($errors->any())<div class="card-footer text-danger">{{ $errors->first() }}</div>@endif
        <div class="card-footer text-end">
            <a href="{{ route('warehouse.cycle_counting.index') }}" class="btn btn-secondary me-2">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Session</button>
        </div>
    </div>
</form>

<script>
const ccItems = @json($items);
const itemOptions = ccItems.map((item) => `<option value="${item.id}" data-stock="${Number(item.current_stock || 0)}" data-unit="${item.unit || ''}">${item.item_code} - ${item.item_name}</option>`).join('');

function addCycleCountRow() {
    const rowsWrap = document.getElementById('ccRows');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select name="item_id[]" class="form-select cc-item"><option value="">-- Pilih Item --</option>${itemOptions}</select></td>
        <td><input type="number" class="form-control cc-system text-end" value="0" readonly></td>
        <td><input type="number" step="0.0001" name="counted_qty[]" class="form-control cc-counted text-end" value=""></td>
        <td><input type="number" class="form-control cc-variance text-end" value="0" readonly></td>
        <td><select name="reason[]" class="form-select"><option value="">-</option><option value="selisih_uom">Selisih UOM</option><option value="damage_scrap">Damage/Scrap</option><option value="miss_issue">Issue belum tercatat</option><option value="miss_receive">Receive belum tercatat</option><option value="other">Lainnya</option></select></td>
        <td><input type="text" name="line_notes[]" class="form-control"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger cc-remove">x</button></td>`;

    const itemSel = tr.querySelector('.cc-item');
    const systemEl = tr.querySelector('.cc-system');
    const countedEl = tr.querySelector('.cc-counted');
    const varianceEl = tr.querySelector('.cc-variance');
    const calc = () => {
        const sys = parseFloat(systemEl.value || '0') || 0;
        const cnt = parseFloat(countedEl.value || '0') || 0;
        varianceEl.value = (cnt - sys).toFixed(4);
    };
    itemSel.addEventListener('change', () => {
        const opt = itemSel.options[itemSel.selectedIndex];
        systemEl.value = parseFloat(opt?.dataset.stock || '0').toFixed(4);
        calc();
    });
    countedEl.addEventListener('input', calc);
    tr.querySelector('.cc-remove').addEventListener('click', () => tr.remove());
    rowsWrap.appendChild(tr);
    if (window.initSearchableSelects) window.initSearchableSelects(tr);
}

document.getElementById('btnAddRow')?.addEventListener('click', addCycleCountRow);
addCycleCountRow();
</script>
@endsection
