@extends('layouts.mms')

@section('title', 'Surat Jalan')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-truck"></i> Surat Jalan</h3>
        <p class="text-muted mb-0">Pengiriman barang jadi ke customer.</p>
    </div>
    <div class="text-md-end"><a href="{{ route('warehouse.delivery_notes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Buat Surat Jalan</a></div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Cari No. SJ / Customer / SO..." value="{{ $search }}"></div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    @foreach(['draft' => 'Draft', 'approved' => 'Approved', 'sent' => 'Sent', 'returned' => 'Returned'] as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('warehouse.delivery_notes.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr><th>No. SJ</th><th>Tgl Kirim</th><th>Customer</th><th>Ref. SO</th><th>Status</th><th class="text-center">Aksi</th></tr>
                </thead>
                <tbody>
                @forelse($notes as $note)
                    @php
                        $badge = match($note->status) {
                            'draft' => 'bg-secondary',
                            'approved' => 'bg-primary',
                            'sent' => 'bg-success',
                            'returned' => 'bg-danger',
                            default => 'bg-light text-dark',
                        };
                    @endphp
                    <tr>
                        <td><strong>{{ $note->dn_number }}</strong></td>
                        <td>{{ optional($note->dn_date)->format('d/m/Y') }}</td>
                        <td>{{ $note->salesOrder?->customer?->name ?? '-' }}</td>
                        <td>{{ $note->salesOrder?->so_number ?? '-' }}</td>
                        <td><span class="badge {{ $badge }}">{{ strtoupper($note->status) }}</span></td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('warehouse.delivery_notes.print', $note) }}" target="_blank" class="btn btn-outline-dark" title="Cetak Surat Jalan"><i class="bi bi-printer"></i></a>
                                @if($note->status === 'draft')
                                    <a href="{{ route('warehouse.delivery_notes.edit', $note) }}" class="btn btn-warning text-dark" title="Edit Surat Jalan"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" action="{{ route('warehouse.delivery_notes.approve', $note) }}" onsubmit="return confirm('Approve pengiriman ini? Stok akan berkurang.')" class="d-inline">@csrf<button class="btn btn-success" title="Approve Surat Jalan"><i class="bi bi-check-lg"></i> Approve</button></form>
                                    <form method="POST" action="{{ route('warehouse.delivery_notes.destroy', $note) }}" onsubmit="return confirm('Hapus SJ?')" class="d-inline">@csrf @method('DELETE')<button class="btn btn-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>
                                @endif
                                @if(in_array($note->status, ['approved', 'sent'], true))
                                    <a href="{{ route('warehouse.delivery_notes.sign', $note) }}" class="btn btn-primary" title="Tanda Tangan Penerima"><i class="bi bi-pencil-fill"></i> Tanda Tangan</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada Surat Jalan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
