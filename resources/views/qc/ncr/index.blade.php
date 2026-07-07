@extends('layouts.mms')

@section('title', 'Non-Conformance Report')

@section('content')
@include('partials.alerts')
@php
    $statusBadge = [
        'open' => 'bg-danger',
        'analyzed' => 'bg-warning text-dark',
        'waiting_responsible' => 'bg-warning text-dark',
        'waiting_gm' => 'bg-warning text-dark',
        'appealed' => 'bg-danger',
        'approved' => 'bg-primary',
        'closed' => 'bg-success',
    ];
    $statusLabel = [
        'waiting_responsible' => 'Menunggu Penanggung Jawab',
        'waiting_gm' => 'Menunggu GM',
        'appealed' => 'Banding ke QC',
        'open' => 'Open',
        'analyzed' => 'Analyzed',
        'approved' => 'Approved',
        'closed' => 'Closed',
    ];
    $dispBadge = ['repair' => 'bg-info text-dark', 'scrap' => 'bg-dark', 'return_to_vendor' => 'bg-secondary'];
@endphp
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold text-danger"><i class="bi bi-exclamation-octagon-fill"></i> Laporan Ketidaksesuaian (NCR)</h3>
        <p class="text-muted">Manajemen produk reject dan analisis perbaikan.</p>
    </div>
    <div class="col-md-6 text-md-end">
        @if(auth()->user()?->hasPermission('qc_ncr_manage'))
            <a href="{{ route('qc.ncr.create') }}" class="btn btn-danger"><i class="bi bi-plus-lg"></i> Buat NCR Manual</a>
        @endif
    </div>
</div>

<div class="card shadow-sm border-start border-4 border-danger">
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>No. NCR</th><th>Tanggal</th><th>Sumber</th><th>Barang Reject</th><th>Isu / Masalah</th><th>Disposisi</th><th>Status</th><th class="text-center">Aksi</th></tr></thead>
            <tbody>
            @forelse($ncrs as $ncr)
                <tr>
                    <td><strong>{{ $ncr->ncr_number }}</strong></td>
                    <td>{{ optional($ncr->created_at)->format('d/m/y') }}</td>
                    <td>{{ strtoupper($ncr->source_type) }}</td>
                    <td><strong class="text-danger">{{ (float) $ncr->qty_reject + 0 }}</strong> {{ $ncr->item?->item_code }}<br><small>{{ $ncr->item?->item_name }}</small></td>
                    <td><small>{{ \Illuminate\Support\Str::limit($ncr->issue_description, 34) }}</small></td>
                    <td><span class="badge {{ $dispBadge[$ncr->disposition] ?? 'bg-light text-muted border' }}">{{ strtoupper(str_replace('_', ' ', $ncr->disposition ?: '-')) }}</span></td>
                    <td><span class="badge {{ $statusBadge[$ncr->status] ?? 'bg-light text-dark' }}">{{ $statusLabel[$ncr->status] ?? strtoupper((string) $ncr->status) }}</span></td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a href="{{ route('qc.ncr.print', $ncr) }}" target="_blank" class="btn btn-sm btn-outline-dark" title="Cetak NCR"><i class="bi bi-printer"></i></a>
                            @if(in_array($ncr->status, ['open','analyzed','waiting_responsible','appealed'], true) && auth()->user()?->hasPermission('qc_ncr_manage'))
                                <a href="{{ route('qc.ncr.edit', $ncr) }}" class="btn btn-sm btn-warning text-dark" title="Analisa / Edit"><i class="bi bi-pencil-square"></i></a>
                            @endif
                            @if($ncr->status === 'waiting_responsible' && auth()->user()?->hasPermission('qc_ncr_resp_approve'))
                                <form method="POST" action="{{ route('qc.ncr.sign_responsible', $ncr) }}" onsubmit="return confirm('Tanda tangan sebagai penanggung jawab?')">@csrf<button class="btn btn-sm btn-info text-white" title="Tanda Tangan"><i class="bi bi-pen"></i></button></form>
                            @endif
                            @if($ncr->status === 'waiting_gm' && auth()->user()?->hasPermission('qc_ncr_approve'))
                                <form method="POST" action="{{ route('qc.ncr.approve', $ncr) }}" onsubmit="return confirm('Setujui langkah perbaikan ini?')">@csrf<button class="btn btn-sm btn-success" title="Approve GM"><i class="bi bi-check-lg"></i></button></form>
                            @endif
                            @if($ncr->status === 'approved' && auth()->user()?->hasPermission('qc_ncr_manage'))
                                <form method="POST" action="{{ route('qc.ncr.close', $ncr) }}" onsubmit="return confirm('Tutup kasus NCR ini?')">@csrf<button class="btn btn-sm btn-dark" title="Tutup Kasus"><i class="bi bi-archive"></i></button></form>
                            @endif
                        </div>
                    </td>
                </tr>
                @if($ncr->status === 'waiting_responsible' && auth()->user()?->hasPermission('qc_ncr_manage'))
                    <tr>
                        <td colspan="8" class="bg-light">
                            <form method="POST" action="{{ route('qc.ncr.assign_responsible', $ncr) }}" class="d-flex gap-2 align-items-center justify-content-end">
                                @csrf
                                <span class="small text-muted">Assign penanggung jawab:</span>
                                <select name="operator_id" class="form-select form-select-sm w-auto" required>
                                    <option value="">Pilih Operator</option>
                                    @foreach($operators as $operator)
                                        <option value="{{ $operator->id }}" @selected((int) $ncr->operator_id === (int) $operator->id)>{{ $operator->fullname }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-outline-primary">Assign</button>
                            </form>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="8" class="text-center text-muted py-5">Belum ada data NCR.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
