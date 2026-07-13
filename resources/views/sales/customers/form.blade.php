@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Customer' : 'Tambah Customer')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="bi bi-person-vcard"></i> Form Data Customer</h5></div>
            <div class="card-body">
                @include('partials.alerts')
                <form method="POST" action="{{ $isEdit ? route('sales.customers.update', $customer) : route('sales.customers.store') }}">
                    @csrf
                    @if($isEdit) @method('PUT') @endif
                    <div class="row">
                        <div class="col-md-6 border-end">
                            <h6 class="text-primary mb-3">Identitas Perusahaan</h6>
                            <div class="mb-3">
                                <label class="fw-bold">Kode Customer <span class="text-danger">*</span></label>
                                <input type="text" name="customer_code" class="form-control bg-light fw-bold text-primary" value="{{ old('customer_code', $customer->customer_code) }}" readonly>
                                <div class="form-text small">Auto Generate (CT-XXX).</div>
                            </div>
                            <div class="mb-3"><label>Nama Perusahaan <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ old('name', $customer->name) }}" required></div>
                            <div class="mb-3"><label>NPWP (Tax ID)</label><input type="text" name="tax_id" class="form-control" value="{{ old('tax_id', $customer->tax_id) }}"></div>
                            <div class="mb-3"><label>No. Seri Faktur Pajak (Default)</label><input type="text" name="tax_invoice_number" class="form-control" value="{{ old('tax_invoice_number', $customer->tax_invoice_number) }}" placeholder="010.000-24.00000001"><div class="form-text small">Format: 000.000-YY.12345678</div></div>
                            <div class="mb-3"><label>Alamat Lengkap</label><textarea name="address" class="form-control" rows="3">{{ old('address', $customer->address) }}</textarea></div>
                        </div>
                        <div class="col-md-6 ps-4">
                            <h6 class="text-primary mb-3">Kontak Person</h6>
                            <div class="mb-3"><label>PIC (Contact Person)</label><input type="text" name="pic" class="form-control" value="{{ old('pic', $customer->pic) }}"></div>
                            <div class="mb-3"><label>No. Telepon / HP</label><input type="text" name="phone" class="form-control" value="{{ old('phone', $customer->phone) }}"></div>
                            <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $customer->email) }}"></div>
                            <div class="mb-3">
                                <label>Nama Sales</label>
                                <input type="text" name="sales_name" class="form-control" value="{{ old('sales_name', $customer->sales_name ?: ($customer->creator?->fullname ?: $customer->creator?->username)) }}" placeholder="Nama Sales">
                                <div class="form-text small">Kosongkan jika ingin menggunakan nama Anda secara otomatis.</div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mt-3">
                        <a href="{{ route('sales.customers.index') }}" class="btn btn-secondary px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-5 fw-bold"><i class="bi bi-save"></i> Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
