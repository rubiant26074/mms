<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Print Rekap Cash / Chasier</title>
    <style>@page{size:A4 portrait;margin:0}body{font-family:Arial,sans-serif;font-size:11px;margin:0;padding:20px;color:#000}.box{border:1px solid #ccc;padding:20px;max-width:800px;margin:auto;min-height:96vh;display:flex;flex-direction:column}.doc-content{flex:1 1 auto}.header{border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:flex-start}.header-left img{max-height:60px;object-fit:contain}.header-right{text-align:right}.doc-title{font-size:24px;font-weight:bold;color:#555;letter-spacing:1.6px}.info-table,.summary-table,.data-table{width:100%;border-collapse:collapse}.info-table{margin-bottom:16px}.info-table td{vertical-align:top;padding:2px 0}.summary-table{margin-bottom:14px}.summary-table th,.summary-table td,.data-table th,.data-table td{border:1px solid #000;padding:6px}.summary-table th,.data-table th{background:#f8f9fa;font-size:10px;text-transform:uppercase}.summary-value{font-size:14px;font-weight:bold}.section-title{margin-top:14px;margin-bottom:6px;font-size:11px;font-weight:bold;text-transform:uppercase;text-decoration:underline}.text-right{text-align:right}.text-center{text-align:center}.ok{color:#0f5132;font-weight:bold}.bad{color:#b02a37;font-weight:bold}.page-footer{border-top:1px solid #ccc;padding-top:10px;text-align:center;margin-top:auto}.footer-comp-name{font-size:14.3px;font-weight:bold;display:block;margin-bottom:3px}.footer-addr{font-size:9px;color:#555}@media print{.box{border:none}}</style>
</head>
<body onload="window.print()">
<div class="box">
    <div class="doc-content">
        <div class="header"><div class="header-left">@if($company->logo_path)<img src="{{ asset($company->logo_path) }}" alt="Logo">@endif</div><div class="header-right"><div class="doc-title">REKAP CASH / CHASIER</div><div style="font-size:14px;font-weight:bold;color:#333;margin-top:5px;">{{ \Illuminate\Support\Carbon::parse($report['from'])->format('d/m/Y') }} - {{ \Illuminate\Support\Carbon::parse($report['to'])->format('d/m/Y') }}</div></div></div>
        <table class="info-table"><tr><td width="55%"><strong>Periode:</strong> {{ \Illuminate\Support\Carbon::parse($report['from'])->format('d/m/Y') }} s/d {{ \Illuminate\Support\Carbon::parse($report['to'])->format('d/m/Y') }}<br><strong>Akun Kas/Bank:</strong> {{ $report['cash_label'] }}</td><td width="45%" align="right"><strong>Dicetak Oleh:</strong> {{ $printedBy }}<br><strong>Waktu Cetak:</strong> {{ now()->format('d/m/Y H:i:s') }}</td></tr></table>
        <table class="summary-table"><thead><tr><th class="text-center">Saldo Awal</th><th class="text-center">Mutasi Masuk</th><th class="text-center">Mutasi Keluar</th><th class="text-center">Saldo Akhir</th></tr></thead><tbody><tr><td class="text-center summary-value">Rp {{ number_format($report['opening'], 0, ',', '.') }}</td><td class="text-center summary-value ok">Rp {{ number_format($report['income'], 0, ',', '.') }}</td><td class="text-center summary-value bad">Rp {{ number_format($report['expense'], 0, ',', '.') }}</td><td class="text-center summary-value {{ $report['closing'] >= 0 ? 'ok' : 'bad' }}">Rp {{ number_format($report['closing'], 0, ',', '.') }}</td></tr></tbody></table>
        <div class="section-title">Ringkasan Harian</div>
        <table class="data-table"><thead><tr><th width="18%">Tanggal</th><th width="21%" class="text-right">Pemasukan</th><th width="21%" class="text-right">Pengeluaran</th><th width="20%" class="text-right">Net</th><th width="20%" class="text-right">Saldo Berjalan</th></tr></thead><tbody>
            @php $running = $report['opening']; @endphp
            @forelse($report['rows'] as $row)
                @php $net = (float) $row->income_amount - (float) $row->expense_amount; $running += $net; @endphp
                <tr><td>{{ \Illuminate\Support\Carbon::parse($row->expense_date)->format('d/m/Y') }}</td><td class="text-right ok">Rp {{ number_format((float) $row->income_amount, 0, ',', '.') }}</td><td class="text-right bad">Rp {{ number_format((float) $row->expense_amount, 0, ',', '.') }}</td><td class="text-right {{ $net >= 0 ? 'ok' : 'bad' }}">Rp {{ number_format($net, 0, ',', '.') }}</td><td class="text-right {{ $running >= 0 ? 'ok' : 'bad' }}">Rp {{ number_format($running, 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center">Tidak ada transaksi posted pada periode ini.</td></tr>
            @endforelse
        </tbody></table>
        <div class="section-title">Detail Transaksi Posted</div>
        <table class="data-table"><thead><tr><th>Tgl</th><th>No. Bukti</th><th>Jenis</th><th>Kategori</th><th>Deskripsi</th><th>Akun</th><th class="text-right">Nominal</th></tr></thead><tbody>
            @forelse($report['transactions'] as $row)
                <tr><td>{{ optional($row->expense_date)->format('d/m/Y') }}</td><td>{{ $row->expense_number }}</td><td>{{ strtoupper($row->transaction_type === 'income' ? 'PEMASUKAN' : 'PENGELUARAN') }}</td><td>{{ $row->category }}</td><td>{{ $row->description }}@if($row->vendor_name)<br><small>{{ $row->vendor_name }}</small>@endif</td><td>{{ trim(($row->counterCoa?->account_code ?: '') . ($row->counterCoa?->account_name ? ' - ' . $row->counterCoa?->account_name : '')) }}<br><small>Kas/Bank: {{ trim(($row->cashCoa?->account_code ?: '') . ($row->cashCoa?->account_name ? ' - ' . $row->cashCoa?->account_name : '')) }}</small></td><td class="text-right {{ $row->transaction_type === 'income' ? 'ok' : 'bad' }}">Rp {{ number_format((float) $row->amount, 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="7" class="text-center">Tidak ada detail transaksi posted.</td></tr>
            @endforelse
        </tbody></table>
    </div>
    <div class="page-footer"><span class="footer-comp-name">{{ strtoupper($company->company_name ?? '-') }}</span><span class="footer-addr">{{ $company->address ?? '-' }}</span></div>
</div>
</body>
</html>
