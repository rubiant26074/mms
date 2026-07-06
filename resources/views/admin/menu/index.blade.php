@extends('layouts.mms')

@section('title', 'Custom Menu Manager')

@section('content')
@include('partials.alerts')
<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white fw-bold">1. Mode Tampilan Menu</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.menu.mode') }}">
                    @csrf
                    <label class="form-label">Pilih Mode:</label>
                    <select name="menu_mode" class="form-select bg-light fw-bold mb-3">
                        <option value="role" @selected($mode==='role')>Original (Sesuai Role)</option>
                        <option value="custom" @selected($mode==='custom')>Custom (Manual Per Menu)</option>
                    </select>
                    <button class="btn btn-primary w-100">Simpan Mode</button>
                </form>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-header bg-light fw-bold">2. Pilih User</div>
            <div class="list-group list-group-flush" style="max-height:420px;overflow-y:auto">
                @foreach($users as $userRow)
                    <a href="{{ route('admin.menu.index', ['edit_user' => $userRow->id]) }}" class="list-group-item list-group-item-action {{ $editUser?->id === $userRow->id ? 'active' : '' }}">
                        <div class="d-flex justify-content-between"><strong>{{ $userRow->fullname }}</strong><span class="badge bg-secondary rounded-pill">{{ $userRow->role?->role_name }}</span></div>
                        <small class="text-muted">@{{ $userRow->username }}</small>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-md-8">
        @if($editUser)
            <div class="card shadow-sm border-primary">
                <div class="card-header bg-primary text-white fw-bold"><i class="bi bi-grid-fill me-2"></i> Akses Menu: {{ $editUser->fullname }}</div>
                <div class="card-body">
                    @if($mode !== 'custom')
                        <div class="alert alert-warning">Mode saat ini masih <strong>Original</strong>. Checklist aktif setelah mode diubah ke <strong>Custom</strong>.</div>
                    @endif
                    <form method="POST" action="{{ route('admin.menu.users.save', $editUser) }}">
                        @csrf
                        <div class="row">
                            @foreach($menuTree as $node)
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3 bg-light h-100">
                                        @if($node['type'] === 'single')
                                            <label class="form-check fw-bold"><input class="form-check-input" type="checkbox" name="menus[]" value="{{ $node['slug'] }}" @checked(in_array($node['slug'], $access))> {{ $node['label'] }}</label>
                                        @else
                                            <div class="fw-bold mb-2">{{ $node['label'] }}</div>
                                            @foreach($node['children'] as $child)
                                                <label class="form-check small"><input class="form-check-input" type="checkbox" name="menus[]" value="{{ $child['slug'] }}" @checked(in_array($child['slug'], $access))> {{ $child['label'] }}</label>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="text-end"><button class="btn btn-primary px-4"><i class="bi bi-save"></i> Simpan Akses Menu</button></div>
                    </form>
                </div>
            </div>
        @else
            <div class="alert alert-info">Pilih user di sebelah kiri untuk mengatur akses menu.</div>
        @endif
    </div>
</div>
@endsection
