<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>PR - {{ $pr->pr_number }}</title>
    <style>
        @page{size:A4 portrait;margin:0}
        body{font-family:Arial,sans-serif;font-size:11px;margin:0;padding:20px;color:#000}
        .box{max-width:800px;margin:auto;min-height:96vh;display:flex;flex-direction:column;position:relative}
        .watermark{position:absolute;top:45%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);font-size:90px;font-weight:bold;color:rgba(200,0,0,.14);border:4px solid rgba(200,0,0,.14);padding:8px 30px;z-index:9999;pointer-events:none}
        .header{border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:15px;display:flex;justify-content:space-between}
        .logo{max-height:31px;object-fit:contain}
        .doc-title-box{border:none;padding:5px;display:inline-block;text-align:center}
        .doc-title{font-size:18px;font-weight:bold;letter-spacing:1px}
        .info-table,.data-table,.summary-table,.footer-sig{border-collapse:collapse}
        .info-table{width:100%;margin-bottom:15px}
        .info-table td{vertical-align:top;padding:2px}
        .data-table{width:100%;margin-bottom:12px}
        .data-table th,.data-table td,.summary-table td,.footer-sig th,.footer-sig td{border:1px solid #000;padding:8px}
        .data-table th,.footer-sig th{background:#f2f2f2;text-transform:uppercase;font-size:10px}
        .text-center{text-align:center}.text-right{text-align:right}
        .footer-sig{width:100%;table-layout:fixed;margin-top:20px}
        .footer-sig td{height:92px;text-align:center;vertical-align:bottom}
        .sig-name{font-weight:bold;text-decoration:underline;display:block}
        .page-footer{margin-top:auto;text-align:center;border-top:1px solid #ccc;padding-top:10px}
        .footer-comp-name{font-size:14px;font-weight:bold;display:block}
        @media print{
            .page-footer{position:relative} 
            .page-footer::after{content:"Page " counter(page);position:absolute;right:10px;bottom:10px;font-size:9px;color:#555}
            .no-print{display:none}
            .page-footer{position:fixed;bottom:20px;left:20px;right:20px;background:#fff}
            tr{page-break-inside:avoid;break-inside:avoid}
            .footer-sig{page-break-inside:avoid;break-inside:avoid}
        }
        .no-print-btn {
            position: fixed;
            top: 12px;
            right: 12px;
            background: #111;
            color: #fff;
            border: 0;
            border-radius: 4px;
            padding: 7px 12px;
            cursor: pointer;
            z-index: 9999;
        }
        @media print {
            .no-print-btn {
                display: none;
            }
        }
    </style>
</head>
<body onload="window.print()">
@php
    $company = app(App\Services\MmsContext::class)->company();
    $approved = in_array($pr->status, ['approved', 'completed'], true);
@endphp
<button class="no-print-btn" onclick="window.print()">Print</button>
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
                    <div class="doc-title">PURCHASE REQUEST</div>
                    <div style="font-size:9px;letter-spacing:1px">FORM PERMINTAAN PEMBELIAN</div>
                </div>
                <div style="font-size:13px;font-weight:bold;margin-top:8px">{{ $pr->pr_number }}</div>
            </div>
        </div>
    </td></tr></thead>
    <tbody><tr><td style="border:none;padding:0;">
        <div class="content" style="padding-top: 10px;">
            <table class="info-table">
                <tr>
                    <td width="55%">
                        <strong>Requester:</strong><br>
                        <strong>{{ strtoupper($pr->creator?->fullname ?: 'Staff PPIC') }}</strong><br>
                        Departemen: PPIC / Production<br>
                        <strong>Keperluan / Catatan:</strong><br>
                        {!! nl2br(e($pr->notes ?: '-')) !!}
                    </td>
                    <td width="45%" align="right">
                        <strong>Tanggal Request :</strong> {{ optional($pr->pr_date)->format('d F Y') ?: '-' }}<br>
                        <strong>Tgl Dibutuhkan :</strong> {{ optional($pr->required_date)->format('d F Y') ?: '-' }}<br>
                        <strong>Status :</strong> {{ strtoupper(str_replace('_', ' ', $pr->status)) }}
                    </td>
                </tr>
            </table>

            <table class="data-table">
                <thead>
                    <tr>
                        <th class="text-center" width="40">No</th>
                        <th>Kode Barang</th>
                        <th>Deskripsi Barang</th>
                        <th class="text-center" width="70">Qty</th>
                        <th class="text-center" width="60">Unit</th>
                        <th class="text-center" width="100">Est. Tgl Pakai</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pr->items as $row)
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td class="text-center">{{ $row->item?->item_code ?: 'ITEM-'.$row->item_id }}</td>
                            <td>
                                <strong>{{ $row->item?->item_name ?: 'Item #'.$row->item_id }}</strong>
                                @if($row->notes)
                                    <br><small><i>Ket: {{ $row->notes }}</i></small>
                                @endif
                            </td>
                            <td class="text-center"><strong>{{ $row->qty + 0 }}</strong></td>
                            <td class="text-center">{{ $row->item?->unit ?: '-' }}</td>
                            <td class="text-center">{{ optional($pr->required_date)->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">Tidak ada detail item PR.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <table class="footer-sig">
                <thead>
                    <tr>
                        <th>Dibuat Oleh</th>
                        <th>Disetujui Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            @if($pr->creator?->signature_path)
                                <img src="{{ asset($pr->creator->signature_path) }}" style="max-height:50px;max-width:140px;display:block;margin:0 auto 5px auto;object-fit:contain" alt="Signature">
                            @else
                                <div style="height:55px"></div>
                            @endif
                            <span class="sig-name">{{ $pr->creator?->fullname ?: 'Staff PPIC' }}</span>
                            <span>Tgl: {{ optional($pr->pr_date)->format('d/m/Y') ?: '-' }}</span>
                        </td>
                        <td>
                            @if($approved && $pr->approver?->signature_path)
                                <img src="{{ asset($pr->approver->signature_path) }}" style="max-height:50px;max-width:140px;display:block;margin:0 auto 5px auto;object-fit:contain" alt="Signature">
                            @else
                                <div style="height:55px"></div>
                            @endif
                            <span class="sig-name">{{ $approved ? ($pr->approver?->fullname ?: '....................') : '....................' }}</span>
                            <span>Tgl: {{ $approved && $pr->updated_at ? $pr->updated_at->format('d/m/Y') : '/ /' }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </td></tr></tbody>
    <tfoot><tr><td style="border:none;padding:0;height:40px;">
        <div class="page-footer">
            <span class="footer-comp-name">{{ strtoupper($company->company_name ?? '-') }}</span>
            <span>{{ $company->address ?? '-' }}</span>
            <div style="margin-top: 5px;">
                @php
                    $phoneVal = is_array($company) ? ($company['phone'] ?? null) : ($company->phone ?? null);
                @endphp
                @if($phoneVal)
                    <span style="display: inline-flex; align-items: center; margin-right: 15px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72, 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        {{ $phoneVal }}
                    </span>
                @endif
            </div>
        </div>
    </td></tr></tfoot>
    </table>
</div>
</body>
</html>
