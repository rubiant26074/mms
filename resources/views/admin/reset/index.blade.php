@extends('layouts.mms')

@section('title', 'System Reset')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow border-danger">
            <div class="card-header bg-danger text-white"><h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill"></i> Factory Reset / Pembersihan Data</h5></div>
            <div class="card-body">
                @include('partials.alerts')
                <p class="text-dark">Fitur ini digunakan untuk <strong>menghapus seluruh data transaksi</strong> untuk memulai sistem dari nol.</p>
                <div class="alert alert-warning"><strong>Data transaksi akan dihapus permanen.</strong><ul class="mb-0 small"><li>Quotation, SO, SPK, PO</li><li>Warehouse, QC, Invoice, Jurnal</li><li>Absensi, Payroll, System Logs</li></ul></div>
                <div class="alert alert-info"><strong>Data master tetap aman:</strong> User, Role, Company, Customer, Supplier, Item, Mesin, BOM, COA.</div>
                <form method="POST" action="{{ route('admin.reset.store') }}" onsubmit="return confirm('Lanjutkan reset database transaksi sekarang?')">
                    @csrf
                    <div class="form-check mb-3 p-3 border rounded bg-light">
                        <input class="form-check-input" type="checkbox" name="reset_items" id="resetItems" value="1">
                        <label class="form-check-label fw-bold" for="resetItems">Nol-kan Stok Barang & Saldo Akun?</label>
                    </div>
                    <div class="mb-3"><label class="form-label fw-bold">Konfirmasi Password Admin:</label><input type="password" name="admin_password" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label fw-bold">Ketik RESET:</label><input type="text" name="confirm_text" class="form-control" required></div>
                    <div class="d-grid"><button class="btn btn-danger btn-lg fw-bold"><i class="bi bi-trash3-fill"></i> RESET DATABASE SEKARANG</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
