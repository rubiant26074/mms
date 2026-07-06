<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $customers = Customer::query()
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('customer_code', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('pic', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->latest('id')
            ->get();

        return view('sales.customers.index', compact('customers', 'search'));
    }

    public function create(): View
    {
        return view('sales.customers.form', [
            'customer' => new Customer(['customer_code' => $this->nextCustomerCode()]),
            'isEdit' => false,
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('sales.customers.form', compact('customer') + ['isEdit' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['customer_code'] = $data['customer_code'] ?: $this->nextCustomerCode();
        $data['code'] = $data['customer_code'];
        $data['created_by'] = auth()->id();
        Customer::query()->create($data);

        return redirect()->route('sales.customers.index')->with('success', 'Data Customer berhasil disimpan!');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $this->validated($request, $customer);
        $data['code'] = $data['customer_code'] ?: $customer->code;
        $customer->update($data);

        return redirect()->route('sales.customers.index')->with('success', 'Data Customer berhasil disimpan!');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        try {
            $name = $customer->name;
            $customer->delete();

            return redirect()->route('sales.customers.index')->with('success', "Customer {$name} berhasil dihapus.");
        } catch (\Throwable) {
            return back()->withErrors('Gagal menghapus. Data ini sedang digunakan oleh modul lain.');
        }
    }

    public function saveAjax(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:customers,name'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'pic' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'tax_invoice_number' => ['nullable', 'regex:/^\d{3}\.\d{3}-\d{2}\.\d{8}$/'],
        ]);

        $code = $this->nextCustomerCode();
        $customer = Customer::query()->create($data + [
            'customer_code' => $code,
            'code' => $code,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => ['id' => $customer->id, 'name' => $customer->name, 'code' => $customer->customer_code],
        ]);
    }

    public function importAjax(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'rows' => ['required', 'array'],
            'mode' => ['nullable', 'in:skip,update'],
        ]);

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $mode = $payload['mode'] ?? 'skip';

        foreach ($payload['rows'] as $idx => $row) {
            $rowNo = $idx + 1;
            $name = trim((string) ($row['name'] ?? $row['customer_name'] ?? ''));
            if ($name === '') {
                $errors[] = "Baris {$rowNo}: name wajib diisi.";
                $skipped++;
                continue;
            }

            $nsfp = preg_replace('/\s+/', '', trim((string) ($row['tax_invoice_number'] ?? $row['nsfp'] ?? '')));
            if ($nsfp !== '' && ! preg_match('/^\d{3}\.\d{3}-\d{2}\.\d{8}$/', $nsfp)) {
                $errors[] = "Baris {$rowNo}: format NSFP tidak valid.";
                $skipped++;
                continue;
            }

            $code = trim((string) ($row['customer_code'] ?? '')) ?: $this->nextCustomerCode();
            $existing = Customer::query()
                ->where('customer_code', $code)
                ->orWhere('name', $name)
                ->first();

            $data = [
                'customer_code' => $code,
                'code' => $code,
                'name' => $name,
                'address' => trim((string) ($row['address'] ?? '')),
                'phone' => trim((string) ($row['phone'] ?? '')),
                'pic' => trim((string) ($row['pic'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')),
                'tax_id' => trim((string) ($row['tax_id'] ?? '')),
                'tax_invoice_number' => $nsfp !== '' ? $nsfp : null,
            ];

            if ($existing) {
                if ($mode === 'update') {
                    $existing->update($data);
                    $updated++;
                } else {
                    $skipped++;
                }
                continue;
            }

            Customer::query()->create($data + ['created_by' => auth()->id()]);
            $inserted++;
        }

        return response()->json(compact('inserted', 'updated', 'skipped', 'errors') + ['status' => 'success']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'customer_code' => ['nullable', 'string', 'max:20', Rule::unique('customers', 'customer_code')->ignore($customer)],
            'name' => ['required', 'string', 'max:100', Rule::unique('customers', 'name')->ignore($customer)],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'pic' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'tax_invoice_number' => ['nullable', 'regex:/^\d{3}\.\d{3}-\d{2}\.\d{8}$/'],
        ]);
    }

    private function nextCustomerCode(): string
    {
        $last = Customer::query()
            ->where('customer_code', 'like', 'CT-%')
            ->latest('id')
            ->value('customer_code');
        $next = $last ? ((int) substr((string) $last, 3)) + 1 : 1;

        return 'CT-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
