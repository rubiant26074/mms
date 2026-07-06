@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Mesin' : 'Tambah Mesin')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white"><h5 class="mb-0">Form Data Mesin</h5></div>
            <div class="card-body">
                @include('partials.alerts')
                <form method="POST" action="{{ $isEdit ? route('admin.machines.update', $machine) : route('admin.machines.store') }}">
                    @csrf
                    @if($isEdit) @method('PUT') @endif
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Kode Mesin <span class="text-danger">*</span></label>
                            <input type="text" name="machine_code" class="form-control fw-bold" value="{{ old('machine_code', $machine->machine_code) }}" required placeholder="MC-XXX-01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Nama Mesin <span class="text-danger">*</span></label>
                            <input type="text" name="machine_name" class="form-control" value="{{ old('machine_name', $machine->machine_name) }}" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Tipe Proses <span class="text-danger">*</span></label>
                            <select name="process_type" class="form-select" required>
                                <option value="">-- Pilih Proses --</option>
                                @foreach(['Fibre Laser','CO Laser','Metal Bending','Acrylic Bending','Welding','Assembling','Powder Coating','Machining','Other'] as $process)
                                    <option value="{{ $process }}" @selected(old('process_type', $machine->process_type) === $process)>{{ $process === 'Other' ? 'Lainnya' : $process }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Status Operasional</label>
                            <select name="status" class="form-select fw-bold">
                                <option value="active" @selected(old('status', $machine->status) === 'active')>Active (Siap Jalan)</option>
                                <option value="maintenance" @selected(old('status', $machine->status) === 'maintenance')>Maintenance (Perbaikan)</option>
                                <option value="broken" @selected(old('status', $machine->status) === 'broken')>Broken (Rusak)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Lokasi / Line</label>
                        <input type="text" name="location" class="form-control" value="{{ old('location', $machine->location) }}" placeholder="Contoh: Line A, Gedung 2">
                    </div>
                    <div class="mb-3">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $machine->notes) }}</textarea>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('admin.machines.index') }}" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
