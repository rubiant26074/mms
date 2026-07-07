@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Gaji' : 'Buat Slip Gaji')

@section('content')
@include('partials.alerts')
@php($startValue = $payroll->period_start instanceof \Illuminate\Support\Carbon ? $payroll->period_start->format('Y-m-d') : $payroll->period_start)
@php($endValue = $payroll->period_end instanceof \Illuminate\Support\Carbon ? $payroll->period_end->format('Y-m-d') : $payroll->period_end)

<form method="POST" action="{{ $isEdit ? route('hrd.payroll.update', $payroll) : route('hrd.payroll.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">Info Karyawan & Periode</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>No. Slip</label>
                        <input type="text" class="form-control fw-bold" value="{{ $payroll->payroll_code ?: 'AUTO' }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Pilih Karyawan <span class="text-danger">*</span></label>
                        <select name="user_id" id="empSelect" class="form-select" required onchange="getEmpInfo()">
                            <option value="">-- Pilih --</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" data-basic="{{ (float) $employee->basic_salary }}" @selected((string) old('user_id', $payroll->user_id) === (string) $employee->id)>
                                    {{ $employee->fullname }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label>Dari Tanggal</label>
                            <input type="date" name="period_start" id="pStart" class="form-control" value="{{ old('period_start', $startValue) }}" onchange="calcAttendance()">
                        </div>
                        <div class="col-6 mb-3">
                            <label>Sampai Tanggal</label>
                            <input type="date" name="period_end" id="pEnd" class="form-control" value="{{ old('period_end', $endValue) }}" onchange="calcAttendance()">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Jumlah Kehadiran (Hari)</label>
                        <div class="input-group">
                            <input type="number" name="total_attendance" id="totAttend" class="form-control" value="{{ old('total_attendance', (int) $payroll->total_attendance) }}" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="calcAttendance()">Hitung Ulang</button>
                        </div>
                        <small class="text-muted">Dihitung dari data Absensi (Hadir/Terlambat).</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-success text-white">Komponen Gaji</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>Gaji Pokok (Basic)</label>
                        <input type="text" name="basic_salary" id="basicSal" class="form-control text-end fw-bold" value="{{ old('basic_salary', number_format((float) $payroll->basic_salary, 0, ',', '.')) }}" required onkeyup="formatRibuan(this); calcNet()">
                    </div>
                    <div class="mb-3">
                        <label>Total Tunjangan (+)</label>
                        <input type="text" name="allowance_total" id="allowance" class="form-control text-end text-success" value="{{ old('allowance_total', number_format((float) $payroll->allowance_total, 0, ',', '.')) }}" onkeyup="formatRibuan(this); calcNet()">
                    </div>
                    <div class="mb-3">
                        <label>Total Potongan (-)</label>
                        <input type="text" name="deduction_total" id="deduct" class="form-control text-end text-danger" value="{{ old('deduction_total', number_format((float) $payroll->deduction_total, 0, ',', '.')) }}" onkeyup="formatRibuan(this); calcNet()">
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="fw-bold">Gaji Bersih (Take Home Pay)</label>
                        <h3 class="text-end text-primary" id="netSalary">Rp {{ number_format((float) $payroll->net_salary, 0, ',', '.') }}</h3>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label>Catatan</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes', $payroll->notes) }}</textarea>
            </div>

            <div class="d-flex justify-content-between">
                <a href="{{ route('hrd.payroll.index') }}" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary px-4 fw-bold">Simpan Gaji</button>
            </div>
        </div>
    </div>
</form>

<script>
function formatRibuan(input) {
    let val = input.value.replace(/[^0-9]/g, '');
    input.value = new Intl.NumberFormat('id-ID').format(val);
}

function getEmpInfo() {
    const sel = document.getElementById('empSelect');
    const opt = sel.options[sel.selectedIndex];
    const basic = opt ? (opt.getAttribute('data-basic') || 0) : 0;

    document.getElementById('basicSal').value = new Intl.NumberFormat('id-ID').format(basic);
    calcAttendance();
    calcNet();
}

function calcAttendance() {
    const uid = document.getElementById('empSelect').value;
    const start = document.getElementById('pStart').value;
    const end = document.getElementById('pEnd').value;

    if (uid && start && end) {
        fetch(`{{ route('hrd.payroll.attendance_count') }}?uid=${uid}&start=${start}&end=${end}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('totAttend').value = data.count || 0;
            });
    }
}

function calcNet() {
    let basic = parseFloat(document.getElementById('basicSal').value.replace(/\./g, '')) || 0;
    let allow = parseFloat(document.getElementById('allowance').value.replace(/\./g, '')) || 0;
    let deduct = parseFloat(document.getElementById('deduct').value.replace(/\./g, '')) || 0;

    let net = (basic + allow) - deduct;
    document.getElementById('netSalary').innerText = "Rp " + new Intl.NumberFormat('id-ID').format(net);
}
</script>
@endsection
