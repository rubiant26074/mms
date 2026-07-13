@extends('layouts.mms')

@section('title', 'Tanda Tangan Penerimaan - ' . $deliveryNote->dn_number)

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-pencil-fill"></i> Tanda Tangan Penerimaan</h5>
                <small class="opacity-75">No. Surat Jalan: {{ $deliveryNote->dn_number }}</small>
            </div>
            <div class="card-body p-4">
                <form id="signatureForm" method="POST" action="{{ route('warehouse.delivery_notes.sign.store', $deliveryNote) }}">
                    @csrf
                    
                    <div class="mb-3">
                        <label for="received_by_name" class="form-label fw-bold text-dark">Nama Penerima</label>
                        <input type="text" class="form-control rounded-2 border-secondary" id="received_by_name" name="received_by_name" value="{{ old('received_by_name', $deliveryNote->received_by_name) }}" placeholder="Ketik nama penerima..." required autocomplete="off">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark d-flex justify-content-between">
                            <span>Tanda Tangan (Stylus / Jari)</span>
                            <button type="button" id="clearBtn" class="btn btn-sm btn-outline-danger py-0 px-2 fs-7">Hapus</button>
                        </label>
                        <div class="border border-secondary rounded-3 bg-light overflow-hidden position-relative" style="height: 250px;">
                            <canvas id="sigCanvas" class="w-100 h-100" style="touch-action: none; cursor: crosshair;"></canvas>
                        </div>
                        <input type="hidden" name="signature_base64" id="signature_base64">
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger py-2 px-3 rounded-3 mb-3">
                            <ul class="mb-0 ps-3">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-success py-2.5 rounded-3 fw-bold"><i class="bi bi-check-circle-fill"></i> Simpan Penerimaan</button>
                        <a href="{{ route('warehouse.delivery_notes.index') }}" class="btn btn-outline-secondary py-2 rounded-3">Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('sigCanvas');
    const ctx = canvas.getContext('2d');
    const clearBtn = document.getElementById('clearBtn');
    const hiddenInput = document.getElementById('signature_base64');
    const form = document.getElementById('signatureForm');
    
    // Set internal resolution of canvas based on display size
    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = 250; // set constant height
        
        // Fill canvas with white background so it doesn't save as transparent
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        ctx.lineWidth = 3.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#000000';
    }
    
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    let drawing = false;
    let lastX = 0;
    let lastY = 0;

    // Get position relative to canvas
    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: clientX - rect.left,
            y: clientY - rect.top
        };
    }

    function startDraw(e) {
        drawing = true;
        const pos = getPos(e);
        lastX = pos.x;
        lastY = pos.y;
        
        // Prevent scrolling on touch screens
        if (e.touches) e.preventDefault();
    }

    // Drawing handler
    function draw(e) {
        if (!drawing) return;
        const pos = getPos(e);
        
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        
        lastX = pos.x;
        lastY = pos.y;
        
        if (e.touches) e.preventDefault();
    }

    function stopDraw() {
        drawing = false;
    }

    // Mouse listeners
    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDraw);
    canvas.addEventListener('mouseout', stopDraw);

    // Touch listeners
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', stopDraw);

    // Clear signature
    clearBtn.addEventListener('click', function() {
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    });

    // Form submit validation
    form.addEventListener('submit', function(e) {
        // Create a temporary canvas to check if canvas is blank
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = canvas.width;
        tempCanvas.height = canvas.height;
        const tempCtx = tempCanvas.getContext('2d');
        tempCtx.fillStyle = '#ffffff';
        tempCtx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
        
        // If the signature canvas matches a blank white canvas, warn user
        if (canvas.toDataURL() === tempCanvas.toDataURL()) {
            alert('Tanda tangan masih kosong! Silakan berikan tanda tangan terlebih dahulu.');
            e.preventDefault();
            return false;
        }
        
        hiddenInput.value = canvas.toDataURL('image/png');
    });
});
</script>
@endpush
@endsection
