<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MmsContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(MmsContext $context): View
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing('role.permissions');
        $context->syncLegacySession($user);

        $roleSlug = $user->role?->role_slug ?? 'default';

        // Determinisasikan tipe dashboard
        $dashboardType = 'default';
        if ($roleSlug === 'admin') {
            $dashboardType = 'admin';
        } elseif (in_array($roleSlug, ['sal_mkt', 'sales_staff', 'sales_manager'], true)) {
            $dashboardType = 'sales';
        } elseif ($roleSlug === 'hrd') {
            $dashboardType = 'hrd';
        } elseif ($roleSlug === 'engineering') {
            $dashboardType = 'engineering';
        } elseif ($roleSlug === 'ppic') {
            $dashboardType = 'ppic';
        } elseif ($roleSlug === 'warehouse') {
            $dashboardType = 'warehouse';
        } elseif (in_array($roleSlug, ['qc', 'spv_qc'], true)) {
            $dashboardType = 'qc';
        } elseif ($roleSlug === 'purchasing') {
            $dashboardType = 'procurement';
        } elseif (in_array($roleSlug, ['supervisor', 'operator'], true) || str_starts_with($roleSlug, 'op_') || str_starts_with($roleSlug, 'op_msn_')) {
            $dashboardType = 'production';
        } elseif ($roleSlug === 'finacc') {
            $dashboardType = 'finacc';
        } elseif (in_array($roleSlug, ['manager', 'owner'], true)) {
            $dashboardType = 'manager';
        }

        // Siapkan stats dasar (agar view tidak error jika memanggil stats lama)
        $stats = [
            'users' => $this->safeCount('users'),
            'roles' => $this->safeCount('roles'),
            'sales_orders' => $this->safeCount('sales_orders'),
            'spk_active' => $this->safeCount('spk', function ($query) {
                return $query->whereIn('status', ['released', 'in_production']);
            }),
        ];

        // Custom stats berdasarkan tipe dashboard
        $roleStats = [];
        switch ($dashboardType) {
            case 'admin':
                $roleStats = [
                    'logs_count' => $this->safeCount('system_logs'),
                    'wa_logs_today' => $this->safeCount('wa_logs', function ($q) {
                        return $q->whereDate('created_at', today());
                    }),
                ];
                break;
            case 'sales':
                $roleStats = [
                    'pending_quotations' => $this->safeCount('quotations', function ($q) {
                        return $q->where('status', 'draft');
                    }),
                    'new_customers' => $this->safeCount('customers', function ($q) {
                        return $q->whereMonth('created_at', now()->month);
                    }),
                    'total_revenue' => $this->safeSum('sales_orders', 'grand_total', function ($q) {
                        return $q->whereNotIn('status', ['cancelled', 'rejected']);
                    }),
                ];
                break;
            case 'hrd':
                $roleStats = [
                    'active_employees' => $this->safeCount('employees', function ($q) {
                        return Schema::hasColumn('employees', 'is_active') ? $q->where('is_active', 1) : $q;
                    }),
                    'attendance_today' => $this->safeCount('attendance', function ($q) {
                        $dateCol = Schema::hasColumn('attendance', 'attendance_date') ? 'attendance_date' : 'created_at';
                        return $q->whereDate($dateCol, today());
                    }),
                    'pending_payroll' => $this->safeCount('payrolls', function ($q) {
                        return $q->where('status', 'draft');
                    }),
                ];
                break;
            case 'engineering':
                $roleStats = [
                    'total_items' => $this->safeCount('items'),
                    'active_boms' => $this->safeCount('boms', function ($q) {
                        return Schema::hasColumn('boms', 'is_active') ? $q->where('is_active', 1) : $q;
                    }),
                    'active_machines' => $this->safeCount('machines', function ($q) {
                        return Schema::hasColumn('machines', 'status') ? $q->where('status', 'active') : $q;
                    }),
                ];
                break;
            case 'ppic':
                $roleStats = [
                    'low_stock_items' => $this->safeCount('items', function ($q) {
                        return Schema::hasColumns('items', ['current_stock', 'min_stock']) ? $q->whereRaw('current_stock <= min_stock') : $q;
                    }),
                    'pending_pr' => $this->safeCount('purchase_requests', function ($q) {
                        return $q->where('status', 'pending');
                    }),
                ];
                break;
            case 'production':
                $roleStats = [
                    'running_assignments' => $this->safeCount('production_assignments', function ($q) {
                        return $q->where('status', 'running');
                    }),
                    'completed_today' => $this->safeCount('production_assignments', function ($q) {
                        return $q->where('status', 'completed')->whereDate('created_at', today());
                    }),
                ];
                break;
            case 'procurement':
                $roleStats = [
                    'open_rfq' => $this->safeCount('rfqs', function ($q) {
                        return $q->where('status', 'open');
                    }),
                    'pending_po' => $this->safeCount('purchase_orders', function ($q) {
                        return $q->where('status', 'pending');
                    }),
                    'total_purchase_spend' => $this->safeSum('purchase_orders', 'grand_total', function ($q) {
                        return $q->whereNotIn('status', ['cancelled']);
                    }),
                ];
                break;
            case 'warehouse':
                $roleStats = [
                    'pending_receipts' => $this->safeCount('goods_receipts', function ($q) {
                        return $q->where('status', 'pending');
                    }),
                    'pending_issues' => $this->safeCount('material_issues', function ($q) {
                        return $q->where('status', 'pending');
                    }),
                    'sj_today' => $this->safeCount('delivery_notes', function ($q) {
                        return $q->whereDate('created_at', today());
                    }),
                ];
                break;
            case 'qc':
                $roleStats = [
                    'qc_inspections_today' => $this->safeCount('qc_incoming', function ($q) {
                        return $q->whereDate('created_at', today());
                    }),
                    'open_ncr' => $this->safeCount('ncr', function ($q) {
                        return $q->where('status', 'open');
                    }),
                ];
                break;
            case 'finacc':
                $roleStats = [
                    'cash_balance' => $this->safeSum('coa', 'current_balance', function ($q) {
                        return $q->where('account_type', 'asset')->where(function ($sub) {
                            $sub->where('account_name', 'like', '%kas%')->orWhere('account_name', 'like', '%bank%');
                        });
                    }),
                    'unposted_journals' => $this->safeCount('journals', function ($q) {
                        return $q->where('status', 'draft');
                    }),
                ];
                break;
        }

        // Data chart default untuk SPK & Sales Order (untuk fallback agar tidak crash)
        $spkData = ['draft' => 0, 'process' => $stats['spk_active'], 'completed' => 0];
        try {
            $spkStats = DB::table('spk')->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
            $spkData = [
                'draft' => (int) ($spkStats['draft'] ?? 0),
                'process' => (int) (($spkStats['released'] ?? 0) + ($spkStats['in_production'] ?? 0) + ($spkStats['waiting_eng'] ?? 0) + ($spkStats['waiting_mgr'] ?? 0)),
                'completed' => (int) (($spkStats['completed'] ?? 0) + ($spkStats['closed'] ?? 0)),
            ];
        } catch (\Exception $e) {}

        $salesLabels = [now()->subMonths(2)->translatedFormat('F Y'), now()->subMonths(1)->translatedFormat('F Y'), now()->translatedFormat('F Y')];
        $salesValues = [0, 0, 0];
        try {
            $salesMonthly = DB::select("
                SELECT DATE_FORMAT(so_date, '%M %Y') as month_label, DATE_FORMAT(so_date, '%Y-%m') as month_key, COUNT(*) as total 
                FROM sales_orders 
                WHERE so_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
                GROUP BY DATE_FORMAT(so_date, '%Y-%m'), DATE_FORMAT(so_date, '%M %Y') 
                ORDER BY month_key
            ");
            if (!empty($salesMonthly)) {
                $salesLabels = collect($salesMonthly)->pluck('month_label')->all();
                $salesValues = collect($salesMonthly)->pluck('total')->map(fn($v) => (int) $v)->all();
            }
        } catch (\Exception $e) {}

        return view('dashboard.index', [
            'user' => $user,
            'company' => $context->company(),
            'stats' => $stats,
            'roleStats' => $roleStats,
            'dashboardType' => $dashboardType,
            'spkData' => $spkData,
            'salesLabels' => $salesLabels,
            'salesValues' => $salesValues,
        ]);
    }

    private function safeCount(string $table, ?\Closure $queryModifier = null): int
    {
        try {
            if (! Schema::hasTable($table)) {
                return 0;
            }
            $query = DB::table($table);
            if ($queryModifier) {
                $query = $queryModifier($query);
            }
            return (int) $query->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function safeSum(string $table, string $column, ?\Closure $queryModifier = null): float
    {
        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                return 0;
            }
            $query = DB::table($table);
            if ($queryModifier) {
                $query = $queryModifier($query);
            }
            return (float) $query->sum($column);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
