<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\MaterialIssue;
use App\Models\Spk;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MaterialIssueController extends Controller
{
    public function index(Request $request): View
    {
        $status = $this->rememberedFilter($request, 'status', '');
        $search = $this->rememberedFilter($request, 'search', '');
        $issues = MaterialIssue::query()
            ->with('spk')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('itr_number', 'like', $term)
                        ->orWhere('received_by', 'like', $term)
                        ->orWhere('issued_by', 'like', $term)
                        ->orWhereHas('spk', fn ($spk) => $spk->where('spk_number', 'like', $term)->orWhere('project_name', 'like', $term));
                });
            })
            ->latest('id')
            ->get();

        return view('warehouse.material-issues.index', compact('issues', 'status', 'search'));
    }

    public function create(Request $request): View
    {
        $spk = $request->integer('spk_id') ? Spk::query()->with('materials.item')->find($request->integer('spk_id')) : null;

        return view('warehouse.material-issues.form', [
            'issue' => new MaterialIssue([
                'itr_date' => now()->toDateString(),
                'received_by' => auth()->user()?->fullname,
                'status' => 'request',
            ]),
            'spk' => $spk,
            'spkOptions' => $this->availableSpks($spk?->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'spk_id' => ['required', 'integer', 'exists:spk,id'],
            'itr_date' => ['required', 'date'],
            'received_by' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'item_id' => ['required', 'array'],
            'item_id.*' => ['required', 'integer', 'exists:items,id'],
            'qty_issued' => ['required', 'array'],
            'qty_issued.*' => ['required', 'numeric', 'min:0'],
        ]);

        if (MaterialIssue::query()->where('spk_id', $data['spk_id'])->where('status', '!=', 'rejected')->exists()) {
            throw ValidationException::withMessages(['spk_id' => 'SPK ini sudah memiliki ITR yang sedang diproses atau selesai.']);
        }

        $rows = [];
        foreach ($data['item_id'] as $idx => $itemId) {
            $qty = (float) ($data['qty_issued'][$idx] ?? 0);
            if ($qty > 0) {
                $rows[] = ['item_id' => (int) $itemId, 'qty_issued' => $qty];
            }
        }
        if ($rows === []) {
            return back()->withInput()->withErrors('Minimal 1 material harus diminta.');
        }

        DB::transaction(function () use ($data, $rows): void {
            $issue = MaterialIssue::query()->create([
                'itr_number' => $this->nextItrNumber(),
                'spk_id' => $data['spk_id'],
                'itr_date' => $data['itr_date'],
                'received_by' => $data['received_by'],
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
                'status' => 'request',
            ]);
            $issue->items()->createMany($rows);
        });

        return redirect()->route('warehouse.material_issues.index')->with('success', 'Permintaan Material (ITR) berhasil diajukan.');
    }

    public function approve(MaterialIssue $issue): RedirectResponse
    {
        if ($issue->status !== 'request') {
            return back()->withErrors('Gagal approve. Status mungkin sudah berubah.');
        }

        DB::transaction(function () use ($issue): void {
            $issue->load('items.item');
            $issue->update(['status' => 'approved', 'issued_by' => auth()->user()?->fullname, 'created_at' => now()]);
            foreach ($issue->items as $row) {
                DB::table('items')->where('id', $row->item_id)->decrement('current_stock', (float) $row->qty_issued);
            }
        });

        return redirect()->route('warehouse.material_issues.index')->with('success', 'Permintaan disetujui. Stok fisik telah dipotong.');
    }

    public function destroy(MaterialIssue $issue): RedirectResponse
    {
        if ($issue->status !== 'request') {
            return back()->withErrors('Hanya ITR berstatus request yang bisa dihapus.');
        }
        DB::transaction(function () use ($issue): void {
            $issue->items()->delete();
            $issue->delete();
        });

        return redirect()->route('warehouse.material_issues.index')->with('success', 'ITR request berhasil dihapus.');
    }

    public function print(MaterialIssue $issue): View
    {
        return view('warehouse.material-issues.print', [
            'issue' => $issue->load(['spk.salesOrder.customer', 'items.item', 'creator']),
            'company' => app(MmsContext::class)->company(),
        ]);
    }

    private function availableSpks(?int $selectedId = null)
    {
        return Spk::query()
            ->where(function ($query) use ($selectedId): void {
                $query->whereIn('status', ['released', 'in_production']);
                if ($selectedId) {
                    $query->orWhere('id', $selectedId);
                }
            })
            ->where(function ($query) use ($selectedId): void {
                $query->whereDoesntHave('materialIssues', fn ($q) => $q->where('status', '!=', 'rejected'));
                if ($selectedId) {
                    $query->orWhere('id', $selectedId);
                }
            })
            ->latest('id')
            ->get(['id', 'spk_number', 'project_name']);
    }

    private function nextItrNumber(): string
    {
        $ym = now()->format('ym');
        $count = MaterialIssue::query()->where('itr_number', 'like', "ITR-{$ym}-%")->count() + 1;

        return 'ITR-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
