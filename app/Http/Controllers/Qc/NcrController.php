<?php

namespace App\Http\Controllers\Qc;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Ncr;
use App\Models\ProductionAssignment;
use App\Models\QcProduction;
use App\Models\User;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NcrController extends Controller
{
    public function index(): View
    {
        return view('qc.ncr.index', [
            'ncrs' => Ncr::query()->with(['item', 'operator'])->latest('id')->get(),
            'operators' => $this->operators(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Ncr([
            'ncr_number' => 'AUTO',
            'source_type' => 'production',
            'qty_reject' => 0,
            'disposition' => 'pending',
            'status' => 'open',
        ]), false);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Ncr::query()->create($data + [
            'ncr_number' => $this->nextNcrNumber(),
            'reference_id' => 0,
            'status' => 'open',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('qc.ncr.index')->with('success', 'NCR berhasil disimpan.');
    }

    public function edit(Ncr $ncr): View
    {
        return $this->form($ncr, true);
    }

    public function update(Request $request, Ncr $ncr): RedirectResponse
    {
        $data = $this->validated($request);
        if ($ncr->status === 'open' && trim((string) $data['root_cause']) !== '') {
            $data['status'] = 'analyzed';
        }
        $ncr->update($data);

        return redirect()->route('qc.ncr.index')->with('success', 'NCR berhasil disimpan.');
    }

    public function assignResponsible(Request $request, Ncr $ncr): RedirectResponse
    {
        abort_if(! $request->user()?->hasPermission('qc_ncr_manage'), 403);
        $data = $request->validate(['operator_id' => ['required', 'integer', 'exists:users,id']]);
        if ($ncr->status !== 'waiting_responsible') {
            return back()->withErrors('NCR ini tidak dalam status menunggu penanggung jawab.');
        }
        $ncr->update(['operator_id' => $data['operator_id']]);

        return back()->with('success', 'Penanggung jawab berhasil ditugaskan.');
    }

    public function signResponsible(Request $request, Ncr $ncr): RedirectResponse
    {
        abort_if(! $request->user()?->hasPermission('qc_ncr_resp_approve'), 403);
        if ($ncr->status !== 'waiting_responsible') {
            return back()->withErrors('NCR ini tidak dalam status menunggu tanda tangan.');
        }
        if ($ncr->operator_id && (int) $ncr->operator_id !== (int) $request->user()?->id) {
            return back()->withErrors('Hanya penanggung jawab yang ditunjuk yang boleh menandatangani.');
        }
        $ncr->update(['status' => 'waiting_gm', 'resp_signed_by' => auth()->id(), 'resp_signed_at' => now()]);

        return back()->with('success', 'Tanda tangan penanggung jawab berhasil.');
    }

    public function appeal(Request $request, Ncr $ncr): RedirectResponse
    {
        abort_if(! $request->user()?->hasPermission('qc_ncr_resp_approve'), 403);
        $data = $request->validate(['appeal_note' => ['required', 'string']]);
        if ($ncr->status !== 'waiting_responsible') {
            return back()->withErrors('NCR ini tidak dalam status menunggu penanggung jawab.');
        }
        if ($ncr->operator_id && (int) $ncr->operator_id !== (int) $request->user()?->id) {
            return back()->withErrors('Hanya penanggung jawab yang ditunjuk yang boleh banding.');
        }
        $ncr->update(['status' => 'appealed', 'resp_appeal_by' => auth()->id(), 'resp_appeal_at' => now(), 'resp_appeal_note' => $data['appeal_note']]);

        return back()->with('success', 'Banding terkirim ke QC.');
    }

    public function approve(Request $request, Ncr $ncr): RedirectResponse
    {
        abort_if(! $request->user()?->hasPermission('qc_ncr_approve'), 403);
        if ($ncr->status !== 'waiting_gm') {
            return back()->withErrors('NCR ini tidak dalam status menunggu approval GM.');
        }
        if (! $ncr->resp_signed_by || ! $ncr->resp_signed_at) {
            return back()->withErrors('NCR belum ditandatangani penanggung jawab.');
        }
        if (in_array($ncr->disposition, ['pending', null, ''], true)) {
            return redirect()->route('qc.ncr.edit', $ncr)->withErrors('Disposisi belum ditentukan.');
        }

        DB::transaction(function () use ($ncr): void {
            $ncr->update(['status' => 'approved', 'gm_approved_by' => auth()->id(), 'gm_approved_at' => now()]);
            $qc = $ncr->source_type === 'production' ? QcProduction::query()->find($ncr->reference_id) : null;
            if ($qc && in_array($ncr->disposition, ['repair', 'scrap'], true)) {
                $label = $ncr->ncr_number ?: 'NCR-' . $ncr->id;
                $item = $ncr->item;
                $itemLabel = trim(($item?->item_code ?: '') . ' ' . ($item?->item_name ?: ''));
                $process = trim('NCR ' . $label . ' - ' . strtoupper($ncr->disposition) . ' ' . $itemLabel);
                ProductionAssignment::query()->firstOrCreate([
                    'spk_id' => $qc->spk_id,
                    'process_name' => $process,
                ], [
                    'operator_id' => null,
                    'status' => 'assigned',
                ]);
            }
        });

        return back()->with('success', 'NCR disetujui GM.');
    }

    public function close(Request $request, Ncr $ncr): RedirectResponse
    {
        abort_if(! $request->user()?->hasPermission('qc_ncr_manage'), 403);
        if ($ncr->status !== 'approved') {
            return back()->withErrors('NCR ini belum disetujui GM.');
        }
        $ncr->update(['status' => 'closed']);

        return back()->with('success', 'Kasus NCR ditutup.');
    }

    public function print(Ncr $ncr): View
    {
        return view('qc.ncr.print', [
            'ncr' => $ncr->load(['item', 'creator', 'operator', 'responsibleSigner', 'gmApprover']),
            'company' => app(MmsContext::class)->company(),
        ]);
    }

    private function form(Ncr $ncr, bool $isEdit): View
    {
        return view('qc.ncr.form', [
            'ncr' => $ncr,
            'isEdit' => $isEdit,
            'items' => Item::query()->orderBy('item_name')->get(['id', 'item_code', 'item_name']),
            'operators' => $this->operators(),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'source_type' => ['required', 'in:production,incoming'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'qty_reject' => ['required', 'numeric', 'min:0.01'],
            'issue_description' => ['required', 'string'],
            'root_cause' => ['nullable', 'string'],
            'corrective_action' => ['nullable', 'string'],
            'operator_id' => ['nullable', 'integer', 'exists:users,id'],
            'disposition' => ['required', 'in:pending,repair,scrap,return_to_vendor'],
        ]);
    }

    private function operators()
    {
        return User::query()->where('role_id', '!=', 1)->orderBy('fullname')->get(['id', 'fullname']);
    }

    private function nextNcrNumber(): string
    {
        $ym = now()->format('ym');
        $count = Ncr::query()->where('ncr_number', 'like', "NCR-{$ym}-%")->count() + 1;

        return 'NCR-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
