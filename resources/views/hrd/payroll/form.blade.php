@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Gaji' : 'Buat Slip Gaji')

@section('content')
@include('partials.alerts')
@php
    $startValue = $payroll->period_start instanceof \Illuminate\Support\Carbon ? $payroll->period_start->format('Y-m-d') : $payroll->period_start;
    $endValue = $payroll->period_end instanceof \Illuminate\Support\Carbon ? $payroll->period_end->format('Y-m-d') : $payroll->period_end;
    $storedAllowances = is_array($payroll->allowance_details) ? $payroll->allowance_details : [];
    if (empty($storedAllowances) && $payroll->allowance_total > 0) {
        $storedAllowances = [['name' => 'Tunjangan', 'amount' => (float) $payroll->allowance_total]];
    }

    $storedDeductions = is_array($payroll->deduction_details) ? $payroll->deduction_details : [];
    if (empty($storedDeductions) && $payroll->deduction_total > 0) {
        $storedDeductions = [['name' => 'Potongan', 'amount' => (float) $payroll->deduction_total]];
    }
@endphp

<form method="POST" action="{{ $isEdit ? route('hrd.payroll.update', $payroll) : route('hrd.payroll.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row">
        <!-- Kolom Kiri: Info Karyawan & Periode -->
        <div class="col-md-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-person-badge"></i> Info Karyawan & Periode</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">No. Slip</label>
                        <input type="text" class="form-control fw-bold bg-light" value="{{ $payroll->payroll_code ?: 'AUTO' }}" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Pilih Karyawan <span class="text-danger">*</span></label>
                        <select name="user_id" id="empSelect" class="form-select" required onchange="getEmpInfo()">
                            <option value="">-- Pilih Karyawan --</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" data-basic="{{ (float) $employee->basic_salary }}" @selected((string) old('user_id', $payroll->user_id) === (string) $employee->id)>
                                    {{ $employee->fullname }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label text-muted small fw-bold">Dari Tanggal</label>
                            <input type="date" name="period_start" id="pStart" class="form-control" value="{{ old('period_start', $startValue) }}" onchange="calcAttendance()">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label text-muted small fw-bold">Sampai Tanggal</label>
                            <input type="date" name="period_end" id="pEnd" class="form-control" value="{{ old('period_end', $endValue) }}" onchange="calcAttendance()">
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold d-block">Mode Perhitungan Kehadiran</label>
                        <div class="form-check form-check-inline me-3">
                            <input class="form-check-input" type="radio" name="attendance_mode" id="modeAuto" value="auto" @checked(old('attendance_mode', $payroll->attendance_mode ?? 'auto') === 'auto') onchange="toggleAttendMode()">
                            <label class="form-check-label" for="modeAuto">Otomatis (Tarik Absensi)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="attendance_mode" id="modeManual" value="manual" @checked(old('attendance_mode', $payroll->attendance_mode ?? 'auto') === 'manual') onchange="toggleAttendMode()">
                            <label class="form-check-label" for="modeManual">Manual (Isi Sendiri)</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Jumlah Kehadiran (Hari) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="total_attendance" id="totAttend" class="form-control" value="{{ old('total_attendance', (int) $payroll->total_attendance) }}" required>
                            <button class="btn btn-outline-secondary" type="button" id="btnSyncAttend" onclick="calcAttendance()">Hitung Ulang</button>
                        </div>
                        <small class="text-muted" id="attendHelper">Dihitung otomatis dari data Absensi (Hadir/Terlambat).</small>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label text-muted small fw-bold">Catatan Slip Gaji</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Tambahkan catatan jika diperlukan...">{{ old('notes', $payroll->notes) }}</textarea>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('hrd.payroll.index') }}" class="btn btn-outline-secondary px-4">Batal</a>
                <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-save"></i> Simpan Gaji</button>
            </div>
        </div>

        <!-- Kolom Ranan: Rincian Gaji & Komponen -->
        <div class="col-md-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-cash-stack"></i> Komponen Penggajian</h6>
                </div>
                <div class="card-body p-4">
                    <!-- Gaji Pokok -->
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold">Gaji Pokok (Basic)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light fw-bold">Rp</span>
                            <input type="text" name="basic_salary" id="basicSal" class="form-control text-end fw-bold" value="{{ old('basic_salary', number_format((float) $payroll->basic_salary, 0, ',', '.')) }}" required onkeyup="formatRibuan(this); calcNet()">
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- List Tunjangan -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0 text-success"><i class="bi bi-plus-circle-fill"></i> Komponen Tunjangan (+)</h6>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="addAllowanceRow('', 0)"><i class="bi bi-plus-lg"></i> Tambah Tunjangan</button>
                        </div>
                        <div id="allowance-list">
                            <!-- Tempat Baris Tunjangan Ditambahkan -->
                        </div>
                        <div class="row g-2 mt-2 pt-2 border-top">
                            <div class="col-7 text-end text-muted small align-self-center">Total Tunjangan:</div>
                            <div class="col-4">
                                <input type="text" id="allowance" class="form-control form-control-sm text-end text-success fw-bold bg-light" value="{{ old('allowance_total', number_format((float) $payroll->allowance_total, 0, ',', '.')) }}" readonly>
                            </div>
                            <div class="col-1"></div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- List Potongan -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0 text-danger"><i class="bi bi-dash-circle-fill"></i> Komponen Potongan (-)</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="addDeductionRow('', 0)"><i class="bi bi-plus-lg"></i> Tambah Potongan</button>
                        </div>
                        <div id="deduction-list">
                            <!-- Tempat Baris Potongan Ditambahkan -->
                        </div>
                        <div class="row g-2 mt-2 pt-2 border-top">
                            <div class="col-7 text-end text-muted small align-self-center">Total Potongan:</div>
                            <div class="col-4">
                                <input type="text" id="deduct" class="form-control form-control-sm text-end text-danger fw-bold bg-light" value="{{ old('deduction_total', number_format((float) $payroll->deduction_total, 0, ',', '.')) }}" readonly>
                            </div>
                            <div class="col-1"></div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Hasil Akhir -->
                    <div class="bg-light p-4 rounded border">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted d-block small fw-bold text-uppercase">Gaji Bersih (Take Home Pay)</span>
                                <span class="text-muted small">Net = Gaji Pokok + Tunjangan - Potongan</span>
                            </div>
                            <h3 class="text-end text-primary fw-bold mb-0" id="netSalary">Rp {{ number_format((float) $payroll->net_salary, 0, ',', '.') }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Initial Data
const initAllowances = @json($storedAllowances);
const initDeductions = @json($storedDeductions);

document.addEventListener('DOMContentLoaded', () => {
    if (initAllowances.length > 0) {
        initAllowances.forEach(item => addAllowanceRow(item.name, item.amount));
    }
    if (initDeductions.length > 0) {
        initDeductions.forEach(item => addDeductionRow(item.name, item.amount));
    }
    toggleAttendMode();
    sumAllowances();
    sumDeductions();
});

function formatRibuan(input) {
    let val = input.value.replace(/[^0-9]/g, '');
    input.value = new Intl.NumberFormat('id-ID').format(val);
}

function formatCurrency(num) {
    return new Intl.NumberFormat('id-ID').format(num);
}

// 1. Kehadiran Mode Toggle
function toggleAttendMode() {
    const isAuto = document.getElementById('modeAuto').checked;
    const totAttend = document.getElementById('totAttend');
    const btnSync = document.getElementById('btnSyncAttend');
    const helper = document.getElementById('attendHelper');
    
    if (isAuto) {
        totAttend.setAttribute('readonly', 'true');
        btnSync.style.display = 'inline-block';
        helper.innerText = "Dihitung otomatis dari data Absensi (Hadir/Terlambat).";
        calcAttendance();
    } else {
        totAttend.removeAttribute('readonly');
        btnSync.style.display = 'none';
        helper.innerText = "Ketikkan jumlah hari kehadiran karyawan secara manual.";
    }
}

function getEmpInfo() {
    const sel = document.getElementById('empSelect');
    const opt = sel.options[sel.selectedIndex];
    const basic = opt ? (opt.getAttribute('data-basic') || 0) : 0;

    document.getElementById('basicSal').value = formatCurrency(basic);
    calcAttendance();
    calcNet();
}

function calcAttendance() {
    const uid = document.getElementById('empSelect').value;
    const start = document.getElementById('pStart').value;
    const end = document.getElementById('pEnd').value;
    const isAuto = document.getElementById('modeAuto').checked;

    if (!isAuto) return;

    if (uid && start && end) {
        fetch(`{{ route('hrd.payroll.attendance_count') }}?uid=${uid}&start=${start}&end=${end}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('totAttend').value = data.count || 0;
            });
    }
}

// 2. Allowances (Tunjangan)
function addAllowanceRow(name = '', amount = 0) {
    const list = document.getElementById('allowance-list');
    const rowId = 'allowance_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
    
    const html = `
        <div class="row g-2 mb-2 align-items-center allowance-row" id="${rowId}">
            <div class="col-7">
                <input type="text" name="allowance_names[]" class="form-control form-control-sm" placeholder="Nama Tunjangan" value="${name}" required>
            </div>
            <div class="col-4">
                <input type="text" name="allowance_amounts[]" class="form-control form-control-sm text-end raw-amount" placeholder="Nominal" value="${formatCurrency(amount)}" required onkeyup="formatRibuan(this); sumAllowances()">
            </div>
            <div class="col-1 text-center">
                <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1" onclick="document.getElementById('${rowId}').remove(); sumAllowances()"><i class="bi bi-trash"></i></button>
            </div>
        </div>
    `;
    
    list.insertAdjacentHTML('beforeend', html);
    sumAllowances();
}

function sumAllowances() {
    let total = 0;
    document.querySelectorAll('#allowance-list .raw-amount').forEach(el => {
        let val = parseFloat(el.value.replace(/\./g, '')) || 0;
        total += val;
    });
    document.getElementById('allowance').value = formatCurrency(total);
    calcNet();
}

// 3. Deductions (Potongan)
function addDeductionRow(name = '', amount = 0) {
    const list = document.getElementById('deduction-list');
    const rowId = 'deduction_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
    
    const html = `
        <div class="row g-2 mb-2 align-items-center deduction-row" id="${rowId}">
            <div class="col-7">
                <input type="text" name="deduction_names[]" class="form-control form-control-sm" placeholder="Nama Potongan" value="${name}" required>
            </div>
            <div class="col-4">
                <input type="text" name="deduction_amounts[]" class="form-control form-control-sm text-end raw-amount" placeholder="Nominal" value="${formatCurrency(amount)}" required onkeyup="formatRibuan(this); sumDeductions()">
            </div>
            <div class="col-1 text-center">
                <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1" onclick="document.getElementById('${rowId}').remove(); sumDeductions()"><i class="bi bi-trash"></i></button>
            </div>
        </div>
    `;
    
    list.insertAdjacentHTML('beforeend', html);
    sumDeductions();
}

function sumDeductions() {
    let total = 0;
    document.querySelectorAll('#deduction-list .raw-amount').forEach(el => {
        let val = parseFloat(el.value.replace(/\./g, '')) || 0;
        total += val;
    });
    document.getElementById('deduct').value = formatCurrency(total);
    calcNet();
}

// 4. Net Salary Calculation
function calcNet() {
    let basic = parseFloat(document.getElementById('basicSal').value.replace(/\./g, '')) || 0;
    let allow = parseFloat(document.getElementById('allowance').value.replace(/\./g, '')) || 0;
    let deduct = parseFloat(document.getElementById('deduct').value.replace(/\./g, '')) || 0;

    let net = (basic + allow) - deduct;
    document.getElementById('netSalary').innerText = "Rp " + formatCurrency(net);
}
</script>
@endsection
