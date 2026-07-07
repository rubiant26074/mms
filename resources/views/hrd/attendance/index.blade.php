@extends('layouts.mms')

@section('title', 'Absensi Karyawan')

@section('content')
@include('partials.alerts')
@php($hasIn = $todayAttendance !== null)
@php($hasOut = $todayAttendance && $todayAttendance->clock_out)

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card shadow border-primary h-100">
            <div class="card-header bg-primary text-white text-center">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> Absensi Selfie</h5>
                <small>{{ now()->translatedFormat('l, d F Y') }}</small>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <h2 class="fw-bold text-dark mb-2" id="clockDisplay">00:00:00</h2>
                    @if(($company->attendance_location_name ?? '') !== '' || $geoRequired)
                        <div class="small text-muted">
                            Lokasi target:
                            <strong>{{ ($company->attendance_location_name ?? '') !== '' ? $company->attendance_location_name : 'Titik Admin' }}</strong>
                            @if($geoRequired)
                                <br>Radius valid: {{ (int) $company->attendance_radius_meters }} meter
                            @endif
                        </div>
                    @endif
                </div>

                @if(trim((string) $currentUser->face_reference_path) === '')
                    <div class="alert alert-warning">
                        Registrasi wajah belum ada. Buka `User Setting` untuk mengambil foto wajah referensi terlebih dahulu.
                    </div>
                @else
                    <div class="text-center mb-3">
                        <div class="small text-muted mb-2">Wajah referensi terdaftar</div>
                        <img src="{{ asset($currentUser->face_reference_path) }}" alt="Face Reference" class="rounded border" style="width:100px; height:100px; object-fit:cover;">
                    </div>
                @endif

                @if(!$hasIn)
                    <form method="POST" action="{{ route('hrd.attendance.store') }}" enctype="multipart/form-data" class="attendance-form" data-form-type="in">
                        @csrf
                        <input type="hidden" name="type" value="in">
                        <input type="hidden" name="latitude" class="latitude-field">
                        <input type="hidden" name="longitude" class="longitude-field">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Foto Selfie Masuk</label>
                            <input type="file" name="selfie_in" class="form-control" accept="image/*" capture="user" required>
                            <div class="form-text">Gunakan kamera depan Android untuk foto selfie absensi masuk.</div>
                        </div>
                        <div class="small text-muted mb-3 geo-status">Lokasi belum diambil.</div>
                        <button type="button" class="btn btn-outline-primary w-100 mb-2 btn-detect-geo">
                            <i class="bi bi-crosshair"></i> Ambil Lokasi GPS
                        </button>
                        <button type="submit" class="btn btn-success btn-lg w-100 py-3 shadow">
                            <i class="bi bi-box-arrow-in-right display-6 d-block mb-1"></i>
                            ABSEN MASUK
                        </button>
                    </form>
                @elseif(!$hasOut)
                    <div class="alert alert-success py-2 mb-3">
                        Masuk: <strong>{{ \Illuminate\Support\Carbon::parse($todayAttendance->clock_in)->format('H:i') }}</strong>
                        @if($todayAttendance->clock_in_distance_meters)
                            <br><small>Jarak: {{ number_format((float) $todayAttendance->clock_in_distance_meters, 1, ',', '.') }} m</small>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('hrd.attendance.store') }}" enctype="multipart/form-data" class="attendance-form" data-form-type="out">
                        @csrf
                        <input type="hidden" name="type" value="out">
                        <input type="hidden" name="latitude" class="latitude-field">
                        <input type="hidden" name="longitude" class="longitude-field">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Foto Selfie Pulang</label>
                            <input type="file" name="selfie_out" class="form-control" accept="image/*" capture="user" required>
                            <div class="form-text">Gunakan kamera depan Android untuk foto selfie absensi pulang.</div>
                        </div>
                        <div class="small text-muted mb-3 geo-status">Lokasi belum diambil.</div>
                        <button type="button" class="btn btn-outline-primary w-100 mb-2 btn-detect-geo">
                            <i class="bi bi-crosshair"></i> Ambil Lokasi GPS
                        </button>
                        <button type="submit" class="btn btn-danger btn-lg w-100 py-3 shadow" onclick="return confirm('Yakin ingin absen pulang?')">
                            <i class="bi bi-box-arrow-left display-6 d-block mb-1"></i>
                            ABSEN PULANG
                        </button>
                    </form>
                @else
                    <div class="alert alert-info">
                        <i class="bi bi-check-circle-fill display-4 d-block mb-2"></i>
                        <h5>Kehadiran Selesai</h5>
                        <p class="mb-0">
                            In: <strong>{{ \Illuminate\Support\Carbon::parse($todayAttendance->clock_in)->format('H:i') }}</strong><br>
                            Out: <strong>{{ \Illuminate\Support\Carbon::parse($todayAttendance->clock_out)->format('H:i') }}</strong>
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">
                    {{ $isHrd ? 'Rekap Harian: ' . \Illuminate\Support\Carbon::parse($filterDate)->format('d/m/Y') : 'Riwayat Kehadiran Saya' }}
                </h6>

                @if($isHrd)
                    <form class="d-flex" method="GET">
                        <input type="date" name="date" class="form-control form-control-sm me-2" value="{{ $filterDate }}">
                        <button type="submit" class="btn btn-sm btn-primary">Cari</button>
                    </form>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-striped">
                        <thead class="table-light">
                            <tr>
                                @if($isHrd)
                                    <th>Nama Karyawan</th>
                                    <th>Jabatan</th>
                                @else
                                    <th>Tanggal</th>
                                @endif
                                <th class="text-center">Jam Masuk</th>
                                <th class="text-center">Jam Pulang</th>
                                <th class="text-center">Selfie</th>
                                <th class="text-center">Geotag</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $row)
                                <tr>
                                    @if($isHrd)
                                        <td><strong>{{ $row->user?->fullname ?: '-' }}</strong></td>
                                        <td><small class="text-muted">{{ $row->user?->role?->role_name ?: '-' }}</small></td>
                                    @else
                                        <td>{{ optional($row->date)->format('d/m/Y') }}</td>
                                    @endif
                                    <td class="text-center fw-bold">{{ $row->clock_in ? \Illuminate\Support\Carbon::parse($row->clock_in)->format('H:i') : '-' }}</td>
                                    <td class="text-center">{{ $row->clock_out ? \Illuminate\Support\Carbon::parse($row->clock_out)->format('H:i') : '-' }}</td>
                                    <td class="text-center">
                                        @if($row->clock_in_photo)
                                            <a href="{{ asset($row->clock_in_photo) }}" target="_blank" class="btn btn-sm btn-outline-success mb-1">Masuk</a>
                                        @endif
                                        @if($row->clock_out_photo)
                                            <a href="{{ asset($row->clock_out_photo) }}" target="_blank" class="btn btn-sm btn-outline-danger">Pulang</a>
                                        @endif
                                        @if(!$row->clock_in_photo && !$row->clock_out_photo)
                                            -
                                        @endif
                                    </td>
                                    <td class="text-center">{{ collect([
                                        $row->clock_in_distance_meters ? 'IN ' . number_format((float) $row->clock_in_distance_meters, 1, ',', '.') . 'm' : null,
                                        $row->clock_out_distance_meters ? 'OUT ' . number_format((float) $row->clock_out_distance_meters, 1, ',', '.') . 'm' : null,
                                    ])->filter()->implode(' | ') ?: '-' }}</td>
                                    <td class="text-center"><span class="badge {{ $row->status === 'late' ? 'bg-warning text-dark' : ($row->status === 'absent' ? 'bg-danger' : 'bg-success') }}">{{ strtoupper((string) $row->status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada data absensi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { hour12: false });
    const clock = document.getElementById('clockDisplay');
    if (clock) {
        clock.textContent = timeString;
    }
}
setInterval(updateClock, 1000);
updateClock();

document.querySelectorAll('.attendance-form').forEach(function (form) {
    const detectButton = form.querySelector('.btn-detect-geo');
    const latField = form.querySelector('.latitude-field');
    const lngField = form.querySelector('.longitude-field');
    const geoStatus = form.querySelector('.geo-status');

    function setGeoStatus(message, isError = false) {
        if (!geoStatus) return;
        geoStatus.textContent = message;
        geoStatus.classList.toggle('text-danger', isError);
        geoStatus.classList.toggle('text-muted', !isError);
    }

    detectButton?.addEventListener('click', function () {
        if (!navigator.geolocation) {
            setGeoStatus('Browser tidak mendukung geolocation.', true);
            return;
        }
        setGeoStatus('Mengambil lokasi GPS...');
        navigator.geolocation.getCurrentPosition(function (position) {
            latField.value = position.coords.latitude.toFixed(7);
            lngField.value = position.coords.longitude.toFixed(7);
            const accuracy = Math.round(position.coords.accuracy || 0);
            setGeoStatus('Lokasi terekam. Akurasi +/- ' + accuracy + ' meter.');
        }, function (error) {
            setGeoStatus('Gagal mengambil lokasi: ' + error.message, true);
        }, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        });
    });

    form.addEventListener('submit', function (event) {
        if (!latField.value || !lngField.value) {
            event.preventDefault();
            setGeoStatus('Silakan ambil lokasi GPS terlebih dahulu.', true);
        }
    });
});
</script>
@endsection
