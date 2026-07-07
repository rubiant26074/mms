<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\CycleCountSession;
use App\Models\Item;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CycleCountingController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));

        $sessions = CycleCountSession::query()
            ->with(['creator', 'poster'])
            ->withCount('items')
            ->withSum('items', 'variance_qty')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('session_number', 'like', $term)
                        ->orWhere('count_area', 'like', $term);
                });
            })
            ->latest('id')
            ->get();

        return view('warehouse.cycle-counting.index', compact('sessions', 'status', 'search'));
    }

    public function create(): View
    {
        return view('warehouse.cycle-counting.form', [
            'items' => Item::query()
                ->whereIn('item_type', ['raw_material', 'consumable', 'wip', 'finish_good'])
                ->orderBy('item_name')
                ->get(['id', 'item_code', 'item_name', 'unit', 'current_stock']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'count_date' => ['required', 'date'],
            'count_area' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'item_id' => ['required', 'array'],
            'item_id.*' => ['nullable', 'integer', 'exists:items,id'],
            'counted_qty' => ['required', 'array'],
            'counted_qty.*' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'array'],
            'reason.*' => ['nullable', 'string', 'max:160'],
            'line_notes' => ['nullable', 'array'],
            'line_notes.*' => ['nullable', 'string'],
        ]);

        $rows = $this->validatedRows($data);

        $session = DB::transaction(function () use ($data, $rows): CycleCountSession {
            $session = CycleCountSession::query()->create([
                'session_number' => $this->nextSessionNumber((string) $data['count_date']),
                'count_date' => $data['count_date'],
                'count_area' => $data['count_area'] ?? null,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $items = Item::query()->whereIn('id', array_keys($rows))->get()->keyBy('id');
            foreach ($rows as $itemId => $row) {
                $item = $items->get($itemId);
                if (! $item) {
                    continue;
                }
                $systemQty = (float) $item->current_stock;
                $countedQty = (float) $row['counted_qty'];
                $session->items()->create([
                    'item_id' => $itemId,
                    'system_qty' => $systemQty,
                    'counted_qty' => $countedQty,
                    'variance_qty' => $countedQty - $systemQty,
                    'reason' => $row['reason'] ?: null,
                    'notes' => $row['notes'] ?: null,
                ]);
            }

            if (! $session->items()->exists()) {
                throw ValidationException::withMessages(['item_id' => 'Tidak ada item valid yang tersimpan.']);
            }

            return $session;
        });

        return redirect()->route('warehouse.cycle_counting.show', $session)->with('success', 'Cycle counting berhasil dibuat.');
    }

    public function show(CycleCountSession $session): View
    {
        return view('warehouse.cycle-counting.show', [
            'session' => $session->load(['items.item', 'creator', 'poster']),
        ]);
    }

    public function post(CycleCountSession $session): RedirectResponse
    {
        if ($session->status !== 'draft') {
            return back()->withErrors('Session ini sudah diposting atau tidak valid.');
        }

        DB::transaction(function () use ($session): void {
            $session->load('items');
            if ($session->items->isEmpty()) {
                throw ValidationException::withMessages(['session' => 'Session tidak memiliki detail item.']);
            }

            foreach ($session->items as $line) {
                DB::table('items')->where('id', $line->item_id)->update(['current_stock' => (float) $line->counted_qty]);
            }

            $session->update([
                'status' => 'posted',
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);
        });

        return redirect()->route('warehouse.cycle_counting.show', $session)->with('success', 'Penyesuaian stok berhasil diposting.');
    }

    public function print(CycleCountSession $session): View
    {
        return view('warehouse.cycle-counting.print', [
            'session' => $session->load(['items.item', 'creator', 'poster']),
            'company' => app(MmsContext::class)->company(),
        ]);
    }

    private function validatedRows(array $data): array
    {
        $rows = [];
        foreach ($data['item_id'] as $idx => $rawId) {
            $itemId = (int) ($rawId ?? 0);
            $counted = $data['counted_qty'][$idx] ?? null;
            if ($itemId <= 0 || $counted === null || $counted === '') {
                continue;
            }

            $rows[$itemId] = [
                'counted_qty' => (float) $counted,
                'reason' => trim((string) ($data['reason'][$idx] ?? '')),
                'notes' => trim((string) ($data['line_notes'][$idx] ?? '')),
            ];
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['item_id' => 'Minimal 1 item harus diisi untuk cycle counting.']);
        }

        return $rows;
    }

    private function nextSessionNumber(string $countDate): string
    {
        $ym = \Carbon\Carbon::parse($countDate)->format('ym');
        $count = CycleCountSession::query()->where('session_number', 'like', "CC-{$ym}-%")->count() + 1;

        return 'CC-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
