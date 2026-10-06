<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Reminder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'reminder@test.dev'],
            ['name' => 'R', 'password' => bcrypt('x')]
        );
        $this->actingAs($user);

        return $user;
    }

    private function makeCustomer(string $name, string $phone = '0700123456'): Customer
    {
        return Customer::create(['name' => $name, 'phone' => $phone]);
    }

    private function fakeOpenWa(string $sessionState = 'ready'): void
    {
        config([
            'services.anthropic.api_key' => null,
            'services.openwa.api_key' => 'test-key',
            'services.openwa.base_url' => 'http://openwa.test',
            'services.openwa.session' => 'default',
        ]);

        Http::fake([
            'openwa.test/api/sessions/*/messages/send-text' => Http::response(['success' => true], 200),
            'openwa.test/api/sessions/*' => Http::response(['status' => $sessionState], 200),
        ]);
    }

    public function test_ledger_page_renders_customer_reminder_form_with_action(): void
    {
        $this->actingUser();
        $customer = $this->makeCustomer('Ledger Reminder Customer');

        $this->get(route('ledger.show', ['customer', $customer->id]))
            ->assertOk()
            ->assertSee('action="'.route('reminders.customer', $customer).'"', false)
            ->assertSee('method="POST"', false);
    }

    public function test_ledger_page_renders_supplier_reminder_form_with_action(): void
    {
        $this->actingUser();
        $supplier = Supplier::create(['name' => 'Ledger Reminder Supplier', 'phone' => '0700654321']);

        $this->get(route('ledger.show', ['supplier', $supplier->id]))
            ->assertOk()
            ->assertSee('action="'.route('reminders.supplier', $supplier).'"', false)
            ->assertSee('method="POST"', false);
    }

    public function test_whatsapp_reminder_sends_and_records_sent(): void
    {
        $this->actingUser();
        $customer = $this->makeCustomer('WhatsApp Reminder Customer');
        $this->fakeOpenWa('ready');

        $this->from(route('ledger.show', ['customer', $customer->id]))
            ->post(route('reminders.customer', $customer), [
                'channel' => 'whatsapp',
                'currency' => 'AFN',
                'amount' => '50',
            ])
            ->assertRedirect(route('ledger.show', ['customer', $customer->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reminders', [
            'remindable_id' => $customer->id,
            'channel' => 'whatsapp',
            'status' => 'sent',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/messages/send-text')
                && $request['chatId'] === '93700123456@c.us';
        });
    }

    public function test_whatsapp_reminder_fails_when_session_not_connected(): void
    {
        $this->actingUser();
        $customer = $this->makeCustomer('Disconnected Reminder Customer');
        $this->fakeOpenWa('disconnected');

        $this->from(route('ledger.show', ['customer', $customer->id]))
            ->post(route('reminders.customer', $customer), [
                'channel' => 'whatsapp',
                'currency' => 'AFN',
                'amount' => '50',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('reminders', [
            'remindable_id' => $customer->id,
            'channel' => 'whatsapp',
            'status' => 'failed',
        ]);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/messages/send-text'));
    }

    public function test_reminder_message_follows_active_locale(): void
    {
        $this->actingUser();
        $customer = $this->makeCustomer('Locale Customer');
        $this->fakeOpenWa('ready');

        foreach ([
            'en' => 'friendly reminder',
            'fa' => 'یادآوری دوستانه',
            'ps' => 'دوستانه یادونه',
        ] as $locale => $needle) {
            $this->withSession(['locale' => $locale])
                ->post(route('reminders.customer', $customer), [
                    'channel' => 'whatsapp',
                    'currency' => 'AFN',
                    'amount' => '50',
                ])->assertSessionHas('success');

            $reminder = Reminder::latest('id')->first();
            $this->assertStringContainsString($needle, $reminder->message, "Locale {$locale} should render its own template");
            $this->assertStringContainsString('Locale Customer', $reminder->message);
        }
    }
}
