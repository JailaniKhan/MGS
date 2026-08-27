<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Reminder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openwa.api_key' => 'test-key',
            'services.openwa.base_url' => 'http://127.0.0.1:2785',
            'services.openwa.session' => 'default',
        ]);
    }

    protected function actingUser(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'wa-msg@test.dev'],
            ['name' => 'W', 'password' => bcrypt('x')]
        );
        $this->actingAs($user);

        return $user;
    }

    protected function customer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Ahmad Karimi',
            'phone' => '+93700000001',
        ], $attributes));
    }

    public function test_sends_custom_text_and_logs_reminder(): void
    {
        $user = $this->actingUser();
        $customer = $this->customer();

        Http::fake([
            '*127.0.0.1:2785*' => Http::response(['status' => 'sent', 'id' => 'BAE5'], 200),
        ]);

        $res = $this->postJson(route('customers.message', $customer), [
            'type' => 'text',
            'message' => 'Salam, your order is ready.',
        ]);

        $res->assertOk()->assertJson(['ok' => true, 'status' => 'sent']);

        Http::assertSent(function ($request) use ($customer) {
            return str_contains($request->url(), '/messages/send-text')
                && $request['chatId'] === '93700000001@c.us'
                && $request['text'] === 'Salam, your order is ready.';
        });

        $this->assertDatabaseHas('reminders', [
            'user_id' => $user->id,
            'remindable_type' => 'customer',
            'remindable_id' => $customer->id,
            'channel' => 'whatsapp',
            'message' => 'Salam, your order is ready.',
            'status' => 'sent',
            'provider_message_id' => 'BAE5',
        ]);

        $this->assertNotNull($customer->fresh()->last_contacted_at);
    }

    public function test_sends_voice_note_stores_media_and_logs_reminder(): void
    {
        $this->actingUser();
        $supplier = Supplier::create(['name' => 'Karim Ltd', 'phone' => '0701112223']);

        Http::fake([
            '*127.0.0.1:2785*' => Http::response(['status' => 'sent', 'id' => 'VOICE1'], 200),
        ]);

        $payload = 'data:audio/webm;base64,' . base64_encode(str_repeat('WEBMAUDIO', 8));

        $res = $this->postJson(route('suppliers.message', $supplier), [
            'type' => 'voice',
            'audio' => $payload,
            'duration' => 4,
        ]);

        $res->assertOk()->assertJson(['ok' => true, 'status' => 'sent']);

        Http::assertSent(function ($request) use ($supplier) {
            return str_contains($request->url(), '/messages/send-voice')
                && $request['chatId'] === '93701112223@c.us'
                && str_starts_with((string) $request['audio'], 'data:audio/webm;base64,');
        });

        $reminder = Reminder::where('remindable_type', 'supplier')->where('remindable_id', $supplier->id)->first();
        $this->assertNotNull($reminder);
        $this->assertSame('sent', $reminder->status);
        $this->assertSame('audio/webm; codecs=opus', $reminder->media_type);
        $this->assertStringStartsWith('whatsapp-outbox/', $reminder->media_path);

        Storage::disk('local')->assertExists($reminder->media_path);
        Storage::disk('local')->delete($reminder->media_path);
    }

    public function test_failed_gateway_send_is_logged_as_failed(): void
    {
        $this->actingUser();
        $customer = $this->customer();

        Http::fake([
            '*127.0.0.1:2785*' => Http::response(['error' => 'session not ready'], 400),
        ]);

        $res = $this->postJson(route('customers.message', $customer), [
            'type' => 'text',
            'message' => 'Will fail',
        ]);

        $res->assertOk()->assertJson(['ok' => false, 'status' => 'failed']);
        $this->assertDatabaseHas('reminders', ['status' => 'failed', 'channel' => 'whatsapp']);
    }

    public function test_rejects_invalid_type_and_missing_message(): void
    {
        $this->actingUser();
        $customer = $this->customer();

        $this->postJson(route('customers.message', $customer), ['type' => 'sticker'])
            ->assertStatus(422);

        $this->postJson(route('customers.message', $customer), ['type' => 'text'])
            ->assertStatus(422);
    }

    public function test_rejects_contact_without_phone(): void
    {
        $this->actingUser();
        $customer = $this->customer(['phone' => null]);

        $this->postJson(route('customers.message', $customer), [
            'type' => 'text',
            'message' => 'Hi',
        ])->assertStatus(422);
    }

    public function test_show_renders_empty_conversation_for_new_chat(): void
    {
        $this->actingUser();
        $customer = $this->customer(['name' => 'Fresh Contact']);

        $res = $this->get("/whatsapp-chats/customer/{$customer->id}");
        $res->assertOk();
        $res->assertSee('Fresh Contact');
        $res->assertSee(__('messages.wa_empty_conversation'));
    }

    public function test_voice_media_streams_to_owner_only(): void
    {
        $owner = $this->actingUser();
        $customer = $this->customer();

        $reminder = Reminder::create([
            'user_id' => $owner->id,
            'remindable_type' => 'customer',
            'remindable_id' => $customer->id,
            'amount' => null,
            'currency' => 'AFN',
            'channel' => 'whatsapp',
            'message' => '',
            'media_path' => 'whatsapp-outbox/test.webm',
            'media_type' => 'audio/webm; codecs=opus',
            'status' => 'sent',
        ]);

        Storage::disk('local')->put($reminder->media_path, 'FAKEAUDIO');

        $this->get(route('whatsapp.chats.media', $reminder))
            ->assertOk()
            ->assertHeader('content-type', 'audio/webm; codecs=opus');

        // Another user cannot read someone else's media. Reminder carries a
        // per-user global scope, so the binding itself resolves to 404 —
        // ownership is enforced before the controller even runs.
        $other = User::create(['name' => 'O', 'email' => 'other-wa-msg@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($other)
            ->get(route('whatsapp.chats.media', $reminder))
            ->assertNotFound();

        Storage::disk('local')->delete($reminder->media_path);
    }
}
