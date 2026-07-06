@extends('layouts.mms')

@section('title', 'Master Supplier')

@section('content')
@include('partials.alerts')
<div class="row mb-3">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="bi bi-truck"></i> Master Supplier / Vendor</h3>
        <p class="text-muted">Database vendor pembelian material dan jasa.</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="{{ route('procurement.suppliers.create') }}" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Tambah Supplier</a>
        <button type="button" class="btn btn-outline-success shadow-sm ms-2" data-bs-toggle="modal" data-bs-target="#importSupplierModal"><i class="bi bi-file-earmark-excel"></i> Import Excel</button>
    </div>
</div>

<div class="card shadow-sm mb-4 border-start border-4 border-primary">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center" id="supplier-filter-form">
            <div class="col-md-5"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="text" name="search" class="form-control" placeholder="Cari Kode / Nama / Kontak / Telp / Bank..." value="{{ $search }}" autocomplete="off"></div></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="{{ route('procurement.suppliers.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light"><tr><th>Kode</th><th>Nama Supplier</th><th>Kontak</th><th>Bank</th><th class="text-center">Aksi</th></tr></thead>
            <tbody>
            @forelse($suppliers as $row)
                <tr>
                    <td class="fw-bold text-primary">{{ $row->code }}</td>
                    <td><strong>{{ $row->name }}</strong><br><small class="text-muted">{{ \Illuminate\Support\Str::limit($row->address, 45) }}</small></td>
                    <td><strong>{{ $row->contact_person }}</strong><br><small><i class="bi bi-telephone"></i> {{ $row->phone }}</small>@if($row->email)<br><small class="text-primary">{{ $row->email }}</small>@endif</td>
                    <td>@if($row->bank_name)<small class="d-block text-muted">{{ $row->bank_name }}</small><strong>{{ $row->bank_number }}</strong>@else - @endif</td>
                    <td class="text-center"><div class="btn-group"><a href="{{ route('procurement.suppliers.edit', $row) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a><form method="POST" action="{{ route('procurement.suppliers.destroy', $row) }}" onsubmit="return confirm('Hapus supplier {{ $row->name }}?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></div></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center py-5 text-muted">Data supplier belum ada.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="importSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="bi bi-file-earmark-excel"></i> Import Master Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="alert alert-info small mb-3"><strong>Format kolom:</strong> <code>code, name, address, phone, email, contact_person, bank_name, bank_number</code></div>
            <div class="row g-2 align-items-end">
                <div class="col-md-6"><label class="form-label">File Excel (.xlsx/.xls/.csv)</label><input type="file" id="supplierExcelFile" class="form-control" accept=".xlsx,.xls,.csv"></div>
                <div class="col-md-3"><label class="form-label">Mode Import</label><select id="supplierImportMode" class="form-select"><option value="skip">Skip jika sudah ada</option><option value="update">Update jika sudah ada</option></select></div>
                <div class="col-md-3 d-grid"><button type="button" id="btnSupplierImport" class="btn btn-success"><i class="bi bi-upload"></i> Mulai Import</button></div>
            </div>
            <div id="supplierImportResult" class="mt-3"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button></div>
    </div></div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
(function(){const form=document.getElementById('supplier-filter-form');const search=form?.querySelector('input[name="search"]');let t;search?.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>form.requestSubmit?form.requestSubmit():form.submit(),400)});})();
function normalizeHeader(v){return String(v||'').toLowerCase().trim().replace(/\s+/g,'_');}
document.getElementById('btnSupplierImport')?.addEventListener('click',function(){
    const input=document.getElementById('supplierExcelFile');const box=document.getElementById('supplierImportResult');
    if(!input.files||!input.files[0]){alert('Pilih file Excel terlebih dahulu.');return;}
    const reader=new FileReader();
    reader.onload=function(e){const wb=XLSX.read(e.target.result,{type:'array'});const rows=XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]],{header:1,defval:''});const first=rows[0]||[];const hasHeader=first.map(normalizeHeader).includes('name');const map={};if(hasHeader)first.forEach((h,i)=>{const k=normalizeHeader(h);if(k)map[k]=i;});else ['code','name','address','phone','email','contact_person','bank_name','bank_number'].forEach((k,i)=>map[k]=i);const data=[];for(let i=hasHeader?1:0;i<rows.length;i++){const o={};Object.entries(map).forEach(([k,idx])=>o[k]=rows[i][idx]??'');if(o.name)data.push(o);}fetch('{{ route('procurement.suppliers.import_ajax') }}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({rows:data,mode:document.getElementById('supplierImportMode').value})}).then(r=>r.json()).then(res=>{box.innerHTML=`<div class="alert alert-success">Import selesai. Inserted: <b>${res.inserted}</b>, Updated: <b>${res.updated}</b>, Skipped: <b>${res.skipped}</b>.</div>`;}).catch(err=>box.innerHTML=`<div class="alert alert-danger">Error: ${err}</div>`);};
    reader.readAsArrayBuffer(input.files[0]);
});
</script>
@endpush
@endsection
