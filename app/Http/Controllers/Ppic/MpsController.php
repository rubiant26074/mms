<?php

namespace App\Http\Controllers\Ppic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MpsController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::today();
        $month = (int) $request->query('month', $today->month);
        $year = (int) $request->query('year', $today->year);

        if ($month < 1 || $month > 12) {
            $month = $today->month;
        }
        if ($year < 2000 || $year > 2100) {
            $year = $today->year;
        }

        $schedules = DB::table('spk')
            ->select([
                'spk.*',
                DB::raw("COALESCE(spk.project_name, '-') as project_name"),
                'customers.name as customer_name',
                DB::raw('(SELECT COUNT(*) FROM production_assignments WHERE spk_id = spk.id) as total_tasks'),
                DB::raw("(SELECT COUNT(*) FROM production_assignments WHERE spk_id = spk.id AND status = 'completed') as completed_tasks"),
            ])
            ->leftJoin('sales_orders', 'sales_orders.id', '=', 'spk.sales_order_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->where(function ($query) use ($month, $year): void {
                $query->where(function ($dateQuery) use ($month, $year): void {
                    $dateQuery->whereMonth('spk.deadline_date', $month)
                        ->whereYear('spk.deadline_date', $year);
                })->orWhereIn('spk.status', ['released', 'in_production']);
            })
            ->orderBy('spk.deadline_date')
            ->get()
            ->map(function ($row) use ($today) {
                $deadline = $row->deadline_date ? Carbon::parse($row->deadline_date) : null;
                $daysRemaining = $deadline ? $today->diffInDays($deadline, false) : null;
                $totalTasks = (int) $row->total_tasks;
                $completedTasks = (int) $row->completed_tasks;
                $percent = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

                if (in_array($row->status, ['completed', 'closed'], true)) {
                    $percent = 100;
                }

                $row->deadline = $deadline;
                $row->days_remaining = $daysRemaining;
                $row->progress_percent = max(0, min(100, $percent));
                $row->status_label = $this->statusLabel($row->status, $daysRemaining);
                $row->card_classes = $this->cardClasses($row->status, $daysRemaining);

                return $row;
            });

        return view('ppic.mps.index', compact('schedules', 'month', 'year'));
    }

    /**
     * @return array{border:string,bg:string,text:string,progress:string}
     */
    private function cardClasses(string $status, ?int $daysRemaining): array
    {
        if (in_array($status, ['completed', 'closed'], true)) {
            return ['border' => 'border-dark', 'bg' => 'bg-secondary bg-opacity-10', 'text' => 'text-dark', 'progress' => 'bg-success'];
        }

        if ($daysRemaining !== null && $daysRemaining < 0) {
            return ['border' => 'border-danger', 'bg' => 'bg-danger bg-opacity-10', 'text' => 'text-danger', 'progress' => 'bg-danger'];
        }

        if ($daysRemaining !== null && $daysRemaining <= 3) {
            return ['border' => 'border-warning', 'bg' => 'bg-warning bg-opacity-10', 'text' => 'text-warning text-dark', 'progress' => 'bg-primary'];
        }

        return ['border' => 'border-success', 'bg' => 'bg-success bg-opacity-10', 'text' => 'text-success', 'progress' => 'bg-primary'];
    }

    private function statusLabel(string $status, ?int $daysRemaining): string
    {
        if (in_array($status, ['completed', 'closed'], true)) {
            return 'SELESAI';
        }

        if ($daysRemaining === null) {
            return strtoupper(str_replace('_', ' ', $status));
        }

        if ($daysRemaining < 0) {
            return 'TERLAMBAT ' . abs($daysRemaining) . ' HARI';
        }

        return 'Sisa ' . $daysRemaining . ' Hari';
    }
}
