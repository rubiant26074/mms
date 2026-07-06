@extends('layouts.mms')

@section('title', 'Form Inspeksi QC Incoming')

@section('content')
@include('partials.alerts')
<form method="POST" action="{{ route('qc.incoming.store_inspection', $receipt) }}">
    @csrf
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white"><h5 class="mb-0">Info Penerimaan Barang</h5></div>
        <div class="card-body"><div class="row">
            <div class="col-md-3"><label class="fw-bold">No. GR</label><div class="form-control-plaintext">{{ $receipt->gr_number }}</div></div>
            <div class="col-md-3"><label class="fw-bold">Tgl Terima</label><div class="form-control-plaintext">{{ optional($receipt->gr_date)->format('d/m/Y') }}</div></div>
            <div class="col-md-3"><label class="fw-bold">Sumber</label><div class="form-control-plaintext">{{ $receipt->receipt_type === 'consignment' ? (($receipt->customer?->name ?: '-') . ' (Cust)') : ($receipt->purchaseOrder?->supplier?->name ?: '-') }}</div></div>
            <div class="col-md-3"><label class="fw-bold">No. SJ</label><div class="form-control-plaintext">{{ $receipt->delivery_note_number }}</div></div>
        </div></div>
    </div>

    @foreach($receipt->items as $idx => $row)
        @php $type = $row->item?->qc_type ?: 'general'; @endphp
        <div class="card shadow-sm mb-4 border-start border-4 border-info">
            <div class="card-header bg-light d-flex justify-content-between align-items-center"><div><h6 class="mb-0 fw-bold">{{ $row->item?->item_name }}</h6><small class="text-muted">Kode: {{ $row->item?->item_code }}</small></div><span class="badge bg-info text-dark">Standar QC: {{ strtoupper($type) }}</span></div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><label>Qty Diterima</label><input type="number" class="form-control bg-light fw-bold" value="{{ $row->qty_received + 0 }}" readonly><small class="text-muted">{{ $row->item?->unit }}</small></div>
                    <div class="col-md-3"><label>Qty OK (Good) <span class="text-success">*</span></label><input type="number" name="qty_good[]" class="form-control border-success fw-bold qty-good" data-idx="{{ $idx }}" value="{{ $row->qty_received + 0 }}" step="0.01" required oninput="calcReject({{ $idx }}, {{ $row->qty_received + 0 }})"></div>
                    <div class="col-md-3"><label>Qty Reject <span class="text-danger">*</span></label><input type="number" name="qty_reject[]" id="reject_{{ $idx }}" class="form-control border-danger fw-bold text-danger" value="0" step="0.01" readonly></div>
                    <div class="col-md-3"><label>Catatan Item</label><input type="text" name="item_notes[]" class="form-control" placeholder="Keterangan reject..."></div>
                </div>
                <div class="p-3 bg-light rounded border">
                    <h6 class="small fw-bold text-uppercase text-muted mb-2">Checklist Kriteria ({{ ucfirst($type) }})</h6>
                    <div class="row g-2">
                        @if($type === 'plate')
                            @foreach(['thickness'=>'Ketebalan','flatness'=>'Kerataan','rust'=>'Karat / Korosi','dimension'=>'Dimensi PxL'] as $key => $label)<div class="col-md-3"><label class="small fw-bold">{{ $label }}</label><select name="checklist[{{ $row->item_id }}][{{ $key }}]" class="form-select form-select-sm"><option value="OK">OK</option><option value="NG">NG</option></select></div>@endforeach
                        @elseif($type === 'coating')
                            <div class="col-md-3"><label class="small fw-bold">Ketebalan (Micron)</label><input type="text" name="checklist[{{ $row->item_id }}][micron]" class="form-control form-control-sm"></div>
                            @foreach(['color'=>'Warna / Visual','adhesion'=>'Adhesion','defect'=>'Cacat Fisik'] as $key => $label)<div class="col-md-3"><label class="small fw-bold">{{ $label }}</label><select name="checklist[{{ $row->item_id }}][{{ $key }}]" class="form-select form-select-sm"><option value="OK">OK</option><option value="NG">NG</option></select></div>@endforeach
                        @elseif($type === 'machining')
                            @foreach(['dimension'=>'Dimensi vs Drawing','surface'=>'Surface Finish','thread'=>'Drat / Thread'] as $key => $label)<div class="col-md-4"><label class="small fw-bold">{{ $label }}</label><select name="checklist[{{ $row->item_id }}][{{ $key }}]" class="form-select form-select-sm"><option value="OK">OK</option><option value="NG">NG</option></select></div>@endforeach
                        @else
                            <div class="col-12"><div class="alert alert-info py-1 mb-0 small"><i class="bi bi-check-circle"></i> Item General/Consumable: Cek Kuantitas, Kemasan, dan Kesesuaian Fisik.</div><input type="hidden" name="checklist[{{ $row->item_id }}][check]" value="General Inspection OK"></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="card shadow-sm mb-5"><div class="card-body"><label class="fw-bold">Catatan Kesimpulan QC:</label><textarea name="notes" class="form-control mb-3" rows="2" placeholder="Contoh: Barang diterima dalam kondisi baik, dokumen lengkap."></textarea><div class="d-flex justify-content-between"><a href="{{ route('qc.incoming.index') }}" class="btn btn-secondary px-4">Batal</a><button type="submit" class="btn btn-primary px-4 fw-bold shadow"><i class="bi bi-save"></i> Simpan & Ajukan Approval</button></div></div></div>
</form>

@push('scripts')
<script>
function calcReject(idx,totalQty){const goodEl=document.querySelector(`.qty-good[data-idx='${idx}']`);const rejectEl=document.getElementById(`reject_${idx}`);let good=parseFloat(goodEl.value)||0;let total=parseFloat(totalQty)||0;if(good>total){alert('Jumlah Good tidak boleh melebihi Jumlah Diterima!');goodEl.value=total;good=total;}rejectEl.value=Math.round((total-good)*100)/100;}
</script>
@endpush
@endsection
