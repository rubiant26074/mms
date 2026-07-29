<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Quotation - {{ $quotation->quote_number }}</title>
    <style>
        @page{size:A4 portrait;margin:0}
        body{font-family:Arial,sans-serif;font-size:11px;margin:0;padding:20px;color:#000}
        .box{max-width:800px;margin:auto;min-height:96vh;display:flex;flex-direction:column;position:relative}
        .watermark{position:absolute;top:45%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);font-size:86px;font-weight:bold;color:rgba(200,0,0,.13);border:4px solid rgba(200,0,0,.13);padding:8px 28px;z-index:9999;pointer-events:none}
        .content{position:relative;z-index:1;flex:1 1 auto}
        .header{border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:15px;display:flex;justify-content:space-between;align-items:flex-start}
        .logo{max-height:31px;object-fit:contain}
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
            tr{page-break-inside:avoid;break-inside:avoid}
            .summary-table{page-break-inside:avoid;break-inside:avoid}
            .footer-sig{page-break-inside:avoid;break-inside:avoid}
            .notes-box{page-break-inside:avoid;break-inside:avoid}
        }
    </style>
</head>
<body onload="window.print()">
@php
    $totalBruto = $quotation->items->sum(fn($row) => (float) $row->subtotal);
    $approved = in_array($quotation->status, ['approved','sent','won'], true);
@endphp
<button class="no-print" onclick="window.print()">Print</button>
<div class="box">
    @if(! $approved)<div class="watermark">DRAFT</div>@endif
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
                    <div class="doc-title">QUOTATION</div>
                    <div class="doc-subtitle">FORM PENAWARAN HARGA</div>
                </div>
                <div class="doc-number">{{ $quotation->quote_number }}</div>
            </div>
        </div>
    </td></tr></thead>
    <tbody><tr><td style="border:none;padding:0;">
        <div class="content">

        <table class="info-table">
            <tr>
                <td width="55%">
                    <strong>Kepada:</strong><br>
                    <strong>{{ strtoupper($quotation->customer?->name ?: '-') }}</strong><br>
                    Up : {{ $quotation->customer?->pic ?: '-' }}<br>
                    {!! nl2br(e($quotation->customer?->address ?: '-')) !!}<br>
                    Telp: {{ $quotation->customer?->phone ?: '-' }}
                </td>
                <td width="45%" align="right">
                    <strong>Tanggal :</strong> {{ optional($quotation->quote_date)->format('d F Y') ?: '-' }}<br>
                    <strong>Terms :</strong> {{ $quotation->payment_terms ?: '-' }}<br>
                    <strong>Validity :</strong> {{ $quotation->validity ?: '-' }}<br>
                    <strong>Status :</strong> {{ strtoupper(str_replace('_', ' ', $quotation->status)) }}
                </td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Nama Item</th>
                    <th>Material/Spec</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Harga Satuan</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($quotation->items as $item)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td class="text-center">{{ $item->item_code_manual ?: '-' }}</td>
                        <td><strong>{{ $item->item_name_manual ?: $item->temp_item_name ?: '-' }}</strong></td>
                        <td>{{ $item->material_manual ?: $item->temp_spec ?: '-' }}</td>
                        <td class="text-center">{{ number_format((float) $item->qty, 0, ',', '.') }}</td>
                        <td class="text-center">{{ $item->unit_manual ?: $item->temp_uom ?: '-' }}</td>
                        <td class="text-right">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center">Tidak ada detail item quotation.</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="summary-table">
            <tr><td class="lbl">Subtotal</td><td class="text-right">Rp {{ number_format($totalBruto, 0, ',', '.') }}</td></tr>
            <tr><td class="lbl">{{ $quotation->discount_type === 'percent' ? 'Discount (' . ($quotation->discount_value + 0) . '%)' : 'Discount' }}</td><td class="text-right">Rp {{ number_format((float) $quotation->discount_amount, 0, ',', '.') }}</td></tr>
            <tr><td class="lbl">PPN ({{ $quotation->ppn_percent + 0 }}%)</td><td class="text-right">Rp {{ number_format((float) $quotation->tax_amount, 0, ',', '.') }}</td></tr>
            <tr class="grand"><td>GRAND TOTAL</td><td class="text-right">Rp {{ number_format((float) $quotation->grand_total, 0, ',', '.') }}</td></tr>
        </table>

        @if($quotation->notes)
            <div class="notes-box"><strong>Catatan:</strong><br>{!! nl2br(e($quotation->notes)) !!}</div>
        @endif

        <table class="footer-sig">
            <thead><tr><th>Dibuat Oleh</th><th>Disetujui Oleh</th><th>Diterima Customer</th></tr></thead>
            <tbody>
                <tr>
                    <td>
                        @if($quotation->creator?->signature_path)
                            <img src="{{ asset($quotation->creator->signature_path) }}" class="sig-image" alt="Signature">
                        @else
                            <div style="height:55px"></div>
                        @endif
                        <span class="sig-name">{{ $quotation->creator?->fullname ?: 'Sales' }}</span>
                        <span>Tgl: {{ optional($quotation->quote_date)->format('d/m/Y') ?: '-' }}</span>
                    </td>
                    <td>
                        @if($approved && $quotation->approver?->signature_path)
                            <img src="{{ asset($quotation->approver->signature_path) }}" class="sig-image" alt="Signature">
                        @else
                            <div style="height:55px"></div>
                        @endif
                        <span class="sig-name">{{ $approved ? ($quotation->approver?->fullname ?: '....................') : '....................' }}</span>
                        <span>Tgl: {{ $approved && $quotation->updated_at ? $quotation->updated_at->format('d/m/Y') : '/ /' }}</span>
                    </td>
                    <td>
                        <div style="height:55px"></div>
                        <span class="sig-name">{{ $quotation->customer?->name ?: 'Customer' }}</span>
                        <span>(Tanda Tangan & Stempel)</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</td></tr></tbody>
