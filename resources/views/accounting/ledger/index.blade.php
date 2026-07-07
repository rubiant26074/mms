@extends('layouts.mms')

@section('title', 'Buku Besar (General Ledger)')

@section('content')
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end" id="ledger-filter-form">
            <div class="col-md-4">
                <label class="form-label fw-bold">Pilih Akun</label>
                <select name="coa_id" class="form-select" required>
                    <option value="">-- Pilih Akun --</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" @selected($coaId === $acc->id)>{{ $acc->account_code }} - {{ $acc->account_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Dari Tanggal</label><input type="date" name="start_date" class="form-control" value="{{ $startDate }}"></div>
            <div class="col-md-2"><label class="form-label">Sampai Tanggal</label><input type="date" name="end_date" class="form-control" value="{{ $endDate }}"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Tampilkan</button></div>
            <div class="col-md-2">
                @if($account)
                    <a href="{{ route('accounting.ledger.print', ['coa_id' => $coaId, 'start_date' => $startDate, 'end_date' => $endDate]) }}" target="_blank" class="btn btn-outline-dark w-100"><i class="bi bi-printer"></i> Print</a>
                @else
                    <button type="button" class="btn btn-outline-secondary w-100" disabled><i class="bi bi-printer"></i> Print</button>
                @endif
            </div>
        </form>
    </div>
</div>

@if($account)
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0 text-primary">{{ $account->account_code }} - {{ $account->account_name }}</h5>
        <small class="text-muted">Saldo Normal: {{ strtoupper($account->normal_balance) }}</small>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-bordered table-hover mb-0">
            <thead class="table-light text-center"><tr><th>Tanggal</th><th>No. Jurnal</th><th>Keterangan</th><th>Debit</th><th>Kredit</th><th>Saldo</th></tr></thead>
            <tbody>
                <tr class="bg-light fw-bold"><td colspan="5" class="text-end">SALDO AWAL (Per {{ \Illuminate\Support\Carbon::parse($startDate)->format('d/m/Y') }})</td><td class="text-end">{{ number_format($openingBalance, 0, ',', '.') }}</td></tr>
                @forelse($ledger as $row)
                    <tr>
                        <td class="text-center">{{ \Illuminate\Support\Carbon::parse($row->journal_date)->format('d/m/Y') }}</td>
                        <td><span class="fw-bold text-primary">{{ $row->journal_no }}</span><br><small class="text-muted">{{ $row->reference_no }}</small></td>
                        <td>{{ $row->description }}</td>
                        <td class="text-end">{{ $row->debit > 0 ? number_format($row->debit, 0, ',', '.') : '-' }}</td>
                        <td class="text-end">{{ $row->credit > 0 ? number_format($row->credit, 0, ',', '.') : '-' }}</td>
                        <td class="text-end fw-bold bg-light">{{ number_format($row->running_balance, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-3 text-muted">Tidak ada transaksi pada periode ini.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light fw-bold"><tr><td colspan="3" class="text-end">TOTAL MUTASI :</td><td class="text-end text-success">{{ number_format($totalDebit, 0, ',', '.') }}</td><td class="text-end text-danger">{{ number_format($totalCredit, 0, ',', '.') }}</td><td class="text-end bg-warning bg-opacity-25">{{ number_format($endingBalance, 0, ',', '.') }}</td></tr></tfoot>
        </table>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('ledger-filter-form');
    if (!form) return;
    const submit = () => form.requestSubmit ? form.requestSubmit() : form.submit();
    form.querySelector('select[name="coa_id"]')?.addEventListener('change', submit);
    form.querySelector('input[name="start_date"]')?.addEventListener('change', submit);
    form.querySelector('input[name="end_date"]')?.addEventListener('change', submit);
})();
</script>
@endpush
