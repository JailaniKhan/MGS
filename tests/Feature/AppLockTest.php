<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AppLockTest extends TestCase
{
    use RefreshDatabase;

    private function enablePin(string $pin = '1234'): void
    {
        Setting::set('pin_lock_hash', Hash::make($pin));
        Setting::set('pin_lock_enabled', '1');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/app-lock')->assertRedirect(route('login'));
    }

    public function test_index_renders_set_pin_form_when_disabled(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/app-lock');

        $response->assertOk();
        $response->assertSee('pin-form');
        $response->assertDontSee('remove-pin');
    }

    public function test_index_renders_remove_pin_and_biometric_toggle_when_enabled(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['pin_verified' => true]);
        $this->enablePin();

        $response = $this->get('/app-lock');

        $response->assertOk();
        $response->assertSee('app-lock/remove-pin');
        $response->assertSee('app-lock/toggle-biometric');
        $response->assertDontSee('pin-form');
    }

    public function test_locked_session_is_redirected_to_lock_screen(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->enablePin();

        $this->get(route('dashboard'))->assertRedirect(route('app-lock.lock'));
    }

    public function test_app_lock_settings_are_blocked_while_locked(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->enablePin();

        $this->get('/app-lock')->assertRedirect(route('app-lock.lock'));
    }

    public function test_lock_screen_route_renders_while_locked(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->enablePin();

        $response = $this->get(route('app-lock.lock'));

        $response->assertOk();
        $response->assertSee('pin-verify-form');
        $response->assertSee('type="password"', false);
    }

    public function test_verify_pin_success_sets_session_and_unlocks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->enablePin();

        $this->post(route('app-lock.verify-pin'), ['pin' => '1234'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue(session('pin_verified'));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_verify_pin_with_wrong_pin_fails(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->enablePin();

        $this->post(route('app-lock.verify-pin'), ['pin' => '9999'])
            ->assertOk()
            ->assertJson(['success' => false]);

        $this->assertNull(session('pin_verified'));
        $this->get(route('dashboard'))->assertRedirect(route('app-lock.lock'));
    }

    public function test_set_pin_accepts_four_digits(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('app-lock.set-pin'), ['pin' => '1234', 'pin_confirmation' => '1234'])
            ->assertRedirect(route('app-lock.index'));

        $this->assertSame('1', Setting::get('pin_lock_enabled'));
        $this->assertNotNull(Setting::get('pin_lock_hash'));
    }

    public function test_set_pin_rejects_non_numeric_values(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('app-lock.set-pin'), ['pin' => 'abcd', 'pin_confirmation' => 'abcd'])
            ->assertSessionHasErrors('pin');

        $this->assertNull(Setting::get('pin_lock_enabled'));
        $this->assertNull(Setting::get('pin_lock_hash'));
    }

    public function test_set_pin_requires_exactly_four_digits(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('app-lock.set-pin'), ['pin' => '123', 'pin_confirmation' => '123'])
            ->assertSessionHasErrors('pin');

        $this->assertNull(Setting::get('pin_lock_enabled'));
    }

    public function test_remove_pin_disables_lock_and_clears_hash(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['pin_verified' => true]);
        $this->enablePin();

        $this->delete(route('app-lock.remove-pin'))->assertRedirect(route('app-lock.index'));

        $this->assertSame('0', Setting::get('pin_lock_enabled'));
        $this->assertNull(Setting::get('pin_lock_hash'));
    }

    public function test_toggle_biometric_is_rejected_without_a_pin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('app-lock.toggle-biometric'))
            ->assertRedirect(route('app-lock.index'))
            ->assertSessionHas('error', __('messages.biometric_requires_pin'));

        $this->assertNull(Setting::get('biometric_lock_enabled'));
    }

    public function test_toggle_biometric_works_when_pin_is_enabled(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['pin_verified' => true]);
        $this->enablePin();

        $this->post(route('app-lock.toggle-biometric'))
            ->assertRedirect(route('app-lock.index'))
            ->assertSessionHas('success');

        $this->assertSame('1', Setting::get('biometric_lock_enabled'));

        $this->post(route('app-lock.toggle-biometric'));

        $this->assertSame('0', Setting::get('biometric_lock_enabled'));
    }

    public function test_verify_pin_is_throttled_after_five_attempts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->enablePin();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('app-lock.verify-pin'), ['pin' => '0000'])->assertOk();
        }

        $this->post(route('app-lock.verify-pin'), ['pin' => '0000'])->assertStatus(429);
    }
}
