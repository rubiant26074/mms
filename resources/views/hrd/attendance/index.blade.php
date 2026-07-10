@extends('layouts.mms')

@section('title', 'Absensi Karyawan')

@section('content')
@include('partials.alerts')
@php
$hasIn = $todayAttendance !== null;
$hasOut = $todayAttendance && $todayAttendance->clock_out;
@endphp

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card shadow border-primary h-100">
            <div class="card-header bg-primary text-white text-center d-flex justify-content-between align-items-center">
                <div class="text-start">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge"></i> Pencatatan Absensi</h5>
                    <small>{{ now()->translatedFormat('l, d F Y') }}</small>
                </div>
                @if($isHrd || $currentUser->role?->role_slug === 'admin')
                    <button type="button" class="btn btn-dark btn-sm text-white border-secondary" data-bs-toggle="modal" data-bs-target="#qrCodeModal">
                        <i class="bi bi-qr-code"></i> QR Kantor
                    </button>
                @endif
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

                @if(!$hasIn || !$hasOut)
                    <!-- Nav Tabs Absensi -->
                    <ul class="nav nav-pills nav-fill mb-3 border p-1 rounded bg-light" id="attendanceTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active small py-2 fw-semibold" id="selfie-tab" data-bs-toggle="tab" data-bs-target="#selfie-pane" type="button" role="tab" aria-controls="selfie-pane" aria-selected="true">
                                <i class="bi bi-camera me-1"></i> Selfie + GPS
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link small py-2 fw-semibold" id="qr-tab" data-bs-toggle="tab" data-bs-target="#qr-pane" type="button" role="tab" aria-controls="qr-pane" aria-selected="false">
                                <i class="bi bi-qr-code-scan me-1"></i> QR Code + GPS
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="attendanceTabsContent">
                        <!-- Tab 1: Selfie + Geotag -->
                        <div class="tab-pane fade show active" id="selfie-pane" role="tabpanel" aria-labelledby="selfie-tab">
                            @if(trim((string) $currentUser->face_reference_path) === '')
                                <div class="alert alert-warning mb-3">
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
                                    <input type="hidden" name="method" value="selfie_geotag">
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
                                    <input type="hidden" name="method" value="selfie_geotag">
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
                            @endif
                        </div>

                        <!-- Tab 2: QR Code + Geotag -->
                        <div class="tab-pane fade" id="qr-pane" role="tabpanel" aria-labelledby="qr-tab">
                            @if($hasIn && !$hasOut)
                                <div class="alert alert-success py-2 mb-3">
                                    Masuk: <strong>{{ \Illuminate\Support\Carbon::parse($todayAttendance->clock_in)->format('H:i') }}</strong>
                                    @if($todayAttendance->clock_in_distance_meters)
                                        <br><small>Jarak: {{ number_format((float) $todayAttendance->clock_in_distance_meters, 1, ',', '.') }} m</small>
                                    @endif
                                </div>
                            @endif

                            <form method="POST" action="{{ route('hrd.attendance.store') }}" class="attendance-form-qr" id="qrAttendanceForm">
                                @csrf
                                <input type="hidden" name="type" value="{{ $hasIn ? 'out' : 'in' }}">
                                <input type="hidden" name="method" value="qr_geotag">
                                <input type="hidden" name="latitude" class="qr-latitude-field" id="qr_latitude">
                                <input type="hidden" name="longitude" class="qr-longitude-field" id="qr_longitude">
                                <input type="hidden" name="qr_code" class="qr-code-field" id="qr_code_value">

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Scan QR Code Kehadiran</label>
                                    <div id="qr-reader" style="width: 100%; min-height: 250px; background: #fafafa;" class="rounded border overflow-hidden mb-2"></div>
                                    <div class="form-text text-secondary small">Arahkan kamera ke QR Code resmi absensi yang disediakan di kantor.</div>
                                </div>

                                <div class="small text-muted mb-3 qr-geo-status" id="qrGeoStatus">Lokasi GPS belum dideteksi.</div>

                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-primary w-50 btn-detect-qr-geo" id="btnGetQrGeo">
                                        <i class="bi bi-crosshair"></i> Ambil GPS
                                    </button>
                                    <button type="button" class="btn btn-success w-50" id="btnStartQrScan">
                                        <i class="bi bi-camera"></i> Mulai Scan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="alert alert-info text-center">
                        <i class="bi bi-check-circle-fill display-4 d-block mb-2 text-success"></i>
                        <h5>Kehadiran Hari Ini Selesai</h5>
                        <p class="mb-0 small text-muted">Anda telah melakukan absen masuk dan pulang.</p>
                        <hr>
                        <p class="mb-0 small">
                            Masuk: <strong>{{ \Illuminate\Support\Carbon::parse($todayAttendance->clock_in)->format('H:i') }}</strong><br>
                            Pulang: <strong>{{ \Illuminate\Support\Carbon::parse($todayAttendance->clock_out)->format('H:i') }}</strong>
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
                                <th class="text-center">Metode</th>
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
                                    <td class="text-center">
                                        @if($row->attendance_method === 'qr_geotag')
                                            <span class="badge bg-info text-dark"><i class="bi bi-qr-code"></i> QR Code</span>
                                        @elseif($row->attendance_method === 'selfie_geotag')
                                            <span class="badge bg-secondary"><i class="bi bi-camera"></i> Selfie Camera</span>
                                        @else
                                            <span class="badge bg-light text-dark">{{ $row->attendance_method ?: '-' }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center fw-bold">{{ $row->clock_in ? \Illuminate\Support\Carbon::parse($row->clock_in)->format('H:i') : '-' }}</td>
                                    <td class="text-center">{{ $row->clock_out ? \Illuminate\Support\Carbon::parse($row->clock_out)->format('H:i') : '-' }}</td>
                                    <td class="text-center">
                                        @if($row->clock_in_photo)
                                            <a href="{{ asset($row->clock_in_photo) }}" target="_blank" class="btn btn-xs btn-outline-success mb-1" style="font-size: 0.75rem; padding: 2px 6px;">Masuk</a>
                                        @endif
                                        @if($row->clock_out_photo)
                                            <a href="{{ asset($row->clock_out_photo) }}" target="_blank" class="btn btn-xs btn-outline-danger" style="font-size: 0.75rem; padding: 2px 6px;">Pulang</a>
                                        @endif
                                        @if(!$row->clock_in_photo && !$row->clock_out_photo)
                                            -
                                        @endif
                                    </td>
                                    <td class="text-center small">{{ collect([
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

@if($isHrd || $currentUser->role?->role_slug === 'admin')
    <!-- Modal QR Code Kantor -->
    <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="qrCodeModalLabel"><i class="bi bi-qr-code"></i> QR Code Absensi Kantor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    @php
                        $qrData = 'MMS-ABSEN-' . md5($company->id . '-' . $company->company_name);
                        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($qrData);
                    @endphp
                    <img src="{{ $qrUrl }}" alt="Office QR Code" class="img-fluid border p-3 bg-white rounded shadow-sm mb-3" style="width: 250px; height: 250px;">
                    <h6 class="fw-bold text-dark mb-1">MMS Absensi QR Code</h6>
                    <p class="text-muted small mb-0 px-3">Tampilkan kode QR ini di layar lobi atau cetak agar karyawan dapat melakukan scan untuk absensi masuk/pulang.</p>
                    <div class="mt-3">
                        <span class="badge bg-secondary font-monospace p-2" style="font-size: 0.85rem;">Token: {{ $qrData }}</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endif

<script src="https://unpkg.com/html5-qrcode"></script>
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

// 1. Logika Absensi Selfie (GPS Deteksi)
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

// 2. Logika Absensi QR Code (GPS + Scanner)
document.addEventListener("DOMContentLoaded", function () {
    const qrForm = document.getElementById('qrAttendanceForm');
    if (!qrForm) return;

    const btnGetQrGeo = document.getElementById('btnGetQrGeo');
    const btnStartQrScan = document.getElementById('btnStartQrScan');
    const qrGeoStatus = document.getElementById('qrGeoStatus');
    const qrLatitude = document.getElementById('qr_latitude');
    const qrLongitude = document.getElementById('qr_longitude');
    const qrCodeValue = document.getElementById('qr_code_value');

    let html5QrcodeScanner = null;

    function setQrGeoStatus(message, isError = false) {
        if (!qrGeoStatus) return;
        qrGeoStatus.textContent = message;
        qrGeoStatus.classList.toggle('text-danger', isError);
        qrGeoStatus.classList.toggle('text-muted', !isError);
    }

    // Ambil GPS untuk QR Code
    btnGetQrGeo?.addEventListener('click', function () {
        if (!navigator.geolocation) {
            setQrGeoStatus('Browser tidak mendukung geolocation.', true);
            return;
        }
        setQrGeoStatus('Mengambil lokasi GPS...');
        navigator.geolocation.getCurrentPosition(function (position) {
            qrLatitude.value = position.coords.latitude.toFixed(7);
            qrLongitude.value = position.coords.longitude.toFixed(7);
            const accuracy = Math.round(position.coords.accuracy || 0);
            setQrGeoStatus('Lokasi GPS terekam. Akurasi +/- ' + accuracy + ' meter.');
        }, function (error) {
            setQrGeoStatus('Gagal mengambil lokasi: ' + error.message, true);
        }, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        });
    });

    // Jalankan QR Scanner
    btnStartQrScan?.addEventListener('click', function () {
        // Cek GPS dahulu
        if (!qrLatitude.value || !qrLongitude.value) {
            setQrGeoStatus('Silakan ambil lokasi GPS terlebih dahulu sebelum memulai scan.', true);
            return;
        }

        btnStartQrScan.disabled = true;
        btnStartQrScan.textContent = "Scanner Aktif...";

        if (!html5QrcodeScanner) {
            html5QrcodeScanner = new Html5Qrcode("qr-reader");
        }

        const qrSuccessCallback = (decodedText, decodedResult) => {
            // Hentikan scanner
            html5QrcodeScanner.stop().then((ignore) => {
                qrCodeValue.value = decodedText;
                setQrGeoStatus('QR Code berhasil di-scan. Mengirim data absensi...');
                
                // Submit Form secara otomatis
                qrForm.submit();
            }).catch((err) => {
                console.error("Gagal menghentikan scanner: ", err);
                qrForm.submit();
            });
        };

        const config = { fps: 10, qrbox: { width: 220, height: 220 } };

        html5QrcodeScanner.start(
            { facingMode: "environment" },
            config,
            qrSuccessCallback,
            (errorMessage) => {
                // parse error diam-diam untuk scan kontinu
            }
        ).catch((err) => {
            setQrGeoStatus('Gagal memulai kamera: ' + err, true);
            btnStartQrScan.disabled = false;
            btnStartQrScan.textContent = "Mulai Scan";
        });
    });
});
</script>
@endsection
