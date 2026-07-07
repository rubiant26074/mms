@extends('layouts.mms')

@section('title', 'Production Task Assignment')

@section('content')
@include('partials.alerts')
@php
    $badges = [
        'waiting_mgr' => 'bg-danger',
        'final' => 'bg-success',
        'released' => 'bg-info text-dark',
        'in_production' => 'bg-warning text-dark',
        'completed' => 'bg-success',
        'closed' => 'bg-dark',
    ];
@endphp
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-kanban"></i> Manajemen Tugas</h3>
        <p class="text-muted mb-0">Distribusi tugas operator dan monitoring status SPK.</p>
    </div>
    <div class="col-md-6">
        <form method="GET" class="d-flex justify-content-md-end gap-2 mt-2 mt-md-0">
            <select name="status" class="form-select w-auto" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
                <option value="">Semua Status</option>
                @foreach(['released','in_production','completed','closed','waiting_mgr','final'] as $opt)
                    <option value="{{ $opt }}" @selected($status === $opt)>{{ strtoupper(str_replace('_', ' ', $opt)) }}</option>
                @endforeach
            </select>
            <a href="{{ route('production.tasks.index') }}" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">No. SPK / Project</th>
                    <th>Deadline</th>
                    <th>Progress Tugas</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($spks as $row)
                @php
                    $days = $row->days_remaining;
                    $deadlineClass = $days !== null && $days < 0 ? 'text-danger fw-bold' : (($days !== null && $days <= 2) ? 'text-warning fw-bold' : 'text-dark');
                @endphp
                <tr>
                    <td class="ps-4">
                        <strong class="text-primary">{{ $row->spk_number }}</strong><br>
                        <small class="text-muted fw-bold">{{ $row->customer_name ?: '-' }} | SO: {{ $row->so_number ?: '-' }}</small>
                    </td>
                    <td>
                        <div class="{{ $deadlineClass }}">{{ $row->deadline ? $row->deadline->format('d M Y') : '-' }}</div>
                        <small class="text-muted">
                            @if($days === null)
                                -
                            @elseif($days < 0)
                                Terlambat
                            @else
                                {{ $days }} Hari lagi
                            @endif
                        </small>
                    </td>
                    <td style="width:25%">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Selesai: {{ (int) $row->completed }} / {{ (int) $row->total_needed }} Proses</span>
                            <span class="fw-bold">{{ $row->progress_percent }}%</span>
                        </div>
                        <div class="progress" style="height:6px">
                            <div class="progress-bar {{ $row->progress_percent >= 100 ? 'bg-success' : 'bg-primary' }}" style="width: {{ $row->progress_percent }}%"></div>
                        </div>
                    </td>
                    <td><span class="badge {{ $badges[$row->status] ?? 'bg-secondary' }}">{{ strtoupper(str_replace('_', ' ', $row->status)) }}</span></td>
                    <td class="text-center">
                        @if(auth()->user()?->hasPermission('prod_task_manage'))
                            <a href="{{ route('production.tasks.manage', $row->id) }}" class="btn btn-sm btn-outline-primary fw-bold"><i class="bi bi-people-fill me-1"></i> ATUR TUGAS</a>
                        @else
                            <span class="text-muted small">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center py-5 text-muted">Tidak ada SPK aktif.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
