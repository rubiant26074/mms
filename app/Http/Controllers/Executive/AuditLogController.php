<?php

namespace App\Http\Controllers\Executive;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $module = trim((string) $request->query('module', ''));
        $search = trim((string) $request->query('search', ''));

        $logs = DB::table('system_logs')
            ->when($module !== '', fn ($query) => $query->where('module', $module))
            ->when($search !== '', function ($query) use ($search): void {
                $term = "%{$search}%";
                $query->where(function ($sub) use ($term): void {
                    $sub->where('user_name', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('executive.logs.index', compact('logs', 'module', 'search'));
    }
}
