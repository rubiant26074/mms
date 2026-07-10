<h4 class="fw-bold text-dark mb-3 mt-4"><i class="bi bi-people-fill me-2 text-primary"></i> Panel Sumber Daya Manusia (HRD)</h4>
<div class="row g-4 mb-4">
    <!-- KPI 1 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Total Karyawan Aktif</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['active_employees'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-person-fill-check"></i> Anggota tim terdaftar aktif</small>
            </div>
        </div>
    </div>
    <!-- KPI 2 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Absensi Masuk Hari Ini</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['attendance_today'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-clock-history"></i> Karyawan telah melakukan check-in</small>
            </div>
        </div>
    </div>
    <!-- KPI 3 -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Payroll Status Draft</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['pending_payroll'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-cash-stack"></i> Penggajian siap diperiksa</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary"><i class="bi bi-lightning-charge"></i> Tindakan Cepat HRD</h6></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('hrd.employees.index') }}" class="btn btn-outline-primary"><i class="bi bi-person-vcard me-1"></i> Database Karyawan</a>
            <a href="{{ route('hrd.attendance.index') }}" class="btn btn-outline-primary"><i class="bi bi-calendar2-check me-1"></i> Kelola Absensi</a>
            <a href="{{ route('hrd.payroll.index') }}" class="btn btn-outline-primary"><i class="bi bi-cash-coin me-1"></i> Proses Payroll / Gaji</a>
        </div>
    </div>
</div>
