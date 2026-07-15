<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>ITR - {{ $issue->itr_number }}</title>
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
        .info-table td { padding: 3px 2px; vertical-align: top; }
        
        .section-header { font-weight: bold; font-size: 11px; margin-top: 15px; margin-bottom: 10px; text-transform: uppercase; background: #f2f2f2; padding: 6px 10px; border: 1px solid #000; }
        
        .item-table th, .item-table td, .footer-sig th, .footer-sig td { border: 1px solid #000; padding: 8px; }
        .item-table th, .footer-sig th { background: #f2f2f2; font-size: 10px; text-transform: uppercase; font-weight: bold; }
        .notes-box { border: 1px solid #000; padding: 10px; margin-bottom: 15px; min-height: 42px; background: #fafafa; }
        
        .footer-sig { table-layout: fixed; margin-top: 18px; }
        .footer-sig td { height: 90px; text-align: center; vertical-align: bottom; }
        .no-print { text-align: center; margin-bottom: 20px; }
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
<div class="box">
    <div class="no-print"><button onclick="window.print()" style="padding: 8px 16px; background-color: #333; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Cetak Bukti</button></div>
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
                    <div class="doc-title">BUKTI PENGELUARAN BARANG</div>
                    <div class="doc-subtitle">WAREHOUSE ISSUE / ITR</div>
                </div>
                <div class="doc-number">{{ $issue->itr_number }}</div>
            </div>
        </div>
    </td></tr></thead>
    <tbody><tr><td style="border:none;padding:0;">
        <div class="content">

        <table class="info-table">
            <tr>
                <td width="15%"><strong>No. ITR</strong></td>
                <td width="35%">: <strong>{{ $issue->itr_number }}</strong></td>
                <td width="15%"><strong>Tgl Keluar</strong></td>
                <td width="35%">: {{ optional($issue->itr_date)->format('d F Y') }}</td>
            </tr>
            <tr>
                <td><strong>Ref. SPK</strong></td>
                <td>: {{ $issue->spk?->spk_number }}</td>
                <td><strong>Customer</strong></td>
                <td>: {{ $issue->spk?->salesOrder?->customer?->name ?: '-' }}</td>
            </tr>
            <tr>
                <td><strong>Project</strong></td>
                <td>: {{ $issue->spk?->project_name ?: '-' }}</td>
                <td><strong>Ref. SO</strong></td>
                <td>: {{ $issue->spk?->salesOrder?->so_number ?: '-' }}</td>
            </tr>
        </table>

        <div class="section-header">Daftar Material Keluar</div>
        <table class="item-table" style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
            <thead>
                <tr>
                    <th style="width: 5%; text-align: center;">No</th>
                    <th>Nama Material</th>
                    <th style="width: 20%; text-align: center;">Kode Item</th>
                    <th style="width: 15%; text-align: center;">Kepemilikan</th>
                    <th style="width: 15%; text-align: center;">Qty Keluar</th>
                    <th style="width: 12%; text-align: center;">Satuan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($issue->items as $row)
                    <tr>
                        <td align="center">{{ $loop->iteration }}</td>
                        <td>{{ $row->item?->item_name }}</td>
                        <td align="center">{{ $row->item?->item_code }}</td>
                        <td align="center"><small>{{ $row->item?->ownership === 'customer' ? 'Consignment' : 'Internal' }}</small></td>
                        <td align="center" style="font-weight: bold;">{{ (float) $row->qty_issued + 0 }}</td>
                        <td align="center">{{ $row->item?->unit }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="notes-box"><strong>Catatan Gudang:</strong> {!! $issue->notes ? nl2br(e($issue->notes)) : '-' !!}</div>

        <table class="footer-sig">
            <thead>
                <tr>
                    <th>Diserahkan Oleh (Gudang)</th>
                    <th>Diterima Oleh (Produksi)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><div style="height:42px"></div><strong>{{ $issue->issued_by ?: '....................' }}</strong><br><small>Petugas Gudang</small></td>
                    <td><div style="height:42px"></div><strong>{{ $issue->received_by ?: '....................' }}</strong><br><small>Produksi</small></td>
                </tr>
            </tbody>
        </table>
    </div>
</td></tr></tbody>
<tfoot><tr><td style="border:none;padding:0;height:40px;">
    <!-- Footer -->
    <div class="page-footer">
        <span class="footer-comp-name">{{ strtoupper($compName ?? '-') }}</span>
        <span>{{ $compAddress ?? '-' }}</span>
    </div>
</td></tr></tfoot>
</table>
</div>
</body>
</html>
