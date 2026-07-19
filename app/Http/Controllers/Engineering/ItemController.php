<?php

namespace App\Http\Controllers\Engineering;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $type = $this->rememberedFilter($request, 'filter_type', '');
        $search = $this->rememberedFilter($request, 'search', '');
        $canSeePrice = $request->user()?->hasPermission('item_price_view') ?? false;
        $isAdmin = $request->user()?->role?->role_slug === 'admin';

        $items = Item::query()
            ->when($type !== '', fn ($query) => $query->where('item_type', $type))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(fn ($sub) => $sub->where('item_code', 'like', $term)->orWhere('item_name', 'like', $term));
            })
            ->orderBy('item_code')
            ->get();

        return view('engineering.items.index', compact('items', 'type', 'search', 'canSeePrice', 'isAdmin'));
    }

    public function create(): View
    {
        return view('engineering.items.form', [
            'item' => new Item([
                'item_code' => '',
                'item_type' => 'finish_good',
                'ownership' => 'internal',
                'qc_type' => 'general',
                'unit' => 'Pcs',
                'base_price' => 0,
                'current_stock' => 0,
                'min_stock' => 0,
            ]),
            'customers' => Customer::query()->orderBy('name')->get(),
            'isEdit' => false,
            'canSeePrice' => auth()->user()?->hasPermission('item_price_view') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['drawing_file'] = $this->storeDrawing($request);
        if (! $request->user()?->hasPermission('item_price_view')) {
            $data['base_price'] = 0;
        }
        Item::query()->create($data);

        return redirect()->route('warehouse.items.index')->with('success', 'Data tersimpan!');
    }

    public function edit(Item $item): View
    {
        return view('engineering.items.form', [
            'item' => $item,
            'customers' => Customer::query()->orderBy('name')->get(),
            'isEdit' => true,
            'canSeePrice' => auth()->user()?->hasPermission('item_price_view') ?? false,
        ]);
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $data = $this->validated($request, $item);
        $data['drawing_file'] = $this->storeDrawing($request) ?: $item->drawing_file;
        if (! $request->user()?->hasPermission('item_price_view')) {
            unset($data['base_price']);
        }
        $item->update($data);

        return redirect()->route('warehouse.items.index')->with('success', 'Data tersimpan!');
    }

    public function destroy(Request $request, Item $item): RedirectResponse
    {
        if ($request->user()?->role?->role_slug !== 'admin') {
            return back()->withErrors('Hanya Admin yang memiliki akses untuk menghapus barang.');
        }

        try {
            $item->delete();

            return redirect()->route('warehouse.items.index')->with('success', 'Barang berhasil dihapus.');
        } catch (\Throwable) {
            return back()->withErrors('Gagal menghapus. Barang mungkin sudah digunakan dalam transaksi.');
        }
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        if ($request->user()?->role?->role_slug !== 'admin') {
            return back()->withErrors('Hanya Admin yang memiliki akses untuk menghapus barang.');
        }

        $ids = $request->input('ids', []);
        if (! is_array($ids) || empty($ids)) {
            return back()->withErrors('Pilih setidaknya satu barang untuk dihapus.');
        }

        $deletedCount = 0;
        $failedCount = 0;

        foreach ($ids as $id) {
            $item = Item::query()->find($id);
            if (! $item) {
                continue;
            }
            try {
                $item->delete();
                $deletedCount++;
            } catch (\Throwable) {
                $failedCount++;
            }
        }

        if ($deletedCount > 0 && $failedCount === 0) {
            return redirect()->route('warehouse.items.index')->with('success', "{$deletedCount} barang berhasil dihapus.");
        } elseif ($deletedCount > 0 && $failedCount > 0) {
            return redirect()->route('warehouse.items.index')->with('warning', "{$deletedCount} barang berhasil dihapus, tetapi {$failedCount} barang gagal dihapus karena sudah digunakan dalam transaksi.");
        } else {
            return back()->withErrors('Gagal menghapus barang terpilih. Barang mungkin sudah digunakan dalam transaksi.');
        }
    }

    public function generateCode(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => ['nullable', Rule::exists('customers', 'id')],
            'type' => ['nullable', 'in:internal,customer'],
            'item_type' => ['nullable', 'in:raw_material,wip,finish_good,consumable'],
        ]);

        try {
            $prefix = 'INT';
            $customerCode = '';
            if ($request->filled('customer_id')) {
                $customerCode = (string) Customer::query()->whereKey($request->integer('customer_id'))->value('customer_code');
            }

            if ($request->query('item_type') === 'consumable') {
                $prefix = 'CS';
            } elseif ($request->query('item_type') === 'raw_material') {
                $prefix = $request->query('type') === 'customer' ? 'RM-' . $this->requireCustomerCode($customerCode) : 'RM-INT';
            } elseif ($request->query('type') === 'customer') {
                $prefix = $this->requireCustomerCode($customerCode);
            }

            $last = Item::query()->where('item_code', 'like', $prefix . '-%')->latest('id')->value('item_code');
            $next = $last ? ((int) collect(explode('-', (string) $last))->last()) + 1 : 1;

            return response()->json(['status' => 'success', 'code' => $prefix . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT)]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function importAjax(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'rows' => ['required', 'array'],
            'mode' => ['nullable', 'in:skip,update'],
        ]);
        $mode = $payload['mode'] ?? 'skip';
        $canSeePrice = $request->user()?->hasPermission('item_price_view') ?? false;
        $inserted = $updated = $skipped = 0;
        $errors = [];

        $customersByCode = Customer::query()->whereNotNull('customer_code')->pluck('id', 'customer_code')->mapWithKeys(fn ($id, $code) => [strtolower((string) $code) => $id]);
        $customersByName = Customer::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [strtolower((string) $name) => $id]);

        foreach ($payload['rows'] as $idx => $row) {
            $rowNo = $idx + 1;
            $code = trim((string) ($row['item_code'] ?? ''));
            $name = trim((string) ($row['item_name'] ?? ''));
            if ($code === '' || $name === '') {
                $errors[] = "Baris {$rowNo}: item_code dan item_name wajib diisi.";
                $skipped++;
                continue;
            }

            $ownership = $this->mapValue($row['ownership'] ?? 'internal', ['internal' => 'internal', 'customer' => 'customer', 'consignment' => 'customer'], 'internal');
            $customerId = null;
            $custCode = strtolower(trim((string) ($row['customer_code'] ?? '')));
            $custName = strtolower(trim((string) ($row['customer_name'] ?? '')));
            if ($custCode !== '') {
                $customerId = $customersByCode[$custCode] ?? null;
            }
            if (! $customerId && $custName !== '') {
                $customerId = $customersByName[$custName] ?? null;
            }
            if ($ownership === 'customer' && ! $customerId) {
                $errors[] = "Baris {$rowNo}: ownership=customer tapi customer_code/name tidak ditemukan.";
                $skipped++;
                continue;
            }

            $data = [
                'customer_id' => $customerId,
                'item_code' => $code,
                'item_name' => $name,
                'item_type' => $this->mapValue($row['item_type'] ?? 'finish_good', ['finish_good' => 'finish_good', 'finish good' => 'finish_good', 'fg' => 'finish_good', 'raw_material' => 'raw_material', 'raw material' => 'raw_material', 'rm' => 'raw_material', 'wip' => 'wip', 'work in progress' => 'wip', 'consumable' => 'consumable'], 'finish_good'),
                'ownership' => $ownership,
                'qc_type' => $this->mapValue($row['qc_type'] ?? 'general', ['general' => 'general', 'sheet_metal' => 'sheet_metal', 'sheet metal' => 'sheet_metal', 'plate' => 'plate', 'coating' => 'coating', 'paint' => 'coating', 'machining' => 'machining', 'consumable' => 'consumable'], 'general'),
                'unit' => trim((string) ($row['unit'] ?? 'Pcs')) ?: 'Pcs',
                'current_stock' => $this->toNumber($row['current_stock'] ?? $row['qty'] ?? $row['stok'] ?? $row['stok_awal'] ?? 0),
                'base_price' => $canSeePrice ? $this->toNumber($row['base_price'] ?? 0) : 0,
                'min_stock' => $this->toNumber($row['min_stock'] ?? 0),
                'description' => trim((string) ($row['description'] ?? '')),
                'drawing_file' => trim((string) ($row['drawing_file'] ?? '')),
            ];

            $existing = Item::query()->where('item_code', $code)->first();
            if ($existing && $mode === 'update') {
                $existing->update($data);
                $updated++;
            } elseif ($existing) {
                $skipped++;
            } else {
                Item::query()->create($data);
                $inserted++;
            }
        }

        return response()->json(compact('inserted', 'updated', 'skipped', 'errors') + ['status' => 'success']);
    }

    private function validated(Request $request, ?Item $item = null): array
    {
        return $request->validate([
            'customer_id' => ['nullable', Rule::exists('customers', 'id')],
            'item_code' => ['required', 'string', 'max:50', Rule::unique('items', 'item_code')->ignore($item)],
            'item_name' => ['required', 'string', 'max:200'],
            'item_type' => ['required', 'in:raw_material,wip,finish_good,consumable'],
            'ownership' => ['required', 'in:internal,customer'],
            'qc_type' => ['nullable', 'in:general,sheet_metal,plate,coating,machining,consumable'],
            'unit' => ['required', 'string', 'max:20'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'current_stock' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'drawing_file' => ['nullable'],
        ]);
    }

    private function storeDrawing(Request $request): ?string
    {
        $base64 = trim((string) $request->input('drawing_base64', ''));
        if ($base64 !== '') {
            $parts = explode(',', $base64, 2);
            if (count($parts) === 2) {
                $decoded = base64_decode($parts[1], true);
                if ($decoded !== false && strlen($decoded) > 100) {
                    $origName = $request->input('drawing_filename', 'drawing.pdf');
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    if (! in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'dwg', 'dxf'])) {
                        $ext = 'pdf';
                    }
                    $filename = 'drw_' . uniqid() . '_' . now()->format('YmdHis') . '.' . $ext;
                    $directory = public_path('uploads/drawings');
                    if (! is_dir($directory)) {
                        @mkdir($directory, 0775, true);
                    }
                    if (file_put_contents($directory . '/' . $filename, $decoded) !== false) {
                        return 'uploads/drawings/' . $filename;
                    }
                }
            }
        }

        if (! $request->hasFile('drawing_file')) {
            return null;
        }

        return $request->file('drawing_file')->store('uploads/drawings', 'public_root');
    }

    private function requireCustomerCode(string $code): string
    {
        if ($code === '') {
            throw new \RuntimeException('Kode customer belum tersedia.');
        }

        return $code;
    }

    private function mapValue(mixed $value, array $map, string $default): string
    {
        $key = preg_replace('/\s+/', ' ', strtolower(trim((string) $value)));

        return $map[$key] ?? $default;
    }

    private function toNumber(mixed $value): float
    {
        $value = str_replace(['.', ','], ['', '.'], (string) $value);
        $value = preg_replace('/[^0-9.]/', '', $value);

        return $value === '' ? 0.0 : (float) $value;
    }
}
