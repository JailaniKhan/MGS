<?php

use App\Services\WhatsApp\OpenWaManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    if (!config('services.openwa.auto_start', false)) {
        return;
    }

    // Failure backoff: if the gateway has failed to start on consecutive health
    // checks, exponentially back off launch attempts so we don't fork a new
    // node process every minute forever on a broken installation. The cache
    // key stores the number of consecutive failures. We reset it whenever the
    // gateway successfully responds.
    //
    // 0   = first try, or last attempt succeeded
    //       -> try immediately
    // 1-3 = a few consecutive failures
    //       -> try every minute (so we recover automatically from transient issues)
    // 4-9 = many failures — likely a real install problem (broken binary,
    //       missing libs, path mismatch). Back off to every ~5 minutes.
    // 10+ = give up until user manually restarts from the settings page.
    // Wrap every cache touch in a safe() helper so a database-down scenario
    // cannot recurse-fail inside the scheduler (CACHE_STORE=database).
    $safe = static function (callable $fn, mixed $fallback = null): mixed {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return $fallback;
        }
    };

    $failures = (int) $safe(fn() => Cache::get('openwa:health:failures', 0), 0);

    if ($failures >= 10) {
        return;
    }

    try {
        $manager = app(OpenWaManager::class);

        if (!$manager->isRunning()) {
            // Apply backoff: after 4+ consecutive failures, only attempt a
            // restart every 5 minutes (skip the other 4 ticks out of 5).
            if ($failures >= 4 && (now()->minute % 5) !== 0) {
                return;
            }

            Log::warning('OpenWA health-check: gateway down, restarting', [
                'consecutive_failures' => $failures,
            ]);
            $started = $manager->start();
            if (!$started) {
                $safe(fn() => Cache::increment('openwa:health:failures'));
                Log::error('OpenWA health-check: start failed', [
                    'consecutive_failures' => $failures + 1,
                ]);
                return;
            }

            $safe(fn() => Cache::forget('openwa:health:failures'));
            return;
        }

        // Gateway is up — clear the failure counter and make sure the
        // WhatsApp session is also active.
        if ($failures > 0) {
            $safe(fn() => Cache::forget('openwa:health:failures'));
        }

        $openWa = app(\App\Services\WhatsApp\OpenWaService::class);
        $status = $openWa->sessionStatus();

        if ($status && ($status['status'] ?? null) === 'disconnected') {
            Log::info('OpenWA health-check: session disconnected, starting');
            $manager->startSession();
        }
    } catch (\Throwable $e) {
        $safe(fn() => Cache::increment('openwa:health:failures'));
        Log::error('OpenWA health-check error', [
            'error' => $e->getMessage(),
            'consecutive_failures' => $failures + 1,
        ]);
    }
})->everyMinute()->name('openwa-health-check');