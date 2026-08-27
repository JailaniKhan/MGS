<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppChatsTest extends TestCase
{
    use RefreshDatabase;

    protected function actingUser(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'wa@test.dev'],
            ['name' => 'W', 'password' => bcrypt('x')]
        );
        $this->actingAs($user);

        return $user;
    }

    protected function reminder(array $overrides = []): Reminder
    {
        $createdAt = null;
        if (array_key_exists('created_at', $overrides)) {
            $createdAt = $overrides['created_at'];
            unset($overrides['created_at']);
        }

        $reminder = Reminder::create(array_merge([
            'remindable_type' => 'customer',
            'remindable_id' => 0,
            'amount' => 100,
            'currency' => 'AFN',
            'channel' => 'whatsapp',
            'message' => 'Please pay',
            'status' => 'sent',
        ], $overrides));

        if ($createdAt !== null) {
            $reminder->forceFill(['created_at' => $createdAt])->save();
        }

        return $reminder;
    }

    public function test_index_groups_chats_per_contact_with_latest_message(): void
    {
        $this->actingUser();
        $customer = Customer::create(['name' => 'Ahmad', 'phone' => '+93700000001']);

        $this->reminder([
            'remindable_id' => $customer->id,
            'message' => 'First reminder',
            'created_at' => now()->subDay(),
        ]);
        $this->reminder([
            'remindable_id' => $customer->id,
            'message' => 'Second reminder',
        ]);

        $res = $this->get('/whatsapp-chats');
        $res->assertOk();
        $res->assertSee('Ahmad');
        $res->assertSee('Second reminder');
        $res->assertDontSee('First reminder');
        $res->assertSee('wa-chat-list');
    }

    public function test_index_counts_only_viewing_users_chats(): void
    {
        $this->actingUser();
        $other = User::create(['name' => 'O', 'email' => 'other-wa@test.dev', 'password' => bcrypt('x')]);
        $customer = Customer::create(['name' => 'Hidden', 'phone' => '+93700000002']);

        $otherReminder = new Reminder([
            'remindable_type' => 'customer',
            'remindable_id' => $customer->id,
            'channel' => 'whatsapp',
            'message' => 'Hidden message',
            'status' => 'sent',
        ]);
        $otherReminder->user_id = $other->id;
        $otherReminder->save();

        // The other user's reminder must not surface anywhere in this
        // viewer's UI. (The contact itself may appear in the new-chat picker
        // — it belongs to the viewer and has no chat history here.)
        $this->get('/whatsapp-chats')
            ->assertOk()
            ->assertDontSee('Hidden message');
    }

    public function test_index_ignores_non_whatsapp_channels(): void
    {
        $this->actingUser();
        $customer = Customer::create(['name' => 'SmsOnly', 'phone' => '+93700000003']);

        $this->reminder([
            'remindable_id' => $customer->id,
            'channel' => 'sms',
            'message' => 'SMS reminder',
        ]);

        // SMS history stays out of the WhatsApp hub entirely.
        $this->get('/whatsapp-chats')
            ->assertOk()
            ->assertDontSee('SMS reminder');
    }

    public function test_show_renders_conversation_oldest_first_with_back_button(): void
    {
        $this->actingUser();
        $customer = Customer::create(['name' => 'Fatima', 'phone' => '+93700000004']);

        $this->reminder([
            'remindable_id' => $customer->id,
            'message' => 'Alpha message',
            'status' => 'sent',
            'created_at' => now()->subDay(),
        ]);
        $this->reminder([
            'remindable_id' => $customer->id,
            'message' => 'Beta failed message',
            'status' => 'failed',
            'error_message' => 'session not ready',
        ]);

        $res = $this->get("/whatsapp-chats/customer/{$customer->id}");
        $res->assertOk();
        $res->assertSee("Alpha message");
        $res->assertSee("Beta failed message");
        $res->assertSee("session not ready");
        $res->assertSee(route('whatsapp.chats.index'));
        $res->assertSee(route('customers.show', $customer->id));

        $this->assertTrue(strpos($res->content(), 'Alpha message') < strpos($res->content(), 'Beta failed message'));
    }

    public function test_show_aborts_for_unknown_type_or_missing_messages(): void
    {
        $this->actingUser();

        $this->get('/whatsapp-chats/robot/1')->assertNotFound();
        $this->get('/whatsapp-chats/customer/999999')->assertNotFound();
    }
}
