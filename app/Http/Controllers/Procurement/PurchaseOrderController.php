<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\SpkMaterial;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'approver', 'financeApprover'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(fn ($sub) => $sub->where('po_number', 'like', $term)->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $term)));
            })
            ->latest('id')
            ->get();

        return view('procurement.orders.index', compact('orders', 'status', 'search'));
    }

    public function create(Request $request): View
    {
        $order = new PurchaseOrder([
            'po_number' => 'AUTO',
            'po_date' => now()->toDateString(),
            'delivery_date' => now()->addDays(3)->toDateString(),
            'payment_terms' => 'Net 30 Days',
            'ppn_percent' => 11,
            'discount_amount' => 0,
            'status' => 'draft',
        ]);
        $items = collect([]);
        $prId = $request->integer('pr_id') ?: null;
        if ($prId) {
            $pr = PurchaseRequest::query()->with('items.item')->find($prId);
            if ($pr) {
                $order->purchase_request_id = $pr->id;
                $order->notes = trim("Ref PR: {$pr->pr_number}\n" . (string) $pr->notes);
                $items = $this->itemsFromPurchaseRequest($pr);
            }
        }

        return $this->form($order, $items, false);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $rows = $this->itemRows($request);
        if ($rows === []) {
            return back()->withInput()->withErrors('Minimal 1 item PO harus diisi.');
        }

        DB::transaction(function () use ($data, $rows): void {
            $totals = $this->totals($rows, (float) $data['discount_amount'], (float) $data['ppn_percent']);
            $order = PurchaseOrder::query()->create($data + $totals + [
                'po_number' => $this->nextPoNumber(),
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);
            $order->items()->createMany($rows);
            $this->applyPrProgress($order->purchase_request_id, $rows);
        });

        return redirect()->route('procurement.orders.index')->with('success', 'PO berhasil disimpan!');
    }

    public function edit(PurchaseOrder $order): View|RedirectResponse
    {
        if ($order->status !== 'draft') {
            return redirect()->route('procurement.orders.index')->withErrors('PO hanya bisa diedit saat Draft.');
        }

        return $this->form($order->load('items.item'), $order->items, true);
    }

    public function update(Request $request, PurchaseOrder $order): RedirectResponse
    {
        if ($order->status !== 'draft') {
            return redirect()->route('procurement.orders.index')->withErrors('PO hanya bisa diedit saat Draft.');
        }
        $data = $this->validated($request);
        $rows = $this->itemRows($request);
        if ($rows === []) {
            return back()->withInput()->withErrors('Minimal 1 item PO harus diisi.');
        }

        DB::transaction(function () use ($order, $data, $rows): void {
            $this->rollbackPrItems($order);
            $totals = $this->totals($rows, (float) $data['discount_amount'], (float) $data['ppn_percent']);
            $order->update($data + $totals + ['status' => 'draft']);
            $order->items()->delete();
            $order->items()->createMany($rows);
            $this->applyPrProgress($order->purchase_request_id, $rows);
        });

        return redirect()->route('procurement.orders.index')->with('success', 'PO berhasil disimpan!');
    }

    public function destroy(PurchaseOrder $order): RedirectResponse
    {
        if (! in_array($order->status, ['draft', 'cancelled'], true)) {
            return back()->withErrors('Hanya PO Draft/Cancelled yang boleh dihapus.');
        }

        DB::transaction(function () use ($order): void {
            $prId = $order->purchase_request_id;
            $this->rollbackPrItems($order);
            $order->delete();
            $this->refreshPrStatus($prId);
        });

        return redirect()->route('procurement.orders.index')->with('success', 'PO berhasil dihapus.');
    }

    public function workflow(Request $request, PurchaseOrder $order, string $action): RedirectResponse
    {
        $user = $request->user();
        if ($action === 'submit' && $user?->hasPermission('purch_po_manage') && $order->status === 'draft') {
            $order->update(['status' => 'submitted']);
            return back()->with('success', 'PO berhasil diajukan untuk approval.');
        }
        if ($action === 'approve' && $user?->hasPermission('purch_po_approve') && $order->status === 'submitted') {
            $order->update(['status' => 'approved_pm', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            return back()->with('success', 'PO berhasil di-Approve Plant Manager.');
        }
        if ($action === 'approve_finance' && $user?->hasPermission('purch_po_approve_finance') && $order->status === 'approved_pm') {
            $order->update(['status' => 'approved_finance', 'finance_approved_by' => auth()->id(), 'finance_approved_at' => now()]);
            return back()->with('success', 'PO berhasil di-Approve Finance.');
        }
        if ($action === 'send_vendor' && $user?->hasPermission('purch_po_manage') && $order->status === 'approved_finance') {
            $order->update(['status' => 'sent']);
            return back()->with('success', 'Status PO diubah menjadi SENT.');
        }
        if ($action === 'cancel' && $user?->hasPermission('purch_po_manage')) {
            $order->update(['status' => 'cancelled']);
            return back()->with('success', 'PO dibatalkan.');
        }

        return back()->withErrors('Aksi tidak valid atau status sudah berubah.');
    }

    public function print(PurchaseOrder $order): View
    {
        return view('procurement.orders.print', [
            'order' => $order->load(['supplier', 'items.item', 'creator', 'approver', 'financeApprover']),
            'company' => app(\App\Services\MmsContext::class)->company(),
        ]);
    }

    private function form(PurchaseOrder $order, $items, bool $isEdit): View
    {
        return view('procurement.orders.form', [
            'order' => $order,
            'items' => $items,
            'isEdit' => $isEdit,
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'rawMaterials' => Item::query()->whereIn('item_type', ['raw_material', 'consumable'])->orderBy('item_name')->get(),
            'prs' => PurchaseRequest::query()->whereIn('status', ['approved', 'partial'])->latest('id')->get(['id', 'pr_number', 'notes']),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'purchase_request_id' => ['nullable', 'integer', 'exists:purchase_requests,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'po_date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'ppn_percent' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['purchase_request_id'] = $data['purchase_request_id'] ?? null;
        $data['discount_amount'] = (float) str_replace('.', '', (string) ($data['discount_amount'] ?? 0));
        $data['ppn_percent'] = (float) ($data['ppn_percent'] ?? 0);

        return $data;
    }

    private function itemRows(Request $request): array
    {
        $rows = [];
        foreach ($request->input('item_id', []) as $i => $itemId) {
            $qty = (float) ($request->input("qty.{$i}") ?? 0);
            $price = (float) ($request->input("price.{$i}") ?? 0);
            if ((int) $itemId > 0 && $qty > 0) {
                $rows[] = [
                    'item_id' => (int) $itemId,
                    'qty' => $qty,
                    'unit_price' => $price,
                    'subtotal' => $qty * $price,
                    'notes' => trim((string) ($request->input("item_notes.{$i}") ?? '')),
                    'pr_item_id' => $request->filled("pr_item_ref_id.{$i}") ? (int) $request->input("pr_item_ref_id.{$i}") : null,
                ];
            }
        }

        return $rows;
    }

    private function totals(array $rows, float $discount, float $ppnPercent): array
    {
        $subtotal = array_sum(array_map(fn ($row) => (float) $row['subtotal'], $rows));
        $dpp = max($subtotal - $discount, 0);
        $tax = $dpp * ($ppnPercent / 100);

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'grand_total' => $dpp + $tax,
        ];
    }

    private function itemsFromPurchaseRequest(PurchaseRequest $pr)
    {
        $rows = $pr->items
            ->filter(fn ($row) => ((float) $row->qty - (float) $row->qty_ordered) > 0)
            ->map(function ($row) {
                $remaining = (float) $row->qty - (float) $row->qty_ordered;

                return new \App\Models\PurchaseOrderItem([
                    'item_id' => $row->item_id,
                    'qty' => $remaining,
                    'unit_price' => 0,
                    'subtotal' => 0,
                    'notes' => '',
                    'pr_item_id' => $row->id,
                ]);
            })
            ->values();

        if ($rows->isNotEmpty() || ! preg_match('/\[REF-SPK:(\d+)\]/', (string) $pr->notes, $m)) {
            return $rows;
        }

        return SpkMaterial::query()
            ->with('item')
            ->where('spk_id', (int) $m[1])
            ->get()
            ->filter(fn ($row) => ($row->item?->ownership ?? 'internal') !== 'customer')
            ->map(function ($row) {
                $qty = max((float) $row->qty_required - (float) ($row->item?->current_stock ?? 0), (float) $row->qty_required);

                return new \App\Models\PurchaseOrderItem([
                    'item_id' => $row->item_id,
                    'qty' => $qty,
                    'unit_price' => 0,
                    'subtotal' => 0,
                    'notes' => '',
                    'pr_item_id' => null,
                ]);
            })
            ->values();
    }

    private function applyPrProgress(?int $prId, array $rows): void
    {
        foreach ($rows as $row) {
            if (! empty($row['pr_item_id'])) {
                PurchaseRequestItem::query()->whereKey($row['pr_item_id'])->increment('qty_ordered', (float) $row['qty']);
            }
        }
        $this->refreshPrStatus($prId);
    }

    private function rollbackPrItems(PurchaseOrder $order): void
    {
        foreach ($order->items as $row) {
            if ($row->pr_item_id) {
                DB::table('purchase_request_items')
                    ->where('id', $row->pr_item_id)
                    ->update(['qty_ordered' => DB::raw('GREATEST(IFNULL(qty_ordered,0) - ' . (float) $row->qty . ', 0)')]);
            }
        }
    }

    private function refreshPrStatus(?int $prId): void
    {
        if (! $prId) {
            return;
        }
        $remaining = PurchaseRequestItem::query()
            ->where('purchase_request_id', $prId)
            ->whereRaw('(qty - IFNULL(qty_ordered,0)) > 0.001')
            ->count();
        $ordered = PurchaseRequestItem::query()
            ->where('purchase_request_id', $prId)
            ->whereRaw('IFNULL(qty_ordered,0) > 0.001')
            ->count();
        $status = $ordered > 0 && $remaining > 0 ? 'partial' : ($ordered > 0 ? 'processed' : 'approved');
        PurchaseRequest::query()->whereKey($prId)->update(['status' => $status]);
    }

    private function nextPoNumber(): string
    {
        $ym = now()->format('ym');
        $count = PurchaseOrder::query()->where('po_number', 'like', "PO-{$ym}-%")->count() + 1;

        return 'PO-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
