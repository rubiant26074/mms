@extends('layouts.mms')

@section('title', $isEdit ? 'Edit RFQ' : 'Buat RFQ')

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-8">
        <h3 class="fw-bold"><i class="bi bi-clipboard2-data"></i> {{ $isEdit ? 'Edit RFQ' : 'Buat RFQ' }}</h3>
        <p class="text-muted mb-0">Input item dan penawaran vendor untuk pembandingan harga.</p>
    </div>
</div>

@if($suppliers->isEmpty())
    <div class="alert alert-warning">Master supplier kosong. Tambahkan vendor terlebih dulu di menu Suppliers.</div>
@endif

<form method="POST" action="{{ $isEdit ? route('procurement.rfqs.update', $rfq) : route('procurement.rfqs.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2"><label class="form-label">Nomor RFQ</label><input type="text" name="rfq_number" class="form-control" value="{{ old('rfq_number', $rfq->rfq_number) }}" placeholder="Auto jika kosong"></div>
                <div class="col-md-3 mb-2"><label class="form-label">Tanggal RFQ</label><input type="date" name="rfq_date" class="form-control" value="{{ old('rfq_date', optional($rfq->rfq_date)->format('Y-m-d') ?: $rfq->rfq_date) }}" required></div>
                <div class="col-md-3 mb-2"><label class="form-label">Batas Penawaran</label><input type="date" name="due_date" class="form-control" value="{{ old('due_date', optional($rfq->due_date)->format('Y-m-d') ?: $rfq->due_date) }}"></div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        @foreach(['draft','sent','evaluated','closed','cancelled'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $rfq->status ?: 'draft') === $status)>{{ strtoupper($status) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-2"><label class="form-label">Catatan</label><textarea name="notes" rows="2" class="form-control">{{ old('notes', $rfq->notes) }}</textarea></div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <strong>Quote Vendor</strong>
            <button type="button" class="btn btn-sm btn-success" id="btnAddRfqRow"><i class="bi bi-plus"></i> Tambah</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light"><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Vendor</th><th>Harga</th><th>Lead Time</th><th>Catatan</th><th>Aksi</th></tr></thead>
                    <tbody id="rfqRows">
                    @foreach($lines as $line)
                        <tr>
                            <td><input type="text" name="item_name[]" class="form-control form-control-sm" value="{{ $line->item_name }}" required></td>
                            <td><input type="number" step="0.0001" min="0" name="qty[]" class="form-control form-control-sm text-end" value="{{ $line->qty + 0 }}" required></td>
                            <td><input type="text" name="unit[]" class="form-control form-control-sm" value="{{ $line->unit }}"></td>
                            <td><select name="supplier_id[]" class="form-select form-select-sm" required><option value="">-- Vendor --</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((int) $supplier->id === (int) $line->supplier_id)>{{ $supplier->code }} - {{ $supplier->name }}</option>@endforeach</select></td>
                            <td><input type="number" step="0.01" min="0" name="unit_price[]" class="form-control form-control-sm text-end" value="{{ $line->unit_price + 0 }}" required></td>
                            <td><input type="number" step="1" min="0" name="lead_time_days[]" class="form-control form-control-sm text-end" value="{{ $line->lead_time_days }}"></td>
                            <td><input type="text" name="line_notes[]" class="form-control form-control-sm" value="{{ $line->notes }}"></td>
                            <td class="text-center"><button type="button" class="btn btn-sm btn-danger btn-rem-rfq">x</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <a href="{{ route('procurement.rfqs.index') }}" class="btn btn-secondary">Batal</a>
            <button class="btn btn-primary px-5" @disabled($suppliers->isEmpty())>Simpan</button>
        </div>
    </div>
</form>

@push('scripts')
<script>
(function(){
    const rows=document.getElementById('rfqRows');
    const addBtn=document.getElementById('btnAddRfqRow');
    if(!rows||!addBtn)return;
    const supplierOpt=`@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->code }} - {{ $supplier->name }}</option>@endforeach`;
    const bind=tr=>tr.querySelector('.btn-rem-rfq')?.addEventListener('click',()=>tr.remove());
    const add=()=>{
        const tr=document.createElement('tr');
        tr.innerHTML=`<td><input type="text" name="item_name[]" class="form-control form-control-sm" required></td><td><input type="number" step="0.0001" min="0" name="qty[]" class="form-control form-control-sm text-end" required></td><td><input type="text" name="unit[]" class="form-control form-control-sm" value="Unit"></td><td><select name="supplier_id[]" class="form-select form-select-sm" required><option value="">-- Vendor --</option>${supplierOpt}</select></td><td><input type="number" step="0.01" min="0" name="unit_price[]" class="form-control form-control-sm text-end" required></td><td><input type="number" step="1" min="0" name="lead_time_days[]" class="form-control form-control-sm text-end"></td><td><input type="text" name="line_notes[]" class="form-control form-control-sm"></td><td class="text-center"><button type="button" class="btn btn-sm btn-danger btn-rem-rfq">x</button></td>`;
        rows.appendChild(tr);bind(tr);
    };
    rows.querySelectorAll('tr').forEach(bind);
    if(!rows.querySelector('tr'))add();
    addBtn.addEventListener('click',add);
})();
</script>
@endpush
@endsection
