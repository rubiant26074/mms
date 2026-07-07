<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\CompanyProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class JournalController extends Controller
{
    public function index(Request $request): View
    {
        return view('accounting.journal.index', $this->journalData($request));
    }

    public function create(): View
    {
        return view('accounting.journal.form', [
            'accounts' => ChartOfAccount::query()->orderBy('account_code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'journal_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string'],
            'coa_id' => ['required', 'array', 'min:2'],
            'coa_id.*' => ['nullable', 'integer', 'exists:coa,id'],
            'debit' => ['required', 'array'],
            'credit' => ['required', 'array'],
        ]);

        $items = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($data['coa_id'] as $index => $coaId) {
            $debit = (float) ($data['debit'][$index] ?? 0);
            $credit = (float) ($data['credit'][$index] ?? 0);
            if ($coaId && ($debit > 0 || $credit > 0)) {
                $items[] = ['coa_id' => (int) $coaId, 'debit' => $debit, 'credit' => $credit];
                $totalDebit += $debit;
                $totalCredit += $credit;
            }
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return back()->withInput()->withErrors('Jurnal Tidak Balance! Debit: ' . $totalDebit . ', Kredit: ' . $totalCredit);
        }
        if ($totalDebit <= 0 || $items === []) {
            return back()->withInput()->withErrors('Nominal tidak boleh 0.');
        }

        DB::transaction(function () use ($data, $items, $totalDebit, $totalCredit): void {
            $ym = Carbon::parse($data['journal_date'])->format('ym');
            $count = DB::table('journals')->where('journal_no', 'like', "JRN-{$ym}-%")->count() + 1;
            $journalNo = 'JRN-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);

            $journalId = DB::table('journals')->insertGetId([
                'journal_no' => $journalNo,
                'journal_date' => $data['journal_date'],
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'],
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'type' => 'general',
                'created_by' => auth()->id(),
            ]);

            $normalBalances = ChartOfAccount::query()
                ->whereIn('id', array_column($items, 'coa_id'))
                ->pluck('normal_balance', 'id');

            foreach ($items as $item) {
                DB::table('journal_items')->insert([
                    'journal_id' => $journalId,
                    'coa_id' => $item['coa_id'],
                    'debit' => $item['debit'],
                    'credit' => $item['credit'],
                ]);

                $normal = $normalBalances[$item['coa_id']] ?? 'debit';
                $change = $normal === 'credit'
                    ? ($item['credit'] - $item['debit'])
                    : ($item['debit'] - $item['credit']);
                DB::table('coa')->where('id', $item['coa_id'])->increment('current_balance', $change);
            }
        });

        return redirect()->route('accounting.journal.index')->with('success', 'Jurnal berhasil disimpan!');
    }

    public function print(Request $request): View
    {
        $data = $this->journalData($request);
        $data['company'] = CompanyProfile::query()->find(1) ?? new CompanyProfile();

        return view('accounting.journal.print', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function journalData(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $startDate = $this->dateOrEmpty((string) $request->query('start_date', ''));
        $endDate = $this->dateOrEmpty((string) $request->query('end_date', ''));

        $rows = DB::table('journal_items as ji')
            ->join('journals as j', 'j.id', '=', 'ji.journal_id')
            ->join('coa as c', 'c.id', '=', 'ji.coa_id')
            ->select('j.id as journal_id', 'j.journal_date', 'j.journal_no', 'j.reference_no', 'j.description', 'ji.debit', 'ji.credit', 'c.account_name', 'c.account_code')
            ->when($startDate !== '', fn ($query) => $query->where('j.journal_date', '>=', $startDate))
            ->when($endDate !== '', fn ($query) => $query->where('j.journal_date', '<=', $endDate))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('j.journal_no', 'like', $term)
                        ->orWhere('j.reference_no', 'like', $term)
                        ->orWhere('j.description', 'like', $term)
                        ->orWhere('c.account_name', 'like', $term)
                        ->orWhere('c.account_code', 'like', $term);
                });
            })
            ->orderByDesc('j.journal_date')
            ->orderByDesc('j.id')
            ->orderBy('ji.credit')
            ->get();

        $periodLabel = 'Semua Periode';
        if ($startDate !== '' && $endDate !== '') {
            $periodLabel = Carbon::parse($startDate)->format('d/m/Y') . ' - ' . Carbon::parse($endDate)->format('d/m/Y');
        } elseif ($startDate !== '') {
            $periodLabel = 'Mulai ' . Carbon::parse($startDate)->format('d/m/Y');
        } elseif ($endDate !== '') {
            $periodLabel = 'Sampai ' . Carbon::parse($endDate)->format('d/m/Y');
        }

        return [
            'rows' => $rows,
            'search' => $search,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'periodLabel' => $periodLabel,
            'totalDebit' => (float) $rows->sum('debit'),
            'totalCredit' => (float) $rows->sum('credit'),
            'journalCount' => $rows->pluck('journal_id')->unique()->count(),
        ];
    }

    private function dateOrEmpty(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
}
