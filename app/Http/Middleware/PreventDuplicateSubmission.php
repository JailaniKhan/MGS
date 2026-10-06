<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replay protection for state-changing form submissions.
 *
 * Every state-changing form carries a hidden `_form_id` token (injected by
 * resources/js/ui.js — unique per render). The first request that presents a
 * token consumes it; any later request presenting the same token is answered
 * without running the route again.
 *
 * Two real-world sources of duplicate rows this prevents:
 *
 *  - A double tap / double-fired submit on a create form (two customers with
 *    the same name, a second order, etc.).
 *  - The NativePHP Android shell replaying a captured POST body while the
 *    WebView navigates after the form was submitted more than once.
 *
 * Requests without a token (JSON/fetch callers, API routes, artisan-driven
 * posts) pass through untouched, so this middleware never changes the
 * behaviour of non-form traffic.
 */
class PreventDuplicateSubmission
{
    /** How long a token stays "used" — long enough to cover a slow device retry. */
    private const TTL_SECONDS = 120;

    /**
     * Tokens longer than this are not tokens: accept the request and let the
     * normal validation layer deal with the payload.
     */
    private const MAX_TOKEN_LENGTH = 64;

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $token = $request->input('_form_id');

        if (! is_string($token) || $token === '' || strlen($token) > self::MAX_TOKEN_LENGTH) {
            return $next($request);
        }

        // Scope by user so one shop's token can never consume another's.
        $key = 'form-token:'.($request->user()?->getAuthIdentifier() ?? 'guest').':'.sha1($token);

        if (! Cache::add($key, true, self::TTL_SECONDS)) {
            return $request->expectsJson()
                ? response()->json([
                    'ok' => false,
                    'duplicate' => true,
                    'message' => __('messages.duplicate_submission'),
                ], 409)
                : redirect()->back()->with('error', __('messages.duplicate_submission'));
        }

        return $next($request);
    }
}
