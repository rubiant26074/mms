@extends('layouts.mms')

@section('title', 'Identitas Perusahaan')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-building"></i> Pengaturan Identitas Perusahaan</h5>
            </div>
            <div class="card-body">
                @include('partials.alerts')
                <form method="POST" action="{{ route('admin.company.update') }}" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    <div class="text-center mb-4 p-3 bg-light border rounded">
                        <label class="form-label fw-bold d-block">Logo Saat Ini:</label>
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="Company Logo" style="max-height: 100px; max-width: 100%;">
                        @else
                            <span class="text-muted fst-italic">Belum ada logo diupload</span>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Perusahaan (PT/CV) <span class="text-danger">*</span></label>
                        <input type="text" name="company_name" class="form-control fw-bold" value="{{ old('company_name', $companyData->company_name) }}" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $companyData->phone) }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Alamat Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $companyData->email) }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Website</label>
                        <input type="text" name="website" class="form-control" value="{{ old('website', $companyData->website) }}" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Running Text TV Dashboard</label>
                        <textarea name="running_text" class="form-control" rows="2">{{ old('running_text', $companyData->running_text) }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Token WA Fonte</label>
                        <input type="text" name="fonte_token" class="form-control" value="{{ old('fonte_token', $companyData->fonte_token) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Theme Aplikasi</label>
                        <select name="ui_theme" class="form-select">
                            @foreach($themes as $slug => $label)
                                <option value="{{ $slug }}" @selected(old('ui_theme', $companyData->ui_theme ?: 'original') === $slug)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Daftar tema otomatis diambil dari file <code>assets/css/theme-*.css</code>.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat Lengkap</label>
                        <textarea name="address" class="form-control" rows="3">{{ old('address', $companyData->address) }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload Logo Baru (JPG/PNG)</label>
                        <input type="file" name="logo" class="form-control" accept="image/png, image/jpeg">
                        <div class="form-text">Biarkan kosong jika tidak ingin mengubah logo.</div>
                    </div>
                    <hr>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
