<?php

namespace App\Http\Controllers;

use App\Services\MmsContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TvController extends Controller
{
    public function lobby(Request $request, MmsContext $context): View
    {
        $this->ensureFulfillmentSourceColumn();

        $company = $context->company();
        $companyName = (string) ($company->company_name ?: 'EXECUTIVE LOBBY');
        $runningText = trim((string) $company->running_text);
        if ($runningText === '') {
            $runningText = 'SELAMAT DATANG DI DASHBOARD EXECUTIVE MMS - SAFETY FIRST - KERJA BERKUALITAS, TEPAT WAKTU, DAN AMAN';
        }

        $today = now()->toDateString();
        $month = now()->month;
        $year = now()->year;

        $salesMonth = (float) DB::table('sales_orders')
            ->whereIn('status', ['confirmed', 'in_production', 'completed'])
            ->whereMonth('so_date', $month)
            ->whereYear('so_date', $year)
            ->sum('grand_total');
        $target = 500000000;
        $pct = $target > 0 ? (int) round(($salesMonth / $target) * 100) : 0;

        $soToday = (int) DB::table('sales_orders')->whereDate('so_date', $today)->count();
        $sjToday = (int) DB::table('delivery_notes')->whereDate('dn_date', $today)->count();

        $recentSo = collect(DB::select("
            SELECT so.id, so.so_number, so.status AS so_status,
                   COALESCE(so.fulfillment_source, 'spk') AS fulfillment_source,
                   c.name AS customer_name, so.grand_total,
                   s.id AS spk_id, s.spk_number, s.status AS spk_status
            FROM sales_orders so
            JOIN customers c ON so.customer_id = c.id
            LEFT JOIN spk s ON s.id = (
                SELECT id FROM spk WHERE sales_order_id = so.id ORDER BY id DESC LIMIT 1
            )
            ORDER BY so.id DESC LIMIT 5
        "));

        $spkIds = $recentSo->pluck('spk_id')->filter()->map(fn ($id) => (int) $id)->values();
        $tasksBySpk = [];
        if ($spkIds->isNotEmpty()) {
            $taskRows = collect(DB::table('production_assignments')
                ->select('spk_id', 'process_name', 'status')
                ->whereIn('spk_id', $spkIds)
                ->orderByRaw("FIELD(status,'in_progress','assigned','pending','hold','completed')")
                ->orderByDesc('id')
                ->get());
            foreach ($taskRows as $row) {
                $tasksBySpk[(int) $row->spk_id] ??= [];
                $tasksBySpk[(int) $row->spk_id][] = (array) $row;
            }

            $qcRows = collect(DB::select("
                SELECT qp.*
                FROM qc_production qp
                JOIN (SELECT spk_id, MAX(id) AS max_id FROM qc_production GROUP BY spk_id) qmax
                  ON qp.id = qmax.max_id
                WHERE qp.spk_id IN (" . implode(',', $spkIds->all()) . ")
            "))->keyBy(fn ($row) => (int) $row->spk_id);

            $recentSo = $recentSo->map(function ($row) use ($tasksBySpk, $qcRows) {
                $taskRows = ! empty($row->spk_id) ? ($tasksBySpk[(int) $row->spk_id] ?? []) : [];
                $qcRow = ! empty($row->spk_id) ? (array) ($qcRows->get((int) $row->spk_id) ?? []) : null;
                $row->stage_text = $this->currentStage((array) $row, $taskRows, $qcRow);
                $row->stage_class = $this->stageBadgeClass($row->stage_text);

                return $row;
            });
        }

        $openSoCount = (int) DB::table('sales_orders')
            ->whereNotIn(DB::raw('COALESCE(status,"")'), ['completed', 'closed', 'cancelled'])
            ->count();
        $completedMonth = (int) DB::table('sales_orders')
            ->whereIn('status', ['completed', 'closed'])
            ->whereMonth('so_date', $month)
            ->whereYear('so_date', $year)
            ->count();

        $fgStockMonth = 0;
        $spkMonth = 0;
        $fulfillmentRows = DB::table('sales_orders')
            ->selectRaw("COALESCE(fulfillment_source,'spk') src, COUNT(*) total")
            ->whereMonth('so_date', $month)
            ->whereYear('so_date', $year)
            ->groupBy(DB::raw("COALESCE(fulfillment_source,'spk')"))
            ->get();
        foreach ($fulfillmentRows as $row) {
            $src = strtolower((string) $row->src);
            if ($src === 'fg_stock') {
                $fgStockMonth += (int) $row->total;
            } else {
                $spkMonth += (int) $row->total;
            }
        }

        $monthLabels = [];
        $monthSoCounts = [];
        $monthSjCounts = [];
        $monthIdx = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->startOfMonth()->subMonths($i);
            $monthLabels[] = $date->format('M y');
            $monthSoCounts[] = 0;
            $monthSjCounts[] = 0;
            $monthIdx[$date->format('Y-m')] = count($monthLabels) - 1;
        }

        foreach (DB::select("SELECT DATE_FORMAT(so_date,'%Y-%m') ym, COUNT(*) total FROM sales_orders WHERE so_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(so_date,'%Y-%m') ORDER BY ym") as $row) {
            if (isset($monthIdx[$row->ym])) {
                $monthSoCounts[$monthIdx[$row->ym]] = (int) $row->total;
            }
        }
        foreach (DB::select("SELECT DATE_FORMAT(dn_date,'%Y-%m') ym, COUNT(*) total FROM delivery_notes WHERE dn_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(dn_date,'%Y-%m') ORDER BY ym") as $row) {
            if (isset($monthIdx[$row->ym])) {
                $monthSjCounts[$monthIdx[$row->ym]] = (int) $row->total;
            }
        }

        $dailyLabels = [];
        $dailySoCounts = [];
        $dailySjCounts = [];
        $dailyIdx = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dailyLabels[] = $date->format('d M');
            $dailySoCounts[] = 0;
            $dailySjCounts[] = 0;
            $dailyIdx[$date->toDateString()] = count($dailyLabels) - 1;
        }
        foreach (DB::select("SELECT so_date d, COUNT(*) total FROM sales_orders WHERE so_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY so_date ORDER BY so_date") as $row) {
            if (isset($dailyIdx[$row->d])) {
                $dailySoCounts[$dailyIdx[$row->d]] = (int) $row->total;
            }
        }
        foreach (DB::select("SELECT dn_date d, COUNT(*) total FROM delivery_notes WHERE dn_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY dn_date ORDER BY dn_date") as $row) {
            if (isset($dailyIdx[$row->d])) {
                $dailySjCounts[$dailyIdx[$row->d]] = (int) $row->total;
            }
        }

        $statusLabels = [];
        $statusCounts = [];
        $statusColors = ['#22c55e', '#06b6d4', '#f59e0b', '#ef4444', '#8b5cf6', '#3b82f6', '#64748b', '#14b8a6'];
        foreach (DB::table('sales_orders')
            ->selectRaw("COALESCE(status,'unknown') st, COUNT(*) total")
            ->whereMonth('so_date', $month)
            ->whereYear('so_date', $year)
            ->groupBy(DB::raw("COALESCE(status,'unknown')"))
            ->orderByDesc('total')
            ->get() as $row) {
            $statusLabels[] = $this->labelizeStatus($row->st);
            $statusCounts[] = (int) $row->total;
        }

        $totalOrdersMonth = array_sum($statusCounts);
        $deliveryRatePct = $totalOrdersMonth > 0 ? (int) round(($completedMonth / $totalOrdersMonth) * 100) : 0;
        $avgOrderWeek = count($dailySoCounts) ? round(array_sum($dailySoCounts) / count($dailySoCounts), 1) : 0;
        $avgShipWeek = count($dailySjCounts) ? round(array_sum($dailySjCounts) / count($dailySjCounts), 1) : 0;
        $peakOrderDay = '-';
        if ($dailySoCounts !== []) {
            $mx = max($dailySoCounts);
            $ix = array_search($mx, $dailySoCounts, true);
            if ($ix !== false && isset($dailyLabels[$ix])) {
                $peakOrderDay = $dailyLabels[$ix] . ' (' . (int) $mx . ' SO)';
            }
        }

        return view('tv.lobby', compact(
            'company',
            'companyName',
            'runningText',
            'pct',
            'soToday',
            'sjToday',
            'deliveryRatePct',
            'completedMonth',
            'totalOrdersMonth',
            'openSoCount',
            'spkMonth',
            'fgStockMonth',
            'peakOrderDay',
            'avgOrderWeek',
            'avgShipWeek',
            'recentSo',
            'monthLabels',
            'monthSoCounts',
            'monthSjCounts',
            'dailyLabels',
            'dailySoCounts',
            'dailySjCounts',
            'statusLabels',
            'statusCounts',
            'statusColors',
        ));
    }

    public function executive(Request $request): Response
    {
        $this->ensureFulfillmentSourceColumn();

        $company = app(MmsContext::class)->company();
        $companyName = (string) ($company->company_name ?: 'MMS EXECUTIVE TV');
        $runningText = trim((string) $company->running_text);
        if ($runningText === '') {
            $runningText = 'EXECUTIVE TV DASHBOARD MMS - MONITOR OVERALL BISNIS - SALES, PURCHASING, PRODUKSI, WAREHOUSE, FINANCE';
        }

        $m1 = now()->startOfMonth()->toDateString();
        $m2 = now()->startOfMonth()->addMonth()->toDateString();

        $k = [];
        $k['so_booking'] = (float) DB::table('sales_orders')->whereNotIn('status', ['cancelled', 'rejected'])->whereDate('so_date', '>=', $m1)->whereDate('so_date', '<', $m2)->sum('grand_total');
        $k['so_active'] = (int) DB::table('sales_orders')->whereIn('status', ['confirmed', 'in_production', 'delivered'])->count();
        $k['so_fg'] = (int) DB::table('sales_orders')->whereRaw("COALESCE(fulfillment_source,'spk')='fg_stock'")->whereNotIn('status', ['completed', 'cancelled', 'rejected'])->count();
        $k['inv_month'] = (float) DB::table('invoices')->where('status', '!=', 'cancelled')->whereDate('invoice_date', '>=', $m1)->whereDate('invoice_date', '<', $m2)->sum('grand_total');
        $k['col_month'] = (float) DB::table('invoice_payments')->whereDate('payment_date', '>=', $m1)->whereDate('payment_date', '<', $m2)->sum('amount');
        $k['ar_out'] = (float) DB::table('invoices')->whereIn('status', ['unpaid', 'partial'])->selectRaw('COALESCE(SUM(GREATEST(grand_total-paid_amount,0)),0) total')->value('total');
        $k['ap_out'] = (float) DB::table('supplier_bills')->whereIn('status', ['unpaid', 'partial'])->selectRaw('COALESCE(SUM(GREATEST(grand_total-paid_amount,0)),0) total')->value('total');
        $k['spk_active'] = (int) DB::table('spk')->whereIn('status', ['released', 'in_production'])->count();
        $k['spk_wait'] = (int) DB::table('spk')->whereIn('status', ['waiting_eng', 'preliminary', 'waiting_mgr', 'final'])->count();
        $k['prod_run'] = (int) DB::table('production_assignments')->where('status', 'in_progress')->count();
        $k['prod_out_today'] = (float) DB::table('production_assignments')->where('status', 'completed')->whereRaw('DATE(COALESCE(end_time, created_at))=CURDATE()')->sum('qty_good');
        $k['qc_ng7'] = (int) DB::table('qc_production')->where('status', 'ng')->whereRaw('qc_date>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)')->count();
        $k['pr_open'] = (int) DB::table('purchase_requests')->whereIn('status', ['submitted', 'approved', 'partial'])->count();
        $k['po_open'] = (int) DB::table('purchase_orders')->whereIn('status', ['submitted', 'approved_pm', 'approved_finance', 'sent'])->count();
        $k['po_month'] = (float) DB::table('purchase_orders')->where('status', '!=', 'cancelled')->whereDate('po_date', '>=', $m1)->whereDate('po_date', '<', $m2)->sum('grand_total');
        $k['dn_today'] = (int) DB::table('delivery_notes')->whereRaw('dn_date=CURDATE()')->count();
        $k['low_stock'] = (int) DB::table('items')->whereRaw('COALESCE(min_stock,0)>0 AND COALESCE(current_stock,0)>0 AND current_stock<=min_stock')->count();
        $k['neg_stock'] = (int) DB::table('items')->whereRaw('COALESCE(current_stock,0)<0')->count();
        $k['near_exp'] = (int) DB::table('warehouse_batches')->whereRaw('COALESCE(is_active,1)=1 AND COALESCE(qty_available,0)>0 AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)')->count();
        $k['cash_in'] = (float) DB::table('finance_cash_expenses')->where('transaction_type', 'income')->where('status', 'posted')->whereDate('expense_date', '>=', $m1)->whereDate('expense_date', '<', $m2)->sum('amount');
        $k['cash_out'] = (float) DB::table('finance_cash_expenses')->where('transaction_type', 'expense')->where('status', 'posted')->whereDate('expense_date', '>=', $m1)->whereDate('expense_date', '<', $m2)->sum('amount');
        $k['ar_overdue'] = (int) DB::table('invoices')->whereIn('status', ['unpaid', 'partial'])->whereRaw('due_date<CURDATE()')->count();
        $k['so_overdue'] = (int) DB::table('sales_orders')->whereNotIn('status', ['completed', 'cancelled', 'rejected'])->whereNotNull('delivery_date')->whereRaw('delivery_date<CURDATE()')->count();
        $k['po_overdue'] = (int) DB::table('purchase_orders')->whereIn('status', ['sent', 'approved_finance', 'approved_pm'])->whereRaw('delivery_date<CURDATE()')->count();
        $k['machine_down'] = (int) DB::table('machines')->where('status', 'broken')->count();
        $k['machine_maint'] = (int) DB::table('machines')->where('status', 'maintenance')->count();
        $k['machine_run'] = (int) DB::table('machines')->where('status', 'active')->count();
        $k['machine_total'] = (int) DB::table('machines')->count();

        $pulse = [
            'SO' => (int) DB::table('sales_orders')->whereRaw('so_date=CURDATE()')->count(),
            'QT' => (int) DB::table('quotations')->whereRaw('quote_date=CURDATE()')->count(),
            'INV' => (int) DB::table('invoices')->whereRaw('invoice_date=CURDATE()')->count(),
            'AR RCPT' => (int) DB::table('invoice_payments')->whereRaw('payment_date=CURDATE()')->count(),
            'PR' => (int) DB::table('purchase_requests')->whereRaw('pr_date=CURDATE()')->count(),
            'PO' => (int) DB::table('purchase_orders')->whereRaw('po_date=CURDATE()')->count(),
            'SJ' => (int) $k['dn_today'],
        ];

        $keys = [];
        $labels = [];
        for ($i = 5; $i >= 0; $i--) {
            $t = now()->subMonths($i);
            $keys[] = $t->format('Y-m');
            $labels[] = $t->format('M y');
        }
        $serSO = array_fill_keys($keys, 0.0);
        $serINV = array_fill_keys($keys, 0.0);
        $serCOL = array_fill_keys($keys, 0.0);

        foreach (DB::select("SELECT DATE_FORMAT(so_date,'%Y-%m') ym, SUM(grand_total) t FROM sales_orders WHERE status NOT IN ('cancelled','rejected') AND so_date>=DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(so_date,'%Y-%m')") as $r) {
            if (isset($serSO[$r->ym])) {
                $serSO[$r->ym] = (float) $r->t;
            }
        }
        foreach (DB::select("SELECT DATE_FORMAT(invoice_date,'%Y-%m') ym, SUM(grand_total) t FROM invoices WHERE status!='cancelled' AND invoice_date>=DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(invoice_date,'%Y-%m')") as $r) {
            if (isset($serINV[$r->ym])) {
                $serINV[$r->ym] = (float) $r->t;
            }
        }
        foreach (DB::select("SELECT DATE_FORMAT(payment_date,'%Y-%m') ym, SUM(amount) t FROM invoice_payments WHERE payment_date>=DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(payment_date,'%Y-%m')") as $r) {
            if (isset($serCOL[$r->ym])) {
                $serCOL[$r->ym] = (float) $r->t;
            }
        }

        $soDist = DB::table('sales_orders')->select('status', DB::raw('COUNT(*) cnt'))->groupBy('status')->get();
        $spkDist = DB::table('spk')->select('status', DB::raw('COUNT(*) cnt'))->groupBy('status')->get();
        $recentSO = DB::table('sales_orders')->selectRaw("so_number, so_date, status, COALESCE(fulfillment_source,'spk') fulfillment_source, grand_total")->orderByDesc('id')->limit(8)->get();
        $arDue = DB::table('invoices')->selectRaw('invoice_number, due_date, (grand_total-paid_amount) os')->whereIn('status', ['unpaid', 'partial'])->orderBy('due_date')->limit(8)->get();
        $opsAlerts = [
            ['SO Overdue Delivery', (int) $k['so_overdue'], 'danger'],
            ['AR Overdue', (int) $k['ar_overdue'], 'danger'],
            ['PO Overdue', (int) $k['po_overdue'], 'warn'],
            ['SPK Waiting Queue', (int) $k['spk_wait'], 'warn'],
            ['QC NG (7 Hari)', (int) $k['qc_ng7'], 'danger'],
            ['Low Stock', (int) $k['low_stock'], 'warn'],
            ['Stock Minus', (int) $k['neg_stock'], 'danger'],
            ['Near Expiry 30 Hari', (int) $k['near_exp'], 'warn'],
        ];

        return response()->view('tv.executive', [
            'company' => $company,
            'companyName' => $companyName,
            'runningText' => $runningText,
            'k' => $k,
            'pulse' => $pulse,
            'labels' => array_values($labels),
            'serSO' => array_values($serSO),
            'serINV' => array_values($serINV),
            'serCOL' => array_values($serCOL),
            'soDist' => $soDist,
            'spkDist' => $spkDist,
            'recentSO' => $recentSO,
            'arDue' => $arDue,
            'opsAlerts' => $opsAlerts,
        ]);
    }

    public function production(Request $request, MmsContext $context): Response
    {
        try {
            DB::statement("ALTER TABLE production_assignments ADD COLUMN IF NOT EXISTS machine_id INT NULL AFTER operator_id");
        } catch (\Throwable) {
            // ignore legacy compatibility DDL issues
        }

        $company = $context->company();
        $companyName = (string) ($company->company_name ?: 'PRODUCTION FLOOR');
        $runningText = trim((string) $company->running_text);
        if ($runningText === '') {
            $runningText = 'UTAMAKAN KESELAMATAN DAN KESEHATAN KERJA (K3) - SAFETY FIRST - GUNAKAN APD LENGKAP - JAGA KUALITAS DAN DISIPLIN PROSES';
        }

        $headerLogoMode = ! empty($company->logo_path) ? 'image' : 'icon';

        $outRow = (array) (DB::table('production_assignments')
            ->selectRaw("
                COALESCE(SUM(qty_good),0) AS good_qty,
                COALESCE(SUM(qty_reject),0) AS reject_qty,
                COUNT(*) AS task_done
            ")
            ->where('status', 'completed')
            ->whereRaw('DATE(COALESCE(end_time, created_at)) = CURDATE()')
            ->first() ?? []);
        $goodToday = (float) ($outRow['good_qty'] ?? 0);
        $rejectToday = (float) ($outRow['reject_qty'] ?? 0);
        $totalToday = $goodToday + $rejectToday;
        $yieldToday = $totalToday > 0 ? round(($goodToday / $totalToday) * 100, 1) : 100.0;
        $taskDoneToday = (int) ($outRow['task_done'] ?? 0);

        $assignDist = ['pending' => 0, 'assigned' => 0, 'in_progress' => 0, 'hold' => 0, 'completed' => 0];
        foreach (DB::table('production_assignments')
            ->select('status', DB::raw('COUNT(*) AS cnt'))
            ->groupBy('status')
            ->get() as $row) {
            $status = strtolower((string) $row->status);
            if (array_key_exists($status, $assignDist)) {
                $assignDist[$status] = (int) $row->cnt;
            }
        }

        $activeJobs = collect(DB::select("
            SELECT pa.id, pa.process_name, pa.status, pa.qty_input, pa.qty_good, pa.qty_reject,
                   pa.start_time, pa.created_at,
                   spk.spk_number, spk.project_name, spk.deadline_date, spk.priority,
                   u.fullname AS operator_name
            FROM production_assignments pa
            LEFT JOIN spk ON spk.id = pa.spk_id
            LEFT JOIN users u ON u.id = pa.operator_id
            WHERE pa.status IN ('in_progress','hold','assigned')
            ORDER BY FIELD(pa.status,'in_progress','hold','assigned'), COALESCE(pa.start_time, pa.created_at) ASC, pa.id ASC
            LIMIT 12
        "));

        $runningJobsMachineRows = collect(DB::select("
            SELECT pa.id, pa.machine_id, pa.process_name, pa.start_time, pa.created_at,
                   GREATEST(TIMESTAMPDIFF(SECOND, COALESCE(pa.start_time, pa.created_at), NOW()), 0) AS runtime_seconds,
                   spk.spk_number,
                   u.fullname AS operator_name
            FROM production_assignments pa
            LEFT JOIN spk ON spk.id = pa.spk_id
            LEFT JOIN users u ON u.id = pa.operator_id
            WHERE pa.status = 'in_progress'
            ORDER BY COALESCE(pa.start_time, pa.created_at) ASC, pa.id ASC
            LIMIT 200
        "))->map(fn ($row) => (array) $row)->all();

        $recentDoneJobs = collect(DB::select("
            SELECT pa.process_name, pa.qty_good, pa.qty_reject, pa.end_time,
                   spk.spk_number, spk.project_name,
                   u.fullname AS operator_name
            FROM production_assignments pa
            LEFT JOIN spk ON spk.id = pa.spk_id
            LEFT JOIN users u ON u.id = pa.operator_id
            WHERE pa.status='completed'
            ORDER BY COALESCE(pa.end_time, pa.created_at) DESC, pa.id DESC
            LIMIT 12
        "));

        $outputByProcessRows = collect(DB::select("
            SELECT COALESCE(process_name,'(Tanpa Proses)') AS process_name,
                   COALESCE(SUM(qty_good),0) AS good_qty,
                   COALESCE(SUM(qty_reject),0) AS reject_qty
            FROM production_assignments
            WHERE status='completed' AND DATE(COALESCE(end_time, created_at)) = CURDATE()
            GROUP BY process_name
            ORDER BY good_qty DESC, process_name ASC
            LIMIT 8
        "));

        $targetProcesses = ['Fibre Laser', 'CO Laser', 'Metal Bending', 'Acrylic Bending', 'Welding', 'Other'];
        $machines = DB::table('machines')
            ->whereIn('process_type', $targetProcesses)
            ->orderBy('process_type')
            ->orderBy('machine_name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $machineCounts = ['active' => 0, 'idle' => 0, 'maintenance' => 0, 'broken' => 0];
        $runningSlotsByBucket = array_fill_keys($targetProcesses, 0);
        $runningJobSlotsByMachineId = [];
        $runningJobSlotsByBucket = array_fill_keys($targetProcesses, []);

        foreach ($runningJobsMachineRows as $job) {
            $machineId = (int) ($job['machine_id'] ?? 0);
            $slot = [
                'process_name' => (string) ($job['process_name'] ?? ''),
                'spk_number' => (string) ($job['spk_number'] ?? ''),
                'operator_name' => (string) ($job['operator_name'] ?? ''),
                'start_at' => (string) (($job['start_time'] ?: $job['created_at']) ?: ''),
                'runtime_seconds' => max(0, (int) ($job['runtime_seconds'] ?? 0)),
            ];
            if ($machineId > 0) {
                $runningJobSlotsByMachineId[$machineId] ??= [];
                $runningJobSlotsByMachineId[$machineId][] = $slot;
                continue;
            }

            $bucket = $this->machineProcessBucket($job['process_name'] ?? '');
            $runningSlotsByBucket[$bucket] = (int) ($runningSlotsByBucket[$bucket] ?? 0) + 1;
            $runningJobSlotsByBucket[$bucket][] = $slot;
        }

        foreach ($machines as &$machine) {
            $masterStatus = strtolower((string) ($machine['status'] ?? 'active'));
            $displayStatus = 'idle';

            if ($masterStatus === 'broken') {
                $displayStatus = 'broken';
            } elseif ($masterStatus === 'maintenance') {
                $displayStatus = 'maintenance';
            } else {
                $machineId = (int) ($machine['id'] ?? 0);
                $directSlot = null;
                if ($machineId > 0 && ! empty($runningJobSlotsByMachineId[$machineId])) {
                    $directSlot = array_shift($runningJobSlotsByMachineId[$machineId]);
                }

                if (is_array($directSlot)) {
                    $displayStatus = 'active';
                    $machine['tv_running_process'] = (string) ($directSlot['process_name'] ?? '');
                    $machine['tv_running_spk'] = (string) ($directSlot['spk_number'] ?? '');
                    $machine['tv_running_operator'] = (string) ($directSlot['operator_name'] ?? '');
                    $machine['tv_running_start_at'] = (string) ($directSlot['start_at'] ?? '');
                    $machine['tv_running_runtime_seconds'] = (int) ($directSlot['runtime_seconds'] ?? 0);
                } else {
                    $bucket = $this->machineProcessBucket($machine['process_type'] ?? 'Other');
                    $quota = (int) ($runningSlotsByBucket[$bucket] ?? 0);
                    if ($quota > 0) {
                        $displayStatus = 'active';
                        $runningSlotsByBucket[$bucket] = $quota - 1;
                        $slot = ! empty($runningJobSlotsByBucket[$bucket]) ? array_shift($runningJobSlotsByBucket[$bucket]) : null;
                        if (is_array($slot)) {
                            $machine['tv_running_process'] = (string) ($slot['process_name'] ?? '');
                            $machine['tv_running_spk'] = (string) ($slot['spk_number'] ?? '');
                            $machine['tv_running_operator'] = (string) ($slot['operator_name'] ?? '');
                            $machine['tv_running_start_at'] = (string) ($slot['start_at'] ?? '');
                            $machine['tv_running_runtime_seconds'] = (int) ($slot['runtime_seconds'] ?? 0);
                        }
                    }
                }
            }

            $machine['tv_display_status'] = $displayStatus;
            if (isset($machineCounts[$displayStatus])) {
                $machineCounts[$displayStatus]++;
            }
        }
        unset($machine);

        $machineTotal = count($machines);
        $machineSlides = array_chunk($machines, 6);
        if ($machineSlides === []) {
            $machineSlides = [[]];
        }

        $qcTodayRow = (array) (DB::table('qc_production')
            ->selectRaw("
                COUNT(*) AS qc_count,
                SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS qc_ok_docs,
                SUM(CASE WHEN status='ng' THEN 1 ELSE 0 END) AS qc_ng_docs,
                COALESCE(SUM(qty_pass),0) AS qty_pass,
                COALESCE(SUM(qty_reject),0) AS qty_reject
            ")
            ->whereRaw('qc_date = CURDATE()')
            ->first() ?? []);
        $qcTodayCount = (int) ($qcTodayRow['qc_count'] ?? 0);
        $qcTodayOkDocs = (int) ($qcTodayRow['qc_ok_docs'] ?? 0);
        $qcTodayNgDocs = (int) ($qcTodayRow['qc_ng_docs'] ?? 0);
        $qcTodayPassQty = (float) ($qcTodayRow['qty_pass'] ?? 0);
        $qcTodayRejectQty = (float) ($qcTodayRow['qty_reject'] ?? 0);
        $spkActiveCount = (int) DB::table('spk')->whereIn('status', ['released', 'in_production'])->count();

        $qcLatestMap = [];
        foreach (DB::select("
            SELECT q1.*
            FROM qc_production q1
            JOIN (SELECT spk_id, MAX(id) AS max_id FROM qc_production GROUP BY spk_id) q2 ON q1.id = q2.max_id
        ") as $row) {
            $qcLatestMap[(int) $row->spk_id] = (array) $row;
        }

        $spkQcMonitor = collect(DB::select("
            SELECT id, spk_number, project_name, status, deadline_date, priority
            FROM spk
            WHERE status IN ('released','in_production','completed')
            ORDER BY FIELD(status,'completed','in_production','released'),
                     FIELD(priority,'urgent','normal'),
                     id DESC
            LIMIT 14
        "))->map(fn ($row) => (array) $row)->all();

        $qcQueueRows = [];
        $qcWaitingCount = 0;
        $qcNgFollowupCount = 0;
        foreach ($spkQcMonitor as $spk) {
            $spkId = (int) ($spk['id'] ?? 0);
            $spkStatus = strtolower((string) ($spk['status'] ?? ''));
            $qc = $qcLatestMap[$spkId] ?? null;
            $qcStatus = strtolower((string) ($qc['status'] ?? ''));

            if ($spkStatus === 'completed') {
                if ($qc === null || $qcStatus === '' || $qcStatus === 'draft') {
                    $label = 'MENUNGGU QC';
                    $tone = 'wait';
                    $qcWaitingCount++;
                } elseif ($qcStatus === 'ng') {
                    $label = 'QC NG - FOLLOW UP';
                    $tone = 'danger';
                    $qcNgFollowupCount++;
                } elseif ($qcStatus === 'completed') {
                    $label = 'QC SELESAI';
                    $tone = 'ok';
                } else {
                    $label = 'QC ' . strtoupper($qcStatus);
                    $tone = 'wait';
                }
            } elseif ($spkStatus === 'in_production') {
                $label = 'PROSES PRODUKSI';
                $tone = 'run';
                if (is_array($qc) && $qcStatus === 'ng') {
                    $label = 'PRODUKSI ULANG / CEK NG';
                    $tone = 'danger';
                    $qcNgFollowupCount++;
                }
            } else {
                $label = 'READY PRODUKSI';
                $tone = 'idle';
            }

            $qcQueueRows[] = [
                'spk_number' => (string) ($spk['spk_number'] ?? '-'),
                'project_name' => (string) ($spk['project_name'] ?? '-'),
                'deadline_date' => (string) ($spk['deadline_date'] ?? ''),
                'priority' => (string) ($spk['priority'] ?? 'normal'),
                'spk_status' => (string) ($spk['status'] ?? ''),
                'qc_label' => $label,
                'tone' => $tone,
            ];
        }

        $alertParts = [];
        $bgAlert = 'ok';
        if ($machineCounts['broken'] > 0) {
            $alertParts[] = 'MESIN DOWN: ' . $machineCounts['broken'];
            $bgAlert = 'danger';
        }
        if ($machineCounts['maintenance'] > 0) {
            $alertParts[] = 'MAINTENANCE: ' . $machineCounts['maintenance'];
            if ($bgAlert !== 'danger') {
                $bgAlert = 'warn';
            }
        }
        if ((int) $assignDist['in_progress'] <= 0) {
            $alertParts[] = 'TIDAK ADA JOB RUNNING';
            if ($bgAlert === 'ok') {
                $bgAlert = 'idle';
            }
        }
        if ($qcNgFollowupCount > 0) {
            $alertParts[] = 'QC NG FOLLOW UP: ' . $qcNgFollowupCount;
            $bgAlert = 'danger';
        }
        if ($qcWaitingCount > 0) {
            $alertParts[] = 'ANTRIAN QC: ' . $qcWaitingCount;
            if ($bgAlert === 'ok') {
                $bgAlert = 'warn';
            }
        }
        if ($alertParts === []) {
            $alertParts[] = 'PRODUKSI & QC BERJALAN NORMAL';
        }
        $statusText = implode(' | ', $alertParts);

        $procLabels = [];
        $procGood = [];
        $procNg = [];
        foreach ($outputByProcessRows as $row) {
            $procLabels[] = (string) $row->process_name;
            $procGood[] = (float) $row->good_qty;
            $procNg[] = (float) $row->reject_qty;
        }
        if ($procLabels === []) {
            $procLabels = ['Belum Ada Output'];
            $procGood = [0];
            $procNg = [0];
        }

        $assignChartLabels = ['Pending', 'Assigned', 'Running', 'Hold', 'Completed'];
        $assignChartValues = [
            (int) $assignDist['pending'],
            (int) $assignDist['assigned'],
            (int) $assignDist['in_progress'],
            (int) $assignDist['hold'],
            (int) $assignDist['completed'],
        ];
        $qcChartLabels = ['QC OK Today', 'QC NG Today'];
        $qcChartValues = [(int) $qcTodayOkDocs, (int) $qcTodayNgDocs];

        return response()->view('tv.production', compact(
            'company',
            'companyName',
            'runningText',
            'headerLogoMode',
            'goodToday',
            'rejectToday',
            'yieldToday',
            'taskDoneToday',
            'assignDist',
            'activeJobs',
            'recentDoneJobs',
            'machineCounts',
            'machineTotal',
            'machineSlides',
            'qcTodayCount',
            'qcTodayOkDocs',
            'qcTodayNgDocs',
            'qcTodayPassQty',
            'qcTodayRejectQty',
            'spkActiveCount',
            'qcQueueRows',
            'qcWaitingCount',
            'qcNgFollowupCount',
            'bgAlert',
            'statusText',
            'procLabels',
            'procGood',
            'procNg',
            'assignChartLabels',
            'assignChartValues',
            'qcChartLabels',
            'qcChartValues',
        ));
    }

    private function ensureFulfillmentSourceColumn(): void
    {
        try {
            DB::statement("ALTER TABLE sales_orders ADD COLUMN IF NOT EXISTS fulfillment_source VARCHAR(30) NULL AFTER status");
        } catch (\Throwable) {
            // ignore legacy compatibility DDL issues
        }
    }

    private function currentStage(array $row, array $taskRows = [], ?array $qcRow = null): string
    {
        $spkStatus = strtolower((string) ($row['spk_status'] ?? ''));
        $soStatus = strtolower((string) ($row['so_status'] ?? ''));
        $fulfillmentSource = strtolower((string) ($row['fulfillment_source'] ?? 'spk'));

        if (in_array($soStatus, ['draft', 'waiting_approval'], true)) {
            return 'DEPT SALES - MENUNGGU APPROVAL';
        }
        if ($soStatus === 'completed') {
            return 'SELESAI / CLOSED';
        }
        if ($soStatus === 'delivered') {
            return 'DEPT GUDANG - PENGIRIMAN';
        }
        if ($fulfillmentSource === 'fg_stock') {
            return 'DEPT GUDANG - FG STOCK / SIAP KIRIM';
        }
        if ($spkStatus === '') {
            return 'DEPT PPIC - MENUNGGU SPK';
        }
        if (in_array($spkStatus, ['draft', 'preliminary', 'waiting_eng'], true)) {
            return 'DEPT ENGINEERING - DRAWING & PARTLIST';
        }
        if (in_array($spkStatus, ['waiting_mgr', 'final'], true)) {
            return 'DEPT PPIC - APPROVAL';
        }
        if (in_array($spkStatus, ['released', 'in_production'], true)) {
            $proc = '';
            foreach ($taskRows as $task) {
                if (strtolower((string) ($task['status'] ?? '')) === 'in_progress') {
                    $proc = (string) ($task['process_name'] ?? '');
                    break;
                }
            }
            if ($proc === '') {
                foreach ($taskRows as $task) {
                    if (in_array(strtolower((string) ($task['status'] ?? '')), ['assigned', 'pending', 'hold'], true)) {
                        $proc = (string) ($task['process_name'] ?? '');
                        break;
                    }
                }
            }
            return $proc !== '' ? 'DEPT PRODUKSI - ' . $proc : 'DEPT PRODUKSI - PERSIAPAN';
        }
        if ($spkStatus === 'completed' || is_array($qcRow)) {
            return 'DEPT QC - INSPECTION QC PRODUKSI';
        }
        if ($spkStatus === 'closed') {
            return 'SELESAI / CLOSED';
        }

        return 'DEPT PPIC';
    }

    private function labelizeStatus(?string $status): string
    {
        $status = trim((string) $status);
        if ($status === '') {
            return '-';
        }

        return ucwords(str_replace('_', ' ', strtolower($status)));
    }

    private function stageBadgeClass(string $stageText): string
    {
        $text = strtolower($stageText);
        if (str_contains($text, 'closed') || str_contains($text, 'selesai')) {
            return 'success';
        }
        if (str_contains($text, 'qc')) {
            return 'warning';
        }
        if (str_contains($text, 'produksi')) {
            return 'info';
        }
        if (str_contains($text, 'gudang')) {
            return 'primary';
        }

        return 'secondary';
    }

    private function formatRupiahCompact(float|int $n): string
    {
        $n = (float) $n;
        $a = abs($n);
        if ($a >= 1000000000) {
            return 'Rp ' . number_format($n / 1000000000, 1, ',', '.') . ' M';
        }
        if ($a >= 1000000) {
            return 'Rp ' . number_format($n / 1000000, 1, ',', '.') . ' Jt';
        }

        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    private function formatNumberCompact(float|int $n): string
    {
        $n = (float) $n;
        $a = abs($n);
        if ($a >= 1000000) {
            return number_format($n / 1000000, 1, ',', '.') . 'M';
        }
        if ($a >= 1000) {
            return number_format($n / 1000, 1, ',', '.') . 'K';
        }

        return number_format($n, 0, ',', '.');
    }

    private function machineProcessBucket(?string $processName): string
    {
        $process = strtolower(trim((string) $processName));
        if ($process === '') {
            return 'Other';
        }
        if (str_contains($process, 'fibre laser') || str_contains($process, 'fiber laser')) {
            return 'Fibre Laser';
        }
        if (str_contains($process, 'co laser')) {
            return 'CO Laser';
        }
        if (str_contains($process, 'acrylic') && str_contains($process, 'bending')) {
            return 'Acrylic Bending';
        }
        if (str_contains($process, 'metal bending') || (str_contains($process, 'bending') && ! str_contains($process, 'acrylic'))) {
            return 'Metal Bending';
        }
        if (str_contains($process, 'welding') || preg_match('/\blas\b/', $process)) {
            return 'Welding';
        }

        return 'Other';
    }
}
