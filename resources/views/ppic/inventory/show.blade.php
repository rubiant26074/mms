@extends('layouts.mms')

@section('title', 'Kartu Stok: ' . $item->item_code)

@section('content')
<div class="row mb-3">
    <div class="col-md-8">
        <a href="{{ route('ppic.inventory.index') }}" class="btn btn-secondary mb-3"><i class="bi bi-arrow-left"></i> Kembali</a>
        <h4 class="fw-bold text-primary">{{ $item->item_name }}</h4>
        <span class="badge bg-dark">{{ $item->item_code }}</span>
        <span class="badge bg-secondary">{{ ucwords(str_replace('_',' ', $item->item_type)) }}</span>
        @if($item->ownership === 'customer')<span class="badge bg-info text-dark">CONSIGNMENT</span>@endif
    </div>
    <div class="col-md-4 text-end">
        <div class="card border-primary text-center"><div class="card-body py-2"><small class="text-muted">Stok Saat Ini</small><h2 class="fw-bold text-primary mb-0">{{ $item->current_stock + 0 }} <span class="fs-6 text-muted">{{ $item->unit }}</span></h2></div></div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light"><strong>Riwayat Mutasi (100 Transaksi Terakhir)</strong></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped mb-0">
                <thead class="table-dark text-center"><tr><th>Tanggal</th><th>Dokumen</th><th>Tipe</th><th>Keterangan / Pihak</th><th>Masuk</th><th>Keluar</th></tr></thead>
                <tbody>
                @forelse($history as $row)
                    @php $in = $row->type === 'IN' ? (float) $row->qty : 0; $out = $row->type === 'OUT' ? (float) $row->qty : 0; @endphp
                    <tr>
                        <td class="text-center">{{ $row->date ? date('d/m/Y', strtotime($row->date)) : '-' }}</td>
                        <td class="text-center fw-bold">{{ $row->doc_no }}</td>
                        <td class="text-center">{!! $row->type === 'IN' ? '<span class="badge bg-success">IN</span>' : '<span class="badge bg-danger">OUT</span>' !!}</td>
                        <td><strong>{{ $row->party }}</strong>@if($row->description)<br><small class="text-muted">{{ $row->description }}</small>@endif</td>
                        <td class="text-end text-success fw-bold">{{ $in > 0 ? '+' . ($in + 0) : '-' }}</td>
                        <td class="text-end text-danger fw-bold">{{ $out > 0 ? '-' . ($out + 0) : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada transaksi mutasi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
