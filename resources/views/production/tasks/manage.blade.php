@extends('layouts.mms')

@section('title', 'Atur Tugas Produksi')

@section('content')
@include('partials.alerts')
@php
    $statusBadge = ['assigned'=>'bg-primary','in_progress'=>'bg-warning text-dark','hold'=>'bg-secondary','completed'=>'bg-success','pending'=>'bg-light text-dark'];
@endphp
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between gap-3 flex-wrap">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-kanban"></i> Atur Tugas Produksi</h3>
                <div class="text-primary fw-bold">{{ $spk->spk_number }}</div>
                <div class="text-muted small">{{ $spk->salesOrder?->customer?->name ?: '-' }} | SO: {{ $spk->salesOrder?->so_number ?: '-' }}</div>
            </div>
            <div class="text-md-end">
                <div class="small text-muted">Deadline</div>
                <div class="fw-bold">{{ optional($spk->deadline_date)->format('d M Y') ?: '-' }}</div>
                <span class="badge bg-info text-dark">{{ strtoupper(str_replace('_', ' ', $spk->status)) }}</span>
            </div>
        </div>
        @if($spk->project_name)
            <div class="mt-2">{{ $spk->project_name }}</div>
        @endif
    </div>
</div>

<form method="POST" action="{{ route('production.tasks.assign', $spk) }}">
    @csrf
    <div class="card shadow-sm">
        <div class="card-header bg-light fw-bold">Daftar Proses</div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Proses</th>
                        <th>Operator</th>
                        <th>Mesin</th>
                        <th>Status</th>
                        <th>Output</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($processes as $process)
                    @php
                        $task = $assignments->get($process);
                        $locked = $task && in_array($task->status, ['in_progress', 'hold', 'completed'], true);
                    @endphp
                    <tr>
                        <td class="fw-bold">
                            {{ $process }}
                            <input type="hidden" name="process_name[]" value="{{ $process }}">
                        </td>
                        <td style="min-width:220px">
                            <select name="operator_id[]" class="form-select form-select-sm">
                                <option value="">-- Pilih Operator --</option>
                                @foreach($operators as $operator)
                                    <option value="{{ $operator->id }}" @selected((int) ($task?->operator_id) === (int) $operator->id)>{{ $operator->fullname }}</option>
                                @endforeach
                            </select>
                            @if($locked)<div class="small text-muted mt-1">Status aktif tidak direset saat operator/mesin diganti.</div>@endif
                        </td>
                        <td style="min-width:220px">
                            <select name="machine_id[]" class="form-select form-select-sm">
                                <option value="">-- Tanpa Mesin --</option>
                                @foreach($machines as $machine)
                                    <option value="{{ $machine->id }}" @selected((int) ($task?->machine_id) === (int) $machine->id)>{{ $machine->machine_code }} - {{ $machine->machine_name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><span class="badge {{ $statusBadge[$task?->status ?? 'pending'] ?? 'bg-secondary' }}">{{ strtoupper(str_replace('_', ' ', $task?->status ?? 'pending')) }}</span></td>
                        <td class="small">
                            Good: <strong>{{ (float) ($task?->qty_good ?? 0) + 0 }}</strong><br>
                            Reject: <strong>{{ (float) ($task?->qty_reject ?? 0) + 0 }}</strong>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Route proses belum tersedia di SPK ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <a href="{{ route('production.tasks.index') }}" class="btn btn-secondary">Kembali</a>
            <button class="btn btn-primary px-5" @disabled(empty($processes))>Simpan Assignment</button>
        </div>
    </div>
</form>
@endsection
