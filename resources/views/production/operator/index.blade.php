@extends('layouts.mms')

@section('title', 'Operator Panel')

@section('content')
@include('partials.alerts')

@if($isViewOnly)
    <div class="card shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
            <div>
                <h5 class="mb-1">Mode Lihat Operator</h5>
                <div class="text-muted small">Pilih operator untuk melihat antrian dan progress tanpa eksekusi tindakan.</div>
            </div>
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="mode" value="view">
                <select name="operator_id" class="form-select" required>
                    <option value="">Pilih Operator</option>
                    @foreach($operators as $operator)
                        <option value="{{ $operator->id }}" @selected((int) $operatorId === (int) $operator->id)>{{ $operator->fullname }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary">Tampilkan</button>
            </form>
        </div>
    </div>
@endif

@if($isViewOnly && ! $operatorId)
    <div class="alert alert-warning text-center">Pilih operator terlebih dahulu untuk melihat antrian tugas.</div>
@else
<div class="row">
    <div class="col-lg-8 mb-4">
        @if($activeTask)
            @php
                $isProgress = $activeTask->status === 'in_progress';
                $spk = $activeTask->spk;
            @endphp
            <div class="card shadow-sm border-{{ $isProgress ? 'primary' : 'warning' }} mb-4">
                <div class="card-header {{ $isProgress ? 'bg-primary text-white' : 'bg-warning text-dark' }} text-center py-3">
                    <h5 class="mb-0"><i class="bi {{ $isProgress ? 'bi-gear-wide-connected' : 'bi-pause-circle' }}"></i> {{ $isProgress ? 'SEDANG DIKERJAKAN' : 'PEKERJAAN HOLD' }}</h5>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between gap-3 flex-wrap mb-3">
                        <div>
                            <h3 class="text-primary fw-bold mb-1">{{ $activeTask->process_name }}</h3>
                            <div class="fw-bold">{{ $spk?->spk_number }}</div>
                            <div class="text-muted small">{{ $spk?->salesOrder?->customer?->name ?: '-' }} | SO: {{ $spk?->salesOrder?->so_number ?: '-' }}</div>
                        </div>
                        <div class="text-md-end">
                            <span class="badge bg-light text-dark border"><i class="bi bi-cpu"></i> {{ $activeTask->machine ? ($activeTask->machine->machine_code.' - '.$activeTask->machine->machine_name) : 'Tanpa Mesin' }}</span>
                            @if($activeTask->start_time)<div class="small text-muted mt-2">Mulai: {{ $activeTask->start_time->format('d/m/Y H:i') }}</div>@endif
                        </div>
                    </div>

                    @if($spk?->drawing_link)
                        @php
                            $spkLink = trim((string) $spk->drawing_link);
                            $spkDrawingUrl = preg_match('/^https?:\/\//i', $spkLink) ? $spkLink : asset(ltrim($spkLink, '/'));
                        @endphp
                        <div class="mb-3">
                            <a href="{{ $spkDrawingUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-danger fw-bold shadow-sm">
                                <i class="bi bi-file-earmark-pdf-fill fs-6 me-1"></i> Buka / Download Drawing SPK
                            </a>
                        </div>
                    @endif

                    @if($spk?->project_name)<div class="bg-light border rounded p-3 mb-3">{{ $spk->project_name }}</div>@endif

                    @if($isProgress)
                        <div class="d-flex gap-2 mb-3">
                            @if(! $isViewOnly)
                                <button class="btn btn-warning flex-fill fw-bold" data-bs-toggle="modal" data-bs-target="#holdModal"><i class="bi bi-pause-circle"></i> HOLD</button>
                                <button class="btn btn-success flex-fill fw-bold" data-bs-toggle="modal" data-bs-target="#finishModal" @disabled($partStats['unfinished'] > 0)><i class="bi bi-check-circle"></i> SELESAI</button>
                            @endif
                        </div>
                        @if($partStats['unfinished'] > 0)
                            <div class="alert alert-warning py-2">Checklist partlist belum selesai semua. Selesai: {{ $partStats['done'] }} / {{ $partStats['total'] }}.</div>
                        @endif
                    @elseif(! $isViewOnly)
                        <form method="POST" action="{{ route('production.operator.resume', $activeTask) }}">
                            @csrf
                            <button class="btn btn-primary w-100 py-3 fw-bold"><i class="bi bi-play-circle"></i> LANJUT KERJA</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light fw-bold">Checklist Partlist</div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Part</th><th class="text-end">Target</th><th class="text-end">Done</th><th>Status</th><th width="260">Input</th></tr></thead>
                        <tbody>
                        @forelse($partlists as $part)
                            @php
                                $row = $progress->get($part->id, ['qty_done' => 0, 'state' => 'progress']);
                                $done = (float) $row['qty_done'];
                                $target = (float) $part->qty;
                                $complete = ($row['state'] ?? '') === 'done' || ($target > 0 && $done >= $target);
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <strong>{{ $part->part_name ?: $part->item_no }}</strong>
                                            <div class="text-muted small">{{ $part->drawing_no ?: '-' }} | {{ $part->process ?: '-' }}</div>
                                        </div>
                                        @if($part->resolved_drawing_url)
                                            <a href="{{ $part->resolved_drawing_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-danger ms-2 px-2 py-1" title="Buka / Download Drawing {{ $part->part_name }}">
                                                <i class="bi bi-file-earmark-pdf-fill"></i> Drawing
                                            </a>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end">{{ $target + 0 }}</td>
                                <td class="text-end">{{ $done + 0 }}</td>
                                <td><span class="badge {{ $complete ? 'bg-success' : 'bg-warning text-dark' }}">{{ $complete ? 'DONE' : 'PENDING' }}</span></td>
                                <td>
                                    @if(! $isViewOnly && ! $complete && $isProgress)
                                        <form method="POST" action="{{ route('production.operator.part_progress', $activeTask) }}" class="d-flex gap-1">
                                            @csrf
                                            <input type="hidden" name="partlist_id" value="{{ $part->id }}">
                                            <input type="number" step="0.01" min="0.01" name="qty_done" class="form-control form-control-sm" placeholder="Qty" required>
                                            <button class="btn btn-sm btn-primary"><i class="bi bi-save"></i></button>
                                        </form>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Partlist belum tersedia untuk SPK ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($progressLogs->isNotEmpty())
                <div class="card shadow-sm">
                    <div class="card-header bg-light fw-bold">Log Progress Terakhir</div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Waktu</th><th>Part</th><th class="text-end">Qty</th><th>Status</th><th>Catatan</th></tr></thead>
                            <tbody>
                                @foreach($progressLogs as $log)
                                    <tr>
                                        <td>{{ optional($log->created_at)->format('d/m H:i') }}</td>
                                        <td>{{ $log->partlist?->part_name ?: '-' }}</td>
                                        <td class="text-end">{{ (float) $log->qty_done + 0 }}</td>
                                        <td>{{ strtoupper($log->progress_state) }}</td>
                                        <td>{{ $log->notes ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @else
            <div class="card shadow-sm mb-4">
                <div class="card-body text-center py-5">
                    <i class="bi bi-cup-hot display-4 text-muted"></i>
                    <h4 class="mt-3">Tidak ada tugas aktif</h4>
                    <p class="text-muted mb-0">Mulai salah satu tugas dari antrian di samping.</p>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light fw-bold">Antrian Tugas</div>
            <div class="list-group list-group-flush">
                @forelse($queueTasks as $task)
                    <div class="list-group-item">
                        <div class="fw-bold text-primary">{{ $task->process_name }}</div>
                        <div class="small text-muted">{{ $task->spk?->spk_number }} | {{ $task->spk?->salesOrder?->customer?->name ?: '-' }}</div>
                        <div class="small mt-1"><i class="bi bi-cpu"></i> {{ $task->machine ? ($task->machine->machine_code.' - '.$task->machine->machine_name) : 'Tanpa Mesin' }}</div>
                        @if($task->spk?->drawing_link)
                            @php
                                $qLink = trim((string) $task->spk->drawing_link);
                                $qDrawingUrl = preg_match('/^https?:\/\//i', $qLink) ? $qLink : asset(ltrim($qLink, '/'));
                            @endphp
                            <div class="mt-1">
                                <a href="{{ $qDrawingUrl }}" target="_blank" rel="noopener noreferrer" class="text-danger small fw-bold">
                                    <i class="bi bi-file-earmark-pdf-fill"></i> Drawing SPK
                                </a>
                            </div>
                        @endif
                        @if(! $isViewOnly && ! $activeTask)
                            <form method="POST" action="{{ route('production.operator.start', $task) }}" class="mt-2">
                                @csrf
                                <button class="btn btn-sm btn-primary w-100" onclick="return confirm('Mulai kerjakan tugas ini?')"><i class="bi bi-play-circle"></i> MULAI</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="list-group-item text-center text-muted py-4">Tidak ada antrian tugas.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endif

@if($activeTask && ! $isViewOnly)
<div class="modal fade" id="holdModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('production.operator.hold', $activeTask) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Hold Pekerjaan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">Alasan</label>
                <select name="reason" class="form-select mb-3" required>
                    <option value="Material kurang">Material kurang</option>
                    <option value="Mesin bermasalah">Mesin bermasalah</option>
                    <option value="Menunggu instruksi">Menunggu instruksi</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
                <label class="form-label">Catatan</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
            <div class="modal-footer"><button class="btn btn-warning">Simpan Hold</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="finishModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('production.operator.finish', $activeTask) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Selesaikan Pekerjaan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">Qty Good</label>
                <input type="number" step="0.01" min="0" name="qty_good" class="form-control mb-3" required>
                <label class="form-label">Qty Reject</label>
                <input type="number" step="0.01" min="0" name="qty_reject" class="form-control mb-3" value="0">
                <label class="form-label">Catatan</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
            <div class="modal-footer"><button class="btn btn-success">Selesai</button></div>
        </form>
    </div>
</div>
@endif
@endsection
