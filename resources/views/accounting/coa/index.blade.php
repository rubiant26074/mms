@extends('layouts.mms')

@section('title', 'Chart of Accounts (COA)')

@php
    $badges = [
        'asset' => 'bg-primary',
        'liability' => 'bg-warning text-dark',
        'equity' => 'bg-info text-dark',
        'revenue' => 'bg-success',
        'expense' => 'bg-danger',
    ];
@endphp

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-list-columns-reverse"></i> Chart of Accounts</h3>
        <p class="text-muted">Daftar Akun Perkiraan untuk Jurnal Akuntansi.</p>
    </div>
    <div class="col-md-6 text-end">
        <form method="POST" action="{{ route('accounting.coa.reconcile') }}" class="d-inline" onsubmit="return confirm('Rekonsiliasi semua saldo COA dari mutasi jurnal?')">
            @csrf
            <button type="submit" class="btn btn-outline-dark me-2"><i class="bi bi-arrow-repeat"></i> Rekonsiliasi Saldo</button>
        </form>
        <a href="{{ route('accounting.coa.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah Akun</a>
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="coa-filter-form">
            <div class="col-md-4"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="text" name="search" class="form-control" placeholder="Cari Kode / Nama Akun..." value="{{ $search }}" autocomplete="off"></div></div>
            <div class="col-md-3"><select name="type" class="form-select"><option value="">- Semua Kategori -</option>@foreach(['asset','liability','equity','revenue','expense'] as $opt)<option value="{{ $opt }}" @selected($type === $opt)>{{ ucfirst($opt) }}</option>@endforeach</select></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('accounting.coa.index') }}" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light"><tr><th>Kode Akun</th><th>Nama Akun</th><th>Kategori</th><th>Saldo Normal</th><th class="text-end">Saldo Saat Ini</th><th class="text-center">Aksi</th></tr></thead>
            <tbody>
            @forelse($accounts as $account)
                <tr>
                    <td><strong>{{ $account->account_code }}</strong></td>
                    <td>{{ $account->account_name }}</td>
                    <td><span class="badge {{ $badges[$account->account_type] ?? 'bg-light' }}">{{ strtoupper($account->account_type) }}</span></td>
                    <td>{{ strtoupper($account->normal_balance) }}</td>
                    <td class="text-end fw-bold">Rp {{ number_format((float) $account->current_balance, 0, ',', '.') }}</td>
                    <td class="text-center">
                        <a href="{{ route('accounting.coa.edit', $account) }}" class="btn btn-sm btn-warning text-white"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('accounting.coa.destroy', $account) }}" class="d-inline" onsubmit="return confirm('Hapus akun ini?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada data akun.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('coa-filter-form');
    if (!form) return;
    const search = form.querySelector('input[name="search"]');
    const type = form.querySelector('select[name="type"]');
    let t;
    const submit = () => form.requestSubmit ? form.requestSubmit() : form.submit();
    search?.addEventListener('input', () => { clearTimeout(t); t = setTimeout(submit, 400); });
    type?.addEventListener('change', submit);
})();
</script>
@endpush
