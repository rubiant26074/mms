<h4 class="fw-bold text-dark mb-3 mt-4"><i class="bi bi-kanban me-2 text-primary"></i> Panel Operasional Produksi & Pabrik</h4>
<div class="row g-4 mb-4">
    <!-- KPI 1 -->
    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Tugas Produksi Sedang Berjalan (Running)</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['running_assignments'] ?? 0) }}</h3>
                <small class="text-muted"><i class="bi bi-play-circle-fill text-primary"></i> Pekerjaan mesin sedang berlangsung</small>
            </div>
        </div>
    </div>
    <!-- KPI 2 -->
    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Tugas Selesai Hari Ini</div>
                <h3 class="fw-bold text-dark">{{ number_format($roleStats['completed_today'] ?? 0) }}</h3>
                <small class="text-success"><i class="bi bi-check-circle-fill"></i> Target harian terselesaikan</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary"><i class="bi bi-lightning-charge"></i> Tindakan Cepat Operator & Supervisor</h6></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('production.tasks.index') }}" class="btn btn-outline-primary"><i class="bi bi-list-task me-1"></i> Penugasan Kerja (Task Assignment)</a>
            <a href="{{ route('production.operator.index') }}" class="btn btn-outline-primary"><i class="bi bi-phone me-1"></i> Panel Operator (Mulai/Selesai)</a>
            <a href="{{ route('production.reports.index') }}" class="btn btn-outline-primary"><i class="bi bi-file-bar-graph me-1"></i> Laporan Harian Produksi</a>
        </div>
    </div>
</div>
