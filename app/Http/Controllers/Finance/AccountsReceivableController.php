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
        $deliveryNote = $request->integer('sj_id')
            ? DeliveryNote::query()->with(['salesOrder.customer', 'salesOrder.items.item', 'items.item'])->find($request->integer('sj_id'))
            : null;

        return view('finance.ar.form', [
            'invoice' => new Invoice([
                'invoice_number' => 'AUTO',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'status' => 'draft',
            ]),
            'deliveryNote' => $deliveryNote,
            'deliveryNotes' => $this->availableDeliveryNotes($deliveryNote?->id),
            'lines' => $deliveryNote ? $this->invoiceLines($deliveryNote) : collect(),
            'totals' => $deliveryNote ? $this->calculateTotals($deliveryNote, 0) : ['subtotal' => 0, 'discount' => 0, 'tax' => 0, 'grand' => 0],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedInvoice($request);
        $deliveryNote = DeliveryNote::query()->with(['salesOrder.customer', 'salesOrder.items', 'items'])->findOrFail($data['delivery_note_id']);
        $totals = $this->calculateTotals($deliveryNote, $this->money($data['discount_amount'] ?? '0'));
        $this->validateTaxNumber($data['tax_invoice_number'] ?? null);

        DB::transaction(function () use ($data, $deliveryNote, $totals): void {
            Invoice::query()->create([
                'invoice_number' => $this->nextInvoiceNumber(),
                'tax_invoice_number' => $this->cleanTaxNumber($data['tax_invoice_number'] ?? null),
                'delivery_note_id' => $deliveryNote->id,
                'customer_id' => $deliveryNote->salesOrder?->customer_id,
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
            ]);
        });

        return redirect()->route('finance.ar.index')->with('success', 'Invoice berhasil disimpan.');
    }

    public function edit(Invoice $invoice): View
    {
        if ($invoice->status !== 'draft') {
            abort(403, 'Hanya invoice draft yang dapat diedit.');
        }

        $invoice->load(['deliveryNote.salesOrder.customer', 'deliveryNote.salesOrder.items.item', 'deliveryNote.items.item']);

        return view('finance.ar.form', [
            'invoice' => $invoice,
            'deliveryNote' => $invoice->deliveryNote,
            'deliveryNotes' => $this->availableDeliveryNotes($invoice->delivery_note_id),
            'lines' => $invoice->deliveryNote ? $this->invoiceLines($invoice->deliveryNote) : collect(),
            'totals' => $invoice->deliveryNote ? $this->calculateTotals($invoice->deliveryNote, (float) $invoice->discount_amount) : ['subtotal' => 0, 'discount' => 0, 'tax' => 0, 'grand' => 0],
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->status !== 'draft') {
            return back()->withErrors('Hanya invoice draft yang dapat diedit.');
        }

        $data = $this->validatedInvoice($request, $invoice->id);
        $deliveryNote = DeliveryNote::query()->with(['salesOrder.customer', 'salesOrder.items', 'items'])->findOrFail($data['delivery_note_id']);
        $totals = $this->calculateTotals($deliveryNote, $this->money($data['discount_amount'] ?? '0'));
        $this->validateTaxNumber($data['tax_invoice_number'] ?? null, $invoice->id);

        $invoice->update([
            'tax_invoice_number' => $this->cleanTaxNumber($data['tax_invoice_number'] ?? null),
            'delivery_note_id' => $deliveryNote->id,
            'customer_id' => $deliveryNote->salesOrder?->customer_id,
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'],
            'subtotal' => $totals['subtotal'],
            'discount_amount' => $totals['discount'],
            'tax_amount' => $totals['tax'],
            'grand_total' => $totals['grand'],
            'notes' => $data['notes'] ?? null,
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

            if ($newStatus === 'paid' && in_array((string) ($locked->deliveryNote?->status ?? ''), ['approved', 'sent'], true)) {
                $locked->deliveryNote?->salesOrder?->update(['status' => 'completed']);
            }
        });

        return redirect()->route('finance.ar.index')->with('success', 'Pembayaran berhasil disimpan.');
    }

    public function print(Invoice $invoice): View
    {
        return view('finance.ar.print', [
            'invoice' => $invoice->load(['customer', 'deliveryNote.salesOrder', 'deliveryNote.items.item']),
            'company' => app(MmsContext::class)->company(),
            'lines' => $invoice->deliveryNote ? $this->invoiceLines($invoice->deliveryNote) : collect(),
        ]);
    }

    public function printTax(Invoice $invoice): View
    {
        return view('finance.ar.print-tax', [
            'invoice' => $invoice->load(['customer', 'deliveryNote.salesOrder', 'deliveryNote.items.item']),
            'company' => app(MmsContext::class)->company(),
            'lines' => $invoice->deliveryNote ? $this->invoiceLines($invoice->deliveryNote) : collect(),
        ]);
    }

    private function validatedInvoice(Request $request, ?int $invoiceId = null): array
    {
        return $request->validate([
            'delivery_note_id' => ['required', 'integer', 'exists:delivery_notes,id'],
            'tax_invoice_number' => ['nullable', 'string', 'max:40'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'discount_amount' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function availableDeliveryNotes(?int $selectedId = null)
    {
        return DeliveryNote::query()
            ->with('salesOrder.customer')
            ->whereIn('status', ['approved', 'sent'])
            ->where(function ($query) use ($selectedId): void {
                $query->whereDoesntHave('invoices', fn ($inv) => $inv->where('status', '!=', 'cancelled'));
                if ($selectedId) {
                    $query->orWhereKey($selectedId);
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

    private function calculateTotals(DeliveryNote $deliveryNote, float $discount): array
    {
        $deliveryNote->loadMissing('salesOrder');
        $subtotal = $this->invoiceLines($deliveryNote)->sum('total');
        $discount = min(max(0, $discount), $subtotal);
        $taxPercent = (float) ($deliveryNote->salesOrder?->ppn_percent ?? 11);
        $dpp = max(0, $subtotal - $discount);
        $tax = $dpp * ($taxPercent / 100);

        return ['subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax, 'grand' => $dpp + $tax];
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
