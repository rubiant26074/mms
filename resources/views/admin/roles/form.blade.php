@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Role' : 'Tambah Role')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-dark text-white">Form Role</div>
            <div class="card-body">
                @include('partials.alerts')
                <form method="POST" action="{{ $isEdit ? route('admin.roles.update', $roleData) : route('admin.roles.store') }}">
                    @csrf
                    @if($isEdit) @method('PUT') @endif
                    <div class="mb-3">
                        <label>Nama Role (Tampilan)</label>
                        <input type="text" name="role_name" class="form-control" value="{{ old('role_name', $roleData->role_name) }}" required>
                    </div>
                    <div class="mb-3">
                        <label>Role Slug (Kode Unik)</label>
                        <input type="text" name="role_slug" class="form-control" value="{{ old('role_slug', $roleData->role_slug) }}" required>
                        <small class="text-muted">Gunakan huruf kecil, tanpa spasi (ganti dengan _)</small>
                    </div>
                    <div class="mb-3">
                        <label>Deskripsi</label>
                        <textarea name="description" class="form-control">{{ old('description', $roleData->description) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <a href="{{ route('admin.roles.index') }}" class="btn btn-light border">Batal</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
