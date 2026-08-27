<?php

namespace Tests\Feature\Reports;

use App\Models\CashbookEntry;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
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

    private function makeProduct(string $name = 'Widget'): Product
    {
        $category = Category::create(['name' => 'General']);

        return Product::create(['name' => $name, 'category_id' => $category->id]);
    }

    private function purchaseItem(User $user, Supplier $supplier, Product $product, string $createdAt, int $qty, string $unitPrice, string $currency = 'AFN'): void
    {
        $lineTotal = bcmul($unitPrice, (string) $qty, 2);
        $purchase = $this->makePurchase($user, $supplier, [
            'total_amount' => $lineTotal,
            'subtotal' => $lineTotal,
            'currency' => $currency,
        ]);
        $purchase->forceFill(['created_at' => $createdAt])->save();

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'subtotal' => $lineTotal,
        ]);
    }

    private function orderItem(Order $order, Product $product, int $qty, string $unitPrice): void
    {
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'subtotal' => bcmul($unitPrice, (string) $qty, 2),
        ]);
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
        // Expense rows render with a leading minus sign.
        $response->assertSee('>-50.00', false);
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

        $product = $this->makeProduct('Profit Widget');
        $customer = Customer::create(['name' => 'Profit Customer', 'phone' => '0700000004']);
        $order = $this->orderAt($user, $customer, '2026-08-10 10:00:00', ['total_amount' => '900.00', 'subtotal' => '900.00']);
        $this->orderItem($order, $product, 1, '900.00');

        OrderReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'return_date' => '2026-08-11',
            'total_amount' => '100.00',
            'status' => 'completed',
            'currency' => 'AFN',
        ]);

        // Bought 10 units at 20.50; only 1 unit was sold, so COGS = 20.50.
        // The purchase return below must NOT change the per-unit cost basis.
        $supplier = Supplier::create(['name' => 'Profit Supplier', 'phone' => '0700000005']);
        $this->purchaseItem($user, $supplier, $product, '2026-08-08 10:00:00', 10, '20.50');
        PurchaseReturn::create([
            'purchase_id' => Purchase::first()->id,
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
        // Revenue 900 - 100 return = 800; sold-goods cost 1 x 20.50 = 20.50;
        // Expenses 20.50 + 50 cash + 50 salary + 30 expense = 150.50; net = 649.50.
        $response->assertSee('>800.00', false);
        // Expense rows render with a leading minus sign.
        $response->assertSee('>-20.50', false);
        $response->assertSee('>-150.50', false);
        // Net profit hero wraps its value on a new line; the badge is inline.
        $response->assertSee('+649.50', false);
    }

    public function test_month_period_counts_returns_and_expenses_on_the_first_day(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct('FirstDay Widget');
        $customer = Customer::create(['name' => 'FirstDay Customer', 'phone' => '0700000008']);
        $order = $this->orderAt($user, $customer, '2026-08-05 10:00:00', ['total_amount' => '500.00', 'subtotal' => '500.00']);
        $this->orderItem($order, $product, 1, '500.00');

        OrderReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'return_date' => '2026-08-01',
            'total_amount' => '50.00',
            'status' => 'completed',
            'currency' => 'AFN',
        ]);

        $supplier = Supplier::create(['name' => 'FirstDay Supplier', 'phone' => '0700000009']);
        $this->purchaseItem($user, $supplier, $product, '2026-08-05 10:00:00', 10, '18.00');
        PurchaseReturn::create([
            'purchase_id' => Purchase::first()->id,
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
        // Revenue 500 - 50 = 450; sold-goods cost 1 x 18 = 18; expenses 18 + 10 = 28; net = 422.
        $response->assertSee('>450.00', false);
        // COGS and expense rows render with a leading minus sign.
        $response->assertSee('>-18.00', false);
        $response->assertSee('>-10.00', false);
        // Net profit hero wraps its value on a new line; the badge is inline.
        $response->assertSee('+422.00', false);
    }

    public function test_gross_profit_and_net_margin_are_displayed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct('Margin Widget');
        $customer = Customer::create(['name' => 'Margin Customer', 'phone' => '0700000010']);
        $order = $this->orderAt($user, $customer, '2026-08-10 10:00:00', ['total_amount' => '200.00', 'subtotal' => '200.00']);
        $this->orderItem($order, $product, 1, '200.00');

        // 1 unit sold at weighted average cost 5.00 -> gross profit 195.00.
        $supplier = Supplier::create(['name' => 'Margin Supplier', 'phone' => '0700000011']);
        $this->purchaseItem($user, $supplier, $product, '2026-08-08 10:00:00', 10, '5.00');

        $response = $this->get('/reports/profit-loss?date_to=2026-08-12');

        $response->assertOk();
        $response->assertSee(__('messages.gross_profit'));
        $response->assertSee(__('messages.net_margin'));
        // Gross = 200 - (1 x 5.00) = 195; net = 195; margin = 97.5%.
        $response->assertSee('>195.00', false);
        $response->assertSee('97.5%');
    }

    public function test_unit_profit_matches_buy_and_sell_price_example(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct('Gadget');
        $supplier = Supplier::create(['name' => 'USD Supplier', 'phone' => '0700000020']);
        $this->purchaseItem($user, $supplier, $product, '2026-08-08 10:00:00', 10, '20.50', 'USD');

        $customer = Customer::create(['name' => 'USD Margin Buyer', 'phone' => '0700000021']);
        $order = $this->orderAt($user, $customer, '2026-08-10 10:00:00', [
            'total_amount' => '20.70',
            'subtotal' => '20.70',
            'currency' => 'USD',
        ]);
        $this->orderItem($order, $product, 1, '20.70');

        $response = $this->get('/reports/profit-loss?date_to=2026-08-12&currency=USD');

        $response->assertOk();
        // Bought at 20.50, sold at 20.70 -> profit is 0.20 per unit.
        $response->assertSee(__('messages.unit_profit_loss'));
        $response->assertSee('Gadget');
        $response->assertSee('+0.20');
        $response->assertSee('20.50');
        $response->assertSee('20.70');
    }

    public function test_sale_without_purchase_cost_is_flagged_not_silently_profited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct('Ghost Item');
        $customer = Customer::create(['name' => 'Ghost Buyer', 'phone' => '0700000022']);
        $order = $this->orderAt($user, $customer, '2026-08-10 10:00:00', ['total_amount' => '100.00', 'subtotal' => '100.00']);
        $this->orderItem($order, $product, 1, '100.00');

        $response = $this->get('/reports/profit-loss?date_to=2026-08-12');

        $response->assertOk();
        // No purchase in AFN for this product: the line is flagged instead of
        // being counted as pure margin.
        $response->assertSee(__('messages.no_cost_data'));
        $response->assertSee('Ghost Item');
        // Per-sale lines without cost render an em-dash instead of a figure.
        $response->assertSee('>—<', false);
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
