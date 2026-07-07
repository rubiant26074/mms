@extends('layouts.mms')

@section('title', 'Taxation (Perpajakan)')

@section('content')
@include('partials.alerts')
<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-receipt"></i> Rekapitulasi PPN</h3>
        <p class="text-muted">Masa Pajak: {{ $periodLabel }}</p>
    </div>
    <div class="col-md-6 text-end">
        <form method="GET" action="{{ route('finance.tax.index') }}" class="d-flex justify-content-end gap-2">
            <select name="month" class="form-select w-auto">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($m === $month)>{{ \Illuminate\Support\Carbon::create(null, $m, 1)->format('F') }}</option>
                @endfor
            </select>
            <select name="year" class="form-select w-auto">
                @for($y = now()->year - 1; $y <= now()->year + 1; $y++)
                    <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                @endfor
            </select>
            <button type="submit" class="btn btn-primary">Lihat</button>
            <button type="submit" formaction="{{ route('finance.tax.print') }}" formtarget="_blank" class="btn btn-outline-dark"><i class="bi bi-printer"></i> Print</button>
        </form>
    </div>
</div>

@unless($taxTableExists)
    <div class="alert alert-warning">Tabel <code>tax_payments</code> belum tersedia. Jalankan migration: <code>database/migrations/20260211_02_tax_payments.sql</code></div>
@endunless

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-start border-4 border-info">
            <div class="card-header bg-white fw-bold text-info">PPN KELUARAN (Penjualan)</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span>DPP</span><strong>Rp {{ number_format($dpp_out, 0, ',', '.') }}</strong></div>
                <div class="d-flex justify-content-between"><span>Total PPN Dipungut</span><h4 class="text-info fw-bold">Rp {{ number_format($ppn_out, 0, ',', '.') }}</h4></div>
                <hr><small class="text-muted">Sumber: Invoice Customer</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-start border-4 border-success">
            <div class="card-header bg-white fw-bold text-success">PPN MASUKAN (Pembelian)</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span>DPP</span><strong>Rp {{ number_format($dpp_in, 0, ',', '.') }}</strong></div>
                <div class="d-flex justify-content-between"><span>Total PPN Dibayar</span><h4 class="text-success fw-bold">Rp {{ number_format($ppn_in, 0, ',', '.') }}</h4></div>
                <hr><small class="text-muted">Sumber: Tagihan Supplier</small>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3"><div class="card shadow-sm h-100"><div class="card-body"><small class="text-muted text-uppercase">Status PPN</small><h5 class="fw-bold mt-2 {{ $statusColor }}">{{ $statusTax }}</h5></div></div></div>
    <div class="col-md-4 mb-3"><div class="card shadow-sm h-100"><div class="card-body"><small class="text-muted text-uppercase">Kewajiban Setor PPN</small><h4 class="fw-bold mt-2 text-danger">Rp {{ number_format($tax_due, 0, ',', '.') }}</h4></div></div></div>
    <div class="col-md-4 mb-3"><div class="card shadow-sm h-100"><div class="card-body"><small class="text-muted text-uppercase">Sudah Disetor / Sisa</small><div class="mt-2"><div class="fw-bold text-success">Rp {{ number_format($taxPaid, 0, ',', '.') }}</div><div class="fw-bold text-danger">Sisa: Rp {{ number_format($taxRemaining, 0, ',', '.') }}</div></div></div></div></div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-light fw-bold">Input Pembayaran Pajak (Setor PPN)</div>
    <div class="card-body">
        <form method="POST" action="{{ route('finance.tax.payment') }}" class="row g-3">
            @csrf
            <input type="hidden" name="period_month" value="{{ $month }}">
            <input type="hidden" name="period_year" value="{{ $year }}">
            <div class="col-md-3"><label class="form-label">Tanggal Setor</label><input type="date" name="payment_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required></div>
            <div class="col-md-3"><label class="form-label">Metode</label><select name="method" class="form-select" required><option value="Transfer Bank">Transfer Bank</option><option value="Cash">Cash</option><option value="e-Billing Pajak">e-Billing Pajak</option></select></div>
            <div class="col-md-3"><label class="form-label">Nominal Setor (Rp)</label><input type="text" name="amount" class="form-control" value="{{ number_format($taxRemaining, 0, ',', '.') }}" onkeyup="formatRibuan(this)" required></div>
            <div class="col-md-3"><label class="form-label">No. Referensi</label><input type="text" name="reference_no" class="form-control" placeholder="NTPN / e-Billing"></div>
            <div class="col-12"><label class="form-label">Catatan</label><textarea name="notes" rows="2" class="form-control" placeholder="Catatan pembayaran pajak"></textarea></div>
            <div class="col-12 d-flex justify-content-between"><small class="text-muted align-self-center">Sisa setor masa ini: Rp {{ number_format($taxRemaining, 0, ',', '.') }}</small><button type="submit" class="btn btn-danger" @disabled($taxRemaining <= 0 || ! $taxTableExists)>Simpan Setor Pajak</button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-light fw-bold">Riwayat Pembayaran Pajak Masa {{ strtoupper($periodLabel) }}</div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-striped mb-0">
            <thead class="table-light"><tr><th>Tanggal</th><th>Metode</th><th>Referensi</th><th>No. Jurnal</th><th class="text-end">Jumlah</th></tr></thead>
            <tbody>
            @forelse($paymentHistory as $p)
                <tr><td>{{ \Illuminate\Support\Carbon::parse($p->payment_date)->format('d/m/Y') }}</td><td>{{ $p->method }}</td><td>{{ $p->reference_no ?: '-' }}</td><td>{{ $p->journal_no ?: '-' }}</td><td class="text-end fw-bold">Rp {{ number_format($p->amount, 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada pembayaran pajak untuk masa ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light fw-bold">Monitoring Nomor Faktur Pajak (Invoice)</div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>No. Invoice</th><th>Tanggal</th><th>Jatuh Tempo</th><th>No. Seri Faktur Pajak</th><th class="text-center">Status</th><th class="text-center">Aksi</th></tr></thead>
            <tbody>
            @forelse($invoices as $invoice)
                @php $hasNsfp = filled($invoice->tax_invoice_number); @endphp
                <tr>
                    <td><strong>{{ $invoice->invoice_number }}</strong></td>
                    <td>{{ $invoice->invoice_date ? \Illuminate\Support\Carbon::parse($invoice->invoice_date)->format('d/m/Y') : '-' }}</td>
                    <td>{{ $invoice->due_date ? \Illuminate\Support\Carbon::parse($invoice->due_date)->format('d/m/Y') : '-' }}</td>
                    <td>{!! $hasNsfp ? e($invoice->tax_invoice_number) : '<span class="text-danger">Belum diisi</span>' !!}</td>
                    <td class="text-center"><span class="badge {{ $hasNsfp ? 'bg-success' : 'bg-danger' }}">{{ $hasNsfp ? 'Lengkap' : 'Belum' }}</span></td>
                    <td class="text-center"><a href="{{ route('finance.ar.edit', $invoice->id) }}" class="btn btn-sm btn-outline-primary">Edit Invoice</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada invoice pada periode ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
function formatRibuan(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    input.value = new Intl.NumberFormat('id-ID').format(value);
}
</script>
@endpush
