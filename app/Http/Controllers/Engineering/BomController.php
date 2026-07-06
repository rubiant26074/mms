<?php

namespace App\Http\Controllers\Engineering;

use App\Http\Controllers\Controller;
use App\Models\Bom;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BomController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $boms = Bom::query()
            ->with('item')
            ->withCount('details')
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where('bom_code', 'like', $term)
                    ->orWhereHas('item', fn ($item) => $item->where('item_name', 'like', $term)->orWhere('item_code', 'like', $term));
            })
            ->latest('id')
            ->get();

        return view('engineering.boms.index', compact('boms', 'search'));
    }

    public function create(): View
    {
        return $this->form(new Bom(['qty_result' => 1, 'status' => 'active']), collect([]), false);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $details = $this->validatedDetails($request);
        if ($details === []) {
            return back()->withInput()->withErrors('Minimal 1 material BOM harus diisi.');
        }

        DB::transaction(function () use ($data, $details): void {
            $bom = Bom::query()->create($data + ['bom_code' => $this->nextBomCode(), 'created_by' => auth()->id()]);
            $bom->details()->createMany($details);
        });

        return redirect()->route('engineering.boms.index')->with('success', 'BOM Berhasil Disimpan!');
    }

    public function edit(Bom $bom): View
    {
        return $this->form($bom->load('details.material'), $bom->details, true);
    }

    public function update(Request $request, Bom $bom): RedirectResponse
    {
        $data = $this->validated($request, $bom);
        $details = $this->validatedDetails($request);
        if ($details === []) {
            return back()->withInput()->withErrors('Minimal 1 material BOM harus diisi.');
        }

        DB::transaction(function () use ($bom, $data, $details): void {
            $bom->update($data);
            $bom->details()->delete();
            $bom->details()->createMany($details);
        });

        return redirect()->route('engineering.boms.index')->with('success', 'BOM Berhasil Disimpan!');
    }

    public function destroy(Bom $bom): RedirectResponse
    {
        if ($bom->status === 'locked') {
            return back()->withErrors('BOM berstatus LOCKED tidak boleh dihapus karena digunakan history produksi.');
        }
        $bom->details()->delete();
        $bom->delete();

        return redirect()->route('engineering.boms.index')->with('success', 'BOM berhasil dihapus.');
    }

    private function form(Bom $bom, $details, bool $isEdit): View
    {
        $fgItems = Item::query()
            ->whereIn('item_type', ['finish_good', 'wip'])
            ->when($isEdit, fn ($query) => $query->orWhereKey($bom->item_id))
            ->orderBy('item_code')
            ->get();
        $materials = Item::query()
            ->whereIn('item_type', ['raw_material', 'consumable', 'wip'])
            ->orderBy('item_code')
            ->get();

        return view('engineering.boms.form', compact('bom', 'details', 'isEdit', 'fgItems', 'materials'));
    }

    private function validated(Request $request, ?Bom $bom = null): array
    {
        return $request->validate([
            'item_id' => ['required', Rule::exists('items', 'id')],
            'qty_result' => ['required', 'numeric', 'min:0.0001'],
            'status' => ['required', 'in:active,inactive,locked'],
            'notes' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);
    }

    private function validatedDetails(Request $request): array
    {
        $rows = [];
        foreach ($request->input('material_id', []) as $i => $materialId) {
            $materialId = (int) $materialId;
            $qty = (float) ($request->input("qty_needed.{$i}") ?? 0);
            if ($materialId <= 0 || $qty <= 0) {
                continue;
            }
            $material = Item::query()->find($materialId);
            if (! $material) {
                continue;
            }
            $rows[] = [
                'material_id' => $materialId,
                'qty' => $qty,
                'unit' => $material->unit,
                'waste_percent' => (float) ($request->input("waste_percent.{$i}") ?? 0),
                'notes' => trim((string) ($request->input("detail_notes.{$i}") ?? '')),
            ];
        }

        return $rows;
    }

    private function nextBomCode(): string
    {
        $ym = now()->format('ym');
        $count = Bom::query()->where('bom_code', 'like', "BOM-{$ym}-%")->count() + 1;

        return 'BOM-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
