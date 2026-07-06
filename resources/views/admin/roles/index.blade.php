@extends('layouts.mms')

@section('title', 'Manajemen Role')

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6"><h3 class="fw-bold"><i class="bi bi-shield-lock"></i> Manajemen Role Access</h3></div>
    <div class="col-md-6 text-end">
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary me-2">Kembali ke Users</a>
        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">+ Tambah Role</a>
    </div>
</div>
<div class="alert alert-info py-2 small"><i class="bi bi-info-circle"></i> <b>Role Slug</b> digunakan untuk logika sistem. Jangan ubah slug penting tanpa evaluasi akses.</div>
<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-hover">
            <thead class="table-light"><tr><th>Role Name</th><th>Slug</th><th>Deskripsi</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach($roles as $role)
                    <tr>
                        <td><strong>{{ $role->role_name }}</strong></td>
                        <td><code>{{ $role->role_slug }}</code></td>
                        <td>{{ $role->description }}</td>
                        <td>
                            <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-warning text-white">Edit</a>
                            @if($role->role_slug !== 'admin')
                                <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus role ini? User dengan role ini akan kehilangan akses.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger">Hapus</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
