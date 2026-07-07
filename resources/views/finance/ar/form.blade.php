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
                        <label>Referensi Surat Jalan <span class="text-danger">*</span></label>
                        <select name="delivery_note_id" class="form-select" required onchange="window.location.href='{{ route('finance.ar.create') }}?sj_id='+this.value" @disabled($isEdit)>
                            <option value="">-- Pilih SJ --</option>
                            @foreach($deliveryNotes as $dn)
                                <option value="{{ $dn->id }}" @selected((int) old('delivery_note_id', $deliveryNote?->id) === $dn->id)>{{ $dn->dn_number }} - {{ $dn->salesOrder?->customer?->name }}</option>
                            @endforeach
                        </select>
                        @if($isEdit)<input type="hidden" name="delivery_note_id" value="{{ $deliveryNote?->id }}">@endif
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
                        <thead class="bg-light"><tr><th>Barang</th><th class="text-center">Qty Kirim (SJ)</th><th class="text-end">Harga Satuan (SO)</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                        @forelse($lines as $line)
                            <tr><td><strong>{{ $line['item_name'] }}</strong><br><small class="text-muted">{{ $line['item_code'] }}</small></td><td class="text-center">{{ $line['qty_sent'] + 0 }} {{ $line['unit'] }}</td><td class="text-end">Rp {{ number_format($line['unit_price'], 0, ',', '.') }}</td><td class="text-end">Rp {{ number_format($line['total'], 0, ',', '.') }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-5 text-muted">Pilih Surat Jalan terlebih dahulu...</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                            <tr><td colspan="3" class="text-end">Subtotal :</td><td class="text-end fw-bold">Rp {{ number_format($totals['subtotal'], 0, ',', '.') }}</td></tr>
                            <tr><td colspan="3" class="text-end align-middle">Diskon (Rp) :</td><td><input type="text" name="discount_amount" class="form-control text-end" value="{{ old('discount_amount', number_format($totals['discount'], 0, ',', '.')) }}"></td></tr>
                            <tr><td colspan="3" class="text-end">PPN :</td><td class="text-end">Rp {{ number_format($totals['tax'], 0, ',', '.') }}</td></tr>
                            <tr class="bg-primary text-white"><td colspan="3" class="text-end fw-bold">GRAND TOTAL :</td><td class="text-end fw-bold fs-5">Rp {{ number_format($totals['grand'], 0, ',', '.') }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="text-end"><a href="{{ route('finance.ar.index') }}" class="btn btn-secondary me-2">Batal</a><button type="submit" class="btn btn-primary px-4 fw-bold" @disabled(! $deliveryNote)>Simpan Invoice</button></div>
        </div>
    </div>
</form>
@endsection
