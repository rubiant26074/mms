<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Vendor Rating - {{ $period ?: 'Semua Periode' }}</title>
    <style>
        @page{size:A4 landscape;margin:12mm}body{font-family:Arial,sans-serif;font-size:12px;color:#111}.header{display:flex;justify-content:space-between;border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:14px}.title{font-size:20px;font-weight:bold}.muted{color:#666}.table{border-collapse:collapse;width:100%}.table th,.table td{border:1px solid #333;padding:7px}.table th{background:#f2f2f2;text-transform:uppercase;font-size:11px}.text-right{text-align:right}.text-center{text-align:center}.badge{border:1px solid #333;padding:2px 8px;font-weight:bold}.footer{margin-top:18px;text-align:right}@media print{body{margin:0}}
    </style>
</head>
<body onload="window.print()">
@php
    $grade = function ($score) {
        $score = (float) $score;
        if ($score >= 85) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 55) return 'C';
        return 'D';
    };
@endphp
<div class="header">
    <div>
        <div class="title">VENDOR RATING</div>
        <div class="muted">{{ strtoupper($company->company_name ?? 'MMS System') }}</div>
    </div>
    <div style="text-align:right">
        <strong>Periode:</strong> {{ $period ?: 'Semua Periode' }}<br>
        <strong>Vendor:</strong> {{ $supplier?->name ?: 'Semua Vendor' }}<br>
        <strong>Dicetak:</strong> {{ now()->format('d/m/Y H:i') }}
    </div>
</div>

<table class="table">
    <thead>
        <tr>
            <th>No</th>
            <th>Periode</th>
            <th>Vendor</th>
            <th class="text-right">Lead Time</th>
            <th class="text-right">Kualitas</th>
            <th class="text-right">Harga</th>
            <th class="text-right">Total</th>
            <th class="text-center">Grade</th>
            <th>Catatan</th>
        </tr>
    </thead>
    <tbody>
    @forelse($ratings as $rating)
        <tr>
            <td class="text-center">{{ $loop->iteration }}</td>
            <td>{{ $rating->rating_period }}</td>
            <td><strong>{{ $rating->supplier?->code ?: '-' }}</strong> - {{ $rating->supplier?->name ?: '-' }}</td>
            <td class="text-right">{{ number_format((float) $rating->lead_time_score, 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) $rating->quality_score, 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) $rating->price_score, 2, ',', '.') }}</td>
            <td class="text-right"><strong>{{ number_format((float) $rating->total_score, 2, ',', '.') }}</strong></td>
            <td class="text-center"><span class="badge">{{ $grade($rating->total_score) }}</span></td>
            <td>{{ $rating->notes ?: '-' }}</td>
        </tr>
    @empty
        <tr><td colspan="9" class="text-center muted">Belum ada data vendor rating.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="footer">Mengetahui,<br><br><br><strong>Procurement Manager</strong></div>
</body>
</html>
