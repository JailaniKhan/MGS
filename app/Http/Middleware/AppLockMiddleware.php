<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

class AppLockMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $pinEnabled = Setting::get('pin_lock_enabled', '0');

        if ($pinEnabled === '1' && ! session('pin_verified')) {
            // Allow the lock screen and verification endpoint only - no settings access while locked.
            if ($request->routeIs('app-lock.lock') || $request->routeIs('app-lock.verify-pin')
                || $request->routeIs('login.*') || $request->routeIs('register')) {
                return $next($request);
            }

            return redirect()->route('app-lock.lock');
        }

        return $next($request);
    }
}
