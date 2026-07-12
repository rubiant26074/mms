<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice - {{ $invoice->invoice_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { max-width: 800px; margin: auto; border: 1px solid #ccc; padding: 20px; min-height: 96vh; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        .doc-title { font-size: 26px; font-weight: bold; color: #555; letter-spacing: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 8px; text-align: left; }
        td { border-bottom: 1px solid #eee; padding: 8px; }
        .text-right { text-align: right; }
        .no-print { text-align:center; margin-bottom:20px; }
        @media print { .no-print { display:none; } .box { border:none; } }
    </style>
</head>
<body onload="window.print()">
<div class="no-print"><button onclick="window.print()">Cetak Invoice</button></div>
<div class="box">
    <div class="header"><div><strong>{{ $company['company_name'] ?? 'MMS SYSTEM' }}</strong><br>{{ $company['address'] ?? '-' }}</div><div class="doc-title">INVOICE<br><small>{{ $invoice->invoice_number }}</small></div></div>
    <table style="margin-bottom:15px;"><tr><td style="border:0;width:55%;"><strong>Kepada:</strong><br><strong>{{ $invoice->customer?->name }}</strong><br>{!! nl2br(e($invoice->customer?->address ?? '-')) !!}</td><td style="border:0;"><strong>Tanggal:</strong> {{ optional($invoice->invoice_date)->format('d/m/Y') }}<br><strong>Jatuh Tempo:</strong> {{ optional($invoice->due_date)->format('d/m/Y') }}<br><strong>No. {{ $invoice->sales_order_id ? 'SO' : 'SJ' }}:</strong> {{ $invoice->sales_order_id ? ($invoice->salesOrder?->so_number ?? '-') : ($invoice->deliveryNote?->dn_number ?? '-') }}<br><strong>NSFP:</strong> {{ $invoice->tax_invoice_number ?: '-' }}</td></tr></table>
    <table>
        <thead><tr><th>Barang</th><th class="text-right">Qty</th><th class="text-right">Harga</th><th class="text-right">Total</th></tr></thead>
        <tbody>@foreach($lines as $line)<tr><td><strong>{{ $line['item_name'] }}</strong><br><small>{{ $line['item_code'] }}</small></td><td class="text-right">{{ $line['qty_sent'] + 0 }} {{ $line['unit'] }}</td><td class="text-right">Rp {{ number_format($line['unit_price'], 0, ',', '.') }}</td><td class="text-right">Rp {{ number_format($line['total'], 0, ',', '.') }}</td></tr>@endforeach</tbody>
        <tfoot>
            <tr><td colspan="3" class="text-right">Subtotal</td><td class="text-right">Rp {{ number_format($invoice->subtotal, 0, ',', '.') }}</td></tr>
            @if($invoice->invoice_type === 'normal' && (float) $invoice->dp_amount > 0)
                <tr><td colspan="3" class="text-right" style="color:red;">Uang Muka (DP)</td><td class="text-right" style="color:red;">-Rp {{ number_format($invoice->dp_amount, 0, ',', '.') }}</td></tr>
            @endif
            <tr><td colspan="3" class="text-right">Diskon</td><td class="text-right">Rp {{ number_format($invoice->discount_amount, 0, ',', '.') }}</td></tr>
            <tr><td colspan="3" class="text-right">PPN</td><td class="text-right">Rp {{ number_format($invoice->tax_amount, 0, ',', '.') }}</td></tr>
            <tr><td colspan="3" class="text-right"><strong>Grand Total</strong></td><td class="text-right"><strong>Rp {{ number_format($invoice->grand_total, 0, ',', '.') }}</strong></td></tr>
        </tfoot>
    </table>
</div>
</body>
</html>
