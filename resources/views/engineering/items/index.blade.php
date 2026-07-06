@extends('layouts.mms')

@section('title', 'Master Barang')

@php
    $badges = ['raw_material' => 'bg-primary', 'finish_good' => 'bg-success', 'wip' => 'bg-warning text-dark', 'consumable' => 'bg-secondary'];
@endphp

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-box-seam"></i> Master Barang</h3>
        <p class="text-muted">Database Material, Sparepart, dan Finish Good.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="{{ route('engineering.items.create') }}" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Tambah Barang</a>
        <button type="button" class="btn btn-outline-success shadow-sm ms-2" data-bs-toggle="modal" data-bs-target="#importItemsModal"><i class="bi bi-file-earmark-excel"></i> Import Excel</button>
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="item-filter-form">
            <div class="col-md-4"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="text" name="search" class="form-control" placeholder="Cari Kode atau Nama Barang..." value="{{ $search }}" autocomplete="off"></div></div>
            <div class="col-md-3">
                <select name="filter_type" class="form-select">
                    <option value="">- Semua Tipe -</option>
                    <option value="raw_material" @selected($type === 'raw_material')>Raw Material</option>
                    <option value="wip" @selected($type === 'wip')>WIP (Setengah Jadi)</option>
                    <option value="finish_good" @selected($type === 'finish_good')>Finish Good</option>
                    <option value="consumable" @selected($type === 'consumable')>Consumable</option>
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('engineering.items.index') }}" class="btn btn-outline-secondary w-100" title="Reset"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-4">Kode Barang</th><th>Nama Barang / Spesifikasi</th><th>Tipe / Owner</th><th class="text-center">Stok</th><th class="text-center">Satuan</th>@if($canSeePrice)<th class="text-end">Harga Dasar</th>@endif<th class="text-center">Aksi</th></tr></thead>
                <tbody>
                @forelse($items as $row)
                    @php
                        $lowStock = (float) $row->current_stock <= (float) $row->min_stock;
                        $drawing = preg_match('/^(uploads\/|https?:\/\/)/i', (string) $row->drawing_file) ? (string) $row->drawing_file : '';
                    @endphp
                    <tr>
                        <td class="ps-4 fw-bold text-primary">{{ $row->item_code }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div>{{ $row->item_name }}</div>
                                @if($drawing !== '')<a href="{{ preg_match('/^https?:\/\//i', $drawing) ? $drawing : asset($drawing) }}" target="_blank" rel="noopener noreferrer" class="ms-2 text-danger" title="Lihat Drawing"><i class="bi bi-file-earmark-pdf-fill fs-5"></i></a>@endif
                            </div>
                            @if($row->description)<small class="text-muted d-block text-truncate" style="max-width:250px">{{ $row->description }}</small>@endif
                        </td>
                        <td><span class="badge {{ $badges[$row->item_type] ?? 'bg-light text-dark' }}">{{ ucwords(str_replace('_', ' ', $row->item_type)) }}</span>@if($row->ownership === 'customer') <span class="badge bg-info text-dark border ms-1">Consignment</span>@endif</td>
                        <td class="text-center {{ $lowStock ? 'text-danger fw-bold' : 'text-dark fw-bold' }}">{{ $row->current_stock + 0 }} @if($lowStock)<i class="bi bi-exclamation-circle-fill ms-1" title="Stok Menipis"></i>@endif</td>
                        <td class="text-center">{{ $row->unit }}</td>
                        @if($canSeePrice)<td class="text-end text-success fw-bold">Rp {{ number_format((float) $row->base_price, 0, ',', '.') }}</td>@endif
                        <td class="text-center"><div class="btn-group"><a href="{{ route('engineering.items.edit', $row) }}" class="btn btn-sm btn-outline-warning text-dark"><i class="bi bi-pencil"></i></a><form method="POST" action="{{ route('engineering.items.destroy', $row) }}" onsubmit="return confirm('Hapus barang {{ $row->item_code }}?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></div></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $canSeePrice ? 7 : 6 }}" class="text-center py-5 text-muted">Data tidak ditemukan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="importItemsModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="bi bi-file-earmark-excel"></i> Import Master Barang (Excel)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="alert alert-info small mb-3"><strong>Format kolom:</strong> <code>item_code, item_name, item_type, ownership, qc_type, unit, base_price, min_stock, description, customer_code, customer_name, drawing_file</code></div>
            <div class="row g-2 align-items-end">
                <div class="col-md-6"><label class="form-label">File Excel (.xlsx/.xls/.csv)</label><input type="file" id="itemsExcelFile" class="form-control" accept=".xlsx,.xls,.csv"></div>
                <div class="col-md-3"><label class="form-label">Mode Import</label><select id="itemsImportMode" class="form-select"><option value="skip">Skip jika kode sudah ada</option><option value="update">Update jika kode sudah ada</option></select></div>
                <div class="col-md-3 d-grid"><button type="button" id="btnItemsImport" class="btn btn-success"><i class="bi bi-upload"></i> Mulai Import</button></div>
            </div>
            <div class="mt-2"><a href="{{ asset('assets/templates/master_barang_template.xls') }}" class="btn btn-sm btn-outline-primary" download><i class="bi bi-download"></i> Download Template Excel</a></div>
            <div id="itemsImportResult" class="mt-3"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button></div>
    </div></div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
(function () {
    const form = document.getElementById('item-filter-form');
    const search = form?.querySelector('input[name="search"]');
    const type = form?.querySelector('select[name="filter_type"]');
    let t;
    const submit = () => form.requestSubmit ? form.requestSubmit() : form.submit();
    search?.addEventListener('input', () => { clearTimeout(t); t = setTimeout(submit, 400); });
    type?.addEventListener('change', submit);
})();
function normalizeHeader(val) { return String(val || '').toLowerCase().trim().replace(/\s+/g, '_'); }
function detectHeaderRow(row) { const headers = row.map(normalizeHeader); return headers.includes('item_code') && headers.includes('item_name'); }
document.getElementById('btnItemsImport')?.addEventListener('click', function () {
    const fileInput = document.getElementById('itemsExcelFile');
    const resultBox = document.getElementById('itemsImportResult');
    if (!fileInput.files || !fileInput.files[0]) { alert('Pilih file Excel terlebih dahulu.'); return; }
    const reader = new FileReader();
    reader.onload = function (e) {
        const workbook = XLSX.read(e.target.result, { type: 'array' });
        const rows = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]], { header: 1, defval: '' });
        const headers = {};
        const hasHeader = detectHeaderRow(rows[0] || []);
        if (hasHeader) rows[0].forEach((h, i) => { const key = normalizeHeader(h); if (key) headers[key] = i; });
        else ['item_code','item_name','item_type','ownership','qc_type','unit','base_price','min_stock','description','customer_code','customer_name','drawing_file'].forEach((k, i) => headers[k] = i);
        const data = [];
        for (let i = hasHeader ? 1 : 0; i < rows.length; i++) {
            const obj = {};
            Object.entries(headers).forEach(([k, idx]) => obj[k] = rows[i][idx] ?? '');
            if (obj.item_code || obj.item_name) data.push(obj);
        }
        fetch('{{ route('engineering.items.import_ajax') }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({ rows: data, mode: document.getElementById('itemsImportMode').value })
        }).then(r => r.json()).then(res => {
            let html = `<div class="alert alert-success">Import selesai. Inserted: <b>${res.inserted}</b>, Updated: <b>${res.updated}</b>, Skipped: <b>${res.skipped}</b>.</div>`;
            if (res.errors?.length) html += `<div class="alert alert-warning"><ul class="mb-0">${res.errors.map(e => `<li>${String(e).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}</li>`).join('')}</ul></div>`;
            resultBox.innerHTML = html;
        }).catch(err => resultBox.innerHTML = `<div class="alert alert-danger">Error: ${err}</div>`);
    };
    reader.readAsArrayBuffer(fileInput.files[0]);
});
</script>
@endpush
@endsection
