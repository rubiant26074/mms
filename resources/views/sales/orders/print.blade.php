<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Sales Order - {{ $order->so_number }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <style>body{font-family:Arial,sans-serif}.doc{max-width:960px;margin:24px auto;padding:24px}@media print{.no-print{display:none}.doc{margin:0;max-width:100%}}</style>
</head>
<body>
<div class="doc">
    <div class="d-flex justify-content-between mb-4"><div><h3 class="fw-bold mb-1">SALES ORDER</h3><div class="text-muted">{{ $order->so_number }}</div></div><button class="btn btn-sm btn-dark no-print" onclick="window.print()">Print</button></div>
    <div class="row mb-4">
        <div class="col-7"><strong>Customer:</strong><br>{{ $order->customer?->name }}<br><span class="text-muted">{{ $order->customer?->address }}</span></div>
        <div class="col-5"><table class="table table-sm table-borderless"><tr><td>Tanggal</td><td>: {{ optional($order->so_date)->format('d/m/Y') }}</td></tr><tr><td>PO Customer</td><td>: {{ $order->cust_po_number ?: '-' }}</td></tr><tr><td>Delivery</td><td>: {{ optional($order->delivery_date)->format('d/m/Y') ?: '-' }}</td></tr><tr><td>Status</td><td>: {{ strtoupper(str_replace('_', ' ', $order->status)) }}</td></tr></table></div>
    </div>
    <table class="table table-bordered">
        <thead class="table-light"><tr><th>No</th><th>Kode</th><th>Item</th><th>Material</th><th>Qty</th><th>Unit</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr></thead>
        <tbody>@foreach($order->items as $item)<tr><td>{{ $loop->iteration }}</td><td>{{ $item->item?->item_code ?: $item->item_code_manual }}</td><td>{{ $item->item?->item_name ?: $item->item_name_manual }}</td><td>{{ $item->material_manual ?: $item->item?->description }}</td><td>{{ number_format((float) $item->qty, 2, ',', '.') }}</td><td>{{ $item->unit_manual ?: $item->item?->unit }}</td><td class="text-end">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td><td class="text-end">{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td></tr>@endforeach</tbody>
        <tfoot><tr><td colspan="7" class="text-end">Subtotal</td><td class="text-end">Rp {{ number_format((float) $order->subtotal + (float) $order->discount_amount + (float) $order->tax_amount, 0, ',', '.') }}</td></tr><tr><td colspan="7" class="text-end">Discount</td><td class="text-end">Rp {{ number_format((float) $order->discount_amount, 0, ',', '.') }}</td></tr><tr><td colspan="7" class="text-end">PPN</td><td class="text-end">Rp {{ number_format((float) $order->tax_amount, 0, ',', '.') }}</td></tr><tr class="fw-bold"><td colspan="7" class="text-end">Grand Total</td><td class="text-end">Rp {{ number_format((float) $order->grand_total, 0, ',', '.') }}</td></tr></tfoot>
    </table>
    @if($order->notes)<div class="mt-3"><strong>Catatan:</strong><br>{!! nl2br(e($order->notes)) !!}</div>@endif
    <div class="row text-center mt-5"><div class="col-6">Dibuat Oleh<br><br><br><strong>{{ $order->creator?->fullname ?? '-' }}</strong></div><div class="col-6">Disetujui Oleh<br><br><br><strong>{{ $order->approver?->fullname ?? '-' }}</strong></div></div>
</div>
</body>
</html>
