<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>QC Report - {{ $qc->qc_number }}</title>
    <style>@page{size:A4 portrait;margin:0}body{font-family:Arial,sans-serif;font-size:11px;margin:0;padding:20px;color:#000}.box{max-width:800px;margin:auto;position:relative;min-height:96vh}.header{border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:15px;display:flex;justify-content:space-between}.doc-title-box{border:none;padding:5px;display:inline-block;text-align:center}.doc-title{font-size:18px;font-weight:bold;letter-spacing:1px}.info-table,.data-table,.footer-sig{width:100%;border-collapse:collapse}.info-table{margin-bottom:15px}.info-table td{vertical-align:top;padding:2px}.data-table th,.data-table td,.footer-sig th,.footer-sig td{border:1px solid #000;padding:8px}.data-table th,.footer-sig th{background:#f2f2f2;font-size:10px;text-transform:uppercase}.footer-sig{margin-top:30px;table-layout:fixed}.footer-sig td{height:90px;text-align:center;vertical-align:bottom}.sig-name{font-weight:bold;text-decoration:underline;display:block}.page-footer{position:absolute;bottom:10px;left:0;right:0;text-align:center;border-top:1px solid #ccc;padding-top:10px}.footer-comp-name{font-size:14px;font-weight:bold;display:block}.status-ng{color:red;font-weight:bold}@media print{.no-print{display:none}}</style>
</head>
<body onload="window.print()">
@php $gr = $qc->goodsReceipt; @endphp
<div class="box">
    <div class="header"><div>@if($company->logo_path)<img src="{{ asset($company->logo_path) }}" style="max-height:45px;object-fit:contain" alt="Logo">@endif</div><div style="text-align:right"><div class="doc-title-box"><div class="doc-title">INCOMING INSPECTION</div><div style="font-size:9px;letter-spacing:1px">LAPORAN PEMERIKSAAN BARANG MASUK</div></div><div style="font-size:13px;font-weight:bold;margin-top:8px">{{ $qc->qc_number }}</div></div></div>
    <table class="info-table">
        <tr><td width="15%"><strong>Sumber</strong></td><td width="35%">: {{ $gr->receipt_type === 'consignment' ? (($gr->customer?->name ?: '-') . ' (Customer)') : ($gr->purchaseOrder?->supplier?->name ?: '-') }}</td><td width="15%"><strong>Tgl Inspeksi</strong></td><td width="35%">: {{ optional($qc->qc_date)->format('d F Y') }}</td></tr>
        <tr><td><strong>Ref. GR</strong></td><td>: {{ $gr->gr_number }}</td><td><strong>No. SJ</strong></td><td>: {{ $gr->delivery_note_number }}</td></tr>
        <tr><td><strong>Ref. PO</strong></td><td>: {{ $gr->purchaseOrder?->po_number ?: '-' }}</td><td><strong>Inspector</strong></td><td>: {{ $qc->inspector?->fullname }}</td></tr>
        <tr><td><strong>Keputusan</strong></td><td colspan="3">: <strong style="text-transform:uppercase;font-size:12px">{{ $qc->final_decision }}</strong></td></tr>
    </table>
    <table class="data-table"><thead><tr><th>No</th><th>Nama Barang / Material</th><th>Qty Rec</th><th>Qty OK</th><th>Qty NG</th><th>Detail Pengecekan</th><th>Keterangan</th></tr></thead><tbody>
        @foreach($qc->items as $row)
            @php $checks = $row->checklist_data ? json_decode($row->checklist_data, true) : []; @endphp
            <tr style="{{ $row->qty_reject > 0 ? 'color:red' : '' }}"><td align="center">{{ $loop->iteration }}</td><td><strong>{{ $row->item?->item_name }}</strong><br><small>Kode: {{ $row->item?->item_code }}</small><br><small style="font-style:italic">Std: {{ ucfirst($row->item?->qc_type ?: 'general') }}</small></td><td align="center">{{ $row->qty_received + 0 }}</td><td align="center" style="font-weight:bold;color:green">{{ $row->qty_good + 0 }}</td><td align="center" style="font-weight:bold;color:red">{{ $row->qty_reject + 0 }}</td><td>@forelse((array) $checks as $k => $v)<span style="border:1px solid #ccc;padding:2px 4px;margin:1px;display:inline-block">{{ ucfirst($k) }}: <strong class="{{ in_array($v, ['NG','Fail','Major'], true) ? 'status-ng' : '' }}">{{ $v }}</strong></span>@empty<span style="color:#999;font-style:italic">- Visual Check Only -</span>@endforelse</td><td>{{ $row->notes }}</td></tr>
        @endforeach
    </tbody></table>
    <div style="border:1px solid #000;padding:5px;min-height:40px;margin-bottom:15px"><strong>Catatan / Kesimpulan:</strong><br>{!! nl2br(e($qc->notes)) !!}</div>
    <table class="footer-sig"><thead><tr><th>Diperiksa Oleh (Inspector)</th><th>Disetujui (QC Manager)</th><th>Gudang (Serah Terima)</th></tr></thead><tbody><tr><td><div style="height:55px"></div><span class="sig-name">{{ $qc->inspector?->fullname ?: 'Inspector' }}</span><span>Tgl: {{ optional($qc->qc_date)->format('d/m/Y') }}</span></td><td><div style="height:55px"></div><span class="sig-name">{{ $qc->approver?->fullname ?: '....................' }}</span><span>Tgl: {{ $qc->approved_at ? $qc->approved_at->format('d/m/Y') : '____ / ____ / ______' }}</span></td><td><div style="height:55px"></div><span class="sig-name">{{ $qc->handoverUser?->fullname ?: '( Staff Gudang )' }}</span><span>Tgl: {{ $qc->handover_at ? $qc->handover_at->format('d/m/Y') : '____ / ____ / ______' }}</span></td></tr></tbody></table>
    <div class="page-footer"><span class="footer-comp-name">{{ strtoupper($company->company_name ?? '-') }}</span><span>{{ $company->address ?? '-' }}</span></div>
</div>
</body>
</html>
