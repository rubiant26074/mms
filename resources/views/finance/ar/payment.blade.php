@extends('layouts.mms')

@section('title', 'Input Pembayaran AR')

@section('content')
@php($remaining = $invoice->grand_total - $invoice->paid_amount)
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white"><h5 class="mb-0">Informasi Invoice</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><td width="120" class="text-muted">No. Invoice</td><td class="fw-bold">{{ $invoice->invoice_number }}</td></tr>
                            <tr><td class="text-muted">Customer</td><td>{{ $invoice->customer?->name }}</td></tr>
                            <tr><td class="text-muted">Tgl Invoice</td><td>{{ optional($invoice->invoice_date)->format('d/m/Y') }}</td></tr>
                            <tr><td class="text-muted">Status SJ</td><td><span class="badge {{ in_array($invoice->deliveryNote?->status, ['approved','sent'], true) ? 'bg-success' : 'bg-warning text-dark' }}">{{ in_array($invoice->deliveryNote?->status, ['approved','sent'], true) ? 'Terkirim' : 'Belum Kirim' }}</span></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6 text-end">
                        <small class="text-muted">Total Tagihan</small><h4 class="fw-bold">Rp {{ number_format($invoice->grand_total, 0, ',', '.') }}</h4>
                        <small class="text-muted">Sudah Dibayar</small><h5 class="text-success">Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}</h5>
                        <hr><small class="text-muted">Sisa Tagihan</small><h3 class="text-danger fw-bold">Rp {{ number_format($remaining, 0, ',', '.') }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header bg-success text-white"><h6 class="mb-0 fw-bold"><i class="bi bi-wallet2"></i> Form Penerimaan Dana</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('finance.ar.payment.store', $invoice) }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 mb-3"><label>Tanggal Bayar</label><input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}" required></div>
                        <div class="col-md-4 mb-3"><label>Metode</label><select name="method" class="form-select" required><option>Transfer Bank</option><option>Cash</option><option>Cek / Giro</option></select></div>
                        <div class="col-md-4 mb-3"><label>Jumlah Bayar</label><input type="text" name="amount" class="form-control text-end fw-bold" value="{{ old('amount', number_format($remaining, 0, ',', '.')) }}" required></div>
                    </div>
                    <div class="mb-3"><label>Catatan</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
                    <div class="text-end"><a href="{{ route('finance.ar.index') }}" class="btn btn-secondary me-2">Batal</a><button class="btn btn-success px-4 fw-bold">Simpan Pembayaran</button></div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light fw-bold">Riwayat Pembayaran</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Tanggal</th><th>Metode</th><th class="text-end">Jumlah</th><th>Catatan</th><th>Recorded</th></tr></thead>
                    <tbody>@forelse($invoice->payments as $payment)<tr><td>{{ optional($payment->payment_date)->format('d/m/Y') }}</td><td>{{ $payment->method }}</td><td class="text-end">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td><td>{{ $payment->notes ?: '-' }}</td><td>{{ $payment->recorder?->fullname ?: '-' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-3">Belum ada pembayaran.</td></tr>@endforelse</tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
