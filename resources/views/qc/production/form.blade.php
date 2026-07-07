@extends('layouts.mms')

@section('title', 'Input Hasil QC Produksi')

@section('content')
@include('partials.alerts')
@php($grandChecked = $totalCheckedPreviously)
<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="bi bi-check2-all"></i> Form QC Final</h5></div>
            <div class="card-body">
                <div class="row mb-3 bg-light p-3 rounded border mx-0">
                    <div class="col-md-4"><label class="small text-muted">No. SPK</label><div class="fw-bold">{{ $spk->spk_number }}</div></div>
                    <div class="col-md-4"><label class="small text-muted">Project</label><div class="fw-bold">{{ $spk->project_name ?: '-' }}</div></div>
                    <div class="col-md-4 text-md-end"><span class="badge bg-warning text-dark">Total QC Sebelumnya:</span> <strong>{{ $grandChecked + 0 }} Pcs</strong></div>
                </div>

                <form method="POST" action="{{ route('qc.production.store', $spk) }}">
                    @csrf
                    @if($isSheetMetal)
                        <div class="card mb-4 border-info">
                            <div class="card-header bg-info text-dark fw-bold"><i class="bi bi-list-check"></i> Standard Checklist: Sheet Metal Process</div>
                            <div class="card-body bg-light">
                                <div class="row">
                                    <div class="col-md-4"><h6 class="text-primary border-bottom pb-1">Laser Cutting</h6>@foreach(['Clean Cut / No Dross','Dimensi Akurat','No Scratch'] as $idx => $label)<div class="form-check"><input class="form-check-input" type="checkbox" name="chk_laser[]" value="{{ $label }}" id="lc{{ $idx }}"><label class="form-check-label" for="lc{{ $idx }}">{{ $label }}</label></div>@endforeach</div>
                                    <div class="col-md-4"><h6 class="text-warning text-dark border-bottom pb-1">Bending</h6>@foreach(['Sudut Sesuai','Panjang Tekuk Sesuai','No Cracking'] as $idx => $label)<div class="form-check"><input class="form-check-input" type="checkbox" name="chk_bend[]" value="{{ $label }}" id="bd{{ $idx }}"><label class="form-check-label" for="bd{{ $idx }}">{{ $label }}</label></div>@endforeach</div>
                                    <div class="col-md-4"><h6 class="text-danger border-bottom pb-1">Welding / Assembling</h6>@foreach(['Las Matang/Kuat','Finishing Rapi','Posisi Akurat'] as $idx => $label)<div class="form-check"><input class="form-check-input" type="checkbox" name="chk_weld[]" value="{{ $label }}" id="wd{{ $idx }}"><label class="form-check-label" for="wd{{ $idx }}">{{ $label }}</label></div>@endforeach</div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light text-center"><tr><th width="30%">Nama Barang</th><th>Target</th><th>Sisa</th><th>Cek Sekarang</th><th class="text-success">OK</th><th class="text-danger">NG</th></tr></thead>
                            <tbody>
                            @foreach($products as $idx => $product)
                                @php($remaining = max(0, (float) $product['plan_qty'] - $grandChecked))
                                <tr>
                                    <td><strong>{{ $product['item_name'] }}</strong><br><small class="text-muted">{{ $product['item_code'] }}</small><input type="hidden" name="item_id[]" value="{{ $product['item_id'] }}">@if($product['qc_type'] === 'sheet_metal') <span class="badge bg-secondary" style="font-size:.6rem">Sheet Metal</span>@endif</td>
                                    <td class="text-center bg-light">{{ (float) $product['plan_qty'] + 0 }}</td>
                                    <td class="text-center fw-bold">{{ $remaining + 0 }}</td>
                                    <td><input type="number" name="qty_check[]" class="form-control text-center border-primary js-check" value="{{ $remaining + 0 }}" min="0" step="0.01" data-idx="{{ $idx }}" required></td>
                                    <td><input type="number" name="qty_pass[]" class="form-control text-center fw-bold text-success js-ok" value="{{ $remaining + 0 }}" min="0" step="0.01" data-idx="{{ $idx }}" required></td>
                                    <td><input type="number" class="form-control text-center fw-bold text-danger bg-light js-ng" value="0" readonly data-idx="{{ $idx }}"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mb-3"><label class="form-label fw-bold">Catatan Inspeksi</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
                    <div class="d-flex justify-content-between"><a href="{{ route('qc.production.index') }}" class="btn btn-secondary px-4">Batal</a><button class="btn btn-success px-4 fw-bold shadow"><i class="bi bi-save"></i> Simpan Hasil</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function(){function recalc(idx){const check=document.querySelector(`.js-check[data-idx="${idx}"]`);const ok=document.querySelector(`.js-ok[data-idx="${idx}"]`);const ng=document.querySelector(`.js-ng[data-idx="${idx}"]`);let c=parseFloat(check?.value||'0');let o=parseFloat(ok?.value||'0');if(o>c){o=c;ok.value=c;}if(ng)ng.value=Math.round((c-o)*100)/100;}document.querySelectorAll('.js-check,.js-ok').forEach(el=>el.addEventListener('input',()=>recalc(el.dataset.idx)));})();
</script>
@endpush
@endsection
