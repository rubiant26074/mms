@extends('layouts.mms')

@section('title', 'Ajukan Material')

@section('content')
@include('partials.alerts')
<form method="POST" action="{{ route('warehouse.material_issues.store') }}">
    @csrf
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">Info Pengajuan</div>
                <div class="card-body">
                    <div class="mb-3"><label>Referensi SPK</label><select name="spk_id" class="form-select" required onchange="window.location.href='{{ route('warehouse.material_issues.create') }}?spk_id='+this.value"><option value="">-- Pilih SPK --</option>@foreach($spkOptions as $opt)<option value="{{ $opt->id }}" @selected($spk?->id === $opt->id)>{{ $opt->spk_number }} - {{ \Illuminate\Support\Str::limit($opt->project_name, 24) }}</option>@endforeach</select><small class="text-muted">Hanya menampilkan SPK yang belum dibuatkan ITR.</small></div>
                    <div class="mb-3"><label>Tanggal Request</label><input type="date" name="itr_date" class="form-control" value="{{ old('itr_date', optional($issue->itr_date)->format('Y-m-d') ?: now()->toDateString()) }}" required></div>
                    <div class="mb-3"><label>Pemohon (Produksi)</label><input type="text" name="received_by" class="form-control" value="{{ old('received_by', $issue->received_by) }}" required></div>
                    <div class="mb-3"><label>Catatan</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-info text-dark">Daftar Material (Berdasarkan BOM SPK)</div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light"><tr><th>Material</th><th>Stok Gudang</th><th>Kebutuhan SPK</th><th width="150">Jml Diminta</th><th>Satuan</th></tr></thead>
                        <tbody>
                        @forelse($spk?->materials ?? [] as $mat)
                            <tr>
                                <td><strong>{{ $mat->item?->item_name }}</strong><br><small class="text-muted">{{ $mat->item?->item_code }}</small><input type="hidden" name="item_id[]" value="{{ $mat->item_id }}"></td>
                                <td>{{ (float) ($mat->item?->current_stock ?? 0) + 0 }}</td>
                                <td>{{ (float) $mat->qty_required + 0 }}</td>
                                <td><input type="number" name="qty_issued[]" class="form-control fw-bold border-primary" value="{{ (float) $mat->qty_required + 0 }}" step="0.0001" min="0" required></td>
                                <td>{{ $mat->item?->unit }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-5 text-muted">Pilih SPK terlebih dahulu untuk memuat daftar material.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white text-end"><a href="{{ route('warehouse.material_issues.index') }}" class="btn btn-secondary me-2">Batal</a><button class="btn btn-primary px-4" @disabled(! $spk)><i class="bi bi-send"></i> Ajukan Permintaan</button></div>
            </div>
        </div>
    </div>
</form>
@endsection
