@extends('layouts.mms')

@section('title', $isEdit ? 'Edit User' : 'Tambah User Baru')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">{{ $isEdit ? 'Edit User' : 'Tambah User Baru' }}</h5>
            </div>
            <div class="card-body">
                @include('partials.alerts')
                <form method="POST" action="{{ $isEdit ? route('admin.users.update', $userData) : route('admin.users.store') }}" enctype="multipart/form-data">
                    @csrf
                    @if($isEdit) @method('PUT') @endif
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" value="{{ old('username', $userData->username) }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="fullname" class="form-control" value="{{ old('fullname', $userData->fullname) }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Role / Jabatan <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select" required>
                            <option value="">-- Pilih Role --</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" @selected((string) old('role_id', $userData->role_id) === (string) $role->id)>{{ $role->role_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Password {!! $isEdit ? '<small class="text-muted">(Kosongkan jika tidak ingin mengubah)</small>' : '<span class="text-danger">*</span>' !!}</label>
                        <input type="password" name="password" class="form-control" {{ $isEdit ? '' : 'required' }}>
                    </div>
                    <div class="mb-3">
                        <label>Tanda Tangan Digital (Scan/Foto)</label>
                        @if($userData->signature_path)
                            <div class="mb-2 p-2 border rounded bg-light" style="width: fit-content;">
                                <img src="{{ asset($userData->signature_path) }}" alt="Signature" style="height: 80px;">
                                <div class="small text-muted mt-1 text-center">Current Signature</div>
                            </div>
                        @endif
                        <input type="file" name="signature" class="form-control" accept="image/png, image/jpeg">
                        <div class="form-text">Format: JPG/PNG. Background transparan disarankan. Max 2MB.</div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
