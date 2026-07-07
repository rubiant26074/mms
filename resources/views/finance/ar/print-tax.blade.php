<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Faktur Pajak - {{ $invoice->tax_invoice_number ?: $invoice->invoice_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #000; }
        .sheet { max-width: 760px; margin: auto; border: 1px solid #000; padding: 14px; }
        h2 { text-align:center; margin:0 0 10px; }
        table { width:100%; border-collapse:collapse; }
        th, td { border:1px solid #000; padding:6px; }
        .text-right { text-align:right; }
        .no-print { text-align:center; margin-bottom:12px; }
        @media print { .no-print { display:none; } }
    </style>
</head>
<body onload="window.print()">
<div class="no-print"><button onclick="window.print()">Cetak Faktur Pajak</button></div>
<div class="sheet">
    <h2>FAKTUR PAJAK</h2>
    <table style="margin-bottom:10px;"><tr><td>No. Seri Faktur Pajak</td><td><strong>{{ $invoice->tax_invoice_number ?: '-' }}</strong></td></tr><tr><td>No. Invoice</td><td>{{ $invoice->invoice_number }} | {{ optional($invoice->invoice_date)->format('d/m/Y') }}</td></tr><tr><td>Penjual</td><td>{{ $company['company_name'] ?? 'MMS SYSTEM' }}</td></tr><tr><td>Pembeli</td><td>{{ $invoice->customer?->name }}<br>{{ $invoice->customer?->tax_id ?: '-' }}</td></tr></table>
    <table>
        <thead><tr><th>Nama Barang Kena Pajak / Jasa Kena Pajak</th><th class="text-right">Harga Jual</th></tr></thead>
        <tbody>@foreach($lines as $line)<tr><td>{{ $line['item_name'] }}</td><td class="text-right">Rp {{ number_format($line['total'], 0, ',', '.') }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td class="text-right">Harga Jual / Penggantian</td><td class="text-right">Rp {{ number_format($invoice->subtotal, 0, ',', '.') }}</td></tr><tr><td class="text-right">Dikurangi Potongan Harga</td><td class="text-right">Rp {{ number_format($invoice->discount_amount, 0, ',', '.') }}</td></tr><tr><td class="text-right">Dasar Pengenaan Pajak (DPP)</td><td class="text-right">Rp {{ number_format(max(0, $invoice->subtotal - $invoice->discount_amount), 0, ',', '.') }}</td></tr><tr><td class="text-right">PPN</td><td class="text-right">Rp {{ number_format($invoice->tax_amount, 0, ',', '.') }}</td></tr></tfoot>
    </table>
</div>
</body>
</html>
