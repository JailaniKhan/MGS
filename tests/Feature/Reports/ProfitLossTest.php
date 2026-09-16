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

    public function test_default_view_renders_single_date_input_and_all_time_label(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/reports/profit-loss');

        $response->assertOk();
        $response->assertDontSee('name="start_date"');
        $response->assertDontSee('name="end_date"');
        // All Time is the only window: its label shows, the old period pills
        // and any period= links are gone entirely.
        $response->assertSee(__('messages.all_time'));
        $response->assertDontSee('period=', false);
        $response->assertDontSee(__('messages.this_month'));
        $response->assertDontSee(__('messages.last_30_days'));
        $response->assertDontSee(__('messages.this_quarter'));
        $response->assertDontSee(__('messages.this_year'));
    }

    public function test_window_closes_at_the_anchor_end_of_day(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'Boundary Customer', 'phone' => '0700000000']);
        $this->orderAt($user, $customer, '2026-08-12 23:59:59', ['total_amount' => '200.00', 'subtotal' => '200.00']);
        $this->orderAt($user, $customer, '2026-08-13 10:00:00', ['total_amount' => '300.00', 'subtotal' => '300.00']);

        $response = $this->get('/reports/profit-loss?date_to=2026-08-12');

        $response->assertOk();
        // The anchor day's last second is inside the window.
        $response->assertSee('>200.00', false);
        // The day after the anchor is outside it.
        $response->assertDontSee('>300.00', false);
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

    public function test_date_only_columns_count_rows_on_the_earliest_record_day(): void
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
        // compare would silently drop the EARLIEST day from all three. Their dates
        // (Aug 1) precede the earliest order (Aug 5), so under the all-time window
        // they sit on the window's first day.
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

    private function returnItemsFor(OrderReturn $return, array $items): void
    {
        foreach ($items as $item) {
            $lineTotal = bcmul($item['unit_price'], (string) $item['quantity'], 2);

            $return->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $lineTotal,
            ]);
        }
    }

    public function test_returned_goods_credit_cogs_at_cost_not_sale_price(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Worked example from the domain review: sell 10 @ 100 (cost 60), return 2.
        // Net revenue 800; COGS must be 8 x 60 = 480 (not 600); GP 320 (not 200).
        $product = $this->makeProduct('Returned Widget');
        $customer = Customer::create(['name' => 'Return Buyer', 'phone' => '0700000030']);
        $order = $this->orderAt($user, $customer, '2026-08-10 10:00:00', ['total_amount' => '1000.00', 'subtotal' => '1000.00']);
        $this->orderItem($order, $product, 10, '100.00');

        $supplier = Supplier::create(['name' => 'Return Supplier', 'phone' => '0700000031']);
        $this->purchaseItem($user, $supplier, $product, '2026-08-08 10:00:00', 10, '60.00');

        $return = OrderReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'return_date' => '2026-08-11',
            'total_amount' => '200.00',
            'status' => 'completed',
            'currency' => 'AFN',
        ]);
        $this->returnItemsFor($return, [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '100.00']]);

        $response = $this->get('/reports/profit-loss?date_to=2026-08-12');

        $response->assertOk();
        // COGS credited: 8 x 60 = 480, rendered as an expense row with a minus.
        $response->assertSee('>-480.00', false);
        // Gross profit 800 - 480 = 320.
        $response->assertSee('>320.00', false);
        // The sale is no longer costed at the full 600.
        $response->assertDontSee('>-600.00', false);
    }

    public function test_opening_stock_product_is_costed_at_its_afn_pool_price(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Stock entered via the product form (opening stock), never purchased:
        // the pool price is the cost basis (same convention as the backfill
        // command's proxy purchases).
        $product = $this->makeProduct('Ghee Opening Stock');
        $product->update(['price' => '1050.00', 'stock_afn' => 120, 'stock_usd' => 0]);

        $customer = Customer::create(['name' => 'Ghee Buyer', 'phone' => '0700000051']);
        $order = $this->orderAt($user, $customer, '2026-09-03 06:00:00', ['total_amount' => '55000.00', 'subtotal' => '55000.00']);
        $this->orderItem($order, $product, 50, '1100.00');

        $response = $this->get('/reports/profit-loss?date_to=2026-09-03');

        $response->assertOk();
        // Revenue 55,000; COGS 50 x 1,050 = 52,500; gross AND net profit 2,500.
        $response->assertSee('>55,000.00', false);
        $response->assertSee('>-52,500.00', false);
        $response->assertSee('>2,500.00', false);
        $response->assertSee('+2,500.00', false);
        // The sale is no longer flagged as missing cost data.
        $response->assertDontSee(__('messages.no_cost_data'));
    }

    public function test_opening_stock_product_is_costed_at_its_usd_pool_price(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // USD mirror: price stays 0 on the AFN side, price_usd carries the
        // pool price. Never converted across currencies.
        $product = $this->makeProduct('USD Opening Stock Widget');
        $product->update(['price' => '0.00', 'price_usd' => '20.50', 'stock_afn' => 0, 'stock_usd' => 120]);

        $customer = Customer::create(['name' => 'USD Opening Buyer', 'phone' => '0700000052']);
        $order = $this->orderAt($user, $customer, '2026-09-03 06:00:00', ['total_amount' => '42.00', 'subtotal' => '42.00', 'currency' => 'USD']);
        $this->orderItem($order, $product, 2, '21.00');

        $response = $this->get('/reports/profit-loss?date_to=2026-09-03&currency=USD');

        $response->assertOk();
        // Revenue 42; COGS 2 x 20.50 = 41; profit 1.
        $response->assertSee('>42.00', false);
        $response->assertSee('>-41.00', false);
        $response->assertSee('>1.00', false);
        $response->assertSee('+1.00', false);
        $response->assertDontSee(__('messages.no_cost_data'));
    }

    public function test_real_purchases_beat_the_product_price_proxy(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Catalog price 10, but really purchased at 5: the purchase basis
        // must win — no blending with the proxy.
        $product = $this->makeProduct('Real Purchase Beats Proxy');
        $product->update(['price' => '10.00', 'stock_afn' => 10, 'stock_usd' => 0]);

        $supplier = Supplier::create(['name' => 'Real Purchase Supplier', 'phone' => '0700000053']);
        $this->purchaseItem($user, $supplier, $product, '2026-08-10 10:00:00', 10, '5.00');

        $customer = Customer::create(['name' => 'Proxy Beater Buyer', 'phone' => '0700000054']);
        $order = $this->orderAt($user, $customer, '2026-08-11 10:00:00', ['total_amount' => '12.00', 'subtotal' => '12.00']);
        $this->orderItem($order, $product, 1, '12.00');

        $response = $this->get('/reports/profit-loss?date_to=2026-08-12');

        $response->assertOk();
        // COGS is 5 (the purchase), not 10 (the catalog proxy).
        $response->assertSee('>-5.00', false);
        $response->assertDontSee('>-10.00', false);
        $response->assertSee('>7.00', false);
    }

    public function test_cancelled_return_does_not_credit_cogs(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct('Cancelled Return Widget');
        $customer = Customer::create(['name' => 'Cancelled Return Buyer', 'phone' => '0700000034']);
        $order = $this->orderAt($user, $customer, '2026-08-10 10:00:00', ['total_amount' => '1000.00', 'subtotal' => '1000.00']);
        $this->orderItem($order, $product, 10, '100.00');

        $supplier = Supplier::create(['name' => 'Cancelled Return Supplier', 'phone' => '0700000035']);
        $this->purchaseItem($user, $supplier, $product, '2026-08-08 10:00:00', 10, '60.00');

        $return = OrderReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'return_date' => '2026-08-11',
            'total_amount' => '200.00',
            'status' => 'cancelled',
            'currency' => 'AFN',
        ]);
        $this->returnItemsFor($return, [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '100.00']]);

        $response = $this->get('/reports/profit-loss?date_to=2026-08-12');

        $response->assertOk();
        // Cancelled return: full sale stands, revenue 1,000, COGS 600, GP 400.
        // (number_format renders 1000 as "1,000.00" in the view.)
        $response->assertSee('>1,000.00', false);
        $response->assertSee('>-600.00', false);
        $response->assertSee('>400.00', false);
    }

    public function test_return_without_cost_basis_gets_zero_cost_credit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Sold with no purchase history (missing-cost rule: cost 0); a return
        // of such goods credits nothing back — symmetric with the sale rule.
        $product = $this->makeProduct('No Cost Return Widget');
        $customer = Customer::create(['name' => 'No Cost Return Buyer', 'phone' => '0700000036']);
        $order = $this->orderAt($user, $customer, '2026-08-10 10:00:00', ['total_amount' => '100.00', 'subtotal' => '100.00']);
        $this->orderItem($order, $product, 1, '100.00');

        $return = OrderReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'return_date' => '2026-08-11',
            'total_amount' => '100.00',
            'status' => 'completed',
            'currency' => 'AFN',
        ]);
        $this->returnItemsFor($return, [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '100.00']]);

        $response = $this->get('/reports/profit-loss?date_to=2026-08-12');

        $response->assertOk();
        // Revenue nets to zero (rendered "0.00" in the hero and the revenue row);
        // cost credit is zero; both sides drop out of the period.
        $response->assertSee('0.00');
        // The sale line is still flagged as missing cost data.
        $response->assertSee(__('messages.no_cost_data'));
    }

    public function test_all_time_window_includes_orders_older_than_the_current_year(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'All Time Customer', 'phone' => '0700000041']);
        $this->orderAt($user, $customer, '2025-06-15 10:00:00', ['total_amount' => '100.00', 'subtotal' => '100.00']);
        $this->orderAt($user, $customer, '2026-12-31 23:59:59', ['total_amount' => '300.00', 'subtotal' => '300.00']);

        $response = $this->get('/reports/profit-loss?date_to=2026-12-31');

        $response->assertOk();
        // The window reaches back past the year boundary: both orders count.
        $response->assertSee('>400.00', false);
    }

    public function test_all_time_window_counts_early_expense_in_operating_expenses(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct('All Time Expense Widget');
        $customer = Customer::create(['name' => 'All Time Expense Customer', 'phone' => '0700000042']);
        $order = $this->orderAt($user, $customer, '2026-12-10 10:00:00', ['total_amount' => '200.00', 'subtotal' => '200.00']);
        $this->orderItem($order, $product, 1, '200.00');

        // Unlinked expense from January, long before the anchor month —
        // inside the all-time window.
        Expense::create([
            'category' => 'Utilities',
            'amount' => '50.00',
            'currency' => 'AFN',
            'expense_date' => '2026-01-05',
        ]);

        $response = $this->get('/reports/profit-loss?date_to=2026-12-31');

        $response->assertOk();
        $response->assertSee('>-50.00', false);
    }
}
