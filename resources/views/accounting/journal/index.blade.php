@extends('layouts.mms')

@section('title', 'Jurnal Umum')

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6"><h3 class="fw-bold"><i class="bi bi-journal-text"></i> Jurnal Umum</h3><p class="text-muted">Pencatatan transaksi keuangan (Debit/Kredit).</p></div>
    <div class="col-md-6 text-end">
        <a href="{{ route('accounting.journal.print', ['search' => $search, 'start_date' => $startDate, 'end_date' => $endDate]) }}" target="_blank" class="btn btn-outline-dark me-2"><i class="bi bi-printer"></i> Print</a>
        <a href="{{ route('accounting.journal.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Buat Jurnal Manual</a>
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end" id="journal-filter-form">
            <div class="col-md-4"><label class="form-label small text-muted mb-1">Pencarian</label><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="text" name="search" class="form-control" placeholder="No Jurnal / Ref / Akun / Ket..." value="{{ $search }}" autocomplete="off"></div></div>
            <div class="col-md-3"><label class="form-label small text-muted mb-1">Dari</label><input type="date" name="start_date" class="form-control" value="{{ $startDate }}"></div>
            <div class="col-md-3"><label class="form-label small text-muted mb-1">Sampai</label><input type="date" name="end_date" class="form-control" value="{{ $endDate }}"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('accounting.journal.index') }}" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-hover table-bordered align-middle font-sm">
            <thead class="table-light text-center"><tr><th>Tgl</th><th>No. Jurnal / Ref</th><th>Akun (COA)</th><th>Keterangan</th><th>Debit</th><th>Kredit</th></tr></thead>
            <tbody>
            @php $currentJournal = ''; @endphp
            @forelse($rows as $row)
                @php $isNew = $currentJournal !== $row->journal_no; $currentJournal = $row->journal_no; @endphp
                <tr>
                    @if($isNew)
                        <td class="bg-light">{{ \Illuminate\Support\Carbon::parse($row->journal_date)->format('d/m/Y') }}</td>
                        <td class="bg-light"><strong>{{ $row->journal_no }}</strong><br><small class="text-muted">{{ $row->reference_no }}</small></td>
                    @else
                        <td class="border-0"></td><td class="border-0"></td>
                    @endif
                    <td @class(['fw-bold' => $row->credit <= 0]) @style(['padding-left: 30px' => $row->credit > 0])>{{ $row->account_code }} - {{ $row->account_name }}</td>
                    <td @class(['border-0' => ! $isNew])>{{ $isNew ? $row->description : '' }}</td>
                    <td class="text-end">{{ $row->debit > 0 ? number_format($row->debit, 0, ',', '.') : '-' }}</td>
                    <td class="text-end">{{ $row->credit > 0 ? number_format($row->credit, 0, ',', '.') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-5 text-muted">Tidak ada data jurnal pada filter ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('journal-filter-form');
    if (!form) return;
    const search = form.querySelector('input[name="search"]');
    let t;
    const submit = () => form.requestSubmit ? form.requestSubmit() : form.submit();
    search?.addEventListener('input', () => { clearTimeout(t); t = setTimeout(submit, 400); });
    form.querySelector('input[name="start_date"]')?.addEventListener('change', submit);
    form.querySelector('input[name="end_date"]')?.addEventListener('change', submit);
})();
</script>
@endpush
