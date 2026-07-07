@extends('layouts.mms')

@section('title', $note->exists ? 'Edit Surat Jalan' : 'Buat Surat Jalan')

@section('content')
@php($isEdit = $note->exists)
<form method="POST" action="{{ $isEdit ? route('warehouse.delivery_notes.update', $note) : route('warehouse.delivery_notes.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">Info Pengiriman</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">No. SJ</label><input type="text" class="form-control fw-bold" value="{{ old('dn_number', $note->dn_number) }}" readonly></div>
                    <div class="mb-3">
                        <label class="form-label">Referensi SO <span class="text-danger">*</span></label>
                        <select name="sales_order_id" id="soSelect" class="form-select @error('sales_order_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Sales Order --</option>
                            @foreach($salesOrders as $order)
                                <option value="{{ $order->id }}" @selected((int) old('sales_order_id', $note->sales_order_id) === $order->id)>{{ $order->so_number }} - {{ $order->customer?->name }}</option>
                            @endforeach
                        </select>
                        @error('sales_order_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3"><label class="form-label">Tanggal Kirim</label><input type="date" name="dn_date" class="form-control" value="{{ old('dn_date', optional($note->dn_date)->format('Y-m-d') ?: now()->toDateString()) }}" required></div>
                    <div class="mb-3"><label class="form-label">Driver / Kurir</label><input type="text" name="driver_name" class="form-control" value="{{ old('driver_name', $note->driver_name) }}"></div>
                    <div class="mb-3"><label class="form-label">Plat Nomor</label><input type="text" name="vehicle_number" class="form-control" value="{{ old('vehicle_number', $note->vehicle_number) }}"></div>
                    <div class="mb-3"><label class="form-label">Catatan</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', $note->notes) }}</textarea></div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light"><strong>Barang yang Dikirim</strong></div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0 table-striped">
                        <thead class="table-light">
                            <tr><th>Nama Barang</th><th>Stok Gudang</th><th>Qty Order (SO)</th><th width="150">Qty Kirim</th><th>Satuan</th></tr>
                        </thead>
                        <tbody id="sjItems">
                            @forelse($itemRows as $row)
                                <tr>
                                    <td><strong>{{ $row['item_name'] }}</strong><small class="text-muted"> ({{ $row['item_code'] }})</small><input type="hidden" name="item_id[]" value="{{ $row['item_id'] }}"></td>
                                    <td>{{ $row['current_stock'] + 0 }}</td>
                                    <td>{{ $row['qty'] ?? '-' }}</td>
                                    <td><input type="number" name="qty_sent[]" class="form-control fw-bold border-success" value="{{ $row['qty_sent'] + 0 }}" step="0.01" min="0" required></td>
                                    <td>{{ $row['unit'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center py-5 text-muted">Pilih SO untuk memuat barang...</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @error('item_id')<div class="card-footer text-danger">{{ $message }}</div>@enderror
            </div>
            <div class="text-end">
                <a href="{{ route('warehouse.delivery_notes.index') }}" class="btn btn-secondary me-2">Batal</a>
                <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-save"></i> Simpan SJ</button>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('soSelect')?.addEventListener('change', async function () {
    const tbody = document.getElementById('sjItems');
    if (!this.value) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted">Pilih SO...</td></tr>';
        return;
    }
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5">Memuat data...</td></tr>';
    const url = '{{ route('warehouse.delivery_notes.so_items', ['order' => '__ID__']) }}'.replace('__ID__', this.value);
    const rows = await fetch(url).then(response => response.json());
    tbody.innerHTML = rows.length ? rows.map(item => `
        <tr>
            <td><strong>${item.item_name}</strong><small class="text-muted"> (${item.item_code})</small><input type="hidden" name="item_id[]" value="${item.item_id}"></td>
            <td>${Number(item.current_stock)}</td>
            <td>${Number(item.qty)}</td>
            <td><input type="number" name="qty_sent[]" class="form-control fw-bold border-success" value="${Number(item.qty_sent)}" step="0.01" min="0" max="${Number(item.qty_sent)}" required></td>
            <td>${item.unit ?? ''}</td>
        </tr>
    `).join('') : '<tr><td colspan="5" class="text-center py-5 text-muted">Semua item SO sudah terpenuhi pengirimannya.</td></tr>';
});
</script>
@endsection
