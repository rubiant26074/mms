<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>PR - {{ $pr->pr_number }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <style>body{font-family:Arial,sans-serif;font-size:12px}.doc{max-width:900px;margin:24px auto;padding:24px}@media print{.no-print{display:none}.doc{margin:0;max-width:100%}}</style>
</head>
<body>
<div class="doc">
    <button class="btn btn-sm btn-dark no-print mb-3" onclick="window.print()">Print</button>
    <div class="d-flex justify-content-between border-bottom pb-3 mb-3"><div><h3 class="fw-bold">PURCHASE REQUEST</h3><div>FORM PERMINTAAN PEMBELIAN</div></div><div class="text-end"><strong>{{ $pr->pr_number }}</strong><br>Status: {{ strtoupper($pr->status) }}</div></div>
    <div class="row mb-3"><div class="col-6"><strong>Requester:</strong><br>{{ $pr->creator?->fullname ?: 'PPIC / Production' }}</div><div class="col-6 text-end"><strong>Tanggal Request:</strong> {{ optional($pr->pr_date)->format('d F Y') }}<br><strong>Tgl Dibutuhkan:</strong> {{ optional($pr->required_date)->format('d F Y') }}<br><strong>Keperluan:</strong> {{ $pr->notes ?: '-' }}</div></div>
    <table class="table table-bordered"><thead class="table-light"><tr><th>No</th><th>Kode Barang</th><th>Deskripsi Barang</th><th>Qty</th><th>Unit</th><th>Est. Tgl Pakai</th></tr></thead><tbody>@forelse($pr->items as $row)<tr><td>{{ $loop->iteration }}</td><td>{{ $row->item?->item_code ?: 'ITEM-'.$row->item_id }}</td><td><strong>{{ $row->item?->item_name ?: 'Item #'.$row->item_id }}</strong>@if($row->notes)<br><small><i>Ket: {{ $row->notes }}</i></small>@endif</td><td>{{ $row->qty + 0 }}</td><td>{{ $row->item?->unit ?: '-' }}</td><td>{{ optional($pr->required_date)->format('d/m/Y') }}</td></tr>@empty<tr><td colspan="6" class="text-center">Tidak ada detail item PR.</td></tr>@endforelse</tbody></table>
    <div class="row text-center mt-5"><div class="col-6">Dibuat<br><br><br><strong>{{ $pr->creator?->fullname ?: 'Staff PPIC' }}</strong></div><div class="col-6">Disetujui<br><br><br><strong>{{ $pr->approver?->fullname ?: '....................' }}</strong></div></div>
</div>
</body>
</html>
