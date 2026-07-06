<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Quotation - {{ $quotation->quote_number }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <style>
        body { font-family: Arial, sans-serif; color:#222; }
        .doc { max-width: 960px; margin: 24px auto; padding: 24px; }
        .table th, .table td { vertical-align: middle; }
        @media print { .no-print { display:none; } .doc { margin:0; max-width:100%; } }
    </style>
</head>
<body>
<div class="doc">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h3 class="fw-bold mb-1">QUOTATION</h3>
            <div class="text-muted">{{ $quotation->quote_number }}</div>
        </div>
        <button class="btn btn-sm btn-dark no-print" onclick="window.print()">Print</button>
    </div>
    <div class="row mb-4">
        <div class="col-7">
            <strong>Kepada:</strong><br>
            {{ $quotation->customer?->name }}<br>
            <span class="text-muted">{{ $quotation->customer?->address }}</span>
        </div>
        <div class="col-5">
            <table class="table table-sm table-borderless">
                <tr><td>Tanggal</td><td>: {{ optional($quotation->quote_date)->format('d/m/Y') }}</td></tr>
                <tr><td>Terms</td><td>: {{ $quotation->payment_terms }}</td></tr>
                <tr><td>Status</td><td>: {{ strtoupper(str_replace('_', ' ', $quotation->status)) }}</td></tr>
            </table>
        </div>
    </div>
    <table class="table table-bordered">
        <thead class="table-light"><tr><th>No</th><th>Kode</th><th>Item</th><th>Material</th><th>Qty</th><th>Unit</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr></thead>
        <tbody>
        @foreach($quotation->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->item_code_manual }}</td>
                <td>{{ $item->item_name_manual ?: $item->temp_item_name }}</td>
                <td>{{ $item->material_manual ?: $item->temp_spec }}</td>
                <td>{{ number_format((float) $item->qty, 2, ',', '.') }}</td>
                <td>{{ $item->unit_manual ?: $item->temp_uom }}</td>
                <td class="text-end">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                <td class="text-end">{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="7" class="text-end">Subtotal</td><td class="text-end">Rp {{ number_format((float) $quotation->subtotal + (float) $quotation->discount_amount, 0, ',', '.') }}</td></tr>
            <tr><td colspan="7" class="text-end">Discount</td><td class="text-end">Rp {{ number_format((float) $quotation->discount_amount, 0, ',', '.') }}</td></tr>
            <tr><td colspan="7" class="text-end">PPN ({{ rtrim(rtrim(number_format((float) $quotation->ppn_percent, 2, '.', ''), '0'), '.') }}%)</td><td class="text-end">Rp {{ number_format((float) $quotation->tax_amount, 0, ',', '.') }}</td></tr>
            <tr class="fw-bold"><td colspan="7" class="text-end">Grand Total</td><td class="text-end">Rp {{ number_format((float) $quotation->grand_total, 0, ',', '.') }}</td></tr>
        </tfoot>
    </table>
    @if($quotation->notes)<div class="mt-3"><strong>Catatan:</strong><br>{!! nl2br(e($quotation->notes)) !!}</div>@endif
    <div class="row text-center mt-5">
        <div class="col-6">Dibuat Oleh<br><br><br><strong>{{ $quotation->creator?->fullname ?? '-' }}</strong></div>
        <div class="col-6">Disetujui Oleh<br><br><br><strong>{{ $quotation->approver?->fullname ?? '-' }}</strong></div>
    </div>
</div>
</body>
</html>
