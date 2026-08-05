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
        .logo { max-height: 31px; object-fit: contain; }
        .doc-title-box { border: none; padding: 5px; display: inline-block; text-align: center; }
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
        @media print{
            .page-footer{position:relative}
            .page-footer::after{content:"Page " counter(page);position:absolute;right:10px;bottom:10px;font-size:9px;color:#555} 
            .no-print { display:none; } 
            .box { border:none; padding:0; } 
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
<div class="no-print">
    <button onclick="window.print()" style="padding:8px 16px; background-color:#333; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">Cetak SPK</button>
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
                    <div class="doc-title">SURAT PERINTAH KERJA</div>
                    <div class="doc-subtitle">PPIC DEPARTMENT</div>
                </div>
                <div class="doc-number">{{ $spk->spk_number }}</div>
            </div>
        </div>
    </td></tr></thead>
    <tbody><tr><td style="border:none;padding:0;">
        <div class="content">

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
</td></tr></tbody>
<tfoot><tr><td style="border:none;padding:0;height:40px;">
    <!-- Footer -->
    <div class="page-footer">
        <span class="footer-comp-name">{{ strtoupper($compName ?? '-') }}</span>
        <span>{{ $compAddress ?? '-' }}</span>
        <div style="margin-top: 5px;">
            @php
                $phoneVal = is_array($company) ? ($company['phone'] ?? null) : ($company->phone ?? null);
                $salesPhoneVal = $spk->salesOrder?->customer?->sales_phone;
            @endphp
            @if($phoneVal)
                <span style="display: inline-flex; align-items: center; margin-right: 15px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72, 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    {{ $phoneVal }}
                </span>
            @endif
            @if($salesPhoneVal)
                <span style="display: inline-flex; align-items: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle; color: #25D366; margin-right: 3.5px;"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.457L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.625 1.451 5.436-.002 9.852-4.419 9.855-9.858.002-2.634-1.02-5.11-2.881-6.974A9.784 9.784 0 0 0 12.008 1.94c-5.439 0-9.856 4.417-9.859 9.858-.001 1.76.49 3.473 1.42 4.966l-.995 3.63 3.733-.978c1.452.793 2.924 1.188 4.333 1.188zm12.355-6.737c-.302-.152-1.791-.883-2.073-.984-.282-.102-.489-.153-.694.153-.205.305-.794.996-.973 1.2-.18.204-.36.229-.663.077-1.128-.565-2.128-1.045-2.92-2.404-.15-.258-.15-.418-.001-.568.136-.134.302-.354.453-.531.152-.178.202-.303.303-.506.101-.202.051-.379-.025-.531-.076-.152-.693-1.67-.95-2.285-.25-.601-.523-.518-.718-.528-.186-.01-.399-.01-.612-.01-.213 0-.56.08-.853.401-.293.32-.1.121-.1.121s-.11.236-.264.498c-.144.248-.48.918-.737 1.442-.258.525-.494 1.01-.652 1.332-.239.489-.356.73-.393.8-.073.134-.146.269-.22.404-.393.722-.59 1.545-.588 2.385.006 2.548 1.833 4.89 2.088 5.23.256.34 3.522 5.378 8.532 7.545 1.192.515 2.122.822 2.848 1.053 1.198.38 2.29.327 3.153.198.96-.144 2.952-1.206 3.364-2.37.412-1.164.412-2.164.29-2.37-.123-.207-.453-.356-.755-.508z"/></svg>
                    {{ $salesPhoneVal }}
                </span>
            @endif
        </div>
    </div>
</td></tr></tfoot>
</table>
</div>
</body>
</html>
