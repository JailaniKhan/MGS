<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Shop Owner',
            'email' => 'owner@example.com',
            'phone' => '+93700000000',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ], $overrides);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('orders.index'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
        $this->get(route('password.request'))->assertOk()->assertSee(__('messages.forgot_password_title'));
        $this->get(route('password.reset'))->assertOk()->assertSee(__('messages.reset_password_title'));
        $this->get(route('login.phone'))->assertOk()->assertSee(__('messages.phone_login'));
    }

    public function test_guests_can_switch_language(): void
    {
        $response = $this->post(route('language.update'), ['language' => 'ps']);

        $response->assertRedirect();
        $this->assertDatabaseHas('settings', ['key' => 'language', 'value' => 'ps']);
    }

    public function test_login_succeeds_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'secret123',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_failure_shows_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        Cache::flush();
        $user = User::factory()->create(['password' => 'secret123']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_register_creates_user_hashes_password_and_seeds_chart(): void
    {
        $response = $this->post(route('register'), $this->registerPayload());

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::where('email', 'owner@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertSame('+93700000000', $user->phone);

        $this->assertSame(6, Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->count());
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);

        $this->post(route('register'), $this->registerPayload())
            ->assertSessionHasErrors('email');
    }

    public function test_register_rejects_password_mismatch(): void
    {
        $payload = $this->registerPayload(['password_confirmation' => 'different123']);

        $this->post(route('register'), $payload)
            ->assertSessionHasErrors('password');
    }

    public function test_phone_registered_user_can_phone_login_via_otp(): void
    {
        $this->post(route('register'), $this->registerPayload());
        $user = User::where('email', 'owner@example.com')->first();

        Cache::put('otp_+93700000000', 123456, 300);

        $response = $this->postJson('/api/auth/verify-otp', [
            'phone' => '+93700000000',
            'otp' => '123456',
        ]);

        $response->assertOk();
        $this->assertSame($user->id, $response->json('user.id'));
    }

    public function test_forgot_password_sends_otp_for_registered_phone(): void
    {
        $this->post(route('register'), $this->registerPayload());

        $response = $this->post(route('password.email'), ['phone' => '+93700000000']);

        $response->assertRedirect(route('password.reset'));
        $this->assertNotNull(cache('otp_reset_+93700000000'));
    }

    public function test_forgot_password_rejects_unregistered_phone(): void
    {
        $response = $this->post(route('password.email'), ['phone' => '+93709999999']);

        $response->assertSessionHasErrors('phone');
        $this->assertNull(cache('otp_reset_+93709999999'));
    }

    public function test_reset_password_with_valid_otp_changes_password_and_logs_in(): void
    {
        $this->post(route('register'), $this->registerPayload());
        $user = User::where('email', 'owner@example.com')->first();
        Cache::put('otp_reset_+93700000000', 654321, 300);

        $response = $this->post(route('password.update'), [
            'phone' => '+93700000000',
            'otp' => '654321',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('newpassword1', $user->fresh()->password));
        $this->assertNull(cache('otp_reset_+93700000000'));
    }

    public function test_reset_password_rejects_wrong_otp(): void
    {
        $user = User::factory()->create(['phone' => '+93700000000', 'password' => 'secret123']);
        Cache::put('otp_reset_+93700000000', 654321, 300);

        $this->post(route('password.update'), [
            'phone' => '+93700000000',
            'otp' => '000000',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])->assertSessionHasErrors('otp');

        $this->assertTrue(Hash::check('secret123', $user->fresh()->password));
        $this->assertGuest();
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
