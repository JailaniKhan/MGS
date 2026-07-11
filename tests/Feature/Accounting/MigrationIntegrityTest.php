<?php

namespace Tests\Feature\Accounting;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Accounting\BalanceService;
use App\Services\Accounting\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_command_preserves_wallet_totals(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = \App\Models\Customer::create(['name' => 'Ali', 'phone' => '0700000000']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'completed',
            'total_amount' => 100,
            'currency' => 'AFN',
        ]);

        Payment::create([
            'order_id' => $order->id,
            'amount' => 40,
            'currency' => 'AFN',
        ]);

        $legacyIncoming = Payment::where('currency', 'AFN')->sum('amount');

        $this->artisan('accounting:migrate-to-double-entry', ['--user-id' => $user->id])
            ->assertSuccessful();

        $cash = app(BalanceService::class)->cashBalance($user->id, 'AFN');

        $this->assertSame(number_format((float) $legacyIncoming, 2, '.', ''), $cash);
    }
}
