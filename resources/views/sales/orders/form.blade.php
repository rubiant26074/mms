@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Sales Order' : 'Buat Sales Order')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">SALES ORDER</h5>
        <span class="badge bg-light text-dark fw-bold">{{ $order->so_number }}</span>
    </div>
    <div class="card-body">
        @include('partials.alerts')
        <form method="POST" action="{{ $isEdit ? route('sales.orders.update', $order) : route('sales.orders.store') }}">
            @csrf
            @if($isEdit) @method('PUT') @endif
            <input type="hidden" name="quotation_id" value="{{ old('quotation_id', $order->quotation_id) }}">
            <div class="row">
                <div class="col-md-6 border-end">
                    <div class="mb-3"><label class="fw-bold">Customer <span class="text-danger">*</span></label><select name="customer_id" class="form-select" required><option value="">-- Pilih Customer --</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((int) old('customer_id', $order->customer_id) === $customer->id)>{{ $customer->customer_code }} - {{ $customer->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="fw-bold">PO Customer Number</label><input type="text" name="cust_po_number" class="form-control" value="{{ old('cust_po_number', $order->cust_po_number) }}"></div>
                    <div class="mb-3"><label class="fw-bold">Catatan SO</label><textarea name="notes" class="form-control" rows="3">{{ old('notes', $order->notes) }}</textarea></div>
                </div>
                <div class="col-md-6 ps-4">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="fw-bold">Tgl SO</label><input type="date" name="so_date" class="form-control" value="{{ old('so_date', optional($order->so_date)->format('Y-m-d') ?: now()->toDateString()) }}" required></div>
                        <div class="col-md-6 mb-3"><label class="fw-bold">Est. Delivery</label><input type="date" name="delivery_date" class="form-control" value="{{ old('delivery_date', optional($order->delivery_date)->format('Y-m-d')) }}"></div>
                    </div>
                    <div class="mb-3"><label class="fw-bold">Payment Terms</label><select name="payment_terms" class="form-select">@foreach(['Cash','Net 14 Days','Net 30 Days','Net 60 Days'] as $term)<option value="{{ $term }}" @selected(old('payment_terms', $order->payment_terms ?: 'Net 30 Days') === $term)>{{ $term }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="fw-bold">Sumber Pemenuhan</label><select name="fulfillment_source" class="form-select"><option value="spk" @selected(old('fulfillment_source', $order->fulfillment_source ?: 'spk') === 'spk')>Produksi / SPK</option><option value="fg_stock" @selected(old('fulfillment_source', $order->fulfillment_source) === 'fg_stock')>FG Stock (Tanpa SPK Baru)</option></select></div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="fw-bold">Mode PPN</label><select name="tax_mode" id="taxMode" class="form-select"><option value="exclude" @selected(old('tax_mode') === 'exclude' || (!old('tax_mode') && !$order->tax_included))>Exclude</option><option value="include" @selected(old('tax_mode') === 'include' || (!old('tax_mode') && $order->tax_included))>Include</option></select></div>
                        <div class="col-md-4 mb-3"><label class="fw-bold">PPN (%)</label><input type="number" name="ppn_percent" id="ppnPercent" class="form-control" step="0.01" min="0.01" max="100" value="{{ old('ppn_percent', $order->ppn_percent ?: 11) }}"></div>
                        <div class="col-md-4 mb-3"><label class="fw-bold">Diskon</label><input type="number" name="discount_amount" id="discountAmount" class="form-control calc" step="0.01" min="0" value="{{ old('discount_amount', $order->discount_amount ?: 0) }}"></div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white fw-bold">Daftar Barang Jadi (Finish Goods)</div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-bordered mb-0" id="itemsTable">
                        <thead class="bg-light text-center small"><tr><th>Kode</th><th>Nama Barang</th><th>Material</th><th>Qty</th><th>Unit</th><th>Harga Satuan</th><th>Subtotal</th><th></th></tr></thead>
                        <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td><input type="hidden" name="item_id[]" value="{{ $item->item_id ?: 0 }}"><input name="item_code[]" class="form-control" value="{{ $item->item_code_manual }}"></td>
                                <td><input name="item_name[]" class="form-control" value="{{ $item->item_name_manual }}" required></td>
                                <td><input name="material[]" class="form-control" value="{{ $item->material_manual }}"></td>
                                <td><input name="qty[]" type="number" step="0.01" min="0" class="form-control calc qty" value="{{ $item->qty ?: 1 }}" required></td>
                                <td><input name="unit[]" class="form-control" value="{{ $item->unit_manual ?: 'PCS' }}"></td>
                                <td><input name="price[]" type="number" step="0.01" min="0" class="form-control calc price" value="{{ $item->unit_price ?: 0 }}" required></td>
                                <td class="text-end row-subtotal">0</td>
                                <td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-row">X</button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white"><button type="button" id="addRow" class="btn btn-success btn-sm fw-bold">+ Tambah Baris</button></div>
            </div>
            <div class="row">
                <div class="col-md-7"></div>
                <div class="col-md-5">
                    <div class="card bg-light shadow-sm"><div class="card-body">
                        <div class="d-flex justify-content-between mb-2"><span>Subtotal:</span><span id="txtSubtotal" class="fw-bold">0</span></div>
                        <div class="d-flex justify-content-between mb-2"><span>Diskon:</span><span id="txtDiscount" class="fw-bold">0</span></div>
                        <div class="d-flex justify-content-between mb-2"><span>PPN:</span><span id="txtTax" class="fw-bold">0</span></div>
                        <hr><div class="d-flex justify-content-between"><h5 class="fw-bold text-primary">GRAND TOTAL:</h5><h5 id="txtGrand" class="fw-bold text-primary">0</h5></div>
                    </div></div>
                    <div class="mt-4 text-end"><a href="{{ route('sales.orders.index') }}" class="btn btn-secondary px-4 me-2">Batal</a><button class="btn btn-primary px-5 fw-bold shadow">SIMPAN SALES ORDER</button></div>
                </div>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const tbody = document.querySelector('#itemsTable tbody');
    const rupiah = n => new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
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
        const include = document.getElementById('taxMode').value === 'include';
        const ppn = parseFloat(document.getElementById('ppnPercent').value || 11) / 100;
        let tax = 0, grand = Math.max(0, total - discount);
        if (include) { tax = grand - (grand / (1 + ppn)); }
        document.getElementById('txtSubtotal').textContent = rupiah(total);
        document.getElementById('txtDiscount').textContent = rupiah(discount);
        document.getElementById('txtTax').textContent = rupiah(tax);
        document.getElementById('txtGrand').textContent = rupiah(grand);
    }
    document.addEventListener('input', e => { if (e.target.matches('.calc,#taxMode,#ppnPercent')) recalc(); });
    document.addEventListener('change', e => { if (e.target.matches('#taxMode')) recalc(); });
    document.getElementById('addRow')?.addEventListener('click', () => {
        const row = tbody.querySelector('tr').cloneNode(true);
        row.querySelectorAll('input').forEach(input => input.value = input.name === 'qty[]' ? '1' : (input.name === 'unit[]' ? 'PCS' : ''));
        tbody.appendChild(row); recalc();
    });
    document.addEventListener('click', e => { if (e.target.closest('.remove-row') && tbody.querySelectorAll('tr').length > 1) { e.target.closest('tr').remove(); recalc(); } });
    recalc();
})();
</script>
@endpush
@endsection
