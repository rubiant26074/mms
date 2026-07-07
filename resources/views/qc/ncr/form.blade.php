@extends('layouts.mms')

@section('title', $isEdit ? 'Analisa NCR' : 'Buat NCR Baru')

@section('content')
@include('partials.alerts')
<form method="POST" action="{{ $isEdit ? route('qc.ncr.update', $ncr) : route('qc.ncr.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="row">
        <div class="col-md-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-danger text-white">Informasi Ketidaksesuaian</div>
                <div class="card-body">
                    <div class="mb-3"><label>No. NCR</label><input type="text" class="form-control fw-bold" value="{{ $ncr->ncr_number }}" readonly></div>
                    <div class="mb-3"><label>Sumber Masalah</label><select name="source_type" class="form-select"><option value="production" @selected(old('source_type', $ncr->source_type) === 'production')>Produksi (Internal)</option><option value="incoming" @selected(old('source_type', $ncr->source_type) === 'incoming')>Incoming (Supplier)</option></select></div>
                    <div class="mb-3"><label>Barang / Material</label><select name="item_id" class="form-select" required><option value="">-- Pilih Barang --</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected((int) old('item_id', $ncr->item_id) === (int) $item->id)>{{ $item->item_code }} - {{ $item->item_name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label>Jumlah Reject (Qty)</label><input type="number" name="qty_reject" class="form-control border-danger text-danger fw-bold" value="{{ old('qty_reject', (float) $ncr->qty_reject) }}" step="0.01" required></div>
                    <div class="mb-3"><label>Deskripsi Masalah (Problem)</label><textarea name="issue_description" class="form-control" rows="3" required>{{ old('issue_description', $ncr->issue_description) }}</textarea></div>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-warning text-dark">Analisa & Tindakan Perbaikan</div>
                <div class="card-body">
                    <div class="mb-3"><label class="fw-bold">Akar Penyebab (Root Cause)</label><textarea name="root_cause" class="form-control" rows="3">{{ old('root_cause', $ncr->root_cause) }}</textarea></div>
                    <div class="mb-3"><label class="fw-bold">Tindakan Perbaikan (Corrective Action)</label><textarea name="corrective_action" class="form-control" rows="3">{{ old('corrective_action', $ncr->corrective_action) }}</textarea></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label>Penanggung Jawab</label><select name="operator_id" class="form-select"><option value="">-- Tidak Ada / Umum --</option>@foreach($operators as $operator)<option value="{{ $operator->id }}" @selected((int) old('operator_id', $ncr->operator_id) === (int) $operator->id)>{{ $operator->fullname }}</option>@endforeach</select></div>
                        <div class="col-md-6 mb-3"><label>Disposisi</label><select name="disposition" class="form-select fw-bold">@foreach(['pending'=>'Pending','repair'=>'Repair','scrap'=>'Scrap','return_to_vendor'=>'Return to Vendor'] as $key => $label)<option value="{{ $key }}" @selected(old('disposition', $ncr->disposition) === $key)>{{ $label }}</option>@endforeach</select></div>
                    </div>
                    @if($ncr->status === 'waiting_responsible' && auth()->user()?->hasPermission('qc_ncr_resp_approve'))
                        <div class="border rounded p-3 bg-light">
                            <label class="form-label fw-bold">Banding Penanggung Jawab</label>
                            <div class="d-flex gap-2">
                                <input type="text" form="appeal-form" name="appeal_note" class="form-control" placeholder="Alasan banding">
                                <button type="submit" form="appeal-form" class="btn btn-outline-danger">Kirim Banding</button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            <div class="text-end"><a href="{{ route('qc.ncr.index') }}" class="btn btn-secondary">Kembali</a><button class="btn btn-primary px-4 fw-bold"><i class="bi bi-save"></i> Simpan Analisa</button></div>
        </div>
    </div>
</form>
@if($ncr->exists)
    <form id="appeal-form" method="POST" action="{{ route('qc.ncr.appeal', $ncr) }}">@csrf</form>
@endif
@endsection
