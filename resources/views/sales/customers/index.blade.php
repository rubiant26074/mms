@extends('layouts.mms')

@section('title', 'Master Customer')

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-people-fill"></i> Master Customer</h3>
        <p class="text-muted">Database Pelanggan</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="{{ route('sales.customers.create') }}" class="btn btn-primary shadow-sm"><i class="bi bi-person-plus-fill"></i> Tambah Customer</a>
        <button type="button" class="btn btn-outline-success shadow-sm ms-2" data-bs-toggle="modal" data-bs-target="#importCustomerModal"><i class="bi bi-file-earmark-excel"></i> Import Excel</button>
    </div>
</div>

<div class="modal fade" id="importCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-file-earmark-excel"></i> Import Master Customer (Excel)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small mb-3">
                    <strong>Format kolom:</strong> <code>customer_code, name, address, phone, pic, email, tax_id, sales_name</code>
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">File Excel (.xlsx/.xls/.csv)</label>
                        <input type="file" id="custExcelFile" class="form-control" accept=".xlsx,.xls,.csv">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mode Import</label>
                        <select id="custImportMode" class="form-select">
                            <option value="skip">Skip jika sudah ada</option>
                            <option value="update">Update jika sudah ada</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="button" id="btnCustImport" class="btn btn-success"><i class="bi bi-upload"></i> Mulai Import</button>
                    </div>
                </div>
                <div class="mt-2">
                    <a href="{{ asset('assets/templates/master_customer_template.xls') }}" class="btn btn-sm btn-outline-primary" download><i class="bi bi-download"></i> Download Template Excel</a>
                </div>
                <div id="custImportResult" class="mt-3"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="customer-filter-form">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari Kode / Nama / PIC / Telepon / Email..." value="{{ $search }}" autocomplete="off">
                </div>
            </div>
            <div class="col-md-3">
                <select name="sales" class="form-select" title="Filter Sales">
                    <option value="">Semua Sales</option>
                    @foreach($salesNames as $name)
                        <option value="{{ $name }}" @selected($salesFilter === $name)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('sales.customers.index') }}" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr><th width="15%">Kode</th><th width="25%">Nama Perusahaan</th><th width="20%">PIC / Kontak</th><th width="30%">Alamat / Email</th><th width="10%" class="text-center">Aksi</th></tr>
                </thead>
                <tbody>
                @forelse($customers as $row)
                    <tr>
                        <td><span class="badge bg-secondary">{{ $row->customer_code ?: '-' }}</span></td>
                        <td>
                            <div class="fw-bold text-primary">{{ $row->name }}</div>
                            <small class="text-muted d-block">Sales: {{ $row->sales_name ?: ($row->creator?->fullname ?: ($row->creator?->username ?: '-')) }}</small>
                        </td>
                        <td><strong>{{ $row->pic }}</strong><br><small class="text-muted"><i class="bi bi-telephone"></i> {{ $row->phone }}</small></td>
                        <td>
                            <small class="d-block text-truncate" style="max-width:250px" title="{{ $row->address }}"><i class="bi bi-geo-alt"></i> {{ $row->address }}</small>
                            @if($row->email)<small class="text-primary"><i class="bi bi-envelope"></i> {{ $row->email }}</small>@endif
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="{{ route('sales.customers.edit', $row) }}" class="btn btn-sm btn-warning text-dark" title="Edit"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('sales.customers.destroy', $row) }}" onsubmit="return confirm('Hapus customer ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada data customer.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
(function () {
    const form = document.getElementById('customer-filter-form');
    const search = form?.querySelector('input[name="search"]');
    const sales = form?.querySelector('select[name="sales"]');
    let t;
    search?.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => form.requestSubmit ? form.requestSubmit() : form.submit(), 400); });
    sales?.addEventListener('change', () => form.requestSubmit ? form.requestSubmit() : form.submit());
})();
function normalizeHeader(val) { return String(val || '').toLowerCase().trim().replace(/\s+/g, '_'); }
function detectHeaderRow(row) { return row.map(normalizeHeader).includes('name'); }
document.getElementById('btnCustImport')?.addEventListener('click', function () {
    const fileInput = document.getElementById('custExcelFile');
    const resultBox = document.getElementById('custImportResult');
    if (!fileInput.files || !fileInput.files[0]) { alert('Pilih file Excel terlebih dahulu.'); return; }
    const reader = new FileReader();
    reader.onload = function (e) {
        const workbook = XLSX.read(e.target.result, { type: 'array' });
        const rows = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]], { header: 1, defval: '' });
        const hasHeader = detectHeaderRow(rows[0] || []);
        const headerMap = {};
        if (hasHeader) rows[0].forEach((h, idx) => { const key = normalizeHeader(h); if (key) headerMap[key] = idx; });
        else ['customer_code','name','address','phone','pic','email','tax_id','sales_name'].forEach((k, i) => headerMap[k] = i);
        const items = [];
        for (let i = hasHeader ? 1 : 0; i < rows.length; i++) {
            const obj = {};
            Object.entries(headerMap).forEach(([key, idx]) => obj[key] = rows[i][idx] ?? '');
            if (obj.name) items.push(obj);
        }
        fetch('{{ route('sales.customers.import_ajax') }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({ rows: items, mode: document.getElementById('custImportMode').value })
        }).then(r => r.json()).then(res => {
            resultBox.innerHTML = `<div class="alert alert-success">Import selesai. Inserted: <b>${res.inserted}</b>, Updated: <b>${res.updated}</b>, Skipped: <b>${res.skipped}</b>.</div>`;
        }).catch(err => resultBox.innerHTML = `<div class="alert alert-danger">Error: ${err}</div>`);
    };
    reader.readAsArrayBuffer(fileInput.files[0]);
});
</script>
@endpush
@endsection
