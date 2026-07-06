@extends('layouts.mms')

@section('title', $isEdit ? 'Edit SPK' : 'Buat SPK Baru')

@php
    $internalProcesses = ['Fibre Laser', 'CO Laser', 'Metal Bending', 'Acrylic Bending', 'Welding', 'Assembling'];
    $subconProcesses = ['Powder Coating', 'Plating', 'Hot Deep Galv', 'Machining'];
    $selected = old('processes', array_filter(array_map('trim', explode(',', (string) $spk->required_processes))));
@endphp

@section('content')
@include('partials.alerts')
@if($missing)
    <div class="alert alert-danger shadow-sm border-start border-5 border-danger">
        <h5 class="alert-heading fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill"></i> STOP! BOM Belum Lengkap</h5>
        Item berikut belum memiliki BOM: <strong>{{ implode(', ', $missing) }}</strong>
    </div>
@endif
<form method="POST" action="{{ $isEdit ? route('ppic.spk.update', $spk) : route('ppic.spk.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <fieldset {{ $missing ? 'disabled' : '' }}>
        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-primary text-white fw-bold">Data Utama SPK</div>
                    <div class="card-body">
                        <div class="mb-3"><label class="fw-bold">No. SPK</label><input type="text" class="form-control fw-bold bg-light" value="{{ $spk->spk_number }}" readonly></div>
                        <div class="mb-3"><label class="fw-bold">Ref. Sales Order <span class="text-danger">*</span></label><select name="sales_order_id" class="form-select" onchange="location.href='{{ route('ppic.spk.create') }}?so_id='+this.value" required><option value="">-- Pilih SO --</option>@foreach($salesOrders as $so)<option value="{{ $so->id }}" @selected((int) old('sales_order_id', $spk->sales_order_id) === $so->id)>{{ $so->so_number }}</option>@endforeach</select></div>
                        <div class="mb-3"><label>Tgl SPK</label><input type="date" name="spk_date" class="form-control" value="{{ old('spk_date', optional($spk->spk_date)->format('Y-m-d') ?: now()->toDateString()) }}" required></div>
                        <div class="mb-3"><label>Target Selesai</label><input type="date" name="deadline_date" class="form-control" value="{{ old('deadline_date', optional($spk->deadline_date)->format('Y-m-d') ?: now()->addDays(7)->toDateString()) }}" required></div>
                        <div class="mb-3"><label>Prioritas</label><select name="priority" class="form-select fw-bold"><option value="normal" @selected(old('priority', $spk->priority ?: 'normal') === 'normal')>Normal</option><option value="urgent" @selected(old('priority', $spk->priority) === 'urgent')>Urgent</option></select></div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card shadow-sm mb-3 border-warning">
                    <div class="card-header bg-warning text-dark fw-bold">Item Produksi dari SO</div>
                    <div class="card-body p-0"><table class="table table-striped mb-0 small"><thead class="table-light text-center"><tr><th>Kode</th><th>Nama Barang</th><th>Material</th><th>Qty</th></tr></thead><tbody>@forelse($soItems as $item)<tr><td class="fw-bold px-3">{{ $item->item?->item_code ?: $item->item_code_manual }}</td><td>{{ $item->item?->item_name ?: $item->item_name_manual }}</td><td>{{ $item->material_manual ?: $item->item?->description ?: '-' }}</td><td class="text-center fw-bold text-primary">{{ $item->qty + 0 }} {{ $item->unit_manual ?: $item->item?->unit }}</td></tr>@empty<tr><td colspan="4" class="text-center py-3 text-muted">Silakan pilih Sales Order</td></tr>@endforelse</tbody></table></div>
                </div>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-light fw-bold">Analisa Kebutuhan Material (MRP)</div>
                    <div class="card-body p-0"><table class="table table-bordered mb-0 small"><thead class="table-light text-center"><tr><th>Material</th><th width="80">Stok</th><th width="120">Dibutuhkan</th><th width="80">Unit</th></tr></thead><tbody>@forelse($materials as $m)<tr><td>{{ $m['item_name'] }}</td><td class="text-center {{ ($m['current_stock'] ?? 0) < $m['qty_required'] ? 'text-danger fw-bold' : 'text-success' }}">{{ ($m['current_stock'] ?? 0) + 0 }}</td><td class="text-end fw-bold">{{ round($m['qty_required'], 4) }}</td><td class="text-center">{{ $m['unit'] }}</td></tr>@empty<tr><td colspan="4" class="text-center py-4 text-muted">Pilih Sales Order untuk memuat kebutuhan material</td></tr>@endforelse</tbody></table></div>
                </div>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-light fw-bold">Route Proses Produksi</div>
                    <div class="card-body">
                        <div class="row g-2"><div class="col-md-2 fw-bold small text-muted">INTERNAL</div><div class="col-md-10">@foreach($internalProcesses as $proc)<div class="form-check form-check-inline me-3 mb-2"><input class="form-check-input" type="checkbox" name="processes[]" value="{{ $proc }}" @checked(in_array($proc, $selected, true))><label class="form-check-label small">{{ $proc }}</label></div>@endforeach</div></div>
                        <hr class="my-2">
                        <div class="row g-2"><div class="col-md-2 fw-bold small text-muted">SUB-CON</div><div class="col-md-10">@foreach($subconProcesses as $proc)<div class="form-check form-check-inline me-3 mb-2"><input class="form-check-input" type="checkbox" name="processes[]" value="{{ $proc }}" @checked(in_array($proc, $selected, true))><label class="form-check-label small">{{ $proc }}</label></div>@endforeach</div></div>
                    </div>
                </div>
                <div class="mb-3"><label class="fw-bold small">Catatan Produksi</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', $spk->notes) }}</textarea></div>
                <div class="text-end mb-5"><a href="{{ route('ppic.spk.index') }}" class="btn btn-secondary px-4 me-2">Batal</a><button class="btn btn-primary btn-lg px-5 shadow fw-bold"><i class="bi bi-save-fill me-2"></i> SIMPAN & RILIS SPK</button></div>
            </div>
        </div>
    </fieldset>
</form>
@endsection
