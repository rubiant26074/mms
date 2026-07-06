<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $suppliers = Supplier::query()
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('code', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('contact_person', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('bank_name', 'like', $term)
                        ->orWhere('bank_number', 'like', $term);
                });
            })
            ->orderBy('name')
            ->get();

        return view('procurement.suppliers.index', compact('suppliers', 'search'));
    }

    public function create(): View
    {
        return view('procurement.suppliers.form', [
            'supplier' => new Supplier(['code' => $this->nextCode()]),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Supplier::query()->create($this->validated($request));

        return redirect()->route('procurement.suppliers.index')->with('success', 'Data supplier berhasil disimpan.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('procurement.suppliers.form', compact('supplier') + ['isEdit' => true]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier));

        return redirect()->route('procurement.suppliers.index')->with('success', 'Data supplier berhasil disimpan.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        try {
            $supplier->delete();

            return redirect()->route('procurement.suppliers.index')->with('success', 'Supplier berhasil dihapus.');
        } catch (\Throwable) {
            return back()->withErrors('Gagal menghapus. Supplier mungkin sudah digunakan dalam transaksi.');
        }
    }

    public function importAjax(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'rows' => ['required', 'array'],
            'mode' => ['nullable', 'in:skip,update'],
        ]);
        $mode = $payload['mode'] ?? 'skip';
        $inserted = $updated = $skipped = 0;
        $errors = [];

        foreach ($payload['rows'] as $idx => $row) {
            $rowNo = $idx + 1;
            $name = trim((string) ($row['name'] ?? $row['vendor_name'] ?? ''));
            if ($name === '') {
                $errors[] = "Baris {$rowNo}: name wajib diisi.";
                $skipped++;
                continue;
            }
            $code = trim((string) ($row['code'] ?? '')) ?: $this->nextCode();
            $data = [
                'code' => $code,
                'name' => $name,
                'address' => trim((string) ($row['address'] ?? '')),
                'phone' => trim((string) ($row['phone'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')),
                'contact_person' => trim((string) ($row['contact_person'] ?? $row['pic'] ?? '')),
                'bank_name' => trim((string) ($row['bank_name'] ?? '')),
                'bank_number' => trim((string) ($row['bank_number'] ?? '')),
            ];

            $existing = Supplier::query()->where('code', $code)->orWhere('name', $name)->first();
            if ($existing && $mode === 'update') {
                $existing->update($data);
                $updated++;
            } elseif ($existing) {
                $skipped++;
            } else {
                Supplier::query()->create($data);
                $inserted++;
            }
        }

        return response()->json(compact('inserted', 'updated', 'skipped', 'errors') + ['status' => 'success']);
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('suppliers', 'code')->ignore($supplier)],
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_number' => ['nullable', 'string', 'max:50'],
        ]);
    }

    private function nextCode(): string
    {
        $last = Supplier::query()->where('code', 'like', 'VD-%')->latest('id')->value('code');
        $next = $last ? ((int) substr((string) $last, 3)) + 1 : 1;

        return 'VD-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
