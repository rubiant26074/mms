<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\FixedAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function index(Request $request): View
    {
        return view('accounting.assets.index', $this->assetData($request));
    }

    public function create(): View
    {
        return view('accounting.assets.form', [
            'asset' => new FixedAsset([
                'asset_code' => 'AUTO',
                'category' => 'machinery',
                'acquisition_date' => now(),
                'acquisition_cost' => 0,
                'salvage_value' => 0,
                'useful_life_years' => 4,
            ]),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['asset_code'] = $this->nextAssetCode();
        $data['monthly_depreciation'] = $this->monthlyDepreciation((float) $data['acquisition_cost'], (float) $data['salvage_value'], (int) $data['useful_life_years']);
        $data['book_value'] = $data['acquisition_cost'];
        $data['status'] = 'active';

        FixedAsset::query()->create($data);

        return redirect()->route('accounting.assets.index')->with('success', 'Aset berhasil disimpan!');
    }

    public function edit(FixedAsset $asset): View
    {
        return view('accounting.assets.form', compact('asset') + ['isEdit' => true]);
    }

    public function update(Request $request, FixedAsset $asset): RedirectResponse
    {
        $data = $request->validate([
            'asset_name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::in(['machinery', 'vehicle', 'building', 'equipment', 'electronic'])],
            'notes' => ['nullable', 'string'],
        ]);

        $asset->update($data);

        return redirect()->route('accounting.assets.index')->with('success', 'Aset berhasil disimpan!');
    }

    public function destroy(FixedAsset $asset): RedirectResponse
    {
        $asset->delete();

        return redirect()->route('accounting.assets.index')->with('success', 'Aset berhasil dihapus.');
    }

    public function depreciate(): RedirectResponse
    {
        $count = DB::transaction(function (): int {
            $expenseCoa = $this->coaId('5-1003');
            $accumulatedCoa = $this->coaId('1-1401');
            $today = now()->format('Y-m-d');
            $count = 0;

            $assets = FixedAsset::query()
                ->where('status', 'active')
                ->whereColumn('book_value', '>', 'salvage_value')
                ->lockForUpdate()
                ->get();

            foreach ($assets as $asset) {
                $exists = DB::table('asset_depreciations')
                    ->where('asset_id', $asset->id)
                    ->whereMonth('depreciation_date', now()->month)
                    ->whereYear('depreciation_date', now()->year)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $amount = (float) $asset->monthly_depreciation;
                if (((float) $asset->book_value - $amount) < (float) $asset->salvage_value) {
                    $amount = (float) $asset->book_value - (float) $asset->salvage_value;
                }
                if ($amount <= 0) {
                    continue;
                }

                $journalId = null;
                if ($expenseCoa && $accumulatedCoa) {
                    $journalId = $this->createJournal($today, $asset->asset_code, 'Penyusutan Aset Bln ' . now()->format('m'), [
                        ['coa_id' => $expenseCoa, 'debit' => $amount, 'credit' => 0],
                        ['coa_id' => $accumulatedCoa, 'debit' => 0, 'credit' => $amount],
                    ]);
                }

                DB::table('asset_depreciations')->insert([
                    'asset_id' => $asset->id,
                    'depreciation_date' => $today,
                    'amount' => $amount,
                    'journal_id' => $journalId,
                ]);

                $asset->forceFill([
                    'accumulated_depreciation' => (float) $asset->accumulated_depreciation + $amount,
                    'book_value' => (float) $asset->book_value - $amount,
                ])->save();

                $count++;
            }

            return $count;
        });

        return redirect()->route('accounting.assets.index')->with('success', "Proses Depresiasi Selesai! {$count} aset telah disusutkan.");
    }

    public function print(Request $request): View
    {
        $data = $this->assetData($request);
        $data['company'] = CompanyProfile::query()->find(1) ?? new CompanyProfile();

        return view('accounting.assets.print', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function assetData(Request $request): array
    {
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
        $assets = FixedAsset::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(fn ($sub) => $sub->where('asset_code', 'like', $term)->orWhere('asset_name', 'like', $term)->orWhere('category', 'like', $term));
            })
            ->latest('id')
            ->get();

        return [
            'assets' => $assets,
            'status' => $status,
            'search' => $search,
            'totalAcquisition' => (float) $assets->sum('acquisition_cost'),
            'totalBook' => (float) $assets->sum('book_value'),
            'totalMonthlyDepreciation' => (float) $assets->sum('monthly_depreciation'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'asset_name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::in(['machinery', 'vehicle', 'building', 'equipment', 'electronic'])],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'string'],
            'salvage_value' => ['nullable', 'string'],
            'useful_life_years' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['acquisition_cost'] = $this->money($data['acquisition_cost']);
        $data['salvage_value'] = $this->money($data['salvage_value'] ?? '0');

        return $data;
    }

    private function money(string $value): float
    {
        return (float) str_replace('.', '', preg_replace('/[^0-9.]/', '', $value));
    }

    private function monthlyDepreciation(float $cost, float $salvage, int $years): float
    {
        return $years > 0 ? max(0, ($cost - $salvage) / ($years * 12)) : 0;
    }

    private function nextAssetCode(): string
    {
        $yy = now()->format('y');
        $count = FixedAsset::query()->where('asset_code', 'like', "FA-{$yy}-%")->count() + 1;

        return 'FA-' . $yy . '-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);
    }

    private function coaId(string $accountCode): ?int
    {
        $id = DB::table('coa')->where('account_code', $accountCode)->where(fn ($q) => $q->where('is_active', 1)->orWhereNull('is_active'))->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * @param array<int, array{coa_id:int,debit:float,credit:float}> $items
     */
    private function createJournal(string $date, string $reference, string $description, array $items): int
    {
        $totalDebit = array_sum(array_column($items, 'debit'));
        $totalCredit = array_sum(array_column($items, 'credit'));
        $ym = Carbon::parse($date)->format('ym');
        $count = DB::table('journals')->where('journal_no', 'like', "JRN-{$ym}-%")->count() + 1;
        $journalId = DB::table('journals')->insertGetId([
            'journal_no' => 'JRN-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT),
            'journal_date' => $date,
            'reference_no' => $reference,
            'description' => $description,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'type' => 'general',
            'created_by' => auth()->id(),
        ]);

        $normalBalances = DB::table('coa')->whereIn('id', array_column($items, 'coa_id'))->pluck('normal_balance', 'id');
        foreach ($items as $item) {
            DB::table('journal_items')->insert(['journal_id' => $journalId, 'coa_id' => $item['coa_id'], 'debit' => $item['debit'], 'credit' => $item['credit']]);
            $normal = $normalBalances[$item['coa_id']] ?? 'debit';
            $change = $normal === 'credit' ? ($item['credit'] - $item['debit']) : ($item['debit'] - $item['credit']);
            DB::table('coa')->where('id', $item['coa_id'])->increment('current_balance', $change);
        }

        return (int) $journalId;
    }
}
