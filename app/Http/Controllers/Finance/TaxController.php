<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TaxController extends Controller
{
    public function index(Request $request): View
    {
        [$month, $year] = $this->period($request);

        return view('finance.tax.index', $this->taxData($month, $year));
    }

    public function print(Request $request): View
    {
        [$month, $year] = $this->period($request);
        $data = $this->taxData($month, $year);
        $data['company'] = CompanyProfile::query()->find(1) ?? new CompanyProfile();

        return view('finance.tax.print', $data);
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'between:2000,2100'],
            'payment_date' => ['required', 'date'],
            'method' => ['required', 'in:Transfer Bank,Cash,e-Billing Pajak'],
            'amount' => ['required', 'string'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! Schema::hasTable('tax_payments')) {
            return back()->withErrors('Tabel pembayaran pajak belum tersedia. Jalankan migration database/migrations/20260211_02_tax_payments.sql');
        }

        $month = (int) $data['period_month'];
        $year = (int) $data['period_year'];
        $amount = (float) preg_replace('/[^0-9]/', '', $data['amount']);
        if ($amount <= 0) {
            return back()->withErrors('Nominal setor pajak harus lebih dari 0.');
        }

        $summary = $this->vatSummary($month, $year);
        $alreadyPaid = $this->taxPaid($month, $year);
        $remaining = max(0, $summary['tax_due'] - $alreadyPaid);

        if ($remaining <= 0.01) {
            return back()->withErrors('PPN masa ini sudah lunas.');
        }
        if ($amount > ($remaining + 1)) {
            return back()->withErrors('Nominal melebihi sisa PPN yang harus disetor.');
        }

        $journalNote = '';
        DB::transaction(function () use ($data, $month, $year, $amount, &$journalNote): void {
            $journalId = null;
            $taxPayable = $this->coaId('2-2001');
            $cashOrBank = $this->coaId($data['method'] === 'Cash' ? '1-1001' : '1-1002');

            if ($taxPayable && $cashOrBank) {
                $periodRef = sprintf('PPN-%04d%02d', $year, $month);
                $journalId = $this->createJournal(
                    $data['payment_date'],
                    $periodRef,
                    'Setor PPN Masa ' . Carbon::create($year, $month, 1)->format('F Y') . ' (' . $data['method'] . ')',
                    [
                        ['coa_id' => $taxPayable, 'debit' => $amount, 'credit' => 0],
                        ['coa_id' => $cashOrBank, 'debit' => 0, 'credit' => $amount],
                    ],
                    'payment'
                );
            } else {
                $journalNote = ' Jurnal belum dibuat (COA 2-2001 / kas-bank belum tersedia).';
            }

            DB::table('tax_payments')->insert([
                'tax_type' => 'ppn',
                'period_month' => $month,
                'period_year' => $year,
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'method' => $data['method'],
                'reference_no' => $data['reference_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'journal_id' => $journalId,
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);
        });

        return redirect()
            ->route('finance.tax.index', ['month' => $month, 'year' => $year])
            ->with('success', 'Setor pajak berhasil disimpan!' . $journalNote);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function period(Request $request): array
    {
        $today = now();
        $month = (int) $request->query('month', $today->month);
        $year = (int) $request->query('year', $today->year);

        return [
            $month >= 1 && $month <= 12 ? $month : $today->month,
            $year >= 2000 && $year <= 2100 ? $year : $today->year,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taxData(int $month, int $year): array
    {
        $summary = $this->vatSummary($month, $year);
        $taxTableExists = Schema::hasTable('tax_payments');
        $taxPaid = $taxTableExists ? $this->taxPaid($month, $year) : 0;
        $paymentHistory = $taxTableExists ? $this->paymentHistory($month, $year) : collect();
        $invoices = DB::table('invoices')
            ->select('id', 'invoice_number', 'invoice_date', 'due_date', 'tax_invoice_number', 'customer_id')
            ->where('status', '!=', 'cancelled')
            ->whereMonth('invoice_date', $month)
            ->whereYear('invoice_date', $year)
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();

        $taxRemaining = max(0, $summary['tax_due'] - $taxPaid);
        $statusTax = $summary['ppn_payable'] > 0 ? 'KURANG BAYAR' : ($summary['ppn_payable'] < 0 ? 'LEBIH BAYAR (RESTITUSI)' : 'NIHIL');

        return array_merge($summary, [
            'month' => $month,
            'year' => $year,
            'periodLabel' => Carbon::create($year, $month, 1)->format('F Y'),
            'taxTableExists' => $taxTableExists,
            'taxPaid' => $taxPaid,
            'taxRemaining' => $taxRemaining,
            'statusTax' => $statusTax,
            'statusColor' => $summary['ppn_payable'] > 0 ? 'text-danger' : 'text-success',
            'paymentHistory' => $paymentHistory,
            'invoices' => $invoices,
        ]);
    }

    /**
     * @return array{ppn_out:float,dpp_out:float,ppn_in:float,dpp_in:float,ppn_payable:float,tax_due:float}
     */
    private function vatSummary(int $month, int $year): array
    {
        $out = DB::table('invoices')
            ->selectRaw('COALESCE(SUM(tax_amount),0) as total_ppn, COALESCE(SUM(subtotal),0) as dpp')
            ->where('status', '!=', 'cancelled')
            ->whereMonth('invoice_date', $month)
            ->whereYear('invoice_date', $year)
            ->first();

        $in = DB::table('supplier_bills')
            ->selectRaw('COALESCE(SUM(tax_amount),0) as total_ppn, COALESCE(SUM(subtotal),0) as dpp')
            ->where('status', '!=', 'cancelled')
            ->whereMonth('bill_date', $month)
            ->whereYear('bill_date', $year)
            ->first();

        $ppnOut = (float) ($out->total_ppn ?? 0);
        $ppnIn = (float) ($in->total_ppn ?? 0);
        $ppnPayable = $ppnOut - $ppnIn;

        return [
            'ppn_out' => $ppnOut,
            'dpp_out' => (float) ($out->dpp ?? 0),
            'ppn_in' => $ppnIn,
            'dpp_in' => (float) ($in->dpp ?? 0),
            'ppn_payable' => $ppnPayable,
            'tax_due' => max(0, $ppnPayable),
        ];
    }

    private function taxPaid(int $month, int $year): float
    {
        return (float) DB::table('tax_payments')
            ->where('tax_type', 'ppn')
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where('status', 'posted')
            ->sum('amount');
    }

    private function paymentHistory(int $month, int $year)
    {
        return DB::table('tax_payments as tp')
            ->leftJoin('journals as j', 'j.id', '=', 'tp.journal_id')
            ->select('tp.*', 'j.journal_no')
            ->where('tp.tax_type', 'ppn')
            ->where('tp.period_month', $month)
            ->where('tp.period_year', $year)
            ->orderByDesc('tp.payment_date')
            ->orderByDesc('tp.id')
            ->get();
    }

    private function coaId(string $accountCode): ?int
    {
        $id = DB::table('coa')
            ->where('account_code', $accountCode)
            ->where(function ($query): void {
                $query->where('is_active', 1)->orWhereNull('is_active');
            })
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * @param array<int, array{coa_id:int,debit:float,credit:float}> $items
     */
    private function createJournal(string $journalDate, string $referenceNo, string $description, array $items, string $type): int
    {
        $totalDebit = array_sum(array_column($items, 'debit'));
        $totalCredit = array_sum(array_column($items, 'credit'));
        if (round($totalDebit, 2) !== round($totalCredit, 2) || $totalDebit <= 0) {
            throw new \RuntimeException('Jurnal tidak balance.');
        }

        $ym = Carbon::parse($journalDate)->format('ym');
        $count = DB::table('journals')->where('journal_no', 'like', "JRN-{$ym}-%")->count() + 1;
        $journalNo = 'JRN-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);

        $journalId = (int) DB::table('journals')->insertGetId([
            'journal_no' => $journalNo,
            'journal_date' => $journalDate,
            'reference_no' => $referenceNo,
            'description' => $description,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'type' => $type,
            'created_by' => auth()->id(),
        ]);

        $coaMap = DB::table('coa')
            ->whereIn('id', array_column($items, 'coa_id'))
            ->pluck('normal_balance', 'id');

        foreach ($items as $item) {
            DB::table('journal_items')->insert([
                'journal_id' => $journalId,
                'coa_id' => $item['coa_id'],
                'debit' => $item['debit'],
                'credit' => $item['credit'],
            ]);

            $normal = $coaMap[$item['coa_id']] ?? null;
            if ($normal === null) {
                throw new \RuntimeException('COA ID ' . $item['coa_id'] . ' tidak ditemukan.');
            }
            $change = $normal === 'credit'
                ? ($item['credit'] - $item['debit'])
                : ($item['debit'] - $item['credit']);
            DB::table('coa')->where('id', $item['coa_id'])->increment('current_balance', $change);
        }

        return $journalId;
    }
}
