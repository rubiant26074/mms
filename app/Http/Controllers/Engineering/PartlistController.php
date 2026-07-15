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
        $view = $request->query('view') === 'archive' ? 'archive' : 'active';
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
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
        $parts = $this->partsFromRequest($request);

        DB::transaction(function () use ($data, $parts, $request): void {
            $spk = Spk::query()->lockForUpdate()->findOrFail($data['spk_id']);
            $spk->update(['drawing_link' => $data['drawing_link'] ?? null]);
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
        $rowIndices = $request->input('row_index', []);
        $existingPaths = $request->input('existing_drawing_path', []);

        foreach ($request->input('item_no', []) as $i => $itemNo) {
            $partName = trim((string) ($request->input("part_name.{$i}") ?? ''));
            if (trim((string) $itemNo) === '' && $partName === '') {
                continue;
            }
            $thicknessVal = trim((string) $request->input("thickness.{$i}"));
            $lengthVal = trim((string) $request->input("length.{$i}"));
            $widthVal = trim((string) $request->input("width.{$i}"));

            // Check if there is an uploaded file for this specific row index
            $rowIndex = $rowIndices[$i] ?? $i;
            $drawingPath = $existingPaths[$i] ?? null;

            if ($request->hasFile("drawing_file_{$rowIndex}")) {
                $file = $request->file("drawing_file_{$rowIndex}");
                if ($file->isValid()) {
                    // Save file to public/uploads/drawings/
                    $filename = 'drw_' . uniqid() . '_' . now()->format('YmdHis') . '.' . strtolower($file->getClientOriginalExtension());
                    $directory = public_path('uploads/drawings');
                    if (!is_dir($directory)) {
                        @mkdir($directory, 0775, true);
                    }
                    $file->move($directory, $filename);
                    $drawingPath = 'uploads/drawings/' . $filename;
                }
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
