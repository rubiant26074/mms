@extends('layouts.mms')

@section('title', 'Payroll & Penggajian')

@section('content')
@include('partials.alerts')
@php($canManage = auth()->user()?->hasPermission('hrd_payroll_manage'))

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-cash-stack"></i> Payroll Karyawan</h3>
        <p class="text-muted">Perhitungan dan pembayaran gaji bulanan.</p>
    </div>
    <div class="col-md-6 text-end">
        @if($canManage)
            <a href="{{ route('hrd.payroll.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Buat Slip Gaji
            </a>
        @endif
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end" id="payroll-filter-form">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Pencarian</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="No Slip / Nama / Jabatan..." value="{{ $search }}" autocomplete="off">
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Dari</label>
                <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Sampai</label>
                <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="draft" @selected($status === 'draft')>Draft</option>
                    <option value="paid" @selected($status === 'paid')>Paid</option>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="{{ route('hrd.payroll.index') }}" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>No. Slip</th>
                        <th>Periode</th>
                        <th>Nama Karyawan</th>
                        <th>Kehadiran</th>
                        <th class="text-end">THP (Gaji Bersih)</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payrolls as $row)
                        <tr>
                            <td><strong>{{ $row->payroll_code }}</strong></td>
                            <td>
                                <small class="text-muted">
                                    {{ optional($row->period_start)->format('d/m') }} - {{ optional($row->period_end)->format('d/m/Y') }}
                                </small>
                            </td>
                            <td>
                                <strong>{{ $row->employee?->fullname ?: '-' }}</strong><br>
                                <small class="text-muted">{{ $row->employee?->role?->role_name ?: '-' }}</small>
                            </td>
                            <td>{{ (int) $row->total_attendance }} Hari</td>
                            <td class="text-end fw-bold text-success">Rp {{ number_format((float) $row->net_salary, 0, ',', '.') }}</td>
                            <td><span class="badge {{ $row->status === 'paid' ? 'bg-success' : 'bg-secondary' }}">{{ strtoupper($row->status) }}</span></td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="{{ route('hrd.payroll.print', $row) }}" target="_blank" class="btn btn-sm btn-outline-dark" title="Cetak Slip">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    @if($canManage && $row->status === 'draft')
                                        <a href="{{ route('hrd.payroll.edit', $row) }}" class="btn btn-sm btn-warning text-dark"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('hrd.payroll.pay', $row) }}" class="d-inline" onsubmit="return confirm('Tandai sudah dibayar?')">
                                            @csrf
                                            <button class="btn btn-sm btn-success" title="Bayar"><i class="bi bi-wallet2"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('hrd.payroll.destroy', $row) }}" class="d-inline" onsubmit="return confirm('Hapus data gaji ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Belum ada data payroll.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('payroll-filter-form');
    if (!form) return;

    const search = form.querySelector('input[name="search"]');
    const status = form.querySelector('select[name="status"]');
    const start = form.querySelector('input[name="start_date"]');
    const end = form.querySelector('input[name="end_date"]');
    let t;

    const submit = () => {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    };

    if (search) {
        search.addEventListener('input', () => {
            clearTimeout(t);
            t = setTimeout(submit, 400);
        });
    }
    if (status) status.addEventListener('change', submit);
    if (start) start.addEventListener('change', submit);
    if (end) end.addEventListener('change', submit);
})();
</script>
@endsection
