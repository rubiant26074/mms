<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>NCR - {{ $ncr->ncr_number }}</title>
    <style>
        @page{size:A4 portrait;margin:0}body{font-family:Arial,sans-serif;font-size:11px;margin:0;padding:20px;color:#000}.box{border:1px solid #ccc;padding:20px;max-width:800px;margin:auto;min-height:96vh;display:flex;flex-direction:column}.doc-content{flex:1}.header{border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:20px;display:flex;justify-content:space-between}.doc-title{font-size:24px;font-weight:bold;color:#555;letter-spacing:2px}.doc-number{text-align:right;font-weight:bold}.info-table{width:100%;border-collapse:collapse;margin-bottom:16px}.info-table td{padding:4px;vertical-align:top}.label{font-weight:bold;width:130px}.box-title{background:#333;color:#fff;padding:3px 10px;font-weight:bold;display:inline-block;margin-bottom:6px;font-size:10px}.analysis-box{border:1px solid #000;padding:10px;margin-bottom:16px;min-height:70px}.disposition-table{width:100%;border:1px solid #000;margin-bottom:16px;text-align:center}.disposition-table td{border:1px solid #000;padding:10px;width:25%}.selected-disp{background:#ddd;font-weight:bold;border:2px solid #000}.signature-section{margin-top:20px;display:flex;justify-content:space-between;text-align:center;gap:10px}.sig-box{width:24%;border:1px solid #000;padding:6px;min-height:110px;display:flex;flex-direction:column;justify-content:flex-end}.sig-head{font-weight:bold;margin-bottom:6px;background:#f0f0f0;padding:4px}.sig-name{font-weight:bold;border-top:1px solid #000;padding-top:4px}.sig-note{font-size:9px;color:#555}.no-print{text-align:center;margin-bottom:15px}@media print{.no-print{display:none}.box{border:none}}
    </style>
</head>
<body onload="window.print()">
<div class="no-print"><button onclick="window.print()">Cetak NCR</button> <button onclick="window.close()">Tutup</button></div>
<div class="box">
    <div class="doc-content">
        <div class="header">
            <div>@if($company->logo_path)<img src="{{ asset($company->logo_path) }}" style="max-height:60px" alt="Logo">@endif<br><strong>{{ $company->company_name ?? 'MMS' }}</strong><br><small>QUALITY ASSURANCE DEPARTMENT</small></div>
            <div><div class="doc-title">NCR REPORT</div><div class="doc-number">{{ $ncr->ncr_number }}</div></div>
        </div>
        <table class="info-table">
            <tr><td class="label">Tanggal Laporan</td><td>: {{ optional($ncr->created_at)->format('d F Y') }}</td><td class="label">Sumber Masalah</td><td>: {{ strtoupper($ncr->source_type) }}</td></tr>
            <tr><td class="label">Kode Barang</td><td>: <strong>{{ $ncr->item?->item_code }}</strong></td><td class="label">Nama Barang</td><td>: {{ $ncr->item?->item_name }}</td></tr>
            <tr><td class="label">Jumlah Reject</td><td>: <strong style="color:red;font-size:14px">{{ (float) $ncr->qty_reject + 0 }} {{ $ncr->item?->unit }}</strong></td><td class="label">Penanggung Jawab</td><td>: {{ $ncr->operator?->fullname ?: '-' }}</td></tr>
        </table>
        <div class="box-title">DESKRIPSI KETIDAKSESUAIAN</div><div class="analysis-box">{!! nl2br(e($ncr->issue_description)) !!}</div>
        <div class="box-title">AKAR PENYEBAB</div><div class="analysis-box">{!! $ncr->root_cause ? nl2br(e($ncr->root_cause)) : '<em>Belum dianalisa</em>' !!}</div>
        <div class="box-title">TINDAKAN PERBAIKAN</div><div class="analysis-box">{!! $ncr->corrective_action ? nl2br(e($ncr->corrective_action)) : '<em>Belum ditentukan</em>' !!}</div>
        <div class="box-title">KEPUTUSAN / DISPOSISI</div>
        <table class="disposition-table"><tr>@foreach(['pending'=>'PENDING','repair'=>'REPAIR','scrap'=>'SCRAP','return_to_vendor'=>'RETURN TO VENDOR'] as $key => $label)<td class="{{ $ncr->disposition === $key ? 'selected-disp' : '' }}">{{ $label }}</td>@endforeach</tr></table>
        <div class="signature-section">
            <div class="sig-box"><div class="sig-head">Dibuat Oleh</div><div style="height:55px"></div><div class="sig-name">{{ $ncr->creator?->fullname ?: '....................' }}</div><div class="sig-note">Inspector</div></div>
            <div class="sig-box"><div class="sig-head">Penanggung Jawab</div><div style="height:55px"></div><div class="sig-name">{{ $ncr->responsibleSigner?->fullname ?: ($ncr->operator?->fullname ?: '....................') }}</div><div class="sig-note">{{ $ncr->resp_signed_at ? $ncr->resp_signed_at->format('d/m/Y') : 'Menunggu Tanda Tangan' }}</div></div>
            <div class="sig-box"><div class="sig-head">Disetujui GM</div><div style="height:55px"></div><div class="sig-name">{{ $ncr->gmApprover?->fullname ?: '....................' }}</div><div class="sig-note">{{ $ncr->gm_approved_at ? $ncr->gm_approved_at->format('d/m/Y') : 'Menunggu Approval' }}</div></div>
            <div class="sig-box"><div class="sig-head">Diverifikasi QA</div><div style="height:55px"></div><div class="sig-name">....................</div><div class="sig-note">QA</div></div>
        </div>
    </div>
    <div style="border-top:1px solid #ccc;padding-top:10px;text-align:center;margin-top:20px"><strong>{{ strtoupper($company->company_name ?? '-') }}</strong><br><span style="font-size:9px;color:#555">{{ $company->address ?? '-' }}</span></div>
</div>
</body>
</html>
