@extends('layouts.mms')

@section('title', 'Batch & Expiry Tracking')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-upc-scan"></i> Batch & Expiry Tracking</h3>
        <p class="text-muted mb-0">Kontrol batch number dan tanggal kedaluwarsa untuk raw material / finish good.</p>
    </div>
    <div class="text-md-end"><a href="{{ route('warehouse.batch_expiry.print', request()->only(['search', 'expiry'])) }}" target="_blank" class="btn btn-outline-dark"><i class="bi bi-printer"></i> Print</a></div>
</div>

<div class="row mb-4">
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Batch Aktif</small><h4 class="mb-0">{{ (int) $summary['total_batches'] }}</h4></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Near Expiry (&lt;=30 hari)</small><h4 class="mb-0 text-warning">{{ (int) $summary['near_expiry'] }}</h4></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Expired</small><h4 class="mb-0 text-danger">{{ (int) $summary['expired'] }}</h4></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Total Qty Batch</small><h4 class="mb-0">{{ number_format((float) $summary['total_qty'], 2, ',', '.') }}</h4></div></div></div>
</div>

<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white fw-bold">Input Batch Baru / Incoming</div>
            <div class="card-body">
                <form method="POST" action="{{ route('warehouse.batch_expiry.store_batch') }}">
                    @csrf
                    <div class="mb-2"><label class="form-label">Item</label><select name="item_id" class="form-select" required><option value="">-- Pilih Item --</option>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->item_code }} - {{ $item->item_name }}</option>@endforeach</select></div>
                    <div class="row">
                        <div class="col-md-6 mb-2"><label class="form-label">Batch Number</label><input type="text" name="batch_number" class="form-control" required></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Qty Masuk</label><input type="number" step="0.0001" min="0.0001" name="qty" class="form-control" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-2"><label class="form-label">MFG Date</label><input type="date" name="mfg_date" class="form-control"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Expiry Date</label><input type="date" name="expiry_date" class="form-control"></div>
                    </div>
                    <div class="mb-2"><label class="form-label">Ref Dokumen</label><input type="text" name="source_doc" class="form-control" placeholder="Contoh: GR-2602-0004"></div>
                    <div class="mb-3"><label class="form-label">Catatan</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Batch</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white fw-bold">Mutasi Batch (IN / OUT / ADJUST)</div>
            <div class="card-body">
                <form method="POST" action="{{ route('warehouse.batch_expiry.store_movement') }}">
                    @csrf
                    <div class="mb-2"><label class="form-label">Pilih Batch</label><select name="batch_id" class="form-select" required><option value="">-- Pilih Batch Aktif --</option>@foreach($batchOptions as $batch)<option value="{{ $batch->id }}">{{ $batch->item?->item_code }} | {{ $batch->batch_number }} | Qty: {{ number_format($batch->qty_available, 2, ',', '.') }} {{ $batch->unit ?: $batch->item?->unit }}</option>@endforeach</select></div>
                    <div class="row">
                        <div class="col-md-4 mb-2"><label class="form-label">Tipe</label><select name="movement_type" class="form-select"><option value="out">OUT</option><option value="in">IN</option><option value="adjust">ADJUST (+/-)</option></select></div>
                        <div class="col-md-4 mb-2"><label class="form-label">Qty</label><input type="number" step="0.0001" name="qty" class="form-control" required></div>
                        <div class="col-md-4 mb-2"><label class="form-label">Tanggal</label><input type="date" name="movement_date" value="{{ now()->toDateString() }}" class="form-control"></div>
                    </div>
                    <div class="mb-2"><label class="form-label">Ref Dokumen</label><input type="text" name="ref_doc" class="form-control" placeholder="ITR / SJ / Penyesuaian"></div>
                    <div class="mb-3"><label class="form-label">Catatan</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                    <button type="submit" class="btn btn-dark"><i class="bi bi-arrow-left-right"></i> Simpan Mutasi</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Cari item code / nama / batch..." value="{{ $search }}"></div>
            <div class="col-md-3"><select name="expiry" class="form-select"><option value="all" @selected($expiry === 'all')>Semua Expiry</option><option value="expired" @selected($expiry === 'expired')>Expired</option><option value="near" @selected($expiry === 'near')>Near Expiry (&lt;= 30 hari)</option><option value="safe" @selected($expiry === 'safe')>Safe (&gt; 30 hari)</option><option value="no_expiry" @selected($expiry === 'no_expiry')>Tanpa Expiry</option></select></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-2"><a href="{{ route('warehouse.batch_expiry.index') }}" class="btn btn-outline-secondary w-100">Reset</a></div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Item</th><th>Batch</th><th>MFG</th><th>Expiry</th><th class="text-end">Qty Available</th><th>Status</th><th>Ref</th></tr></thead>
                <tbody>
                @forelse($batches as $batch)
                    @php
                        $badge = '<span class="badge bg-secondary">NO EXPIRY</span>';
                        if ($batch->expiry_date) {
                            $badge = $batch->expiry_date->isPast()
                                ? '<span class="badge bg-danger">EXPIRED</span>'
                                : ($batch->expiry_date->lte(now()->addDays(30)) ? '<span class="badge bg-warning text-dark">NEAR EXPIRY</span>' : '<span class="badge bg-success">SAFE</span>');
                        }
                    @endphp
                    <tr>
                        <td><strong>{{ $batch->item?->item_code }}</strong><br><small class="text-muted">{{ $batch->item?->item_name }}</small></td>
                        <td class="fw-bold">{{ $batch->batch_number }}</td>
                        <td>{{ optional($batch->mfg_date)->format('d/m/Y') ?: '-' }}</td>
                        <td>{{ optional($batch->expiry_date)->format('d/m/Y') ?: '-' }}</td>
                        <td class="text-end fw-bold">{{ number_format($batch->qty_available, 2, ',', '.') }} {{ $batch->unit ?: $batch->item?->unit }}</td>
                        <td>{!! $badge !!}</td>
                        <td>{{ $batch->source_doc ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data batch.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold">Riwayat Mutasi Batch Terakhir</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead class="table-light"><tr><th>Tanggal</th><th>Item / Batch</th><th>Tipe</th><th class="text-end">Qty</th><th>Ref</th><th>Catatan</th></tr></thead>
                <tbody>
                @forelse($recentMoves as $move)
                    <tr>
                        <td>{{ optional($move->movement_date)->format('d/m/Y') }}</td>
                        <td><strong>{{ $move->batch?->item?->item_code }}</strong> - {{ $move->batch?->batch_number }}</td>
                        <td><span class="badge bg-light text-dark border">{{ strtoupper($move->movement_type) }}</span></td>
                        <td class="text-end fw-bold {{ $move->qty < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($move->qty, 2, ',', '.') }} {{ $move->batch?->unit ?: $move->batch?->item?->unit }}</td>
                        <td>{{ $move->ref_doc ?: '-' }}</td>
                        <td>{{ $move->notes ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">Belum ada mutasi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
