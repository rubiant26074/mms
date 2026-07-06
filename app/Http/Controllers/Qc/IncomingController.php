<?php

namespace App\Http\Controllers\Qc;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\QcIncoming;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IncomingController extends Controller
{
    public function index(): View
    {
        return view('qc.incoming.index', [
            'pending' => GoodsReceipt::query()->with(['items', 'purchaseOrder.supplier', 'customer'])->where('status', 'qc_pending')->latest('id')->get(),
            'waitingApproval' => QcIncoming::query()->with(['goodsReceipt', 'inspector'])->where('status', 'waiting_approval')->latest('id')->get(),
            'handover' => QcIncoming::query()->with('goodsReceipt')->where('status', 'completed')->whereNull('handover_by')->latest('id')->get(),
            'history' => QcIncoming::query()->with(['goodsReceipt', 'inspector'])->where('status', 'completed')->whereNotNull('handover_by')->latest('id')->limit(10)->get(),
        ]);
    }

    public function inspect(Request $request): View|RedirectResponse
    {
        $receipt = GoodsReceipt::query()->with(['items.item', 'purchaseOrder.supplier', 'customer'])->findOrFail($request->integer('gr_id'));
        if ($receipt->status !== 'qc_pending') {
            return redirect()->route('qc.incoming.index')->withErrors('GR ini tidak sedang menunggu QC.');
        }
        if (QcIncoming::query()->where('goods_receipt_id', $receipt->id)->where('status', 'waiting_approval')->exists()) {
            return redirect()->route('qc.incoming.index')->withErrors('GR ini sudah punya hasil QC yang menunggu approval.');
        }

        return view('qc.incoming.form', compact('receipt'));
    }

    public function storeInspection(Request $request, GoodsReceipt $receipt): RedirectResponse
    {
        if ($receipt->status !== 'qc_pending') {
            return redirect()->route('qc.incoming.index')->withErrors('GR ini tidak sedang menunggu QC.');
        }

        $request->validate([
            'qty_good' => ['required', 'array'],
            'qty_reject' => ['required', 'array'],
            'item_notes' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ]);

        $receipt->load('items.item');
        DB::transaction(function () use ($request, $receipt): void {
            $rejectCount = 0;
            foreach ($receipt->items as $idx => $row) {
                if ((float) $request->input("qty_reject.{$idx}") > 0) {
                    $rejectCount++;
                }
            }
            $decision = $rejectCount === 0 ? 'accepted' : ($rejectCount === $receipt->items->count() ? 'rejected' : 'partial');
            $qc = QcIncoming::query()->create([
                'qc_number' => $this->nextQcNumber(),
                'goods_receipt_id' => $receipt->id,
                'qc_date' => now()->toDateString(),
                'inspector_id' => auth()->id(),
                'status' => 'waiting_approval',
                'final_decision' => $decision,
                'notes' => $request->input('notes'),
            ]);

            foreach ($receipt->items as $idx => $row) {
                $good = (float) $request->input("qty_good.{$idx}", 0);
                $reject = (float) $request->input("qty_reject.{$idx}", 0);
                $checklist = $request->input("checklist.{$row->item_id}", []);
                $qc->items()->create([
                    'item_id' => $row->item_id,
                    'qty_received' => $row->qty_received,
                    'qty_good' => $good,
                    'qty_reject' => $reject,
                    'checklist_data' => $checklist ? json_encode($checklist) : null,
                    'notes' => $request->input("item_notes.{$idx}"),
                ]);
                $row->update(['qty_good' => $good, 'qty_reject' => $reject]);
            }
        });

        return redirect()->route('qc.incoming.index')->with('success', 'Hasil QC berhasil disimpan! Menunggu Approval Manager.');
    }

    public function approve(QcIncoming $qc): RedirectResponse
    {
        if ($qc->status !== 'waiting_approval') {
            return back()->withErrors('Gagal approve. Status QC sudah berubah.');
        }

        DB::transaction(function () use ($qc): void {
            $qc->load('items');
            $qc->update(['status' => 'completed', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            $grStatus = $qc->final_decision === 'rejected' ? 'rejected' : 'approved';
            $qc->goodsReceipt()->update(['status' => $grStatus]);
            if ($grStatus === 'approved') {
                foreach ($qc->items as $row) {
                    if ((float) $row->qty_good > 0) {
                        DB::table('items')->where('id', $row->item_id)->increment('current_stock', (float) $row->qty_good);
                    }
                }
            }
        });

        return redirect()->route('qc.incoming.index')->with('success', 'QC berhasil di-Approve. Stok barang OK sudah diperbarui.');
    }

    public function handover(QcIncoming $qc): RedirectResponse
    {
        if ($qc->status !== 'completed' || $qc->handover_by) {
            return back()->withErrors('Barang mungkin sudah diterima sebelumnya.');
        }
        $qc->update(['handover_by' => auth()->id(), 'handover_at' => now()]);

        return redirect()->route('qc.incoming.index')->with('success', 'Serah terima berhasil dicatat.');
    }

    public function print(QcIncoming $qc): View
    {
        return view('qc.incoming.print', [
            'qc' => $qc->load(['goodsReceipt.purchaseOrder.supplier', 'goodsReceipt.customer', 'items.item', 'inspector', 'approver', 'handoverUser']),
            'company' => app(\App\Services\MmsContext::class)->company(),
        ]);
    }

    private function nextQcNumber(): string
    {
        $ym = now()->format('ym');
        $count = QcIncoming::query()->where('qc_number', 'like', "QC-IN-{$ym}-%")->count() + 1;

        return 'QC-IN-' . $ym . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
