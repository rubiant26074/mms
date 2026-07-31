<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>RFQ - {{ $rfq->rfq_number }}</title>
    <style>
        body{font-family:Arial,sans-serif;font-size:12px;color:#111}.wrap{max-width:1180px;margin:0 auto}.title{font-size:18px;font-weight:700;margin-bottom:4px}.muted{color:#555}table{width:100%;border-collapse:collapse;margin-top:8px}th,td{border:1px solid #ccc;padding:6px 8px}th{background:#f3f3f3;text-align:left}.text-end{text-align:right}.text-center{text-align:center}@media print{body{margin:0}}
    </style>
</head>
<body onload="window.print()">
<div class="wrap">
    <div class="title">RFQ Comparison Report</div>
    <div class="muted">{{ $company->company_name ?? 'MMS' }}</div>
    <div class="muted">
        No RFQ: <strong>{{ $rfq->rfq_number }}</strong> |
        Tanggal: {{ optional($rfq->rfq_date)->format('d/m/Y') }} |
        Due: {{ optional($rfq->due_date)->format('d/m/Y') ?: '-' }} |
        Status: {{ strtoupper($rfq->status) }}
    </div>
    <table>
        <thead><tr><th>Item</th><th class="text-end">Qty</th><th>Unit</th><th>Vendor</th><th class="text-end">Harga</th><th class="text-end">Lead Time</th><th class="text-end">Subtotal</th><th class="text-center">Flag</th></tr></thead>
        <tbody>
        @forelse($rfq->quotes->sortBy([['item_name','asc'],['unit_price','asc']]) as $line)
            @php
                $key = trim((string) $line->item_name).'|'.trim((string) $line->unit);
                $isBest = (float) $line->unit_price <= (float) ($bestPrices[$key] ?? 0);
                $subtotal = (float) $line->qty * (float) $line->unit_price;
            @endphp
            <tr>
                <td>{{ $line->item_name }}</td>
                <td class="text-end">{{ number_format((float) $line->qty, 4, ',', '.') }}</td>
                <td>{{ $line->unit }}</td>
                <td>{{ $line->supplier?->code }} - {{ $line->supplier?->name }}</td>
                <td class="text-end">{{ number_format((float) $line->unit_price, 2, ',', '.') }}</td>
                <td class="text-end">{{ $line->lead_time_days !== null ? $line->lead_time_days.' hari' : '-' }}</td>
                <td class="text-end">{{ number_format($subtotal, 2, ',', '.') }}</td>
                <td class="text-center">{{ $isBest ? 'Best Price' : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center">Belum ada quote vendor.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
