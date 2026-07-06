@extends('layouts.mms')

@section('title', $isEdit ? 'Edit PO' : 'Buat PO')

@section('content')
@include('partials.alerts')
<form method="POST" action="{{ $isEdit ? route('procurement.orders.update', $order) : route('procurement.orders.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">Header PO</div>
                <div class="card-body">
                    <div class="mb-2"><label class="form-label">No. PO</label><input type="text" class="form-control fw-bold" value="{{ $order->po_number }}" readonly></div>
                    <div class="mb-2"><label class="form-label">Supplier <span class="text-danger">*</span></label><select name="supplier_id" class="form-select" required><option value="">-- Pilih Supplier --</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((int) old('supplier_id', $order->supplier_id) === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
                    <div class="mb-2">
                        <label class="form-label">Referensi PR</label>
                        <select name="purchase_request_id" class="form-select" onchange="if(this.value&&!{{ $isEdit ? 'true' : 'false' }}) window.location.href='{{ route('procurement.orders.create') }}?pr_id='+encodeURIComponent(this.value)">
                            <option value="">-- Tanpa PR (Direct) --</option>
                            @foreach($prs as $pr)<option value="{{ $pr->id }}" @selected((int) old('purchase_request_id', $order->purchase_request_id) === $pr->id)>{{ $pr->pr_number }}</option>@endforeach
                        </select>
                        <small class="text-muted">Pilih PR untuk tarik item sisa.</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">Info Pengiriman & Bayar</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-4 mb-2"><label class="form-label">Tgl PO</label><input type="date" name="po_date" class="form-control" value="{{ old('po_date', optional($order->po_date)->format('Y-m-d') ?: now()->toDateString()) }}" required></div>
                        <div class="col-4 mb-2"><label class="form-label">Tgl Kirim (Est)</label><input type="date" name="delivery_date" class="form-control" value="{{ old('delivery_date', optional($order->delivery_date)->format('Y-m-d')) }}"></div>
                        <div class="col-4 mb-2"><label class="form-label">Terms</label><input type="text" name="payment_terms" class="form-control" value="{{ old('payment_terms', $order->payment_terms) }}"></div>
                    </div>
                    <div class="mb-2"><label class="form-label">Catatan</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', $order->notes) }}</textarea></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between"><strong>Detail Item</strong><button type="button" class="btn btn-sm btn-success" onclick="addItemRow()">+ Item</button></div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-bordered mb-0">
                <thead class="bg-light"><tr><th width="35%">Barang</th><th width="10%">Qty</th><th width="20%">Harga Satuan</th><th width="20%">Subtotal</th><th width="5%"></th></tr></thead>
                <tbody id="poItems">
                @foreach($items as $row)
                    <tr>
                        <td>
                            <select name="item_id[]" class="form-select mb-1" required>
                                <option value="{{ $row->item_id }}">{{ $row->item?->item_code }} - {{ $row->item?->item_name }}</option>
                                @foreach($rawMaterials as $rm)<option value="{{ $rm->id }}">{{ $rm->item_code }} - {{ $rm->item_name }}</option>@endforeach
                            </select>
                            <input type="text" name="item_notes[]" class="form-control form-control-sm" placeholder="Catatan" value="{{ $row->notes }}">
                            <input type="hidden" name="pr_item_ref_id[]" value="{{ $row->pr_item_id }}">
                        </td>
                        <td><input type="number" name="qty[]" class="form-control qty" value="{{ $row->qty + 0 }}" step="0.01" oninput="calcTotal()" required></td>
                        <td><input type="number" name="price[]" class="form-control price text-end" value="{{ $row->unit_price + 0 }}" step="0.01" oninput="calcTotal()" required></td>
                        <td><input type="text" class="form-control subtotal text-end bg-light" value="{{ number_format((float) $row->subtotal, 0, ',', '.') }}" readonly></td>
                        <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            <div class="row justify-content-end"><div class="col-md-4">
                <div class="d-flex justify-content-between mb-2"><span>Total Bruto:</span><strong id="totalBruto">0</strong></div>
                <div class="d-flex justify-content-between mb-2 align-items-center"><span>Diskon (Rp):</span><input type="text" name="discount_amount" id="discAmount" class="form-control form-control-sm text-end w-50" value="{{ number_format((float) old('discount_amount', $order->discount_amount), 0, ',', '.') }}" onkeyup="formatRibuan(this); calcTotal()"></div>
                <div class="d-flex justify-content-between mb-2 align-items-center"><span>PPN (%):</span><input type="number" name="ppn_percent" id="ppnPercent" class="form-control form-control-sm text-end w-50" value="{{ old('ppn_percent', $order->ppn_percent + 0) }}" oninput="calcTotal()"></div>
                <hr><div class="d-flex justify-content-between"><span class="fs-5 fw-bold">Grand Total:</span><span class="fs-5 fw-bold text-primary" id="grandTotal">0</span></div>
            </div></div>
        </div>
    </div>
    <div class="d-flex justify-content-between mb-5"><a href="{{ route('procurement.orders.index') }}" class="btn btn-secondary">Kembali</a><button type="submit" class="btn btn-primary btn-lg">Simpan PO</button></div>
</form>

@push('scripts')
<script>
const rawMaterials=@json($rawMaterials);
const escHtml=(v)=>String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
function addItemRow(){let opts='<option value="">-- Pilih --</option>';rawMaterials.forEach(rm=>{opts+='<option value="'+rm.id+'">'+escHtml(rm.item_code)+' - '+escHtml(rm.item_name)+'</option>';});document.getElementById('poItems').insertAdjacentHTML('beforeend','<tr><td><select name="item_id[]" class="form-select mb-1" required>'+opts+'</select><input type="text" name="item_notes[]" class="form-control form-control-sm" placeholder="Catatan"><input type="hidden" name="pr_item_ref_id[]" value=""></td><td><input type="number" name="qty[]" class="form-control qty" value="1" step="0.01" oninput="calcTotal()" required></td><td><input type="number" name="price[]" class="form-control price text-end" value="0" step="0.01" oninput="calcTotal()" required></td><td><input type="text" class="form-control subtotal text-end bg-light" value="0" readonly></td><td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td></tr>');calcTotal();}
function removeRow(btn){btn.closest('tr').remove();calcTotal();}
function formatRibuan(input){let val=input.value.replace(/[^0-9]/g,'');input.value=new Intl.NumberFormat('id-ID').format(val);}
function calcTotal(){let bruto=0;document.querySelectorAll('#poItems tr').forEach(row=>{let qty=parseFloat(row.querySelector('.qty').value)||0;let price=parseFloat(row.querySelector('.price').value)||0;let sub=qty*price;row.querySelector('.subtotal').value=new Intl.NumberFormat('id-ID').format(sub);bruto+=sub;});let disc=parseFloat(document.getElementById('discAmount').value.replace(/\./g,''))||0;let ppn=parseFloat(document.getElementById('ppnPercent').value)||0;let dpp=Math.max(bruto-disc,0);let grand=dpp+(dpp*(ppn/100));document.getElementById('totalBruto').innerText='Rp '+new Intl.NumberFormat('id-ID').format(bruto);document.getElementById('grandTotal').innerText='Rp '+new Intl.NumberFormat('id-ID').format(grand);}
window.addEventListener('load',()=>{if(!document.querySelector('#poItems tr')) addItemRow(); else calcTotal();});
</script>
@endpush
@endsection
