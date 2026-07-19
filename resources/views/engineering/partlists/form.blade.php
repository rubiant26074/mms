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
                <thead class="table-light"><tr><th width="65" class="text-center">Item No</th><th>Drawing No</th><th>Part Name</th><th width="90">Qty</th><th>Material</th><th width="100">Thick</th><th width="100">Length</th><th width="100">Width</th><th>Process</th><th>Notes</th><th>Drawing File</th><th width="60"></th></tr></thead>
                <tbody>
                @forelse($parts as $part)
                    <tr>
                        <td>
                            <input type="hidden" name="row_index[]" value="{{ $loop->index }}">
                            <input name="item_no[]" class="form-control text-center" value="{{ $part->item_no }}">
                        </td>
                        <td><input name="drawing_no[]" class="form-control" value="{{ $part->drawing_no }}"></td>
                        <td><input name="part_name[]" class="form-control" value="{{ $part->part_name }}"></td>
                        <td><input name="qty[]" type="number" step="0.01" class="form-control" value="{{ $part->qty !== null ? $part->qty + 0 : '' }}"></td>
                        <td><input name="material[]" class="form-control" value="{{ $part->material }}"></td>
                        <td><input name="thickness[]" class="form-control" value="{{ $part->thickness }}"></td>
                        <td><input name="length[]" type="number" step="0.01" class="form-control" value="{{ $part->length !== null ? $part->length + 0 : '' }}"></td>
                        <td><input name="width[]" type="number" step="0.01" class="form-control" value="{{ $part->width !== null ? $part->width + 0 : '' }}"></td>
                        <td><input name="process[]" class="form-control" value="{{ $part->process }}"></td>
                        <td><input name="notes[]" class="form-control" value="{{ $part->notes }}"></td>
                        <td style="min-width: 220px;">
                            @php
                                $isUploaded = $part->drawing_path && str_starts_with($part->drawing_path, 'uploads/');
                                $hasLink = $part->drawing_path && ! $isUploaded;
                            @endphp
                            <div class="d-flex align-items-center gap-1">
                                <input type="hidden" name="existing_drawing_path[]" class="js-existing-path" value="{{ $part->drawing_path }}">
                                <input type="hidden" name="remove_drawing_{{ $loop->index }}" class="js-remove-drawing" value="0">
                                <input type="file" name="drawing_file_{{ $loop->index }}" class="d-none js-drawing-file" accept=".pdf,.png,.jpg,.jpeg,.dwg,.dxf">

                                <!-- Manual Link / Win Path Input -->
                                <input type="text" name="drawing_path_{{ $loop->index }}" class="form-control form-control-sm js-drawing-path" placeholder="Link (Drive/Web/Win)" value="{{ $hasLink ? trim($part->drawing_path, ' "') : '' }}" style="min-width: 110px;">

                                <!-- Upload Button -->
                                <button type="button" class="btn btn-sm {{ $isUploaded ? 'btn-success' : 'btn-outline-secondary' }} px-2 js-upload-btn" title="{{ $isUploaded ? 'File terupload: '.basename($part->drawing_path).' (Klik untuk ganti file)' : 'Upload File Drawing PDF/Gambar' }}">
                                    <i class="bi {{ $isUploaded ? 'bi-file-earmark-check-fill' : 'bi-cloud-upload' }}"></i>
                                </button>

                                <!-- Open/Download Link -->
                                @if($isUploaded)
                                    <a href="{{ asset($part->drawing_path) }}" target="_blank" class="btn btn-sm btn-outline-success px-2 py-1 js-download-btn" title="Buka File Upload {{ basename($part->drawing_path) }}"><i class="bi bi-download"></i></a>
                                @elseif($hasLink)
                                    <a href="{{ preg_match('/^https?:\/\//i', $part->drawing_path) ? $part->drawing_path : 'javascript:void(0)' }}" @if(preg_match('/^https?:\/\//i', $part->drawing_path)) target="_blank" @else onclick="alert('Link lokal Windows: {{ addslashes($part->drawing_path) }}')" @endif class="btn btn-sm btn-outline-info px-2 py-1 js-download-btn" title="Buka Link"><i class="bi bi-link-45deg"></i></a>
                                @endif
                            </div>
                            <div class="small text-success mt-1 js-file-status" style="{{ $isUploaded ? '' : 'display:none;' }} font-size:10px; font-weight:bold;">
                                {{ $isUploaded ? '✓ '.basename($part->drawing_path) : '' }}
                            </div>
                        </td>
                        <td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-row">X</button></td>
                    </tr>
                @empty
                    <tr>
                        <td>
                            <input type="hidden" name="row_index[]" value="0">
                            <input name="item_no[]" class="form-control text-center">
                        </td>
                        <td><input name="drawing_no[]" class="form-control"></td><td><input name="part_name[]" class="form-control"></td><td><input name="qty[]" type="number" step="0.01" class="form-control"></td><td><input name="material[]" class="form-control"></td><td><input name="thickness[]" class="form-control"></td><td><input name="length[]" type="number" step="0.01" class="form-control"></td><td><input name="width[]" type="number" step="0.01" class="form-control"></td><td><input name="process[]" class="form-control"></td><td><input name="notes[]" class="form-control"></td>
                        <td style="min-width: 220px;">
                            <div class="d-flex align-items-center gap-1">
                                <input type="hidden" name="existing_drawing_path[]" class="js-existing-path" value="">
                                <input type="hidden" name="remove_drawing_0" class="js-remove-drawing" value="0">
                                <input type="file" name="drawing_file_0" class="d-none js-drawing-file" accept=".pdf,.png,.jpg,.jpeg,.dwg,.dxf">

                                <input type="text" name="drawing_path_0" class="form-control form-control-sm js-drawing-path" placeholder="Link (Drive/Web/Win)" style="min-width: 110px;">
                                
                                <button type="button" class="btn btn-sm btn-outline-secondary px-2 js-upload-btn" title="Upload File Drawing PDF/Gambar">
                                    <i class="bi bi-cloud-upload"></i>
                                </button>
                            </div>
                            <div class="small text-success mt-1 js-file-status" style="display:none; font-size:10px; font-weight:bold;"></div>
                        </td>
                        <td class="text-center"><button type="button" class="btn btn-sm btn-danger remove-row">X</button></td>
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
    const templateExistingPath = templateRow.querySelector('.js-existing-path');
    if (templateExistingPath) templateExistingPath.value = '';
    
    // Remove download button/link from template if it exists
    const downloadBtn = templateRow.querySelector('.js-download-btn');
    if (downloadBtn) downloadBtn.remove();

    let rowCounter = tbody.querySelectorAll('tr').length + 10;

    // Trigger file input clicking relatively
    tbody.addEventListener('click', e => {
        const uploadBtn = e.target.closest('.js-upload-btn');
        if (uploadBtn) {
            const row = uploadBtn.closest('tr');
            const fileInput = row.querySelector('.js-drawing-file');
            if (fileInput) {
                fileInput.click();
            }
        }
    });

    // Handle file selection change
    tbody.addEventListener('change', e => {
        if (e.target.classList.contains('js-drawing-file')) {
            const fileInput = e.target;
            const row = fileInput.closest('tr');
            const statusEl = row.querySelector('.js-file-status');
            const pathInput = row.querySelector('.js-drawing-path');
            const uploadBtn = row.querySelector('.js-upload-btn');
            
            if (fileInput.files.length > 0) {
                const filename = fileInput.files[0].name;
                statusEl.textContent = '✓ ' + filename;
                statusEl.style.display = 'block';
                // Clear the manual path input if they upload a file
                pathInput.value = '';
                pathInput.placeholder = 'Upload terpilih...';
                // Highlight upload button as success
                uploadBtn.classList.remove('btn-outline-secondary');
                uploadBtn.classList.add('btn-success');
            } else {
                statusEl.textContent = '';
                statusEl.style.display = 'none';
                pathInput.placeholder = 'Link (Drive/Web)';
                uploadBtn.classList.remove('btn-success');
                uploadBtn.classList.add('btn-outline-secondary');
            }
        }
    });

    document.getElementById('addRow')?.addEventListener('click', () => {
        const row = templateRow.cloneNode(true);
        rowCounter++;
        
        // Update name of the file input in the new row
        const fileInput = row.querySelector('.js-drawing-file');
        if (fileInput) {
            fileInput.name = 'drawing_file_' + rowCounter;
            fileInput.value = '';
        }

        // Update name of the path input in the new row
        const pathInput = row.querySelector('.js-drawing-path');
        if (pathInput) {
            pathInput.name = 'drawing_path_' + rowCounter;
            pathInput.value = '';
            pathInput.placeholder = 'Link (Drive/Web)';
        }
        
        // Update value of row_index hidden input
        const indexInput = row.querySelector('input[name="row_index[]"]');
        if (indexInput) {
            indexInput.value = rowCounter;
        }

        // Reset existing path input if any
        const existingInput = row.querySelector('.js-existing-path');
        if (existingInput) {
            existingInput.value = '';
        }

        // Reset status element
        const statusEl = row.querySelector('.js-file-status');
        if (statusEl) {
            statusEl.textContent = '';
            statusEl.style.display = 'none';
        }

        // Reset upload button style
        const uploadBtn = row.querySelector('.js-upload-btn');
        if (uploadBtn) {
            uploadBtn.classList.remove('btn-success');
            uploadBtn.classList.add('btn-outline-secondary');
        }

        tbody.appendChild(row);
    });

    document.addEventListener('click', e => {
        if (e.target.closest('.remove-row')) {
            if (tbody.querySelectorAll('tr').length > 1) {
                e.target.closest('tr').remove();
            } else {
                tbody.querySelectorAll('tr input:not([type="hidden"])').forEach(input => input.value = '');
                const fileInput = tbody.querySelector('tr .js-drawing-file');
                if (fileInput) fileInput.value = '';
                const pathInput = tbody.querySelector('tr .js-drawing-path');
                if (pathInput) {
                    pathInput.value = '';
                    pathInput.placeholder = 'Link (Drive/Web)';
                }
                const downloadBtn = tbody.querySelector('tr .js-download-btn');
                if (downloadBtn) downloadBtn.remove();
                const existingInput = tbody.querySelector('tr .js-existing-path');
                if (existingInput) existingInput.value = '';
                const statusEl = tbody.querySelector('tr .js-file-status');
                if (statusEl) {
                    statusEl.textContent = '';
                    statusEl.style.display = 'none';
                }
                const uploadBtn = tbody.querySelector('tr .js-upload-btn');
                if (uploadBtn) {
                    uploadBtn.classList.remove('btn-success');
                    uploadBtn.classList.add('btn-outline-secondary');
                }
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

                const fileInput = row.querySelector('.js-drawing-file');
                if (fileInput) {
                    fileInput.name = 'drawing_file_' + rowCounter;
                    fileInput.value = '';
                }

                const pathInput = row.querySelector('.js-drawing-path');
                if (pathInput) {
                    pathInput.name = 'drawing_path_' + rowCounter;
                    pathInput.value = '';
                    pathInput.placeholder = 'Link (Drive/Web)';
                }
                
                const indexInput = row.querySelector('input[name="row_index[]"]');
                if (indexInput) {
                    indexInput.value = rowCounter;
                }

                const existingInput = row.querySelector('.js-existing-path');
                if (existingInput) {
                    existingInput.value = '';
                }

                const statusEl = row.querySelector('.js-file-status');
                if (statusEl) {
                    statusEl.textContent = '';
                    statusEl.style.display = 'none';
                }

                const uploadBtn = row.querySelector('.js-upload-btn');
                if (uploadBtn) {
                    uploadBtn.classList.remove('btn-success');
                    uploadBtn.classList.add('btn-outline-secondary');
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
