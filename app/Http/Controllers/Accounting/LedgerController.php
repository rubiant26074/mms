<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LedgerController extends Controller
{
    public function index(Request $request): View
    {
        return view('accounting.ledger.index', $this->ledgerData($request));
    }

    public function print(Request $request): View
    {
        $data = $this->ledgerData($request);
        abort_if(! $data['account'], 404);
        $data['company'] = CompanyProfile::query()->find(1) ?? new CompanyProfile();

        return view('accounting.ledger.print', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function ledgerData(Request $request): array
    {
        $startDate = $this->dateOrDefault((string) $request->query('start_date', ''), now()->startOfMonth()->format('Y-m-d'));
        $endDate = $this->dateOrDefault((string) $request->query('end_date', ''), now()->format('Y-m-d'));
        if ($endDate < $startDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $coaId = (int) $request->query('coa_id', 0);
        $accounts = ChartOfAccount::query()->orderBy('account_code')->get();
        $account = $coaId > 0 ? ChartOfAccount::query()->find($coaId) : null;
        $ledger = collect();
        $openingBalance = 0.0;

        if ($account) {
            $previous = DB::table('journal_items as ji')
                ->join('journals as j', 'j.id', '=', 'ji.journal_id')
                ->selectRaw('COALESCE(SUM(ji.debit),0) as total_debit, COALESCE(SUM(ji.credit),0) as total_credit')
                ->where('ji.coa_id', $account->id)
                ->where('j.journal_date', '<', $startDate)
                ->first();

            $previousDebit = (float) ($previous->total_debit ?? 0);
            $previousCredit = (float) ($previous->total_credit ?? 0);
            $openingBalance = (float) $account->opening_balance + ($account->normal_balance === 'debit'
                ? ($previousDebit - $previousCredit)
                : ($previousCredit - $previousDebit));

            $running = $openingBalance;
            $ledger = DB::table('journal_items as ji')
                ->join('journals as j', 'j.id', '=', 'ji.journal_id')
                ->select('j.journal_date', 'j.journal_no', 'j.reference_no', 'j.description', 'ji.debit', 'ji.credit')
                ->where('ji.coa_id', $account->id)
                ->whereBetween('j.journal_date', [$startDate, $endDate])
                ->orderBy('j.journal_date')
                ->orderBy('j.id')
                ->get()
                ->map(function ($row) use ($account, &$running) {
                    $debit = (float) $row->debit;
                    $credit = (float) $row->credit;
                    $running += $account->normal_balance === 'debit' ? ($debit - $credit) : ($credit - $debit);
                    $row->running_balance = $running;
                    return $row;
                });
        }

        return [
            'accounts' => $accounts,
            'account' => $account,
            'ledger' => $ledger,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'coaId' => $coaId,
            'openingBalance' => $openingBalance,
            'totalDebit' => (float) $ledger->sum('debit'),
            'totalCredit' => (float) $ledger->sum('credit'),
            'endingBalance' => $ledger->last()->running_balance ?? $openingBalance,
        ];
    }

    private function dateOrDefault(string $value, string $default): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $default;
    }
}
