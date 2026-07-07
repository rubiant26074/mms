@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Transaksi Cash/Chasier' : 'Input Transaksi Cash/Chasier')

@section('content')
@include('partials.alerts')
<form method="POST" action="{{ $isEdit ? route('finance.cash.update', $transaction) : route('finance.cash.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">Info Transaksi</div>
                <div class="card-body">
                    <div class="mb-2"><label class="form-label">No. Bukti</label><input type="text" class="form-control fw-bold" value="{{ $transaction->expense_number }}" readonly></div>
                    <div class="mb-2"><label class="form-label">Jenis Transaksi <span class="text-danger">*</span></label><select name="transaction_type" id="transactionType" class="form-select" required><option value="expense" @selected(old('transaction_type', $transaction->transaction_type) === 'expense')>Pengeluaran</option><option value="income" @selected(old('transaction_type', $transaction->transaction_type) === 'income')>Pemasukan</option></select></div>
                    <div class="mb-2"><label class="form-label">Tanggal <span class="text-danger">*</span></label><input type="date" name="expense_date" class="form-control" value="{{ old('expense_date', optional($transaction->expense_date)->format('Y-m-d') ?: now()->toDateString()) }}" required></div>
                    <div class="mb-2"><label class="form-label">Kategori <span class="text-danger">*</span></label><input type="text" name="category" class="form-control" value="{{ old('category', $transaction->category) }}" required></div>
                    <div class="mb-2"><label class="form-label">Metode Pembayaran</label><select name="payment_method" class="form-select">@foreach(['Cash','Transfer Bank','E-Wallet','Lainnya'] as $method)<option value="{{ $method }}" @selected(old('payment_method', $transaction->payment_method) === $method)>{{ $method }}</option>@endforeach</select></div>
                    <div class="mb-2"><label class="form-label">Nominal (Rp) <span class="text-danger">*</span></label><input type="number" name="amount" class="form-control text-end" value="{{ old('amount', (float) $transaction->amount) }}" min="0" step="0.01" required></div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">Akun & Detail</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Akun Lawan <span class="text-danger">*</span></label><select name="coa_id" id="counterCoaSelect" class="form-select" required data-selected="{{ old('coa_id', $transaction->coa_id) }}"><option value="">-- Pilih Akun --</option></select><small class="text-muted">Pengeluaran: akun expense, Pemasukan: akun revenue.</small></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Akun Kas / Bank <span class="text-danger">*</span></label><select name="cash_coa_id" class="form-select" required><option value="">-- Pilih Akun Kas/Bank --</option>@foreach($cashAccounts as $acc)<option value="{{ $acc->id }}" @selected((int) old('cash_coa_id', $transaction->cash_coa_id) === $acc->id)>{{ $acc->account_code }} - {{ $acc->account_name }}</option>@endforeach</select></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label" id="counterpartyLabel">Relasi (Vendor / Sumber)</label><input type="text" name="vendor_name" class="form-control" value="{{ old('vendor_name', $transaction->vendor_name) }}" placeholder="Opsional"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">No. Referensi</label><input type="text" name="reference_no" class="form-control" value="{{ old('reference_no', $transaction->reference_no) }}" placeholder="Contoh: INV-001 / Bukti Transfer"></div>
                    </div>
                    <div class="mb-3"><label class="form-label" id="descriptionLabel">Deskripsi Transaksi <span class="text-danger">*</span></label><textarea name="description" rows="4" class="form-control" required>{{ old('description', $transaction->description) }}</textarea></div>
                    <div class="mb-0"><label class="form-label">Catatan Internal</label><textarea name="notes" rows="2" class="form-control">{{ old('notes', $transaction->notes) }}</textarea></div>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between mb-5"><a href="{{ route('finance.cash.index') }}" class="btn btn-secondary">Kembali</a><button type="submit" class="btn btn-primary px-4">Simpan Draft</button></div>
</form>

@push('scripts')
<script>
(function(){const trx=document.getElementById('transactionType');const cp=document.getElementById('counterpartyLabel');const desc=document.getElementById('descriptionLabel');const counterSelect=document.getElementById('counterCoaSelect');if(!trx||!cp||!desc||!counterSelect)return;const expenseAccounts=@json($expenseAccounts->map(fn($a)=>['id'=>$a->id,'label'=>$a->account_code.' - '.$a->account_name])->values());const revenueAccounts=@json($revenueAccounts->map(fn($a)=>['id'=>$a->id,'label'=>$a->account_code.' - '.$a->account_name])->values());const refill=()=>{const selected=parseInt(counterSelect.getAttribute('data-selected')||counterSelect.value||'0',10);const source=trx.value==='income'?revenueAccounts:expenseAccounts;counterSelect.innerHTML='<option value="">-- Pilih Akun --</option>';source.forEach(acc=>{const opt=document.createElement('option');opt.value=String(acc.id);opt.textContent=acc.label;if(selected>0&&selected===acc.id)opt.selected=true;counterSelect.appendChild(opt);});counterSelect.removeAttribute('data-selected');};const sync=()=>{if(trx.value==='income'){cp.textContent='Relasi (Pelanggan / Sumber Dana)';desc.innerHTML='Deskripsi Pemasukan <span class="text-danger">*</span>';}else{cp.textContent='Relasi (Vendor / Penerima)';desc.innerHTML='Deskripsi Pengeluaran <span class="text-danger">*</span>';}refill();};trx.addEventListener('change',sync);sync();})();
</script>
@endpush
@endsection
