<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\DeliveryNote;
use App\Models\SalesOrder;
use App\Services\MmsContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DeliveryNoteController extends Controller
{
    public function index(Request $request): View
    {
        $status = $this->rememberedFilter($request, 'status', '');
        $search = $this->rememberedFilter($request, 'search', '');

        $notes = DeliveryNote::query()
            ->with('salesOrder.customer')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('dn_number', 'like', $term)
                        ->orWhereHas('salesOrder', fn ($so) => $so->where('so_number', 'like', $term))
                        ->orWhereHas('salesOrder.customer', fn ($customer) => $customer->where('name', 'like', $term));
                });
            })
            ->latest('id')
            ->get();

        return view('warehouse.delivery-notes.index', compact('notes', 'status', 'search'));
    }

    public function create(): View
    {
        return view('warehouse.delivery-notes.form', [
            'note' => new DeliveryNote([
                'dn_number' => 'AUTO',
                'dn_date' => now()->toDateString(),
                'status' => 'draft',
            ]),
            'salesOrders' => $this->availableSalesOrders(),
            'itemRows' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $rows = $this->validatedRows($data);
        $this->ensureSalesOrderReady((int) $data['sales_order_id']);

        DB::transaction(function () use ($data, $rows): void {
            $note = DeliveryNote::query()->create([
                'dn_number' => $this->nextDnNumber(),
                'sales_order_id' => $data['sales_order_id'],
                'dn_date' => $data['dn_date'],
                'driver_name' => $data['driver_name'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $note->items()->createMany($rows);
        });

        return redirect()->route('warehouse.delivery_notes.index')->with('success', 'Surat Jalan disimpan sebagai draft. Approve untuk memotong stok.');
    }

    public function edit(DeliveryNote $deliveryNote): View
    {
        if ($deliveryNote->status !== 'draft') {
            abort(403, 'Hanya Surat Jalan draft yang bisa diedit.');
        }

        return view('warehouse.delivery-notes.form', [
            'note' => $deliveryNote->load(['items.item', 'salesOrder']),
            'salesOrders' => $this->availableSalesOrders($deliveryNote->sales_order_id),
            'itemRows' => $this->selectedItemRows($deliveryNote),
        ]);
    }

    public function update(Request $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        if ($deliveryNote->status !== 'draft') {
            return back()->withErrors('Hanya Surat Jalan draft yang bisa diedit.');
        }

        $data = $this->validatedData($request);
        $rows = $this->validatedRows($data);
        $this->ensureSalesOrderReady((int) $data['sales_order_id']);

        DB::transaction(function () use ($deliveryNote, $data, $rows): void {
            $deliveryNote->update([
                'sales_order_id' => $data['sales_order_id'],
                'dn_date' => $data['dn_date'],
                'driver_name' => $data['driver_name'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $deliveryNote->items()->delete();
            $deliveryNote->items()->createMany($rows);
        });

        return redirect()->route('warehouse.delivery_notes.index')->with('success', 'Surat Jalan berhasil diperbarui.');
    }

    public function approve(DeliveryNote $deliveryNote): RedirectResponse
    {
        if ($deliveryNote->status !== 'draft') {
            return back()->withErrors('Gagal approve. Status mungkin sudah berubah.');
        }

        DB::transaction(function () use ($deliveryNote): void {
            $deliveryNote->load(['items', 'salesOrder']);
            $this->ensureSalesOrderReady((int) $deliveryNote->sales_order_id);

            $deliveryNote->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'created_at' => now(),
            ]);

            foreach ($deliveryNote->items as $item) {
                DB::table('items')->where('id', $item->item_id)->decrement('current_stock', (float) $item->qty_sent);
            }

            $paid = DB::table('invoices')
                ->where('delivery_note_id', $deliveryNote->id)
                ->where('status', 'paid')
                ->exists();
            $deliveryNote->salesOrder?->update(['status' => $paid ? 'completed' : 'delivered']);
        });

        return redirect()->route('warehouse.delivery_notes.index')->with('success', 'Surat Jalan disetujui. Stok barang jadi telah dipotong.');
    }

    public function destroy(DeliveryNote $deliveryNote): RedirectResponse
    {
        if ($deliveryNote->status !== 'draft') {
            return back()->withErrors('Hanya Surat Jalan draft yang bisa dihapus.');
        }

        DB::transaction(function () use ($deliveryNote): void {
            $deliveryNote->items()->delete();
            $deliveryNote->delete();
        });

        return redirect()->route('warehouse.delivery_notes.index')->with('success', 'Surat Jalan draft berhasil dihapus.');
    }

    public function print(DeliveryNote $deliveryNote): View
    {
        return view('warehouse.delivery-notes.print', [
            'note' => $deliveryNote->load(['salesOrder.customer', 'items.item', 'creator', 'approver']),
            'spkSummary' => $this->spkSummary((int) $deliveryNote->sales_order_id),
            'company' => app(MmsContext::class)->company(),
        ]);
    }

    public function soItems(SalesOrder $order): JsonResponse
    {
        return response()->json($this->salesOrderItemRows($order->id)->values());
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'sales_order_id' => ['required', 'integer', 'exists:sales_orders,id'],
            'dn_date' => ['required', 'date'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'vehicle_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'item_id' => ['required', 'array'],
            'item_id.*' => ['required', 'integer', 'exists:items,id'],
            'qty_sent' => ['required', 'array'],
            'qty_sent.*' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function validatedRows(array $data): array
    {
        $rows = [];
        foreach ($data['item_id'] as $idx => $itemId) {
            $qty = (float) ($data['qty_sent'][$idx] ?? 0);
            if ($qty > 0) {
                $rows[] = ['item_id' => (int) $itemId, 'qty_sent' => $qty];
            }
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['item_id' => 'Minimal 1 item harus dikirim.']);
        }

        return $rows;
    }

    private function ensureSalesOrderReady(int $salesOrderId): void
    {
        $order = SalesOrder::query()->find($salesOrderId);
        $closed = DB::table('spk')->where('sales_order_id', $salesOrderId)->where('status', 'closed')->exists();
        if (! $order || ! $closed) {
            throw ValidationException::withMessages([
                'sales_order_id' => 'Sales Order belum selesai QC/SPK (status SPK harus CLOSED).',
            ]);
        }
    }

    private function availableSalesOrders(?int $selectedId = null)
    {
        return SalesOrder::query()
            ->with('customer')
            ->where(function ($query) use ($selectedId): void {
                $query->whereIn('status', ['confirmed', 'in_production', 'delivered', 'completed'])
                    ->whereExists(function ($sub): void {
                        $sub->selectRaw('1')
                            ->from('spk')
                            ->whereColumn('spk.sales_order_id', 'sales_orders.id')
                            ->where('spk.status', 'closed');
                    });
                if ($selectedId) {
                    $query->orWhere('id', $selectedId);
                }
            })
            ->latest('id')
            ->get();
    }

    private function selectedItemRows(DeliveryNote $note)
    {
        return $note->items->map(fn ($row) => [
            'item_id' => $row->item_id,
            'item_code' => $row->item?->item_code,
            'item_name' => $row->item?->item_name,
            'unit' => $row->item?->unit,
            'current_stock' => (float) ($row->item?->current_stock ?? 0),
            'qty' => null,
            'qty_sent' => (float) $row->qty_sent,
        ]);
    }

    private function salesOrderItemRows(int $salesOrderId)
    {
        $sent = DB::table('delivery_notes as dn')
            ->join('delivery_note_items as dni', 'dni.delivery_note_id', '=', 'dn.id')
            ->where('dn.sales_order_id', $salesOrderId)
            ->whereIn('dn.status', ['approved', 'sent'])
            ->groupBy('dni.item_id')
            ->select('dni.item_id', DB::raw('SUM(dni.qty_sent) as sent_qty'));

        return DB::table('sales_order_items as soi')
            ->join('items as i', 'i.id', '=', 'soi.item_id')
            ->leftJoinSub($sent, 'sent', fn ($join) => $join->on('sent.item_id', '=', 'soi.item_id'))
            ->where('soi.sales_order_id', $salesOrderId)
            ->select([
                'soi.item_id',
                'i.item_code',
                'i.item_name',
                'i.unit',
                'i.current_stock',
                DB::raw('GREATEST((soi.qty - COALESCE(sent.sent_qty, 0)), 0) as qty'),
            ])
            ->having('qty', '>', 0)
            ->get()
            ->map(fn ($row) => [
                'item_id' => (int) $row->item_id,
                'item_code' => $row->item_code,
                'item_name' => $row->item_name,
                'unit' => $row->unit,
                'current_stock' => (float) $row->current_stock,
                'qty' => (float) $row->qty,
                'qty_sent' => min((float) $row->current_stock, (float) $row->qty),
            ]);
    }

    private function spkSummary(int $salesOrderId): array
    {
        $rows = DB::table('spk')
            ->where('sales_order_id', $salesOrderId)
            ->orderBy('id')
            ->get(['spk_number', 'project_name']);

        return [
            'numbers' => $rows->pluck('spk_number')->filter()->implode(', ') ?: '-',
            'projects' => $rows->pluck('project_name')->filter()->implode(', ') ?: '-',
        ];
    }

    private function nextDnNumber(): string
    {
        $ym = now()->format('ym');
        $count = DeliveryNote::query()->where('dn_number', 'like', "DN-{$ym}-%")->count() + 1;

        return 'DN-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    public function signForm(DeliveryNote $deliveryNote): View
    {
        if (! in_array($deliveryNote->status, ['approved', 'sent'], true)) {
            abort(403, 'Hanya Surat Jalan yang sudah Approved yang bisa ditandatangani.');
        }

        return view('warehouse.delivery-notes.sign', compact('deliveryNote'));
    }

    public function storeSign(Request $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        if (! in_array($deliveryNote->status, ['approved', 'sent'], true)) {
            return back()->withErrors('Hanya Surat Jalan yang sudah Approved yang bisa ditandatangani.');
        }

        $request->validate([
            'received_by_name' => ['required', 'string', 'max:100'],
            'signature_base64' => ['required', 'string'],
        ]);

        $dataUrl = (string) $request->input('signature_base64');
        if (! preg_match('/^data:image\/(png|jpe?g);base64,([A-Za-z0-9+\/=\r\n]+)$/i', $dataUrl, $matches)) {
            return back()->withErrors('Data gambar tanda tangan tidak valid.');
        }

        $extension = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);
        $binary = base64_decode(str_replace(["\r", "\n"], '', $matches[2]), true);
        if ($binary === false) {
            return back()->withErrors('Gagal membaca data gambar tanda tangan.');
        }

        $directory = storage_path('app/user-media/signature');
        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $filename = 'cust_sig_' . $deliveryNote->id . '_' . now()->format('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        if (@file_put_contents($directory . DIRECTORY_SEPARATOR . $filename, $binary) === false) {
            return back()->withErrors('Gagal menyimpan file tanda tangan di server.');
        }

        $deliveryNote->update([
            'customer_signature_path' => 'user-media/signature/' . $filename,
            'received_by_name' => $request->input('received_by_name'),
            'received_at' => now(),
            'status' => 'sent',
        ]);

        return redirect()->route('warehouse.delivery_notes.index')->with('success', 'Tanda tangan customer berhasil disimpan. Surat Jalan telah ditandatangani.');
    }
}
