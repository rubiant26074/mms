<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('accounting.reports.index', $this->reportData($request));
    }

    public function print(Request $request): View
    {
        $data = $this->reportData($request);
        $data['company'] = CompanyProfile::query()->find(1) ?? new CompanyProfile();
        $data['preparedUser'] = auth()->user();
        $data['approverUser'] = User::query()
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->where(function ($query): void {
                $query->whereRaw("LOWER(COALESCE(roles.role_name, '')) LIKE '%finance%'")
                    ->orWhereRaw("LOWER(COALESCE(roles.role_name, '')) LIKE '%account%'");
            })
            ->orderByRaw("CASE WHEN LOWER(COALESCE(roles.role_name, '')) LIKE '%manager%' THEN 0 ELSE 1 END")
            ->orderBy('users.id')
            ->select('users.*')
            ->first();

        return view('accounting.reports.print', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportData(Request $request): array
    {
        $startDate = $this->dateOrDefault((string) $request->query('start_date', ''), now()->startOfYear()->format('Y-m-d'));
        $endDate = $this->dateOrDefault((string) $request->query('end_date', ''), now()->format('Y-m-d'));
        $type = $request->query('type') === 'bs' ? 'bs' : 'pl';

        $data = [
            'revenue' => collect(),
            'expense' => collect(),
            'asset' => collect(),
            'liability' => collect(),
            'equity' => collect(),
        ];
        $totals = [
            'totalRevenue' => 0.0,
            'totalExpense' => 0.0,
            'totalAsset' => 0.0,
            'totalLiability' => 0.0,
            'totalEquity' => 0.0,
            'netIncome' => 0.0,
        ];

        if ($type === 'pl') {
            $rows = $this->accountBalances(['revenue', 'expense'], $startDate, $endDate);
            foreach ($rows as $row) {
                $balance = $this->balance($row);
                if ($row->account_type === 'revenue') {
                    $row->balance_rev = $balance;
                    $data['revenue']->push($row);
                    $totals['totalRevenue'] += $balance;
                } else {
                    $row->balance_exp = $balance;
                    $data['expense']->push($row);
                    $totals['totalExpense'] += $balance;
                }
            }
            $totals['netIncome'] = $totals['totalRevenue'] - $totals['totalExpense'];
        } else {
            $rows = $this->accountBalances(['asset', 'liability', 'equity'], null, $endDate);
            foreach ($rows as $row) {
                $balance = $this->balance($row);
                if ($row->account_type === 'asset') {
                    $row->balance_asset = $balance;
                    $data['asset']->push($row);
                    $totals['totalAsset'] += $balance;
                } elseif ($row->account_type === 'liability') {
                    $row->balance_passiva = $balance;
                    $data['liability']->push($row);
                    $totals['totalLiability'] += $balance;
                } else {
                    $row->balance_passiva = $balance;
                    $data['equity']->push($row);
                    $totals['totalEquity'] += $balance;
                }
            }

            $retained = $this->accountBalances(['revenue', 'expense'], null, $endDate)
                ->sum(fn ($row) => $this->balance($row));
            $data['equity']->push((object) [
                'account_code' => '3-9999',
                'account_name' => 'Laba Tahun Berjalan',
                'balance_passiva' => $retained,
            ]);
            $totals['totalEquity'] += $retained;
        }

        return array_merge($totals, [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'reportType' => $type,
            'data' => $data,
            'periodLabel' => $type === 'pl'
                ? Carbon::parse($startDate)->format('d M Y') . ' s/d ' . Carbon::parse($endDate)->format('d M Y')
                : 'Per Tanggal: ' . Carbon::parse($endDate)->format('d M Y'),
        ]);
    }

    /**
     * @param array<int, string> $types
     */
    private function accountBalances(array $types, ?string $startDate, string $endDate)
    {
        return DB::table('coa as c')
            ->leftJoinSub(function ($query) use ($startDate, $endDate): void {
                $query->from('journal_items as ji')
                    ->join('journals as j', 'j.id', '=', 'ji.journal_id')
                    ->selectRaw('ji.coa_id, SUM(ji.debit) as sum_debit, SUM(ji.credit) as sum_credit')
                    ->when($startDate, fn ($sub) => $sub->whereBetween('j.journal_date', [$startDate, $endDate]), fn ($sub) => $sub->where('j.journal_date', '<=', $endDate))
                    ->groupBy('ji.coa_id');
            }, 'm', 'm.coa_id', '=', 'c.id')
            ->selectRaw('c.account_code, c.account_name, c.account_type, c.opening_balance, c.normal_balance, COALESCE(m.sum_debit, 0) as sum_debit, COALESCE(m.sum_credit, 0) as sum_credit')
            ->whereIn('c.account_type', $types)
            ->orderBy('c.account_code')
            ->get();
    }

    private function balance(object $row): float
    {
        $movement = $row->normal_balance === 'credit'
            ? ((float) $row->sum_credit - (float) $row->sum_debit)
            : ((float) $row->sum_debit - (float) $row->sum_credit);

        return (float) $row->opening_balance + $movement;
    }

    private function dateOrDefault(string $value, string $default): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $default;
    }
}
