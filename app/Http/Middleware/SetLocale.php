<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // A guest's session choice wins; the shop-wide setting is the default.
        $locale = session('locale') ?? Setting::get('language', 'ps');
        if (in_array($locale, ['en', 'ps', 'fa'])) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
