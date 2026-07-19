<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Item;
use App\Models\MmsNotification;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesOrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = $this->rememberedFilter($request, 'status', '');
        $search = $this->rememberedFilter($request, 'search', '');
        $salesOrders = SalesOrder::query()
            ->with('customer')
            ->withCount('items')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('so_number', 'like', $term)
                        ->orWhere('cust_po_number', 'like', $term)
                        ->orWhereHas('customer', fn ($cust) => $cust->where('name', 'like', $term));
                });
            })
            ->latest('id')
            ->get();

        return view('sales.orders.index', compact('salesOrders', 'status', 'search'));
    }

    public function create(Request $request): View
    {
        $quote = $request->query('quote_id') ? Quotation::query()->with('items')->find($request->query('quote_id')) : null;
        $items = collect([new SalesOrderItem(['qty' => 1, 'unit_manual' => 'PCS'])]);
        $order = new SalesOrder([
            'so_number' => 'AUTO',
            'quotation_id' => $quote?->id,
            'customer_id' => $quote?->customer_id,
            'so_date' => now()->toDateString(),
            'delivery_date' => now()->addDays(3)->toDateString(),
            'payment_terms' => $quote?->payment_terms ?: 'Net 30 Days',
            'fulfillment_source' => 'spk',
            'ppn_percent' => $quote?->ppn_percent ?: 11,
            'tax_included' => (bool) ($quote?->tax_included),
            'discount_amount' => $quote?->discount_amount ?: 0,
            'status' => 'draft',
        ]);

        if ($quote) {
            $items = $quote->items->map(fn ($item) => new SalesOrderItem([
                'item_id' => $item->item_id ?: 0,
                'item_code_manual' => $item->item_code_manual,
                'item_name_manual' => $item->item_name_manual ?: $item->temp_item_name,
                'material_manual' => $item->material_manual ?: $item->temp_spec,
                'unit_manual' => $item->unit_manual ?: $item->temp_uom,
                'qty' => $item->qty,
                'unit_price' => $item->unit_price,
            ]));
        }

        return $this->formView($order, $items, false);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validated($request);
        $computed = $this->computeItems($request, (int) $payload['customer_id']);
        if ($computed['rows'] === []) {
            return back()->withInput()->withErrors('Minimal 1 baris item SO harus diisi.');
        }

        DB::transaction(function () use ($payload, $computed): void {
            $taxIncluded = $payload['tax_mode'] === 'include';
            unset($payload['tax_mode'], $payload['discount_amount']);
            $order = SalesOrder::query()->create($payload + [
                'so_number' => $this->nextSoNumber(),
                'tax_included' => $taxIncluded,
                'subtotal' => $computed['subtotal'],
                'discount_amount' => $computed['discount'],
                'tax_amount' => $computed['tax'],
                'grand_total' => $computed['grand'],
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);
            $order->items()->createMany($computed['rows']);
            if ($order->quotation_id) {
                Quotation::query()->whereKey($order->quotation_id)->whereIn('status', ['won', 'so_created'])->update(['status' => 'so_created']);
            }
        });

        return redirect()->route('sales.orders.index')->with('success', 'Sales Order Berhasil Disimpan!');
    }

    public function edit(SalesOrder $order): View|RedirectResponse
    {
        if (! in_array($order->status, ['draft', 'rejected'], true)) {
            return redirect()->route('sales.orders.index')->withErrors('Sales Order tidak bisa diubah pada status saat ini.');
        }

        return $this->formView($order->load('items'), $order->items, true);
    }

    public function update(Request $request, SalesOrder $order): RedirectResponse
    {
        if (! in_array($order->status, ['draft', 'rejected'], true)) {
            return redirect()->route('sales.orders.index')->withErrors('Sales Order tidak bisa diubah pada status saat ini.');
        }

        $payload = $this->validated($request);
        $computed = $this->computeItems($request, (int) $payload['customer_id']);
        if ($computed['rows'] === []) {
            return back()->withInput()->withErrors('Minimal 1 baris item SO harus diisi.');
        }

        DB::transaction(function () use ($order, $payload, $computed): void {
            $taxIncluded = $payload['tax_mode'] === 'include';
            unset($payload['tax_mode'], $payload['discount_amount']);
            $order->update($payload + [
                'tax_included' => $taxIncluded,
                'subtotal' => $computed['subtotal'],
                'discount_amount' => $computed['discount'],
                'tax_amount' => $computed['tax'],
                'grand_total' => $computed['grand'],
            ]);
            $order->items()->delete();
            $order->items()->createMany($computed['rows']);
        });

        return redirect()->route('sales.orders.index')->with('success', 'Sales Order Berhasil Disimpan!');
    }

    public function destroy(SalesOrder $order): RedirectResponse
    {
        if (! in_array($order->status, ['draft', 'cancelled'], true)) {
            return back()->withErrors('Hanya SO berstatus Draft atau Cancelled yang boleh dihapus.');
        }
        $order->items()->delete();
        $order->delete();

        return redirect()->route('sales.orders.index')->with('success', 'Sales Order berhasil dihapus.');
    }

    public function workflow(Request $request, SalesOrder $order, string $action): RedirectResponse
    {
        $permission = in_array($action, ['approve', 'reject'], true) ? 'sales_so_approve' : 'sales_so_manage';
        if (! $request->user()?->hasPermission($permission)) {
            abort(403);
        }

        $message = 'Aksi tidak valid atau status sudah berubah.';
        if ($action === 'submit' && $order->status === 'draft') {
            $order->update(['status' => 'waiting_approval']);
            $message = 'Sales Order diajukan untuk Approval.';
        } elseif ($action === 'approve' && $order->status === 'waiting_approval') {
            $order->update(['status' => 'confirmed', 'approved_by' => auth()->id(), 'approved_at' => now()]);

            // Notify Engineering team
            MmsNotification::query()->create([
                'sender_id' => auth()->id(),
                'target_role' => 'engineering',
                'title' => 'Sales Order Approved: ' . $order->so_number,
                'message' => "Sales Order {$order->so_number} telah di-approve. Silakan periksa item dan siapkan BOM.",
                'link' => route('engineering.boms.index'),
                'type' => 'sales_order',
            ]);

            MmsNotification::query()->create([
                'sender_id' => auth()->id(),
                'target_role' => 'engineer',
                'title' => 'Sales Order Approved: ' . $order->so_number,
                'message' => "Sales Order {$order->so_number} telah di-approve. Silakan periksa item dan siapkan BOM.",
                'link' => route('engineering.boms.index'),
                'type' => 'sales_order',
            ]);

            $message = 'Sales Order berhasil di-Approve (Confirmed)! Notifikasi telah dikirim ke Engineering.';
        } elseif ($action === 'reject' && $order->status === 'waiting_approval') {
            $order->update(['status' => 'rejected']);
            $message = 'Sales Order ditolak (Rejected).';
        } elseif ($action === 'cancel' && in_array($order->status, ['draft', 'waiting_approval', 'confirmed'], true)) {
            $order->update(['status' => 'cancelled']);
            $message = 'Sales Order dibatalkan.';
        } elseif ($action === 'mark_sent' && in_array($order->status, ['confirmed', 'in_production', 'delivered', 'completed'], true)) {
            $order->update(['sent_to_client_at' => now(), 'sent_to_client_by' => auth()->id()]);
            
            $phone = $order->customer?->phone;
            if (!empty($phone)) {
                $compName = app(\App\Services\MmsContext::class)->company()->company_name ?? 'MMS Promindo';
                $soNum = $order->so_number;
                $soDate = optional($order->so_date)->format('d/m/Y') ?: '-';
                $custPo = $order->cust_po_number ?: '-';
                
                $msg = "Halo *{$order->customer->name}*,\n\n";
                $msg .= "Berikut kami kirimkan *Sales Order (SO)* dari {$compName}:\n";
                $msg .= "- No. SO: {$soNum}\n";
                $msg .= "- Tanggal SO: {$soDate}\n";
                $msg .= "- PO Customer: {$custPo}\n";
                $msg .= "- Total: Rp " . number_format((float)$order->grand_total, 0, ',', '.') . "\n\n";
                $msg .= "Anda dapat melihat dokumen cetak Sales Order dengan membuka link berikut:\n";
                $msg .= route('sales.orders.print.public', $order) . "\n\n";
                $msg .= "Terima kasih atas kerja samanya.";

                $waService = app(\App\Services\WhatsappService::class);
                list($waSuccess, $waError) = $waService->sendMessage($phone, $msg);

                if ($waSuccess) {
                    $message = 'Sales Order berhasil ditandai Terkirim dan WhatsApp terkirim ke customer.';
                } else {
                    $message = 'Sales Order ditandai Terkirim, tetapi WhatsApp gagal dikirim: ' . $waError;
                }
            } else {
                $message = 'Sales Order ditandai Terkirim, tetapi nomor HP customer kosong sehingga WhatsApp tidak dikirim.';
            }
        }

        return redirect()->route('sales.orders.index')->with('success', $message);
    }

    public function print(SalesOrder $order): View
    {
        return view('sales.orders.print', [
            'order' => $order->load(['customer', 'items.item', 'creator', 'approver']),
            'company' => app(\App\Services\MmsContext::class)->company(),
        ]);
    }

    private function formView(SalesOrder $order, $items, bool $isEdit): View
    {
        return view('sales.orders.form', [
            'order' => $order,
            'items' => $items,
            'customers' => Customer::query()->orderBy('name')->get(),
            'isEdit' => $isEdit,
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'quotation_id' => ['nullable', Rule::exists('quotations', 'id')],
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'so_date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'cust_po_number' => ['nullable', 'string', 'max:50'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'fulfillment_source' => ['required', 'in:spk,fg_stock'],
            'ppn_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'tax_mode' => ['required', 'in:include,exclude'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function computeItems(Request $request, int $customerId): array
    {
        $rows = [];
        $bruto = 0.0;
        $gross = 0.0;
        $factor = 1 + ((float) $request->input('ppn_percent', 11) / 100);
        $taxIncluded = $request->input('tax_mode') === 'include';

        foreach ($request->input('item_name', []) as $i => $rawName) {
            $name = trim((string) $rawName);
            $qty = (float) ($request->input("qty.{$i}") ?? 0);
            $priceInput = (float) ($request->input("price.{$i}") ?? 0);
            if ($name === '' || $qty <= 0 || $priceInput < 0) {
                continue;
            }

            $code = preg_replace('/[^A-Za-z0-9\-]/', '', strtoupper(trim((string) ($request->input("item_code.{$i}") ?? ''))));
            $unit = trim((string) ($request->input("unit.{$i}") ?? 'PCS')) ?: 'PCS';
            $material = trim((string) ($request->input("material.{$i}") ?? ''));
            $itemId = (int) ($request->input("item_id.{$i}") ?? 0);
            $price = $taxIncluded ? $priceInput / $factor : $priceInput;
            $subtotal = $qty * $price;
            $bruto += $subtotal;
            $gross += $qty * $priceInput;
            $itemId = $this->resolveItem($itemId, $customerId, $code, $name, $unit, $price, $material);

            $rows[] = [
                'item_id' => $itemId,
                'item_code_manual' => $code,
                'item_name_manual' => $name,
                'material_manual' => $material,
                'unit_manual' => $unit,
                'qty' => $qty,
                'unit_price' => $price,
                'subtotal' => $subtotal,
            ];
        }

        $discountCap = $taxIncluded ? $gross : $bruto;
        $discount = min(max((float) $request->input('discount_amount', 0), 0), $discountCap);
        if ($taxIncluded) {
            $grossAfterDiscount = max(0, $gross - $discount);
            $subtotal = $grossAfterDiscount / $factor;
            $tax = $grossAfterDiscount - $subtotal;
            $grand = $grossAfterDiscount;
        } else {
            $subtotal = max(0, $bruto - $discount);
            $tax = 0.0;
            $grand = $subtotal;
        }

        return compact('rows', 'subtotal', 'discount', 'tax', 'grand');
    }

    private function resolveItem(int $itemId, int $customerId, string &$code, string $name, string $unit, float $price, string $material): int
    {
        if ($itemId > 0) {
            return $itemId;
        }
        $existing = $code !== '' ? Item::query()->where('item_code', $code)->value('id') : null;
        $existing ??= Item::query()->where('customer_id', $customerId)->where('item_name', $name)->where('item_type', 'finish_good')->latest('id')->value('id');
        if ($existing) {
            return (int) $existing;
        }
        if ($code === '') {
            $code = 'FG-' . str_pad((string) (Item::query()->count() + 1), 5, '0', STR_PAD_LEFT);
        }

        return (int) Item::query()->create([
            'customer_id' => $customerId,
            'item_code' => $code,
            'item_name' => $name,
            'item_type' => 'finish_good',
            'ownership' => 'customer',
            'qc_type' => 'general',
            'unit' => $unit,
            'base_price' => $price,
            'current_stock' => 0,
            'min_stock' => 0,
            'description' => $material ?: 'Auto-created from Sales Order',
        ])->id;
    }

    private function nextSoNumber(): string
    {
        $ym = now()->format('ym');
        $maxSeq = 0;
        $existing = SalesOrder::query()
            ->where('so_number', 'like', "SO-{$ym}-%")
            ->pluck('so_number');

        foreach ($existing as $num) {
            if (preg_match('/^SO-\d{4}-(\d+)/', (string) $num, $matches)) {
                $seq = (int) $matches[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $next = $maxSeq + 1;
        while (SalesOrder::query()->where('so_number', 'SO-' . $ym . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT))->exists()) {
            $next++;
        }

        return 'SO-' . $ym . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
