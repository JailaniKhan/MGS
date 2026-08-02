<?php

namespace App\Providers;

use App\Console\Commands\OpenWaBundle;
use App\Console\Commands\OpenWaInstall;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\WhatsApp\OpenWaManager;
use App\Services\WhatsApp\OpenWaService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenWaService::class);
        $this->app->singleton(WhatsAppService::class, fn($app) => new WhatsAppService($app->make(OpenWaService::class)));
        $this->app->singleton(OpenWaManager::class);
    }

    public function boot(): void
    {
        Relation::morphMap([
            'customer' => Customer::class,
            'supplier' => Supplier::class,
        ]);

        Customer::resolveRelationUsing('reminders', function ($model) {
            return $model->morphMany(\App\Models\Reminder::class, 'remindable');
        });

        Supplier::resolveRelationUsing('reminders', function ($model) {
            return $model->morphMany(\App\Models\Reminder::class, 'remindable');
        });

        $this->registerCommands();

        if (config('services.openwa.auto_start', false)) {
            $this->bootOpenwa();
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                OpenWaInstall::class,
                OpenWaBundle::class,
            ]);
        }
    }

    protected function bootOpenwa(): void
    {
        $manager = $this->app->make(OpenWaManager::class);

        // Detect NativePHP mobile runtime. We check three signals and treat
        // any one as authoritative:
        //   1. config('nativephp-internal.running') — the canonical flag
        //      set by NativePHP's C++ bridge via setenv("NATIVEPHP_RUNNING", "true")
        //      which the published config file then surfaces.
        //   2. getenv('NATIVEPHP_RUNNING') — same signal, read directly in
        //      case the config isn't published yet (early boot, installer, etc).
        //   3. config('nativephp-internal.platform') in ['android','ios'] or
        //      getenv('NATIVEPHP_PLATFORM') ditto.
        $isMobile =
            config('nativephp-internal.running', false)
            || getenv('NATIVEPHP_RUNNING') === 'true'
            || in_array(config('nativephp-internal.platform') ?: getenv('NATIVEPHP_PLATFORM'), ['android', 'ios'], true);

        // Only auto-start the gateway in the NativePHP persistent runtime,
        // where this boot() runs ONCE for the whole app session. On a web
        // dev server (php artisan serve) boot() runs PER request, so calling
        // ensureStarted() here blocked every page load on gateway HTTP
        // probes (and possible relaunch). On web the gateway is started
        // on demand by SettingsController::openwa() instead.
        if ($isMobile) {
            $manager->ensureStarted();

            // Mobile (NativePHP persistent runtime): the PHP process lives for
            // the entire app session. Register a shutdown handler so the gateway
            // is stopped cleanly when the user closes the app — otherwise the
            // node process keeps running in the background on Android.
            //
            // CAVEAT: register_shutdown_function does NOT fire when the app is
            // force-stopped / SIGKILLed / OOM-killed, which Android does under
            // memory pressure. For graceful teardown under force-stop, a future
            // improvement would use NativePHP's native lifecycle hooks
            // (onDestroy / onPause in the Activity) — but PHP can't reach those
            // directly without a Kotlin bridge, so this is the best we can do.
            register_shutdown_function(function () use ($manager) {
                $manager->stop();
            });
        }
    }
}
