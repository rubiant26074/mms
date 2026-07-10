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
                    @csrf
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
                        <input type="hidden" name="logo_selected" id="logo_selected" value="0">
                        <input type="hidden" name="logo_base64" id="logo_base64" value="">
                        <input type="file" name="logo" id="logo" class="form-control" accept="image/png, image/jpeg">
                        <div class="form-text">Biarkan kosong jika tidak ingin mengubah logo.</div>
                    </div>
                    <hr>
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-geo-alt"></i> Pengaturan Geotag Absensi Kantor</h5>
                    <div class="mb-3">
                        <label class="form-label">Nama Lokasi Absensi / Lobi</label>
                        <input type="text" name="attendance_location_name" class="form-control" value="{{ old('attendance_location_name', $companyData->attendance_location_name) }}" placeholder="Contoh: Lobi Utama Kantor / Lantai 1">
                        <div class="form-text">Nama atau label keterangan tempat absensi dipasang.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Latitude Kantor <span class="text-danger">*</span></label>
                            <input type="text" name="attendance_latitude" id="company_latitude" class="form-control font-monospace" value="{{ old('attendance_latitude', $companyData->attendance_latitude) }}" placeholder="Contoh: -6.175392">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Longitude Kantor <span class="text-danger">*</span></label>
                            <input type="text" name="attendance_longitude" id="company_longitude" class="form-control font-monospace" value="{{ old('attendance_longitude', $companyData->attendance_longitude) }}" placeholder="Contoh: 106.827153">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Radius Valid (Meter) <span class="text-danger">*</span></label>
                            <input type="number" name="attendance_radius_meters" class="form-control" value="{{ old('attendance_radius_meters', $companyData->attendance_radius_meters ?? 100) }}" min="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnGetCompanyLocation">
                            <i class="bi bi-crosshair"></i> Ambil Koordinat Saya Saat Ini
                        </button>
                        <span id="geo_company_status" class="small text-muted ms-2"></span>
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

@push('scripts')
<script>
    document.getElementById('logo')?.addEventListener('change', function () {
        const selected = this.files.length > 0;
        document.getElementById('logo_selected').value = selected ? '1' : '0';
        document.getElementById('logo_base64').value = '';

        if (! selected) {
            return;
        }

        const file = this.files[0];
        if (! ['image/jpeg', 'image/png'].includes(file.type) || file.size > 2 * 1024 * 1024) {
            return;
        }

        const reader = new FileReader();
        reader.onload = function () {
            document.getElementById('logo_base64').value = String(reader.result || '');
        };
        reader.readAsDataURL(file);
    });

    // Ambil Koordinat Perusahaan/Kantor
    document.getElementById('btnGetCompanyLocation')?.addEventListener('click', function () {
        const geoStatus = document.getElementById('geo_company_status');
        const latField = document.getElementById('company_latitude');
        const lngField = document.getElementById('company_longitude');

        if (!navigator.geolocation) {
            if (geoStatus) geoStatus.textContent = 'Browser Anda tidak mendukung Geolocation.';
            return;
        }

        if (geoStatus) geoStatus.textContent = 'Mendeteksi koordinat...';

        navigator.geolocation.getCurrentPosition(function (position) {
            latField.value = position.coords.latitude.toFixed(7);
            lngField.value = position.coords.longitude.toFixed(7);
            const accuracy = Math.round(position.coords.accuracy || 0);
            if (geoStatus) geoStatus.textContent = 'Terekam (Akurasi: +/- ' + accuracy + 'm)';
        }, function (error) {
            if (geoStatus) geoStatus.textContent = 'Gagal mendeteksi: ' + error.message;
        }, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        });
    });
</script>
@endpush
