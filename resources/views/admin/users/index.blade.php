@extends('layouts.mms')

@section('title', 'Manajemen User')

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-people"></i> Manajemen User</h3>
        <p class="text-muted">Kelola akun pengguna dan hak akses.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-dark me-2"><i class="bi bi-shield-lock"></i> Kelola Role</a>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah User</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>User Info</th>
                        <th>Role / Jabatan</th>
                        <th class="text-center">TTD</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $row)
                        @php
                            $slug = $row->role?->role_slug ?? '';
                            $badge = match($slug) {
                                'admin' => 'bg-dark',
                                'manager' => 'bg-primary',
                                'ppic' => 'bg-warning text-dark',
                                'staff' => 'bg-secondary',
                                default => 'bg-info',
                            };
                        @endphp
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>
                                <div class="fw-bold">{{ $row->fullname }}</div>
                                <small class="text-muted">@{{ $row->username }}</small>
                            </td>
                            <td><span class="badge {{ $badge }}">{{ $row->role?->role_name ?? 'Tanpa Role' }}</span></td>
                            <td class="text-center">
                                @if($row->signature_path)
                                    <span class="badge bg-success"><i class="bi bi-pen-fill"></i> OK</span>
                                @else
                                    <span class="badge bg-secondary text-white-50"><i class="bi bi-dash-circle"></i></span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.users.edit', $row) }}" class="btn btn-sm btn-warning text-white" title="Edit User"><i class="bi bi-pencil"></i></a>
                                @if($slug !== 'admin' && $row->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.destroy', $row) }}" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus user ini?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Hapus User"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
