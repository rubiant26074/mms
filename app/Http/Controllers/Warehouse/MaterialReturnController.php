<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\MaterialReturn;
use App\Models\Spk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MaterialReturnController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));

        $returns = MaterialReturn::query()
            ->with('spk')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('ret_number', 'like', $term)
                        ->orWhere('returned_by', 'like', $term)
                        ->orWhere('received_by', 'like', $term)
                        ->orWhereHas('spk', fn ($spk) => $spk->where('spk_number', 'like', $term));
                });
            })
            ->latest('id')
            ->get();

        return view('warehouse.material-returns.index', compact('returns', 'status', 'search'));
    }

    public function create(Request $request): View
    {
        return view('warehouse.material-returns.form', [
            'return' => new MaterialReturn([
                'ret_date' => now()->toDateString(),
                'returned_by' => auth()->user()?->fullname,
                'status' => 'request',
                'spk_id' => $request->integer('spk_id') ?: null,
            ]),
            'spkOptions' => Spk::query()
                ->whereIn('status', ['released', 'in_production', 'completed'])
                ->latest('id')
                ->get(['id', 'spk_number', 'project_name']),
            'materials' => Item::query()
                ->whereIn('item_type', ['raw_material', 'consumable'])
                ->orderBy('item_name')
                ->get(['id', 'item_code', 'item_name', 'unit']),
            'oldRows' => collect(old('type', []))->map(fn ($type, $idx) => [
                'type' => $type,
                'item_id' => old("item_id.{$idx}"),
                'item_name_manual' => old("item_name_manual.{$idx}"),
                'qty' => old("qty.{$idx}"),
                'unit' => old("unit.{$idx}"),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'spk_id' => ['required', 'integer', 'exists:spk,id'],
            'ret_date' => ['required', 'date'],
            'returned_by' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'type' => ['required', 'array'],
            'type.*' => ['required', 'in:intact,waste'],
            'item_id' => ['nullable', 'array'],
            'item_id.*' => ['nullable', 'integer', 'exists:items,id'],
            'item_name_manual' => ['nullable', 'array'],
            'item_name_manual.*' => ['nullable', 'string', 'max:255'],
            'qty' => ['required', 'array'],
            'qty.*' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'array'],
            'unit.*' => ['nullable', 'string', 'max:50'],
        ]);

        $rows = $this->validatedRows($data);

        DB::transaction(function () use ($data, $rows): void {
            $return = MaterialReturn::query()->create([
                'ret_number' => $this->nextReturnNumber(),
                'spk_id' => $data['spk_id'],
                'ret_date' => $data['ret_date'],
                'returned_by' => $data['returned_by'],
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
                'status' => 'request',
            ]);
            $return->items()->createMany($rows);
        });

        return redirect()->route('warehouse.material_returns.index')->with('success', 'Pengembalian material diajukan ke Gudang.');
    }

    public function approve(MaterialReturn $materialReturn): RedirectResponse
    {
        if ($materialReturn->status !== 'request') {
            return back()->withErrors('Gagal approve. Status mungkin sudah berubah.');
        }

        DB::transaction(function () use ($materialReturn): void {
            $materialReturn->load('items');
            $materialReturn->update([
                'status' => 'approved',
                'received_by' => auth()->user()?->fullname,
                'created_at' => now(),
            ]);

            foreach ($materialReturn->items as $row) {
                if ($row->type === 'intact' && $row->item_id) {
                    DB::table('items')->where('id', $row->item_id)->increment('current_stock', (float) $row->qty);
                }
            }
        });

        return redirect()->route('warehouse.material_returns.index')->with('success', 'Pengembalian diterima. Stok material utuh telah ditambahkan kembali.');
    }

    public function destroy(MaterialReturn $materialReturn): RedirectResponse
    {
        if ($materialReturn->status !== 'request') {
            return back()->withErrors('Hanya return berstatus request yang bisa dihapus.');
        }

        DB::transaction(function () use ($materialReturn): void {
            $materialReturn->items()->delete();
            $materialReturn->delete();
        });

        return redirect()->route('warehouse.material_returns.index')->with('success', 'Request pengembalian material berhasil dihapus.');
    }

    private function validatedRows(array $data): array
    {
        $rows = [];
        foreach ($data['type'] as $idx => $type) {
            $qty = (float) ($data['qty'][$idx] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $itemId = $type === 'intact' ? (int) ($data['item_id'][$idx] ?? 0) : null;
            $manualName = $type === 'waste' ? trim((string) ($data['item_name_manual'][$idx] ?? '')) : null;

            if ($type === 'intact' && $itemId <= 0) {
                throw ValidationException::withMessages(['item_id' => 'Item master wajib dipilih untuk material utuh.']);
            }
            if ($type === 'waste' && $manualName === '') {
                throw ValidationException::withMessages(['item_name_manual' => 'Nama barang sisa wajib diisi untuk tipe waste.']);
            }

            $rows[] = [
                'type' => $type,
                'item_id' => $itemId ?: null,
                'item_name_manual' => $manualName,
                'qty' => $qty,
                'unit' => $data['unit'][$idx] ?? null,
            ];
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['qty' => 'Minimal 1 material harus dikembalikan.']);
        }

        return $rows;
    }

    private function nextReturnNumber(): string
    {
        $ym = now()->format('ym');
        $count = MaterialReturn::query()->where('ret_number', 'like', "RET-{$ym}-%")->count() + 1;

        return 'RET-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
