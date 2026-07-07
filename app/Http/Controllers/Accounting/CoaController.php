<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CoaController extends Controller
{
    public function index(Request $request): View
    {
        $type = trim((string) $request->query('type', ''));
        $search = trim((string) $request->query('search', ''));

        $accounts = ChartOfAccount::query()
            ->when($type !== '', fn ($query) => $query->where('account_type', $type))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(fn ($sub) => $sub->where('account_code', 'like', $term)->orWhere('account_name', 'like', $term));
            })
            ->orderBy('account_code')
            ->get();

        return view('accounting.coa.index', compact('accounts', 'type', 'search'));
    }

    public function create(): View
    {
        return view('accounting.coa.form', [
            'account' => new ChartOfAccount([
                'account_type' => 'asset',
                'normal_balance' => 'debit',
                'opening_balance' => 0,
            ]),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['current_balance'] = $data['opening_balance'];

        ChartOfAccount::query()->create($data);

        return redirect()->route('accounting.coa.index')->with('success', 'Data Akun tersimpan!');
    }

    public function edit(ChartOfAccount $coa): View
    {
        return view('accounting.coa.form', [
            'account' => $coa,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, ChartOfAccount $coa): RedirectResponse
    {
        $coa->update($this->validated($request, $coa));

        return redirect()->route('accounting.coa.index')->with('success', 'Data Akun tersimpan!');
    }

    public function destroy(ChartOfAccount $coa): RedirectResponse
    {
        $coa->delete();

        return redirect()->route('accounting.coa.index')->with('success', 'Akun berhasil dihapus.');
    }

    public function reconcile(): RedirectResponse
    {
        $updated = DB::transaction(fn () => $this->reconcileBalances());

        return redirect()->route('accounting.coa.index')->with('success', 'Rekonsiliasi saldo COA selesai. Akun diperbarui: ' . $updated);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ChartOfAccount $account = null): array
    {
        return $request->validate([
            'account_code' => ['required', 'string', 'max:20', Rule::unique('coa', 'account_code')->ignore($account)],
            'account_name' => ['required', 'string', 'max:100'],
            'account_type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'opening_balance' => ['required', 'numeric'],
        ]);
    }

    private function reconcileBalances(): int
    {
        $rows = DB::table('coa as c')
            ->leftJoin('journal_items as ji', 'ji.coa_id', '=', 'c.id')
            ->selectRaw('c.id, c.normal_balance, c.opening_balance, c.current_balance, COALESCE(SUM(ji.debit), 0) as sum_debit, COALESCE(SUM(ji.credit), 0) as sum_credit')
            ->groupBy('c.id', 'c.normal_balance', 'c.opening_balance', 'c.current_balance')
            ->get();

        $updated = 0;
        foreach ($rows as $row) {
            $opening = (float) $row->opening_balance;
            $sumDebit = (float) $row->sum_debit;
            $sumCredit = (float) $row->sum_credit;
            $current = round((float) $row->current_balance, 2);
            $expected = round($opening + ($row->normal_balance === 'credit' ? ($sumCredit - $sumDebit) : ($sumDebit - $sumCredit)), 2);

            if (abs($expected - $current) > 0.009) {
                DB::table('coa')->where('id', $row->id)->update(['current_balance' => $expected]);
                $updated++;
            }
        }

        return $updated;
    }
}
