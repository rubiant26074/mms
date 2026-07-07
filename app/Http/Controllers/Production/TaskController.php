<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\ProductionAssignment;
use App\Models\Spk;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $spks = DB::table('spk')
            ->select([
                'spk.*',
                'sales_orders.so_number',
                'customers.name as customer_name',
                DB::raw('(SELECT COUNT(*) FROM production_assignments WHERE spk_id = spk.id) as assigned'),
                DB::raw("(SELECT COUNT(*) FROM production_assignments WHERE spk_id = spk.id AND status = 'completed') as completed"),
            ])
            ->leftJoin('sales_orders', 'sales_orders.id', '=', 'spk.sales_order_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->where(function ($query): void {
                $query->whereIn('spk.status', ['waiting_mgr', 'final', 'released', 'in_production', 'completed', 'closed'])
                    ->orWhereExists(function ($sub): void {
                        $sub->selectRaw('1')
                            ->from('production_assignments as pa')
                            ->whereColumn('pa.spk_id', 'spk.id')
                            ->where('pa.process_name', 'like', 'NCR %');
                    });
            })
            ->when($status !== '', fn ($query) => $query->where('spk.status', $status))
            ->orderBy('spk.deadline_date')
            ->get()
            ->map(function ($row) {
                $processes = $this->processList((string) $row->required_processes);
                $needed = max(count($processes), (int) $row->assigned);
                $done = (int) $row->completed;
                $row->total_needed = $needed;
                $row->progress_percent = $needed > 0 ? (int) round(($done / $needed) * 100) : 0;
                $row->deadline = $row->deadline_date ? Carbon::parse($row->deadline_date) : null;
                $row->days_remaining = $row->deadline ? now()->startOfDay()->diffInDays($row->deadline, false) : null;

                return $row;
            });

        return view('production.tasks.index', compact('spks', 'status'));
    }

    public function manage(Spk $spk): View
    {
        $spk->load('salesOrder.customer');
        $assignments = ProductionAssignment::query()
            ->with(['operator', 'machine'])
            ->where('spk_id', $spk->id)
            ->get()
            ->keyBy('process_name');
        $processes = $this->processList((string) $spk->required_processes);
        foreach ($assignments->keys() as $process) {
            if (str_starts_with((string) $process, 'NCR ') && ! in_array($process, $processes, true)) {
                $processes[] = (string) $process;
            }
        }

        return view('production.tasks.manage', [
            'spk' => $spk,
            'processes' => $processes,
            'assignments' => $assignments,
            'operators' => $this->operators(),
            'machines' => Machine::query()->orderBy('process_type')->orderBy('machine_name')->get(),
        ]);
    }

    public function assign(Request $request, Spk $spk): RedirectResponse
    {
        $data = $request->validate([
            'process_name' => ['required', 'array'],
            'process_name.*' => ['required', 'string', 'max:150'],
            'operator_id' => ['nullable', 'array'],
            'operator_id.*' => ['nullable', 'integer', 'exists:users,id'],
            'machine_id' => ['nullable', 'array'],
            'machine_id.*' => ['nullable', 'integer', 'exists:machines,id'],
        ]);

        DB::transaction(function () use ($spk, $data): void {
            foreach ($data['process_name'] as $index => $process) {
                $process = trim((string) $process);
                $operatorId = (int) ($data['operator_id'][$index] ?? 0);
                $machineId = (int) ($data['machine_id'][$index] ?? 0);
                if ($process === '' || $operatorId <= 0) {
                    continue;
                }

                $assignment = ProductionAssignment::query()
                    ->where('spk_id', $spk->id)
                    ->where('process_name', $process)
                    ->first();

                $payload = [
                    'operator_id' => $operatorId,
                    'machine_id' => $machineId > 0 ? $machineId : null,
                ];

                if ($assignment) {
                    if (! in_array($assignment->status, ['in_progress', 'hold', 'completed'], true)) {
                        $payload['status'] = 'assigned';
                    }
                    $assignment->update($payload);
                } else {
                    ProductionAssignment::query()->create($payload + [
                        'spk_id' => $spk->id,
                        'process_name' => $process,
                        'status' => 'assigned',
                    ]);
                }
            }

            if ($spk->status === 'released') {
                $spk->update(['status' => 'in_production']);
            }
        });

        return redirect()->route('production.tasks.index')->with('success', 'Tugas berhasil didistribusikan.');
    }

    /**
     * @return array<int, string>
     */
    private function processList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($process) => $process !== ''));
    }

    private function operators()
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->where('role_slug', 'operator')->orWhere('role_slug', 'like', 'op_%'))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'role_id']);
    }
}
