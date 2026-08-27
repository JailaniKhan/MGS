<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function user(): User
    {
        return User::create(['name' => 'S', 'email' => 'settings@test.dev', 'password' => bcrypt('x')]);
    }

    public function test_settings_page_renders_new_fields(): void
    {
        $this->actingAs($this->user());

        $res = $this->get('/settings');
        $res->assertOk();
        $res->assertSee('usd_to_afn_rate');
        $res->assertSee('min_stock_threshold');
        $res->assertSee('purchase_prefix');
        // removed fields are gone from the form
        $res->assertDontSee('name="currency"');
        $res->assertDontSee('name="language"');
    }

    public function test_update_persists_new_settings(): void
    {
        $this->actingAs($this->user());

        $res = $this->post('/settings', [
            'company_name' => 'Test Biz',
            'company_phone' => '0700000000',
            'company_email' => 'x@y.dev',
            'invoice_prefix' => 'INV-',
            'purchase_prefix' => 'PUR-',
            'usd_to_afn_rate' => '73.5',
            'min_stock_threshold' => '5',
        ]);
        $res->assertRedirect(route('settings.index'));
        $res->assertSessionHas('success');

        $this->assertSame('Test Biz', Setting::get('company_name'));
        $this->assertSame('73.5', Setting::get('usd_to_afn_rate'));
        $this->assertSame('5', Setting::get('min_stock_threshold'));
        $this->assertSame('PUR-', Setting::get('purchase_prefix'));
    }

    public function test_update_rejects_invalid_values(): void
    {
        $this->actingAs($this->user());

        $res = $this->post('/settings', [
            'company_email' => 'not-an-email',
            'usd_to_afn_rate' => '-5',
            'min_stock_threshold' => '2.5',
        ]);
        $res->assertSessionHasErrors(['company_email', 'usd_to_afn_rate', 'min_stock_threshold']);
    }
}
