@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Penerimaan' : 'Input Penerimaan Material')

@section('content')
@include('partials.alerts')
<form method="POST" id="formGR" action="{{ $isEdit ? route('warehouse.receipts.update', $receipt) : route('warehouse.receipts.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="card shadow-sm mb-3 border-primary">
        <div class="card-body">
            <label class="fw-bold mb-2">Sumber Material:</label>
            <div class="d-flex gap-4 flex-wrap">
                <div class="form-check"><input class="form-check-input" type="radio" name="receipt_type" id="typeNormal" value="normal" @checked(old('receipt_type', $receipt->receipt_type) === 'normal') onchange="toggleType()"><label class="form-check-label fw-bold" for="typeNormal"><i class="bi bi-building"></i> Internal / Pembelian (NORMAL)<div class="text-muted small">Wajib Referensi PO Supplier</div></label></div>
                <div class="form-check"><input class="form-check-input" type="radio" name="receipt_type" id="typeConsign" value="consignment" @checked(old('receipt_type', $receipt->receipt_type) === 'consignment') onchange="toggleType()"><label class="form-check-label fw-bold" for="typeConsign"><i class="bi bi-person-workspace"></i> Dari Customer (CONSIGNMENT)<div class="text-muted small">Tanpa PO Internal, Barang Titipan</div></label></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white"><h5 class="mb-0">Informasi Logistik</h5></div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">No. GR (Internal)</label><input type="text" class="form-control fw-bold bg-light" value="{{ $receipt->gr_number }}" readonly></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">No. Surat Jalan <span class="text-danger">*</span></label><input type="text" name="delivery_note_number" class="form-control" value="{{ old('delivery_note_number', $receipt->delivery_note_number) }}" required placeholder="Nomor dari Supplier"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Tanggal Terima</label><input type="date" name="gr_date" class="form-control" value="{{ old('gr_date', optional($receipt->gr_date)->format('Y-m-d') ?: now()->toDateString()) }}" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Nama Sopir</label><input type="text" name="driver_name" class="form-control" value="{{ old('driver_name', $receipt->driver_name) }}" placeholder="Nama Pengantar"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Plat Nomor</label><input type="text" name="vehicle_number" class="form-control" value="{{ old('vehicle_number', $receipt->vehicle_number) }}" placeholder="B 1234 XXX"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Diterima Oleh (Gudang)</label><input type="text" name="received_by" class="form-control" value="{{ old('received_by', $receipt->received_by) }}"></div>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-dark">Referensi Asal Barang</div>
                <div class="card-body">
                    <div id="sectionPO">
                        <div class="mb-3"><label class="fw-bold">Pilih Purchase Order (PO) <span class="text-danger">*</span></label><select name="purchase_order_id" id="poSelect" class="form-select form-select-lg" onchange="loadPOItems(this.value)"><option value="">-- Pilih PO --</option>@foreach($pos as $po)<option value="{{ $po->id }}" @selected((int) old('purchase_order_id', $receipt->purchase_order_id) === $po->id)>{{ $po->po_number }} - {{ $po->supplier_name }}</option>@endforeach</select><div class="form-text">Daftar barang akan otomatis dimuat dari PO.</div></div>
                    </div>
                    <div id="sectionCust">
                        <div class="mb-3"><label class="fw-bold">Pilih Customer Pengirim <span class="text-danger">*</span></label><select name="customer_id" id="custSelect" class="form-select form-select-lg"><option value="">-- Pilih Customer --</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected((int) old('customer_id', $receipt->customer_id) === $c->id)>{{ $c->name }}</option>@endforeach</select><div class="form-text">Silakan input item barang secara manual di bawah.</div></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Catatan Penerimaan</label><textarea name="notes" class="form-control" rows="3" placeholder="Kondisi barang saat diterima, dll...">{{ old('notes', $receipt->notes) }}</textarea></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center"><h6 class="mb-0 fw-bold"><i class="bi bi-list-check"></i> Checklist Barang Datang</h6><button type="button" id="btnAddManual" class="btn btn-sm btn-success" onclick="addManualItem()"><i class="bi bi-plus-lg"></i> Tambah Item</button></div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-bordered table-striped mb-0 align-middle">
                <thead class="table-light"><tr><th width="35%">Nama Barang</th><th width="10%">Satuan</th><th width="15%" class="text-center col-po-qty">Qty PO</th><th width="15%" class="text-center">Sisa PO</th><th width="15%" class="text-center bg-warning bg-opacity-10">Qty Diterima</th><th width="10%">Hapus</th></tr></thead>
                <tbody id="grItems">
                @forelse($items as $row)
                    <tr>
                        <td>
                            @if($receipt->receipt_type === 'normal')
                                <strong>{{ $row->item?->item_name }}</strong><br><small class="text-muted">{{ $row->item?->item_code }}</small><input type="hidden" name="item_id[]" value="{{ $row->item_id }}">
                            @else
                                <select name="item_id[]" class="form-select">@foreach($rawMaterials as $rm)<option value="{{ $rm->id }}" @selected($rm->id === $row->item_id)>{{ $rm->item_code }} - {{ $rm->item_name }}</option>@endforeach</select>
                            @endif
                        </td>
                        <td class="unit-cell">{{ $row->item?->unit }}</td>
                        <td class="text-center col-po-qty"><input type="text" name="qty_po[]" class="form-control form-control-sm text-center bg-light" value="{{ $row->qty_po + 0 }}" readonly></td>
                        <td class="text-center text-muted">-</td>
                        <td class="bg-warning bg-opacity-10"><input type="number" name="qty_received[]" class="form-control form-control-sm text-center fw-bold border-warning" value="{{ $row->qty_received + 0 }}" step="0.01" required></td>
                        <td><button type="button" class="btn btn-danger btn-sm delete-row" onclick="this.closest('tr').remove()">X</button></td>
                    </tr>
                @empty
                    <tr id="emptyRow"><td colspan="6" class="text-center py-5 text-muted">Silakan pilih Referensi di atas...</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-muted">* <strong>Qty Diterima</strong> adalah jumlah fisik yang dihitung oleh gudang.<br>* Jika barang datang parsial, input sesuai yang datang saja. Sisa PO akan tetap terbuka.</div>
    </div>
    <div class="d-flex justify-content-between mb-5"><a href="{{ route('warehouse.receipts.index') }}" class="btn btn-secondary btn-lg px-4">Kembali</a><div><button type="submit" name="save_draft" class="btn btn-outline-warning btn-lg me-2">Simpan Draft</button><button type="submit" name="submit_qc" class="btn btn-primary btn-lg px-4 shadow"><i class="bi bi-check-circle"></i> Simpan & Ajukan QC</button></div></div>
