<?php

namespace App\Http\Controllers\Qc;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Ncr;
use App\Models\ProductionAssignment;
use App\Models\QcProduction;
use App\Models\Spk;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function index(): View
    {
        $pending = Spk::query()
            ->with(['salesOrder.items.item', 'productionQcs' => fn ($query) => $query->latest('id')->limit(1)])
            ->whereIn('status', ['completed', 'closed'])
            ->where(function ($query): void {
                $query->whereDoesntHave('productionQcs')
                    ->orWhereHas('productionQcs', function ($sub): void {
                        $sub->whereRaw('id = (select max(q2.id) from qc_production q2 where q2.spk_id = spk.id)')
                            ->where(function ($last): void {
                                $last->where('status', 'ng')
                                    ->orWhere(function ($anomali): void {
                                        $anomali->where('qty_reject', '>', 0)
                                            ->where(fn ($s) => $s->whereNull('status')->orWhere('status', '')->orWhere('status', 'completed'));
                                    });
                            });
                    });
            })
            ->orderBy('deadline_date')
            ->get();

        $history = QcProduction::query()
            ->with(['spk.salesOrder.items.item', 'inspector'])
            ->whereIn('id', function ($query): void {
                $query->selectRaw('max(id)')
                    ->from('qc_production')
                    ->groupBy('spk_id');
            })
            ->latest('id')
            ->limit(10)
            ->get();

        return view('qc.production.index', compact('pending', 'history'));
    }

    public function inspect(Request $request): View|RedirectResponse
    {
        $spk = Spk::query()->with('salesOrder.items.item')->findOrFail($request->integer('spk_id'));
        $lastQc = QcProduction::query()->where('spk_id', $spk->id)->latest('id')->first();
        if ($lastQc && $this->normalizedStatus($lastQc) !== 'ng') {
            return redirect()->route('qc.production.index')->withErrors("QC untuk SPK ini sudah pernah disimpan ({$lastQc->qc_number}).");
        }

        $products = $this->products($spk);
        if ($products->isEmpty()) {
            return redirect()->route('qc.production.index')->withErrors('Item sales order untuk SPK ini tidak ditemukan.');
        }

        return view('qc.production.form', [
            'spk' => $spk,
            'products' => $products,
            'totalCheckedPreviously' => (float) QcProduction::query()->where('spk_id', $spk->id)->sum('qty_check'),
            'lastQc' => $lastQc,
            'isSheetMetal' => $products->contains(fn ($row) => ($row['qc_type'] ?? '') === 'sheet_metal'),
        ]);
    }

    public function store(Request $request, Spk $spk): RedirectResponse
    {
        $lastQc = QcProduction::query()->where('spk_id', $spk->id)->latest('id')->first();
        if ($lastQc && $this->normalizedStatus($lastQc) !== 'ng') {
            return redirect()->route('qc.production.index')->withErrors("QC untuk SPK ini sudah pernah disimpan ({$lastQc->qc_number}).");
        }

        $data = $request->validate([
            'item_id' => ['required', 'array'],
            'item_id.*' => ['required', 'integer', 'exists:items,id'],
            'qty_check' => ['required', 'array'],
            'qty_check.*' => ['required', 'numeric', 'min:0'],
            'qty_pass' => ['required', 'array'],
            'qty_pass.*' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'chk_laser' => ['nullable', 'array'],
            'chk_bend' => ['nullable', 'array'],
            'chk_weld' => ['nullable', 'array'],
        ]);

        $products = $this->products($spk);
        $totalPlan = (float) $products->sum('plan_qty');
        $totalCheckedBefore = (float) QcProduction::query()->where('spk_id', $spk->id)->sum('qty_check');
        $maxAllowed = $lastQc && $this->normalizedStatus($lastQc) === 'ng'
            ? (float) $lastQc->qty_reject
            : max(0, $totalPlan - $totalCheckedBefore);

        $rows = [];
        $totalCheck = $totalOk = $totalNg = 0.0;
        foreach ($data['item_id'] as $idx => $itemId) {
            $check = (float) ($data['qty_check'][$idx] ?? 0);
            $ok = min((float) ($data['qty_pass'][$idx] ?? 0), $check);
            $ng = max(0, $check - $ok);
            $rows[] = ['item_id' => (int) $itemId, 'qty_check' => $check, 'qty_ok' => $ok, 'qty_ng' => $ng];
            $totalCheck += $check;
            $totalOk += $ok;
            $totalNg += $ng;
        }
        if ($totalCheck > $maxAllowed + 0.001) {
            return back()->withInput()->withErrors('Qty Check melebihi sisa yang boleh di-QC. Maksimum: ' . rtrim(rtrim(number_format($maxAllowed, 2, '.', ''), '0'), '.'));
        }

        $qc = DB::transaction(function () use ($request, $spk, $rows, $totalCheck, $totalOk, $totalNg, $data, $products, $totalPlan, $totalCheckedBefore): QcProduction {
            $qc = QcProduction::query()->create([
                'qc_number' => $this->nextQcNumber(),
                'spk_id' => $spk->id,
                'qc_date' => now()->toDateString(),
                'inspector_id' => auth()->id(),
                'status' => $totalNg > 0 ? 'ng' : 'completed',
                'qty_check' => $totalCheck,
                'qty_pass' => $totalOk,
                'qty_reject' => $totalNg,
                'notes' => trim((string) ($data['notes'] ?? '') . $this->checklistNotes($request)),
                'created_by' => auth()->id(),
            ]);

            foreach ($rows as $row) {
                if ($row['qty_ok'] > 0) {
                    Item::query()->whereKey($row['item_id'])->increment('current_stock', $row['qty_ok']);
                }
                if ($row['qty_ng'] > 0) {
                    $this->createNcr($qc, $spk, $row, $products);
                }
            }

            if ($totalNg <= 0 && ($totalCheckedBefore + $totalCheck) >= ($totalPlan - 0.001)) {
                $spk->update(['status' => 'closed']);
            }

            return $qc;
        });

        return redirect()->route('qc.production.print', $qc)->with('success', 'QC produksi berhasil disimpan.');
    }

    public function print(QcProduction $qc): View
    {
        return view('qc.production.print', [
            'qc' => $qc->load(['spk.salesOrder.customer', 'spk.salesOrder.items.item', 'inspector', 'approver']),
            'company' => app(MmsContext::class)->company(),
            'history' => QcProduction::query()->with('inspector')->where('spk_id', $qc->spk_id)->where('id', '<=', $qc->id)->orderBy('id')->get(),
        ]);
    }

    private function products(Spk $spk)
    {
        return $spk->salesOrder?->items->map(fn ($row) => [
            'item_id' => $row->item_id,
            'item_name' => $row->item?->item_name ?: $row->item_name_manual,
            'item_code' => $row->item?->item_code ?: $row->item_code_manual,
            'unit' => $row->item?->unit ?: $row->unit_manual,
            'qc_type' => $row->item?->qc_type,
            'plan_qty' => (float) $row->qty,
        ]) ?? collect();
    }

    private function normalizedStatus(QcProduction $qc): string
    {
        if ((float) $qc->qty_reject > 0 && in_array((string) $qc->status, ['', 'completed'], true)) {
            return 'ng';
        }

        return strtolower((string) $qc->status);
    }

    private function checklistNotes(Request $request): string
    {
        $laser = implode(', ', $request->input('chk_laser', [])) ?: '-';
        $bend = implode(', ', $request->input('chk_bend', [])) ?: '-';
        $weld = implode(', ', $request->input('chk_weld', [])) ?: '-';
        if ($laser === '-' && $bend === '-' && $weld === '-') {
            return '';
        }

        return "\n[QC CHECKLIST]\n- Laser: {$laser}\n- Bending: {$bend}\n- Welding: {$weld}";
    }

    private function createNcr(QcProduction $qc, Spk $spk, array $row, $products): void
    {
        $item = $products->firstWhere('item_id', $row['item_id']);
        $operatorId = ProductionAssignment::query()
            ->where('spk_id', $spk->id)
            ->where('status', 'completed')
            ->whereNotNull('operator_id')
            ->latest('end_time')
            ->latest('id')
            ->value('operator_id');

        Ncr::query()->create([
            'ncr_number' => $this->nextNcrNumber(),
            'source_type' => 'production',
            'reference_id' => $qc->id,
            'item_id' => $row['item_id'],
            'qty_reject' => $row['qty_ng'],
            'issue_description' => 'Reject QC Produksi - SPK ' . $spk->spk_number . ' (' . ($item['item_code'] ?? '-') . ' - ' . ($item['item_name'] ?? 'Item') . ')',
            'root_cause' => '',
            'corrective_action' => '',
            'operator_id' => $operatorId,
            'disposition' => 'pending',
            'status' => 'waiting_responsible',
            'created_by' => auth()->id(),
        ]);
    }

    private function nextQcNumber(): string
    {
        $ym = now()->format('ym');
        $count = QcProduction::query()->where('qc_number', 'like', "QC-PRD-{$ym}-%")->count() + 1;

        return 'QC-PRD-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function nextNcrNumber(): string
    {
        $ym = now()->format('ym');
        $count = Ncr::query()->where('ncr_number', 'like', "NCR-{$ym}-%")->count() + 1;

        return 'NCR-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
