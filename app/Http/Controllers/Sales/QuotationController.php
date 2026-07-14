<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));

        $quotations = Quotation::query()
            ->with('customer')
            ->withCount(['items', 'salesOrders as active_so_count'])
            ->when($status !== '', function ($query) use ($status): void {
                if ($status === 'so_created') {
                    $query->whereHas('salesOrders', fn ($so) => $so->where('status', '<>', 'cancelled'));
                } elseif ($status === 'won') {
                    $query->where('status', 'won')->whereDoesntHave('salesOrders', fn ($so) => $so->where('status', '<>', 'cancelled'));
                } else {
                    $query->where('status', $status);
                }
            })
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('quote_number', 'like', $term)
                        ->orWhereHas('customer', fn ($cust) => $cust->where('name', 'like', $term));
                });
            })
            ->latest('id')
            ->get();

        return view('sales.quotations.index', compact('quotations', 'status', 'search'));
    }

    public function create(): View
    {
        return view('sales.quotations.form', [
            'quotation' => new Quotation([
                'quote_number' => 'AUTO',
                'quote_date' => now()->toDateString(),
                'payment_terms' => 'Net 30 Days',
                'ppn_percent' => 11,
                'tax_included' => false,
                'status' => 'draft',
            ]),
            'items' => collect([new QuotationItem(['ownership' => 'internal', 'qty' => 1, 'unit_manual' => 'Pcs'])]),
            'customers' => Customer::query()->orderBy('name')->get(),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validated($request);
        $computed = $this->computeItems($request);
        if ($computed['rows'] === []) {
            return back()->withInput()->withErrors('Minimal 1 baris item quotation harus diisi.');
        }

        $attachment = $this->storeAttachment($request);
        $taxIncluded = $payload['tax_mode'] === 'include';

        DB::transaction(function () use ($payload, $computed, $attachment, $taxIncluded): void {
            unset($payload['tax_mode']);
            $quotation = Quotation::query()->create($payload + [
                'quote_number' => $this->nextQuoteNumber(),
                'tax_included' => $taxIncluded,
                'subtotal' => $computed['subtotal'],
                'discount_amount' => $computed['discount'],
                'tax_amount' => $computed['tax'],
                'grand_total' => $computed['grand'],
                'status' => 'draft',
                'attachment' => $attachment,
                'created_by' => auth()->id(),
            ]);
            $quotation->items()->createMany($computed['rows']);
        });

        return redirect()->route('sales.quotations.index')->with('success', 'Penawaran berhasil disimpan!');
    }

    public function edit(Quotation $quotation): View|RedirectResponse
    {
        if (! in_array($quotation->status, ['draft', 'rejected'], true)) {
            return redirect()->route('sales.quotations.index')->withErrors('Quotation tidak bisa diubah pada status saat ini.');
        }

        return view('sales.quotations.form', [
            'quotation' => $quotation->load('items'),
            'items' => $quotation->items,
            'customers' => Customer::query()->orderBy('name')->get(),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        if (! in_array($quotation->status, ['draft', 'rejected'], true)) {
            return redirect()->route('sales.quotations.index')->withErrors('Quotation tidak bisa diubah pada status saat ini.');
        }

        $payload = $this->validated($request);
        $computed = $this->computeItems($request);
        if ($computed['rows'] === []) {
            return back()->withInput()->withErrors('Minimal 1 baris item quotation harus diisi.');
        }

        $attachment = $this->storeAttachment($request) ?: $quotation->attachment;
        $taxIncluded = $payload['tax_mode'] === 'include';

        DB::transaction(function () use ($quotation, $payload, $computed, $attachment, $taxIncluded): void {
            unset($payload['tax_mode']);
            $quotation->update($payload + [
                'tax_included' => $taxIncluded,
                'subtotal' => $computed['subtotal'],
                'discount_amount' => $computed['discount'],
                'tax_amount' => $computed['tax'],
                'grand_total' => $computed['grand'],
                'attachment' => $attachment,
            ]);
            $quotation->items()->delete();
            $quotation->items()->createMany($computed['rows']);
        });

        return redirect()->route('sales.quotations.index')->with('success', 'Penawaran berhasil disimpan!');
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        if (! in_array($quotation->status, ['draft', 'rejected'], true)) {
            return back()->withErrors('Hanya penawaran berstatus Draft atau Rejected yang boleh dihapus.');
        }

        $quotation->items()->delete();
        $quotation->delete();

        return redirect()->route('sales.quotations.index')->with('success', 'Quotation berhasil dihapus.');
    }

    public function workflow(Request $request, Quotation $quotation, string $action): RedirectResponse
    {
        $permission = in_array($action, ['approve', 'reject'], true)
            ? 'sales_quotation_approve'
            : 'sales_quotation_manage';
        if (! $request->user()?->hasPermission($permission)) {
            abort(403);
        }

        $message = 'Aksi tidak valid atau status sudah berubah.';

        DB::transaction(function () use ($quotation, $action, &$message): void {
            if ($action === 'submit' && $quotation->status === 'draft') {
                $quotation->update(['status' => 'waiting_approval']);
                $message = 'Diajukan untuk approval.';
            } elseif ($action === 'approve' && $quotation->status === 'waiting_approval') {
                $quotation->update(['status' => 'approved', 'approved_by' => auth()->id()]);
                $message = 'Approved.';
            } elseif ($action === 'reject' && $quotation->status === 'waiting_approval') {
                $quotation->update(['status' => 'rejected']);
                $message = 'Rejected.';
            } elseif ($action === 'mark_sent' && in_array($quotation->status, ['approved', 'sent', 'won'], true)) {
                if ($quotation->status === 'approved') {
                    $quotation->update(['status' => 'sent', 'sent_to_client_at' => now(), 'sent_to_client_by' => auth()->id()]);
                }
                
                $phone = $quotation->customer?->phone;
                if (!empty($phone)) {
                    $compName = app(\App\Services\MmsContext::class)->company()->company_name ?? 'MMS Promindo';
                    $quoteNum = $quotation->quote_number;
                    $quoteDate = optional($quotation->quote_date)->format('d/m/Y') ?: '-';
                    
                    $msg = "Halo *{$quotation->customer->name}*,\n\n";
                    $msg .= "Berikut kami kirimkan *Quotation (Penawaran Harga)* dari {$compName}:\n";
                    $msg .= "- No. Quotation: {$quoteNum}\n";
                    $msg .= "- Tanggal: {$quoteDate}\n";
                    $msg .= "- Total: Rp " . number_format((float)$quotation->grand_total, 0, ',', '.') . "\n\n";
                    $msg .= "Anda dapat melihat dokumen cetak penawaran dengan membuka link berikut:\n";
                    $msg .= route('sales.quotations.print.public', $quotation) . "\n\n";
                    $msg .= "Terima kasih atas perhatian dan kerja samanya.";

                    $waService = app(\App\Services\WhatsappService::class);
                    list($waSuccess, $waError) = $waService->sendMessage($phone, $msg);

                    if ($waSuccess) {
                        $message = 'Quotation berhasil dikirim ke customer via WhatsApp menggunakan Fonnte.';
                    } else {
                        $message = 'Quotation berhasil ditandai terkirim, tetapi WhatsApp gagal dikirim: ' . $waError;
                    }
                } else {
                    $message = 'Quotation ditandai terkirim, tetapi nomor HP customer kosong sehingga WhatsApp tidak dikirim.';
                }
            } elseif ($action === 'won' && $quotation->status === 'sent') {
                $quotation->update(['status' => 'won']);
                $message = 'Selamat! Quotation WON.';
            } elseif ($action === 'lost' && $quotation->status === 'sent') {
                $quotation->update(['status' => 'lost']);
                $message = 'Status: Lost.';
            } elseif ($action === 'revise') {
                $new = $this->makeRevision($quotation);
                $message = "Revisi berhasil dibuat ({$new->quote_number}).";
            }
        });

        return redirect()->route('sales.quotations.index')->with('success', $message);
    }

    public function print(Quotation $quotation): View
    {
        return view('sales.quotations.print', [
            'quotation' => $quotation->load(['customer', 'items', 'creator', 'approver']),
            'company' => app(\App\Services\MmsContext::class)->company(),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'quote_date' => ['required', 'date'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'ppn_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'tax_mode' => ['required', 'in:include,exclude'],
            'discount_type' => ['required', 'in:fixed,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, subtotal: float, discount: float, tax: float, grand: float}
     */
    private function computeItems(Request $request): array
    {
        $rows = [];
        $total = 0.0;
        $codes = $request->input('item_code', []);
        $names = $request->input('item_name', []);
        $materials = $request->input('material', []);
        $ownerships = $request->input('ownership', []);
        $units = $request->input('unit', []);
        $qtys = $request->input('qty', []);
        $prices = $request->input('price', []);
        $usedCodes = [];

        foreach ($codes as $i => $rawCode) {
            $name = trim((string) ($names[$i] ?? ''));
            $qty = (float) ($qtys[$i] ?? 0);
            $price = (float) ($prices[$i] ?? 0);
            if ($name === '' || $qty <= 0 || $price < 0) {
                continue;
            }

            $code = preg_replace('/[^A-Za-z0-9\-]/', '', strtoupper(trim((string) $rawCode)));
            if ($code === '' || isset($usedCodes[$code])) {
                $code = 'ITEM-' . str_pad((string) (count($rows) + 1), 4, '0', STR_PAD_LEFT);
            }
            $usedCodes[$code] = true;
            $line = $qty * $price;
            $total += $line;
            $unit = trim((string) ($units[$i] ?? ''));
            $rows[] = [
                'item_code_manual' => $code,
                'item_name_manual' => $name,
                'temp_item_name' => $name,
                'material_manual' => trim((string) ($materials[$i] ?? '')),
                'ownership' => ((string) ($ownerships[$i] ?? 'internal')) === 'customer' ? 'customer' : 'internal',
                'unit_manual' => $unit,
                'temp_uom' => $unit,
                'qty' => $qty,
                'unit_price' => $price,
                'subtotal' => $line,
            ];
        }

        $discType = $request->input('discount_type', 'fixed');
        $discVal = (float) $request->input('discount_value', 0);
        if ($discType === 'percent') {
            $discount = $total * ($discVal / 100);
        } else {
            $discount = $discVal;
        }
        $discount = min(max($discount, 0), $total);
        $subtotal = max(0, $total - $discount);
        $tax = $request->input('tax_mode') === 'include' ? $subtotal * ((float) $request->input('ppn_percent', 11) / 100) : 0.0;

        return [
            'rows' => $rows,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'grand' => $subtotal + $tax,
        ];
    }

    private function storeAttachment(Request $request): ?string
    {
        if (! $request->hasFile('attachment')) {
            return null;
        }

        return $request->file('attachment')->store('uploads/quotations', 'public_root');
    }

    private function nextQuoteNumber(): string
    {
        $ym = now()->format('ym');
        $count = Quotation::query()->where('quote_number', 'like', "QT-{$ym}-%")->count() + 1;

        return 'QT-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function makeRevision(Quotation $quotation): Quotation
    {
        $quotation->load('items');
        $version = ((int) $quotation->revision_version) + 1;
        $baseNumber = preg_replace('/-R\d+$/', '', $quotation->quote_number);
        $new = $quotation->replicate(['quote_number', 'status', 'approved_by', 'sent_to_client_at', 'sent_to_client_by']);
        $new->quote_number = $baseNumber . '-R' . $version;
        $new->revision_version = $version;
        $new->revision_of = $quotation->revision_of ?: $quotation->id;
        $new->quote_date = now()->toDateString();
        $new->status = 'draft';
        $new->notes = "Revisi dari {$quotation->quote_number}\n" . (string) $quotation->notes;
        $new->created_by = auth()->id();
        $new->save();

        foreach ($quotation->items as $item) {
            $new->items()->create($item->replicate(['quotation_id'])->toArray());
        }
        $quotation->update(['status' => 'revised']);

        return $new;
    }
}
