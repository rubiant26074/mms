@extends('layouts.mms')

@section('title', 'Material Return')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-arrow-return-left"></i> Material Return</h3>
        <p class="text-muted mb-0">Pengembalian sisa material produksi ke Gudang.</p>
    </div>
    <div class="text-md-end"><a href="{{ route('warehouse.material_returns.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Buat Pengembalian</a></div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Cari No. Retur / SPK / Pengembali / Penerima..." value="{{ $search }}"></div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    @foreach(['request' => 'Request', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('warehouse.material_returns.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr><th>No. Retur</th><th>Tanggal</th><th>Ref. SPK</th><th>Dikembalikan Oleh</th><th>Diterima Gudang</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                @forelse($returns as $return)
                    @php
                        $badge = match($return->status) {
                            'request' => 'bg-warning text-dark',
                            'approved' => 'bg-success',
                            'rejected' => 'bg-danger',
                            default => 'bg-secondary',
                        };
                    @endphp
                    <tr>
                        <td><strong>{{ $return->ret_number }}</strong></td>
                        <td>{{ optional($return->ret_date)->format('d/m/Y') }}</td>
                        <td>{{ $return->spk?->spk_number ?? '-' }}</td>
                        <td>{{ $return->returned_by }}</td>
                        <td>{{ $return->received_by ?: '-' }}</td>
                        <td><span class="badge {{ $badge }}">{{ strtoupper($return->status) }}</span></td>
                        <td>
                            @if($return->status === 'request')
                                <div class="btn-group">
                                    <form method="POST" action="{{ route('warehouse.material_returns.approve', $return) }}" onsubmit="return confirm('Terima barang ini dan update stok?')">@csrf<button class="btn btn-sm btn-success fw-bold"><i class="bi bi-check-lg"></i> Terima</button></form>
                                    <form method="POST" action="{{ route('warehouse.material_returns.destroy', $return) }}" onsubmit="return confirm('Hapus request return ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button></form>
                                </div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pengembalian material.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
