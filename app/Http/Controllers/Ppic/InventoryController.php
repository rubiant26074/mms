<?php

namespace App\Http\Controllers\Ppic;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $type = trim((string) $request->query('type', ''));
        $search = trim((string) $request->query('search', ''));

        $items = Item::query()
            ->when($type !== '', fn ($query) => $query->where('item_type', $type), fn ($query) => $query->whereIn('item_type', ['raw_material', 'wip', 'consumable']))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(fn ($sub) => $sub->where('item_name', 'like', $term)->orWhere('item_code', 'like', $term));
            })
            ->orderBy('current_stock')
            ->get();

        return view('ppic.inventory.index', compact('items', 'type', 'search'));
    }

    public function show(Item $item): View
    {
        $history = DB::query()
            ->fromSub(function ($query) use ($item): void {
                $query->selectRaw("'IN' as type, gr.gr_date as date, gr.gr_number as doc_no, gri.qty_received as qty, s.name as party, 'Penerimaan Supplier/Cust' as description")
                    ->from('goods_receipt_items as gri')
                    ->join('goods_receipts as gr', 'gri.goods_receipt_id', '=', 'gr.id')
                    ->leftJoin('purchase_orders as po', 'gr.purchase_order_id', '=', 'po.id')
                    ->leftJoin('suppliers as s', 'po.supplier_id', '=', 's.id')
                    ->where('gri.item_id', $item->id)
                    ->where('gr.status', 'approved')
                    ->unionAll(
                        DB::table('material_issue_items as mii')
                            ->selectRaw("'OUT' as type, mi.itr_date as date, mi.itr_number as doc_no, mii.qty_issued as qty, CONCAT('Produksi: ', spk.spk_number) as party, mi.notes as description")
                            ->join('material_issues as mi', 'mii.material_issue_id', '=', 'mi.id')
                            ->join('spk', 'mi.spk_id', '=', 'spk.id')
                            ->where('mii.item_id', $item->id)
                    );
            }, 'stock_history')
            ->orderByDesc('date')
            ->orderByDesc('doc_no')
            ->limit(100)
            ->get();

        return view('ppic.inventory.show', compact('item', 'history'));
    }
}
