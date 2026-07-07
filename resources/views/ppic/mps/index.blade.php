@extends('layouts.mms')

@section('title', 'Master Production Schedule (MPS)')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-calendar-week"></i> Jadwal Produksi (MPS)</h3>
        <p class="text-muted">Monitoring deadline dan progres produksi.</p>
    </div>
    <div class="col-md-6">
        <form method="GET" class="d-flex justify-content-end gap-2">
            <select name="month" class="form-select w-auto">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($m === $month)>{{ \Illuminate\Support\Carbon::create(null, $m, 1)->format('F') }}</option>
                @endfor
            </select>
            <select name="year" class="form-select w-auto">
                @for($y = now()->year - 1; $y <= now()->year + 1; $y++)
                    <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                @endfor
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>
    </div>
</div>

<div class="row">
    @forelse($schedules as $row)
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm {{ $row->card_classes['border'] }} border-top border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                        <div>
                            <h6 class="fw-bold mb-0">{{ $row->spk_number }}</h6>
                            <small class="text-muted">Ref SO: {{ $row->sales_order_id ? '#' . $row->sales_order_id : '-' }}</small>
                        </div>
                        <span class="badge {{ $row->card_classes['bg'] }} {{ $row->card_classes['text'] }}">{{ $row->status_label }}</span>
                    </div>

                    <h5 class="card-title text-primary">{{ $row->project_name }}</h5>
                    <p class="card-text small text-muted mb-3">
                        Customer: <strong>{{ $row->customer_name ?: '-' }}</strong><br>
                        Deadline: {{ $row->deadline ? $row->deadline->format('d M Y') : '-' }}
                    </p>

                    <div class="d-flex justify-content-between small mb-1">
                        <span>Progress Produksi</span>
                        <span class="fw-bold">{{ $row->progress_percent }}%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar {{ $row->card_classes['progress'] }}" role="progressbar" style="width: {{ $row->progress_percent }}%" aria-valuenow="{{ $row->progress_percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="mt-2 small text-muted text-end">
                        Task Selesai: {{ (int) $row->completed_tasks }} / {{ (int) $row->total_tasks }}
                    </div>
                </div>
                <div class="card-footer bg-white text-center">
                    <a href="{{ route('ppic.spk.print', $row->id) }}" target="_blank" class="btn btn-sm btn-outline-dark w-100">Lihat Detail SPK</a>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center py-5 text-muted bg-white border rounded">
            <i class="bi bi-calendar-x display-4"></i>
            <p class="mt-3">Tidak ada jadwal produksi pada periode ini.</p>
        </div>
    @endforelse
</div>
@endsection
