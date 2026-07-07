@extends('layouts.mms')

@section('title', 'QC Hasil Produksi')

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-clipboard-check"></i> QC Hasil Produksi</h3>
        <p class="text-muted">Inspeksi akhir barang jadi sebelum masuk gudang.</p>
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-warning">
    <div class="card-header bg-white"><h6 class="mb-0 fw-bold text-warning"><i class="bi bi-hourglass-split"></i> Menunggu Inspeksi</h6></div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>No. SPK</th><th>Deadline</th><th>Project</th><th>Barang Jadi</th><th>Status Prod</th><th class="text-center">Aksi</th></tr></thead>
            <tbody>
            @forelse($pending as $spk)
                <tr>
                    <td><strong>{{ $spk->spk_number }}</strong></td>
                    <td>{{ optional($spk->deadline_date)->format('d/m/Y') ?: '-' }}</td>
                    <td>{{ $spk->project_name ?: '-' }}</td>
                    <td>{{ $spk->salesOrder?->items->map(fn($row) => $row->item?->item_name ?: $row->item_name_manual)->filter()->join(', ') ?: '-' }}</td>
                    <td><span class="badge bg-success">{{ strtoupper(str_replace('_', ' ', $spk->status)) }}</span></td>
                    <td class="text-center">
                        @if(auth()->user()?->hasPermission('qc_production_manage'))
                            <a href="{{ route('qc.production.inspect', ['spk_id' => $spk->id]) }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-search"></i> Inspect</a>
                        @else
                            <span class="text-muted small">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4 text-muted">Tidak ada barang produksi selesai yang menunggu QC.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm border-start border-4 border-success">
    <div class="card-header bg-white"><h6 class="mb-0 fw-bold text-success"><i class="bi bi-check-all"></i> Riwayat QC Selesai</h6></div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>No. QC</th><th>Tgl QC</th><th>No. SPK</th><th>Barang</th><th>Hasil</th><th>Inspector</th><th class="text-center">Aksi</th></tr></thead>
            <tbody>
            @forelse($history as $qc)
                <tr>
                    <td><strong>{{ $qc->qc_number }}</strong></td>
                    <td>{{ optional($qc->qc_date)->format('d/m/Y') }}</td>
                    <td>{{ $qc->spk?->spk_number ?: '-' }}</td>
                    <td>{{ $qc->spk?->salesOrder?->items->map(fn($row) => $row->item?->item_name ?: $row->item_name_manual)->filter()->join(', ') ?: '-' }}</td>
                    <td><span class="badge bg-success">{{ (float) $qc->qty_pass + 0 }} OK</span>@if((float) $qc->qty_reject > 0)<span class="badge bg-danger ms-1">{{ (float) $qc->qty_reject + 0 }} NG</span>@endif</td>
                    <td>{{ $qc->inspector?->fullname ?: '-' }}</td>
                    <td class="text-center"><a href="{{ route('qc.production.print', $qc) }}" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer"></i> Print Verifikasi</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-4 text-muted">Riwayat QC produksi belum ada.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
