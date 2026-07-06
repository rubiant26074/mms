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

        return view('dashboard.index', [
            'user' => $user,
            'company' => $context->company(),
            'stats' => [
                'users' => DB::table('users')->count(),
                'roles' => DB::table('roles')->count(),
                'sales_orders' => DB::table('sales_orders')->count(),
                'spk_active' => DB::table('spk')->whereIn('status', ['released', 'in_production'])->count(),
            ],
        ]);
    }
}
