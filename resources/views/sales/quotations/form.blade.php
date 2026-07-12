@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Quotation' : 'Buat Quotation')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-11">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Form Quotation</h5></div>
            <div class="card-body">
                @include('partials.alerts')
                <form method="POST" enctype="multipart/form-data" action="{{ $isEdit ? route('sales.quotations.update', $quotation) : route('sales.quotations.store') }}">
                    @csrf
                    @if($isEdit) @method('PUT') @endif
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><label class="fw-bold">No. Quotation</label><input type="text" class="form-control bg-light fw-bold" value="{{ $quotation->quote_number }}" readonly></div>
                        <div class="col-md-3"><label>Customer <span class="text-danger">*</span></label><select name="customer_id" class="form-select" required><option value="">- Pilih Customer -</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((int) old('customer_id', $quotation->customer_id) === $customer->id)>{{ $customer->name }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label>Tanggal</label><input type="date" name="quote_date" class="form-control" value="{{ old('quote_date', optional($quotation->quote_date)->format('Y-m-d') ?: now()->toDateString()) }}" required></div>
                        <div class="col-md-2"><label>Payment Terms</label><select name="payment_terms" class="form-select">@foreach(['Cash','Net 14 Days','Net 30 Days','Net 60 Days'] as $term)<option value="{{ $term }}" @selected(old('payment_terms', $quotation->payment_terms ?: 'Net 30 Days') === $term)>{{ $term }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label>Attachment</label><input type="file" name="attachment" class="form-control">@if($quotation->attachment)<small><a href="{{ asset($quotation->attachment) }}" target="_blank">Lihat lampiran</a></small>@endif</div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-2"><label>Mode PPN</label><select name="tax_mode" id="taxMode" class="form-select"><option value="exclude" @selected(!old('tax_mode') && !$quotation->tax_included || old('tax_mode') === 'exclude')>Exclude PPN</option><option value="include" @selected(old('tax_mode') === 'include' || (!old('tax_mode') && $quotation->tax_included))>Include PPN</option></select></div>
                        <div class="col-md-2"><label>PPN (%)</label><input type="number" name="ppn_percent" id="ppnPercent" class="form-control" min="0.01" max="100" step="0.01" value="{{ old('ppn_percent', $quotation->ppn_percent ?: 11) }}"></div>
                        <div class="col-md-2"><label>Discount</label><input type="number" name="discount_amount" id="discountAmount" class="form-control" min="0" step="0.01" value="{{ old('discount_amount', $quotation->discount_amount ?: 0) }}"></div>
                        <div class="col-md-6"><label>Notes</label><input type="text" name="notes" class="form-control" value="{{ old('notes', $quotation->notes) }}"></div>
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered align-middle" id="itemsTable">
                            <thead class="table-light"><tr><th>Kode</th><th>Nama Item</th><th>Material/Spec</th><th>Ownership</th><th>Unit</th><th width="100">Qty</th><th width="150">Harga</th><th width="150">Subtotal</th><th width="60"></th></tr></thead>
                            <tbody>
                            @foreach($items as $item)
                                <tr>
                                    <td><input name="item_code[]" class="form-control" value="{{ old('item_code.' . $loop->index, $item->item_code_manual) }}"></td>
                                    <td><input name="item_name[]" class="form-control" required value="{{ old('item_name.' . $loop->index, $item->item_name_manual ?: $item->temp_item_name) }}"></td>
                                    <td><input name="material[]" class="form-control" value="{{ old('material.' . $loop->index, $item->material_manual ?: $item->temp_spec) }}"></td>
                                    <td><select name="ownership[]" class="form-select"><option value="internal" @selected($item->ownership !== 'customer')>Internal</option><option value="customer" @selected($item->ownership === 'customer')>Customer</option></select></td>
                                    <td><input name="unit[]" class="form-control" value="{{ old('unit.' . $loop->index, $item->unit_manual ?: $item->temp_uom) }}"></td>
                                    <td><input name="qty[]" type="number" step="0.01" min="0" class="form-control calc qty" value="{{ old('qty.' . $loop->index, $item->qty ?: 1) }}"></td>
                                    <td><input name="price[]" type="number" step="0.01" min="0" class="form-control calc price" value="{{ old('price.' . $loop->index, $item->unit_price ?: 0) }}"></td>
                                    <td class="text-end row-subtotal">0</td>
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-row"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot>
                                <tr><td colspan="7" class="text-end">Subtotal :</td><td class="text-end" id="txtSubtotal">0</td><td></td></tr>
                                <tr><td colspan="7" class="text-end">Discount :</td><td class="text-end" id="txtDiscount">0</td><td></td></tr>
                                <tr><td colspan="7" class="text-end">PPN :</td><td class="text-end" id="txtTax">0</td><td></td></tr>
                                <tr class="fw-bold"><td colspan="7" class="text-end">Grand Total :</td><td class="text-end" id="txtGrand">0</td><td></td></tr>
                            </tfoot>
                        </table>
                    </div>
                    <button type="button" id="addRow" class="btn btn-outline-primary btn-sm mb-3"><i class="bi bi-plus-circle"></i> Tambah Item</button>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('sales.quotations.index') }}" class="btn btn-secondary px-4">Batal</a>
                        <button class="btn btn-primary px-5 fw-bold"><i class="bi bi-save"></i> Simpan Penawaran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const tbody = document.querySelector('#itemsTable tbody');
    const fields = ['item_code[]', 'item_name[]', 'material[]', 'ownership[]', 'unit[]', 'qty[]', 'price[]'];
    const rupiah = n => new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
    const cleanNumber = value => {
        let text = String(value ?? '').trim();
        if (!text) return '';
        text = text.replace(/[^\d,.\-]/g, '');
        if (text.includes(',') && text.includes('.')) text = text.replace(/\./g, '').replace(',', '.');
        else if (text.includes(',')) text = text.replace(',', '.');
        else if ((text.match(/\./g) || []).length > 1 || /^\d{1,3}(\.\d{3})+$/.test(text)) text = text.replace(/\./g, '');
        return text;
    };
    const normalizeOwnership = value => {
        const text = String(value ?? '').trim().toLowerCase();
        return ['customer', 'cust', 'consignment', 'konsinyasi'].includes(text) ? 'customer' : 'internal';
    };
    const rowValues = row => fields.map(name => row.querySelector(`[name="${name}"]`));
    const addRow = () => {
        const row = tbody.querySelector('tr').cloneNode(true);
        row.querySelectorAll('input').forEach(input => input.value = input.name === 'qty[]' ? '1' : '');
        row.querySelectorAll('select').forEach(select => select.value = 'internal');
        row.querySelector('.row-subtotal').textContent = '0';
        tbody.appendChild(row);
        return row;
    };
    const parseClipboard = text => text
        .replace(/\r\n/g, '\n')
        .replace(/\r/g, '\n')
        .split('\n')
        .filter(row => row.trim() !== '')
        .map(row => row.split('\t'));
    const headerMap = row => {
        const aliases = {
            item_code: ['kode', 'kode item', 'kode barang', 'code', 'item code', 'item_code'],
            item_name: ['nama', 'nama item', 'nama barang', 'item', 'item name', 'description', 'deskripsi'],
            material: ['material', 'spec', 'spesifikasi', 'specification'],
            ownership: ['ownership', 'kepemilikan', 'owner'],
            unit: ['unit', 'uom', 'satuan'],
            qty: ['qty', 'quantity', 'jumlah'],
            price: ['harga', 'harga satuan', 'price', 'unit price']
        };
        const normalized = row.map(cell => String(cell ?? '').trim().toLowerCase());
        const map = {};
        Object.entries(aliases).forEach(([field, names]) => {
            const idx = normalized.findIndex(cell => names.includes(cell));
            if (idx >= 0) map[field] = idx;
        });
        return Object.keys(map).length >= 2 ? map : null;
    };
    const setField = (input, value) => {
        if (!input) return;
        if (input.name === 'ownership[]') input.value = normalizeOwnership(value);
        else if (['qty[]', 'price[]'].includes(input.name)) input.value = cleanNumber(value);
        else input.value = String(value ?? '').trim();
    };
    function recalc() {
        let total = 0;
        tbody.querySelectorAll('tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty')?.value || 0);
            const price = parseFloat(row.querySelector('.price')?.value || 0);
            const sub = qty * price;
            total += sub;
            row.querySelector('.row-subtotal').textContent = rupiah(sub);
        });
        const discount = Math.min(Math.max(parseFloat(document.getElementById('discountAmount').value || 0), 0), total);
        const subtotal = Math.max(0, total - discount);
        const tax = document.getElementById('taxMode').value === 'include' ? subtotal * (parseFloat(document.getElementById('ppnPercent').value || 11) / 100) : 0;
        document.getElementById('txtSubtotal').textContent = rupiah(total);
        document.getElementById('txtDiscount').textContent = rupiah(discount);
        document.getElementById('txtTax').textContent = rupiah(tax);
        document.getElementById('txtGrand').textContent = rupiah(subtotal + tax);
    }
    document.addEventListener('input', e => { if (e.target.matches('.calc,#discountAmount,#taxMode,#ppnPercent')) recalc(); });
    document.addEventListener('change', e => { if (e.target.matches('#taxMode')) recalc(); });
    document.getElementById('addRow')?.addEventListener('click', () => {
        addRow();
        recalc();
    });
    document.addEventListener('paste', e => {
        const target = e.target;
        if (!target.closest('#itemsTable tbody') || !target.matches('input,select')) return;

        const rows = parseClipboard(e.clipboardData?.getData('text/plain') || '');
        if (!rows.length || (rows.length === 1 && rows[0].length === 1)) return;

        e.preventDefault();
        const bodyRows = Array.from(tbody.querySelectorAll('tr'));
        const startRow = bodyRows.indexOf(target.closest('tr'));
        const startCol = Math.max(0, fields.indexOf(target.name));
        const headers = headerMap(rows[0]);
        const dataRows = headers ? rows.slice(1) : rows;
        const fieldKeys = ['item_code', 'item_name', 'material', 'ownership', 'unit', 'qty', 'price'];

        dataRows.forEach((cells, offset) => {
            let row = tbody.querySelectorAll('tr')[startRow + offset];
            if (!row) row = addRow();

            if (headers) {
                fieldKeys.forEach(key => setField(row.querySelector(`[name="${key}[]"]`), cells[headers[key]] ?? ''));
            } else {
                const inputs = rowValues(row);
                cells.forEach((cell, idx) => setField(inputs[startCol + idx], cell));
            }
        });

        recalc();
    });
    document.addEventListener('click', e => {
        if (e.target.closest('.remove-row') && tbody.querySelectorAll('tr').length > 1) {
            e.target.closest('tr').remove();
            recalc();
        }
    });
    recalc();
})();
</script>
@endpush
@endsection
