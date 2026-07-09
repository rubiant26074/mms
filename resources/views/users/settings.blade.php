@extends('layouts.mms')

@section('title', 'User Setting')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">User Setting</h5>
            </div>
            <div class="card-body">
                @include('partials.alerts')

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" value="{{ $userData->fullname }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jabatan</label>
                        <input type="text" class="form-control" value="{{ $roleName }}" readonly>
                    </div>
                </div>

                <form method="POST" action="{{ route('user_settings.profile') }}" enctype="multipart/form-data" class="mb-4" id="profileForm">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Upload Tanda Tangan (PNG/JPG)</label>
                            <input type="hidden" name="signature_selected" id="signature_selected" value="0">
                            <input type="hidden" name="signature_base64" id="signature_base64" value="">
                            <input type="file" name="signature_file" id="signature_file" class="form-control js-profile-image" data-target="signature" data-max-kb="4096" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
                            @if($userData->signature_path)
                                <div class="mt-2">
                                    <img src="{{ asset($userData->signature_path) }}" alt="Signature" style="height:60px; max-width:200px; object-fit:contain;">
                                </div>
                            @endif
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Upload Avatar (PNG/JPG)</label>
                            <input type="hidden" name="avatar_selected" id="avatar_selected" value="0">
                            <input type="hidden" name="avatar_base64" id="avatar_base64" value="">
                            <input type="file" name="avatar_file" id="avatar_file" class="form-control js-profile-image" data-target="avatar" data-max-kb="4096" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
                            @if($userData->avatar_path)
                                <div class="mt-2">
                                    <img src="{{ asset($userData->avatar_path) }}" alt="Avatar" class="rounded-circle" style="height:60px; width:60px; object-fit:cover;">
                                </div>
                            @endif
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Registrasi Wajah Absensi (Selfie Kamera Depan)</label>
                            <input type="hidden" name="face_reference_selected" id="face_reference_selected" value="0">
                            <input type="hidden" name="face_reference_base64" id="face_reference_base64" value="">
                            <input type="file" name="face_reference_file" id="face_reference_file" class="form-control js-profile-image" data-target="face_reference" data-max-kb="8192" accept=".png,.jpg,.jpeg,image/png,image/jpeg" capture="user">
                            <div class="form-text">Gunakan kamera depan Android untuk mendaftarkan wajah referensi absensi.</div>
                            @if($userData->face_reference_path)
                                <div class="mt-2">
                                    <img src="{{ asset($userData->face_reference_path) }}" alt="Face Reference" style="height:110px; width:110px; object-fit:cover;" class="rounded border">
                                </div>
                            @endif
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan Profil</button>
                </form>

                <form method="POST" action="{{ route('user_settings.password') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Password Lama</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Password Baru</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Konfirmasi Password</label>
                            <input type="password" name="new_password_confirmation" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-dark">Ubah Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const pendingProfileReads = new Set();

    document.querySelectorAll('.js-profile-image').forEach((input) => {
        input.addEventListener('change', function () {
            const target = this.dataset.target;
            const selectedInput = document.getElementById(`${target}_selected`);
            const base64Input = document.getElementById(`${target}_base64`);
            const selected = this.files.length > 0;

            selectedInput.value = selected ? '1' : '0';
            base64Input.value = '';

            if (! selected) {
                return;
            }

            const file = this.files[0];
            const maxKb = Number(this.dataset.maxKb || 2048);
            if (! ['image/jpeg', 'image/png'].includes(file.type) || file.size > maxKb * 1024) {
                return;
            }

            pendingProfileReads.add(target);
            const reader = new FileReader();
            reader.onload = function () {
                base64Input.value = String(reader.result || '');
                pendingProfileReads.delete(target);
            };
            reader.onerror = function () {
                pendingProfileReads.delete(target);
            };
            reader.readAsDataURL(file);
        });
    });

    document.getElementById('profileForm')?.addEventListener('submit', function (event) {
        if (pendingProfileReads.size > 0) {
            event.preventDefault();
            setTimeout(() => this.requestSubmit(), 250);
        }
    });
</script>
@endpush
