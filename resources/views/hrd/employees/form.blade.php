@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Karyawan' : 'Tambah Karyawan')

@section('content')
@include('partials.alerts')
@php($joinDateValue = $employee->join_date instanceof \Illuminate\Support\Carbon ? $employee->join_date->format('Y-m-d') : $employee->join_date)

<form method="POST" action="{{ $isEdit ? route('hrd.employees.update', $employee) : route('hrd.employees.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">Data Pribadi & Akun</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>NIK <span class="text-danger">*</span></label>
                            <input type="text" name="nik" class="form-control" value="{{ old('nik', $employee->nik) }}" required placeholder="KRY-001">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label>Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="fullname" class="form-control" value="{{ old('fullname', $employee->fullname) }}" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>No. HP / WA</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $employee->phone) }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Departemen</label>
                            <select name="department" class="form-select">
                                <option value="">-- Pilih --</option>
                                @foreach($departments as $value => $label)
                                    <option value="{{ $value }}" @selected(old('department', $employee->department) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Alamat Domisili</label>
                        <textarea name="address" class="form-control" rows="2">{{ old('address', $employee->address) }}</textarea>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Username (Login)</label>
                            <input type="text" name="username" class="form-control fw-bold" value="{{ old('username', $employee->username) }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" {{ $isEdit ? '' : 'required' }} placeholder="{{ $isEdit ? '(Biarkan kosong jika tetap)' : '' }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Role / Jabatan Sistem <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select" required>
                            <option value="">-- Pilih Role --</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" @selected((string) old('role_id', $employee->role_id) === (string) $role->id)>{{ $role->role_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">Status & Penggajian</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Tanggal Masuk</label>
                            <input type="date" name="join_date" class="form-control" value="{{ old('join_date', $joinDateValue) }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Status Karyawan</label>
                            <select name="employee_status" class="form-select">
                                <option value="probation" @selected(old('employee_status', $employee->employee_status) === 'probation')>Probation (Percobaan)</option>
                                <option value="contract" @selected(old('employee_status', $employee->employee_status) === 'contract')>Contract (PKWT)</option>
                                <option value="permanent" @selected(old('employee_status', $employee->employee_status) === 'permanent')>Permanent (Tetap)</option>
                                <option value="resigned" @selected(old('employee_status', $employee->employee_status) === 'resigned')>Resigned (Keluar)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Gaji Pokok (Basic Salary)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" name="basic_salary" class="form-control fw-bold text-end" value="{{ old('basic_salary', number_format((float) $employee->basic_salary, 0, ',', '.')) }}" onkeyup="formatRibuan(this)">
                        </div>
                        <small class="text-muted">Akan digunakan sebagai default di modul Payroll.</small>
                    </div>

                    <div class="mb-3">
                        <label>Info Rekening Bank</label>
                        <input type="text" name="bank_account" class="form-control" value="{{ old('bank_account', $employee->bank_account) }}" placeholder="Nama Bank - No. Rekening">
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="{{ route('hrd.employees.index') }}" class="btn btn-secondary btn-lg px-4">Kembali</a>
                <button type="submit" class="btn btn-primary btn-lg px-5 shadow">Simpan Data</button>
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
