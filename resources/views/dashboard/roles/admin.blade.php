<h4 class="fw-bold text-dark mb-3 mt-4"><i class="bi bi-sliders me-2 text-primary"></i> Panel Kontrol Administrator</h4>
<div class="row g-4 mb-4">
    <!-- 1. Pengelolaan Pengguna & Hak Akses -->
    <div class="col-md-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0 border-top border-4 border-primary">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="background-color: rgba(11, 99, 246, 0.12); color: #0b63f6; width: 40px; height: 40px;">
                        <i class="bi bi-people-fill fs-5"></i>
                    </div>
                    <h5 class="card-title mb-0 fw-bold" style="font-size: 0.95rem;">Pengguna & Akses</h5>
                </div>
                <p class="card-text text-muted small mb-4">Kelola akun pengguna, peran jabatan, dan hak perizinan sistem.</p>
                <div class="list-group list-group-flush small">
                    <a href="{{ route('admin.users.index') }}" class="list-group-item list-group-item-action border-0 px-0 d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-chevron-right me-1 text-primary"></i> Kelola Users</span>
                        <span class="badge bg-light text-dark border">{{ $stats['users'] }}</span>
                    </a>
                    <a href="{{ route('admin.roles.index') }}" class="list-group-item list-group-item-action border-0 px-0 d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-chevron-right me-1 text-primary"></i> Peran & Jabatan (Roles)</span>
                        <span class="badge bg-light text-dark border">{{ $stats['roles'] }}</span>
                    </a>
                    <a href="{{ route('admin.roles.permissions') }}" class="list-group-item list-group-item-action border-0 px-0">
                        <i class="bi bi-chevron-right me-1 text-primary"></i> Hak Akses (Permissions)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Profil & Data Master -->
    <div class="col-md-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0 border-top border-4 border-success">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="background-color: rgba(43, 138, 62, 0.12); color: #2b8a3e; width: 40px; height: 40px;">
                        <i class="bi bi-building fs-5"></i>
                    </div>
                    <h5 class="card-title mb-0 fw-bold" style="font-size: 0.95rem;">Profil & Data Master</h5>
                </div>
                <p class="card-text text-muted small mb-4">Konfigurasi profil instansi, logo, tema UI, serta daftar mesin pabrik.</p>
                <div class="list-group list-group-flush small">
                    <a href="{{ route('admin.company.edit') }}" class="list-group-item list-group-item-action border-0 px-0">
                        <i class="bi bi-chevron-right me-1 text-success"></i> Profil Perusahaan
                    </a>
                    <a href="{{ route('admin.machines.index') }}" class="list-group-item list-group-item-action border-0 px-0">
                        <i class="bi bi-chevron-right me-1 text-success"></i> Kelola Mesin Produksi
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Kustomisasi & Setup -->
    <div class="col-md-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0 border-top border-4 border-warning">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="background-color: rgba(217, 119, 6, 0.12); color: #d97706; width: 40px; height: 40px;">
                        <i class="bi bi-gear-wide-connected fs-5"></i>
                    </div>
                    <h5 class="card-title mb-0 fw-bold" style="font-size: 0.95rem;">Kustomisasi & Setup</h5>
                </div>
                <p class="card-text text-muted small mb-4">Atur kustomisasi tata letak menu sidebar dan inisialisasi setup awal.</p>
                <div class="list-group list-group-flush small">
                    <a href="{{ route('admin.menu.index') }}" class="list-group-item list-group-item-action border-0 px-0">
                        <i class="bi bi-chevron-right me-1 text-warning"></i> Kustomisasi Menu
                    </a>
                    <a href="{{ route('admin.setup.index') }}" class="list-group-item list-group-item-action border-0 px-0">
                        <i class="bi bi-chevron-right me-1 text-warning"></i> Setup Wizard Awal
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Pemeliharaan & Utilitas -->
    <div class="col-md-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0 border-top border-4 border-danger">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="background-color: rgba(220, 38, 38, 0.12); color: #dc2626; width: 40px; height: 40px;">
                        <i class="bi bi-shield-fill-check fs-5"></i>
                    </div>
                    <h5 class="card-title mb-0 fw-bold" style="font-size: 0.95rem;">Pemeliharaan Sistem</h5>
                </div>
                <p class="card-text text-muted small mb-4">Perawatan database, backup data, log pengiriman WA, dan konsol utilitas.</p>
                <div class="list-group list-group-flush small">
                    <a href="{{ route('admin.backup.index') }}" class="list-group-item list-group-item-action border-0 px-0">
                        <i class="bi bi-chevron-right me-1 text-danger"></i> Database Backup
                    </a>
                    <a href="{{ route('admin.reset.index') }}" class="list-group-item list-group-item-action border-0 px-0">
                        <i class="bi bi-chevron-right me-1 text-danger"></i> Reset Transaksi
                    </a>
                    <a href="{{ route('admin.wa_logs.index') }}" class="list-group-item list-group-item-action border-0 px-0">
                        <i class="bi bi-chevron-right me-1 text-danger"></i> WhatsApp Logs
                    </a>
                    <a href="{{ route('admin.system.index') }}" class="list-group-item list-group-item-action border-0 px-0">
                        <i class="bi bi-chevron-right me-1 text-danger"></i> System Utility (Artisan)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
