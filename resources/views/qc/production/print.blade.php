<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Verifikasi QC Produksi - {{ $qc->qc_number }}</title>
    <style>
        @page{size:A4 portrait;margin:0}body{font-family:Arial,sans-serif;font-size:11px;margin:0;padding:20px;color:#000}.box{max-width:800px;margin:auto}.header{border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:15px;display:flex;justify-content:space-between}.doc-title-box{border:none;padding:5px;display:inline-block;text-align:center}.doc-title{font-size:18px;font-weight:bold;letter-spacing:1px}.info-table,.data-table,.footer-sig{width:100%;border-collapse:collapse;margin-bottom:12px}.info-table td{vertical-align:top;padding:2px}.section-header{font-weight:bold;font-size:11px;margin-bottom:5px;text-transform:uppercase;background:#f8f9fa;padding:4px;border:1px solid #ccc}.data-table th,.data-table td,.footer-sig th,.footer-sig td{border:1px solid #000;padding:6px}.data-table th,.footer-sig th{background:#f2f2f2;font-size:10px}.footer-sig{table-layout:fixed;margin-top:20px}.footer-sig td{height:100px;text-align:center;vertical-align:bottom}.no-print{text-align:center;margin-bottom:15px}@media print{.no-print{display:none}}
    </style>
</head>
<body onload="window.print()">
<div class="no-print"><button onclick="window.print()">Cetak</button> <button onclick="window.close()">Tutup</button></div>
<div class="box">
    <table style="width:100%;border-collapse:collapse;border:none;">
    <thead><tr><td style="border:none;padding:0;">
        <div class="header">
            <div>@if($company->logo_path)<img src="{{ asset($company->logo_path) }}" style="max-height:45px;object-fit:contain" alt="Logo">@endif</div>
            <div style="text-align:right"><div class="doc-title-box"><div class="doc-title">QC FINAL PRODUCTION</div><div style="font-size:9px;letter-spacing:1px">VERIFIKASI HASIL PEMERIKSAAN</div></div><div style="font-size:13px;font-weight:bold;margin-top:8px">{{ $qc->qc_number }}</div></div>
        </div>
    </td></tr></thead>
    <tbody><tr><td style="border:none;padding:0;">
        <div class="content" style="padding-top: 10px;">
    <table class="info-table">
        <tr><td width="15%"><strong>No. SPK</strong></td><td width="35%">: {{ $qc->spk?->spk_number }}</td><td width="15%"><strong>Tgl QC</strong></td><td width="35%">: {{ optional($qc->qc_date)->format('d F Y') }}</td></tr>
        <tr><td><strong>Project</strong></td><td>: {{ $qc->spk?->project_name ?: '-' }}</td><td><strong>Inspector</strong></td><td>: {{ $qc->inspector?->fullname ?: '-' }}</td></tr>
        <tr><td><strong>Deadline SPK</strong></td><td>: {{ optional($qc->spk?->deadline_date)->format('d F Y') ?: '-' }}</td><td><strong>Status QC</strong></td><td>: <strong>{{ strtoupper($qc->status) }}</strong></td></tr>
    </table>
    @php
        $items = $qc->spk?->salesOrder?->items ?? collect();
        $sumPass = $history->sum('qty_pass');
        $qtyNg = (float) $qc->qty_reject;
        $qtyCheck = min((float) $items->sum('qty'), (float) $sumPass + $qtyNg);
        preg_match('/- Laser:\s*(.*)/i', (string) $qc->notes, $laser);
        preg_match('/- Bending:\s*(.*)/i', (string) $qc->notes, $bend);
        preg_match('/- Welding:\s*(.*)/i', (string) $qc->notes, $weld);
    @endphp
    <div class="section-header">1. Ringkasan Hasil QC</div>
    <table class="data-table"><thead><tr><th>Qty Check</th><th>Qty OK</th><th>Qty NG</th><th>Keterangan</th></tr></thead><tbody><tr><td align="center"><strong>{{ $qtyCheck + 0 }}</strong></td><td align="center" style="color:#0b5d2a"><strong>{{ (float) $sumPass + 0 }}</strong></td><td align="center" style="color:#b00020"><strong>{{ $qtyNg + 0 }}</strong></td><td>{{ $qtyNg > 0 ? 'Ada item NG, tindak lanjut NCR diperlukan.' : 'Semua hasil dinyatakan OK.' }}</td></tr></tbody></table>
    <div class="section-header">2. Checklist Verifikasi Proses</div>
    <table class="data-table"><tbody><tr><td width="20%"><strong>Laser</strong></td><td>{{ trim($laser[1] ?? '-') ?: '-' }}</td></tr><tr><td><strong>Bending</strong></td><td>{{ trim($bend[1] ?? '-') ?: '-' }}</td></tr><tr><td><strong>Welding</strong></td><td>{{ trim($weld[1] ?? '-') ?: '-' }}</td></tr></tbody></table>
    <div class="section-header">3. Item Referensi SPK / SO</div>
    <table class="data-table"><thead><tr><th>No</th><th>Kode</th><th>Nama Barang</th><th>Qty Plan</th><th>Unit</th></tr></thead><tbody>@forelse($items as $row)<tr><td align="center">{{ $loop->iteration }}</td><td>{{ $row->item?->item_code ?: $row->item_code_manual }}</td><td>{{ $row->item?->item_name ?: $row->item_name_manual }}</td><td align="right">{{ (float) $row->qty + 0 }}</td><td>{{ $row->item?->unit ?: $row->unit_manual }}</td></tr>@empty<tr><td colspan="5" align="center">Item tidak tersedia.</td></tr>@endforelse</tbody></table>
    @if($qc->notes)<div class="section-header">4. Catatan</div><div style="border:1px solid #000;padding:8px;margin-bottom:12px;white-space:pre-line">{{ $qc->notes }}</div>@endif
    <table class="footer-sig"><thead><tr><th>Inspector QC</th><th>Diketahui</th></tr></thead><tbody><tr><td><strong>{{ $qc->inspector?->fullname ?: 'QC Inspector' }}</strong><br>{{ optional($qc->qc_date)->format('d/m/Y') }}</td><td><strong>{{ $qc->approver?->fullname ?: 'QC Manager' }}</strong><br>__/__/____</td></tr></tbody></table>
    </div>
</td></tr></tbody>
<tfoot><tr><td style="border:none;padding:0;">
    <div style="margin-top:14px;text-align:center;border-top:1px solid #ccc;padding-top:10px"><strong>{{ strtoupper($company->company_name ?? '-') }}</strong><br><span style="font-size:9px;color:#555">{{ $company->address ?? '-' }}</span></div>
</td></tr></tfoot>
</table>
</div>
</body>
</html>
