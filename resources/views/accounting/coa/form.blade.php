@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Akun' : 'Tambah Akun Baru')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">Form Chart of Accounts</div>
            <div class="card-body">
                @include('partials.alerts')
                <form method="POST" action="{{ $isEdit ? route('accounting.coa.update', $account) : route('accounting.coa.store') }}">
                    @csrf
                    @if($isEdit) @method('PUT') @endif
                    <div class="mb-3"><label>Kode Akun <span class="text-danger">*</span></label><input type="text" name="account_code" class="form-control fw-bold" value="{{ old('account_code', $account->account_code) }}" required placeholder="Contoh: 1-1100"></div>
                    <div class="mb-3"><label>Nama Akun <span class="text-danger">*</span></label><input type="text" name="account_name" class="form-control" value="{{ old('account_name', $account->account_name) }}" required></div>
                    <div class="mb-3"><label>Tipe Akun</label><select name="account_type" class="form-select">@foreach(['asset'=>'ASSET (Harta)','liability'=>'LIABILITY (Kewajiban)','equity'=>'EQUITY (Modal)','revenue'=>'REVENUE (Pendapatan)','expense'=>'EXPENSE (Beban)'] as $value => $label)<option value="{{ $value }}" @selected(old('account_type', $account->account_type) === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="mb-3"><label>Saldo Normal</label><select name="normal_balance" class="form-select"><option value="debit" @selected(old('normal_balance', $account->normal_balance) === 'debit')>Debit</option><option value="credit" @selected(old('normal_balance', $account->normal_balance) === 'credit')>Credit</option></select></div>
                    <div class="mb-3"><label>Saldo Awal (Opening)</label><input type="number" name="opening_balance" class="form-control" value="{{ old('opening_balance', $account->opening_balance ?? 0) }}" step="0.01"><div class="form-text">Masukkan nilai saldo awal jika migrasi data lama.</div></div>
                    <div class="d-flex justify-content-between mt-4"><a href="{{ route('accounting.coa.index') }}" class="btn btn-secondary">Batal</a><button type="submit" class="btn btn-primary">Simpan Akun</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
