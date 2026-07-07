@extends('layouts.mms')

@section('title', 'Laporan Hasil Produksi')

@section('content')
@php
    $activityBadges = [
        'start' => 'bg-primary',
        'hold' => 'bg-warning text-dark',
        'resume' => 'bg-info text-dark',
        'finish' => 'bg-success',
    ];
@endphp
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Laporan Produksi Harian</h4>
        <p class="text-muted mb-0">Tanggal: {{ \Illuminate\Support\Carbon::parse($date)->format('d F Y') }} | Aktivitas: {{ strtoupper($activity) }}</p>
    </div>
    <form method="GET" class="d-flex gap-2 flex-wrap">
        <input type="date" name="date" class="form-control w-auto" value="{{ $date }}">
        <select name="activity" class="form-select w-auto">
            @foreach(['all' => 'Semua Aktivitas', 'start' => 'Start', 'hold' => 'Hold', 'resume' => 'Resume', 'finish' => 'Finish'] as $key => $label)
                <option value="{{ $key }}" @selected($activity === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary" title="Filter"><i class="bi bi-filter"></i></button>
        <button type="button" class="btn btn-dark" onclick="window.print()" title="Print"><i class="bi bi-printer"></i></button>
    </form>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-2 mb-md-0">
        <div class="card bg-success text-white shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div><h2 class="fw-bold mb-0">{{ number_format($sumGood, 0, ',', '.') }}</h2><small class="text-white-50 text-uppercase">Total Good (Finish)</small></div>
                <i class="bi bi-check-circle fs-1 opacity-25"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-2 mb-md-0">
        <div class="card bg-danger text-white shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div><h2 class="fw-bold mb-0">{{ number_format($sumReject, 0, ',', '.') }}</h2><small class="text-white-50 text-uppercase">Total Reject (Finish)</small></div>
                <i class="bi bi-x-circle fs-1 opacity-25"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white shadow-sm border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div><h2 class="fw-bold mb-0">{{ $yield }}%</h2><small class="text-white-50 text-uppercase">Yield Rate (Finish)</small></div>
                <i class="bi bi-pie-chart fs-1 opacity-25"></i>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-bold">Aktivitas Tugas Operator</div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0 table-striped">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Waktu</th>
                    <th>Aktivitas</th>
                    <th>Operator</th>
                    <th>Project & Proses</th>
                    <th class="text-center text-success">Good</th>
                    <th class="text-center text-danger">Reject</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                @php($assignment = $log->assignment)
                <tr>
                    <td class="ps-4 text-muted font-monospace">{{ optional($log->log_time)->format('H:i') }}</td>
                    <td><span class="badge {{ $activityBadges[strtolower($log->activity)] ?? 'bg-secondary' }}">{{ strtoupper($log->activity) }}</span></td>
                    <td class="fw-bold">{{ $log->operator?->fullname ?: '-' }}</td>
                    <td>
                        <div class="fw-bold text-dark">{{ $assignment?->spk?->spk_number ?: '-' }}</div>
                        <span class="badge bg-secondary">{{ $assignment?->process_name ?: '-' }}</span>
                        <small class="text-muted ms-1">{{ \Illuminate\Support\Str::limit($assignment?->spk?->project_name ?: '-', 24) }}</small>
                    </td>
                    <td class="text-center fw-bold text-success fs-5">{{ $log->activity === 'finish' ? ((float) ($assignment?->qty_good ?? 0) + 0) : '-' }}</td>
                    <td class="text-center fw-bold text-danger fs-5">{{ $log->activity === 'finish' ? ((float) ($assignment?->qty_reject ?? 0) + 0) : '-' }}</td>
                    <td class="text-muted small fst-italic">{{ $log->notes ?: ($assignment?->notes ?: '-') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-5 text-muted">Belum ada aktivitas produksi pada tanggal ini.</td></tr>
            @endforelse
            </tbody>
            @if($logs->isNotEmpty())
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end pe-3">TOTAL FINISH ({{ $countFinish }}):</td>
                        <td class="text-center text-success">{{ $sumGood + 0 }}</td>
                        <td class="text-center text-danger">{{ $sumReject + 0 }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

<div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-white fw-bold">Progress Partlist Operator</div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0 table-striped">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Waktu</th>
                    <th>Operator</th>
                    <th>SPK & Proses</th>
                    <th>Part</th>
                    <th class="text-center">Qty Update</th>
                    <th class="text-center">Status</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
            @forelse($partProgress as $row)
                <tr>
                    <td class="ps-4 text-muted font-monospace">{{ optional($row->created_at)->format('H:i') }}</td>
                    <td class="fw-bold">{{ $row->creator?->fullname ?: '-' }}</td>
                    <td>
                        <div class="fw-bold text-dark">{{ $row->assignment?->spk?->spk_number ?: '-' }}</div>
                        <span class="badge bg-secondary">{{ $row->assignment?->process_name ?: '-' }}</span>
                    </td>
                    <td>
                        <div class="fw-bold">{{ $row->partlist?->part_name ?: '-' }}</div>
                        <small class="text-muted">DWG: {{ $row->partlist?->drawing_no ?: '-' }}</small>
                    </td>
                    <td class="text-center fw-bold text-primary">{{ (float) $row->qty_done + 0 }}</td>
                    <td class="text-center">
                        @if($row->progress_state === 'done')
                            <span class="badge bg-success">SELESAI</span>
                        @elseif($row->progress_state === 'pending')
                            <span class="badge bg-danger">BELUM</span>
                        @else
                            <span class="badge bg-warning text-dark">PROGRESS</span>
                        @endif
                    </td>
                    <td class="text-muted small fst-italic">{{ $row->notes ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada progress partlist pada tanggal ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
