<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jurnal Umum - Print</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: auto; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header-left img { max-height: 60px; object-fit: contain; }
        .header-right { text-align: right; }
        .doc-title { font-size: 24px; font-weight: bold; color: #555; letter-spacing: 1.2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #000; padding: 5px; }
        th { background: #f8f9fa; font-size: 10px; }
        .info-table td { border: 0; vertical-align: top; padding: 2px; }
        .text-right { text-align: right; } .text-center { text-align: center; } .fw-bold { font-weight: bold; }
        .page-footer { border-top: 1px solid #ccc; padding-top: 10px; text-align: center; margin-top: auto; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }
        @media print { .box { border: none; } }
    </style>
</head>
<body onload="window.print()">
<div class="box">
    <div class="doc-content">
        <div class="header">
            <div class="header-left">@if($company->logo_path)<img src="{{ asset($company->logo_path) }}" alt="Logo">@endif</div>
            <div class="header-right"><div class="doc-title">JURNAL UMUM</div><div style="font-size: 14px; font-weight: bold; color: #333; margin-top: 5px;">{{ $periodLabel }}</div></div>
        </div>
        <table class="info-table"><tr><td width="55%"><strong>Periode:</strong> {{ $periodLabel }}<br><strong>Pencarian:</strong> {{ $search !== '' ? $search : '-' }}</td><td width="45%" align="right"><strong>Jumlah Jurnal:</strong> {{ $journalCount }}<br><strong>Waktu Cetak:</strong> {{ now()->format('d/m/Y H:i') }}</td></tr></table>
        <table><thead><tr><th class="text-center">Total Debit</th><th class="text-center">Total Kredit</th><th class="text-center">Status</th></tr></thead><tbody><tr><td class="text-center fw-bold">Rp {{ number_format($totalDebit, 0, ',', '.') }}</td><td class="text-center fw-bold">Rp {{ number_format($totalCredit, 0, ',', '.') }}</td><td class="text-center fw-bold">{{ round($totalDebit, 2) === round($totalCredit, 2) ? 'BALANCE' : 'TIDAK BALANCE' }}</td></tr></tbody></table>
        <table>
            <thead><tr><th>Tanggal</th><th>No Jurnal / Ref</th><th>Akun (COA)</th><th>Keterangan</th><th class="text-right">Debit</th><th class="text-right">Kredit</th></tr></thead>
            <tbody>
            @php $currentJournal = ''; @endphp
            @forelse($rows as $row)
                @php $isNew = $currentJournal !== $row->journal_no; $currentJournal = $row->journal_no; @endphp
                <tr>
                    @if($isNew)
                        <td>{{ \Illuminate\Support\Carbon::parse($row->journal_date)->format('d/m/Y') }}</td><td><strong>{{ $row->journal_no }}</strong><br><small>{{ $row->reference_no ?: '-' }}</small></td>
                    @else
                        <td></td><td></td>
                    @endif
                    <td style="{{ $row->credit > 0 ? 'padding-left: 20px;' : 'font-weight: bold;' }}">{{ $row->account_code }} - {{ $row->account_name }}</td>
                    <td>{{ $isNew ? $row->description : '' }}</td>
                    <td class="text-right">{{ $row->debit > 0 ? number_format($row->debit, 0, ',', '.') : '-' }}</td>
                    <td class="text-right">{{ $row->credit > 0 ? number_format($row->credit, 0, ',', '.') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Tidak ada data jurnal pada filter ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="page-footer"><span class="footer-comp-name">{{ strtoupper($company->company_name ?: 'MMS SYSTEM') }}</span><span class="footer-addr">{{ $company->address ?: '-' }}</span></div>
</div>
</body>
</html>
