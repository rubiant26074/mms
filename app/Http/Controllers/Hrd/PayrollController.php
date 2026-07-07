<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\MmsNotification;
use App\Models\Payroll;
use App\Models\User;
use App\Services\MmsContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request, MmsContext $context): View
    {
        $context->syncLegacySession(auth()->user()->loadMissing('role.permissions'));

        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
        $startDate = trim((string) $request->query('start_date', ''));
        $endDate = trim((string) $request->query('end_date', ''));

        $payrolls = Payroll::query()
            ->with(['employee.role'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($startDate !== '', fn ($query) => $query->whereDate('period_start', '>=', $startDate))
            ->when($endDate !== '', fn ($query) => $query->whereDate('period_end', '<=', $endDate))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('payroll_code', 'like', $term)
                        ->orWhereHas('employee', fn ($employee) => $employee->where('fullname', 'like', $term))
                        ->orWhereHas('employee.role', fn ($role) => $role->where('role_name', 'like', $term));
                });
            })
            ->latest('id')
            ->get();

        return view('hrd.payroll.index', compact('payrolls', 'status', 'search', 'startDate', 'endDate'));
    }

    public function create(): View
    {
        return $this->form(new Payroll([
            'payroll_code' => 'AUTO',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'basic_salary' => 0,
            'allowance_total' => 0,
            'deduction_total' => 0,
            'net_salary' => 0,
            'total_attendance' => 0,
            'status' => 'draft',
        ]), false);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Payroll::query()->create($data + [
            'payroll_code' => $this->nextNumber($data['period_end']),
            'net_salary' => $data['basic_salary'] + $data['allowance_total'] - $data['deduction_total'],
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('hrd.payroll.index')->with('success', 'Data Gaji tersimpan!');
    }

    public function edit(Payroll $payroll): View|RedirectResponse
    {
        if ($payroll->status !== 'draft') {
            return redirect()->route('hrd.payroll.index')->withErrors('Hanya slip draft yang bisa diedit.');
        }

        return $this->form($payroll, true);
    }

    public function update(Request $request, Payroll $payroll): RedirectResponse
    {
        if ($payroll->status !== 'draft') {
            return back()->withErrors('Hanya slip draft yang bisa diubah.');
        }

        $data = $this->validated($request);
        $payroll->update($data + [
            'net_salary' => $data['basic_salary'] + $data['allowance_total'] - $data['deduction_total'],
        ]);

        return redirect()->route('hrd.payroll.index')->with('success', 'Data Gaji tersimpan!');
    }

    public function destroy(Payroll $payroll): RedirectResponse
    {
        if ($payroll->status !== 'draft') {
            return back()->withErrors('Hanya slip draft yang bisa dihapus.');
        }

        $payroll->delete();

        return redirect()->route('hrd.payroll.index')->with('success', 'Data gaji draft berhasil dihapus.');
    }

    public function pay(Payroll $payroll): RedirectResponse
    {
        if ($payroll->status !== 'draft') {
            return back()->withErrors('Hanya slip draft yang bisa ditandai dibayar.');
        }

        $payroll->update(['status' => 'paid']);
        $this->notifyPaid($payroll);

        return redirect()->route('hrd.payroll.index')->with('success', 'Gaji telah dibayarkan (Paid)!');
    }
    public function print(Payroll $payroll): View
    {
        $payroll->loadMissing(['employee.role', 'creator']);

        return view('hrd.payroll.print', [
            'payroll' => $payroll,
            'company' => app(MmsContext::class)->company(),
            'preparedBy' => $payroll->creator?->fullname ?: 'HRD / Finance',
        ]);
    }

    public function attendanceCount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uid' => ['required', 'integer', 'exists:users,id'],
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
        ]);

        $count = Attendance::query()
            ->where('user_id', $data['uid'])
            ->whereBetween('date', [$data['start'], $data['end']])
            ->whereIn('status', ['present', 'late'])
            ->count();

        return response()->json(['count' => $count]);
    }

    private function form(Payroll $payroll, bool $isEdit): View
    {
        return view('hrd.payroll.form', [
            'payroll' => $payroll,
            'isEdit' => $isEdit,
            'employees' => User::query()->with('role')->where('role_id', '!=', 1)->orderBy('fullname')->get(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $request->merge([
            'basic_salary' => $this->money($request->input('basic_salary')),
            'allowance_total' => $this->money($request->input('allowance_total')),
            'deduction_total' => $this->money($request->input('deduction_total')),
        ]);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'allowance_total' => ['nullable', 'numeric', 'min:0'],
            'deduction_total' => ['nullable', 'numeric', 'min:0'],
            'total_attendance' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['draft', 'paid'])],
        ]);

        return $data;
    }

    private function nextNumber(string $date): string
    {
        $ym = Carbon::parse($date)->format('ym');
        $count = Payroll::query()->where('payroll_code', 'like', "PAY-{$ym}-%")->count() + 1;

        return 'PAY-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function money(mixed $value): float
    {
        $raw = preg_replace('/[^\d]/', '', (string) $value);

        return $raw === '' ? 0.0 : (float) $raw;
    }

    private function notifyPaid(Payroll $payroll): void
    {
        $link = route('hrd.payroll.print', $payroll);
        $message = 'Slip payroll #' . $payroll->id . ' telah dibayar.';

        foreach (['owner', 'manager'] as $targetRole) {
            MmsNotification::query()->create([
                'sender_id' => auth()->id(),
                'user_id' => null,
                'title' => 'Payroll Dibayarkan',
                'target_role' => $targetRole,
                'message' => $message,
                'link' => $link,
                'type' => 'success',
                'is_read' => 0,
            ]);
        }
    }
}
