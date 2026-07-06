@extends('layouts.mms')

@section('title', 'Backup Database')

@section('content')
@include('partials.alerts')
<div class="row justify-content-center g-4">
    <div class="col-lg-5">
        <div class="card shadow text-center border-primary">
            <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="bi bi-database-down"></i> Backup Data Sistem</h5></div>
            <div class="card-body py-5">
                <i class="bi bi-cloud-download display-1 text-primary mb-3"></i>
                <h4 class="card-title">Download Database (.sql)</h4>
                <p class="card-text text-muted">Download seluruh struktur dan data database aplikasi MMS.</p>
                <form action="{{ route('admin.backup.download') }}" method="POST">@csrf<button class="btn btn-primary btn-lg px-5 fw-bold shadow"><i class="bi bi-download me-2"></i> DOWNLOAD BACKUP SEKARANG</button></form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow border-danger">
            <div class="card-header bg-danger text-white"><h5 class="mb-0"><i class="bi bi-database-up"></i> Restore Database</h5></div>
            <div class="card-body">
                <div class="alert alert-warning small">Restore penuh belum diaktifkan di Laravel native untuk mencegah overwrite database aktif tanpa audit tambahan.</div>
                <form action="{{ route('admin.backup.restore') }}" method="POST" enctype="multipart/form-data">@csrf<input type="file" name="restore_file" class="form-control mb-3" disabled><button class="btn btn-danger w-100" disabled>RESTORE DATABASE</button></form>
            </div>
        </div>
    </div>
</div>
@endsection
