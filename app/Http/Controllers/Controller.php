<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Get filter value from request query or remember/retrieve from session per route.
     */
    protected function rememberedFilter(Request $request, string $param, string $default = ''): string
    {
        $route = $request->route()?->getName() ?: $request->path();
        $sessionKey = 'mms_filter_' . md5((string) $route) . '_' . $param;

        if ($request->has('reset') || $request->has('reset_filter')) {
            session()->forget($sessionKey);

            return $default;
        }

        if ($request->has($param)) {
            $val = trim((string) $request->query($param, ''));
            session()->put($sessionKey, $val);

            return $val;
        }

        return (string) session()->get($sessionKey, $default);
    }
}
