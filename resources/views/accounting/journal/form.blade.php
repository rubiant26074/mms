@extends('layouts.mms')

@section('title', 'Input Jurnal Umum')

@section('content')
@include('partials.alerts')
<form method="POST" action="{{ route('accounting.journal.store') }}" id="journalForm">
    @csrf
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">Header Jurnal</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3"><label>No. Jurnal</label><input type="text" class="form-control fw-bold" value="AUTO" readonly></div>
                <div class="col-md-3 mb-3"><label>Tanggal</label><input type="date" name="journal_date" class="form-control" value="{{ old('journal_date', now()->format('Y-m-d')) }}" required></div>
                <div class="col-md-6 mb-3"><label>Referensi (No. Bukti)</label><input type="text" name="reference_no" class="form-control" value="{{ old('reference_no') }}" placeholder="Contoh: BKK-001"></div>
            </div>
            <div class="mb-3"><label>Keterangan</label><textarea name="description" class="form-control" rows="2" required>{{ old('description') }}</textarea></div>
        </div>
    </div>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between"><strong>Detail Transaksi</strong><button type="button" class="btn btn-sm btn-success" onclick="addRow()">+ Tambah Baris</button></div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead class="table-light text-center"><tr><th width="40%">Akun (COA)</th><th width="25%">Debit</th><th width="25%">Kredit</th><th width="10%">Hapus</th></tr></thead>
                <tbody id="journalItems"></tbody>
                <tfoot class="table-light fw-bold"><tr><td class="text-end">TOTAL :</td><td class="text-end"><span id="totalDebit">0</span></td><td class="text-end"><span id="totalCredit">0</span></td><td class="text-center"><span id="balanceStatus" class="badge bg-danger">Not Balance</span></td></tr></tfoot>
            </table>
        </div>
    </div>
    <div class="text-end mb-5"><button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> Simpan Jurnal</button></div>
</form>
@endsection

@push('scripts')
<script>
const accounts = @json($accounts->map(fn($a) => ['id' => $a->id, 'account_code' => $a->account_code, 'account_name' => $a->account_name])->values());
function addRow() {
    let opts = '<option value="">-- Pilih Akun --</option>';
    accounts.forEach(acc => opts += `<option value="${acc.id}">${acc.account_code} - ${acc.account_name}</option>`);
    const row = `<tr><td><select name="coa_id[]" class="form-select" required>${opts}</select></td><td><input type="number" name="debit[]" class="form-control text-end debit-input" value="0" min="0" oninput="calcTotal()"></td><td><input type="number" name="credit[]" class="form-control text-end credit-input" value="0" min="0" oninput="calcTotal()"></td><td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); calcTotal()">X</button></td></tr>`;
    document.getElementById('journalItems').insertAdjacentHTML('beforeend', row);
}
function calcTotal() {
    let totD = 0, totC = 0;
    document.querySelectorAll('.debit-input').forEach(i => totD += parseFloat(i.value) || 0);
    document.querySelectorAll('.credit-input').forEach(i => totC += parseFloat(i.value) || 0);
    document.getElementById('totalDebit').innerText = new Intl.NumberFormat('id-ID').format(totD);
    document.getElementById('totalCredit').innerText = new Intl.NumberFormat('id-ID').format(totC);
    const status = document.getElementById('balanceStatus');
    if (totD === totC && totD > 0) { status.className = 'badge bg-success'; status.innerText = 'Balance'; }
    else { status.className = 'badge bg-danger'; status.innerText = 'Not Balance'; }
}
window.addEventListener('load', () => { addRow(); addRow(); });
</script>
@endpush
