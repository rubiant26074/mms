@extends('layouts.mms')

@section('title', 'Bill of Material')

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-diagram-3"></i> Bill of Material</h3>
        <p class="text-muted">Resep material untuk Finish Good / WIP.</p>
    </div>
    <div class="col-md-6 text-end"><a href="{{ route('engineering.boms.create') }}" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Buat BOM Baru</a></div>
</div>

@if(!empty($pendingSoItems))
<div class="card shadow-sm mb-4 border-start border-4 border-warning">
    <div class="card-header bg-warning-subtle text-dark fw-bold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Item Sales Order (Approved) yang Belum Punya BOM ({{ count($pendingSoItems) }})</span>
        <span class="badge bg-warning text-dark">Siap Dibuat BOM</span>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">No. SO</th>
                    <th>Customer</th>
                    <th>Item / Produk SO</th>
                    <th class="text-center">Qty Order</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pendingSoItems as $p)
                <tr>
                    <td class="ps-3 fw-bold text-primary">{{ $p['so_number'] }}</td>
                    <td>{{ $p['customer_name'] }}</td>
                    <td><strong>{{ $p['item_code'] }}</strong><br><span class="text-muted small">{{ $p['item_name'] }}</span></td>
                    <td class="text-center">{{ $p['qty'] + 0 }} {{ $p['unit'] }}</td>
                    <td class="text-center">
                        <a href="{{ route('engineering.boms.create', ['so_id' => $p['so_id'], 'item_id' => $p['item_id']]) }}" class="btn btn-sm btn-primary py-1 px-3">
                            <i class="bi bi-diagram-3"></i> Tarik & Buat BOM
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="bom-filter-form">
            <div class="col-md-5"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="text" name="search" class="form-control" placeholder="Cari kode BOM / item..." value="{{ $search }}" autocomplete="off"></div></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('engineering.boms.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>
<div class="card shadow-sm">
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-4">Kode BOM</th><th>Item Hasil</th><th class="text-center">Qty Hasil</th><th class="text-center">Komponen</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
            <tbody>
            @forelse($boms as $bom)
                <tr>
                    <td class="ps-4 fw-bold text-primary">{{ $bom->bom_code }}</td>
                    <td><strong>{{ $bom->item?->item_code }}</strong><br><span>{{ $bom->item?->item_name }}</span></td>
                    <td class="text-center">{{ $bom->qty_result + 0 }} <span class="small text-muted">{{ $bom->item?->unit }}</span></td>
                    <td class="text-center"><span class="badge bg-info text-dark">{{ $bom->details_count }} item</span></td>
                    <td><span class="badge {{ $bom->status === 'active' ? 'bg-success' : ($bom->status === 'locked' ? 'bg-dark' : 'bg-secondary') }}">{{ strtoupper($bom->status) }}</span></td>
                    <td class="text-center"><div class="btn-group"><a href="{{ route('engineering.boms.edit', $bom) }}" class="btn btn-sm btn-warning text-white"><i class="bi bi-pencil"></i></a>@if($bom->status !== 'locked')<form method="POST" action="{{ route('engineering.boms.destroy', $bom) }}" onsubmit="return confirm('Hapus BOM ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>@endif</div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-5 text-muted">Data BOM belum ada.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@push('scripts')
<script>
(function () { const form = document.getElementById('bom-filter-form'); const search = form?.querySelector('input[name="search"]'); let t; search?.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => form.requestSubmit ? form.requestSubmit() : form.submit(), 400); }); })();
</script>
@endpush
@endsection
