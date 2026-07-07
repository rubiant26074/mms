<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>ITR - {{ $issue->itr_number }}</title>
    <style>
        @page{size:A4 portrait;margin:0}body{font-family:Arial,sans-serif;font-size:11px;margin:0;padding:20px 20px 40px;color:#000}.box{border:1px solid #ccc;padding:20px;max-width:800px;margin:auto;min-height:96vh;display:flex;flex-direction:column}.header{border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:15px;display:flex;justify-content:space-between}.doc-title-box{border:1.5px solid #000;padding:5px;display:inline-block;text-align:center;min-width:230px}.doc-title{font-size:16px;font-weight:bold;letter-spacing:1px}.info-table,.item-table,.footer-sig{width:100%;border-collapse:collapse;margin-bottom:12px}.info-table td{padding:3px 2px}.section-header{font-weight:bold;font-size:11px;margin-bottom:5px;text-transform:uppercase;background:#f8f9fa;padding:4px;border:1px solid #ccc}.item-table th,.item-table td,.footer-sig th,.footer-sig td{border:1px solid #000;padding:5px}.item-table th,.footer-sig th{background:#f2f2f2;font-size:10px}.notes-box{border:1px solid #000;padding:6px;margin-bottom:12px;min-height:42px}.footer-sig{table-layout:fixed;margin-top:18px}.footer-sig td{height:95px;text-align:center;vertical-align:bottom}.no-print{text-align:center;margin-bottom:10px}@media print{.no-print{display:none}.box{border:none}}
    </style>
</head>
<body onload="window.print()">
<div class="box">
    <div class="no-print"><button onclick="window.print()">Cetak Bukti</button></div>
    <div class="header">
        <div>@if($company->logo_path)<img src="{{ asset($company->logo_path) }}" style="max-height:50px" alt="Logo">@endif</div>
        <div style="text-align:right"><div class="doc-title-box"><div class="doc-title">BUKTI PENGELUARAN BARANG</div><div style="font-size:10px">WAREHOUSE ISSUE / ITR</div></div><div style="font-size:13px;font-weight:bold;margin-top:8px">{{ $issue->itr_number }}</div></div>
    </div>
    <table class="info-table">
        <tr><td width="15%"><strong>No. ITR</strong></td><td width="35%">: <strong>{{ $issue->itr_number }}</strong></td><td width="15%"><strong>Tgl Keluar</strong></td><td width="35%">: {{ optional($issue->itr_date)->format('d/m/Y') }}</td></tr>
        <tr><td><strong>Ref. SPK</strong></td><td>: {{ $issue->spk?->spk_number }}</td><td><strong>Customer</strong></td><td>: {{ $issue->spk?->salesOrder?->customer?->name ?: '-' }}</td></tr>
        <tr><td><strong>Project</strong></td><td>: {{ $issue->spk?->project_name ?: '-' }}</td><td><strong>Ref. SO</strong></td><td>: {{ $issue->spk?->sales_order_id ? '#'.$issue->spk->sales_order_id : '-' }}</td></tr>
    </table>
    <div class="section-header">Daftar Material Keluar</div>
    <table class="item-table"><thead><tr><th>No</th><th>Nama Material</th><th>Kode Item</th><th>Kepemilikan</th><th>Qty Keluar</th><th>Satuan</th></tr></thead><tbody>@foreach($issue->items as $row)<tr><td align="center">{{ $loop->iteration }}</td><td>{{ $row->item?->item_name }}</td><td align="center">{{ $row->item?->item_code }}</td><td align="center"><small>{{ $row->item?->ownership === 'customer' ? 'Consignment' : 'Internal' }}</small></td><td align="center"><strong>{{ (float) $row->qty_issued + 0 }}</strong></td><td align="center">{{ $row->item?->unit }}</td></tr>@endforeach</tbody></table>
    <div class="notes-box"><strong>Catatan Gudang:</strong> {!! $issue->notes ? nl2br(e($issue->notes)) : '-' !!}</div>
    <table class="footer-sig"><thead><tr><th>Diserahkan Oleh (Gudang)</th><th>Diterima Oleh (Produksi)</th></tr></thead><tbody><tr><td><div style="height:42px"></div><strong>{{ $issue->issued_by ?: '....................' }}</strong><br><small>Petugas Gudang</small></td><td><div style="height:42px"></div><strong>{{ $issue->received_by ?: '....................' }}</strong><br><small>Produksi</small></td></tr></tbody></table>
    <div style="font-size:9px;margin-top:8px;text-align:center;color:#555">Dicetak pada: {{ now()->format('d/m/Y H:i') }} | Status: {{ strtoupper($issue->status) }}</div>
    <div style="margin-top:auto;text-align:center;border-top:1px solid #ccc;padding-top:10px"><strong>{{ strtoupper($company->company_name ?? '-') }}</strong><br><span style="font-size:9px;color:#555">{{ $company->address ?? '-' }}</span></div>
</div>
</body>
</html>
