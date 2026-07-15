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
        .logo { max-height: 45px; object-fit: contain; }
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
        @media print {
            .no-print { display: none; }
            .box { border: none; }
            body { padding: 20px; }
            .page-footer { position: fixed; bottom: 20px; left: 20px; right: 20px; background: #fff; }
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
    </div>
</td></tr></tfoot>
</table>
</div>
</body>
</html>
