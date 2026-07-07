<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\MmsContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request, MmsContext $context): View
    {
        $context->syncLegacySession(auth()->user()->loadMissing('role.permissions'));

        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));

        $employees = User::query()
            ->with('role')
            ->when($status !== '', fn ($query) => $query->where('employee_status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('nik', 'like', $term)
                        ->orWhere('fullname', 'like', $term)
                        ->orWhere('department', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhereHas('role', fn ($role) => $role->where('role_name', 'like', $term));
                });
            })
            ->orderBy('fullname')
            ->get();

        return view('hrd.employees.index', compact('employees', 'status', 'search'));
    }

    public function create(): View
    {
        return $this->form(new User([
            'join_date' => now()->toDateString(),
            'employee_status' => 'probation',
            'basic_salary' => 0,
        ]), false);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['password'] = Hash::make($data['password']);

        User::query()->create($data);

        return redirect()->route('hrd.employees.index')->with('success', 'Data Karyawan berhasil disimpan!');
    }

    public function edit(User $employee): View
    {
        return $this->form($employee, true);
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        $data = $this->validated($request, $employee);
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $employee->update($data);

        return redirect()->route('hrd.employees.index')->with('success', 'Data Karyawan berhasil disimpan!');
    }

    public function destroy(User $employee): RedirectResponse
    {
        if ($employee->id === auth()->id()) {
            return back()->withErrors('Anda tidak bisa menghapus akun sendiri!');
        }

        try {
            $employee->delete();
        } catch (QueryException) {
            return back()->withErrors('Gagal menghapus. Data user ini terikat dengan transaksi (SO/PO/Log). Sebaiknya set status menjadi Resigned.');
        }

        return redirect()->route('hrd.employees.index')->with('success', 'Data Karyawan berhasil dihapus.');
    }

    private function form(User $employee, bool $isEdit): View
    {
        return view('hrd.employees.form', [
            'employee' => $employee,
            'isEdit' => $isEdit,
            'roles' => Role::query()->orderBy('role_name')->get(),
            'departments' => $this->departments(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $employee = null): array
    {
        $request->merge([
            'basic_salary' => $this->normalizeNumber($request->input('basic_salary')),
        ]);

        return $request->validate([
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($employee)],
            'fullname' => ['required', 'string', 'max:100'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => [$employee ? 'nullable' : 'required', 'string', 'min:4'],
            'nik' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'department' => ['nullable', 'string', 'max:50'],
            'join_date' => ['nullable', 'date'],
            'employee_status' => ['required', Rule::in(['probation', 'contract', 'permanent', 'resigned'])],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'bank_account' => ['nullable', 'string', 'max:120'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function departments(): array
    {
        return [
            'Production' => 'Production',
            'Sales' => 'Sales & Marketing',
            'PPIC' => 'PPIC / Warehouse',
            'Finance' => 'Finance & Acc',
            'HRD' => 'HRD / GA',
            'Management' => 'Management',
        ];
    }

    private function normalizeNumber(mixed $value): float
    {
        $raw = preg_replace('/[^\d]/', '', (string) $value);

        return $raw === '' ? 0.0 : (float) $raw;
    }
}
