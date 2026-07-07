<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Batch & Expiry</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: auto; min-height: 96vh; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; }
        .doc-title { font-size: 21px; font-weight: bold; color: #555; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f3f3; border: 1px solid #999; padding: 6px; text-align: left; }
        td { border: 1px solid #ddd; padding: 6px; vertical-align: top; }
        .meta td { border: none; padding: 2px 4px; }
        .text-right { text-align: right; }
        .no-print { text-align: center; margin-bottom: 20px; }
        @media print { .no-print { display: none; } .box { border: none; } }
    </style>
</head>
<body onload="window.print()">
<div class="no-print"><button onclick="window.print()">Cetak</button></div>
<div class="box">
    <div class="header">
        <div><strong>{{ $company['company_name'] ?? 'MMS SYSTEM' }}</strong><br>{{ $company['address'] ?? '-' }}</div>
        <div class="doc-title">LAPORAN BATCH & EXPIRY</div>
    </div>
    <table class="meta">
        <tr><td>Filter</td><td>: {{ strtoupper($expiry) }}</td><td>Tanggal Cetak</td><td>: {{ now()->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Search</td><td>: {{ $search ?: '-' }}</td><td>Total Qty</td><td>: {{ number_format((float) $summary['total_qty'], 2, ',', '.') }}</td></tr>
    </table>
    <br>
    <table>
        <thead><tr><th>Item</th><th>Batch</th><th>MFG</th><th>Expiry</th><th class="text-right">Qty</th><th>Status</th><th>Ref</th></tr></thead>
        <tbody>
        @forelse($batches as $batch)
            @php
                $status = 'NO EXPIRY';
                if ($batch->expiry_date) {
                    $status = $batch->expiry_date->isPast() ? 'EXPIRED' : ($batch->expiry_date->lte(now()->addDays(30)) ? 'NEAR EXPIRY' : 'SAFE');
                }
            @endphp
            <tr>
                <td><strong>{{ $batch->item?->item_code }}</strong><br>{{ $batch->item?->item_name }}</td>
                <td>{{ $batch->batch_number }}</td>
                <td>{{ optional($batch->mfg_date)->format('d/m/Y') ?: '-' }}</td>
                <td>{{ optional($batch->expiry_date)->format('d/m/Y') ?: '-' }}</td>
                <td class="text-right">{{ number_format($batch->qty_available, 2, ',', '.') }} {{ $batch->unit ?: $batch->item?->unit }}</td>
                <td>{{ $status }}</td>
                <td>{{ $batch->source_doc ?: '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" style="text-align:center;">Belum ada data batch.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