<tfoot><tr><td style="border:none;padding:0;height:45px;">
    <div class="page-footer">
        <span class="footer-comp-name">{{ strtoupper($company->company_name ?? '-') }}</span>
        <span>{{ $company->address ?? '-' }}</span>
        <div style="margin-top: 5px;">
            @if($company->phone)
                <span style="display: inline-flex; align-items: center; margin-right: 15px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    {{ $company->phone }}
                </span>
            @endif
            @if(!empty($customerSalesPhone))
                <span style="display: inline-flex; align-items: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; color: #25D366; margin-right: 3.5px;"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.457L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.625 1.451 5.436-.002 9.852-4.419 9.855-9.858.002-2.634-1.02-5.11-2.881-6.974A9.784 9.784 0 0 0 12.008 1.94c-5.439 0-9.856 4.417-9.859 9.858-.001 1.76.49 3.473 1.42 4.966l-.995 3.63 3.733-.978c1.452.793 2.924 1.188 4.333 1.188zm12.355-6.737c-.302-.152-1.791-.883-2.073-.984-.282-.102-.489-.153-.694.153-.205.305-.794.996-.973 1.2-.18.204-.36.229-.663.077-1.128-.565-2.128-1.045-2.92-2.404-.15-.258-.15-.418-.001-.568.136-.134.302-.354.453-.531.152-.178.202-.303.303-.506.101-.202.051-.379-.025-.531-.076-.152-.693-1.67-.95-2.285-.25-.601-.523-.518-.718-.528-.186-.01-.399-.01-.612-.01-.213 0-.56.08-.853.401-.293.32-.1.121-.1.121s-.11.236-.264.498c-.144.248-.48.918-.737 1.442-.258.525-.494 1.01-.652 1.332-.239.489-.356.73-.393.8-.073.134-.146.269-.22.404-.393.722-.59 1.545-.588 2.385.006 2.548 1.833 4.89 2.088 5.23.256.34 3.522 5.378 8.532 7.545 1.192.515 2.122.822 2.848 1.053 1.198.38 2.29.327 3.153.198.96-.144 2.952-1.206 3.364-2.37.412-1.164.412-2.164.29-2.37-.123-.207-.453-.356-.755-.508z"/></svg>
                    {{ $customerSalesPhone }}
                </span>
            @endif
        </div>
    </div>
</td></tr></tfoot>
</table>
</div>
</body>
</html>
