<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Settings are scoped to their owner since the pin/company leak fix. The
 * static get()/set() API hides the scoping, so these tests exercise it
 * through exactly that surface.
 */
class SettingsIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_users_pin_and_company_are_invisible_to_another(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner);
        Setting::set('company_name', 'Owner Shop');
        Setting::set('pin_lock_hash', Hash::make('1234'));
        Setting::set('pin_lock_enabled', '1');

        $this->actingAs($other);

        $this->assertSame('My Business', Setting::get('company_name', 'My Business'));
        $this->assertNull(Setting::get('pin_lock_enabled'));
        $this->assertNull(Setting::get('pin_lock_hash'));

        // And the other account can hold its own value for the same key.
        Setting::set('company_name', 'Other Shop');
        $this->assertSame('Other Shop', Setting::get('company_name'));

        // Owner's rows are untouched by the second write.
        $this->actingAs($owner);
        $this->assertSame('Owner Shop', Setting::get('company_name'));
        $this->assertTrue(Hash::check('1234', (string) Setting::get('pin_lock_hash')));
    }

    public function test_guests_see_only_userless_rows(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner);
        Setting::set('language', 'en');

        $this->post(route('logout'));

        // Guest context (no auth): owned rows are out of scope entirely.
        $this->assertNull(Setting::get('language'));
        $this->assertSame('ps', Setting::get('language', 'ps'));

        // Machine flags are written userless and stay console-visible.
        Setting::set('double_entry_migrated', '1');
        $this->assertSame('1', Setting::get('double_entry_migrated'));

        $this->actingAs($owner);
        $this->assertNull(Setting::get('double_entry_migrated'), "Machine flags must not leak into a user's namespace.");
    }
}
