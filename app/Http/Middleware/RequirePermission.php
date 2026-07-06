<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->loadMissing('role.permissions')->hasPermission($permission)) {
            abort(403, 'Akses Ditolak');
        }

        return $next($request);
    }
}
