<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds far-future cache headers to hashed Vite build assets (/build/assets/…).
 * These filenames are content-hash-versioned, so they can be cached forever;
 * the manifest/entry points are excluded so fresh builds are picked up.
 */
class SetCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $path = $request->path();
        if (preg_match('#^build/assets/.+\.(css|js|woff2?|ttf|otf|eot|svg|png|jpe?g|gif|webp|avif)$#', $path)) {
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
        }

        return $response;
    }
}
