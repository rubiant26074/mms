<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WaLogController extends Controller
{
    public function index(Request $request): View
    {
        $limit = (int) $request->query('limit', 100);
        if (! in_array($limit, [50, 100, 200, 500], true)) {
            $limit = 100;
        }

        $tableExists = DB::selectOne("SHOW TABLES LIKE 'wa_message_logs'") !== null;
        $rows = collect();

        if ($tableExists) {
            $rows = DB::table('wa_message_logs as w')
                ->leftJoin('users as u', 'u.id', '=', 'w.created_by')
                ->select('w.*', 'u.fullname as created_by_name')
                ->when(in_array($request->query('status'), ['success', 'failed'], true), fn ($query) => $query->where('w.status', $request->query('status')))
                ->when($request->filled('date_from'), fn ($query) => $query->whereDate('w.created_at', '>=', $request->query('date_from')))
                ->when($request->filled('date_to'), fn ($query) => $query->whereDate('w.created_at', '<=', $request->query('date_to')))
                ->orderByDesc('w.id')
                ->limit($limit)
                ->get();
        }

        return view('admin.wa_logs.index', compact('tableExists', 'rows', 'limit'));
    }
}
