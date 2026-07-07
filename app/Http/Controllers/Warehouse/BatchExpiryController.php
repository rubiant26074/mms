<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\WarehouseBatch;
use App\Models\WarehouseBatchMovement;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BatchExpiryController extends Controller
{
    public function index(Request $request): View
    {
        return view('warehouse.batch-expiry.index', $this->pageData($request));
    }

    public function storeBatch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'batch_number' => ['required', 'string', 'max:120'],
            'mfg_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'qty' => ['required', 'numeric', 'min:0.0001'],
            'source_doc' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data): void {
            $item = Item::query()->findOrFail($data['item_id']);
            $batchNumber = strtoupper(trim($data['batch_number']));
            $batch = WarehouseBatch::query()->firstOrNew([
                'item_id' => $item->id,
                'batch_number' => $batchNumber,
            ]);

            $batch->fill([
                'mfg_date' => $data['mfg_date'] ?: $batch->mfg_date,
                'expiry_date' => $data['expiry_date'] ?: $batch->expiry_date,
                'qty_available' => (float) ($batch->qty_available ?? 0) + (float) $data['qty'],
                'unit' => $item->unit,
                'source_doc' => $data['source_doc'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => true,
                'created_by' => $batch->exists ? $batch->created_by : auth()->id(),
            ])->save();

            $batch->movements()->create([
                'movement_date' => now()->toDateString(),
                'movement_type' => 'in',
                'qty' => (float) $data['qty'],
                'ref_doc' => $data['source_doc'] ?? null,
                'notes' => $data['notes'] ?: 'Input batch awal',
                'created_by' => auth()->id(),
            ]);
        });

        return redirect()->route('warehouse.batch_expiry.index')->with('success', 'Batch berhasil disimpan.');
    }

    public function storeMovement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'batch_id' => ['required', 'integer', 'exists:warehouse_batches,id'],
            'movement_type' => ['required', Rule::in(['in', 'out', 'adjust'])],
            'qty' => ['required', 'numeric'],
            'movement_date' => ['nullable', 'date'],
            'ref_doc' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data): void {
            $batch = WarehouseBatch::query()->lockForUpdate()->findOrFail($data['batch_id']);
            $qty = (float) $data['qty'];
            if ($data['movement_type'] !== 'adjust' && $qty <= 0) {
                throw ValidationException::withMessages(['qty' => 'Qty mutasi harus lebih dari 0.']);
            }
            if ($data['movement_type'] === 'adjust' && abs($qty) < 0.0001) {
                throw ValidationException::withMessages(['qty' => 'Qty adjust tidak boleh 0.']);
            }

            $delta = match ($data['movement_type']) {
                'in' => abs($qty),
                'out' => -abs($qty),
                default => $qty,
            };
            $newQty = (float) $batch->qty_available + $delta;
            if ($newQty < 0) {
                throw ValidationException::withMessages(['qty' => 'Stok batch tidak cukup untuk mutasi OUT/ADJUST.']);
            }

            $batch->update(['qty_available' => $newQty, 'is_active' => $newQty > 0]);
            $batch->movements()->create([
                'movement_date' => $data['movement_date'] ?: now()->toDateString(),
                'movement_type' => $data['movement_type'],
                'qty' => $delta,
                'ref_doc' => $data['ref_doc'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
        });

        return redirect()->route('warehouse.batch_expiry.index')->with('success', 'Mutasi batch berhasil disimpan.');
    }

    public function print(Request $request): View
    {
        $data = $this->pageData($request);
        $data['company'] = app(MmsContext::class)->company();

        return view('warehouse.batch-expiry.print', $data);
    }

    private function pageData(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $expiry = trim((string) $request->query('expiry', 'all'));
        $batchQuery = $this->filteredBatches($search, $expiry);

        return [
            'search' => $search,
            'expiry' => $expiry,
            'summary' => $this->summary(),
            'items' => Item::query()
                ->whereIn('item_type', ['raw_material', 'consumable', 'finish_good'])
                ->orderBy('item_name')
                ->get(['id', 'item_code', 'item_name', 'unit']),
            'batchOptions' => WarehouseBatch::query()
                ->with('item')
                ->where('qty_available', '>', 0)
                ->where('is_active', true)
                ->orderBy('expiry_date')
                ->latest('id')
                ->get(),
            'batches' => $batchQuery->get(),
            'recentMoves' => WarehouseBatchMovement::query()
                ->with('batch.item')
                ->latest('id')
                ->limit(15)
                ->get(),
        ];
    }

    private function filteredBatches(string $search, string $expiry)
    {
        return WarehouseBatch::query()
            ->with('item')
            ->where('qty_available', '>', 0)
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('batch_number', 'like', $term)
                        ->orWhereHas('item', fn ($item) => $item->where('item_code', 'like', $term)->orWhere('item_name', 'like', $term));
                });
            })
            ->when($expiry === 'expired', fn ($query) => $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', today()))
            ->when($expiry === 'near', fn ($query) => $query->whereNotNull('expiry_date')->whereBetween('expiry_date', [today(), today()->copy()->addDays(30)]))
            ->when($expiry === 'safe', fn ($query) => $query->whereNotNull('expiry_date')->whereDate('expiry_date', '>', today()->copy()->addDays(30)))
            ->when($expiry === 'no_expiry', fn ($query) => $query->whereNull('expiry_date'))
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->latest('id');
    }

    private function summary(): array
    {
        return [
            'total_batches' => WarehouseBatch::query()->where('qty_available', '>', 0)->count(),
            'near_expiry' => WarehouseBatch::query()->where('qty_available', '>', 0)->whereNotNull('expiry_date')->whereBetween('expiry_date', [today(), today()->copy()->addDays(30)])->count(),
            'expired' => WarehouseBatch::query()->where('qty_available', '>', 0)->whereNotNull('expiry_date')->whereDate('expiry_date', '<', today())->count(),
            'total_qty' => WarehouseBatch::query()->where('qty_available', '>', 0)->sum('qty_available'),
        ];
    }
}
