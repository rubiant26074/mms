<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\ChartOfAccount;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CashController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $type = trim((string) $request->query('trx_type', ''));
        $search = trim((string) $request->query('search', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $transactions = CashTransaction::query()
            ->with(['counterCoa', 'cashCoa'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when(in_array($type, ['expense', 'income'], true), fn ($query) => $query->where('transaction_type', $type))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('expense_number', 'like', $term)
                        ->orWhere('category', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('vendor_name', 'like', $term)
                        ->orWhere('reference_no', 'like', $term)
                        ->orWhereHas('counterCoa', fn ($coa) => $coa->where('account_name', 'like', $term))
                        ->orWhereHas('cashCoa', fn ($coa) => $coa->where('account_name', 'like', $term));
                });
            })
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('expense_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('expense_date', '<=', $dateTo))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $summary = [
            'income_month' => (float) CashTransaction::query()->where('status', 'posted')->where('transaction_type', 'income')->whereRaw("DATE_FORMAT(expense_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")->sum('amount'),
            'expense_month' => (float) CashTransaction::query()->where('status', 'posted')->where('transaction_type', 'expense')->whereRaw("DATE_FORMAT(expense_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")->sum('amount'),
            'draft' => (float) CashTransaction::query()->where('status', 'draft')->sum('amount'),
        ];
        $summary['balance_month'] = $summary['income_month'] - $summary['expense_month'];

        $report = $this->reportData(
            trim((string) $request->query('rekap_from', now()->startOfMonth()->toDateString())),
            trim((string) $request->query('rekap_to', now()->endOfMonth()->toDateString())),
            $request->integer('rekap_cash_coa')
        );

        return view('finance.cash.index', compact('transactions', 'status', 'type', 'search', 'dateFrom', 'dateTo', 'summary', 'report'));
    }

    public function create(): View
    {
        return $this->form(new CashTransaction([
            'expense_number' => 'AUTO',
            'transaction_type' => 'expense',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'status' => 'draft',
            'coa_id' => $this->defaultCounterCoaId('expense'),
            'cash_coa_id' => $this->defaultCashCoaId('Cash'),
        ]), false);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        CashTransaction::query()->create($data + [
            'expense_number' => $this->nextNumber($data['expense_date'], $data['transaction_type']),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('finance.cash.index')->with('success', 'Data transaksi berhasil disimpan.');
    }

    public function edit(CashTransaction $transaction): View|RedirectResponse
    {
        if ($transaction->status !== 'draft') {
            return redirect()->route('finance.cash.index')->withErrors('Hanya transaksi draft yang bisa diedit.');
        }

        return $this->form($transaction, true);
    }

    public function update(Request $request, CashTransaction $transaction): RedirectResponse
    {
        if ($transaction->status !== 'draft') {
            return back()->withErrors('Hanya transaksi draft yang bisa diubah.');
        }
        $transaction->update($this->validated($request));

        return redirect()->route('finance.cash.index')->with('success', 'Data transaksi berhasil disimpan.');
    }

    public function destroy(CashTransaction $transaction): RedirectResponse
    {
        if ($transaction->status !== 'draft') {
            return back()->withErrors('Hanya data draft yang bisa dihapus.');
        }
        $transaction->delete();

        return redirect()->route('finance.cash.index')->with('success', 'Data transaksi berhasil dihapus.');
    }

    public function workflow(CashTransaction $transaction, string $action): RedirectResponse
    {
        try {
            DB::transaction(function () use ($transaction, $action): void {
                $locked = CashTransaction::query()->lockForUpdate()->findOrFail($transaction->id);

                if ($action === 'post') {
                    if ($locked->status !== 'draft') {
                        throw ValidationException::withMessages(['status' => 'Hanya data draft yang bisa diposting.']);
                    }
                    $this->createJournalIfPossible(
                        $locked->expense_date->toDateString(),
                        $locked->expense_number,
                        ($locked->transaction_type === 'income' ? 'Pemasukan' : 'Pengeluaran') . ' Kas/Kasir: ' . $locked->category,
                        $this->journalRows($locked),
                        $locked->transaction_type === 'income' ? 'cash_income' : 'cash_expense'
                    );
                    $locked->update(['status' => 'posted']);
                    return;
                }

                if ($action === 'unpost') {
                    if ($locked->status !== 'posted') {
                        throw ValidationException::withMessages(['status' => 'Hanya data posted yang bisa di-unpost.']);
                    }
                    $this->deleteJournalByReference($locked->expense_number, $locked->transaction_type === 'income' ? 'cash_income' : 'cash_expense');
                    $locked->update(['status' => 'draft']);
                    return;
                }

                if ($action === 'cancel') {
                    if ($locked->status === 'posted') {
                        $this->deleteJournalByReference($locked->expense_number, $locked->transaction_type === 'income' ? 'cash_income' : 'cash_expense');
                    }
                    $locked->update(['status' => 'cancelled']);
                }
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('finance.cash.index')->with('success', 'Status transaksi berhasil diperbarui.');
    }

    public function print(Request $request): View
    {
        $report = $this->reportData(
            trim((string) $request->query('rekap_from', now()->startOfMonth()->toDateString())),
            trim((string) $request->query('rekap_to', now()->endOfMonth()->toDateString())),
            $request->integer('rekap_cash_coa')
        );

        return view('finance.cash.print', [
            'report' => $report,
            'company' => app(MmsContext::class)->company(),
            'printedBy' => auth()->user()?->fullname ?: auth()->user()?->username ?: 'System',
        ]);
    }

    private function form(CashTransaction $transaction, bool $isEdit): View
    {
        return view('finance.cash.form', [
            'transaction' => $transaction,
            'isEdit' => $isEdit,
            'expenseAccounts' => ChartOfAccount::query()->where('account_type', 'expense')->where(fn ($q) => $q->where('is_active', 1)->orWhereNull('is_active'))->orderBy('account_code')->get(),
            'revenueAccounts' => ChartOfAccount::query()->where('account_type', 'revenue')->where(fn ($q) => $q->where('is_active', 1)->orWhereNull('is_active'))->orderBy('account_code')->get(),
            'cashAccounts' => ChartOfAccount::query()->where('account_type', 'asset')->where(fn ($q) => $q->where('is_active', 1)->orWhereNull('is_active'))->orderBy('account_code')->get(),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'transaction_type' => ['required', 'in:expense,income'],
            'coa_id' => ['required', 'integer', 'exists:coa,id'],
            'cash_coa_id' => ['required', 'integer', 'exists:coa,id'],
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'in:Cash,Transfer Bank,E-Wallet,Lainnya'],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'vendor_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $allowedCounterType = $data['transaction_type'] === 'income' ? 'revenue' : 'expense';
        $counterOk = ChartOfAccount::query()->whereKey($data['coa_id'])->where('account_type', $allowedCounterType)->exists();
        $cashOk = ChartOfAccount::query()->whereKey($data['cash_coa_id'])->where('account_type', 'asset')->exists();
        if (! $counterOk) {
            throw ValidationException::withMessages(['coa_id' => 'Akun lawan tidak valid untuk jenis transaksi ini.']);
        }
        if (! $cashOk) {
            throw ValidationException::withMessages(['cash_coa_id' => 'Akun kas/bank tidak valid.']);
        }

        return $data;
    }

    private function reportData(string $from, string $to, int $cashCoaId): array
    {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : now()->startOfMonth()->toDateString();
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : now()->endOfMonth()->toDateString();
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $cashAccounts = ChartOfAccount::query()
            ->where('account_type', 'asset')
            ->where(fn ($q) => $q->where('is_active', 1)->orWhereNull('is_active'))
            ->orderBy('account_code')
            ->get();
        if ($cashCoaId > 0 && ! $cashAccounts->pluck('id')->contains($cashCoaId)) {
            $cashCoaId = 0;
        }

        $openQuery = CashTransaction::query()->where('status', 'posted')->whereDate('expense_date', '<', $from);
        $periodQuery = CashTransaction::query()->where('status', 'posted')->whereBetween('expense_date', [$from, $to]);
        if ($cashCoaId > 0) {
            $openQuery->where('cash_coa_id', $cashCoaId);
            $periodQuery->where('cash_coa_id', $cashCoaId);
        }

        $opening = (float) $openQuery->selectRaw("COALESCE(SUM(CASE WHEN transaction_type='income' THEN amount ELSE -amount END),0) total")->value('total');
        $income = (float) (clone $periodQuery)->where('transaction_type', 'income')->sum('amount');
        $expense = (float) (clone $periodQuery)->where('transaction_type', 'expense')->sum('amount');
        $rows = DB::table('finance_cash_expenses')
            ->selectRaw("expense_date, COALESCE(SUM(CASE WHEN transaction_type='income' THEN amount ELSE 0 END),0) AS income_amount, COALESCE(SUM(CASE WHEN transaction_type='expense' THEN amount ELSE 0 END),0) AS expense_amount")
            ->where('status', 'posted')
            ->whereBetween('expense_date', [$from, $to])
            ->when($cashCoaId > 0, fn ($query) => $query->where('cash_coa_id', $cashCoaId))
            ->groupBy('expense_date')
            ->orderBy('expense_date')
            ->get();
        $transactions = CashTransaction::query()
            ->with(['counterCoa', 'cashCoa'])
            ->where('status', 'posted')
            ->whereBetween('expense_date', [$from, $to])
            ->when($cashCoaId > 0, fn ($query) => $query->where('cash_coa_id', $cashCoaId))
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get();

        return [
            'from' => $from,
            'to' => $to,
            'cash_coa_id' => $cashCoaId,
            'cash_accounts' => $cashAccounts,
            'cash_label' => $cashCoaId > 0 ? optional($cashAccounts->firstWhere('id', $cashCoaId), fn ($row) => $row->account_code . ' - ' . $row->account_name) : 'Semua Akun Kas/Bank',
            'opening' => $opening,
            'income' => $income,
            'expense' => $expense,
            'closing' => $opening + $income - $expense,
            'rows' => $rows,
            'transactions' => $transactions,
        ];
    }

    private function defaultCounterCoaId(string $type): ?int
    {
        return ChartOfAccount::query()
            ->where('account_type', $type === 'income' ? 'revenue' : 'expense')
            ->where(fn ($q) => $q->where('is_active', 1)->orWhereNull('is_active'))
            ->orderBy('account_code')
            ->value('id');
    }

    private function defaultCashCoaId(string $paymentMethod): ?int
    {
        $preferredCode = $paymentMethod === 'Cash' ? '1-1001' : '1-1002';
        $preferred = ChartOfAccount::query()->where('account_code', $preferredCode)->value('id');
        if ($preferred) {
            return (int) $preferred;
        }

        return ChartOfAccount::query()
            ->where('account_type', 'asset')
            ->where(fn ($q) => $q->where('is_active', 1)->orWhereNull('is_active'))
            ->orderBy('account_code')
            ->value('id');
    }

    private function nextNumber(string $date, string $type): string
    {
        $ym = Carbon::parse($date)->format('ym');
        $prefix = $type === 'income' ? 'CASHIN' : 'CASHOUT';
        $count = CashTransaction::query()->where('expense_number', 'like', "{$prefix}-{$ym}-%")->count() + 1;

        return $prefix . '-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function journalRows(CashTransaction $transaction): array
    {
        if ($transaction->transaction_type === 'income') {
            return [
                ['coa_id' => (int) $transaction->cash_coa_id, 'debit' => (float) $transaction->amount, 'credit' => 0],
                ['coa_id' => (int) $transaction->coa_id, 'debit' => 0, 'credit' => (float) $transaction->amount],
            ];
        }

        return [
            ['coa_id' => (int) $transaction->coa_id, 'debit' => (float) $transaction->amount, 'credit' => 0],
            ['coa_id' => (int) $transaction->cash_coa_id, 'debit' => 0, 'credit' => (float) $transaction->amount],
        ];
    }

    private function createJournalIfPossible(string $date, string $referenceNo, string $description, array $items, string $type): void
    {
        if (! DB::getSchemaBuilder()->hasTable('journals') || ! DB::getSchemaBuilder()->hasTable('journal_items')) {
            return;
        }

        $items = collect($items)->filter(fn ($row) => (int) ($row['coa_id'] ?? 0) > 0)->values();
        if ($items->isEmpty()) {
            return;
        }

        $ym = Carbon::parse($date)->format('ym');
        $count = DB::table('journals')->where('journal_no', 'like', "JRN-{$ym}-%")->count() + 1;
        $journalId = DB::table('journals')->insertGetId([
            'journal_no' => 'JRN-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT),
            'journal_date' => $date,
            'reference_no' => $referenceNo,
            'description' => $description,
            'total_debit' => $items->sum('debit'),
            'total_credit' => $items->sum('credit'),
            'type' => $type,
            'created_by' => auth()->id(),
        ]);
        foreach ($items as $row) {
            DB::table('journal_items')->insert([
                'journal_id' => $journalId,
                'coa_id' => $row['coa_id'],
                'debit' => $row['debit'],
                'credit' => $row['credit'],
            ]);
        }
    }

    private function deleteJournalByReference(string $referenceNo, string $type): void
    {
        if (! DB::getSchemaBuilder()->hasTable('journals') || ! DB::getSchemaBuilder()->hasTable('journal_items')) {
            return;
        }
        $journalIds = DB::table('journals')->where('reference_no', $referenceNo)->where('type', $type)->pluck('id');
        if ($journalIds->isEmpty()) {
            return;
        }
        DB::table('journal_items')->whereIn('journal_id', $journalIds)->delete();
        DB::table('journals')->whereIn('id', $journalIds)->delete();
    }
}
