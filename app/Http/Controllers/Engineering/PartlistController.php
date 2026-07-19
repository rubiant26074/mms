<?php

namespace App\Http\Controllers\Engineering;

use App\Http\Controllers\Controller;
use App\Models\Spk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $data = $request->validate([
            'spk_id' => ['required', 'exists:spk,id'],
            'drawing_link' => ['nullable', 'string'],
            'submit_action' => ['nullable', 'in:save,approve'],
        ]);
        try {
            $parts = $this->partsFromRequest($request);
        } catch (\RuntimeException $e) {
            return back()->withErrors($e->getMessage())->withInput();
        }

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
        $existingPaths = $request->input('existing_drawing_path', []);
        $manualPaths = $request->input('drawing_path', []);
        $drawingFiles = $request->file('drawing_file', []);
        $removeFlags = $request->input('remove_drawing', []);

        foreach ($request->input('item_no', []) as $i => $itemNo) {
            $partName = trim((string) ($request->input("part_name.{$i}") ?? ''));
            if (trim((string) $itemNo) === '' && $partName === '') {
                continue;
            }
            $thicknessVal = trim((string) $request->input("thickness.{$i}"));
            $lengthVal = trim((string) $request->input("length.{$i}"));
            $widthVal = trim((string) $request->input("width.{$i}"));

            $drawingPath = $existingPaths[$i] ?? null;

            if (! empty($removeFlags[$i])) {
                $drawingPath = null;
            }

            $manualPath = trim((string) ($manualPaths[$i] ?? ''));
            if ($manualPath !== '') {
                $manualPath = trim($manualPath, " \t\n\r\0\x0B\"'");
                $drawingPath = $manualPath;
            } else {
                if ($drawingPath && ! str_starts_with($drawingPath, 'uploads/')) {
                    $drawingPath = null;
                }
            }

            // Check file upload for row position $i (highest priority)
            $file = $drawingFiles[$i] ?? $request->file("drawing_file_{$i}");
            if ($file) {
                if (! $file->isValid()) {
                    $errCode = $file->getError();
                    $errText = match ($errCode) {
                        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File drawing baris #' . ($i + 1) . ' (' . $file->getClientOriginalName() . ') melebihi batas ukuran file server.',
                        default => 'Gagal upload file drawing baris #' . ($i + 1) . ' (' . $file->getClientOriginalName() . '). Error code: ' . $errCode,
                    };
                    throw new \RuntimeException($errText);
                }

                $filename = 'drw_' . uniqid() . '_' . now()->format('YmdHis') . '.' . strtolower($file->getClientOriginalExtension());
                $directory = public_path('uploads/drawings');
                if (! is_dir($directory)) {
                    @mkdir($directory, 0775, true);
                }
                $file->move($directory, $filename);
                $drawingPath = 'uploads/drawings/' . $filename;
            }

            if ($drawingPath && ! str_starts_with($drawingPath, 'uploads/')) {
                $drawingPath = trim($drawingPath, " \t\n\r\0\x0B\"'");
            }

            $rows[] = [
                'item_no' => trim((string) $itemNo),
                'drawing_no' => trim((string) ($request->input("drawing_no.{$i}") ?? '')),
                'part_name' => $partName,
                'qty' => trim((string) $request->input("qty.{$i}")) !== '' ? (float) $request->input("qty.{$i}") : 0,
                'material' => trim((string) ($request->input("material.{$i}") ?? '')),
                'thickness' => $thicknessVal !== '' ? (float) $thicknessVal : null,
                'length' => $lengthVal !== '' ? (float) $lengthVal : null,
                'width' => $widthVal !== '' ? (float) $widthVal : null,
                'process' => trim((string) ($request->input("process.{$i}") ?? '')),
                'notes' => trim((string) ($request->input("notes.{$i}") ?? '')),
                'drawing_path' => $drawingPath,
            ];
        }

        return $rows;
    }
}
