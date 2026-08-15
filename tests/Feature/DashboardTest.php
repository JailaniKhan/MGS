<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Aggregates are cached (dashboard_v3_{user}_{day}, 120s); distinct
        // users already get distinct keys, but flush anyway so a leftover
        // entry can never leak between tests.
        Cache::flush();
    }

    private function makeCustomer(string $name): Customer
    {
        return Customer::create(['name' => $name, 'phone' => '07'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT)]);
    }

    private function makeSupplier(string $name): Supplier
    {
        return Supplier::create(['name' => $name, 'phone' => '07'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT)]);
    }

    private function makeOrder(Customer $customer, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '0.00',
            'currency' => 'AFN',
        ], $overrides));
    }

    private function makePurchase(Supplier $supplier, array $overrides = []): Purchase
    {
        return Purchase::create(array_merge([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '0.00',
            'currency' => 'AFN',
        ], $overrides));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_pending_orders_count_subtracts_returns_and_ignores_foreign_currency_payments(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Pending Customer');

        // 100 order, 30 paid → pending.
        $order = $this->makeOrder($customer, ['total_amount' => '100.00']);
        Payment::create(['order_id' => $order->id, 'amount' => '30.00', 'currency' => 'AFN']);

        // 50 order fully covered by a return → NOT pending.
        $returned = $this->makeOrder($customer, ['total_amount' => '50.00']);
        OrderReturn::create([
            'order_id' => $returned->id,
            'customer_id' => $customer->id,
            'return_date' => now()->toDateString(),
            'total_amount' => '50.00',
        ]);

        // 200 order paid in USD — a foreign currency never settles it → pending.
        $usdPaid = $this->makeOrder($customer, ['total_amount' => '200.00']);
        Payment::create(['order_id' => $usdPaid->id, 'amount' => '200.00', 'currency' => 'USD']);

        // Cancelled order with no payment → NOT pending.
        $this->makeOrder($customer, ['total_amount' => '999.00', 'status' => 'cancelled']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('pendingOrders', 2);
    }

    public function test_pending_payments_count_subtracts_returns_and_ignores_foreign_currency_payments(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Pending Supplier');

        $purchase = $this->makePurchase($supplier, ['total_amount' => '100.00']);
        PurchasePayment::create(['purchase_id' => $purchase->id, 'amount' => '100.00', 'currency' => 'USD']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('pendingPayments', 1);
    }

    public function test_revenue_excludes_payments_of_cancelled_orders(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Cancelled Customer');

        $cancelled = $this->makeOrder($customer, ['total_amount' => '100.00', 'status' => 'cancelled']);
        Payment::create(['order_id' => $cancelled->id, 'amount' => '100.00', 'currency' => 'AFN']);

        $live = $this->makeOrder($customer, ['total_amount' => '50.00']);
        Payment::create(['order_id' => $live->id, 'amount' => '50.00', 'currency' => 'AFN']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('totalRevenueAFN', 50.0);
    }

    public function test_expenses_exclude_payments_of_cancelled_purchases(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Cancelled Supplier');

        $cancelled = $this->makePurchase($supplier, ['total_amount' => '100.00', 'status' => 'cancelled']);
        PurchasePayment::create(['purchase_id' => $cancelled->id, 'amount' => '100.00', 'currency' => 'AFN']);

        $live = $this->makePurchase($supplier, ['total_amount' => '30.00']);
        PurchasePayment::create(['purchase_id' => $live->id, 'amount' => '30.00', 'currency' => 'AFN']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('totalExpenseAFN', 30.0);
    }

    public function test_low_stock_uses_the_configured_min_stock_threshold(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Setting::set('min_stock_threshold', 3);

        $category = Category::create(['name' => 'Threshold Category']);
        Product::create(['name' => 'Low', 'category_id' => $category->id, 'stock' => 2]);
        Product::create(['name' => 'Edge', 'category_id' => $category->id, 'stock' => 3]);
        Product::create(['name' => 'Fine', 'category_id' => $category->id, 'stock' => 4]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('lowStockProducts', 1);
    }

    public function test_processing_orders_tile_counts_only_processing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Processing Customer');

        $this->makeOrder($customer, ['status' => 'processing']);
        $this->makeOrder($customer, ['status' => 'processing']);
        $this->makeOrder($customer, ['status' => 'completed']);
        $this->makeOrder($customer, ['status' => 'cancelled']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('processingOrders', 2);
    }

    public function test_top_debtors_include_customer_and_supplier_net_balances(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Debtor Customer');
        $supplier = $this->makeSupplier('Creditor Supplier');

        $this->makeOrder($customer, ['total_amount' => '100.00']);
        $this->makePurchase($supplier, ['total_amount' => '80.00']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('topDebtors', function ($debtors) {
                $customerRow = $debtors->firstWhere('type', 'customer');
                $supplierRow = $debtors->firstWhere('type', 'supplier');

                return count($debtors) === 2
                    && $customerRow->pending_afn == 100
                    && $supplierRow->pending_afn == -80;
            });
    }

    public function test_dashboard_renders_hero_metrics_and_sections(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Render Customer');
        $this->makeOrder($customer, ['total_amount' => '100.00']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('messages.net_balance'))
            ->assertSee(__('messages.weekly_revenue'))
            ->assertSee(__('messages.top_debtors_creditors'))
            ->assertSee(__('messages.recent_orders'))
            ->assertSee(__('messages.recent_purchases'))
            ->assertSee(__('messages.recent_reminders'));
    }
}
