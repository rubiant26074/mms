<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>SPK - {{ $spk->spk_number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size:12px; color:#333; margin:0; padding:20px; }
        .box { max-width:800px; margin:auto; border:1px solid #eee; padding:30px; box-shadow:0 0 10px rgba(0,0,0,0.15); }
        .header { display:flex; justify-content:space-between; border-bottom:2px solid #333; padding-bottom:15px; margin-bottom:15px; }
        .doc-title { text-align:right; font-size:24px; font-weight:bold; color:#1a365d; line-height: 1.2; }
        .doc-title small { font-size:16px; color:#4a5568; font-weight:normal; }
        
        .meta-table { width:100%; border:none; margin-bottom:20px; }
        .meta-table td { border:none; padding:4px 0; vertical-align:top; }
        
        .section-title { font-size:13px; font-weight:bold; background-color:#1a365d; color:#fff; padding:6px 10px; margin-top:20px; margin-bottom:10px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        table.data-table { width:100%; border-collapse:collapse; margin-bottom:15px; }
        table.data-table th, table.data-table td { border:1px solid #cbd5e0; padding:8px; text-align:left; }
        table.data-table th { background-color:#f7fafc; font-weight:bold; color:#2d3748; }
        
        .process-badge { display:inline-block; background-color:#edf2f7; color:#2d3748; padding:4px 8px; border-radius:4px; margin-right:5px; margin-bottom:5px; font-weight:bold; border: 1px solid #e2e8f0; }
        .notes-box { border:1px solid #cbd5e0; background-color:#fcfcfc; padding:12px; border-radius:4px; min-height:60px; line-height:1.5; }
        
        .signatures { display:flex; justify-content:space-between; margin-top:40px; }
        .sig-box { text-align:center; width:30%; }
        .sig-line { margin-top:60px; border-top:1px solid #333; padding-top:5px; font-weight:bold; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .no-print { text-align:center; margin-bottom:20px; }
        
        .footer { 
            margin-top: 40px; 
            border-top: 1px solid #cbd5e0; 
            padding-top: 15px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between;
        }
        .footer-left {
            color:#718096;
            line-height: 1.4;
            text-align: left;
        }
        .footer-right {
            text-align: right;
        }
        
        @media print { 
            .no-print { display:none; } 
            .box { border:none; box-shadow:none; padding:0; } 
            body { padding:0; }
        }
    </style>
</head>
<body onload="window.print()">
<div class="no-print">
    <button onclick="window.print()" style="padding:8px 16px; background-color:#1a365d; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">Cetak SPK</button>
</div>
<div class="box">
    <!-- Header -->
    <div class="header">
        <div style="font-size:24px; font-weight:bold; color:#1a365d; align-self: center;">SURAT PERINTAH KERJA</div>
        <div class="doc-title">
            <small style="font-size:16px; color:#4a5568; font-weight:bold;">{{ $spk->spk_number }}</small>
        </div>
    </div>

    <!-- Metadata -->
    <table class="meta-table">
        <tr>
            <td style="width: 50%;">
                <strong>Customer:</strong><br>
                <strong style="color:#2d3748;">{{ $spk->salesOrder?->customer?->name }}</strong><br>
                <span style="color:#4a5568;">{!! nl2br(e($spk->salesOrder?->customer?->address ?? '-')) !!}</span>
            </td>
            <td style="width: 50%; padding-left: 40px;">
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:40%; padding:2px 0;"><strong>No. SO</strong></td>
                        <td style="width:5%; padding:2px 0;">:</td>
                        <td style="padding:2px 0;">{{ $spk->salesOrder?->so_number }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 0;"><strong>PO Customer</strong></td>
                        <td style="padding:2px 0;">:</td>
                        <td style="padding:2px 0;">{{ $spk->salesOrder?->cust_po_number ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 0;"><strong>Nama Proyek</strong></td>
                        <td style="padding:2px 0;">:</td>
                        <td style="padding:2px 0;">{{ $spk->project_name ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 0;"><strong>Tgl Terbit</strong></td>
                        <td style="padding:2px 0;">:</td>
                        <td style="padding:2px 0;">{{ optional($spk->spk_date)->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:2px 0;"><strong>Target Selesai</strong></td>
                        <td style="padding:2px 0;">:</td>
                        <td style="padding:2px 0; color:#e53e3e; font-weight:bold;">{{ optional($spk->deadline_date)->format('d/m/Y') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- 1. Item Barang Jadi -->
    <div class="section-title">1. Item Barang Jadi (Finish Goods / WIP)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:5%;" class="text-center">No</th>
                <th style="width:20%;">Kode Barang</th>
                <th>Nama Barang</th>
                <th>Spesifikasi / Material</th>
                <th style="width:12%;" class="text-right">Qty</th>
                <th style="width:10%;" class="text-center">Unit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($spk->salesOrder?->items ?? [] as $item)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $item->item?->item_code ?: $item->item_code_manual }}</td>
                    <td><strong>{{ $item->item?->item_name ?: $item->item_name_manual }}</strong></td>
                    <td style="color:#4a5568;">{{ $item->material_manual ?: $item->item?->description ?: '-' }}</td>
                    <td class="text-right fw-bold">{{ $item->qty + 0 }}</td>
                    <td class="text-center">{{ $item->unit_manual ?: $item->item?->unit }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- 2. Kebutuhan Material -->
    <div class="section-title">2. Kebutuhan Material (Bahan Baku)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:5%;" class="text-center">No</th>
                <th style="width:20%;">Kode Material</th>
                <th>Nama Material</th>
                <th style="width:15%;" class="text-right">Qty Butuh</th>
                <th style="width:12%;" class="text-center">Unit</th>
                <th style="width:15%;" class="text-center">Cek/Paraf</th>
            </tr>
        </thead>
        <tbody>
            @forelse($spk->materials as $m)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $m->item?->item_code }}</td>
                    <td>{{ $m->item?->item_name }}</td>
                    <td class="text-right fw-bold">{{ $m->qty_required + 0 }}</td>
                    <td class="text-center">{{ $m->item?->unit }}</td>
                    <td></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-3 text-muted">Tidak ada kebutuhan material yang didefinisikan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- 3. Route Proses -->
    <div class="section-title">3. Route Proses Pekerjaan</div>
    <div style="margin-bottom: 20px;">
        @if($spk->required_processes)
            @foreach(explode(',', $spk->required_processes) as $process)
                <span class="process-badge">{{ trim($process) }}</span>
            @endforeach
        @else
            <span class="text-muted font-italic">Tidak ada proses pekerjaan yang didefinisikan.</span>
        @endif
    </div>

    <!-- 4. Catatan Produksi -->
    <div class="section-title">4. Catatan Produksi / Spesifikasi Khusus</div>
    <div class="notes-box">
        {!! nl2br(e($spk->notes ?: '-')) !!}
    </div>

    <!-- Signatures -->
    <div class="signatures">
        <div class="sig-box">
            <div>Dibuat Oleh,</div>
            <div class="sig-line">{{ $spk->creator?->fullname ?: ($spk->creator?->username ?: 'PPIC') }}</div>
        </div>
        <div class="sig-box">
            <div>Disetujui Oleh,</div>
            <div class="sig-line">Manager Produksi</div>
        </div>
        <div class="sig-box">
            <div>Diterima Workshop,</div>
            <div class="sig-line">Supervisor / SPV</div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-left">
            <strong style="color:#1a365d; font-size: 13px;">{{ $company['company_name'] ?? 'MMS SYSTEM' }}</strong><br>
            <span>{!! nl2br(e($company['address'] ?? '-')) !!}</span>
        </div>
        <div class="footer-right">
            @if(!empty($company['logo_path']))
                <img src="{{ asset($company['logo_path']) }}" style="max-height: 40px; object-fit: contain;" alt="Logo">
            @endif
        </div>
    </div>
</div>
</body>
</html>
