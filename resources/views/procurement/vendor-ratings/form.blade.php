@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Vendor Rating' : 'Input Vendor Rating')

@section('content')
@include('partials.alerts')
@php
    $totalPreview = round(((float) old('lead_time_score', $rating->lead_time_score) + (float) old('quality_score', $rating->quality_score) + (float) old('price_score', $rating->price_score)) / 3, 2);
@endphp

<div class="row mb-3">
    <div class="col-md-8">
        <h3 class="fw-bold"><i class="bi bi-star-half"></i> {{ $isEdit ? 'Edit Vendor Rating' : 'Input Vendor Rating' }}</h3>
        <p class="text-muted mb-0">Skor akan dihitung sebagai rata-rata lead time, kualitas, dan harga.</p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        @if($suppliers->isEmpty())
            <div class="alert alert-warning mb-0">Master supplier kosong. Tambahkan vendor terlebih dulu di menu Suppliers.</div>
        @else
            <form method="POST" action="{{ $isEdit ? route('procurement.vendor_ratings.update', $rating) : route('procurement.vendor_ratings.store') }}">
                @csrf
                @if($isEdit) @method('PUT') @endif

                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label">Vendor</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">-- Pilih Vendor --</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $rating->supplier_id) === (int) $supplier->id)>{{ $supplier->code }} - {{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Periode</label>
                        <input type="month" name="rating_period" class="form-control" value="{{ old('rating_period', $rating->rating_period) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Total Skor</label>
                        <input type="text" id="total_score_preview" class="form-control fw-bold text-primary" value="{{ number_format($totalPreview, 2, '.', '') }}" readonly>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Lead Time (0-100)</label>
                        <input type="number" min="0" max="100" step="0.01" name="lead_time_score" class="form-control score-input" value="{{ old('lead_time_score', $rating->lead_time_score) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Kualitas (0-100)</label>
                        <input type="number" min="0" max="100" step="0.01" name="quality_score" class="form-control score-input" value="{{ old('quality_score', $rating->quality_score) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Harga (0-100)</label>
                        <input type="number" min="0" max="100" step="0.01" name="price_score" class="form-control score-input" value="{{ old('price_score', $rating->price_score) }}" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Catatan Evaluasi</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Keterangan evaluasi vendor">{{ old('notes', $rating->notes) }}</textarea>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('procurement.vendor_ratings.index') }}" class="btn btn-secondary">Batal</a>
                    <button class="btn btn-primary px-5">Simpan Rating</button>
                </div>
            </form>
        @endif
    </div>
</div>

@push('scripts')
<script>
(function(){const out=document.getElementById('total_score_preview');const inputs=document.querySelectorAll('.score-input');const recalc=()=>{let sum=0,count=0;inputs.forEach(el=>{const value=parseFloat(el.value||'0');if(!Number.isNaN(value)){sum+=value;count++;}});if(out)out.value=(count?sum/count:0).toFixed(2);};inputs.forEach(el=>el.addEventListener('input',recalc));})();
</script>
@endpush
@endsection
