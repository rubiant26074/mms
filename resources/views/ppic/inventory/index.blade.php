@extends('layouts.mms')

@section('title', 'Inventory Control')

@section('content')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-boxes"></i> Inventory Control</h3>
        <p class="text-muted">Monitoring stok bahan baku dan WIP untuk perencanaan produksi.</p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="inventory-filter-form">
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="">- Semua Tipe Material -</option>
                    <option value="raw_material" @selected($type === 'raw_material')>Raw Material</option>
                    <option value="consumable" @selected($type === 'consumable')>Consumable</option>
                    <option value="wip" @selected($type === 'wip')>WIP (Setengah Jadi)</option>
                    <option value="finish_good" @selected($type === 'finish_good')>Finish Good</option>
                </select>
            </div>
            <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Cari Kode / Nama Barang..." value="{{ $search }}" autocomplete="off"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Cari</button></div>
            <div class="col-md-1"><a href="{{ route('ppic.inventory.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th>Kepemilikan</th><th class="text-center">Min. Stok</th><th class="text-center">Stok Saat Ini</th><th class="text-center">Status</th><th class="text-center">Aksi</th></tr></thead>
                <tbody>
                @forelse($items as $row)
                    @php
                        $stock = (float) $row->current_stock;
                        $min = (float) $row->min_stock;
                        $isEmpty = $stock <= 0;
                        $isCritical = !$isEmpty && $stock <= $min;
                    @endphp
                    <tr class="{{ $isEmpty ? 'bg-danger bg-opacity-10' : ($isCritical ? 'bg-warning bg-opacity-10' : '') }}">
                        <td class="fw-bold">{{ $row->item_code }}</td>
                        <td>{{ $row->item_name }}</td>
                        <td>{{ ucwords(str_replace('_',' ', $row->item_type)) }}</td>
                        <td>{!! $row->ownership === 'customer' ? '<span class="badge bg-info text-dark">Consignment</span>' : '<span class="badge bg-light text-muted border">Internal</span>' !!}</td>
                        <td class="text-center">{{ $min + 0 }}</td>
                        <td class="text-center fw-bold fs-6">{{ $stock + 0 }} {{ $row->unit }}</td>
                        <td class="text-center">
                            @if($isEmpty)<span class="badge bg-dark">KOSONG</span>
                            @elseif($isCritical)<span class="badge bg-danger">KRITIS</span>
                            @else<span class="badge bg-success">AMAN</span>@endif
                        </td>
                        <td class="text-center"><a href="{{ route('ppic.inventory.show', $row) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-card-list"></i> Kartu Stok</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center py-4 text-muted">Data tidak ditemukan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@push('scripts')
<script>
(function(){const form=document.getElementById('inventory-filter-form');const search=form?.querySelector('input[name="search"]');const type=form?.querySelector('select[name="type"]');let t;search?.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>form.requestSubmit?form.requestSubmit():form.submit(),400)});type?.addEventListener('change',()=>form.requestSubmit?form.requestSubmit():form.submit());})();
</script>
@endpush
@endsection
