<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>SPK - {{ $spk->spk_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .box { max-width: 800px; margin: auto; border: 1px solid #ccc; padding: 20px; min-height: 96vh; display: flex; flex-direction: column; position: relative; }
        .content { position: relative; z-index: 1; flex: 1 1 auto; }
        
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-start; }
        .logo { max-height: 45px; object-fit: contain; }
        .doc-title-box { border: none; padding: 5px; display: inline-block; text-align: center; min-width: 250px; }
        .doc-title { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
        .doc-subtitle { font-size: 9px; letter-spacing: 1px; }
        .doc-number { font-size: 13px; font-weight: bold; margin-top: 8px; text-align: right; }
        
        .meta-table { width:100%; border:none; margin-bottom:15px; }
        .meta-table td { border:none; padding:4px 0; vertical-align:top; }
        
        .section-title { font-size:11px; font-weight:bold; background-color:#f2f2f2; color:#000; padding:6px 10px; margin-top:15px; margin-bottom:10px; text-transform: uppercase; border: 1px solid #000; }
        
        table.data-table { width:100%; border-collapse:collapse; margin-bottom:15px; }
        table.data-table th, table.data-table td { border: 1px solid #000; padding:8px; text-align:left; }
        table.data-table th { background-color:#f2f2f2; font-weight:bold; text-transform:uppercase; font-size:10px; }
        
        .process-badge { display:inline-block; background-color:#f9f9f9; color:#000; padding:4px 8px; border: 1px solid #000; margin-right:5px; margin-bottom:5px; font-weight:bold; font-size:10px; }
        .notes-box { border:1px solid #000; background-color:#fafafa; padding:10px; min-height:50px; line-height:1.4; }
        
        .signatures { display:flex; justify-content:space-between; margin-top:20px; }
        .sig-box { text-align:center; width:30%; border: 1px solid #000; padding: 8px; }
        .sig-line { margin-top:55px; border-top:1px solid #000; padding-top:5px; font-weight:bold; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .no-print { text-align:center; margin-bottom:20px; }
        .page-footer { margin-top:auto; text-align:center; border-top:1px solid #ccc; padding-top:10px; }
        .footer-comp-name { font-size:14px; font-weight:bold; display:block; }
        @media print { 
            .no-print { display:none; } 
            .box { border:none; padding:0; } 
        }
    </style>
</head>
<body onload="window.print()">
@php
    $compLogo = is_array($company) ? ($company['logo_path'] ?? null) : ($company->logo_path ?? null);
    $compName = is_array($company) ? ($company['company_name'] ?? null) : ($company->company_name ?? null);
    $compAddress = is_array($company) ? ($company['address'] ?? null) : ($company->address ?? null);
@endphp
<div class="no-print">
    <button onclick="window.print()" style="padding:8px 16px; background-color:#333; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">Cetak SPK</button>
</div>
<div class="box">
    <div class="content">
        <!-- Header -->
        <div class="header">
            <div>
                @if($compLogo)
                    <img src="{{ asset($compLogo) }}" class="logo" alt="Logo">
                @endif
            </div>
            <div style="text-align:right">
                <div class="doc-title-box">
                    <div class="doc-title">SURAT PERINTAH KERJA</div>
                    <div class="doc-subtitle">PPIC DEPARTMENT</div>
                </div>
                <div class="doc-number">{{ $spk->spk_number }}</div>
            </div>
        </div>

        <!-- Metadata -->
        <table class="meta-table">
            <tr>
                <td style="width: 55%;">
                    <strong>Customer:</strong><br>
                    <strong style="color:#000; font-size:12px;">{{ $spk->salesOrder?->customer?->name }}</strong><br>
                    <span style="color:#333;">{!! nl2br(e($spk->salesOrder?->customer?->address ?? '-')) !!}</span>
                </td>
                <td style="width: 45%; padding-left: 20px;">
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
                            <td style="padding:2px 0;">{{ optional($spk->spk_date)->format('d F Y') }}</td>
                        </tr>
                        <tr>
                            <td style="padding:2px 0;"><strong>Target Selesai</strong></td>
                            <td style="padding:2px 0;">:</td>
                            <td style="padding:2px 0; font-weight:bold;">{{ optional($spk->deadline_date)->format('d F Y') }}</td>
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
                        <td>{{ $item->material_manual ?: $item->item?->description ?: '-' }}</td>
                        <td class="text-right" style="font-weight: bold;">{{ $item->qty + 0 }}</td>
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
                        <td class="text-right" style="font-weight: bold;">{{ $m->qty_required + 0 }}</td>
                        <td class="text-center">{{ $m->item?->unit }}</td>
                        <td></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-3">Tidak ada kebutuhan material yang didefinisikan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- 3. Route Process -->
        <div class="section-title">3. Route Proses Pekerjaan</div>
        <div style="margin-bottom: 15px;">
            @if($spk->required_processes)
                @foreach(explode(',', $spk->required_processes) as $process)
                    <span class="process-badge">{{ trim($process) }}</span>
                @endforeach
            @else
                <span class="text-muted" style="font-style: italic;">Tidak ada proses pekerjaan yang didefinisikan.</span>
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
                <div style="font-weight: bold; background-color: #f2f2f2; padding: 2px 0; border-bottom: 1px solid #000; margin-bottom: 5px; font-size: 10px;">DIBUAT OLEH</div>
                <div class="sig-line" style="margin-top: 50px;">{{ $spk->creator?->fullname ?: ($spk->creator?->username ?: 'PPIC') }}</div>
            </div>
            <div class="sig-box">
                <div style="font-weight: bold; background-color: #f2f2f2; padding: 2px 0; border-bottom: 1px solid #000; margin-bottom: 5px; font-size: 10px;">DISETUJUI OLEH</div>
                <div class="sig-line" style="margin-top: 50px;">Manager Produksi</div>
            </div>
            <div class="sig-box">
                <div style="font-weight: bold; background-color: #f2f2f2; padding: 2px 0; border-bottom: 1px solid #000; margin-bottom: 5px; font-size: 10px;">DITERIMA WORKSHOP</div>
                <div class="sig-line" style="margin-top: 50px;">Supervisor / SPV</div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="page-footer">
        <span class="footer-comp-name">{{ strtoupper($compName ?? '-') }}</span>
        <span>{{ $compAddress ?? '-' }}</span>
    </div>
</div>
</body>
</html>
