<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\ProductionAssignment;
use App\Models\ProductionLog;
use App\Models\ProductionPartlistProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $date = $this->date($request);
        $activity = $this->activity($request);

        $logs = ProductionLog::query()
            ->with(['assignment.spk', 'operator'])
            ->whereDate('log_time', $date)
            ->when($activity !== 'all', fn ($query) => $query->where('activity', $activity))
            ->latest('log_time')
            ->get();

        $summary = ProductionAssignment::query()
            ->where('status', 'completed')
            ->whereDate('end_time', $date)
            ->selectRaw('COALESCE(SUM(qty_good),0) as sum_good, COALESCE(SUM(qty_reject),0) as sum_reject, COUNT(*) as count_finish')
            ->first();

        $sumGood = (float) ($summary->sum_good ?? 0);
        $sumReject = (float) ($summary->sum_reject ?? 0);
        $countFinish = (int) ($summary->count_finish ?? 0);
        $totalOutput = $sumGood + $sumReject;
        $yield = $totalOutput > 0 ? round(($sumGood / $totalOutput) * 100, 1) : 0;

        $partProgress = ProductionPartlistProgress::query()
            ->with(['assignment.spk', 'creator', 'partlist'])
            ->whereDate('created_at', $date)
            ->latest('created_at')
            ->get();

        return view('production.reports.index', [
            'date' => $date,
            'activity' => $activity,
            'logs' => $logs,
            'partProgress' => $partProgress,
            'sumGood' => $sumGood,
            'sumReject' => $sumReject,
            'countFinish' => $countFinish,
            'yield' => $yield,
        ]);
    }

    private function date(Request $request): string
    {
        $value = trim((string) $request->query('date', now()->toDateString()));
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function activity(Request $request): string
    {
        $activity = trim((string) $request->query('activity', 'all'));

        return in_array($activity, ['all', 'start', 'hold', 'resume', 'finish'], true) ? $activity : 'all';
    }
}
