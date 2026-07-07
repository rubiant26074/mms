<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi PPN - {{ $periodLabel }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: auto; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header-left img { max-height: 60px; object-fit: contain; }
        .header-right { text-align: right; }
        .doc-title { font-size: 24px; font-weight: bold; color: #555; letter-spacing: 1.2px; }
        .info-table, .item-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .info-table td { vertical-align: top; padding: 2px; }
        .item-table th { background: #f8f9fa; border: 1px solid #000; padding: 8px; text-align: left; font-size: 10px; }
        .item-table td { border: 1px solid #000; padding: 6px; }
        .section-title { font-weight: bold; font-size: 11px; margin: 10px 0 6px; text-decoration: underline; text-transform: uppercase; }
        .text-right { text-align: right; } .text-center { text-align: center; } .ok { color: #0f5132; font-weight: bold; } .bad { color: #b02a37; font-weight: bold; }
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
            <div class="header-right"><div class="doc-title">REKAPITULASI PPN</div><div style="font-size: 14px; font-weight: bold; color: #333; margin-top: 5px;">{{ $periodLabel }}</div></div>
        </div>
        <table class="info-table"><tr><td width="55%"><strong>Status PPN:</strong> {{ $statusTax }}<br><strong>Masa Pajak:</strong> {{ $periodLabel }}<br><strong>Tabel Pembayaran Pajak:</strong> {{ $taxTableExists ? 'Tersedia' : 'Belum Tersedia' }}</td><td width="45%" align="right"><strong>Tanggal Cetak:</strong> {{ now()->format('d/m/Y H:i') }}<br><strong>User:</strong> {{ auth()->user()?->fullname ?: '-' }}</td></tr></table>
        <table class="item-table"><tbody>
            <tr><td>DPP Keluaran</td><td class="text-right">Rp {{ number_format($dpp_out, 0, ',', '.') }}</td></tr>
            <tr><td>PPN Keluaran</td><td class="text-right ok">Rp {{ number_format($ppn_out, 0, ',', '.') }}</td></tr>
            <tr><td>DPP Masukan</td><td class="text-right">Rp {{ number_format($dpp_in, 0, ',', '.') }}</td></tr>
            <tr><td>PPN Masukan</td><td class="text-right ok">Rp {{ number_format($ppn_in, 0, ',', '.') }}</td></tr>
            <tr><td>Kewajiban Setor PPN</td><td class="text-right bad">Rp {{ number_format($tax_due, 0, ',', '.') }}</td></tr>
            <tr><td>Sudah Disetor</td><td class="text-right ok">Rp {{ number_format($taxPaid, 0, ',', '.') }}</td></tr>
            <tr><td>Sisa Setor</td><td class="text-right bad">Rp {{ number_format($taxRemaining, 0, ',', '.') }}</td></tr>
        </tbody></table>
        <div class="section-title">Riwayat Pembayaran Pajak</div>
        <table class="item-table"><thead><tr><th>Tanggal</th><th>Metode</th><th>Referensi</th><th>No. Jurnal</th><th class="text-right">Jumlah</th></tr></thead><tbody>
            @forelse($paymentHistory as $p)<tr><td>{{ \Illuminate\Support\Carbon::parse($p->payment_date)->format('d/m/Y') }}</td><td>{{ $p->method }}</td><td>{{ $p->reference_no ?: '-' }}</td><td>{{ $p->journal_no ?: '-' }}</td><td class="text-right"><strong>Rp {{ number_format($p->amount, 0, ',', '.') }}</strong></td></tr>@empty<tr><td colspan="5" class="text-center">Belum ada pembayaran pajak untuk masa ini.</td></tr>@endforelse
        </tbody></table>
        <div class="section-title">Monitoring Nomor Seri Faktur Pajak</div>
        <table class="item-table"><thead><tr><th>No. Invoice</th><th>Tanggal</th><th>Jatuh Tempo</th><th>No. Seri Faktur Pajak</th><th class="text-center">Status</th></tr></thead><tbody>
            @forelse($invoices as $invoice) @php $hasNsfp = filled($invoice->tax_invoice_number); @endphp <tr><td><strong>{{ $invoice->invoice_number }}</strong></td><td>{{ $invoice->invoice_date ? \Illuminate\Support\Carbon::parse($invoice->invoice_date)->format('d/m/Y') : '-' }}</td><td>{{ $invoice->due_date ? \Illuminate\Support\Carbon::parse($invoice->due_date)->format('d/m/Y') : '-' }}</td><td>{{ $hasNsfp ? $invoice->tax_invoice_number : 'Belum diisi' }}</td><td class="text-center {{ $hasNsfp ? 'ok' : 'bad' }}">{{ $hasNsfp ? 'Lengkap' : 'Belum' }}</td></tr>@empty<tr><td colspan="5" class="text-center">Tidak ada invoice pada periode ini.</td></tr>@endforelse
        </tbody></table>
    </div>
    <div class="page-footer"><span class="footer-comp-name">{{ strtoupper($company->company_name ?: 'MMS SYSTEM') }}</span><span class="footer-addr">{{ $company->address ?: '-' }}</span></div>
</div>
</body>
</html>
