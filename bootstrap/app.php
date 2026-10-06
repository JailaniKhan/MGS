<?php

use App\Http\Middleware\AppLockMiddleware;
use App\Http\Middleware\PreventDuplicateSubmission;
use App\Http\Middleware\SetCacheHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

require __DIR__.'/../app/Support/helpers.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetCacheHeaders::class,
            SetLocale::class,
            AppLockMiddleware::class,
            // Replay guard for state-changing forms — see the class docblock
            // (double taps / NativePHP body replays used to double-INSERT).
            PreventDuplicateSubmission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Standard Laravel behaviour: honour the client's Accept header
        // (fetch/XHR callers expect 422 JSON), always render JSON for api/*.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
