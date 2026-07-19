<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ProductionAssignment;
use App\Models\ProductionLog;
use App\Models\ProductionPartlistProgress;
use App\Models\Spk;
use App\Models\SpkPartlist;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OperatorController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user?->hasPermission('prod_view') && ! $user?->hasPermission('prod_operator_access'), 403);

        $isViewOnly = $user?->hasPermission('prod_view') && (! $user->hasPermission('prod_operator_access') || $request->query('mode') === 'view');
        $operatorId = $isViewOnly ? $request->integer('operator_id') : (int) $user?->id;
        $operators = $isViewOnly ? $this->operators() : collect();

        $activeTask = null;
        $queueTasks = collect();
        $partlists = collect();
        $progress = collect();
        $progressLogs = collect();
        $partStats = ['total' => 0, 'done' => 0, 'unfinished' => 0];

        if ($operatorId > 0) {
            $activeTask = $this->baseAssignmentQuery()
                ->where('production_assignments.operator_id', $operatorId)
                ->whereIn('production_assignments.status', ['in_progress', 'hold'])
                ->first();

            $queueTasks = $this->baseAssignmentQuery()
                ->where('production_assignments.operator_id', $operatorId)
                ->where('production_assignments.status', 'assigned')
                ->orderBy('spk.deadline_date')
                ->get();

            if ($activeTask) {
                if ($activeTask->spk) {
                    $activeTask->spk->drawing_info = $this->parseDrawingInfo($activeTask->spk->drawing_link);
                }
                $partlists = $this->matchingPartlists($activeTask);
                $progress = ProductionPartlistProgress::query()
                    ->where('assignment_id', $activeTask->id)
                    ->get()
                    ->groupBy('partlist_id')
                    ->map(fn ($rows) => [
                        'qty_done' => $rows->sum('qty_done'),
                        'state' => $rows->last()?->progress_state ?: 'progress',
                    ]);
                $partStats = $this->partStats($partlists, $progress);
                $progressLogs = ProductionPartlistProgress::query()
                    ->with('partlist')
                    ->where('assignment_id', $activeTask->id)
                    ->latest('id')
                    ->limit(20)
                    ->get();
            }

            foreach ($queueTasks as $qt) {
                if ($qt->spk) {
                    $qt->spk->drawing_info = $this->parseDrawingInfo($qt->spk->drawing_link);
                }
            }
        }

        return view('production.operator.index', compact(
            'isViewOnly',
            'operatorId',
            'operators',
            'activeTask',
            'queueTasks',
            'partlists',
            'progress',
            'progressLogs',
            'partStats'
        ));
    }

    public function start(Request $request, ProductionAssignment $assignment): RedirectResponse
    {
        $this->authorizeOperatorAction($request, $assignment);

        if ($this->machineRequired((string) $assignment->process_name) && ! $assignment->machine_id) {
            return back()->withErrors('Gagal mulai: mesin belum dipilih pada penugasan proses ini.');
        }

        if ($this->hasMaterialIssueTable() && ! $this->hasApprovedMaterialIssue((int) $assignment->spk_id)) {
            return back()->withErrors('Gagal mulai: material belum dikeluarkan/disetujui oleh Gudang.');
        }

        $assignment->update(['status' => 'in_progress', 'start_time' => now()]);
        $this->log($assignment, 'start', (int) $request->user()?->id);

        return redirect()->route('production.operator.index')->with('success', 'Tugas mulai dikerjakan.');
    }

    public function hold(Request $request, ProductionAssignment $assignment): RedirectResponse
    {
        $this->authorizeOperatorAction($request, $assignment);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $assignment->update(['status' => 'hold']);
        $this->log($assignment, 'hold', (int) $request->user()?->id, trim($data['reason'] . ' - ' . ($data['notes'] ?? '')));

        return redirect()->route('production.operator.index')->with('success', 'Tugas ditahan.');
    }

    public function resume(Request $request, ProductionAssignment $assignment): RedirectResponse
    {
        $this->authorizeOperatorAction($request, $assignment);
        $assignment->update(['status' => 'in_progress']);
        $this->log($assignment, 'resume', (int) $request->user()?->id);

        return redirect()->route('production.operator.index')->with('success', 'Tugas dilanjutkan.');
    }

    public function partProgress(Request $request, ProductionAssignment $assignment): RedirectResponse
    {
        $this->authorizeOperatorAction($request, $assignment);
        $data = $request->validate([
            'partlist_id' => ['required', 'integer', 'exists:spk_partlists,id'],
            'qty_done' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $part = SpkPartlist::query()
            ->where('spk_id', $assignment->spk_id)
            ->whereKey($data['partlist_id'])
            ->firstOrFail();
        $already = (float) ProductionPartlistProgress::query()
            ->where('assignment_id', $assignment->id)
            ->where('partlist_id', $part->id)
            ->sum('qty_done');
        $target = (float) $part->qty;
        if ($target > 0 && $already + (float) $data['qty_done'] > $target) {
            return back()->withErrors("Qty melebihi target part ({$target}). Mohon koreksi.");
        }

        ProductionPartlistProgress::query()->create([
            'assignment_id' => $assignment->id,
            'spk_id' => $assignment->spk_id,
            'partlist_id' => $part->id,
            'qty_done' => (float) $data['qty_done'],
            'progress_state' => $target > 0 && $already + (float) $data['qty_done'] >= $target ? 'done' : 'pending',
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('production.operator.index')->with('success', 'Progress partlist tersimpan.');
    }

    public function finish(Request $request, ProductionAssignment $assignment): RedirectResponse
    {
        $this->authorizeOperatorAction($request, $assignment);
        $data = $request->validate([
            'qty_good' => ['required', 'numeric', 'min:0'],
            'qty_reject' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $partlists = $this->matchingPartlists($assignment);
        $progress = ProductionPartlistProgress::query()
            ->where('assignment_id', $assignment->id)
            ->get()
            ->groupBy('partlist_id')
            ->map(fn ($rows) => [
                'qty_done' => $rows->sum('qty_done'),
                'state' => $rows->last()?->progress_state ?: 'progress',
            ]);

        if ($this->partStats($partlists, $progress)['unfinished'] > 0) {
            return back()->withErrors('Masih ada partlist yang belum selesai.');
        }

        DB::transaction(function () use ($assignment, $request, $data): void {
            $assignment->update([
                'status' => 'completed',
                'end_time' => now(),
                'qty_good' => (float) $data['qty_good'],
                'qty_reject' => (float) ($data['qty_reject'] ?? 0),
                'notes' => $data['notes'] ?? null,
            ]);
            $this->log($assignment, 'finish', (int) $request->user()?->id);

            $remaining = ProductionAssignment::query()
                ->where('spk_id', $assignment->spk_id)
                ->where('status', '!=', 'completed')
                ->count();
            if ($remaining === 0) {
                Spk::query()->whereKey($assignment->spk_id)->update(['status' => 'completed']);
            }
        });

        return redirect()->route('production.operator.index')->with('success', 'Pekerjaan selesai.');
    }

    private function baseAssignmentQuery()
    {
        return ProductionAssignment::query()
            ->select('production_assignments.*')
            ->with(['spk.salesOrder.customer', 'machine'])
            ->join('spk', 'spk.id', '=', 'production_assignments.spk_id');
    }

    private function operators(): Collection
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->where('role_slug', 'operator')->orWhere('role_slug', 'like', 'op_%'))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'role_id']);
    }

    private function matchingPartlists(ProductionAssignment $assignment): Collection
    {
        $parts = SpkPartlist::query()->where('spk_id', $assignment->spk_id)->orderBy('id')->get();
        $matched = $parts->filter(fn ($part) => $this->processMatches((string) $part->process, (string) $assignment->process_name))->values();
        $result = $matched->isNotEmpty() ? $matched : $parts;

        $drawingNos = $result->pluck('drawing_no')->filter()->all();
        $itemNos = $result->pluck('item_no')->filter()->all();
        $partNames = $result->pluck('part_name')->filter()->all();

        $itemDrawings = Item::query()
            ->whereNotNull('drawing_file')
            ->where('drawing_file', '!=', '')
            ->where(function ($q) use ($drawingNos, $itemNos, $partNames): void {
                if ($drawingNos) {
                    $q->orWhereIn('item_code', $drawingNos);
                }
                if ($itemNos) {
                    $q->orWhereIn('item_code', $itemNos);
                }
                if ($partNames) {
                    $q->orWhereIn('item_name', $partNames);
                }
            })
            ->get(['item_code', 'item_name', 'drawing_file']);

        $byCode = $itemDrawings->pluck('drawing_file', 'item_code')->mapWithKeys(fn ($file, $code) => [strtolower((string) $code) => $file]);
        $byName = $itemDrawings->pluck('drawing_file', 'item_name')->mapWithKeys(fn ($file, $name) => [strtolower((string) $name) => $file]);

        foreach ($result as $part) {
            $rawPath = trim((string) $part->drawing_path);
            if ($rawPath === '') {
                $codeKey = strtolower(trim((string) ($part->drawing_no ?: $part->item_no)));
                $nameKey = strtolower(trim((string) $part->part_name));
                $rawPath = (string) ($byCode->get($codeKey) ?: $byName->get($nameKey) ?: '');
            }

            $part->drawing_info = $this->parseDrawingInfo($rawPath);
            $part->resolved_drawing_url = $part->drawing_info['url'] ?? null;
        }

        return $result;
    }

    private function parseDrawingInfo(?string $path): ?array
    {
        if (! $path) {
            return null;
        }

        $path = trim($path, " \t\n\r\0\x0B\"'");
        if ($path === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return [
                'type' => 'web',
                'url' => $path,
                'raw' => $path,
                'win_path' => null,
                'is_local' => false,
            ];
        }

        if (preg_match('/^(file:\/\/\/|[a-z]:[\\\\\/]|\\\\\\\)/i', $path)) {
            $cleanPath = str_replace('\\', '/', $path);
            $fileUrl = preg_match('/^[a-z]:\//i', $cleanPath) ? 'file:///' . $cleanPath : $cleanPath;
            $winPath = str_replace('/', '\\', preg_replace('/^file:\/\/\//i', '', $path));

            return [
                'type' => 'local',
                'url' => $fileUrl,
                'win_path' => $winPath,
                'raw' => $path,
                'is_local' => true,
            ];
        }

        return [
            'type' => 'server',
            'url' => asset(ltrim($path, '/')),
            'raw' => $path,
            'win_path' => null,
            'is_local' => false,
        ];
    }

    private function partStats(Collection $partlists, Collection $progress): array
    {
        $done = 0;
        foreach ($partlists as $part) {
            $row = $progress->get($part->id, ['qty_done' => 0, 'state' => 'progress']);
            $target = (float) $part->qty;
            if (($row['state'] ?? '') === 'done' || ($target > 0 && (float) $row['qty_done'] >= $target)) {
                $done++;
            }
        }

        $total = $partlists->count();

        return [
            'total' => $total,
            'done' => $done,
            'unfinished' => $total === 0 ? 1 : max(0, $total - $done),
        ];
    }

    private function processMatches(string $partProcess, string $routeProcess): bool
    {
        $partProcess = trim($partProcess);
        $routeProcess = trim($routeProcess);
        if ($partProcess === '' || $routeProcess === '') {
            return false;
        }
        if (strcasecmp($partProcess, $routeProcess) === 0 || stripos($partProcess, $routeProcess) !== false || stripos($routeProcess, $partProcess) !== false) {
            return true;
        }

        return count(array_intersect($this->processKeywords($partProcess), $this->processKeywords($routeProcess))) > 0;
    }

    private function processKeywords(string $value): array
    {
        $value = preg_replace('/[^a-z0-9]+/', ' ', strtolower($value));
        $tokens = preg_split('/\s+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        $map = [
            'fibre' => 'laser', 'fiber' => 'laser', 'laser' => 'laser', 'cut' => 'laser', 'cutting' => 'laser',
            'bending' => 'bend', 'bend' => 'bend', 'forming' => 'bend', 'pressbrake' => 'bend',
            'welding' => 'weld', 'weld' => 'weld', 'mig' => 'weld', 'tig' => 'weld',
            'paint' => 'finish', 'painting' => 'finish', 'powder' => 'finish', 'coating' => 'finish', 'grinding' => 'finish',
        ];

        return array_values(array_unique(array_map(fn ($token) => $map[$token] ?? $token, $tokens)));
    }

    private function machineRequired(string $process): bool
    {
        $process = strtolower($process);

        return str_contains($process, 'laser') || str_contains($process, 'bending') || str_contains($process, 'welding') || preg_match('/\blas\b/', $process);
    }

    private function authorizeOperatorAction(Request $request, ProductionAssignment $assignment): void
    {
        abort_if(! $request->user()?->hasPermission('prod_operator_access'), 403);
        abort_if((int) $assignment->operator_id !== (int) $request->user()?->id, 403);
    }

    private function log(ProductionAssignment $assignment, string $activity, int $operatorId, ?string $notes = null): void
    {
        ProductionLog::query()->create([
            'assignment_id' => $assignment->id,
            'activity' => $activity,
            'log_time' => now(),
            'operator_id' => $operatorId,
            'notes' => $notes,
        ]);
    }

    private function hasMaterialIssueTable(): bool
    {
        return DB::getSchemaBuilder()->hasTable('material_issues');
    }

    private function hasApprovedMaterialIssue(int $spkId): bool
    {
        return DB::table('material_issues')->where('spk_id', $spkId)->where('status', 'approved')->exists();
    }
}
