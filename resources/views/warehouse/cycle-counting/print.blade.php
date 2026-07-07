<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cycle Counting - {{ $session->session_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { border: 1px solid #ccc; padding: 20px; max-width: 800px; margin: auto; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-start; }
        .doc-title { font-size: 22px; font-weight: bold; color: #555; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; }
        .meta-table { margin-bottom: 14px; }
        .meta-table td { padding: 2px 4px; vertical-align: top; }
        .item-table th { background: #f8f9fa; border: 1px solid #000; padding: 7px; font-size: 10px; text-align: left; }
        .item-table td { border: 1px solid #000; padding: 7px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .footer-sig { margin-top: 18px; table-layout: fixed; }
        .footer-sig th { border: 1px solid #000; background: #f9f9f9; padding: 5px; font-size: 10px; }
        .footer-sig td { border: 1px solid #000; height: 86px; text-align: center; vertical-align: bottom; padding: 6px; font-size: 10px; }
        .sig-name { display: block; font-weight: bold; text-decoration: underline; }
        .sig-note { display: block; font-size: 9px; color: #555; }
        .page-footer { border-top: 1px solid #ccc; padding-top: 10px; text-align: center; margin-top: 16px; }
        @media print { .no-print { display: none; } .box { border: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="text-align:center; margin-bottom:12px;"><button onclick="window.print()">Cetak</button></div>
    <div class="box">
        <div class="doc-content">
            <div class="header">
                <div><strong>{{ $company['company_name'] ?? 'MMS SYSTEM' }}</strong><br>{{ $company['address'] ?? '-' }}</div>
                <div style="text-align:right;"><div class="doc-title">CYCLE COUNTING</div><div style="font-size:12px; font-weight:bold; margin-top:4px;">{{ $session->session_number }}</div></div>
            </div>
            <table class="meta-table">
                <tr><td width="18%"><strong>Tanggal Count</strong></td><td width="32%">: {{ optional($session->count_date)->format('d/m/Y') }}</td><td width="18%"><strong>Status</strong></td><td width="32%">: {{ strtoupper($session->status) }}</td></tr>
                <tr><td><strong>Area / Zona</strong></td><td>: {{ $session->count_area ?: '-' }}</td><td><strong>Dibuat Oleh</strong></td><td>: {{ $session->creator?->fullname ?: '-' }}</td></tr>
                <tr><td><strong>Posted Oleh</strong></td><td>: {{ $session->poster?->fullname ?: '-' }}</td><td><strong>Tgl Post</strong></td><td>: {{ optional($session->posted_at)->format('d/m/Y H:i') ?: '-' }}</td></tr>
                <tr><td><strong>Catatan</strong></td><td colspan="3">: {{ $session->notes ?: '-' }}</td></tr>
            </table>
            <table class="item-table">
                <thead><tr><th width="4%" class="text-center">No</th><th width="18%">Item Code</th><th width="30%">Item Name</th><th width="12%" class="text-right">System Qty</th><th width="12%" class="text-right">Counted Qty</th><th width="12%" class="text-right">Variance</th><th width="12%">Reason</th></tr></thead>
                <tbody>
                @forelse($session->items as $line)
                    <tr><td class="text-center">{{ $loop->iteration }}</td><td>{{ $line->item?->item_code }}</td><td>{{ $line->item?->item_name }}</td><td class="text-right">{{ number_format($line->system_qty, 4, ',', '.') }} {{ $line->item?->unit }}</td><td class="text-right">{{ number_format($line->counted_qty, 4, ',', '.') }} {{ $line->item?->unit }}</td><td class="text-right">{{ number_format($line->variance_qty, 4, ',', '.') }} {{ $line->item?->unit }}</td><td>{{ $line->reason ?: '-' }}</td></tr>
                @empty
                    <tr><td colspan="7" class="text-center">Tidak ada item pada session ini.</td></tr>
                @endforelse
                </tbody>
            </table>
            <table class="footer-sig">
                <thead><tr><th>Checker</th><th>Approver</th><th>Admin Gudang</th></tr></thead>
                <tbody><tr><td><span class="sig-name">....................</span><span class="sig-note">Pemeriksa Fisik</span></td><td><span class="sig-name">....................</span><span class="sig-note">Verifikasi Variance</span></td><td><span class="sig-name">{{ $session->poster?->fullname ?: '....................' }}</span><span class="sig-note">Posting Penyesuaian</span></td></tr></tbody>
            </table>
        </div>
        <div class="page-footer"><strong>{{ strtoupper($company['company_name'] ?? 'MMS SYSTEM') }}</strong><br><small>{{ $company['address'] ?? '-' }}</small></div>
    </div>
</body>
</html>
