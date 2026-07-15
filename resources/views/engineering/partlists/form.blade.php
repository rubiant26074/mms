@extends('layouts.mms')

@section('title', 'Form Part List')

@section('content')
<div class="row mb-3">
    <div class="col-md-8">
        <h3 class="fw-bold"><i class="bi bi-list-check"></i> Form Part List</h3>
        <p class="text-muted">SPK: {{ $spk->spk_number }} | Status: <span class="badge bg-info text-dark">{{ strtoupper($spk->status) }}</span></p>
    </div>
    <div class="col-md-4 text-end"><a href="{{ route('engineering.partlists.index') }}" class="btn btn-secondary shadow-sm">Kembali</a></div>
</div>
@include('partials.alerts')
<form method="POST" action="{{ route('engineering.partlists.store') }}" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="spk_id" value="{{ $spk->id }}">

    <div class="card shadow-sm">
        <div class="card-body p-0 table-responsive">
            <table class="table table-bordered mb-0" id="partTable">
                <thead class="table-light"><tr><th>Item No</th><th>Drawing No</th><th>Part Name</th><th width="90">Qty</th><th>Material</th><th width="100">Thick</th><th width="100">Length</th><th width="100">Width</th><th>Process</th><th>Notes</th><th>Drawing File</th><th width="60"></th></tr></thead>
                <tbody>
                @forelse($parts as $part)
                    <tr>
                        <input type="hidden" name="row_index[]" value="{{ $loop->index }}">
                        <input type="hidden" name="existing_drawing_path[]" value="{{ $part->drawing_path }}">
                        <td><input name="item_no[]" class="form-control" value="{{ $part->item_no }}"></td>
                        <td><input name="drawing_no[]" class="form-control" value="{{ $part->drawing_no }}"></td>
                        <td><input name="part_name[]" class="form-control" value="{{ $part->part_name }}"></td>
                        <td><input name="qty[]" type="number" step="0.01" class="form-control" value="{{ $part->qty !== null ? $part->qty + 0 : '' }}"></td>
                        <td><input name="material[]" class="form-control" value="{{ $part->material }}"></td>
                        <td><input name="thickness[]" class="form-control" value="{{ $part->thickness }}"></td>
                        <td><input name="length[]" type="number" step="0.01" class="form-control" value="{{ $part->length !== null ? $part->length + 0 : '' }}"></td>
                        <td><input name="width[]" type="number" step="0.01" class="form-control" value="{{ $part->width !== null ? $part->width + 0 : '' }}"></td>
                        <td><input name="process[]" class="form-control" value="{{ $part->process }}"></td>
                        <td><input name="notes[]" class="form-control" value="{{ $part->notes }}"></td>
                        <td>
                            @if($part->drawing_path)
                                <div class="mb-1">
                                    <a href="{{ asset($part->drawing_path) }}" target="_blank" class="btn btn-sm btn-outline-success py-0 px-1" title="Download Drawing"><i class="bi bi-download"></i> Download</a>
                                </div>
                            @endif
                            <input type="file" name="drawing_file_{{ $loop->index }}" class="form-control form-control-sm" accept=".pdf,.png,.jpg,.jpeg,.dwg,.dxf">
                        </td>
                        <td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-row">X</button></td>
                    </tr>
                @empty
                    <tr>
                        <input type="hidden" name="row_index[]" value="0">
                        <input type="hidden" name="existing_drawing_path[]" value="">
                        <td><input name="item_no[]" class="form-control"></td><td><input name="drawing_no[]" class="form-control"></td><td><input name="part_name[]" class="form-control"></td><td><input name="qty[]" type="number" step="0.01" class="form-control"></td><td><input name="material[]" class="form-control"></td><td><input name="thickness[]" class="form-control"></td><td><input name="length[]" type="number" step="0.01" class="form-control"></td><td><input name="width[]" type="number" step="0.01" class="form-control"></td><td><input name="process[]" class="form-control"></td><td><input name="notes[]" class="form-control"></td><td><input type="file" name="drawing_file_0" class="form-control form-control-sm" accept=".pdf,.png,.jpg,.jpeg,.dwg,.dxf"></td><td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-row">X</button></td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <div>
                <button type="button" id="addRow" class="btn btn-success btn-sm"><i class="bi bi-plus-lg"></i> Tambah Baris</button>
            </div>
            @if($spk->salesOrder && $spk->salesOrder->items->isNotEmpty())
                <div>
                    <button type="button" id="pullSoBtn" class="btn btn-info btn-sm fw-bold"><i class="bi bi-download"></i> Tarik Data dari SO</button>
                </div>
            @endif
        </div>
    </div>
    <div class="text-end mt-4">
        <a href="{{ route('engineering.partlists.index') }}" class="btn btn-outline-secondary">Batal & Kembali</a>
        <button name="submit_action" value="save" class="btn btn-primary px-4">Simpan Draft</button>
        <button name="submit_action" value="approve" class="btn btn-success px-4" onclick="return confirm('Approve partlist dan ubah SPK menjadi FINAL?')">Approve Final</button>
    </div>
