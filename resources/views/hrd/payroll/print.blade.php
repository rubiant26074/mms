<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji - {{ $payroll->payroll_code }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; }
        .slip-box { border: 1px solid #ccc; max-width: 760px; margin: auto; padding: 20px; min-height: 96vh; display: flex; flex-direction: column; }
        .doc-content { flex: 1 1 auto; }
        .header { border-bottom: 2px solid #333; margin-bottom: 18px; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: flex-start; }
        .logo-area { width: 50%; }
        .logo-area img { max-height: 55px; object-fit: contain; }
        .header-right { text-align: right; width: 45%; }
        .doc-title { font-size: 22px; font-weight: bold; color: #555; letter-spacing: 1px; }
        .doc-number { font-size: 13px; font-weight: bold; margin-top: 5px; color: #333; }
        .row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .label { font-weight: bold; }
        .nominal { text-align: right; }
        .divider { border-bottom: 1px dashed #ccc; margin: 10px 0; }
        .total-box { background: #f7f7f7; padding: 10px; font-weight: bold; border-top: 2px solid #000; margin-top: 8px; }
        .signature-section { margin-top: 20px; display: flex; justify-content: space-between; text-align: center; gap: 10px; }
        .sig-box { width: 48%; border: 1px solid #000; padding: 8px; min-height: 118px; display: flex; flex-direction: column; justify-content: flex-end; }
        .sig-label { font-weight: bold; margin-bottom: 6px; background: #f8f9fa; padding: 4px; border: 1px solid #ddd; }
        .sig-image { height: 50px; max-width: 120px; object-fit: contain; display: block; margin: 0 auto 5px auto; }
        .sig-name { border-top: 1px solid #000; padding-top: 4px; font-weight: bold; font-size: 10px; }
        .sig-note { font-size: 9px; color: #555; }
        .page-footer { border-top: 1px solid #ccc; padding-top: 10px; text-align: center; margin-top: 16px; }
        .footer-comp-name { font-size: 14.3px; font-weight: bold; display: block; margin-bottom: 3px; }
        .footer-addr { font-size: 9px; color: #555; }
        @media print { .slip-box { border: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="slip-box">
        <div class="doc-content">
            <div class="header">
                <div class="logo-area">
                    @if($company->logo_path)
                        <img src="{{ asset($company->logo_path) }}" alt="Logo">
                    @endif
                </div>
                <div class="header-right">
                    <div class="doc-title">SLIP GAJI</div>
                    <div class="doc-number">{{ $payroll->payroll_code }}</div>
                    <small>Periode: {{ optional($payroll->period_start)->format('d M Y') }} - {{ optional($payroll->period_end)->format('d M Y') }}</small>
                </div>
            </div>

            <div class="row"><span class="label">Nama:</span> <span>{{ $payroll->employee?->fullname ?: '-' }}</span></div>
            <div class="row"><span class="label">Jabatan:</span> <span>{{ $payroll->employee?->role?->role_name ?: '-' }}</span></div>
            <div class="row"><span class="label">Kehadiran:</span> <span>{{ (int) $payroll->total_attendance }} Hari</span></div>

            <div class="divider"></div>

            <div class="row"><span class="label">Gaji Pokok:</span> <span class="nominal">Rp {{ number_format((float) $payroll->basic_salary, 0, ',', '.') }}</span></div>
            <div class="row"><span class="label">Tunjangan:</span> <span class="nominal">Rp {{ number_format((float) $payroll->allowance_total, 0, ',', '.') }}</span></div>
            <div class="row"><span class="label">Potongan:</span> <span class="nominal">(Rp {{ number_format((float) $payroll->deduction_total, 0, ',', '.') }})</span></div>

            <div class="total-box row">
                <span>TAKE HOME PAY:</span>
                <span>Rp {{ number_format((float) $payroll->net_salary, 0, ',', '.') }}</span>
            </div>

            @if($payroll->notes)
                <div style="margin-top:10px; padding:8px; border:1px solid #eee; background:#fafafa;">
                    <strong>Catatan:</strong><br>
                    {!! nl2br(e($payroll->notes)) !!}
                </div>
            @endif

            <div class="signature-section">
                <div class="sig-box">
                    <div class="sig-label">Diterima Oleh</div>
                    @if($payroll->employee?->signature_path)
                        <img src="{{ asset($payroll->employee->signature_path) }}" alt="Signature" class="sig-image">
                    @else
                        <div style="height:50px;"></div>
                    @endif
                    <div class="sig-name">{{ $payroll->employee?->fullname ?: '....................' }}</div>
                    <div class="sig-note">Karyawan</div>
                </div>
                <div class="sig-box">
                    <div class="sig-label">Dibuat Oleh</div>
                    @if($payroll->creator?->signature_path)
                        <img src="{{ asset($payroll->creator->signature_path) }}" alt="Signature" class="sig-image">
                    @else
                        <div style="height:50px;"></div>
                    @endif
                    <div class="sig-name">{{ $preparedBy ?: '....................' }}</div>
                    <div class="sig-note">HRD / Finance</div>
                </div>
            </div>
        </div>

        <div class="page-footer">
            <span class="footer-comp-name">{{ strtoupper($company->company_name ?? '-') }}</span>
            <span class="footer-addr">{{ $company->address ?? '-' }}</span>
        </div>
    </div>
</body>
</html>
