<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Supplier;
use App\Services\WhatsApp\OpenWaService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OpenWaService::class);
        $this->app->singleton(WhatsAppService::class, fn($app) => new WhatsAppService($app->make(OpenWaService::class)));
    }

    /**
     * Bootstrap any application services.
     */
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
    }
}
