<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan - {{ $note->dn_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { max-width: 800px; margin: auto; border: 1px solid #ccc; padding: 20px; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .logo-area img { max-height: 60px; object-fit: contain; }
        .header-right { text-align: right; }
        .doc-title { font-size: 24px; font-weight: bold; color: #555; letter-spacing: 2px; }
        .doc-number { margin-top: 5px; font-size: 14px; font-weight: bold; color: #333; }
        .info-table, .item-table, .footer-sig { width: 100%; border-collapse: collapse; }
        .info-table { margin-bottom: 14px; }
        .info-table td { vertical-align: top; padding: 2px; }
        .item-table { margin-bottom: 20px; }
        .item-table th { background: #f8f9fa; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 8px; text-align: left; font-weight: bold; }
        .item-table td { border-bottom: 1px solid #eee; padding: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .notes-box { border: 1px solid #ddd; background: #fafafa; padding: 8px 10px; margin-bottom: 10px; font-size: 10px; }
        .footer-sig { margin-top: 12px; table-layout: fixed; }
        .footer-sig th { border: 1px solid #000; background: #f9f9f9; padding: 5px; font-size: 10px; }
        .footer-sig td { border: 1px solid #000; height: 90px; text-align: center; vertical-align: bottom; padding: 5px; font-size: 10px; }
        .sig-name { display: block; font-weight: bold; text-decoration: underline; }
        .page-footer { border-top: 1px solid #ccc; padding-top: 10px; text-align: center; font-size: 14px; font-weight: bold; margin-top: 20px; }
        .footer-addr { font-size: 9px; font-weight: normal; margin-top: 3px; color: #555; }
        @media print { .no-print { display: none; } .box { border: none; } }
    </style>
</head>
<body onload="window.print()">
    <div style="text-align: center; margin-bottom: 20px;" class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #333; color: #fff; border: none; border-radius: 5px;">Cetak Surat Jalan</button>
    </div>

    <div class="box">
        <div class="doc-content">
            <div class="header">
                <div class="logo-area">@if(! empty($company['logo_path']))<img src="{{ asset($company['logo_path']) }}" alt="Logo">@endif</div>
                <div class="header-right"><div class="doc-title">SURAT JALAN</div><div class="doc-number">{{ $note->dn_number }}</div></div>
            </div>

            <table class="info-table">
                <tr>
                    <td width="55%">
                        <strong>Kepada Yth:</strong><br>
                        <strong style="font-size: 13px;">{{ strtoupper($note->salesOrder?->customer?->name ?? '-') }}</strong><br>
                        UP: {{ $note->salesOrder?->customer?->pic ?? '-' }}<br>
                        {!! nl2br(e($note->salesOrder?->customer?->address ?? '-')) !!}<br>
                        Telp: {{ $note->salesOrder?->customer?->phone ?? '-' }}<br><br>
                        <strong>Project:</strong> {{ $spkSummary['projects'] }}
                    </td>
                    <td width="45%" style="text-align: right;">
                        <table align="right">
                            <tr><td><strong>Tanggal Kirim :</strong></td><td align="right">{{ optional($note->dn_date)->format('d/m/Y') }}</td></tr>
                            <tr><td><strong>No. SPK :</strong></td><td align="right">{{ $spkSummary['numbers'] }}</td></tr>
                            <tr><td><strong>No. SO :</strong></td><td align="right">{{ $note->salesOrder?->so_number ?? '-' }}</td></tr>
                            <tr><td><strong>No. PO Cust :</strong></td><td align="right">{{ $note->salesOrder?->cust_po_number ?: '-' }}</td></tr>
                            <tr><td><strong>Kendaraan :</strong></td><td align="right">{{ $note->vehicle_number ?: '-' }}</td></tr>
                            <tr><td><strong>Pengemudi :</strong></td><td align="right">{{ $note->driver_name ?: '-' }}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>

            <table class="item-table">
                <thead><tr><th width="5%" class="text-center">No</th><th>Nama Barang / Deskripsi</th><th width="15%" class="text-center">Qty Dikirim</th><th width="15%" class="text-center">Satuan</th><th width="20%">Keterangan</th></tr></thead>
                <tbody>
                    @foreach($note->items as $row)
                        <tr><td class="text-center">{{ $loop->iteration }}</td><td><strong>{{ $row->item?->item_name }}</strong><br><small>{{ $row->item?->item_code }}</small></td><td class="text-center">{{ $row->qty_sent + 0 }}</td><td class="text-center">{{ $row->item?->unit }}</td><td>{{ $row->notes ?: '-' }}</td></tr>
                    @endforeach
                </tbody>
            </table>

            @if($note->notes)<div class="notes-box"><strong>Catatan:</strong><br>{!! nl2br(e($note->notes)) !!}</div>@endif
        </div>

        <table class="footer-sig">
            <tr><th>Dibuat Oleh</th><th>Gudang</th><th>Diterima Oleh</th></tr>
            <tr>
                <td><span class="sig-name">{{ $note->creator?->fullname ?? '-' }}</span></td>
                <td><span class="sig-name">{{ $note->approver?->fullname ?? $note->creator?->fullname ?? '-' }}</span></td>
                <td><span class="sig-name">&nbsp;</span></td>
            </tr>
        </table>
        <div class="page-footer">{{ $company['company_name'] ?? 'PT. MANUFAKTUR SEJAHTERA' }}<div class="footer-addr">{{ $company['address'] ?? '-' }} | {{ $company['phone'] ?? '-' }} | {{ $company['email'] ?? '-' }}</div></div>
    </div>
</body>
</html>
