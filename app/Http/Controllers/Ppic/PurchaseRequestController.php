<?php

namespace App\Http\Controllers\Ppic;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PurchaseRequest;
use App\Models\Spk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
        $prs = PurchaseRequest::query()
            ->with('creator')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(fn ($sub) => $sub->where('pr_number', 'like', $term)->orWhere('notes', 'like', $term)->orWhereHas('creator', fn ($u) => $u->where('fullname', 'like', $term)));
            })
            ->latest('id')
            ->get();

        return view('ppic.purchase_requests.index', compact('prs', 'status', 'search'));
    }

    public function create(Request $request): View
    {
        $pr = new PurchaseRequest([
            'pr_number' => 'AUTO',
            'pr_date' => now()->toDateString(),
            'required_date' => now()->addDays(3)->toDateString(),
            'status' => 'draft',
        ]);
        $items = collect([]);
        $spkId = $request->integer('spk_id') ?: null;
        $spk = $spkId ? Spk::query()->with('materials.item')->find($spkId) : null;
        if ($spk) {
            $pr->required_date = $spk->deadline_date;
            $pr->notes = "Auto-Generate dari SPK: {$spk->spk_number} Project: {$spk->project_name}";
            $items = $this->itemsFromSpk($spk);
        }

        return $this->form($pr, $items, false, $spkId);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $rows = $this->itemRows($request);
        if ($rows === []) {
            return back()->withInput()->withErrors('Minimal 1 item PR harus diisi.');
        }

        DB::transaction(function () use ($data, $rows, $request): void {
            if ($request->filled('spk_id') && ! str_contains((string) $data['notes'], '[REF-SPK:')) {
                $data['notes'] = trim((string) $data['notes']) . ' [REF-SPK:' . $request->integer('spk_id') . ']';
            }
            $pr = PurchaseRequest::query()->create($data + [
                'pr_number' => $this->nextPrNumber(),
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);
            $pr->items()->createMany($rows);
        });

        return redirect()->route('ppic.purchase_requests.index')->with('success', 'Purchase Request berhasil disimpan!');
    }

    public function edit(PurchaseRequest $purchaseRequest): View|RedirectResponse
    {
        if (! in_array($purchaseRequest->status, ['draft', 'rejected'], true)) {
            return redirect()->route('ppic.purchase_requests.index')->withErrors('PR hanya bisa diedit saat Draft/Rejected.');
        }

        return $this->form($purchaseRequest->load('items.item'), $purchaseRequest->items, true);
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        if (! in_array($purchaseRequest->status, ['draft', 'rejected'], true)) {
            return redirect()->route('ppic.purchase_requests.index')->withErrors('PR hanya bisa diedit saat Draft/Rejected.');
        }
        $data = $this->validated($request);
        $rows = $this->itemRows($request);
        if ($rows === []) {
            return back()->withInput()->withErrors('Minimal 1 item PR harus diisi.');
        }

        DB::transaction(function () use ($purchaseRequest, $data, $rows): void {
            $purchaseRequest->update($data);
            $purchaseRequest->items()->delete();
            $purchaseRequest->items()->createMany($rows);
        });

        return redirect()->route('ppic.purchase_requests.index')->with('success', 'Purchase Request berhasil disimpan!');
    }

    public function destroy(PurchaseRequest $purchaseRequest): RedirectResponse
    {
        if (! in_array($purchaseRequest->status, ['draft', 'rejected'], true) && auth()->user()?->role?->role_slug !== 'admin') {
            return back()->withErrors('Hanya PR Draft/Rejected yang boleh dihapus.');
        }
        $purchaseRequest->items()->delete();
        $purchaseRequest->delete();

        return redirect()->route('ppic.purchase_requests.index')->with('success', 'PR berhasil dihapus.');
    }

    public function workflow(Request $request, PurchaseRequest $purchaseRequest, string $action): RedirectResponse
    {
        if ($action === 'submit' && $request->user()?->hasPermission('ppic_pr_manage') && $purchaseRequest->status === 'draft') {
            $purchaseRequest->update(['status' => 'submitted']);
            return back()->with('success', 'PR diajukan.');
        }
        if ($action === 'approve' && $request->user()?->hasPermission('ppic_pr_approve') && $purchaseRequest->status === 'submitted') {
            $purchaseRequest->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now(), 'last_approval_date' => now()]);
            return back()->with('success', 'PR disetujui.');
        }

        return back()->withErrors('Aksi tidak valid atau status sudah berubah.');
    }

    public function print(PurchaseRequest $purchaseRequest): View
    {
        return view('ppic.purchase_requests.print', [
            'pr' => $purchaseRequest->load(['items.item', 'creator', 'approver']),
        ]);
    }

    private function form(PurchaseRequest $pr, $items, bool $isEdit, ?int $spkId = null): View
    {
        return view('ppic.purchase_requests.form', [
            'pr' => $pr,
            'items' => $items,
            'isEdit' => $isEdit,
            'spkId' => $spkId,
            'rawMaterials' => Item::query()->whereIn('item_type', ['raw_material', 'consumable'])->orderBy('item_name')->get(),
            'spkOptions' => Spk::query()->whereIn('status', ['preliminary', 'waiting_mgr', 'released', 'in_production'])->latest('id')->limit(200)->get(),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'pr_date' => ['required', 'date'],
            'required_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function itemRows(Request $request): array
    {
        $rows = [];
        foreach ($request->input('item_id', []) as $i => $itemId) {
            $qty = (float) ($request->input("qty.{$i}") ?? 0);
            if ((int) $itemId > 0 && $qty > 0) {
                $rows[] = ['item_id' => (int) $itemId, 'qty' => $qty, 'notes' => trim((string) ($request->input("item_notes.{$i}") ?? ''))];
            }
        }

        return $rows;
    }

    private function itemsFromSpk(Spk $spk)
    {
        return $spk->materials
            ->filter(fn ($m) => ($m->item?->ownership ?? 'internal') !== 'customer')
            ->map(function ($m) use ($spk) {
                $deficit = (float) $m->qty_required - (float) ($m->item?->current_stock ?? 0);
                $qty = $deficit > 0 ? $deficit : (((float) ($m->item?->current_stock ?? 0) <= (float) ($m->item?->min_stock ?? 0)) ? max((float) ($m->item?->min_stock ?? 0) * 2, 1) : 0);
                if ($qty <= 0) {
                    return null;
                }

                return new \App\Models\PurchaseRequestItem([
                    'item_id' => $m->item_id,
                    'qty' => $qty,
                    'notes' => 'Referensi BOM SPK ' . $spk->spk_number . ' | Need: ' . ((float) $m->qty_required) . ' | Stock: ' . ((float) ($m->item?->current_stock ?? 0)),
                ]);
            })
            ->filter()
            ->values();
    }

    private function nextPrNumber(): string
    {
        $ym = now()->format('ym');
        $count = PurchaseRequest::query()->where('pr_number', 'like', "PR-{$ym}-%")->count() + 1;

        return 'PR-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
