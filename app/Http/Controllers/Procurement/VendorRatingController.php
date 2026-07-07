<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\VendorRating;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendorRatingController extends Controller
{
    public function index(Request $request): View
    {
        $period = trim((string) $request->query('period', now()->format('Y-m')));
        $supplierId = $request->integer('supplier_id') ?: null;

        $ratings = VendorRating::query()
            ->with('supplier')
            ->when($period !== '', fn ($query) => $query->where('rating_period', $period))
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->orderByDesc('rating_period')
            ->orderByDesc('total_score')
            ->get();

        return view('procurement.vendor-ratings.index', [
            'ratings' => $ratings,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'code', 'name']),
            'period' => $period,
            'supplierId' => $supplierId,
            'averageScore' => $ratings->avg('total_score') ?: 0,
        ]);
    }

    public function create(): View
    {
        return $this->form(new VendorRating([
            'rating_period' => now()->format('Y-m'),
            'lead_time_score' => 0,
            'quality_score' => 0,
            'price_score' => 0,
        ]), false);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        VendorRating::query()->updateOrCreate(
            [
                'supplier_id' => $data['supplier_id'],
                'rating_period' => $data['rating_period'],
            ],
            $data + [
                'total_score' => $this->totalScore($data),
                'created_by' => auth()->id(),
            ]
        );

        return redirect()
            ->route('procurement.vendor_ratings.index', ['period' => $data['rating_period']])
            ->with('success', 'Vendor rating berhasil disimpan.');
    }

    public function edit(VendorRating $vendorRating): View
    {
        return $this->form($vendorRating, true);
    }

    public function update(Request $request, VendorRating $vendorRating): RedirectResponse
    {
        $data = $this->validated($request, $vendorRating);
        $vendorRating->update($data + ['total_score' => $this->totalScore($data)]);

        return redirect()
            ->route('procurement.vendor_ratings.index', ['period' => $data['rating_period']])
            ->with('success', 'Vendor rating berhasil disimpan.');
    }

    public function destroy(VendorRating $vendorRating): RedirectResponse
    {
        $vendorRating->delete();

        return back()->with('success', 'Data rating berhasil dihapus.');
    }

    public function print(Request $request): View
    {
        $period = trim((string) $request->query('period', now()->format('Y-m')));
        $supplierId = $request->integer('supplier_id') ?: null;

        $ratings = VendorRating::query()
            ->with('supplier')
            ->when($period !== '', fn ($query) => $query->where('rating_period', $period))
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->orderByDesc('total_score')
            ->get();

        return view('procurement.vendor-ratings.print', [
            'ratings' => $ratings,
            'period' => $period,
            'supplier' => $supplierId ? Supplier::query()->find($supplierId) : null,
            'company' => app(MmsContext::class)->company(),
        ]);
    }

    private function form(VendorRating $rating, bool $isEdit): View
    {
        return view('procurement.vendor-ratings.form', [
            'rating' => $rating,
            'isEdit' => $isEdit,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    private function validated(Request $request, ?VendorRating $rating = null): array
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'rating_period' => [
                'required',
                'date_format:Y-m',
                Rule::unique('vendor_ratings', 'rating_period')
                    ->where('supplier_id', $request->integer('supplier_id'))
                    ->ignore($rating),
            ],
            'lead_time_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'quality_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'price_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        foreach (['lead_time_score', 'quality_score', 'price_score'] as $field) {
            $data[$field] = round((float) $data[$field], 2);
        }

        return $data;
    }

    private function totalScore(array $data): float
    {
        return round(((float) $data['lead_time_score'] + (float) $data['quality_score'] + (float) $data['price_score']) / 3, 2);
    }
}
