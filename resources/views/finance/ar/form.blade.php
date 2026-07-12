@extends('layouts.mms')

@section('title', $invoice->exists ? 'Edit Invoice' : 'Buat Invoice')

@section('content')
@php($isEdit = $invoice->exists)
<form method="POST" action="{{ $isEdit ? route('finance.ar.update', $invoice) : route('finance.ar.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">Info Invoice</div>
                <div class="card-body">
                    <div class="mb-2"><label>No. Invoice</label><input type="text" class="form-control fw-bold" value="{{ old('invoice_number', $invoice->invoice_number) }}" readonly></div>
                    <div class="mb-2"><label>No. Seri Faktur Pajak</label><input type="text" name="tax_invoice_number" class="form-control @error('tax_invoice_number') is-invalid @enderror" value="{{ old('tax_invoice_number', $invoice->tax_invoice_number) }}" placeholder="010.000-24.00000001"><small class="text-muted">Format: 000.000-YY.12345678</small>@error('tax_invoice_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="mb-2">
                        <label>Referensi Berdasarkan</label>
                        <select id="refType" name="ref_type" class="form-select" @disabled($isEdit) onchange="updateRefFields()">
                            <option value="sj" @selected(old('ref_type', $refType ?? 'sj') === 'sj')>Surat Jalan (SJ)</option>
                            <option value="so" @selected(old('ref_type', $refType ?? 'sj') === 'so')>Sales Order (SO)</option>
                        </select>
                    </div>

                    <div class="mb-2" id="sjFieldSection" style="display: {{ old('ref_type', $refType ?? 'sj') === 'sj' ? 'block' : 'none' }}">
                        <label>Referensi Surat Jalan <span class="text-danger">*</span></label>
                        <select name="delivery_note_id" id="deliveryNoteSelect" class="form-select" @required(old('ref_type', $refType ?? 'sj') === 'sj') onchange="window.location.href='{{ route('finance.ar.create') }}?ref_type=sj&sj_id='+this.value" @disabled($isEdit)>
                            <option value="">-- Pilih SJ --</option>
                            @foreach($deliveryNotes as $dn)
                                <option value="{{ $dn->id }}" @selected((int) old('delivery_note_id', $deliveryNote?->id) === $dn->id)>{{ $dn->dn_number }} - {{ $dn->salesOrder?->customer?->name }}</option>
                            @endforeach
                        </select>
                        @if($isEdit && old('ref_type', $refType ?? 'sj') === 'sj')<input type="hidden" name="delivery_note_id" value="{{ $deliveryNote?->id }}">@endif
                    </div>

                    <div class="mb-2" id="soFieldSection" style="display: {{ old('ref_type', $refType ?? 'sj') === 'so' ? 'block' : 'none' }}">
                        <label>Referensi Sales Order <span class="text-danger">*</span></label>
                        <select name="sales_order_id" id="salesOrderSelect" class="form-select" @required(old('ref_type', $refType ?? 'sj') === 'so') onchange="window.location.href='{{ route('finance.ar.create') }}?ref_type=so&so_id='+this.value" @disabled($isEdit)>
                            <option value="">-- Pilih SO --</option>
                            @foreach($salesOrders as $so)
                                <option value="{{ $so->id }}" @selected((int) old('sales_order_id', $salesOrder?->id) === $so->id)>{{ $so->so_number }} - {{ $so->customer?->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-2">
                        <label>Tipe Tagihan</label>
                        <select id="invoiceType" name="invoice_type" class="form-select" @disabled($isEdit) onchange="updateInvoiceTypeFields()">
                            <option value="normal" @selected(old('invoice_type', $invoice->invoice_type ?? 'normal') === 'normal')>Normal / Pelunasan</option>
                            <option value="dp" @selected(old('invoice_type', $invoice->invoice_type ?? 'normal') === 'dp')>Uang Muka (DP)</option>
                        </select>
                    </div>

                    <div id="dpFieldsSection" style="display: {{ old('invoice_type', $invoice->invoice_type ?? 'normal') === 'dp' ? 'block' : 'none' }}">
                        <div class="mb-2">
                            <label>Tipe Uang Muka</label>
                            <select id="dpType" name="dp_type" class="form-select" @disabled($isEdit) onchange="updateDpCalculations()">
                                <option value="percent" @selected(old('dp_type', $invoice->dp_percent ? 'percent' : 'nominal') === 'percent')>Persentase (%)</option>
                                <option value="nominal" @selected(old('dp_type', $invoice->dp_percent ? 'percent' : 'nominal') === 'nominal')>Nominal Rupiah (Rp)</option>
                            </select>
                        </div>
                        <div class="mb-2" id="dpPercentGroup" style="display: {{ old('dp_type', $invoice->dp_percent ? 'percent' : 'nominal') === 'percent' ? 'block' : 'none' }};">
                            <label>Nilai Uang Muka (%)</label>
                            <input type="number" name="dp_percent" id="dpPercentInput" class="form-control" min="0" max="100" step="0.01" value="{{ old('dp_percent', $invoice->dp_percent ? $invoice->dp_percent + 0 : '') }}" @disabled($isEdit) oninput="updateDpCalculations()">
                        </div>
                        <div class="mb-2" id="dpAmountGroup" style="display: {{ old('dp_type', $invoice->dp_percent ? 'percent' : 'nominal') === 'nominal' ? 'block' : 'none' }};">
                            <label>Nilai Uang Muka (Rp)</label>
                            <input type="text" name="dp_amount" id="dpAmountInput" class="form-control text-end" value="{{ old('dp_amount', $invoice->invoice_type === 'dp' ? number_format($invoice->subtotal, 0, ',', '.') : '') }}" @disabled($isEdit) oninput="updateDpCalculations()">
                        </div>
                    </div>

                    <div class="mb-2"><label>Tanggal Invoice</label><input type="date" name="invoice_date" class="form-control" value="{{ old('invoice_date', optional($invoice->invoice_date)->format('Y-m-d') ?: now()->toDateString()) }}" required></div>
                    <div class="mb-2"><label>Jatuh Tempo</label><input type="date" name="due_date" class="form-control" value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d') ?: now()->addDays(30)->toDateString()) }}" required></div>
                    <div class="mb-2"><label>Catatan</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', $invoice->notes) }}</textarea></div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">Detail Tagihan</div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0">
                        <thead class="bg-light"><tr><th>Barang</th><th class="text-center">{{ old('ref_type', $refType ?? 'sj') === 'sj' ? 'Qty Kirim (SJ)' : 'Qty Order (SO)' }}</th><th class="text-end">Harga Satuan (SO)</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                        @forelse($lines as $line)
                            <tr><td><strong>{{ $line['item_name'] }}</strong><br><small class="text-muted">{{ $line['item_code'] }}</small></td><td class="text-center">{{ $line['qty_sent'] + 0 }} {{ $line['unit'] }}</td><td class="text-end">Rp {{ number_format($line['unit_price'], 0, ',', '.') }}</td><td class="text-end">Rp {{ number_format($line['total'], 0, ',', '.') }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-5 text-muted">Pilih {{ old('ref_type', $refType ?? 'sj') === 'sj' ? 'Surat Jalan' : 'Sales Order' }} terlebih dahulu...</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                            <tr><td colspan="3" class="text-end">Subtotal :</td><td class="text-end fw-bold">Rp <span id="lblSubtotal">{{ number_format($totals['subtotal'], 0, ',', '.') }}</span></td></tr>
                            <tr id="dpSubtractionRow" style="display: {{ ($totals['dp_subtraction'] ?? 0) > 0 ? 'table-row' : 'none' }}">
                                <td colspan="3" class="text-end text-danger fw-bold">Uang Muka (DP) :</td>
                                <td class="text-end text-danger fw-bold">-Rp <span id="lblDpSubtraction">{{ number_format($totals['dp_subtraction'] ?? 0, 0, ',', '.') }}</span></td>
                            </tr>
                            <tr><td colspan="3" class="text-end align-middle">Diskon (Rp) :</td><td><input type="text" name="discount_amount" id="discountInput" class="form-control text-end" value="{{ old('discount_amount', number_format($totals['discount'], 0, ',', '.')) }}" oninput="recalc()"></td></tr>
                            <tr><td colspan="3" class="text-end">PPN :</td><td class="text-end">Rp <span id="lblPpn">{{ number_format($totals['tax'], 0, ',', '.') }}</span></td></tr>
                            <tr class="bg-primary text-white"><td colspan="3" class="text-end fw-bold">GRAND TOTAL :</td><td class="text-end fw-bold fs-5">Rp <span id="lblGrandTotal">{{ number_format($totals['grand'], 0, ',', '.') }}</span></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="text-end"><a href="{{ route('finance.ar.index') }}" class="btn btn-secondary me-2">Batal</a><button type="submit" class="btn btn-primary px-4 fw-bold" @disabled(! $deliveryNote && ! $salesOrder)>Simpan Invoice</button></div>
        </div>
    </div>
</form>

<script>
const baseSubtotal = {{ (float) ($refType === 'sj' && $deliveryNote ? $lines->sum('total') : ($refType === 'so' && $salesOrder ? $lines->sum('total') : 0)) }};
const existingDpAmount = {{ (float) ($existingDp ?? 0) }};
const ppnPercent = 11;

function cleanNumber(str) {
    if (!str) return 0;
    return parseFloat(str.replace(/[^0-9,-]/g, '').replace(',', '.')) || 0;
}

function formatNumber(num) {
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(num);
}

function updateRefFields() {
    const refType = document.getElementById('refType').value;
    const sjSection = document.getElementById('sjFieldSection');
    const soSection = document.getElementById('soFieldSection');
    const sjSelect = document.getElementById('deliveryNoteSelect');
    const soSelect = document.getElementById('salesOrderSelect');

    if (refType === 'sj') {
        sjSection.style.display = 'block';
        soSection.style.display = 'none';
        sjSelect.required = true;
        soSelect.required = false;
        soSelect.value = '';
    } else {
        sjSection.style.display = 'none';
        soSection.style.display = 'block';
        sjSelect.required = false;
        soSelect.required = true;
        sjSelect.value = '';
    }
}

function updateInvoiceTypeFields() {
    const type = document.getElementById('invoiceType').value;
    const dpFieldsSection = document.getElementById('dpFieldsSection');
    const tableBody = document.querySelector('table tbody');

    if (type === 'dp') {
        dpFieldsSection.style.display = 'block';
        if (tableBody && !{{ $isEdit ? 'true' : 'false' }}) {
            tableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted font-italic">Uang Muka (DP) - Detail barang disembunyikan</td></tr>`;
        }
    } else {
        dpFieldsSection.style.display = 'none';
    }
    recalc();
}

// Keep it globally bound for element actions
window.updateInvoiceTypeFields = updateInvoiceTypeFields;

function updateDpCalculations() {
    const dpType = document.getElementById('dpType').value;
    const dpPercentGroup = document.getElementById('dpPercentGroup');
    const dpAmountGroup = document.getElementById('dpAmountGroup');
    const dpPercentInput = document.getElementById('dpPercentInput');
    const dpAmountInput = document.getElementById('dpAmountInput');

    if (dpType === 'percent') {
        dpPercentGroup.style.display = 'block';
        dpAmountGroup.style.display = 'none';
        dpPercentInput.required = true;
        dpAmountInput.required = false;
    } else {
        dpPercentGroup.style.display = 'none';
        dpAmountGroup.style.display = 'block';
        dpPercentInput.required = false;
        dpAmountInput.required = true;
    }
    recalc();
}

window.updateDpCalculations = updateDpCalculations;

function recalc() {
    const type = document.getElementById('invoiceType').value;
    const discountInput = document.getElementById('discountInput');
    
    let discountVal = discountInput.value;
    let discountNum = cleanNumber(discountVal);
    discountInput.value = formatNumber(discountNum);

    let subtotal = baseSubtotal;
    let dpSubtraction = 0;

    if (type === 'dp') {
        const dpType = document.getElementById('dpType').value;
        if (dpType === 'percent') {
            const dpPercent = parseFloat(document.getElementById('dpPercentInput').value) || 0;
            subtotal = baseSubtotal * (dpPercent / 100);
        } else {
            const dpAmountInput = document.getElementById('dpAmountInput');
            let dpAmountVal = dpAmountInput.value;
            let dpAmountNum = cleanNumber(dpAmountVal);
            dpAmountInput.value = formatNumber(dpAmountNum);
            subtotal = dpAmountNum;
        }
    } else {
        dpSubtraction = existingDpAmount;
    }

    const dpp = Math.max(0, subtotal - discountNum - dpSubtraction);
    const tax = dpp * (ppnPercent / 100);
    const grand = dpp + tax;

    document.getElementById('lblSubtotal').innerText = formatNumber(subtotal);
    document.getElementById('lblDpSubtraction').innerText = formatNumber(dpSubtraction);
    document.getElementById('dpSubtractionRow').style.display = dpSubtraction > 0 ? 'table-row' : 'none';
    document.getElementById('lblPpn').innerText = formatNumber(tax);
    document.getElementById('lblGrandTotal').innerText = formatNumber(grand);
}

window.recalc = recalc;

document.addEventListener('DOMContentLoaded', function() {
    updateInvoiceTypeFields();
    updateDpCalculations();
    recalc();
});
</script>
        </div>
    </div>
</form>
@endsection
