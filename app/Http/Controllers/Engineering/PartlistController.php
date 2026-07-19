<?php

namespace App\Http\Controllers\Engineering;

use App\Http\Controllers\Controller;
use App\Models\Spk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PartlistController extends Controller
{
    public function index(Request $request): View
    {
        $view = $this->rememberedFilter($request, 'view', 'active') === 'archive' ? 'archive' : 'active';
        $status = $this->rememberedFilter($request, 'status', '');
        $search = $this->rememberedFilter($request, 'search', '');
        $statuses = $view === 'archive'
            ? ['closed']
            : ['preliminary', 'final', 'waiting_eng', 'waiting_mgr', 'released', 'in_production', 'completed'];

        $spks = Spk::query()
            ->with('salesOrder')
            ->withCount('partlists')
            ->whereIn('status', $statuses)
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('spk_number', 'like', $term)
                        ->orWhereHas('salesOrder', fn ($so) => $so->where('so_number', 'like', $term));
                });
            })
            ->latest('id')
            ->get();

        return view('engineering.partlists.index', compact('spks', 'view', 'status', 'search'));
    }

    public function create(Request $request): View
    {
        $spk = Spk::query()->with(['salesOrder.items.item', 'partlists'])->findOrFail($request->integer('spk_id'));

        return view('engineering.partlists.form', ['spk' => $spk, 'parts' => $spk->partlists]);
    }

    public function store(Request $request): RedirectResponse
    {
        // DEBUG: Log semua info upload file drawing
        $debugInfo = [
            'php_upload_max_filesize' => ini_get('upload_max_filesize'),
            'php_post_max_size' => ini_get('post_max_size'),
            'php_file_uploads' => ini_get('file_uploads'),
            'php_max_file_uploads' => ini_get('max_file_uploads'),
            'content_type' => $request->header('Content-Type'),
            'has_files' => $request->hasFile('drawing_file'),
            'all_files_keys' => array_keys($request->allFiles()),
            'drawing_file_raw' => $request->allFiles()['drawing_file'] ?? null,
            'drawing_path_input' => $request->input('drawing_path', []),
            'existing_drawing_path' => $request->input('existing_drawing_path', []),
            'remove_drawing' => $request->input('remove_drawing', []),
            'item_no_count' => count($request->input('item_no', [])),
            'public_uploads_dir' => public_path('uploads/drawings'),
            'public_uploads_writable' => is_writable(public_path('uploads')),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        ];
        Log::channel('single')->info('[PartlistController@store] DEBUG UPLOAD', $debugInfo);

        $data = $request->validate([
            'spk_id' => ['required', 'exists:spk,id'],
            'drawing_link' => ['nullable', 'string'],
            'submit_action' => ['nullable', 'in:save,approve'],
        ]);
        try {
            $parts = $this->partsFromRequest($request);
        } catch (\RuntimeException $e) {
            Log::channel('single')->error('[PartlistController@store] RuntimeException: ' . $e->getMessage());
            return back()->withErrors($e->getMessage())->withInput();
        }

        Log::channel('single')->info('[PartlistController@store] Parts to be saved', [
            'parts_count' => count($parts),
            'drawing_paths' => array_column($parts, 'drawing_path'),
        ]);

        DB::transaction(function () use ($data, $parts, $request): void {
            $spk = Spk::query()->lockForUpdate()->findOrFail($data['spk_id']);
            if ($request->has('drawing_link')) {
                $spk->update(['drawing_link' => $data['drawing_link'] ?? null]);
            }
            $spk->partlists()->delete();
            $spk->partlists()->createMany($parts);
            if ($request->input('submit_action') === 'approve' && $request->user()?->hasPermission('eng_partlist_approve')) {
                $spk->update(['status' => 'final', 'approved_by_eng' => auth()->id(), 'approved_at_eng' => now()]);
            }
        });

        $message = $request->input('submit_action') === 'approve' ? 'Partlist Approved! SPK Status: FINAL' : 'Draft Partlist Berhasil Disimpan.';

        return redirect()->route('engineering.partlists.index')->with('success', $message);
    }

    public function approve(Request $request, Spk $spk): RedirectResponse
    {
        if (! $request->user()?->hasPermission('eng_partlist_approve')) {
            abort(403);
        }
        $spk->update(['status' => 'final', 'approved_by_eng' => auth()->id(), 'approved_at_eng' => now()]);

        return redirect()->route('engineering.partlists.index')->with('success', 'Partlist Disetujui! SPK Status: FINAL');
    }

    public function print(Spk $spk): View
    {
        return view('engineering.partlists.print', ['spk' => $spk->load(['salesOrder.customer', 'partlists'])]);
    }

    private function partsFromRequest(Request $request): array
    {
        $rows = [];
        $existingPaths  = $request->input('existing_drawing_path', []);
        $manualPaths    = $request->input('drawing_path', []);
        $removeFlags    = $request->input('remove_drawing', []);
        $base64Data     = $request->input('drawing_base64', []);
        $base64Names    = $request->input('drawing_filename', []);

        // Still try regular file upload as fallback (in case hosting fixes file_uploads)
        $drawingFiles = $request->file('drawing_file', []);

        $allowedExts = ['pdf', 'png', 'jpg', 'jpeg', 'dwg', 'dxf'];

        Log::channel('single')->info('[partsFromRequest] base64 count=' . count($base64Data) . ', drawingFiles count=' . count($drawingFiles));

        foreach ($request->input('item_no', []) as $i => $itemNo) {
            $partName = trim((string) ($request->input("part_name.{$i}") ?? ''));
            if (trim((string) $itemNo) === '' && $partName === '') {
                continue;
            }
            $thicknessVal = trim((string) $request->input("thickness.{$i}"));
            $lengthVal    = trim((string) $request->input("length.{$i}"));
            $widthVal     = trim((string) $request->input("width.{$i}"));

            $drawingPath = $existingPaths[$i] ?? null;

            if (! empty($removeFlags[$i])) {
                $drawingPath = null;
            }

            // 1. Manual text link takes priority if filled
            $manualPath = trim((string) ($manualPaths[$i] ?? ''));
            if ($manualPath !== '') {
                $manualPath  = trim($manualPath, " \t\n\r\0\x0B\"'");
                $drawingPath = $manualPath;
            } else {
                // Keep existing uploaded path; clear if it was a text link (not a server file)
                if ($drawingPath && ! str_starts_with($drawingPath, 'uploads/')) {
                    $drawingPath = null;
                }
            }

            // 2. Base64 encoded file (primary upload method — bypasses file_uploads=Off)
            $base64 = trim((string) ($base64Data[$i] ?? ''));
            if ($base64 !== '') {
                // Format: "data:application/pdf;base64,<data>"
                $parts = explode(',', $base64, 2);
                if (count($parts) === 2) {
                    $decoded = base64_decode($parts[1], true);
                    if ($decoded !== false && strlen($decoded) > 100) {
                        $origName = $base64Names[$i] ?? 'drawing.pdf';
                        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                        if (! in_array($ext, $allowedExts)) {
                            $ext = 'pdf';
                        }
                        $filename  = 'drw_' . uniqid() . '_' . now()->format('YmdHis') . '.' . $ext;
                        $directory = public_path('uploads/drawings');
                        if (! is_dir($directory)) {
                            @mkdir($directory, 0775, true);
                        }
                        if (file_put_contents($directory . '/' . $filename, $decoded) !== false) {
                            $drawingPath = 'uploads/drawings/' . $filename;
                            Log::channel('single')->info("[partsFromRequest] Base64 saved row $i → $drawingPath");
                        } else {
                            Log::channel('single')->error("[partsFromRequest] file_put_contents FAILED row $i, dir=$directory");
                        }
                    }
                }
            }

            // 3. Regular PHP file upload fallback
            $file = $drawingFiles[$i] ?? null;
            if ($file && $file->isValid()) {
                $ext = strtolower($file->getClientOriginalExtension());
                if (in_array($ext, $allowedExts)) {
                    $filename  = 'drw_' . uniqid() . '_' . now()->format('YmdHis') . '.' . $ext;
                    $directory = public_path('uploads/drawings');
                    if (! is_dir($directory)) {
                        @mkdir($directory, 0775, true);
                    }
                    $file->move($directory, $filename);
                    $drawingPath = 'uploads/drawings/' . $filename;
                }
            }

            if ($drawingPath && ! str_starts_with($drawingPath, 'uploads/')) {
                $drawingPath = trim($drawingPath, " \t\n\r\0\x0B\"'");
            }

            $rows[] = [
                'item_no'    => trim((string) $itemNo),
                'drawing_no' => trim((string) ($request->input("drawing_no.{$i}") ?? '')),
                'part_name'  => $partName,
                'qty'        => trim((string) $request->input("qty.{$i}")) !== '' ? (float) $request->input("qty.{$i}") : 0,
                'material'   => trim((string) ($request->input("material.{$i}") ?? '')),
                'thickness'  => $thicknessVal !== '' ? (float) $thicknessVal : null,
                'length'     => $lengthVal    !== '' ? (float) $lengthVal    : null,
                'width'      => $widthVal     !== '' ? (float) $widthVal     : null,
                'process'    => trim((string) ($request->input("process.{$i}") ?? '')),
                'notes'      => trim((string) ($request->input("notes.{$i}") ?? '')),
                'drawing_path' => $drawingPath,
            ];
        }

        return $rows;
    }

}
