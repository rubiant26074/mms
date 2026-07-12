<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountsReceivableController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));

        $invoices = Invoice::query()
            ->with('customer')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('invoice_number', 'like', $term)
                        ->orWhere('tax_invoice_number', 'like', $term)
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $term));
                });
            })
            ->latest('id')
            ->get();

        $totalOutstanding = Invoice::query()
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->selectRaw('COALESCE(SUM(grand_total - paid_amount),0) as total')
            ->value('total');

        return view('finance.ar.index', compact('invoices', 'status', 'search', 'totalOutstanding'));
    }

    public function create(Request $request): View
    {
        $refType = $request->query('ref_type', $request->has('so_id') ? 'so' : 'sj');

        $deliveryNote = null;
        $salesOrder = null;
        $lines = collect();
        $totals = ['subtotal' => 0, 'discount' => 0, 'dp_subtraction' => 0, 'tax' => 0, 'grand' => 0];

        $salesOrderId = null;
        if ($refType === 'sj' && $request->has('sj_id')) {
            $deliveryNote = DeliveryNote::query()->with(['salesOrder.customer', 'salesOrder.items.item', 'items.item'])->find($request->integer('sj_id'));
            if ($deliveryNote) {
                $salesOrderId = $deliveryNote->sales_order_id;
            }
        } elseif ($refType === 'so' && $request->has('so_id')) {
            $salesOrder = \App\Models\SalesOrder::query()->with(['customer', 'items.item'])->find($request->integer('so_id'));
            if ($salesOrder) {
                $salesOrderId = $salesOrder->id;
            }
        }

        $existingDp = 0;
        if ($salesOrderId) {
            $existingDp = Invoice::query()
                ->where('sales_order_id', $salesOrderId)
                ->where('invoice_type', 'dp')
                ->where('status', '!=', 'cancelled')
                ->sum('subtotal');
        }

        if ($refType === 'sj' && $deliveryNote) {
            $lines = $this->invoiceLines($deliveryNote);
            $totals = $this->calculateTotals($deliveryNote, 0, $existingDp);
        } elseif ($refType === 'so' && $salesOrder) {
            $lines = $this->invoiceLinesForSo($salesOrder);
            $totals = $this->calculateTotalsForSo($salesOrder, 0, $existingDp);
        }

        return view('finance.ar.form', [
            'invoice' => new Invoice([
                'invoice_number' => 'AUTO',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'status' => 'draft',
                'invoice_type' => 'normal',
            ]),
            'refType' => $refType,
            'deliveryNote' => $deliveryNote,
            'deliveryNotes' => $this->availableDeliveryNotes($deliveryNote?->id),
            'salesOrder' => $salesOrder,
            'salesOrders' => $this->availableSalesOrders($salesOrder?->id),
            'lines' => $lines,
            'totals' => $totals,
            'existingDp' => $existingDp,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedInvoice($request);
        $this->validateTaxNumber($data['tax_invoice_number'] ?? null);

        $refType = $data['ref_type'];
        $deliveryNoteId = null;
        $salesOrderId = null;
        $customerId = null;
        $invoiceType = $data['invoice_type'] ?? 'normal';

        $refObject = null;
        if ($refType === 'sj') {
            $refObject = DeliveryNote::query()->with(['salesOrder.customer', 'salesOrder.items', 'items'])->findOrFail($data['delivery_note_id']);
            $deliveryNoteId = $refObject->id;
            $salesOrderId = $refObject->sales_order_id;
            $customerId = $refObject->salesOrder?->customer_id;
        } else {
            $refObject = \App\Models\SalesOrder::query()->with(['customer', 'items'])->findOrFail($data['sales_order_id']);
            $salesOrderId = $refObject->id;
            $customerId = $refObject->customer_id;
        }

        $dpPercent = null;
        $dpAmount = 0;

        if ($invoiceType === 'dp') {
            $refSubtotal = 0;
            if ($refType === 'sj') {
                $refSubtotal = $this->invoiceLines($refObject)->sum('total');
            } else {
                $refSubtotal = $this->invoiceLinesForSo($refObject)->sum('total');
            }

            $dpType = $request->input('dp_type', 'percent');
            if ($dpType === 'percent') {
                $dpPercent = (float) ($data['dp_percent'] ?? 0);
                $dpAmount = $refSubtotal * ($dpPercent / 100);
            } else {
                $dpAmount = $this->money($data['dp_amount'] ?? '0');
                $dpPercent = $refSubtotal > 0 ? ($dpAmount / $refSubtotal) * 100 : 0;
            }

            $taxPercent = (float) ($refObject->ppn_percent ?? 11);
            $taxAmount = $dpAmount * ($taxPercent / 100);
            $grandTotal = $dpAmount + $taxAmount;

            $totals = [
                'subtotal' => $dpAmount,
                'discount' => 0,
                'tax' => $taxAmount,
                'grand' => $grandTotal,
            ];
        } else {
            $dpAmount = Invoice::query()
                ->where('sales_order_id', $salesOrderId)
                ->where('invoice_type', 'dp')
                ->where('status', '!=', 'cancelled')
                ->sum('subtotal');

            if ($refType === 'sj') {
                $totals = $this->calculateTotals($refObject, $this->money($data['discount_amount'] ?? '0'), $dpAmount);
            } else {
                $totals = $this->calculateTotalsForSo($refObject, $this->money($data['discount_amount'] ?? '0'), $dpAmount);
            }
        }

        DB::transaction(function () use ($data, $deliveryNoteId, $salesOrderId, $customerId, $totals, $invoiceType, $dpPercent, $dpAmount): void {
            Invoice::query()->create([
                'invoice_number' => $this->nextInvoiceNumber(),
                'tax_invoice_number' => $this->cleanTaxNumber($data['tax_invoice_number'] ?? null),
                'delivery_note_id' => $deliveryNoteId,
                'sales_order_id' => $salesOrderId,
                'customer_id' => $customerId,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'tax_amount' => $totals['tax'],
                'grand_total' => $totals['grand'],
                'paid_amount' => 0,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
                'invoice_type' => $invoiceType,
                'dp_percent' => $dpPercent,
                'dp_amount' => $dpAmount,
            ]);
        });

        return redirect()->route('finance.ar.index')->with('success', 'Invoice berhasil disimpan.');
    }

    public function edit(Invoice $invoice): View
    {
        if ($invoice->status !== 'draft') {
            abort(403, 'Hanya invoice draft yang dapat diedit.');
        }

        $invoice->load(['deliveryNote.salesOrder.customer', 'deliveryNote.salesOrder.items.item', 'deliveryNote.items.item', 'salesOrder.customer', 'salesOrder.items.item']);

        $refType = $invoice->sales_order_id ? 'so' : 'sj';
        $lines = collect();
        $totals = ['subtotal' => 0, 'discount' => 0, 'dp_subtraction' => 0, 'tax' => 0, 'grand' => 0];

        $salesOrderId = $invoice->sales_order_id ?: ($invoice->deliveryNote?->sales_order_id);
        $existingDp = 0;
        if ($salesOrderId) {
            $existingDp = Invoice::query()
                ->where('sales_order_id', $salesOrderId)
                ->where('invoice_type', 'dp')
                ->where('status', '!=', 'cancelled')
                ->where('id', '!=', $invoice->id)
                ->sum('subtotal');
        }

        if ($invoice->invoice_type === 'dp') {
            $refObject = $invoice->salesOrder ?: ($invoice->deliveryNote ?: null);
            $lines = $this->invoiceLinesForDp($refObject, (float) $invoice->subtotal, $invoice->dp_percent);
            $totals = [
                'subtotal' => (float) $invoice->subtotal,
                'discount' => 0,
                'dp_subtraction' => 0,
                'tax' => (float) $invoice->tax_amount,
                'grand' => (float) $invoice->grand_total,
            ];
        } else {
            if ($refType === 'sj' && $invoice->deliveryNote) {
                $lines = $this->invoiceLines($invoice->deliveryNote);
                $totals = $this->calculateTotals($invoice->deliveryNote, (float) $invoice->discount_amount, (float) $invoice->dp_amount);
            } elseif ($refType === 'so' && $invoice->salesOrder) {
                $lines = $this->invoiceLinesForSo($invoice->salesOrder);
                $totals = $this->calculateTotalsForSo($invoice->salesOrder, (float) $invoice->discount_amount, (float) $invoice->dp_amount);
            }
        }

        return view('finance.ar.form', [
            'invoice' => $invoice,
            'refType' => $refType,
            'deliveryNote' => $invoice->deliveryNote,
            'deliveryNotes' => $this->availableDeliveryNotes($invoice->delivery_note_id),
            'salesOrder' => $invoice->salesOrder,
            'salesOrders' => $this->availableSalesOrders($invoice->sales_order_id),
            'lines' => $lines,
            'totals' => $totals,
            'existingDp' => $existingDp,
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->status !== 'draft') {
            return back()->withErrors('Hanya invoice draft yang dapat diedit.');
        }

        $data = $this->validatedInvoice($request, $invoice->id);
        $this->validateTaxNumber($data['tax_invoice_number'] ?? null, $invoice->id);

        $refType = $data['ref_type'];
        $deliveryNoteId = null;
        $salesOrderId = null;
        $customerId = null;
        $invoiceType = $data['invoice_type'] ?? 'normal';

        $refObject = null;
        if ($refType === 'sj') {
            $refObject = DeliveryNote::query()->with(['salesOrder.customer', 'salesOrder.items', 'items'])->findOrFail($data['delivery_note_id']);
            $deliveryNoteId = $refObject->id;
            $salesOrderId = $refObject->sales_order_id;
            $customerId = $refObject->salesOrder?->customer_id;
        } else {
            $refObject = \App\Models\SalesOrder::query()->with(['customer', 'items'])->findOrFail($data['sales_order_id']);
            $salesOrderId = $refObject->id;
            $customerId = $refObject->customer_id;
        }

        $dpPercent = null;
        $dpAmount = 0;

        if ($invoiceType === 'dp') {
            $refSubtotal = 0;
            if ($refType === 'sj') {
                $refSubtotal = $this->invoiceLines($refObject)->sum('total');
            } else {
                $refSubtotal = $this->invoiceLinesForSo($refObject)->sum('total');
            }

            $dpType = $request->input('dp_type', 'percent');
            if ($dpType === 'percent') {
                $dpPercent = (float) ($data['dp_percent'] ?? 0);
                $dpAmount = $refSubtotal * ($dpPercent / 100);
            } else {
                $dpAmount = $this->money($data['dp_amount'] ?? '0');
                $dpPercent = $refSubtotal > 0 ? ($dpAmount / $refSubtotal) * 100 : 0;
            }

            $taxPercent = (float) ($refObject->ppn_percent ?? 11);
            $taxAmount = $dpAmount * ($taxPercent / 100);
            $grandTotal = $dpAmount + $taxAmount;

            $totals = [
                'subtotal' => $dpAmount,
                'discount' => 0,
                'tax' => $taxAmount,
                'grand' => $grandTotal,
            ];
        } else {
            $dpAmount = Invoice::query()
                ->where('sales_order_id', $salesOrderId)
                ->where('invoice_type', 'dp')
                ->where('status', '!=', 'cancelled')
                ->where('id', '!=', $invoice->id)
                ->sum('subtotal');

            if ($refType === 'sj') {
                $totals = $this->calculateTotals($refObject, $this->money($data['discount_amount'] ?? '0'), $dpAmount);
            } else {
                $totals = $this->calculateTotalsForSo($refObject, $this->money($data['discount_amount'] ?? '0'), $dpAmount);
            }
        }

        $invoice->update([
            'tax_invoice_number' => $this->cleanTaxNumber($data['tax_invoice_number'] ?? null),
            'delivery_note_id' => $deliveryNoteId,
            'sales_order_id' => $salesOrderId,
            'customer_id' => $customerId,
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'],
            'subtotal' => $totals['subtotal'],
            'discount_amount' => $totals['discount'],
            'tax_amount' => $totals['tax'],
            'grand_total' => $totals['grand'],
            'notes' => $data['notes'] ?? null,
            'invoice_type' => $invoiceType,
            'dp_percent' => $dpPercent,
            'dp_amount' => $dpAmount,
        ]);

        return redirect()->route('finance.ar.index')->with('success', 'Invoice berhasil diperbarui.');
    }

    public function post(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status !== 'draft') {
            return back()->withErrors('Hanya invoice draft yang bisa diterbitkan.');
        }
        if ((float) $invoice->tax_amount > 0 && empty($invoice->tax_invoice_number)) {
            return back()->withErrors('No. Seri Faktur Pajak wajib diisi sebelum invoice diterbitkan.');
        }

        $invoice->update(['status' => 'unpaid']);
        $this->createJournalIfPossible($invoice->invoice_date->toDateString(), $invoice->invoice_number, 'Penerbitan Invoice ' . ($invoice->customer?->name ?? ''), [
            ['account' => '1-1201', 'debit' => (float) $invoice->grand_total, 'credit' => 0],
            ['account' => '4-1001', 'debit' => 0, 'credit' => (float) $invoice->grand_total],
        ], 'sales');

        return redirect()->route('finance.ar.index')->with('success', 'Invoice berhasil diterbitkan.');
    }

    public function unpost(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status !== 'unpaid' || (float) $invoice->paid_amount > 0 || $invoice->payments()->exists()) {
            return back()->withErrors('Unpost hanya untuk invoice unpaid tanpa pembayaran.');
        }

        DB::transaction(function () use ($invoice): void {
            $invoice->update(['status' => 'draft']);
            DB::table('journal_items')->whereIn('journal_id', DB::table('journals')->where('reference_no', $invoice->invoice_number)->where('type', 'sales')->pluck('id'))->delete();
            DB::table('journals')->where('reference_no', $invoice->invoice_number)->where('type', 'sales')->delete();
        });

        return redirect()->route('finance.ar.index')->with('success', 'Penerbitan invoice berhasil dibatalkan.');
    }

    public function payment(Invoice $invoice): View
    {
        $invoice->load(['customer', 'deliveryNote.salesOrder', 'payments.recorder']);
        if ($invoice->grand_total - $invoice->paid_amount <= 0.01) {
            abort(403, 'Invoice ini sudah lunas.');
        }

        return view('finance.ar.payment', ['invoice' => $invoice]);
    }

    public function storePayment(Request $request, Invoice $invoice): RedirectResponse
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

        DB::transaction(function () use ($invoice, $data, $amount): void {
            $locked = Invoice::query()->with('deliveryNote.salesOrder')->lockForUpdate()->findOrFail($invoice->id);
            $remaining = (float) $locked->grand_total - (float) $locked->paid_amount;
            if ($remaining <= 0.01) {
                throw ValidationException::withMessages(['amount' => 'Invoice ini sudah lunas.']);
            }
            if ($amount > ($remaining + 1)) {
                throw ValidationException::withMessages(['amount' => 'Jumlah pembayaran melebihi sisa tagihan.']);
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

            $this->createJournalIfPossible($data['payment_date'], $locked->invoice_number, 'Penerimaan Pembayaran (' . $data['method'] . ')', [
                ['account' => $data['method'] === 'Cash' ? '1-1001' : '1-1002', 'debit' => $amount, 'credit' => 0],
                ['account' => '1-1201', 'debit' => 0, 'credit' => $amount],
            ], 'receipt');

            if ($newStatus === 'paid') {
                if ($locked->deliveryNote?->salesOrder) {
                    $locked->deliveryNote->salesOrder->update(['status' => 'completed']);
                } elseif ($locked->salesOrder) {
                    $locked->salesOrder->update(['status' => 'completed']);
                }
            }
        });

        return redirect()->route('finance.ar.index')->with('success', 'Pembayaran berhasil disimpan.');
    }

    public function print(Invoice $invoice): View
    {
        $lines = collect();
        if ($invoice->invoice_type === 'dp') {
            $refObject = $invoice->salesOrder ?: ($invoice->deliveryNote ?: null);
            $lines = $this->invoiceLinesForDp($refObject, (float) $invoice->subtotal, $invoice->dp_percent);
        } else {
            if ($invoice->delivery_note_id && $invoice->deliveryNote) {
                $lines = $this->invoiceLines($invoice->deliveryNote);
            } elseif ($invoice->sales_order_id && $invoice->salesOrder) {
                $lines = $this->invoiceLinesForSo($invoice->salesOrder);
            }
        }

        return view('finance.ar.print', [
            'invoice' => $invoice->load(['customer', 'deliveryNote.salesOrder', 'deliveryNote.items.item', 'salesOrder.customer', 'salesOrder.items.item']),
            'company' => app(MmsContext::class)->company(),
            'lines' => $lines,
        ]);
    }

    public function printTax(Invoice $invoice): View
    {
        $lines = collect();
        if ($invoice->invoice_type === 'dp') {
            $refObject = $invoice->salesOrder ?: ($invoice->deliveryNote ?: null);
            $lines = $this->invoiceLinesForDp($refObject, (float) $invoice->subtotal, $invoice->dp_percent);
        } else {
            if ($invoice->delivery_note_id && $invoice->deliveryNote) {
                $lines = $this->invoiceLines($invoice->deliveryNote);
            } elseif ($invoice->sales_order_id && $invoice->salesOrder) {
                $lines = $this->invoiceLinesForSo($invoice->salesOrder);
            }
        }

        return view('finance.ar.print-tax', [
            'invoice' => $invoice->load(['customer', 'deliveryNote.salesOrder', 'deliveryNote.items.item', 'salesOrder.customer', 'salesOrder.items.item']),
            'company' => app(MmsContext::class)->company(),
            'lines' => $lines,
        ]);
    }

    private function validatedInvoice(Request $request, ?int $invoiceId = null): array
    {
        return $request->validate([
            'ref_type' => ['required', 'in:sj,so'],
            'delivery_note_id' => ['required_if:ref_type,sj', 'nullable', 'integer', 'exists:delivery_notes,id'],
            'sales_order_id' => ['required_if:ref_type,so', 'nullable', 'integer', 'exists:sales_orders,id'],
            'tax_invoice_number' => ['nullable', 'string', 'max:40'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'discount_amount' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'invoice_type' => ['required', 'in:normal,dp'],
            'dp_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'dp_amount' => ['nullable', 'string'],
        ]);
    }

    private function availableSalesOrders(?int $selectedId = null)
    {
        return \App\Models\SalesOrder::query()
            ->with('customer')
            ->whereIn('status', ['confirmed', 'in_production', 'delivered', 'completed'])
            ->where(function ($query) use ($selectedId): void {
                $query->whereDoesntHave('invoices', fn ($inv) => $inv->where('status', '!=', 'cancelled'));
                if ($selectedId) {
                    $query->orWhere('id', $selectedId);
                }
            })
            ->latest('id')
            ->get();
    }

    private function invoiceLinesForSo(\App\Models\SalesOrder $salesOrder)
    {
        return $salesOrder->items->map(function ($line): array {
            return [
                'item_code' => $line->item?->item_code,
                'item_name' => $line->item?->item_name ?: $line->item_name_manual,
                'unit' => $line->item?->unit ?: $line->unit_manual,
                'qty_sent' => (float) $line->qty,
                'unit_price' => (float) $line->unit_price,
                'total' => (float) $line->qty * (float) $line->unit_price,
            ];
        });
    }

    private function calculateTotalsForSo(\App\Models\SalesOrder $salesOrder, float $discount, float $dpSubtraction = 0): array
    {
        $subtotal = $this->invoiceLinesForSo($salesOrder)->sum('total');
        $discount = min(max(0, $discount), $subtotal);
        $taxPercent = (float) ($salesOrder->ppn_percent ?? 11);
        $dpp = max(0, $subtotal - $discount - $dpSubtraction);
        $tax = $dpp * ($taxPercent / 100);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'dp_subtraction' => $dpSubtraction,
            'tax' => $tax,
            'grand' => $dpp + $tax,
        ];
    }

    private function availableDeliveryNotes(?int $selectedId = null)
    {
        return DeliveryNote::query()
            ->with('salesOrder.customer')
            ->whereIn('status', ['approved', 'sent'])
            ->where(function ($query) use ($selectedId): void {
                $query->whereDoesntHave('invoices', fn ($inv) => $inv->where('status', '!=', 'cancelled'));
                if ($selectedId) {
                    $query->orWhere('id', $selectedId);
                }
            })
            ->latest('id')
            ->get();
    }

    private function invoiceLines(DeliveryNote $deliveryNote)
    {
        $deliveryNote->loadMissing(['items.item', 'salesOrder.items']);
        $soItems = $deliveryNote->salesOrder?->items?->keyBy('item_id') ?? collect();

        return $deliveryNote->items->map(function ($line) use ($soItems): array {
            $soLine = $soItems->get($line->item_id);
            $unitPrice = (float) ($soLine?->unit_price ?? 0);

            return [
                'item_code' => $line->item?->item_code,
                'item_name' => $line->item?->item_name,
                'unit' => $line->item?->unit,
                'qty_sent' => (float) $line->qty_sent,
                'unit_price' => $unitPrice,
                'total' => (float) $line->qty_sent * $unitPrice,
            ];
        });
    }

    private function calculateTotals(DeliveryNote $deliveryNote, float $discount, float $dpSubtraction = 0): array
    {
        $deliveryNote->loadMissing('salesOrder');
        $subtotal = $this->invoiceLines($deliveryNote)->sum('total');
        $discount = min(max(0, $discount), $subtotal);
        $taxPercent = (float) ($deliveryNote->salesOrder?->ppn_percent ?? 11);
        $dpp = max(0, $subtotal - $discount - $dpSubtraction);
        $tax = $dpp * ($taxPercent / 100);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'dp_subtraction' => $dpSubtraction,
            'tax' => $tax,
            'grand' => $dpp + $tax,
        ];
    }

    private function invoiceLinesForDp($refObject, float $dpAmount, ?float $dpPercent)
    {
        $refName = '';
        if ($refObject instanceof \App\Models\SalesOrder) {
            $refName = 'SO No. ' . $refObject->so_number;
        } elseif ($refObject instanceof \App\Models\DeliveryNote) {
            $refName = 'SJ No. ' . $refObject->dn_number;
        } else {
            $refName = 'SO/SJ';
        }

        $percentLabel = $dpPercent ? ' ' . ($dpPercent + 0) . '%' : '';

        return collect([[
            'item_code' => 'DP',
            'item_name' => 'Uang Muka / Down Payment' . $percentLabel . ' atas ' . $refName,
            'unit' => 'Lot',
            'qty_sent' => 1,
            'unit_price' => $dpAmount,
            'total' => $dpAmount,
        ]]);
    }

    private function validateTaxNumber(?string $value, ?int $exceptId = null): void
    {
        $taxNumber = $this->cleanTaxNumber($value);
        if ($taxNumber === null) {
            return;
        }
        if (! preg_match('/^\d{3}\.\d{3}-\d{2}\.\d{8}$/', $taxNumber)) {
            throw ValidationException::withMessages(['tax_invoice_number' => 'Format No. Seri Faktur Pajak tidak valid. Gunakan format 000.000-YY.12345678']);
        }

        $exists = Invoice::query()
            ->where('tax_invoice_number', $taxNumber)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['tax_invoice_number' => 'No. Seri Faktur Pajak sudah digunakan pada invoice lain.']);
        }
    }

    private function cleanTaxNumber(?string $value): ?string
    {
        $clean = preg_replace('/\s+/', '', trim((string) $value));

        return $clean === '' ? null : $clean;
    }

    private function money(string|int|float|null $value): float
    {
        return (float) preg_replace('/[^0-9]/', '', (string) $value);
    }

    private function nextInvoiceNumber(): string
    {
        $ym = now()->format('ym');
        $count = Invoice::query()->where('invoice_number', 'like', "INV-{$ym}-%")->count() + 1;

        return 'INV-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
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
            DB::table('journal_items')->insert([
                'journal_id' => $journalId,
                'coa_id' => $row['coa_id'],
                'debit' => $row['debit'],
                'credit' => $row['credit'],
            ]);
        }
    }
}
