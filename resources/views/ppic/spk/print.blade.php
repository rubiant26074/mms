<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>SPK - {{ $spk->spk_number }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <style>body{font-family:Arial,sans-serif;font-size:12px}.doc{max-width:900px;margin:24px auto;padding:24px}@media print{.no-print{display:none}.doc{margin:0;max-width:100%}}</style>
</head>
<body>
<div class="doc">
    <button class="btn btn-sm btn-dark no-print mb-3" onclick="window.print()">Print</button>
    <div class="d-flex justify-content-between border-bottom pb-3 mb-3"><div><h3 class="fw-bold">SURAT PERINTAH KERJA</h3><div>PPIC</div></div><div class="text-end"><strong>{{ $spk->spk_number }}</strong><br>SO: {{ $spk->salesOrder?->so_number }}</div></div>
    <div class="row mb-3"><div class="col-6"><strong>Customer:</strong><br>{{ $spk->salesOrder?->customer?->name }}<br>{{ $spk->salesOrder?->customer?->address }}</div><div class="col-6 text-end"><strong>Tgl Terbit:</strong> {{ optional($spk->spk_date)->format('d F Y') }}<br><strong>PO Customer:</strong> {{ $spk->salesOrder?->cust_po_number ?: '-' }}<br><strong>Deadline:</strong> {{ optional($spk->deadline_date)->format('d F Y') }}</div></div>
    <h6 class="fw-bold bg-light border p-2">1. Kebutuhan Material</h6>
    <table class="table table-bordered table-sm"><thead class="table-light"><tr><th>No</th><th>Material</th><th>Kode</th><th>Qty</th><th>Unit</th><th>Cek</th></tr></thead><tbody>@forelse($spk->materials as $m)<tr><td>{{ $loop->iteration }}</td><td>{{ $m->item?->item_name }}</td><td>{{ $m->item?->item_code }}</td><td>{{ $m->qty_required + 0 }}</td><td>{{ $m->item?->unit }}</td><td></td></tr>@empty<tr><td colspan="6" class="text-center">Tidak ada material.</td></tr>@endforelse</tbody></table>
    <h6 class="fw-bold bg-light border p-2">2. Route Proses</h6>
    <p>{{ $spk->required_processes ?: '-' }}</p>
    <h6 class="fw-bold bg-light border p-2">3. Item Barang Jadi</h6>
    <table class="table table-bordered table-sm"><thead class="table-light"><tr><th>No</th><th>Kode</th><th>Nama Barang</th><th>Material</th><th>Qty</th><th>Unit</th></tr></thead><tbody>@foreach($spk->salesOrder?->items ?? [] as $item)<tr><td>{{ $loop->iteration }}</td><td>{{ $item->item?->item_code ?: $item->item_code_manual }}</td><td>{{ $item->item?->item_name ?: $item->item_name_manual }}</td><td>{{ $item->material_manual ?: $item->item?->description }}</td><td>{{ $item->qty + 0 }}</td><td>{{ $item->unit_manual ?: $item->item?->unit }}</td></tr>@endforeach</tbody></table>
    <div class="mt-3"><strong>Catatan Produksi:</strong><br>{!! nl2br(e($spk->notes ?: '-')) !!}</div>
</div>
</body>
</html>
