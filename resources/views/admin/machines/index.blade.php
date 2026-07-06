@extends('layouts.mms')

@section('title', 'Master Mesin')

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-hdd-rack"></i> Master Mesin</h3>
        <p class="text-muted">Database mesin produksi dan status operasional.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="{{ route('admin.machines.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah Mesin</a>
    </div>
</div>
<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="machine-filter-form">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari Kode / Nama / Tipe / Lokasi..." value="{{ request('search') }}" autocomplete="off">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">- Semua Status -</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="maintenance" @selected(request('status') === 'maintenance')>Maintenance</option>
                    <option value="broken" @selected(request('status') === 'broken')>Broken</option>
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('admin.machines.index') }}" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>Kode Mesin</th><th>Nama Mesin</th><th>Tipe Proses</th><th>Lokasi</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    @foreach($machines as $row)
                        @php
                            $badge = match($row->status) {
                                'active' => 'bg-success',
                                'maintenance' => 'bg-warning text-dark',
                                'broken' => 'bg-danger',
                                default => 'bg-light'
                            };
                        @endphp
                        <tr>
                            <td><strong>{{ $row->machine_code }}</strong></td>
                            <td>{{ $row->machine_name }}</td>
                            <td><span class="badge bg-secondary">{{ $row->process_type }}</span></td>
                            <td>{{ $row->location }}</td>
                            <td><span class="badge {{ $badge }}">{{ strtoupper($row->status) }}</span></td>
                            <td>
                                <a href="{{ route('admin.machines.edit', $row) }}" class="btn btn-sm btn-warning text-white"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('admin.machines.destroy', $row) }}" class="d-inline" onsubmit="return confirm('Hapus mesin ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const form = document.getElementById('machine-filter-form');
    if (!form) return;
    const search = form.querySelector('input[name="search"]');
    const status = form.querySelector('select[name="status"]');
    let t;
    const submit = () => form.requestSubmit ? form.requestSubmit() : form.submit();
    search?.addEventListener('input', () => { clearTimeout(t); t = setTimeout(submit, 400); });
    status?.addEventListener('change', submit);
})();
</script>
@endpush
@endsection
