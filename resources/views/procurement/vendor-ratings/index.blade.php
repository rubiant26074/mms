@extends('layouts.mms')

@section('title', 'Vendor Rating')

@section('content')
@include('partials.alerts')
@php
    $grade = function ($score) {
        $score = (float) $score;
        if ($score >= 85) return ['A', 'bg-success'];
        if ($score >= 70) return ['B', 'bg-primary'];
        if ($score >= 55) return ['C', 'bg-warning text-dark'];
        return ['D', 'bg-danger'];
    };
@endphp

<div class="row mb-3">
    <div class="col-md-8">
        <h3 class="fw-bold"><i class="bi bi-star-half"></i> Vendor Rating</h3>
        <p class="text-muted mb-0">Penilaian vendor berdasarkan lead time, kualitas, dan harga.</p>
    </div>
    <div class="col-md-4 text-md-end mt-2 mt-md-0">
        @if(auth()->user()?->hasPermission('purch_vendor_manage'))
            <a href="{{ route('procurement.vendor_ratings.create') }}" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Input Rating</a>
        @endif
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="vendor-rating-filter">
            <div class="col-md-3">
                <input type="month" name="period" class="form-control" value="{{ $period }}">
            </div>
            <div class="col-md-5">
                <select name="supplier_id" class="form-select">
                    <option value="">Semua Vendor</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((int) $supplierId === (int) $supplier->id)>{{ $supplier->code }} - {{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('procurement.vendor_ratings.index') }}" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a></div>
            <div class="col-md-1"><a href="{{ route('procurement.vendor_ratings.print', ['period' => $period, 'supplier_id' => $supplierId]) }}" target="_blank" class="btn btn-outline-dark w-100" title="Print"><i class="bi bi-printer"></i></a></div>
        </form>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4 mb-2 mb-md-0">
        <div class="card shadow-sm border-0 bg-light"><div class="card-body"><div class="small text-muted">Jumlah Penilaian</div><div class="h4 mb-0">{{ $ratings->count() }}</div></div></div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 bg-light"><div class="card-body"><div class="small text-muted">Rata-rata Skor</div><div class="h4 mb-0">{{ number_format((float) $averageScore, 2, ',', '.') }}</div></div></div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Periode</th>
                    <th>Vendor</th>
                    <th class="text-end">Lead Time</th>
                    <th class="text-end">Kualitas</th>
                    <th class="text-end">Harga</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Grade</th>
                    <th>Catatan</th>
                    @if(auth()->user()?->hasPermission('purch_vendor_manage'))<th class="text-center" width="120">Aksi</th>@endif
                </tr>
            </thead>
            <tbody>
            @forelse($ratings as $rating)
                @php([$label, $badge] = $grade($rating->total_score))
                <tr>
                    <td>{{ $rating->rating_period }}</td>
                    <td><strong>{{ $rating->supplier?->code ?: '-' }}</strong> - {{ $rating->supplier?->name ?: '-' }}</td>
                    <td class="text-end">{{ number_format((float) $rating->lead_time_score, 2, ',', '.') }}</td>
                    <td class="text-end">{{ number_format((float) $rating->quality_score, 2, ',', '.') }}</td>
                    <td class="text-end">{{ number_format((float) $rating->price_score, 2, ',', '.') }}</td>
                    <td class="text-end fw-bold">{{ number_format((float) $rating->total_score, 2, ',', '.') }}</td>
                    <td class="text-center"><span class="badge {{ $badge }}">{{ $label }}</span></td>
                    <td>{{ $rating->notes ?: '-' }}</td>
                    @if(auth()->user()?->hasPermission('purch_vendor_manage'))
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="{{ route('procurement.vendor_ratings.edit', $rating) }}" class="btn btn-sm btn-warning text-dark" title="Edit"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('procurement.vendor_ratings.destroy', $rating) }}" onsubmit="return confirm('Hapus data rating ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>
                            </div>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ auth()->user()?->hasPermission('purch_vendor_manage') ? 9 : 8 }}" class="text-center text-muted py-4">Belum ada data vendor rating.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
(function(){const form=document.getElementById('vendor-rating-filter');form?.querySelectorAll('input,select').forEach(el=>el.addEventListener('change',()=>form.requestSubmit?form.requestSubmit():form.submit()));})();
</script>
@endpush
@endsection
