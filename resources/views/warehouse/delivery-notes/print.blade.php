<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan - {{ $note->dn_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { max-width: 800px; margin: auto; border: 1px solid #ccc; padding: 20px; min-height: 96vh; display: flex; flex-direction: column; position: relative; }
        .content { position: relative; z-index: 1; flex: 1 1 auto; }
        
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .logo { max-height: 45px; object-fit: contain; }
        .doc-title-box { border: none; padding: 5px; display: inline-block; text-align: center; }
        .doc-title { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
        .doc-subtitle { font-size: 9px; letter-spacing: 1px; }
        .doc-number { font-size: 13px; font-weight: bold; margin-top: 8px; text-align: right; }
        
        .info-table, .item-table, .footer-sig { width: 100%; border-collapse: collapse; }
        .info-table { margin-bottom: 14px; }
        .info-table td { vertical-align: top; padding: 2px; }
        .item-table { margin-bottom: 20px; }
        .item-table th { background: #f2f2f2; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 8px; text-align: left; font-weight: bold; text-transform: uppercase; font-size: 10px; }
        .item-table td { border-bottom: 1px solid #eee; padding: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .notes-box { border: 1px solid #ddd; background: #fafafa; padding: 8px 10px; margin-bottom: 10px; font-size: 10px; }
        
        .footer-sig { margin-top: 12px; table-layout: fixed; }
        .footer-sig th { border: 1px solid #000; background: #f2f2f2; padding: 5px; font-size: 10px; text-transform: uppercase; }
        .footer-sig td { border: 1px solid #000; height: 90px; text-align: center; vertical-align: bottom; padding: 5px; font-size: 10px; }
        .sig-name { display: block; font-weight: bold; text-decoration: underline; }
        .sig-image { max-height: 55px; max-width: 140px; display: block; margin: 0 auto 5px auto; object-fit: contain; }
        
        .page-footer { margin-top: auto; text-align: center; border-top: 1px solid #ccc; padding-top: 10px; }
        .footer-comp-name { font-size: 14px; font-weight: bold; display: block; }
        @media print {
            .no-print { display: none; }
            .box { border: none; }
            body { padding: 20px; }
            .page-footer { position: fixed; bottom: 20px; left: 20px; right: 20px; background: #fff; }
            tr { page-break-inside: avoid; break-inside: avoid; }
            .summary-table { page-break-inside: avoid; break-inside: avoid; }
            .footer-sig { page-break-inside: avoid; break-inside: avoid; }
            .notes-box { page-break-inside: avoid; break-inside: avoid; }
        }
    </style>
</head>
<body onload="window.print()">
@php
    $compLogo = is_array($company) ? ($company['logo_path'] ?? null) : ($company->logo_path ?? null);
    $compName = is_array($company) ? ($company['company_name'] ?? null) : ($company->company_name ?? null);
    $compAddress = is_array($company) ? ($company['address'] ?? null) : ($company->address ?? null);
@endphp
<div style="text-align: center; margin-bottom: 20px;" class="no-print">
    <button onclick="window.print()" style="padding: 8px 16px; background-color: #333; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Cetak Surat Jalan</button>
</div>

<div class="box">
    <table style="width:100%;border-collapse:collapse;border:none;">
    <thead><tr><td style="border:none;padding:0;">
        <!-- Header -->
        <div class="header">
            <div>
                @if($compLogo)
                    <img src="{{ asset($compLogo) }}" class="logo" alt="Logo">
                @endif
            </div>
            <div style="text-align:right">
                <div class="doc-title-box">
                    <div class="doc-title">SURAT JALAN</div>
                    <div class="doc-subtitle">DELIVERY NOTE</div>
                </div>
                <div class="doc-number">{{ $note->dn_number }}</div>
            </div>
        </div>
    </td></tr></thead>
    <tbody><tr><td style="border:none;padding:0;">
        <div class="content">

        <table class="info-table">
            <tr>
                <td width="55%">
                    <strong>Kepada Yth:</strong><br>
                    <strong style="font-size: 12px; color:#000;">{{ strtoupper($note->salesOrder?->customer?->name ?? '-') }}</strong><br>
                    UP: {{ $note->salesOrder?->customer?->pic ?? '-' }}<br>
                    {!! nl2br(e($note->salesOrder?->customer?->address ?? '-')) !!}<br>
                    Telp: {{ $note->salesOrder?->customer?->phone ?? '-' }}<br><br>
                    <strong>Project:</strong> {{ $spkSummary['projects'] }}
                </td>
                <td width="45%" style="text-align: right;">
                    <table align="right" style="border-collapse:collapse;">
                        <tr><td style="text-align:left; padding:2px 0;"><strong>Tanggal Kirim</strong></td><td style="padding:2px 5px;">:</td><td align="right" style="padding:2px 0;">{{ optional($note->dn_date)->format('d F Y') }}</td></tr>
                        <tr><td style="text-align:left; padding:2px 0;"><strong>No. SPK</strong></td><td style="padding:2px 5px;">:</td><td align="right" style="padding:2px 0;">{{ $spkSummary['numbers'] }}</td></tr>
                        <tr><td style="text-align:left; padding:2px 0;"><strong>No. SO</strong></td><td style="padding:2px 5px;">:</td><td align="right" style="padding:2px 0;">{{ $note->salesOrder?->so_number ?? '-' }}</td></tr>
                        <tr><td style="text-align:left; padding:2px 0;"><strong>No. PO Cust</strong></td><td style="padding:2px 5px;">:</td><td align="right" style="padding:2px 0;">{{ $note->salesOrder?->cust_po_number ?: '-' }}</td></tr>
                        <tr><td style="text-align:left; padding:2px 0;"><strong>Kendaraan</strong></td><td style="padding:2px 5px;">:</td><td align="right" style="padding:2px 0;">{{ $note->vehicle_number ?: '-' }}</td></tr>
                        <tr><td style="text-align:left; padding:2px 0;"><strong>Pengemudi</strong></td><td style="padding:2px 5px;">:</td><td align="right" style="padding:2px 0;">{{ $note->driver_name ?: '-' }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="item-table">
            <thead>
                <tr>
                    <th width="5%" class="text-center">No</th>
                    <th>Nama Barang / Deskripsi</th>
                    <th width="15%" class="text-center">Qty Dikirim</th>
                    <th width="15%" class="text-center">Satuan</th>
                    <th width="20%">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($note->items as $row)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td><strong>{{ $row->item?->item_name }}</strong><br><small>{{ $row->item?->item_code }}</small></td>
                        <td class="text-center" style="font-weight: bold;">{{ $row->qty_sent + 0 }}</td>
                        <td class="text-center">{{ $row->item?->unit }}</td>
                        <td>{{ $row->notes ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($note->notes)
            <div class="notes-box"><strong>Catatan:</strong><br>{!! nl2br(e($note->notes)) !!}</div>
        @endif
    </div>

    <table class="footer-sig">
        <thead>
            <tr>
                <th>Dibuat Oleh</th>
                <th>Gudang</th>
                <th>Diterima Oleh</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    @if($note->creator?->signature_path)
                        <img src="{{ asset($note->creator->signature_path) }}" class="sig-image" alt="Signature">
                    @else
                        <div style="height: 55px;"></div>
                    @endif
                    <span class="sig-name">{{ $note->creator?->fullname ?? '-' }}</span>
                </td>
                <td>
                    @if($note->approver?->signature_path)
                        <img src="{{ asset($note->approver->signature_path) }}" class="sig-image" alt="Signature">
                    @elseif($note->status !== 'draft' && $note->creator?->signature_path)
                        <img src="{{ asset($note->creator->signature_path) }}" class="sig-image" alt="Signature">
                    @else
                        <div style="height: 55px;"></div>
                    @endif
                    <span class="sig-name">{{ $note->approver?->fullname ?? $note->creator?->fullname ?? '-' }}</span>
                </td>
                <td>
                    @if($note->customer_signature_path)
                        <img src="{{ asset($note->customer_signature_path) }}" class="sig-image" alt="Signature">
                    @else
                        <div style="height: 55px;"></div>
                    @endif
                    <span class="sig-name">{{ $note->received_by_name ?? '....................' }}</span>
                </td>
            </tr>
        </tbody>
    </table>
    </div>
</td></tr></tbody>
<tfoot><tr><td style="border:none;padding:0;height:40px;">
    <div class="page-footer">
        <span class="footer-comp-name">{{ strtoupper($compName ?? '-') }}</span>
        <span>{{ $compAddress ?? '-' }}</span>
    </div>
</td></tr></tfoot>
</table>
</div>
</body>
</html>
