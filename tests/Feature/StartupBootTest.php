<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use App\Services\WhatsApp\OpenWaManager;
use Tests\TestCase;

/**
 * Mobile startup contract: the WhatsApp gateway launch dance (health probes,
 * node spawn, port waits, session POSTs) must never run inside boot() — on
 * the device it blocked the first page behind up to ~15-30s of gateway
 * startup. It runs once, after the first response flushes.
 */
class StartupBootTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('NATIVEPHP_RUNNING');

        parent::tearDown();
    }

    public function test_mobile_boot_does_not_block_on_gateway_startup(): void
    {
        config(['services.openwa.auto_start' => true]);
        putenv('NATIVEPHP_RUNNING=true');

        $spy = new class extends OpenWaManager
        {
            public array $calls = [];

            public function ensureStarted(): bool
            {
                $this->calls[] = 'ensureStarted';

                return true;
            }

            public function stop(): void
            {
                $this->calls[] = 'stop';
            }
        };
        $this->app->instance(OpenWaManager::class, $spy);

        // The provider boots standalone: the gateway dance must NOT run here.
        (new AppServiceProvider($this->app))->boot();
        $this->assertSame([], $spy->calls, 'boot() blocked on gateway startup');

        // It runs once, after the first response flushes (terminate).
        $this->app->terminate();
        $this->assertSame(['ensureStarted'], $spy->calls);

        // The once-guard keeps later requests from re-probing the gateway.
        $this->app->terminate();
        $this->assertSame(['ensureStarted'], $spy->calls);
    }
}
