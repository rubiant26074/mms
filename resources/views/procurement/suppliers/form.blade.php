@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Supplier' : 'Tambah Supplier')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white"><h5 class="mb-0">{{ $isEdit ? 'Edit Supplier' : 'Tambah Supplier Baru' }}</h5></div>
            <div class="card-body">
                @include('partials.alerts')
                <form method="POST" action="{{ $isEdit ? route('procurement.suppliers.update', $supplier) : route('procurement.suppliers.store') }}">
                    @csrf
                    @if($isEdit) @method('PUT') @endif
                    <div class="row">
                        <div class="col-md-4 mb-3"><label>Kode Supplier <span class="text-danger">*</span></label><input type="text" name="code" class="form-control fw-bold" value="{{ old('code', $supplier->code) }}" required placeholder="SUP-00X"></div>
                        <div class="col-md-8 mb-3"><label>Nama Supplier <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ old('name', $supplier->name) }}" required></div>
                    </div>
                    <div class="mb-3"><label>Alamat</label><textarea name="address" class="form-control" rows="2">{{ old('address', $supplier->address) }}</textarea></div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label>Contact Person</label><input type="text" name="contact_person" class="form-control" value="{{ old('contact_person', $supplier->contact_person) }}"></div>
                        <div class="col-md-4 mb-3"><label>Telepon</label><input type="text" name="phone" class="form-control" value="{{ old('phone', $supplier->phone) }}"></div>
                        <div class="col-md-4 mb-3"><label>Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $supplier->email) }}"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label>Nama Bank</label><input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $supplier->bank_name) }}" placeholder="BCA / Mandiri"></div>
                        <div class="col-md-6 mb-3"><label>No. Rekening</label><input type="text" name="bank_number" class="form-control" value="{{ old('bank_number', $supplier->bank_number) }}"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-4"><a href="{{ route('procurement.suppliers.index') }}" class="btn btn-secondary">Batal</a><button class="btn btn-primary px-5">Simpan Data</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
