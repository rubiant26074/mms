@extends('layouts.mms')

@section('title', 'QC Incoming')

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6"><h3 class="fw-bold"><i class="bi bi-shield-check"></i> QC Incoming Material</h3><p class="text-muted">Pemeriksaan kualitas material masuk dari Supplier/Customer.</p></div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-warning">
    <div class="card-header bg-white"><h6 class="mb-0 fw-bold text-warning"><i class="bi bi-hourglass-split"></i> Pending Inspection (Menunggu Pemeriksaan)</h6></div>
    <div class="card-body p-0 table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>No. GR</th><th>Tgl Terima</th><th>Sumber</th><th>Item Masuk</th><th>Penerima</th><th class="text-center">Aksi</th></tr></thead><tbody>
        @forelse($pending as $row)
            @php $source = $row->receipt_type === 'consignment' ? ($row->customer?->name ?: '-') : ($row->purchaseOrder?->supplier?->name ?: '-'); @endphp
            <tr><td><strong>{{ $row->gr_number }}</strong></td><td>{{ optional($row->gr_date)->format('d/m/Y') }}</td><td>@if($row->receipt_type === 'consignment')<span class="badge bg-info text-dark">Consignment</span><br>@endif{{ $source }}</td><td><span class="badge bg-info text-dark">{{ $row->items->count() }} Items</span></td><td>{{ $row->received_by }}</td><td class="text-center"><a href="{{ route('qc.incoming.inspect', ['gr_id' => $row->id]) }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-search"></i> Periksa (Inspect)</a></td></tr>
        @empty
            <tr><td colspan="6" class="text-center py-4 text-muted">Tidak ada barang yang menunggu QC.</td></tr>
        @endforelse
    </tbody></table></div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-header bg-white"><h6 class="mb-0 fw-bold text-primary"><i class="bi bi-person-check-fill"></i> Menunggu Approval Manager</h6></div>
    <div class="card-body p-0 table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>No. QC</th><th>Ref. GR</th><th>Inspector</th><th>Keputusan Inspector</th><th class="text-center">Aksi</th></tr></thead><tbody>
        @forelse($waitingApproval as $row)
            @php $badge = $row->final_decision === 'accepted' ? 'bg-success' : ($row->final_decision === 'rejected' ? 'bg-danger' : 'bg-warning text-dark'); @endphp
            <tr><td><strong>{{ $row->qc_number }}</strong></td><td>{{ $row->goodsReceipt?->gr_number }}</td><td>{{ $row->inspector?->fullname }}</td><td><span class="badge {{ $badge }}">{{ strtoupper($row->final_decision) }}</span></td><td class="text-center"><div class="btn-group"><a href="{{ route('qc.incoming.print', $row) }}" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-eye"></i></a>@if(auth()->user()?->hasPermission('qc_incoming_approve'))<form method="POST" action="{{ route('qc.incoming.approve', $row) }}" onsubmit="return confirm('Approve Hasil QC ini? Stok barang bagus akan otomatis ditambahkan ke Gudang.')">@csrf<button class="btn btn-sm btn-success fw-bold"><i class="bi bi-check-circle"></i> Approve</button></form>@endif</div></td></tr>
        @empty
            <tr><td colspan="5" class="text-center py-3 text-muted">Tidak ada QC menunggu approval.</td></tr>
        @endforelse
    </tbody></table></div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-info">
    <div class="card-header bg-white"><h6 class="mb-0 fw-bold text-info"><i class="bi bi-box-seam"></i> Menunggu Serah Terima Gudang</h6></div>
    <div class="card-body p-0 table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>No. QC</th><th>Ref. GR</th><th>Tgl QC</th><th>Status QC</th><th class="text-center">Aksi</th></tr></thead><tbody>
        @forelse($handover as $row)
            <tr><td><strong>{{ $row->qc_number }}</strong></td><td>{{ $row->goodsReceipt?->gr_number }}</td><td>{{ optional($row->qc_date)->format('d/m/Y') }}</td><td><span class="badge bg-success">QC OK</span></td><td class="text-center">@if(auth()->user()?->hasPermission('whse_view'))<form method="POST" action="{{ route('qc.incoming.handover', $row) }}" onsubmit="return confirm('Konfirmasi penerimaan barang fisik dari QC ke Gudang?')">@csrf<button class="btn btn-sm btn-info text-white fw-bold"><i class="bi bi-hand-thumbs-up"></i> Terima Barang</button></form>@endif</td></tr>
        @empty
            <tr><td colspan="5" class="text-center py-3 text-muted">Tidak ada barang menunggu serah terima.</td></tr>
        @endforelse
    </tbody></table></div>
</div>

<div class="card shadow-sm border-start border-4 border-success">
    <div class="card-header bg-white"><h6 class="mb-0 fw-bold text-success"><i class="bi bi-check-all"></i> Riwayat QC & Tanda Terima</h6></div>
    <div class="card-body p-0 table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>No. QC</th><th>Tgl QC</th><th>No. GR</th><th>Inspector</th><th>Keputusan</th><th class="text-center">Dokumen</th></tr></thead><tbody>
        @forelse($history as $row)
            <tr><td>{{ $row->qc_number }}</td><td>{{ optional($row->qc_date)->format('d/m/Y') }}</td><td>{{ $row->goodsReceipt?->gr_number }}</td><td>{{ $row->inspector?->fullname }}</td><td><span class="badge {{ $row->final_decision === 'accepted' ? 'bg-success' : 'bg-danger' }}">{{ strtoupper($row->final_decision) }}</span></td><td class="text-center"><a href="{{ route('qc.incoming.print', $row) }}" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-file-earmark-text"></i> QC</a></td></tr>
        @empty
            <tr><td colspan="6" class="text-center py-3 text-muted">Riwayat QC belum ada.</td></tr>
        @endforelse
    </tbody></table></div>
</div>
@endsection
