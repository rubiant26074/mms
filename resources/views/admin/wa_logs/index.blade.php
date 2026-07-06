@extends('layouts.mms')

@section('title', 'WA Logs')

@section('content')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-whatsapp"></i> WA Message Logs</h3>
        <p class="text-muted">Audit pengiriman WhatsApp otomatis (Fonte).</p>
    </div>
</div>
@if(!$tableExists)
    <div class="alert alert-warning">Tabel log WA belum tersedia.</div>
@else
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">Semua</option><option value="success" @selected(request('status')==='success')>Success</option><option value="failed" @selected(request('status')==='failed')>Failed</option></select></div>
                <div class="col-md-2"><label class="form-label">Dari Tanggal</label><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
                <div class="col-md-2"><label class="form-label">Sampai</label><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
                <div class="col-md-2"><label class="form-label">Limit</label><select name="limit" class="form-select">@foreach([50,100,200,500] as $opt)<option value="{{ $opt }}" @selected($limit===$opt)>{{ $opt }}</option>@endforeach</select></div>
                <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            </form>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Waktu</th><th>Status</th><th>Nomor</th><th>Pesan</th><th>Media URL</th><th>Dibuat Oleh</th><th>Error / Response</th></tr></thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i:s') }}</td>
                            <td><span class="badge {{ $row->status === 'success' ? 'bg-success' : 'bg-danger' }}">{{ strtoupper($row->status) }}</span></td>
                            <td><div class="fw-bold">{{ $row->recipient_phone ?: '-' }}</div><small class="text-muted">{{ $row->recipient_phone_raw ?: '-' }}</small></td>
                            <td style="max-width:260px"><div class="text-truncate" title="{{ $row->message_text }}">{{ $row->message_text }}</div></td>
                            <td>@if($row->media_url)<a href="{{ $row->media_url }}" target="_blank">Lihat Link</a>@else<span class="text-muted">-</span>@endif</td>
                            <td>{{ $row->created_by_name ?: '-' }}</td>
                            <td style="max-width:320px"><div class="small {{ $row->error_message ? 'text-danger' : 'text-muted' }} text-truncate">{{ $row->error_message ?: ($row->provider_response ?: '-') }}</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada log WA.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
@endsection
