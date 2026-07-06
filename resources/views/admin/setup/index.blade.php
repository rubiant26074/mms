@extends('layouts.mms')

@section('title', 'Wizard Setup ERP')

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-8">
        <h3 class="fw-bold"><i class="bi bi-magic"></i> Wizard Setup ERP</h3>
        <p class="text-muted">Setup awal company profile, fiscal setting, dan konfigurasi pajak.</p>
    </div>
</div>
<div class="card shadow-sm">
    <div class="card-header bg-dark text-white">Langkah {{ $step }} dari 3</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.setup.save') }}">
            @csrf
            <input type="hidden" name="current_step" value="{{ $step }}">
            @if($step === 1)
                <div class="row">
                    <div class="col-md-6 mb-3"><label>Nama Perusahaan</label><input name="company_name" class="form-control" value="{{ old('company_name', $companyData->company_name) }}" required></div>
                    <div class="col-md-6 mb-3"><label>NPWP</label><input name="npwp" class="form-control" value="{{ old('npwp', $companyData->npwp) }}"></div>
                    <div class="col-md-4 mb-3"><label>Telepon</label><input name="phone" class="form-control" value="{{ old('phone', $companyData->phone) }}"></div>
                    <div class="col-md-4 mb-3"><label>Email</label><input name="email" type="email" class="form-control" value="{{ old('email', $companyData->email) }}"></div>
                    <div class="col-md-4 mb-3"><label>Website</label><input name="website" class="form-control" value="{{ old('website', $companyData->website) }}"></div>
                    <div class="col-md-4 mb-3"><label>Tanggal PKP</label><input name="pkp_date" type="date" class="form-control" value="{{ old('pkp_date', optional($companyData->pkp_date)->format('Y-m-d')) }}"></div>
                    <div class="col-md-12 mb-3"><label>Alamat</label><textarea name="address" class="form-control">{{ old('address', $companyData->address) }}</textarea></div>
                </div>
            @elseif($step === 2)
                <div class="row">
                    <div class="col-md-4 mb-3"><label>Bulan Awal Fiscal</label><input name="fiscal_year_start_month" type="number" min="1" max="12" class="form-control" value="{{ old('fiscal_year_start_month', $settings['fiscal_year_start_month'] ?? 1) }}"></div>
                    <div class="col-md-4 mb-3"><label>Currency</label><input name="base_currency" class="form-control" value="{{ old('base_currency', $settings['base_currency'] ?? 'IDR') }}"></div>
                    <div class="col-md-4 mb-3"><label>Lock Backdate Days</label><input name="lock_backdate_days" type="number" min="0" class="form-control" value="{{ old('lock_backdate_days', $settings['lock_backdate_days'] ?? 0) }}"></div>
                    <div class="col-md-6 mb-3"><label>Opening Date</label><input name="opening_date" type="date" class="form-control" value="{{ old('opening_date', $settings['opening_date'] ?? date('Y-m-d')) }}"></div>
                    <div class="col-md-6 mb-3"><label>Opening Capital</label><input name="opening_capital_amount" class="form-control" value="{{ old('opening_capital_amount', $settings['opening_capital_amount'] ?? 0) }}"></div>
                </div>
            @else
                <div class="row">
                    <div class="col-md-3 mb-3"><label>PPN %</label><input name="tax_ppn_rate" class="form-control" value="{{ old('tax_ppn_rate', $settings['tax_ppn_rate'] ?? 11) }}"></div>
                    <div class="col-md-3 mb-3"><label>PPH23 %</label><input name="tax_pph23_rate" class="form-control" value="{{ old('tax_pph23_rate', $settings['tax_pph23_rate'] ?? 2) }}"></div>
                    <div class="col-md-3 mb-3"><label>PPH21 %</label><input name="tax_pph21_rate" class="form-control" value="{{ old('tax_pph21_rate', $settings['tax_pph21_rate'] ?? 5) }}"></div>
                    <div class="col-md-3 mb-3"><label>PPH Final %</label><input name="tax_pph_final_rate" class="form-control" value="{{ old('tax_pph_final_rate', $settings['tax_pph_final_rate'] ?? 0.5) }}"></div>
                    <div class="col-md-4 mb-3"><label>Tax Invoice Prefix</label><input name="tax_invoice_prefix" class="form-control" value="{{ old('tax_invoice_prefix', $settings['tax_invoice_prefix'] ?? 'MMS') }}"></div>
                </div>
            @endif
            <div class="d-flex justify-content-between">
                <a class="btn btn-light border {{ $step <= 1 ? 'disabled' : '' }}" href="{{ route('admin.setup.index', ['step' => max(1, $step - 1)]) }}">Kembali</a>
                <button class="btn btn-primary">{{ $step >= 3 ? 'Simpan & Selesai' : 'Simpan & Lanjut' }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
