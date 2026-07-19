<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountsPayableController extends Controller
{
    public function index(Request $request): View
    {
        $status = $this->rememberedFilter($request, 'status', '');
        $search = $this->rememberedFilter($request, 'search', '');
        $bills = SupplierBill::query()
            ->with(['supplier', 'purchaseOrder'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('bill_number', 'like', $term)
                        ->orWhere('supplier_inv_number', 'like', $term)
                        ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', $term))
                        ->orWhereHas('purchaseOrder', fn ($po) => $po->where('po_number', 'like', $term));
                });
            })
            ->latest('id')
            ->get();
        $totalOutstanding = SupplierBill::query()
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->selectRaw('COALESCE(SUM(grand_total - paid_amount),0) AS total')
            ->value('total');

        return view('finance.ap.index', compact('bills', 'status', 'search', 'totalOutstanding'));
    }

    public function create(Request $request): View
    {
        $po = $request->integer('po_id') ? PurchaseOrder::query()->with(['supplier', 'items.item'])->find($request->integer('po_id')) : null;

        return view('finance.ap.form', [
            'bill' => new SupplierBill([
                'bill_number' => 'AUTO',
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'status' => 'draft',
                'purchase_order_id' => $po?->id,
                'supplier_id' => $po?->supplier_id,
                'notes' => $po ? 'Ref PO: ' . $po->po_number : null,
            ]),
            'purchaseOrder' => $po,
            'purchaseOrders' => $this->availablePurchaseOrders($po?->id),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'lines' => $po ? $this->billLinesFromPo($po) : collect(),
            'totals' => $po ? $this->calculateTotals($this->billLinesFromPo($po), (float) $po->discount_amount, (float) $po->ppn_percent) : $this->emptyTotals(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedBill($request);
        $rows = $this->validatedRows($data);
        $totals = $this->calculateTotals(collect($rows), $this->money($data['discount_amount'] ?? '0'), (float) ($data['ppn_percent'] ?? 11));

        DB::transaction(function () use ($data, $rows, $totals): void {
            $bill = SupplierBill::query()->create([
                'bill_number' => $this->nextBillNumber(),
                'supplier_inv_number' => $data['supplier_inv_number'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'],
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'tax_amount' => $totals['tax'],
                'grand_total' => $totals['grand'],
                'paid_amount' => 0,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $bill->items()->createMany($rows);
            if ($bill->purchase_order_id) {
                PurchaseOrder::query()->whereKey($bill->purchase_order_id)->update(['status' => 'completed']);
            }
        });

        return redirect()->route('finance.ap.index')->with('success', 'Tagihan berhasil disimpan.');
    }

    public function edit(SupplierBill $bill): View
    {
        if ($bill->status !== 'draft') {
            abort(403, 'Tagihan non-draft tidak dapat diedit.');
        }
        $bill->load(['items.item', 'purchaseOrder.supplier']);

        return view('finance.ap.form', [
            'bill' => $bill,
            'purchaseOrder' => $bill->purchaseOrder,
            'purchaseOrders' => $this->availablePurchaseOrders($bill->purchase_order_id),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'lines' => $bill->items->map(fn ($row) => [
                'item_id' => $row->item_id,
                'item_code' => $row->item?->item_code,
                'item_name' => $row->item?->item_name,
                'unit' => $row->item?->unit,
                'qty' => (float) $row->qty,
                'unit_price' => (float) $row->unit_price,
                'subtotal' => (float) $row->subtotal,
            ]),
            'totals' => ['subtotal' => $bill->subtotal, 'discount' => $bill->discount_amount, 'tax' => $bill->tax_amount, 'grand' => $bill->grand_total],
        ]);
    }

    public function update(Request $request, SupplierBill $bill): RedirectResponse
    {
        if ($bill->status !== 'draft') {
            return back()->withErrors('Tagihan non-draft tidak dapat diedit.');
        }
        $data = $this->validatedBill($request);
        $rows = $this->validatedRows($data);
        $totals = $this->calculateTotals(collect($rows), $this->money($data['discount_amount'] ?? '0'), (float) ($data['ppn_percent'] ?? 11));

        DB::transaction(function () use ($bill, $data, $rows, $totals): void {
            $bill->update([
                'supplier_inv_number' => $data['supplier_inv_number'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'],
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'tax_amount' => $totals['tax'],
                'grand_total' => $totals['grand'],
                'notes' => $data['notes'] ?? null,
            ]);
            $bill->items()->delete();
            $bill->items()->createMany($rows);
        });

        return redirect()->route('finance.ap.index')->with('success', 'Tagihan berhasil diperbarui.');
    }

    public function post(SupplierBill $bill): RedirectResponse
    {
        if ($bill->status !== 'draft' || ! $bill->items()->exists()) {
            return back()->withErrors('Hanya tagihan draft dengan item yang bisa diposting.');
        }
        $bill->update(['status' => 'unpaid']);
        $this->createJournalIfPossible($bill->bill_date->toDateString(), $bill->bill_number, 'Posting Tagihan Supplier ' . ($bill->supplier?->name ?? ''), [
            ['account' => '1-1301', 'debit' => (float) $bill->grand_total, 'credit' => 0],
            ['account' => '2-1001', 'debit' => 0, 'credit' => (float) $bill->grand_total],
        ], 'purchase');

        return redirect()->route('finance.ap.index')->with('success', 'Tagihan berhasil diposting.');
    }

    public function unpost(SupplierBill $bill): RedirectResponse
    {
        if ($bill->status !== 'unpaid' || (float) $bill->paid_amount > 0 || $bill->payments()->exists()) {
            return back()->withErrors('Unpost hanya untuk tagihan unpaid tanpa pembayaran.');
        }
        DB::transaction(function () use ($bill): void {
            $bill->update(['status' => 'draft']);
            $journalIds = DB::table('journals')->where('reference_no', $bill->bill_number)->where('type', 'purchase')->pluck('id');
            DB::table('journal_items')->whereIn('journal_id', $journalIds)->delete();
            DB::table('journals')->whereIn('id', $journalIds)->delete();
        });

        return redirect()->route('finance.ap.index')->with('success', 'Posting tagihan berhasil dibatalkan.');
    }

    public function destroy(SupplierBill $bill): RedirectResponse
    {
        if ($bill->status !== 'draft') {
            return back()->withErrors('Hanya tagihan draft yang dapat dihapus.');
        }
        DB::transaction(function () use ($bill): void {
            $bill->items()->delete();
            $bill->delete();
        });

        return redirect()->route('finance.ap.index')->with('success', 'Tagihan draft berhasil dihapus.');
    }

    public function payment(SupplierBill $bill): View
    {
        $bill->load(['supplier', 'payments.recorder']);
        if ($bill->grand_total - $bill->paid_amount <= 0.01) {
            abort(403, 'Tagihan ini sudah lunas.');
        }

        return view('finance.ap.payment', ['bill' => $bill]);
    }

    public function storePayment(Request $request, SupplierBill $bill): RedirectResponse
    {
        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'string'],
            'method' => ['required', Rule::in(['Transfer Bank', 'Cash', 'Cek / Giro'])],
            'notes' => ['nullable', 'string'],
        ]);
        $amount = $this->money($data['amount']);
        if ($amount <= 0) {
            return back()->withErrors('Jumlah pembayaran harus lebih dari 0.');
        }

        DB::transaction(function () use ($bill, $data, $amount): void {
            $locked = SupplierBill::query()->lockForUpdate()->findOrFail($bill->id);
            $remaining = (float) $locked->grand_total - (float) $locked->paid_amount;
            if ($remaining <= 0.01) {
                throw ValidationException::withMessages(['amount' => 'Tagihan ini sudah lunas.']);
            }
            if ($amount > ($remaining + 1)) {
                throw ValidationException::withMessages(['amount' => 'Jumlah pembayaran melebihi sisa tagihan terbaru.']);
            }
            $newPaid = (float) $locked->paid_amount + $amount;
            $newStatus = $newPaid >= ((float) $locked->grand_total - 1) ? 'paid' : 'partial';
            $locked->payments()->create([
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'method' => $data['method'],
                'notes' => $data['notes'] ?? null,
                'recorded_by' => auth()->id(),
            ]);
            $locked->update(['paid_amount' => $newPaid, 'status' => $newStatus]);
            $this->createJournalIfPossible($data['payment_date'], $locked->bill_number, 'Pembayaran Hutang (' . $data['method'] . ')', [
                ['account' => '2-1001', 'debit' => $amount, 'credit' => 0],
                ['account' => $data['method'] === 'Cash' ? '1-1001' : '1-1002', 'debit' => 0, 'credit' => $amount],
            ], 'payment');
        });

        return redirect()->route('finance.ap.index')->with('success', 'Pembayaran berhasil disimpan.');
    }

    private function validatedBill(Request $request): array
    {
        return $request->validate([
            'supplier_inv_number' => ['required', 'string', 'max:120'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'discount_amount' => ['nullable', 'string'],
            'ppn_percent' => ['nullable', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string'],
            'item_id' => ['required', 'array'],
            'item_id.*' => ['required', 'integer', 'exists:items,id'],
            'qty' => ['required', 'array'],
            'qty.*' => ['required', 'numeric', 'min:0.0001'],
            'price' => ['required', 'array'],
            'price.*' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function validatedRows(array $data): array
    {
        $rows = [];
        foreach ($data['item_id'] as $idx => $itemId) {
            $qty = (float) ($data['qty'][$idx] ?? 0);
            $price = (float) ($data['price'][$idx] ?? 0);
            if ($qty > 0) {
                $rows[] = ['item_id' => (int) $itemId, 'qty' => $qty, 'unit_price' => $price, 'subtotal' => $qty * $price];
            }
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['item_id' => 'Item tagihan belum diisi.']);
        }

        return $rows;
    }

    private function availablePurchaseOrders(?int $selectedId = null)
    {
        return PurchaseOrder::query()
            ->with('supplier')
            ->where(function ($query) use ($selectedId): void {
                $query->whereIn('status', ['approved', 'sent']);
                if ($selectedId) {
                    $query->orWhere('id', $selectedId);
                }
            })
            ->latest('id')
            ->get();
    }

    private function billLinesFromPo(PurchaseOrder $po)
    {
        return $po->items->map(fn ($row) => [
            'item_id' => $row->item_id,
            'item_code' => $row->item?->item_code,
            'item_name' => $row->item?->item_name,
            'unit' => $row->item?->unit,
            'qty' => (float) $row->qty,
            'unit_price' => (float) $row->unit_price,
            'subtotal' => (float) $row->subtotal,
        ]);
    }

    private function calculateTotals($rows, float $discount, float $ppnPercent): array
    {
        $subtotal = (float) collect($rows)->sum('subtotal');
        $discount = min(max(0, $discount), $subtotal);
        $dpp = max(0, $subtotal - $discount);
        $tax = $dpp * ($ppnPercent / 100);

        return ['subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax, 'grand' => $dpp + $tax];
    }

    private function emptyTotals(): array
    {
        return ['subtotal' => 0, 'discount' => 0, 'tax' => 0, 'grand' => 0];
    }

    private function money(string|int|float|null $value): float
    {
        return (float) preg_replace('/[^0-9]/', '', (string) $value);
    }

    private function nextBillNumber(): string
    {
        $ym = now()->format('ym');
        $count = SupplierBill::query()->where('bill_number', 'like', "BILL-{$ym}-%")->count() + 1;

        return 'BILL-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function createJournalIfPossible(string $date, string $referenceNo, string $description, array $items, string $type): void
    {
        if (! DB::getSchemaBuilder()->hasTable('journals') || ! DB::getSchemaBuilder()->hasTable('journal_items')) {
            return;
        }
        $rows = [];
        foreach ($items as $item) {
            $coaId = DB::table('coa')->where('account_code', $item['account'])->value('id');
            if (! $coaId) {
                return;
            }
            $rows[] = ['coa_id' => $coaId, 'debit' => $item['debit'], 'credit' => $item['credit']];
        }
        $ym = Carbon::parse($date)->format('ym');
        $count = DB::table('journals')->where('journal_no', 'like', "JRN-{$ym}-%")->count() + 1;
        $journalId = DB::table('journals')->insertGetId([
            'journal_no' => 'JRN-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT),
            'journal_date' => $date,
            'reference_no' => $referenceNo,
            'description' => $description,
            'type' => $type,
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);
        foreach ($rows as $row) {
            DB::table('journal_items')->insert(['journal_id' => $journalId, 'coa_id' => $row['coa_id'], 'debit' => $row['debit'], 'credit' => $row['credit']]);
        }
    }
}
