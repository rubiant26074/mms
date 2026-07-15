<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $reportType === 'pl' ? 'LAPORAN LABA RUGI' : 'LAPORAN NERACA' }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: auto; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .logo-area { width: 50%; } .logo-area img { max-height: 45px; object-fit: contain; }
        .header-right { text-align: right; width: 45%; }
        .doc-title { font-size: 22px; font-weight: bold; color: #555; letter-spacing: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #f8f9fa; border-bottom: 1px solid #000; border-top: 1px solid #000; padding: 8px; text-align: left; }
        td { border-bottom: 1px solid #eee; padding: 8px; }
        .text-right { text-align: right; } .section-title { background: #f1f3f5; font-weight: bold; } .total-row { font-weight: bold; background: #fafafa; }
        .signature-section { margin-top: 16px; display: flex; justify-content: space-between; text-align: center; gap: 10px; }
        .sig-box { width: 48%; border: 1px solid #000; padding: 8px; min-height: 120px; display: flex; flex-direction: column; justify-content: flex-end; }
        .sig-label { font-weight: bold; margin-bottom: 6px; background: #f8f9fa; padding: 4px; border: 1px solid #ddd; }
        .sig-image { height: 50px; max-width: 120px; object-fit: contain; display: block; margin: 0 auto 5px auto; }
        .sig-name { border-top: 1px solid #000; padding-top: 4px; font-weight: bold; font-size: 10px; }
        .sig-note { font-size: 9px; color: #555; }
        .page-footer { border-top: 1px solid #ccc; padding-top: 10px; text-align: center; margin-top: 16px; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }
        @media print { .box { border: none; } }
    </style>
</head>
<body onload="window.print()">
<div class="box">
    <div class="doc-content">
        <div class="header">
            <div class="logo-area">@if($company->logo_path)<img src="{{ asset($company->logo_path) }}" alt="Logo">@endif</div>
            <div class="header-right"><div class="doc-title">{{ $reportType === 'pl' ? 'LAPORAN LABA RUGI' : 'LAPORAN NERACA' }}</div><div>{{ $periodLabel }}</div></div>
        </div>
        @if($reportType === 'pl')
            <table><thead><tr><th>Akun</th><th width="25%" class="text-right">Jumlah (Rp)</th></tr></thead><tbody>
                <tr class="section-title"><td colspan="2">PENDAPATAN (REVENUE)</td></tr>
                @forelse($data['revenue'] as $row)<tr><td>{{ $row->account_code }} - {{ $row->account_name }}</td><td class="text-right">{{ number_format($row->balance_rev, 0, ',', '.') }}</td></tr>@empty<tr><td colspan="2">Belum ada pendapatan.</td></tr>@endforelse
                <tr class="total-row"><td class="text-right">Total Pendapatan</td><td class="text-right">{{ number_format($totalRevenue, 0, ',', '.') }}</td></tr>
                <tr class="section-title"><td colspan="2">BEBAN (EXPENSES)</td></tr>
                @forelse($data['expense'] as $row)<tr><td>{{ $row->account_code }} - {{ $row->account_name }}</td><td class="text-right">{{ number_format($row->balance_exp, 0, ',', '.') }}</td></tr>@empty<tr><td colspan="2">Belum ada beban.</td></tr>@endforelse
                <tr class="total-row"><td class="text-right">Total Beban</td><td class="text-right">{{ number_format($totalExpense, 0, ',', '.') }}</td></tr>
                <tr class="total-row"><td class="text-right">LABA / (RUGI) BERSIH</td><td class="text-right">{{ number_format($netIncome, 0, ',', '.') }}</td></tr>
            </tbody></table>
        @else
            <table><thead><tr><th>AKTIVA (ASSETS)</th><th width="25%" class="text-right">Jumlah (Rp)</th></tr></thead><tbody>@foreach($data['asset'] as $row)<tr><td>{{ $row->account_code }} - {{ $row->account_name }}</td><td class="text-right">{{ number_format($row->balance_asset, 0, ',', '.') }}</td></tr>@endforeach<tr class="total-row"><td class="text-right">TOTAL ASSET</td><td class="text-right">{{ number_format($totalAsset, 0, ',', '.') }}</td></tr></tbody></table>
            <table><thead><tr><th>KEWAJIBAN (LIABILITY)</th><th width="25%" class="text-right">Jumlah (Rp)</th></tr></thead><tbody>@foreach($data['liability'] as $row)<tr><td>{{ $row->account_code }} - {{ $row->account_name }}</td><td class="text-right">{{ number_format($row->balance_passiva, 0, ',', '.') }}</td></tr>@endforeach<tr class="total-row"><td class="text-right">Total Kewajiban</td><td class="text-right">{{ number_format($totalLiability, 0, ',', '.') }}</td></tr></tbody></table>
            <table><thead><tr><th>MODAL (EQUITY)</th><th width="25%" class="text-right">Jumlah (Rp)</th></tr></thead><tbody>@foreach($data['equity'] as $row)<tr><td>{{ $row->account_code }} - {{ $row->account_name }}</td><td class="text-right">{{ number_format($row->balance_passiva, 0, ',', '.') }}</td></tr>@endforeach<tr class="total-row"><td class="text-right">TOTAL PASIVA</td><td class="text-right">{{ number_format($totalLiability + $totalEquity, 0, ',', '.') }}</td></tr></tbody></table>
        @endif
        <div class="signature-section">
            <div class="sig-box"><div class="sig-label">Disusun Oleh</div>@if($preparedUser?->signature_path)<img src="{{ asset($preparedUser->signature_path) }}" class="sig-image" alt="Signature">@else<div style="height:50px;"></div>@endif<div class="sig-name">{{ $preparedUser?->fullname ?: '....................' }}</div><div class="sig-note">Accounting Staff</div></div>
            <div class="sig-box"><div class="sig-label">Mengetahui</div>@if($approverUser?->signature_path)<img src="{{ asset($approverUser->signature_path) }}" class="sig-image" alt="Signature">@else<div style="height:50px;"></div>@endif<div class="sig-name">{{ $approverUser?->fullname ?: '....................' }}</div><div class="sig-note">Finance / Accounting</div></div>
        </div>
    </div>
    <div class="page-footer"><span class="footer-comp-name">{{ strtoupper($company->company_name ?: 'MMS SYSTEM') }}</span><span class="footer-addr">{{ $company->address ?: '-' }}</span></div>
</div>
</body>
</html>
