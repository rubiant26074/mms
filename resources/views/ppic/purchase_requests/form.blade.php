@extends('layouts.mms')

@section('title', $isEdit ? 'Edit Purchase Request' : 'Buat PR Baru')

@section('content')
@include('partials.alerts')
<form method="POST" action="{{ $isEdit ? route('ppic.purchase_requests.update', $pr) : route('ppic.purchase_requests.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <input type="hidden" name="spk_id" value="{{ $spkId }}">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">Header PR</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3"><label>No. PR</label><input type="text" class="form-control fw-bold" value="{{ $pr->pr_number }}" readonly></div>
                <div class="col-md-3 mb-3"><label>Tanggal Request</label><input type="date" name="pr_date" class="form-control" value="{{ old('pr_date', optional($pr->pr_date)->format('Y-m-d') ?: now()->toDateString()) }}" required></div>
                <div class="col-md-3 mb-3"><label>Tgl Dibutuhkan</label><input type="date" name="required_date" class="form-control" value="{{ old('required_date', optional($pr->required_date)->format('Y-m-d') ?: now()->addDays(3)->toDateString()) }}" required></div>
                <div class="col-md-3 mb-3"><label>Status</label><input type="text" class="form-control bg-light" value="{{ strtoupper($pr->status ?: 'draft') }}" readonly></div>
                @if(!$isEdit)
                    <div class="col-12 mb-3"><label class="fw-bold">Referensi SPK (Tarik BOM Otomatis)</label><select class="form-select" onchange="location.href='{{ route('ppic.purchase_requests.create') }}?spk_id='+this.value"><option value="">-- Tanpa Referensi SPK --</option>@foreach($spkOptions as $spk)<option value="{{ $spk->id }}" @selected($spkId === $spk->id)>{{ $spk->spk_number }} | {{ strtoupper($spk->status) }} | Deadline {{ optional($spk->deadline_date)->format('d/m/Y') }}</option>@endforeach</select><small class="text-muted">Jika dipilih, item akan ditarik dari kebutuhan material SPK.</small></div>
                @endif
                <div class="col-12"><label>Catatan / Alasan Pembelian</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', $pr->notes) }}</textarea></div>
            </div>
        </div>
    </div>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between"><strong>Daftar Barang yang Diminta</strong><button type="button" class="btn btn-sm btn-success" id="addRow">+ Tambah Item</button></div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0"><thead class="bg-light text-center"><tr><th width="40%">Nama Barang</th><th width="15%">Qty Request</th><th width="10%">Satuan</th><th>Keterangan</th><th width="5%">#</th></tr></thead><tbody id="prItems">
                @forelse($items as $itemRow)
                    @php $selectedItem = $itemRow->item ?? $rawMaterials->firstWhere('id', $itemRow->item_id); @endphp
                    <tr>
                        <td><select name="item_id[]" class="form-select" required><option value="{{ $itemRow->item_id }}">{{ $selectedItem?->item_code }} - {{ $selectedItem?->item_name }}</option>@foreach($rawMaterials as $rm)<option value="{{ $rm->id }}">{{ $rm->item_code }} - {{ $rm->item_name }}</option>@endforeach</select></td>
                        <td><input type="number" name="qty[]" class="form-control text-center fw-bold" value="{{ $itemRow->qty + 0 }}" step="0.01" required></td>
                        <td class="text-center">{{ $selectedItem?->unit ?: '-' }}</td>
                        <td><input type="text" name="item_notes[]" class="form-control" value="{{ $itemRow->notes }}"></td>
                        <td class="text-center"><button type="button" class="btn btn-danger btn-sm remove-row">X</button></td>
                    </tr>
                @empty
                    <tr>
                        <td><select name="item_id[]" class="form-select" required><option value="">-- Pilih Material --</option>@foreach($rawMaterials as $rm)<option value="{{ $rm->id }}">{{ $rm->item_code }} - {{ $rm->item_name }}</option>@endforeach</select></td>
                        <td><input type="number" name="qty[]" class="form-control text-center fw-bold" value="1" step="0.01" required></td><td class="text-center">-</td><td><input type="text" name="item_notes[]" class="form-control"></td><td class="text-center"><button type="button" class="btn btn-danger btn-sm remove-row">X</button></td>
                    </tr>
                @endforelse
            </tbody></table>
        </div>
        <div class="card-footer text-end"><a href="{{ route('ppic.purchase_requests.index') }}" class="btn btn-secondary">Batal</a><button class="btn btn-primary px-4">Simpan Request</button></div>
    </div>
</form>
@push('scripts')
<script>
(function(){const tbody=document.getElementById('prItems');document.getElementById('addRow')?.addEventListener('click',()=>{const row=tbody.querySelector('tr').cloneNode(true);row.querySelectorAll('input').forEach(i=>i.value=i.name==='qty[]'?'1':'');row.querySelectorAll('select').forEach(s=>s.value='');tbody.appendChild(row)});document.addEventListener('click',e=>{if(e.target.closest('.remove-row')&&tbody.querySelectorAll('tr').length>1)e.target.closest('tr').remove();});})();
</script>
@endpush
@endsection
