<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRfq;
use App\Models\Supplier;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RfqController extends Controller
{
    private const STATUSES = ['draft', 'sent', 'evaluated', 'closed', 'cancelled'];

    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));

        $rfqs = PurchaseRfq::query()
            ->withCount(['quotes as line_count', 'quotes as vendor_count' => fn ($query) => $query->select(DB::raw('count(distinct supplier_id)'))])
            ->withSum('quotes as est_total', DB::raw('qty * unit_price'))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(fn ($sub) => $sub->where('rfq_number', 'like', $term)->orWhere('notes', 'like', $term));
            })
            ->latest('id')
            ->get();

        return view('procurement.rfqs.index', compact('rfqs', 'status', 'search'));
    }

    public function create(): View
    {
        return $this->form(new PurchaseRfq([
            'rfq_number' => '',
            'rfq_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'draft',
        ]), collect(), false);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $rows = $this->quoteRows($request);
        if ($rows === []) {
            return back()->withInput()->withErrors('Minimal 1 baris quote harus diisi.');
        }

        $rfq = DB::transaction(function () use ($data, $rows): PurchaseRfq {
            $rfq = PurchaseRfq::query()->create($data + [
                'rfq_number' => $data['rfq_number'] ?: $this->nextRfqNumber($data['rfq_date']),
                'created_by' => auth()->id(),
            ]);
            $rfq->quotes()->createMany($rows);

            return $rfq;
        });

        return redirect()->route('procurement.rfqs.show', $rfq)->with('success', 'RFQ berhasil disimpan.');
    }

    public function show(PurchaseRfq $rfq): View
    {
        return view('procurement.rfqs.show', [
            'rfq' => $rfq->load(['creator', 'quotes.supplier']),
            'bestPrices' => $this->bestPrices($rfq),
            'vendorSummary' => $this->vendorSummary($rfq),
        ]);
    }

    public function edit(PurchaseRfq $rfq): View
    {
        return $this->form($rfq, $rfq->quotes()->orderBy('id')->get(), true);
    }

    public function update(Request $request, PurchaseRfq $rfq): RedirectResponse
    {
        $data = $this->validated($request, $rfq);
        $rows = $this->quoteRows($request);
        if ($rows === []) {
            return back()->withInput()->withErrors('Minimal 1 baris quote harus diisi.');
        }

        DB::transaction(function () use ($rfq, $data, $rows): void {
            $rfq->update($data + ['rfq_number' => $data['rfq_number'] ?: $this->nextRfqNumber($data['rfq_date'])]);
            $rfq->quotes()->delete();
            $rfq->quotes()->createMany($rows);
        });

        return redirect()->route('procurement.rfqs.show', $rfq)->with('success', 'RFQ berhasil disimpan.');
    }

    public function destroy(PurchaseRfq $rfq): RedirectResponse
    {
        DB::transaction(function () use ($rfq): void {
            $rfq->quotes()->delete();
            $rfq->delete();
        });

        return redirect()->route('procurement.rfqs.index')->with('success', 'RFQ berhasil dihapus.');
    }

    public function print(PurchaseRfq $rfq): View
    {
        return view('procurement.rfqs.print', [
            'rfq' => $rfq->load(['quotes.supplier']),
            'bestPrices' => $this->bestPrices($rfq),
            'company' => app(MmsContext::class)->company(),
        ]);
    }

    public function convertToPo(Request $request, PurchaseRfq $rfq): RedirectResponse
    {
        $supplierId = $request->integer('supplier_id');
        if ($supplierId <= 0 || in_array($rfq->status, ['closed', 'cancelled'], true)) {
            return back()->withErrors('Parameter konversi RFQ tidak valid.');
        }

        $quotes = $rfq->quotes()->with('supplier')->where('supplier_id', $supplierId)->get();
        if ($quotes->isEmpty()) {
            return back()->withErrors('Quote vendor untuk RFQ ini tidak ditemukan.');
        }

        $rows = [];
        $missing = [];
        foreach ($quotes as $quote) {
            $itemId = $quote->item_id ?: $this->findItemId((string) $quote->item_name);
            if (! $itemId) {
                $missing[] = (string) $quote->item_name;
                continue;
            }
            $rows[] = [
                'item_id' => $itemId,
                'qty' => (float) $quote->qty,
                'unit_price' => (float) $quote->unit_price,
                'subtotal' => (float) $quote->qty * (float) $quote->unit_price,
                'notes' => trim((string) $quote->notes . ($quote->lead_time_days ? ' | Lead: ' . $quote->lead_time_days . ' hari' : '')),
                'pr_item_id' => null,
            ];
        }

        if ($missing !== []) {
            return back()->withErrors('Konversi gagal. Item belum terdaftar di master: ' . implode(', ', array_slice(array_unique($missing), 0, 3)));
        }

        $order = DB::transaction(function () use ($rfq, $supplierId, $quotes, $rows): PurchaseOrder {
            $subtotal = array_sum(array_column($rows, 'subtotal'));
            $tax = $subtotal * 0.11;
            $supplierName = $quotes->first()?->supplier?->name ?: 'Vendor';
            $order = PurchaseOrder::query()->create([
                'po_number' => $this->nextPoNumber(),
                'purchase_request_id' => null,
                'supplier_id' => $supplierId,
                'po_date' => now()->toDateString(),
                'delivery_date' => $rfq->due_date?->toDateString() ?: now()->addDays(7)->toDateString(),
                'subtotal' => $subtotal,
                'payment_terms' => 'Net 30 Days',
                'ppn_percent' => 11,
                'discount_amount' => 0,
                'tax_amount' => $tax,
                'status' => 'draft',
                'notes' => trim("Auto-convert dari RFQ {$rfq->rfq_number} (Vendor: {$supplierName})\n" . (string) $rfq->notes),
                'created_by' => auth()->id(),
                'grand_total' => $subtotal + $tax,
            ]);
            $order->items()->createMany($rows);
            if (in_array($rfq->status, ['draft', 'sent'], true)) {
                $rfq->update(['status' => 'evaluated']);
            }

            return $order;
        });

        return redirect()->route('procurement.orders.edit', $order)->with('success', 'RFQ berhasil dikonversi menjadi Draft PO.');
    }

    private function form(PurchaseRfq $rfq, $lines, bool $isEdit): View
    {
        return view('procurement.rfqs.form', [
            'rfq' => $rfq,
            'lines' => $lines,
            'isEdit' => $isEdit,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    private function validated(Request $request, ?PurchaseRfq $rfq = null): array
    {
        return $request->validate([
            'rfq_number' => ['nullable', 'string', 'max:40', Rule::unique('purchase_rfqs', 'rfq_number')->ignore($rfq)],
            'rfq_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function quoteRows(Request $request): array
    {
        $rows = [];
        foreach ($request->input('item_name', []) as $i => $itemName) {
            $itemName = trim((string) $itemName);
            $qty = (float) ($request->input("qty.{$i}") ?? 0);
            $supplierId = (int) ($request->input("supplier_id.{$i}") ?? 0);
            if ($itemName === '' || $qty <= 0 || $supplierId <= 0) {
                continue;
            }
            $rows[] = [
                'item_name' => $itemName,
                'qty' => $qty,
                'unit' => trim((string) ($request->input("unit.{$i}") ?? 'Unit')) ?: 'Unit',
                'supplier_id' => $supplierId,
                'unit_price' => max(0, (float) ($request->input("unit_price.{$i}") ?? 0)),
                'lead_time_days' => $request->filled("lead_time_days.{$i}") ? (int) $request->input("lead_time_days.{$i}") : null,
                'notes' => trim((string) ($request->input("line_notes.{$i}") ?? '')),
            ];
        }

        return $rows;
    }

    private function bestPrices(PurchaseRfq $rfq): array
    {
        $best = [];
        foreach ($rfq->quotes as $quote) {
            $key = trim((string) $quote->item_name) . '|' . trim((string) $quote->unit);
            $price = (float) $quote->unit_price;
            if (! isset($best[$key]) || $price < $best[$key]) {
                $best[$key] = $price;
            }
        }

        return $best;
    }

    private function vendorSummary(PurchaseRfq $rfq): array
    {
        $best = $this->bestPrices($rfq);
        $summary = [];
        foreach ($rfq->quotes as $quote) {
            $supplier = $quote->supplier;
            $id = (int) $quote->supplier_id;
            $key = trim((string) $quote->item_name) . '|' . trim((string) $quote->unit);
            $summary[$id] ??= [
                'supplier_id' => $id,
                'supplier_code' => $supplier?->code ?: '',
                'supplier_name' => $supplier?->name ?: '',
                'line_count' => 0,
                'best_count' => 0,
                'total' => 0,
            ];
            $summary[$id]['line_count']++;
            $summary[$id]['best_count'] += (float) $quote->unit_price <= (float) ($best[$key] ?? 0) ? 1 : 0;
            $summary[$id]['total'] += (float) $quote->qty * (float) $quote->unit_price;
        }
        usort($summary, fn ($a, $b) => $a['total'] <=> $b['total']);

        return $summary;
    }

    private function findItemId(string $itemName): ?int
    {
        $exact = Item::query()->where('item_name', $itemName)->value('id');
        if ($exact) {
            return (int) $exact;
        }

        $like = Item::query()->where('item_name', 'like', "%{$itemName}%")->orderBy('id')->value('id');

        return $like ? (int) $like : null;
    }

    private function nextRfqNumber(string $date): string
    {
        $ym = date('ym', strtotime($date ?: now()->toDateString()));
        $count = PurchaseRfq::query()->where('rfq_number', 'like', "RFQ-{$ym}-%")->count() + 1;

        return 'RFQ-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function nextPoNumber(): string
    {
        $ym = now()->format('ym');
        $count = PurchaseOrder::query()->where('po_number', 'like', "PO-{$ym}-%")->count() + 1;

        return 'PO-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
