<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use App\Services\Accounting\BalanceService;
use App\Services\Accounting\ChartOfAccountsSeeder;
use App\Services\Accounting\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_balance_after_sale_and_payment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $chart = app(ChartOfAccountsSeeder::class);
        $chart->seedForUser($user);
        $customer = $chart->createPartyAccount($user, 'customer', 'Customer', null, null, 'AFN', 3);
        $service = app(TransactionService::class);
        $balance = app(BalanceService::class);

        $service->postCustomerCreditSale($user, $customer, '100.00', 'AFN');
        $this->assertSame('100.00', $balance->balance($customer->id));

        $service->postCustomerPayment($user, $customer, '40.00', 'AFN');
        $this->assertSame('60.00', $balance->balance($customer->id));
    }
}
