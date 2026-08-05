<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice - {{ $invoice->invoice_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { max-width: 800px; margin: auto; border: 1px solid #ccc; padding: 20px; min-height: 96vh; display: flex; flex-direction: column; position: relative; }
        .content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .logo { max-height: 31px; object-fit: contain; }
        .doc-title-box { border: none; padding: 5px; display: inline-block; text-align: center; }
        .doc-title { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
        .doc-subtitle { font-size: 9px; letter-spacing: 1px; }
        .doc-number { font-size: 13px; font-weight: bold; margin-top: 8px; text-align: right; }
        
        table.info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.info-table td { vertical-align: top; padding: 2px; }
        
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.data-table th, table.data-table td { padding: 8px; }
        table.data-table th { background: #f8f9fa; border-top: 1px solid #000; border-bottom: 1px solid #000; text-align: left; font-weight: bold; }
        table.data-table td { border-bottom: 1px solid #eee; }
        
        .text-right { text-align: right; }
        .no-print { text-align: center; margin-bottom: 20px; }
        .page-footer { margin-top: auto; text-align: center; border-top: 1px solid #ccc; padding-top: 10px; }
        .footer-comp-name { font-size: 14px; font-weight: bold; display: block; }
        @media print{
            .page-footer{position:relative}
            .page-footer::after{content:"Page " counter(page);position:absolute;right:10px;bottom:10px;font-size:9px;color:#555}
            .no-print { display: none; }
            .box { border: none; }
            body { padding: 20px; }
            .page-footer { position: fixed; bottom: 20px; left: 20px; right: 20px; background: #fff; }
            tr { page-break-inside: avoid; break-inside: avoid; }
            .summary-table { page-break-inside: avoid; break-inside: avoid; }
            .footer-sig { page-break-inside: avoid; break-inside: avoid; }
            .notes-box { page-break-inside: avoid; break-inside: avoid; }
        }
    </style>
</head>
<body onload="window.print()">
@php
    $compLogo = is_array($company) ? ($company['logo_path'] ?? null) : ($company->logo_path ?? null);
    $compName = is_array($company) ? ($company['company_name'] ?? null) : ($company->company_name ?? null);
    $compAddress = is_array($company) ? ($company['address'] ?? null) : ($company->address ?? null);
@endphp
<div class="no-print"><button onclick="window.print()" style="padding: 8px 16px; background-color: #333; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Cetak Invoice</button></div>
<div class="box">
    <table style="width:100%;border-collapse:collapse;border:none;">
    <thead><tr><td style="border:none;padding:0;">
        <div class="header">
            <div>
                @if($compLogo)
                    <img src="{{ asset($compLogo) }}" class="logo" alt="Logo">
                @endif
            </div>
            <div style="text-align:right">
                <div class="doc-title-box">
                    <div class="doc-title">INVOICE</div>
                    <div class="doc-subtitle">FAKTUR PENJUALAN</div>
                </div>
                <div class="doc-number">{{ $invoice->invoice_number }}</div>
            </div>
        </div>
    </td></tr></thead>
    <tbody><tr><td style="border:none;padding:0;">
        <div class="content">

        <table class="info-table">
            <tr>
                <td width="55%">
                    <strong>Kepada:</strong><br>
                    <strong>{{ strtoupper($invoice->customer?->name ?: '-') }}</strong><br>
                    {!! nl2br(e($invoice->customer?->address ?? '-')) !!}
                </td>
                <td width="45%" align="right">
                    <strong>Tanggal:</strong> {{ optional($invoice->invoice_date)->format('d F Y') }}<br>
                    <strong>Jatuh Tempo:</strong> {{ optional($invoice->due_date)->format('d F Y') }}<br>
                    <strong>No. {{ $invoice->sales_order_id ? 'SO' : 'SJ' }}:</strong> {{ $invoice->sales_order_id ? ($invoice->salesOrder?->so_number ?? '-') : ($invoice->deliveryNote?->dn_number ?? '-') }}<br>
                    <strong>NSFP:</strong> {{ $invoice->tax_invoice_number ?: '-' }}
                </td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Barang</th>
                    <th class="text-right" style="width: 15%;">Qty</th>
                    <th class="text-right" style="width: 20%;">Harga</th>
                    <th class="text-right" style="width: 20%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lines as $line)
                    <tr>
                        <td><strong>{{ $line['item_name'] }}</strong><br><small>{{ $line['item_code'] }}</small></td>
                        <td class="text-right">{{ $line['qty_sent'] + 0 }} {{ $line['unit'] }}</td>
                        <td class="text-right">Rp {{ number_format($line['unit_price'], 0, ',', '.') }}</td>
                        <td class="text-right">Rp {{ number_format($line['total'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td colspan="3" class="text-right" style="border-top: 1px solid #000;">Subtotal</td><td class="text-right" style="border-top: 1px solid #000;">Rp {{ number_format($invoice->subtotal, 0, ',', '.') }}</td></tr>
                @if($invoice->invoice_type === 'normal' && (float) $invoice->dp_amount > 0)
                    <tr><td colspan="3" class="text-right" style="color:red;">Uang Muka (DP)</td><td class="text-right" style="color:red;">-Rp {{ number_format($invoice->dp_amount, 0, ',', '.') }}</td></tr>
                @endif
                <tr><td colspan="3" class="text-right">Diskon</td><td class="text-right">Rp {{ number_format($invoice->discount_amount, 0, ',', '.') }}</td></tr>
                <tr><td colspan="3" class="text-right">PPN</td><td class="text-right">Rp {{ number_format($invoice->tax_amount, 0, ',', '.') }}</td></tr>
                <tr style="font-weight: bold; border-top: 2px solid #000;"><td colspan="3" class="text-right">Grand Total</td><td class="text-right">Rp {{ number_format($invoice->grand_total, 0, ',', '.') }}</td></tr>
            </tfoot>
    </div>
</td></tr></tbody>
<tfoot><tr><td style="border:none;padding:0;height:40px;">
    <div class="page-footer">
        <span class="footer-comp-name">{{ strtoupper($compName ?? '-') }}</span>
        <span>{{ $compAddress ?? '-' }}</span>
        <div style="margin-top: 5px;">
            @php
                $phoneVal = is_array($company) ? ($company['phone'] ?? null) : ($company->phone ?? null);
                $salesPhoneVal = $invoice->customer?->sales_phone ?: $invoice->salesOrder?->customer?->sales_phone;
            @endphp
            @if($phoneVal)
                <span style="display: inline-flex; align-items: center; margin-right: 15px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72, 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    {{ $phoneVal }}
                </span>
            @endif
            @if($salesPhoneVal)
                <span style="display: inline-flex; align-items: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; color: #25D366; margin-right: 3.5px;"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.457L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.625 1.451 5.436-.002 9.852-4.419 9.855-9.858.002-2.634-1.02-5.11-2.881-6.974A9.784 9.784 0 0 0 12.008 1.94c-5.439 0-9.856 4.417-9.859 9.858-.001 1.76.49 3.473 1.42 4.966l-.995 3.63 3.733-.978c1.452.793 2.924 1.188 4.333 1.188zm12.355-6.737c-.302-.152-1.791-.883-2.073-.984-.282-.102-.489-.153-.694.153-.205.305-.794.996-.973 1.2-.18.204-.36.229-.663.077-1.128-.565-2.128-1.045-2.92-2.404-.15-.258-.15-.418-.001-.568.136-.134.302-.354.453-.531.152-.178.202-.303.303-.506.101-.202.051-.379-.025-.531-.076-.152-.693-1.67-.95-2.285-.25-.601-.523-.518-.718-.528-.186-.01-.399-.01-.612-.01-.213 0-.56.08-.853.401-.293.32-.1.121-.1.121s-.11.236-.264.498c-.144.248-.48.918-.737 1.442-.258.525-.494 1.01-.652 1.332-.239.489-.356.73-.393.8-.073.134-.146.269-.22.404-.393.722-.59 1.545-.588 2.385.006 2.548 1.833 4.89 2.088 5.23.256.34 3.522 5.378 8.532 7.545 1.192.515 2.122.822 2.848 1.053 1.198.38 2.29.327 3.153.198.96-.144 2.952-1.206 3.364-2.37.412-1.164.412-2.164.29-2.37-.123-.207-.453-.356-.755-.508z"/></svg>
                    {{ $salesPhoneVal }}
                </span>
            @endif
        </div>
    </div>
</td></tr></tfoot>
</table>
</div>
</body>
</html>