</form>

@push('scripts')
<script>
const masterItems=@json($rawMaterials);
function toggleType(){const type=document.querySelector('input[name="receipt_type"]:checked').value;const sectionPO=document.getElementById('sectionPO');const sectionCust=document.getElementById('sectionCust');const poSelect=document.getElementById('poSelect');const custSelect=document.getElementById('custSelect');const btnAdd=document.getElementById('btnAddManual');const cols=document.querySelectorAll('.col-po-qty');if(type==='normal'){sectionPO.classList.remove('d-none');sectionCust.classList.add('d-none');poSelect.setAttribute('required','required');custSelect.removeAttribute('required');btnAdd.classList.add('d-none');cols.forEach(el=>el.classList.remove('d-none'));}else{sectionPO.classList.add('d-none');sectionCust.classList.remove('d-none');poSelect.removeAttribute('required');custSelect.setAttribute('required','required');btnAdd.classList.remove('d-none');cols.forEach(el=>el.classList.add('d-none'));}}
function loadPOItems(poId){const tbody=document.getElementById('grItems');if(!poId){tbody.innerHTML='<tr><td colspan="6" class="text-center py-5 text-muted">Silakan pilih PO di atas...</td></tr>';return;}tbody.innerHTML='<tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-primary"></div> Memuat detail PO...</td></tr>';fetch('{{ url('/warehouse/receipts/po-items') }}/'+poId).then(res=>res.json()).then(data=>{if(data.length===0){tbody.innerHTML='<tr><td colspan="6" class="text-center py-5 text-danger">PO ini tidak memiliki item atau sudah selesai diterima semua.</td></tr>';return;}let rows='';data.forEach(item=>{let qtyOrder=parseFloat(item.qty);let qtyReceivedTotal=parseFloat(item.total_received||0);let qtyRemaining=qtyOrder-qtyReceivedTotal;let sisaClass=qtyRemaining<=0?'text-success fw-bold':'text-danger fw-bold';let sisaText=qtyRemaining<=0?'LUNAS':qtyRemaining;let defaultInput=qtyRemaining>0?qtyRemaining:0;rows+=`<tr><td><strong class="text-dark">${item.item_name}</strong><br><small class="text-muted">${item.item_code}</small><input type="hidden" name="item_id[]" value="${item.item_id}"></td><td class="unit-cell">${item.unit||'-'}</td><td class="text-center col-po-qty"><span class="badge bg-secondary fs-6">${qtyOrder}</span><input type="hidden" name="qty_po[]" value="${qtyOrder}"></td><td class="text-center"><span class="${sisaClass}">${sisaText}</span>${qtyReceivedTotal>0?`<br><small class="text-muted">Sudah: ${qtyReceivedTotal}</small>`:''}</td><td class="bg-warning bg-opacity-10"><input type="number" name="qty_received[]" class="form-control form-control-sm text-center fw-bold border-warning" value="${defaultInput}" step="0.01" min="0" required></td><td></td></tr>`;});tbody.innerHTML=rows;}).catch(()=>tbody.innerHTML='<tr><td colspan="6" class="text-center py-5 text-danger">Terjadi kesalahan saat memuat data.</td></tr>');}
function addManualItem(){document.getElementById('emptyRow')?.remove();let opts='<option value="">-- Pilih Barang --</option>';masterItems.forEach(i=>opts+=`<option value="${i.id}" data-unit="${i.unit||'-'}">${i.item_code} - ${i.item_name}</option>`);document.getElementById('grItems').insertAdjacentHTML('beforeend',`<tr><td><select name="item_id[]" class="form-select" required onchange="updateUnit(this)">${opts}</select></td><td class="unit-cell">-</td><td class="text-center col-po-qty d-none"><input type="hidden" name="qty_po[]" value="0">-</td><td class="text-center text-muted">-</td><td class="bg-warning bg-opacity-10"><input type="number" name="qty_received[]" class="form-control form-control-sm text-center fw-bold border-warning" value="1" step="0.01" required></td><td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button></td></tr>`);}
function updateUnit(select){select.closest('tr').querySelector('.unit-cell').innerText=select.options[select.selectedIndex].getAttribute('data-unit')||'-';}
window.addEventListener('load',toggleType);
</script>
@endpush
@endsection
