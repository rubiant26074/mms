@extends('layouts.mms')

@section('title', 'Kontrol Hak Akses')

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-8">
        <h3 class="fw-bold"><i class="bi bi-key-fill"></i> Kontrol Hak Akses (RBAC)</h3>
        <p class="text-muted">Atur fitur apa saja yang bisa diakses oleh setiap role/jabatan.</p>
    </div>
</div>
<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">Pilih Role</div>
            <div class="list-group list-group-flush">
                @foreach($roles as $role)
                    <a href="{{ route('admin.roles.permissions', $role) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $selectedRole?->id === $role->id ? 'active fw-bold' : '' }}">
                        {{ $role->role_name }} <i class="bi bi-chevron-right small"></i>
                    </a>
                @endforeach
            </div>
        </div>
        <div class="alert alert-info mt-3 small"><i class="bi bi-info-circle"></i> Role <b>Administrator</b> memiliki akses penuh otomatis.</div>
    </div>
    <div class="col-md-9">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary fw-bold">Setting Izin Akses</h5>
                @if($selectedRole)<span class="badge bg-primary">Role ID: {{ $selectedRole->id }}</span>@endif
            </div>
            <div class="card-body">
                @if($selectedRole)
                    <div class="mb-3 position-relative">
                        <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="permissionSearch" class="form-control ps-5" placeholder="Ketik untuk mencari izin...">
                    </div>
                    <form method="POST" action="{{ route('admin.roles.permissions.update', $selectedRole) }}">
                        @csrf @method('PUT')
                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-hover align-middle table-sm" id="permTable">
                                <thead class="table-light sticky-top"><tr><th width="50" class="text-center"><input type="checkbox" id="checkAll"></th><th>Nama Izin</th><th>Kode Slug</th></tr></thead>
                                <tbody>
                                    @foreach($permissions as $permission)
                                        <tr class="perm-row">
                                            <td class="text-center"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" class="form-check-input perm-check" @checked(in_array($permission->id, $currentPermissions))></td>
                                            <td><span class="fw-bold perm-name">{{ $permission->permission_name }}</span></td>
                                            <td><code class="perm-slug">{{ $permission->permission_slug }}</code></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div id="noResults" class="text-center py-4 text-muted d-none"><i class="bi bi-emoji-frown display-6"></i><p class="mt-2">Tidak ada izin yang cocok.</p></div>
                        </div>
                        <div class="mt-3 border-top pt-3 text-end sticky-bottom bg-white">
                            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save"></i> Simpan Perubahan</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.perm-check').forEach((cb) => { if(cb.closest('tr').style.display !== 'none') cb.checked = this.checked; });
});
document.getElementById('permissionSearch')?.addEventListener('keyup', function() {
    const value = this.value.toLowerCase();
    let visibleCount = 0;
    document.querySelectorAll('.perm-row').forEach((row) => {
        const ok = row.querySelector('.perm-name').textContent.toLowerCase().includes(value) || row.querySelector('.perm-slug').textContent.toLowerCase().includes(value);
        row.style.display = ok ? '' : 'none';
        if (ok) visibleCount++;
    });
    document.getElementById('noResults').classList.toggle('d-none', visibleCount !== 0);
});
</script>
@endpush
@endsection
