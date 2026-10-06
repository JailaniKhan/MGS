<?php

namespace Tests\Feature;

use App\Services\WhatsApp\OpenWaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenWaIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Offline tests always use a fake gateway. Live tests explicitly
        // re-load the real .env values via loadRealOpenWaConfig().
        config([
            'services.openwa.base_url' => 'http://openwa.test',
            'services.openwa.api_key' => 'test-api-key',
            'services.openwa.session' => 'default',
        ]);
    }

    /**
     * Re-load the real OpenWA credentials from .env for live tests.
     */
    protected function loadRealOpenWaConfig(): void
    {
        config([
            'services.openwa.base_url' => env('OPENWA_BASE_URL'),
            'services.openwa.api_key' => env('OPENWA_API_KEY'),
            'services.openwa.session' => env('OPENWA_SESSION'),
            'services.openwa.webhook_secret' => env('OPENWA_WEBHOOK_SECRET'),
            'services.openwa.test_chat_id' => env('OPENWA_TEST_CHAT_ID'),
        ]);
    }

    public function test_is_configured_returns_true_with_api_key(): void
    {
        $service = new OpenWaService;
        $this->assertTrue($service->isConfigured());
    }

    public function test_is_not_configured_without_api_key(): void
    {
        config(['services.openwa.api_key' => null]);
        $service = new OpenWaService;
        $this->assertFalse($service->isConfigured());
    }

    public function test_to_chat_id_formats_afghan_numbers(): void
    {
        $service = new OpenWaService;

        // +93 300 123 4567 -> country 93 + local 3001234567
        $this->assertSame('933001234567@c.us', $service->toChatId('+93 300 123 4567'));
        // 03001234567 -> strip leading 0 -> 3001234567 -> 93 + local
        $this->assertSame('933001234567@c.us', $service->toChatId('03001234567'));
        // 93001234567 -> strip country 93 -> 001234567 -> 93 + 001234567
        $this->assertSame('93001234567@c.us', $service->toChatId('93001234567'));
    }

    public function test_send_text_message_hits_openwa_endpoint(): void
    {
        Http::fake([
            'openwa.test/api/sessions/default/messages/send-text' => Http::response([
                'id' => 'abc123',
                'status' => 'sent',
            ], 200),
        ]);

        $service = new OpenWaService;
        $result = $service->send('03001234567', 'Hello from MGS');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-API-Key', 'test-api-key')
                && $request->url() === 'http://openwa.test/api/sessions/default/messages/send-text'
                && $request['chatId'] === '933001234567@c.us'
                && $request['text'] === 'Hello from MGS';
        });
    }

    public function test_send_returns_false_on_provider_failure(): void
    {
        Http::fake([
            'openwa.test/api/sessions/default/messages/send-text' => Http::response(['error' => 'boom'], 500),
        ]);

        $service = new OpenWaService;
        $this->assertFalse($service->send('03001234567', 'test'));
    }

    public function test_send_skipped_when_not_configured(): void
    {
        config(['services.openwa.api_key' => null]);

        Http::fake();
        $service = new OpenWaService;
        $this->assertFalse($service->send('03001234567', 'test'));

        Http::assertNothingSent();
    }

    public function test_register_webhook_posts_to_openwa(): void
    {
        Http::fake([
            'openwa.test/api/sessions/default/webhooks' => Http::response(['id' => 'wh_1'], 200),
        ]);

        $service = new OpenWaService;
        $ok = $service->registerWebhook(
            'https://mgs.test/api/webhooks/openwa',
            ['message.received'],
            'hmac-secret'
        );

        $this->assertTrue($ok);
        Http::assertSent(function ($request) {
            return $request['url'] === 'https://mgs.test/api/webhooks/openwa'
                && $request['events'] === ['message.received']
                && $request['secret'] === 'hmac-secret';
        });
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['services.openwa.webhook_secret' => 'hmac-secret']);

        $this->postJson('/api/webhooks/openwa', ['event' => 'message.received'])
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid signature']);
    }

    public function test_webhook_accepts_valid_signature(): void
    {
        config(['services.openwa.webhook_secret' => 'hmac-secret']);

        $payload = ['event' => 'message.received', 'data' => ['body' => 'hi']];
        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'hmac-secret');

        $this->withHeader('X-OpenWA-Signature', $signature)
            ->postJson('/api/webhooks/openwa', $payload)
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }

    public function test_webhook_rejects_when_secret_missing(): void
    {
        // Fail-closed: when no webhook secret is configured, the endpoint
        // must not accept unsigned requests (would allow forged events).
        config(['services.openwa.webhook_secret' => null]);

        $this->postJson('/api/webhooks/openwa', ['event' => 'message.received'])
            ->assertStatus(404);
    }

    /*
     * -------------------------------------------------------------------------
     * LIVE TESTS — only run when a real OpenWA gateway is reachable.
     *
     * Set these in your environment / .env to enable:
     *   OPENWA_BASE_URL=https://your-openwa-host
     *   OPENWA_API_KEY=your_real_key
     *   OPENWA_SESSION=default
     *
     * Requires a running OpenWA instance with the session's QR already
     * scanned (session in "connected" state) for send/receive to succeed.
     * -------------------------------------------------------------------------
     */

    protected function liveOpenWa(): ?OpenWaService
    {
        $this->loadRealOpenWaConfig();

        if (empty(config('services.openwa.api_key')) || empty(config('services.openwa.base_url'))) {
            $this->markTestSkipped('OPENWA_API_KEY / OPENWA_BASE_URL not set — live gateway unavailable.');
        }

        // Hit a real health/session endpoint to confirm reachability.
        $service = new OpenWaService;

        try {
            $status = $service->sessionStatus();
        } catch (\Throwable $e) {
            $this->markTestSkipped('OpenWA gateway not reachable: '.$e->getMessage());
        }

        if ($status === null) {
            $this->markTestSkipped('OpenWA gateway not reachable at '.config('services.openwa.base_url'));
        }

        return $service;
    }

    public function test_live_session_status(): void
    {
        $service = $this->liveOpenWa();
        $this->assertIsArray($service->sessionStatus());
    }

    public function test_live_send_text_message(): void
    {
        $service = $this->liveOpenWa();

        // Send to the connected number itself for a true end-to-end delivery check.
        // Override via OPENWA_TEST_CHAT_ID to target a different opted-in recipient.
        $recipient = config('services.openwa.test_chat_id') ?: '93700268836';
        $message = 'OpenWA live test from MGS @ '.now()->toIso8601String();

        $this->assertTrue(
            $service->send($recipient, $message),
            'Live send failed — check session connection and recipient chatId.'
        );
    }
}
