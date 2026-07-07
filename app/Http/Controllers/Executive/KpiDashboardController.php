<?php

namespace App\Http\Controllers\Executive;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KpiDashboardController extends Controller
{
    public function index(): View
    {
        $totalSales = (float) DB::table('invoices')
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->sum('grand_total');

        $totalPurchases = (float) DB::table('purchase_orders')
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->sum('grand_total');

        $activeSpk = (int) DB::table('spk')
            ->whereIn('status', ['released', 'in_production'])
            ->count();

        $totalUsers = (int) DB::table('users')
            ->where('role_id', '!=', 1)
            ->count();

        $salesTrend = DB::table('invoices')
            ->selectRaw("DATE_FORMAT(invoice_date, '%M %Y') as month_label, DATE_FORMAT(invoice_date, '%Y-%m') as month_key, SUM(grand_total) as total")
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->whereRaw('invoice_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)')
            ->groupByRaw("DATE_FORMAT(invoice_date, '%Y-%m'), DATE_FORMAT(invoice_date, '%M %Y')")
            ->orderBy('month_key')
            ->get();

        $spkStats = DB::table('spk')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $topProducts = DB::table('sales_order_items as soi')
            ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->join('items as i', 'i.id', '=', 'soi.item_id')
            ->selectRaw('i.item_name, SUM(soi.qty) as total_sold')
            ->where('so.status', '!=', 'cancelled')
            ->groupBy('i.id', 'i.item_name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        $recentLogs = DB::table('system_logs')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('executive.kpi.index', [
            'totalSales' => $totalSales,
            'totalPurchases' => $totalPurchases,
            'activeSpk' => $activeSpk,
            'totalUsers' => $totalUsers,
            'salesLabels' => $salesTrend->pluck('month_label')->all(),
            'salesValues' => $salesTrend->pluck('total')->map(fn ($value) => (float) $value)->all(),
            'spkDraft' => (int) ($spkStats['draft'] ?? 0),
            'spkProcess' => (int) (($spkStats['released'] ?? 0) + ($spkStats['in_production'] ?? 0) + ($spkStats['waiting_eng'] ?? 0) + ($spkStats['waiting_mgr'] ?? 0)),
            'spkDone' => (int) (($spkStats['completed'] ?? 0) + ($spkStats['closed'] ?? 0)),
            'topProductLabels' => $topProducts->pluck('item_name')->all(),
            'topProductValues' => $topProducts->pluck('total_sold')->map(fn ($value) => (float) $value)->all(),
            'recentLogs' => $recentLogs,
        ]);
    }
}
