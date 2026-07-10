@extends('layouts.mms')

@section('title', 'Utilitas Sistem (Artisan Console)')

@section('content')
@include('partials.alerts')

<div class="row g-4">
    <!-- Main Panel -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white d-flex align-items-center">
                <i class="bi bi-terminal me-2 fs-5"></i>
                <h5 class="mb-0 fw-bold">Utilitas Sistem & Command Artisan</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info border-0 shadow-sm mb-4">
                    <div class="d-flex">
                        <i class="bi bi-info-circle-fill fs-4 me-3 text-info"></i>
                        <div>
                            <h6 class="fw-bold mb-1">Informasi Lingkungan Hosting (cPanel)</h6>
                            <p class="mb-2 small text-secondary">
                                cPanel hosting sering kali tidak menyediakan Terminal SSH secara langsung. Halaman ini menyediakan antarmuka GUI untuk menjalankan perintah `php artisan` secara aman dari browser Anda.
                            </p>
                            <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i> Catatan:</span>
                            <small class="text-muted d-block mt-1">
                                Perintah <code>php artisan serve</code> hanya digunakan pada komputer lokal saat tahap pengembangan (*local development*). Di server cPanel (Produksi), web server Apache/LiteSpeed sudah otomatis melayani aplikasi Anda secara terus menerus, sehingga <code>serve</code> tidak diperlukan dan tidak boleh dijalankan.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle border-light">
                        <thead>
                            <tr class="table-light">
                                <th>Perintah Artisan</th>
                                <th>Deskripsi</th>
                                <th class="text-end" style="width: 150px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($commands as $key => $cmd)
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary font-monospace p-2" style="font-size: 0.85rem;">php artisan {{ $key }}</span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark" style="font-size: 0.9rem;">{{ $cmd['name'] }}</div>
                                        <small class="text-muted d-block">{{ $cmd['desc'] }}</small>
                                        @if($cmd['danger'])
                                            <small class="text-danger fw-bold"><i class="bi bi-exclamation-octagon"></i> Perintah ini berisiko menghapus/memutar balik data!</small>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <form action="{{ route('admin.system.run') }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menjalankan perintah: php artisan {{ $key }}?');">
                                            @csrf
                                            <input type="hidden" name="command" value="{{ $key }}">
                                            <button type="submit" class="btn btn-sm {{ $cmd['danger'] ? 'btn-danger' : 'btn-outline-primary' }} px-3">
                                                <i class="bi bi-play-fill me-1"></i> Jalankan
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Output Terminal Console -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100 d-flex flex-column">
            <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <span class="spinner-grow spinner-grow-sm text-success me-2" role="status" aria-hidden="true"></span>
                    <h5 class="mb-0 fw-bold font-monospace text-success" style="font-size: 0.95rem;">terminal-output.log</h5>
                </div>
                <div class="d-flex gap-1">
                    <span class="badge bg-danger rounded-circle p-1" style="width: 10px; height: 10px;"> </span>
                    <span class="badge bg-warning rounded-circle p-1" style="width: 10px; height: 10px;"> </span>
                    <span class="badge bg-success rounded-circle p-1" style="width: 10px; height: 10px;"> </span>
                </div>
            </div>
            <div class="card-body bg-black text-light p-0 flex-grow-1" style="min-height: 400px; display: flex; flex-direction: column;">
                @if(session('cmd_output') || $errors->has('cmd_output') || $errors->has('error'))
                    @php
                        $isSuccess = session()->has('success');
                        $output = session('cmd_output') ?: $errors->first('cmd_output') ?: $errors->first('error');
                        $lastCmd = session('last_cmd') ?: 'artisan';
                    @endphp
                    <div class="p-3 font-monospace flex-grow-1 overflow-auto" style="max-height: 550px; font-size: 0.85rem; line-height: 1.4; white-space: pre-wrap; word-break: break-all;">
<span class="text-secondary">[System @ MMS-Server]:~$</span> php artisan {{ $lastCmd }}
<span class="{{ $isSuccess ? 'text-success' : 'text-danger' }} fw-bold">--- Eksekusi Selesai ({{ $isSuccess ? 'SUKSES' : 'GAGAL' }}) ---</span>

{{ $output }}
                    </div>
                @else
                    <div class="p-4 text-center text-secondary my-auto font-monospace">
                        <i class="bi bi-terminal display-4 d-block mb-3 text-secondary" style="opacity: 0.4;"></i>
                        <span>Belum ada perintah yang dieksekusi.</span>
                        <small class="d-block mt-2" style="font-size: 0.75rem;">Silakan pilih salah satu perintah di sebelah kiri dan klik "Jalankan". Hasil log eksekusi akan tampil di sini secara real-time.</small>
                    </div>
                @endif
            </div>
            <div class="card-footer bg-dark border-0 p-2 text-center">
                <span class="text-secondary font-monospace" style="font-size: 0.75rem;">Manufacturing Management System Console &copy; 2026</span>
            </div>
        </div>
    </div>
</div>
@endsection