</form>

@if($spk->salesOrder)
    <script id="so-items-json" type="application/json">
        {!! json_encode($spk->salesOrder->items->map(fn($item) => [
            'item_code' => $item->item?->item_code ?: $item->item_code_manual,
            'item_name' => $item->item?->item_name ?: $item->item_name_manual,
            'qty' => $item->qty + 0,
            'material' => $item->material_manual ?: $item->item?->description ?: '',
            'unit' => $item->unit_manual ?: $item->item?->unit ?: '',
        ])->toArray()) !!}
    </script>
@endif

@push('scripts')
<script>
(function () {
    const tbody = document.querySelector('#partTable tbody');
    
    // Cache a clean template row
    const templateRow = tbody.querySelector('tr').cloneNode(true);
    // Clear standard inputs
    templateRow.querySelectorAll('input:not([type="hidden"])').forEach(input => input.value = '');
    
    // Ensure hidden inputs are also reset appropriately
    const templateRowIndex = templateRow.querySelector('input[name="row_index[]"]');
    if (templateRowIndex) templateRowIndex.value = '0';
    const templateExistingPath = templateRow.querySelector('input[name="existing_drawing_path[]"]');
    if (templateExistingPath) templateExistingPath.value = '';
    
    // Remove download button/link from template if it exists
    const downloadDiv = templateRow.querySelector('td:nth-last-child(2) div');
    if (downloadDiv) downloadDiv.remove();

    let rowCounter = tbody.querySelectorAll('tr').length + 10;

    document.getElementById('addRow')?.addEventListener('click', () => {
        const row = templateRow.cloneNode(true);
        rowCounter++;
        
        // Update name of the file input in the new row
        const fileInput = row.querySelector('input[type="file"]');
        if (fileInput) {
            fileInput.name = 'drawing_file_' + rowCounter;
            fileInput.value = '';
        }
        
        // Update value of row_index hidden input
        const indexInput = row.querySelector('input[name="row_index[]"]');
        if (indexInput) {
            indexInput.value = rowCounter;
        }

        tbody.appendChild(row);
    });

    document.addEventListener('click', e => {
        if (e.target.closest('.remove-row')) {
            if (tbody.querySelectorAll('tr').length > 1) {
                e.target.closest('tr').remove();
            } else {
                tbody.querySelectorAll('tr input:not([type="hidden"])').forEach(input => input.value = '');
                const fileInput = tbody.querySelector('tr input[type="file"]');
                if (fileInput) fileInput.value = '';
                const downloadDiv = tbody.querySelector('tr td:nth-last-child(2) div');
                if (downloadDiv) downloadDiv.remove();
                const existingInput = tbody.querySelector('tr input[name="existing_drawing_path[]"]');
                if (existingInput) existingInput.value = '';
            }
        }
    });

    const pullSoBtn = document.getElementById('pullSoBtn');
    if (pullSoBtn) {
        pullSoBtn.addEventListener('click', () => {
            const jsonEl = document.getElementById('so-items-json');
            if (!jsonEl) return;
            
            const items = JSON.parse(jsonEl.textContent || '[]');
            if (items.length === 0) {
                alert('Tidak ada item pada Sales Order ini.');
                return;
            }

            let hasInput = false;
            tbody.querySelectorAll('input:not([type="hidden"])').forEach(input => {
                if (input.value.trim() !== '') hasInput = true;
            });

            if (hasInput && !confirm('Menarik data dari SO akan menimpa part list saat ini. Lanjutkan?')) {
                return;
            }

            tbody.innerHTML = '';

            items.forEach((item, index) => {
                const row = templateRow.cloneNode(true);
                rowCounter++;

                const fileInput = row.querySelector('input[type="file"]');
                if (fileInput) {
                    fileInput.name = 'drawing_file_' + rowCounter;
                    fileInput.value = '';
                }
                
                const indexInput = row.querySelector('input[name="row_index[]"]');
                if (indexInput) {
                    indexInput.value = rowCounter;
                }
                
                const itemNoInput = row.querySelector('input[name="item_no[]"]');
                const partNameInput = row.querySelector('input[name="part_name[]"]');
                const qtyInput = row.querySelector('input[name="qty[]"]');
                const materialInput = row.querySelector('input[name="material[]"]');
                const notesInput = row.querySelector('input[name="notes[]"]');
                
                if (itemNoInput) itemNoInput.value = index + 1;
                if (partNameInput) partNameInput.value = item.item_name;
                if (qtyInput) qtyInput.value = item.qty;
                if (materialInput) materialInput.value = item.material;
                if (notesInput && item.unit) notesInput.value = 'Satuan: ' + item.unit;
                
                tbody.appendChild(row);
            });
        });
    }
})();
</script>
@endpush
@endsection
