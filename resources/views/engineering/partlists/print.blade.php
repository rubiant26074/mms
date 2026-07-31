<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Partlist - {{ $spk->spk_number }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <style>body{font-family:Arial,sans-serif}.doc{max-width:1120px;margin:24px auto;padding:24px}@media print{.no-print{display:none}.doc{margin:0;max-width:100%}}</style>
</head>
<body>
<div class="doc">
    <div class="no-print text-center mb-3"><button class="btn btn-dark" onclick="window.print()">Cetak Arsip Partlist</button></div>
    <div class="d-flex justify-content-between mb-4">
        <div><h3 class="fw-bold mb-1">PART LIST</h3><div class="text-muted">{{ $spk->spk_number }}</div></div>
        <div class="text-end"><strong>SO:</strong> {{ $spk->salesOrder?->so_number ?: '-' }}<br><strong>Status:</strong> {{ strtoupper($spk->status) }}</div>
    </div>
    <table class="table table-bordered table-sm">
        <thead class="table-light"><tr><th>No</th><th>Item No</th><th>Drawing No</th><th>Part Name</th><th>Qty</th><th>Material</th><th>Thick</th><th>L</th><th>W</th><th>Process</th><th>Notes</th><th>Drawing File</th></tr></thead>
        <tbody>
        @forelse($spk->partlists as $part)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $part->item_no }}</td><td>{{ $part->drawing_no }}</td><td>{{ $part->part_name }}</td><td>{{ $part->qty + 0 }}</td><td>{{ $part->material }}</td><td>{{ $part->thickness }}</td><td>{{ $part->length + 0 }}</td><td>{{ $part->width + 0 }}</td><td>{{ $part->process }}</td><td>{{ $part->notes }}</td><td>@if($part->drawing_path)<a href="{{ asset($part->drawing_path) }}" target="_blank">Download</a>@else-@endif</td></tr>
        @empty
            <tr><td colspan="11" class="text-center py-3">Belum ada data partlist pada SPK ini.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
