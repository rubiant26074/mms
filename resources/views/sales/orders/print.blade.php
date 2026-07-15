<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Sales Order - {{ $order->so_number }}</title>
    <style>
        @page{size:A4 portrait;margin:0}
        body{font-family:Arial,sans-serif;font-size:11px;margin:0;padding:20px;color:#000}
        .box{max-width:800px;margin:auto;min-height:96vh;display:flex;flex-direction:column;position:relative}
        .watermark{position:absolute;top:45%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);font-size:82px;font-weight:bold;color:rgba(200,0,0,.13);border:4px solid rgba(200,0,0,.13);padding:8px 28px;z-index:9999;pointer-events:none}
        .content{position:relative;z-index:1;flex:1 1 auto}
        .header{border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:15px;display:flex;justify-content:space-between;align-items:flex-start}
        .logo{max-height:45px;object-fit:contain}
        .doc-title-box{border:none;padding:5px;display:inline-block;text-align:center}
        .doc-title{font-size:18px;font-weight:bold;letter-spacing:1px}
        .doc-subtitle{font-size:9px;letter-spacing:1px}
        .doc-number{font-size:13px;font-weight:bold;margin-top:8px;text-align:right}
        .info-table,.data-table,.summary-table,.footer-sig{border-collapse:collapse}
        .info-table{width:100%;margin-bottom:15px}
        .info-table td{vertical-align:top;padding:2px}
        .data-table{width:100%;margin-bottom:12px}
        .data-table th,.data-table td,.summary-table td,.footer-sig th,.footer-sig td{border:1px solid #000;padding:8px}
        .data-table th,.footer-sig th{background:#f2f2f2;text-transform:uppercase;font-size:10px}
        .text-center{text-align:center}.text-right{text-align:right}
        .summary-table{width:42%;margin-left:auto;margin-bottom:15px}
        .summary-table .lbl{background:#f9f9f9;font-weight:bold}
        .summary-table .grand td{font-weight:bold;border-top:2px solid #000}
        .notes-box{border:1px solid #000;background:#fafafa;padding:8px;margin-bottom:20px}
        .footer-sig{width:100%;table-layout:fixed;margin-top:10px}
        .footer-sig td{height:92px;text-align:center;vertical-align:bottom}
        .sig-name{font-weight:bold;text-decoration:underline;display:block}
        .sig-image{max-height:50px;max-width:140px;display:block;margin:0 auto 5px auto;object-fit:contain}
        .page-footer{margin-top:auto;text-align:center;border-top:1px solid #ccc;padding-top:10px}
        .footer-comp-name{font-size:14px;font-weight:bold;display:block}
        .no-print{position:fixed;top:12px;right:12px;background:#111;color:#fff;border:0;border-radius:4px;padding:7px 12px;cursor:pointer}
        @media print{
            body{padding:20px}
            .no-print{display:none}
            .page-footer{position:fixed;bottom:20px;left:20px;right:20px;background:#fff}
        }
    </style>
</head>
<body onload="window.print()">
@php
    $totalBruto = $order->items->sum(fn($row) => (float) $row->subtotal);
    $confirmed = in_array($order->status, ['confirmed','in_production','delivered','completed'], true);
    $fulfillment = (($order->fulfillment_source ?? 'spk') === 'fg_stock') ? 'FG Stock' : 'Produksi / SPK';
@endphp
<button class="no-print" onclick="window.print()">Print</button>
<div class="box">
    @if(! $confirmed)<div class="watermark">DRAFT</div>@endif
    <table style="width:100%;border-collapse:collapse;border:none;">
    <thead><tr><td style="border:none;padding:0;">
        <div class="header">
            <div>
                @if($company->logo_path)
                    <img src="{{ asset($company->logo_path) }}" class="logo" alt="Logo">
                @endif
            </div>
            <div style="text-align:right">
                <div class="doc-title-box">
                    <div class="doc-title">SALES ORDER</div>
                    <div class="doc-subtitle">FORM ORDER PENJUALAN</div>
                </div>
                <div class="doc-number">{{ $order->so_number }}</div>
            </div>
        </div>
    </td></tr></thead>
    <tbody><tr><td style="border:none;padding:0;">
        <div class="content">

        <table class="info-table">
            <tr>
                <td width="55%">
                    <strong>Customer:</strong><br>
                    <strong>{{ strtoupper($order->customer?->name ?: '-') }}</strong><br>
                    Up : {{ $order->customer?->pic ?: '-' }}<br>
                    {!! nl2br(e($order->customer?->address ?: '-')) !!}<br>
                    Telp: {{ $order->customer?->phone ?: '-' }}
                </td>
                <td width="45%" align="right">
                    <strong>Tgl SO :</strong> {{ optional($order->so_date)->format('d F Y') ?: '-' }}<br>
                    <strong>PO Customer :</strong> {{ $order->cust_po_number ?: '-' }}<br>
                    <strong>Est. Delivery :</strong> {{ optional($order->delivery_date)->format('d F Y') ?: '-' }}<br>
                    <strong>Terms :</strong> {{ $order->payment_terms ?: '-' }}<br>
                    <strong>Pemenuhan :</strong> {{ $fulfillment }}<br>
                    <strong>Status :</strong> {{ strtoupper(str_replace('_', ' ', $order->status)) }}
                </td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Nama Barang</th>
                    <th>Material</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Harga Satuan</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->items as $item)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td class="text-center">{{ $item->item?->item_code ?: $item->item_code_manual ?: '-' }}</td>
                        <td><strong>{{ $item->item?->item_name ?: $item->item_name_manual ?: '-' }}</strong></td>
                        <td>{{ $item->material_manual ?: $item->item?->description ?: '-' }}</td>
                        <td class="text-center">{{ number_format((float) $item->qty, 2, ',', '.') }}</td>
                        <td class="text-center">{{ $item->unit_manual ?: $item->item?->unit ?: '-' }}</td>
                        <td class="text-right">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center">Tidak ada detail item Sales Order.</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="summary-table">
            <tr><td class="lbl">Subtotal</td><td class="text-right">Rp {{ number_format($totalBruto, 0, ',', '.') }}</td></tr>
            <tr><td class="lbl">Discount</td><td class="text-right">Rp {{ number_format((float) $order->discount_amount, 0, ',', '.') }}</td></tr>
            <tr><td class="lbl">PPN ({{ $order->ppn_percent + 0 }}%)</td><td class="text-right">Rp {{ number_format((float) $order->tax_amount, 0, ',', '.') }}</td></tr>
            <tr class="grand"><td>GRAND TOTAL</td><td class="text-right">Rp {{ number_format((float) $order->grand_total, 0, ',', '.') }}</td></tr>
        </table>

        @if($order->notes)
            <div class="notes-box"><strong>Catatan SO:</strong><br>{!! nl2br(e($order->notes)) !!}</div>
        @endif

        <table class="footer-sig">
            <thead><tr><th>Dibuat Oleh</th><th>Disetujui Oleh</th><th>Diterima Customer</th></tr></thead>
            <tbody>
                <tr>
                    <td>
                        @if($order->creator?->signature_path)
                            <img src="{{ asset($order->creator->signature_path) }}" class="sig-image" alt="Signature">
                        @else
                            <div style="height:55px"></div>
                        @endif
                        <span class="sig-name">{{ $order->creator?->fullname ?: 'Sales' }}</span>
                        <span>Tgl: {{ optional($order->so_date)->format('d/m/Y') ?: '-' }}</span>
                    </td>
                    <td>
                        @if($confirmed && $order->approver?->signature_path)
                            <img src="{{ asset($order->approver->signature_path) }}" class="sig-image" alt="Signature">
                        @else
                            <div style="height:55px"></div>
                        @endif
                        <span class="sig-name">{{ $confirmed ? ($order->approver?->fullname ?: '....................') : '....................' }}</span>
                        <span>Tgl: {{ $confirmed && $order->approved_at ? $order->approved_at->format('d/m/Y') : '/ /' }}</span>
                    </td>
                    <td>
                        <div style="height:55px"></div>
                        <span class="sig-name">{{ $order->customer?->name ?: 'Customer' }}</span>
                        <span>(Tanda Tangan & Stempel)</span>
                    </td>
                </tr>
            </tbody>
    </div>
</td></tr></tbody>
<tfoot><tr><td style="border:none;padding:0;height:40px;">
    <div class="page-footer"><span class="footer-comp-name">{{ strtoupper($company->company_name ?? '-') }}</span><span>{{ $company->address ?? '-' }}</span></div>
</td></tr></tfoot>
</table>
</div>
</body>
</html>
