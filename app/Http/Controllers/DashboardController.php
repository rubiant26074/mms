<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MmsContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(MmsContext $context): View
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing('role.permissions');
        $context->syncLegacySession($user);

        // Fetch SPK status stats
        $spkStats = DB::table('spk')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $spkData = [
            'draft' => (int) ($spkStats['draft'] ?? 0),
            'process' => (int) (($spkStats['released'] ?? 0) + ($spkStats['in_production'] ?? 0) + ($spkStats['waiting_eng'] ?? 0) + ($spkStats['waiting_mgr'] ?? 0)),
            'completed' => (int) (($spkStats['completed'] ?? 0) + ($spkStats['closed'] ?? 0)),
        ];

        // Fetch Sales Order monthly trend (last 6 months)
        $salesMonthly = DB::select("
            SELECT DATE_FORMAT(so_date, '%M %Y') as month_label, DATE_FORMAT(so_date, '%Y-%m') as month_key, COUNT(*) as total 
            FROM sales_orders 
            WHERE so_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
            GROUP BY DATE_FORMAT(so_date, '%Y-%m'), DATE_FORMAT(so_date, '%M %Y') 
            ORDER BY month_key
        ");

        $salesLabels = collect($salesMonthly)->pluck('month_label')->all();
        $salesValues = collect($salesMonthly)->pluck('total')->map(fn($v) => (int) $v)->all();

        // Fallbacks if data is empty
        if (empty($salesLabels)) {
            $salesLabels = [now()->subMonths(2)->translatedFormat('F Y'), now()->subMonths(1)->translatedFormat('F Y'), now()->translatedFormat('F Y')];
            $salesValues = [0, 0, 0];
        }

        return view('dashboard.index', [
            'user' => $user,
            'company' => $context->company(),
            'stats' => [
                'users' => DB::table('users')->count(),
                'roles' => DB::table('roles')->count(),
                'sales_orders' => DB::table('sales_orders')->count(),
                'spk_active' => DB::table('spk')->whereIn('status', ['released', 'in_production'])->count(),
            ],
            'spkData' => $spkData,
            'salesLabels' => $salesLabels,
            'salesValues' => $salesValues,
        ]);
    }
}
