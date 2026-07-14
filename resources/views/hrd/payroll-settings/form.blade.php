@extends('layouts.mms')

@section('title', 'Pengaturan Payroll & Jam Kerja')

@section('content')
@include('partials.alerts')

@php
    function formatTime($time) {
        return $time ? substr($time, 0, 5) : '';
    }
@endphp

<form method="POST" action="{{ route('hrd.payroll_settings.update') }}">
    @csrf
    @method('PUT')

    <div class="row mb-3">
        <div class="col-md-8">
            <h3 class="fw-bold"><i class="bi bi-gear-fill"></i> Pengaturan Payroll & Jam Kerja</h3>
            <p class="text-muted">Kelola standar jam kerja karyawan dan perhitungan lembur (overtime).</p>
        </div>
        <div class="col-md-4 text-end">
            <button type="submit" class="btn btn-primary shadow-sm px-4 btn-lg"><i class="bi bi-save-fill"></i> Simpan Pengaturan</button>
        </div>
    </div>

    <div class="row">
        <!-- 1. Jam Kerja Standar -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4 h-100">
                <div class="card-header bg-primary text-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history"></i> 1. Jam Kerja Standar</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="fw-bold text-dark d-block mb-2">Senin - Kamis</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <small class="text-muted">Masuk</small>
                                <input type="time" name="working_hour_mon_thu_start" class="form-control" value="{{ old('working_hour_mon_thu_start', formatTime($setting->working_hour_mon_thu_start)) }}" required>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Pulang</small>
                                <input type="time" name="working_hour_mon_thu_end" class="form-control" value="{{ old('working_hour_mon_thu_end', formatTime($setting->working_hour_mon_thu_end)) }}" required>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="fw-bold text-dark d-block mb-2">Jumat</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <small class="text-muted">Masuk</small>
                                <input type="time" name="working_hour_fri_start" class="form-control" value="{{ old('working_hour_fri_start', formatTime($setting->working_hour_fri_start)) }}" required>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Pulang</small>
                                <input type="time" name="working_hour_fri_end" class="form-control" value="{{ old('working_hour_fri_end', formatTime($setting->working_hour_fri_end)) }}" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Overtime Hari Kerja -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4 h-100">
                <div class="card-header bg-success text-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-briefcase-fill"></i> 2. Overtime Hari Kerja</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="fw-bold text-dark d-block mb-2">Lembur Jam Pertama</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <small class="text-muted">Mulai</small>
                                <input type="time" name="ot_workday_hour1_start" class="form-control" value="{{ old('ot_workday_hour1_start', formatTime($setting->ot_workday_hour1_start)) }}" required>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Selesai</small>
                                <input type="time" name="ot_workday_hour1_end" class="form-control" value="{{ old('ot_workday_hour1_end', formatTime($setting->ot_workday_hour1_end)) }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold text-dark d-block mb-2">Lembur Jam Kedua (Seterusnya)</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <small class="text-muted">Mulai</small>
                                <input type="time" name="ot_workday_hour2_start" class="form-control" value="{{ old('ot_workday_hour2_start', formatTime($setting->ot_workday_hour2_start)) }}" required>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Selesai (Opsional)</small>
                                <input type="time" name="ot_workday_hour2_end" class="form-control" value="{{ old('ot_workday_hour2_end', formatTime($setting->ot_workday_hour2_end)) }}">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold text-dark d-block mb-2">Jam Istirahat (Tidak Dihitung Lembur)</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <small class="text-muted">Mulai</small>
                                <input type="time" name="ot_workday_rest_start" class="form-control" value="{{ old('ot_workday_rest_start', formatTime($setting->ot_workday_rest_start)) }}" required>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Selesai</small>
                                <input type="time" name="ot_workday_rest_end" class="form-control" value="{{ old('ot_workday_rest_end', formatTime($setting->ot_workday_rest_end)) }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="fw-bold text-dark mb-1">Nominal Lembur per Jam</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">Rp</span>
                            <input type="text" name="ot_workday_rate" class="form-control text-end fw-bold" value="{{ old('ot_workday_rate', number_format((float) $setting->ot_workday_rate, 0, ',', '.')) }}" onkeyup="formatRibuan(this)" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Overtime Hari Libur -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4 h-100">
                <div class="card-header bg-danger text-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-x-fill"></i> 3. Overtime Hari Libur</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="fw-bold text-dark d-block mb-2">Shift 1</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <small class="text-muted">Mulai</small>
                                <input type="time" name="ot_holiday_shift1_start" class="form-control" value="{{ old('ot_holiday_shift1_start', formatTime($setting->ot_holiday_shift1_start)) }}" required>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Selesai</small>
                                <input type="time" name="ot_holiday_shift1_end" class="form-control" value="{{ old('ot_holiday_shift1_end', formatTime($setting->ot_holiday_shift1_end)) }}" required>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Istirahat Mulai</small>
                                <input type="time" name="ot_holiday_shift1_rest_start" class="form-control" value="{{ old('ot_holiday_shift1_rest_start', formatTime($setting->ot_holiday_shift1_rest_start)) }}" required>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Istirahat Selesai</small>
                                <input type="time" name="ot_holiday_shift1_rest_end" class="form-control" value="{{ old('ot_holiday_shift1_rest_end', formatTime($setting->ot_holiday_shift1_rest_end)) }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold text-dark d-block mb-2">Shift 2</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <small class="text-muted">Mulai</small>
                                <input type="time" name="ot_holiday_shift2_start" class="form-control" value="{{ old('ot_holiday_shift2_start', formatTime($setting->ot_holiday_shift2_start)) }}" required>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Selesai (Opsional)</small>
                                <input type="time" name="ot_holiday_shift2_end" class="form-control" value="{{ old('ot_holiday_shift2_end', formatTime($setting->ot_holiday_shift2_end)) }}">
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Istirahat Mulai</small>
                                <input type="time" name="ot_holiday_shift2_rest_start" class="form-control" value="{{ old('ot_holiday_shift2_rest_start', formatTime($setting->ot_holiday_shift2_rest_start)) }}" required>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Istirahat Selesai</small>
                                <input type="time" name="ot_holiday_shift2_rest_end" class="form-control" value="{{ old('ot_holiday_shift2_rest_end', formatTime($setting->ot_holiday_shift2_rest_end)) }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="fw-bold text-dark mb-1">Nominal Lembur per Jam</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">Rp</span>
                            <input type="text" name="ot_holiday_rate" class="form-control text-end fw-bold" value="{{ old('ot_holiday_rate', number_format((float) $setting->ot_holiday_rate, 0, ',', '.')) }}" onkeyup="formatRibuan(this)" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function formatRibuan(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    input.value = new Intl.NumberFormat('id-ID').format(value);
}
</script>
@endsection
