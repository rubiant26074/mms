@extends('layouts.mms')

@section('title', 'Master Data Karyawan')

@section('content')
@include('partials.alerts')
@php($canManage = auth()->user()?->hasPermission('hrd_employee_manage'))

<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-person-vcard"></i> Data Karyawan</h3>
        <p class="text-muted">Database pegawai, kontak, dan status kepegawaian.</p>
    </div>
    <div class="col-md-6 text-end">
        @if($canManage)
            <a href="{{ route('hrd.employees.create') }}" class="btn btn-primary">
                <i class="bi bi-person-plus-fill"></i> Tambah Karyawan
            </a>
        @endif
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="emp-filter-form">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari NIK / Nama / Departemen / Telp..." value="{{ $search }}" autocomplete="off">
                </div>
            </div>

            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="permanent" @selected($status === 'permanent')>Permanent</option>
                    <option value="contract" @selected($status === 'contract')>Contract</option>
                    <option value="probation" @selected($status === 'probation')>Probation</option>
                    <option value="resigned" @selected($status === 'resigned')>Resigned</option>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="{{ route('hrd.employees.index') }}" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
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
                        <th>NIK / Nama</th>
                        <th>Departemen / Jabatan</th>
                        <th>Kontak</th>
                        <th>Status</th>
                        <th>Tgl Masuk</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $row)
                        <tr>
                            <td>
                                <strong>{{ $row->fullname }}</strong><br>
                                <small class="text-muted">NIK: {{ $row->nik ?: '-' }}</small>
                            </td>
                            <td>
                                <div class="fw-bold">{{ $row->department ?: '-' }}</div>
                                <small class="text-muted">{{ $row->role?->role_name ?: '-' }}</small>
                            </td>
                            <td>
                                <i class="bi bi-telephone"></i> {{ $row->phone ?: '-' }}<br>
                                <small class="text-muted">{{ str($row->address ?: '-')->limit(20, '...') }}</small>
                            </td>
                            <td><span class="badge {{ match($row->employee_status) {
                                'permanent' => 'bg-success',
                                'contract' => 'bg-info text-dark',
                                'probation' => 'bg-warning text-dark',
                                'resigned' => 'bg-danger',
                                default => 'bg-secondary'
                            } }}">{{ strtoupper($row->employee_status ?: 'unknown') }}</span></td>
                            <td>{{ $row->join_date?->format('d/m/Y') ?: '-' }}</td>
                            <td class="text-center">
                                @if($canManage)
                                    <a href="{{ route('hrd.employees.edit', $row) }}" class="btn btn-sm btn-warning text-dark" title="Edit Data"><i class="bi bi-pencil-square"></i></a>
                                    @if($row->id !== auth()->id())
                                        <form method="POST" action="{{ route('hrd.employees.destroy', $row) }}" class="d-inline" onsubmit="return confirm('Hapus data karyawan ini? User login juga akan terhapus.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Belum ada data karyawan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('emp-filter-form');
    if (!form) return;

    const search = form.querySelector('input[name="search"]');
    const status = form.querySelector('select[name="status"]');
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

    if (status) {
        status.addEventListener('change', submit);
    }
})();
</script>
@endsection
