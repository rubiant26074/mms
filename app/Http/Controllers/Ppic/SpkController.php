<?php

namespace App\Http\Controllers\Ppic;

use App\Http\Controllers\Controller;
use App\Models\Bom;
use App\Models\PurchaseRequest;
use App\Models\SalesOrder;
use App\Models\Spk;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SpkController extends Controller
{
    public function index(Request $request): View
    {
        $status = $this->rememberedFilter($request, 'status', '');
        $search = $this->rememberedFilter($request, 'search', '');
        $spks = Spk::query()
            ->with('salesOrder.customer')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('spk_number', 'like', $term)
                        ->orWhere('project_name', 'like', $term)
                        ->orWhereHas('salesOrder', fn ($so) => $so->where('so_number', 'like', $term)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)));
                });
            })
            ->latest('id')
            ->get();

        return view('ppic.spk.index', compact('spks', 'status', 'search'));
    }

    public function create(Request $request): View
    {
        $spk = new Spk([
            'spk_number' => 'AUTO',
            'spk_date' => now()->toDateString(),
            'deadline_date' => now()->addDays(7)->toDateString(),
            'sales_order_id' => $request->query('so_id'),
            'priority' => 'normal',
            'status' => 'draft',
        ]);

        return $this->form($spk, false);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        [$materials, $missing] = $this->materialsForSo((int) $data['sales_order_id']);
        if ($missing !== []) {
            return back()->withInput()->withErrors('Tidak bisa simpan: BOM belum lengkap untuk ' . implode(', ', $missing) . '.');
        }

        DB::transaction(function () use ($data, $materials): void {
            $processes = $data['processes'] ?? [];
            unset($data['processes']);
            $spk = Spk::query()->create($data + [
                'spk_number' => $this->nextSpkNumber($data['spk_date']),
                'required_processes' => implode(',', $processes),
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);
            $spk->materials()->createMany($materials);
            SalesOrder::query()->whereKey($spk->sales_order_id)->update(['status' => 'in_production']);
        });

        return redirect()->route('ppic.spk.index')->with('success', 'SPK berhasil disimpan!');
    }

    public function edit(Spk $spk): View
    {
        return $this->form($spk->load('materials.item'), true);
    }

    public function update(Request $request, Spk $spk): RedirectResponse
    {
        $data = $this->validated($request);
        [$materials, $missing] = $this->materialsForSo((int) $data['sales_order_id']);
        if ($missing !== []) {
            return back()->withInput()->withErrors('Tidak bisa simpan: BOM belum lengkap untuk ' . implode(', ', $missing) . '.');
        }

        DB::transaction(function () use ($spk, $data, $materials): void {
            $processes = $data['processes'] ?? [];
            unset($data['processes']);
            $spk->update($data + ['required_processes' => implode(',', $processes)]);
            $spk->materials()->delete();
            $spk->materials()->createMany($materials);
        });

        return redirect()->route('ppic.spk.index')->with('success', 'SPK berhasil disimpan!');
    }

    public function destroy(Spk $spk): RedirectResponse
    {
        if (auth()->user()?->role?->role_slug !== 'admin') {
            abort(403);
        }
        $spk->materials()->delete();
        $spk->partlists()->delete();
        $spk->delete();

        return redirect()->route('ppic.spk.index')->with('success', 'SPK berhasil dihapus.');
    }

    public function workflow(Request $request, Spk $spk, string $action): RedirectResponse
    {
        $user = $request->user();
        if ($action === 'submit' && in_array($spk->status, ['draft', 'preliminary'], true)) {
            $spk->update(['status' => 'waiting_eng']);
            return back()->with('success', 'SPK diajukan ke Engineering.');
        }
        if ($action === 'approve_mgr' && in_array($spk->status, ['waiting_mgr', 'final'], true) && in_array($user?->role?->role_slug, ['manager', 'admin'], true)) {
            $prNumber = null;
            DB::transaction(function () use ($spk, $user, &$prNumber): void {
                $spk->update(['status' => 'released', 'approved_by_mgr' => $user->id, 'approved_at_mgr' => now()]);
                $shortages = [];
                foreach ($spk->materials()->with('item')->get() as $material) {
                    if (($material->item?->ownership ?? 'internal') === 'customer') {
                        continue;
                    }
                    $short = max((float) $material->qty_required - (float) ($material->item?->current_stock ?? 0), 0);
                    if ($short > 0.0001) {
                        $shortages[] = ['item_id' => $material->item_id, 'qty' => $short, 'notes' => 'Auto shortage from ' . $spk->spk_number . ' | Need: ' . ((float) $material->qty_required) . ' | Stock: ' . ((float) ($material->item?->current_stock ?? 0))];
                    }
                }
                if ($shortages === []) {
                    return;
                }
                $tag = '[AUTO-SPK-ID:' . $spk->id . ']';
                $existing = PurchaseRequest::query()->where('notes', 'like', "%{$tag}%")->whereIn('status', ['draft', 'submitted', 'approved', 'partial'])->first();
                if ($existing) {
                    $prNumber = $existing->pr_number;
                    return;
                }
                $pr = PurchaseRequest::query()->create([
                    'pr_number' => $this->nextPrNumber(),
                    'pr_date' => now()->toDateString(),
                    'required_date' => optional($spk->deadline_date)->format('Y-m-d') ?: now()->toDateString(),
                    'notes' => 'AUTO-GENERATE dari SPK: ' . $spk->spk_number . ' ' . $tag,
                    'status' => 'approved',
                    'created_by' => $user->id,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);
                $pr->items()->createMany($shortages);
                $prNumber = $pr->pr_number;
            });

            return back()->with('success', $prNumber ? "SPK Dirilis. PR otomatis {$prNumber} dibuat/tersedia untuk shortage material." : 'SPK Dirilis ke Produksi.');
        }
        if ($action === 'receive_spv' && $spk->status === 'released' && in_array($user?->role?->role_slug, ['supervisor', 'admin'], true)) {
            $spk->update(['status' => 'in_production', 'approved_by_spv' => $user->id, 'approved_at_spv' => now()]);
            return back()->with('success', 'SPK Diterima Supervisor Workshop.');
        }

        return back()->withErrors('Aksi tidak valid atau status sudah berubah.');
    }

    public function print(Spk $spk): View
    {
        return view('ppic.spk.print', [
            'spk' => $spk->load(['salesOrder.customer', 'salesOrder.items.item', 'materials.item', 'creator']),
            'company' => app(MmsContext::class)->company(),
        ]);
    }

    private function form(Spk $spk, bool $isEdit): View
    {
        $salesOrders = SalesOrder::query()
            ->where(function ($query) use ($spk): void {
                $query->whereIn('status', ['confirmed', 'in_production']);
                if ($spk->sales_order_id) {
                    $query->orWhere('id', $spk->sales_order_id);
                }
            })
            ->latest('id')
            ->get();
        [$materials, $missing, $soItems] = $spk->sales_order_id ? $this->materialsForSo((int) $spk->sales_order_id, true) : [[], [], collect([])];
        if ($isEdit && $spk->materials->isNotEmpty()) {
            $materials = $spk->materials->map(fn ($m) => [
                'item_id' => $m->item_id,
                'item_code' => $m->item?->item_code,
                'item_name' => $m->item?->item_name,
                'unit' => $m->item?->unit,
                'current_stock' => $m->item?->current_stock,
                'qty_required' => $m->qty_required,
            ])->all();
        }

        return view('ppic.spk.form', compact('spk', 'isEdit', 'salesOrders', 'materials', 'missing', 'soItems'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'sales_order_id' => ['required', Rule::exists('sales_orders', 'id')],
            'spk_date' => ['required', 'date'],
            'deadline_date' => ['required', 'date'],
            'priority' => ['required', 'in:normal,urgent'],
            'notes' => ['nullable', 'string'],
            'processes' => ['nullable', 'array'],
            'processes.*' => ['string'],
        ]);
    }

    private function materialsForSo(int $soId, bool $withSoItems = false): array
    {
        $so = SalesOrder::query()->with('items.item')->find($soId);
        $needs = [];
        $missing = [];
        if (! $so) {
            return $withSoItems ? [[], ['SO tidak ditemukan'], collect([])] : [[], ['SO tidak ditemukan']];
        }

        foreach ($so->items as $soItem) {
            $bom = Bom::query()->with('details.material')
                ->where('item_id', $soItem->item_id)
                ->whereIn('status', ['active', 'locked'])
                ->latest('id')
                ->first();
            if (! $bom || $bom->details->isEmpty()) {
                $missing[] = $soItem->item?->item_name ?: $soItem->item_name_manual ?: ('Item #' . $soItem->item_id);
                continue;
            }
            $output = (float) $bom->qty_result > 0 ? (float) $bom->qty_result : 1;
            foreach ($bom->details as $detail) {
                $need = ((float) $detail->qty / $output) * (float) $soItem->qty;
                $key = $detail->material_id;
                if (! isset($needs[$key])) {
                    $needs[$key] = [
                        'item_id' => $key,
                        'item_code' => $detail->material?->item_code,
                        'item_name' => $detail->material?->item_name,
                        'unit' => $detail->material?->unit,
                        'current_stock' => $detail->material?->current_stock,
                        'qty_required' => 0,
                    ];
                }
                $needs[$key]['qty_required'] += $need;
            }
        }

        $materials = array_values($needs);

        return $withSoItems ? [$materials, array_values(array_unique($missing)), $so->items] : [$materials, array_values(array_unique($missing))];
    }

    private function nextSpkNumber(string $date): string
    {
        $ym = date('ym', strtotime($date));
        $last = Spk::query()->where('spk_number', 'like', "SPK-{$ym}-%")->latest('id')->value('spk_number');
        $seq = 1;
        if ($last && preg_match('/^SPK-\d{4}-(\d+)$/', (string) $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return "SPK-{$ym}-" . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function nextPrNumber(): string
    {
        $ym = now()->format('ym');
        $count = PurchaseRequest::query()->where('pr_number', 'like', "PR-{$ym}-%")->count() + 1;

        return 'PR-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
