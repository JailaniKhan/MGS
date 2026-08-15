<?php

namespace Tests\Feature\Reports;

use App\Models\CashbookEntry;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\SalaryPayment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitLossTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(User $user, Customer $customer, array $overrides = []): Order
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

    private function orderAt(User $user, Customer $customer, string $createdAt, array $overrides = []): Order
    {
        $order = $this->makeOrder($user, $customer, $overrides);
        $order->forceFill(['created_at' => $createdAt])->save();

        return $order;
    }

    private function makePurchase(User $user, Supplier $supplier, array $overrides = []): Purchase
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
        $this->get('/reports/profit-loss')->assertRedirect(route('login'));
    }

    public function test_default_range_renders_single_date_input_with_chips(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/reports/profit-loss');

        $response->assertOk();
        $response->assertDontSee('name="start_date"');
        $response->assertDontSee('name="end_date"');
        $response->assertSee(__('messages.this_month'));
        $response->assertSee(__('messages.last_30_days'));
        $response->assertSee(__('messages.this_quarter'));
        $response->assertSee(__('messages.this_year'));
    }

    public function test_month_period_includes_boundary_rows_of_the_anchor_month(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'Boundary Customer', 'phone' => '0700000000']);
        $this->orderAt($user, $customer, '2026-08-01 00:00:01', ['total_amount' => '200.00', 'subtotal' => '200.00']);
        $this->orderAt($user, $customer, '2026-07-25 12:00:00', ['total_amount' => '300.00', 'subtotal' => '300.00']);

        $cash = CashbookEntry::create([
            'type' => 'out',
            'amount' => '50.00',
            'currency' => 'AFN',
            'entry_date' => '2026-08-12',
        ]);
        $cash->forceFill(['created_at' => '2026-08-12 23:59:59'])->save();

        $response = $this->get('/reports/profit-loss?date_to=2026-08-12');

        $response->assertOk();
        $response->assertSee('>200.00', false);
        $response->assertSee('>50.00', false);
        $response->assertDontSee('>300.00', false);
    }

    public function test_period_thirty_excludes_orders_outside_the_window(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'Window Customer', 'phone' => '0700000001']);
        $this->orderAt($user, $customer, '2026-07-13 10:00:00', ['total_amount' => '150.00', 'subtotal' => '150.00']);
        $this->orderAt($user, $customer, '2026-07-16 10:00:00', ['total_amount' => '80.00', 'subtotal' => '80.00']);

        $response = $this->get('/reports/profit-loss?period=30&date_to=2026-08-12');

        $response->assertOk();
        $response->assertSee('>80.00', false);
        $response->assertDontSee('>150.00', false);
    }

    public function test_currency_filter_keeps_amounts_separate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $afnCustomer = Customer::create(['name' => 'AFN Buyer', 'phone' => '0700000002']);
        $this->orderAt($user, $afnCustomer, '2026-08-10 10:00:00', ['total_amount' => '500.00', 'subtotal' => '500.00']);

        $usdCustomer = Customer::create(['name' => 'USD Buyer', 'phone' => '0700000003']);
        $this->orderAt($user, $usdCustomer, '2026-08-10 10:00:00', [
            'total_amount' => '100.00', 'subtotal' => '100.00', 'currency' => 'USD',
        ]);

        $afnResponse = $this->get('/reports/profit-loss?date_to=2026-08-12');
        $afnResponse->assertOk();
        $afnResponse->assertSee('>500.00', false);
        $afnResponse->assertDontSee('>100.00', false);

        $usdResponse = $this->get('/reports/profit-loss?date_to=2026-08-12&currency=USD');
        $usdResponse->assertOk();
        $usdResponse->assertSee('>100.00', false);
        $usdResponse->assertDontSee('>500.00', false);
    }

    public function test_net_profit_computes_revenue_minus_all_expenses_with_bcmath(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'Profit Customer', 'phone' => '0700000004']);
        $this->orderAt($user, $customer, '2026-08-10 10:00:00', ['total_amount' => '1000.00', 'subtotal' => '1000.00']);

        OrderReturn::create([
            'order_id' => Order::first()->id,
            'customer_id' => $customer->id,
            'return_date' => '2026-08-11',
            'total_amount' => '100.00',
            'status' => 'completed',
            'currency' => 'AFN',
        ]);

        $supplier = Supplier::create(['name' => 'Profit Supplier', 'phone' => '0700000005']);
        $purchase = $this->makePurchase($user, $supplier, ['total_amount' => '200.00', 'subtotal' => '200.00']);
        $purchase->forceFill(['created_at' => '2026-08-08 10:00:00'])->save();

        PurchaseReturn::create([
            'purchase_id' => $purchase->id,
            'supplier_id' => $supplier->id,
            'return_date' => '2026-08-09',
            'total_amount' => '50.00',
            'status' => 'completed',
            'currency' => 'AFN',
        ]);

        $cash = CashbookEntry::create(['type' => 'out', 'amount' => '50.00', 'currency' => 'AFN', 'entry_date' => '2026-08-10']);
        $cash->forceFill(['created_at' => '2026-08-10 10:00:00'])->save();

        $employee = Employee::create(['name' => 'Staff A', 'phone' => '0700000006']);
        $salary = SalaryPayment::create([
            'employee_id' => $employee->id,
            'amount' => '50.00',
            'currency' => 'AFN',
            'for_month' => '2026-08-01',
        ]);
        $salary->forceFill(['created_at' => '2026-08-10 10:00:00'])->save();

        Expense::create([
            'category' => 'Utilities',
            'amount' => '30.00',
            'currency' => 'AFN',
            'expense_date' => '2026-08-11',
        ]);

        $response = $this->get('/reports/profit-loss?date_to=2026-08-12');

        $response->assertOk();
        // Revenue 1000 - 100 return = 900; COGS 200 - 50 return = 150;
        // Expenses 150 + 50 cash + 50 salary + 30 expense = 280; net = 620.
        $response->assertSee('>900.00', false);
        $response->assertSee('>280.00', false);
        $response->assertSee('>620.00', false);
    }

    public function test_month_period_counts_returns_and_expenses_on_the_first_day(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'FirstDay Customer', 'phone' => '0700000008']);
        $this->orderAt($user, $customer, '2026-08-05 10:00:00', ['total_amount' => '500.00', 'subtotal' => '500.00']);

        OrderReturn::create([
            'order_id' => Order::first()->id,
            'customer_id' => $customer->id,
            'return_date' => '2026-08-01',
            'total_amount' => '50.00',
            'status' => 'completed',
            'currency' => 'AFN',
        ]);

        $supplier = Supplier::create(['name' => 'FirstDay Supplier', 'phone' => '0700000009']);
        $purchase = $this->makePurchase($user, $supplier, ['total_amount' => '200.00', 'subtotal' => '200.00']);
        $purchase->forceFill(['created_at' => '2026-08-05 10:00:00'])->save();

        PurchaseReturn::create([
            'purchase_id' => $purchase->id,
            'supplier_id' => $supplier->id,
            'return_date' => '2026-08-01',
            'total_amount' => '20.00',
            'status' => 'completed',
            'currency' => 'AFN',
        ]);

        Expense::create([
            'category' => 'Utilities',
            'amount' => '10.00',
            'currency' => 'AFN',
            'expense_date' => '2026-08-01',
        ]);

        $response = $this->get('/reports/profit-loss?date_to=2026-08-12');

        $response->assertOk();
        // return_date / expense_date are stored date-only; a `>= startOfDay` string
        // compare would silently drop the FIRST day of the month from all three.
        // Revenue 500 - 50 = 450; COGS 200 - 20 = 180; expenses 180 + 10 = 190; net = 260.
        $response->assertSee('>450.00', false);
        $response->assertSee('>180.00', false);
        $response->assertSee('>10.00', false);
        $response->assertSee('>260.00', false);
    }

    public function test_gross_profit_and_net_margin_are_displayed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'Margin Customer', 'phone' => '0700000010']);
        $this->orderAt($user, $customer, '2026-08-10 10:00:00', ['total_amount' => '200.00', 'subtotal' => '200.00']);

        $supplier = Supplier::create(['name' => 'Margin Supplier', 'phone' => '0700000011']);
        $purchase = $this->makePurchase($user, $supplier, ['total_amount' => '50.00', 'subtotal' => '50.00']);
        $purchase->forceFill(['created_at' => '2026-08-08 10:00:00'])->save();

        $response = $this->get('/reports/profit-loss?date_to=2026-08-12');

        $response->assertOk();
        $response->assertSee(__('messages.gross_profit'));
        $response->assertSee(__('messages.net_margin'));
        // Gross = 200 - 50 = 150; net = 150; margin = 75%.
        $response->assertSee('>150.00', false);
        $response->assertSee('75.0%');
    }

    public function test_year_period_spans_the_whole_year_of_the_anchor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'Year Customer', 'phone' => '0700000007']);
        $this->orderAt($user, $customer, '2026-01-01 00:00:01', ['total_amount' => '200.00', 'subtotal' => '200.00']);
        $this->orderAt($user, $customer, '2026-12-31 23:59:59', ['total_amount' => '300.00', 'subtotal' => '300.00']);

        $response = $this->get('/reports/profit-loss?period=year&date_to=2026-12-31');

        $response->assertOk();
        $response->assertSee('>500.00', false);
    }
}
