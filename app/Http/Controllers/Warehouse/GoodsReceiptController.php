<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $status = $this->rememberedFilter($request, 'status', '');
        $search = $this->rememberedFilter($request, 'search', '');
        $receipts = GoodsReceipt::query()
            ->with(['purchaseOrder.supplier', 'customer'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('gr_number', 'like', $term)
                        ->orWhere('delivery_note_number', 'like', $term)
                        ->orWhere('vehicle_number', 'like', $term)
                        ->orWhere('driver_name', 'like', $term)
                        ->orWhereHas('purchaseOrder', fn ($po) => $po->where('po_number', 'like', $term))
                        ->orWhereHas('purchaseOrder.supplier', fn ($s) => $s->where('name', 'like', $term))
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term));
                });
            })
            ->latest('id')
            ->get();

        return view('warehouse.receipts.index', compact('receipts', 'status', 'search'));
    }

    public function create(Request $request): View
    {
        $receipt = new GoodsReceipt([
            'gr_number' => 'AUTO',
            'gr_date' => now()->toDateString(),
            'receipt_type' => 'normal',
            'purchase_order_id' => $request->integer('po_id') ?: null,
            'received_by' => auth()->user()?->fullname,
            'status' => 'draft',
        ]);

        return $this->form($receipt, collect([]), false);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $rows = $this->itemRows($request);
        if ($rows === []) {
            return back()->withInput()->withErrors('Minimal 1 item penerimaan harus diisi.');
        }

        DB::transaction(function () use ($data, $rows, $request): void {
            $receipt = GoodsReceipt::query()->create($data + [
                'gr_number' => $this->nextGrNumber(),
                'status' => $request->has('submit_qc') ? 'qc_pending' : 'draft',
                'created_by' => auth()->id(),
            ]);
            $receipt->items()->createMany($rows);
        });

        return redirect()->route('warehouse.receipts.index')->with('success', 'Penerimaan Barang berhasil disimpan!');
    }

    public function edit(GoodsReceipt $receipt): View
    {
        return $this->form($receipt->load('items.item'), $receipt->items, true);
    }

    public function update(Request $request, GoodsReceipt $receipt): RedirectResponse
    {
        $data = $this->validated($request);
        $rows = $this->itemRows($request);
        if ($rows === []) {
            return back()->withInput()->withErrors('Minimal 1 item penerimaan harus diisi.');
        }

        DB::transaction(function () use ($receipt, $data, $rows, $request): void {
            $receipt->update($data + ['status' => $request->has('submit_qc') ? 'qc_pending' : 'draft']);
            $receipt->items()->delete();
            $receipt->items()->createMany($rows);
        });

        return redirect()->route('warehouse.receipts.index')->with('success', 'Penerimaan Barang berhasil disimpan!');
    }

    public function destroy(GoodsReceipt $receipt): RedirectResponse
    {
        if ($receipt->status !== 'draft') {
            return back()->withErrors('Gagal! Hanya status Draft yang bisa dihapus.');
        }
        $receipt->delete();

        return redirect()->route('warehouse.receipts.index')->with('success', 'Data penerimaan dihapus.');
    }

    public function poItems(PurchaseOrder $order): JsonResponse
    {
        $items = DB::table('purchase_order_items as poi')
            ->join('items as i', 'poi.item_id', '=', 'i.id')
            ->select('poi.item_id', 'poi.qty', 'i.item_code', 'i.item_name', 'i.unit')
            ->selectSub(function ($query) {
                $query->from('goods_receipt_items as gri')
                    ->join('goods_receipts as gr', 'gri.goods_receipt_id', '=', 'gr.id')
                    ->whereColumn('gr.purchase_order_id', 'poi.purchase_order_id')
                    ->whereColumn('gri.item_id', 'poi.item_id')
                    ->where('gr.status', '!=', 'rejected')
                    ->selectRaw('COALESCE(SUM(gri.qty_received), 0)');
            }, 'total_received')
            ->where('poi.purchase_order_id', $order->id)
            ->get();

        return response()->json($items);
    }

    private function form(GoodsReceipt $receipt, $items, bool $isEdit): View
    {
        return view('warehouse.receipts.form', [
            'receipt' => $receipt,
            'items' => $items,
            'isEdit' => $isEdit,
            'pos' => $this->openPurchaseOrders($receipt->purchase_order_id),
            'customers' => Customer::query()->orderBy('name')->get(),
            'rawMaterials' => Item::query()->whereIn('item_type', ['raw_material', 'consumable'])->orderBy('item_name')->get(),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'receipt_type' => ['required', 'in:normal,consignment'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'gr_date' => ['required', 'date'],
            'delivery_note_number' => ['required', 'string', 'max:50'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'vehicle_number' => ['nullable', 'string', 'max:20'],
            'received_by' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);
        if ($data['receipt_type'] === 'normal') {
            if (empty($data['purchase_order_id'])) {
                throw ValidationException::withMessages(['purchase_order_id' => 'Purchase Order wajib dipilih untuk penerimaan normal.']);
            }
            $data['customer_id'] = null;
        } else {
            if (empty($data['customer_id'])) {
                throw ValidationException::withMessages(['customer_id' => 'Customer wajib dipilih untuk penerimaan consignment.']);
            }
            $data['purchase_order_id'] = null;
        }

        return $data;
    }

    private function itemRows(Request $request): array
    {
        $rows = [];
        foreach ($request->input('item_id', []) as $i => $itemId) {
            $received = (float) ($request->input("qty_received.{$i}") ?? 0);
            if ((int) $itemId > 0 && $received > 0) {
                $rows[] = [
                    'item_id' => (int) $itemId,
                    'qty_po' => (float) ($request->input("qty_po.{$i}") ?? 0),
                    'qty_received' => $received,
                ];
            }
        }

        return $rows;
    }

    private function openPurchaseOrders(?int $currentId = null)
    {
        return PurchaseOrder::query()
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->leftJoinSub(
                DB::table('purchase_order_items')->select('purchase_order_id', DB::raw('SUM(qty) as total_qty'))->groupBy('purchase_order_id'),
                'pq',
                'pq.purchase_order_id',
                '=',
                'purchase_orders.id'
            )
            ->leftJoinSub(
                DB::table('goods_receipt_items as gri')
                    ->join('goods_receipts as gr', 'gri.goods_receipt_id', '=', 'gr.id')
                    ->where('gr.status', '!=', 'rejected')
                    ->select('gr.purchase_order_id', DB::raw('SUM(gri.qty_received) as total_received'))
                    ->groupBy('gr.purchase_order_id'),
                'pr',
                'pr.purchase_order_id',
                '=',
                'purchase_orders.id'
            )
            ->where(function ($query) use ($currentId): void {
                $query->whereIn('purchase_orders.status', ['approved_finance', 'sent', 'completed']);
                if ($currentId) {
                    $query->orWhere('purchase_orders.id', $currentId);
                }
            })
            ->where(function ($query) use ($currentId): void {
                $query->whereRaw('(COALESCE(pq.total_qty,0) - COALESCE(pr.total_received,0)) > 0');
                if ($currentId) {
                    $query->orWhere('purchase_orders.id', $currentId);
                }
            })
            ->orderByDesc('purchase_orders.id')
            ->get(['purchase_orders.id', 'purchase_orders.po_number', 'suppliers.name as supplier_name']);
    }

    private function nextGrNumber(): string
    {
        $ym = now()->format('ym');
        $count = GoodsReceipt::query()->where('gr_number', 'like', "GR-{$ym}-%")->count() + 1;

        return 'GR-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
