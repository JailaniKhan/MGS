<?php

namespace Tests\Feature\Reports;

use App\Models\CashbookEntry;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchasePayment;
use App\Models\SalaryPayment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceSheetTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(User $user, array $overrides = []): Product
    {
        $category = Category::create(['name' => 'Fixture Category']);

        return Product::create(array_merge([
            'name' => 'Fixture Product',
            'category_id' => $category->id,
            'price' => '0.00',
            'stock' => 10,
        ], $overrides));
    }

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

    private function addPurchaseItem(Purchase $purchase, Product $product, int $quantity, string $unitPrice): void
    {
        $lineTotal = bcmul((string) $quantity, $unitPrice, 2);
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $lineTotal,
        ]);
        $purchase->update([
            'subtotal' => bcadd((string) $purchase->subtotal, $lineTotal, 2),
            'total_amount' => bcadd((string) $purchase->total_amount, $lineTotal, 2),
        ]);
    }

    private function addOrderItem(Order $order, Product $product, int $quantity, string $unitPrice): void
    {
        $lineTotal = bcmul((string) $quantity, $unitPrice, 2);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $lineTotal,
        ]);
        $order->update([
            'subtotal' => bcadd((string) $order->subtotal, $lineTotal, 2),
            'total_amount' => bcadd((string) $order->total_amount, $lineTotal, 2),
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/reports/balance-sheet')->assertRedirect(route('login'));
    }

    public function test_page_renders_sections_and_currency_filter(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/reports/balance-sheet');

        $response->assertOk();
        $response->assertSee(__('messages.balance_sheet'));
        $response->assertSee(__('messages.assets'));
        $response->assertSee(__('messages.liabilities'));
        $response->assertSee(__('messages.capital'));
        $response->assertSee(__('messages.cash_in_hand'));
        $response->assertSee(__('messages.customer_receivables'));
        $response->assertSee(__('messages.inventory_value'));
        $response->assertSee(__('messages.owner_capital'));
        $response->assertSee(__('messages.retained_earnings'));
        $response->assertSee(__('messages.afn'));
        $response->assertSee(__('messages.usd'));
    }

    public function test_assets_equal_liabilities_plus_equity_when_all_sources_are_present(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct($user, ['name' => 'Identity Product', 'stock' => 10]);
        $customer = Customer::create(['name' => 'Identity Customer', 'phone' => '0700000001']);
        $supplier = Supplier::create(['name' => 'Identity Supplier', 'phone' => '0700000002']);

        $order = $this->makeOrder($user, $customer);
        $this->addOrderItem($order, $product, 10, '100.00'); // order total 1,000.00
        Payment::create(['order_id' => $order->id, 'amount' => '400.00', 'currency' => 'AFN']);

        $purchase = $this->makePurchase($user, $supplier);
        $this->addPurchaseItem($purchase, $product, 10, '30.00'); // purchase total 300.00
        PurchasePayment::create(['purchase_id' => $purchase->id, 'amount' => '100.00', 'currency' => 'AFN']);

        CashbookEntry::create(['type' => 'in', 'amount' => '50.00', 'currency' => 'AFN', 'entry_date' => '2026-08-10']);
        CashbookEntry::create(['type' => 'out', 'amount' => '30.00', 'currency' => 'AFN', 'entry_date' => '2026-08-11']);
        Expense::create(['category' => 'Utilities', 'amount' => '20.00', 'currency' => 'AFN', 'expense_date' => '2026-08-12']);

        $response = $this->get('/reports/balance-sheet');

        $response->assertOk();
        // cash = 400 + 50 in − (100 + 30 + 20) out = 300
        $response->assertViewHas('cashBalance', '300.00');
        // receivables = 1,000 − 400 = 600
        $response->assertViewHas('receivables', '600.00');
        // inventory = 10 stock × 30.00 avg = 300
        $response->assertViewHas('inventoryValue', '300.00');
        $response->assertViewHas('totalAssets', '1200.00');
        // payables = 300 − 100 = 200
        $response->assertViewHas('payables', '200.00');
        // revenue = 1,000 + 50 cash-in; cogs = 300 − 300 inventory = 0; operating = 30 + 20 = 50
        $response->assertViewHas('retainedEarnings', '1000.00');
        $response->assertViewHas('totalLiabilitiesEquity', '1200.00');
    }

    public function test_foreign_currency_payment_does_not_offset_receivable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct($user, ['name' => 'Cross Currency Product', 'stock' => 0]);
        $customer = Customer::create(['name' => 'Cross Currency Customer', 'phone' => '0700000003']);

        $order = $this->makeOrder($user, $customer);
        $this->addOrderItem($order, $product, 1, '500.00'); // order total 500.00 AFN
        // USD payment must NOT reduce the AFN receivable
        Payment::create(['order_id' => $order->id, 'amount' => '500.00', 'currency' => 'USD']);

        $response = $this->get('/reports/balance-sheet');

        $response->assertOk();
        $response->assertViewHas('receivables', '500.00');
        $response->assertViewHas('cashBalance', '0.00');
    }

    public function test_foreign_currency_purchase_payment_does_not_offset_payable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct($user, ['name' => 'Cross Currency Supplier Product', 'stock' => 0]);
        $supplier = Supplier::create(['name' => 'Cross Currency Supplier', 'phone' => '0700000004']);

        $purchase = $this->makePurchase($user, $supplier);
        $this->addPurchaseItem($purchase, $product, 1, '400.00'); // purchase total 400.00 AFN
        // USD payment must NOT reduce the AFN payable
        PurchasePayment::create(['purchase_id' => $purchase->id, 'amount' => '400.00', 'currency' => 'USD']);

        $response = $this->get('/reports/balance-sheet');

        $response->assertOk();
        $response->assertViewHas('payables', '400.00');
        $response->assertViewHas('cashBalance', '0.00');
    }

    public function test_inventory_value_uses_quantity_weighted_average(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct($user, ['name' => 'Weighted Product', 'stock' => 10]);
        $supplier = Supplier::create(['name' => 'Weighted Supplier', 'phone' => '0700000005']);

        $this->addPurchaseItem($this->makePurchase($user, $supplier), $product, 1, '100.00');
        $this->addPurchaseItem($this->makePurchase($user, $supplier), $product, 100, '10.00');

        $response = $this->get('/reports/balance-sheet');

        $response->assertOk();
        // Weighted average = (1×100 + 100×10) / 101 = 1100/101 = 10.89 → stock 10 × 10.89 = 108.90.
        // A plain AVG would have produced 55.00 → 550.00.
        $response->assertViewHas('inventoryValue', '108.90');
        $response->assertSee('108.90');
        $response->assertDontSee('550.00');
    }

    public function test_cancelled_purchases_are_excluded_from_inventory_average(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct($user, ['name' => 'Cancelled Product', 'stock' => 10]);
        $supplier = Supplier::create(['name' => 'Cancelled Supplier', 'phone' => '0700000006']);

        $this->addPurchaseItem($this->makePurchase($user, $supplier, ['status' => 'cancelled']), $product, 10, '90.00');
        $this->addPurchaseItem($this->makePurchase($user, $supplier), $product, 10, '10.00');

        $response = $this->get('/reports/balance-sheet');

        $response->assertOk();
        // Only the 10.00 purchase counts.
        $response->assertViewHas('inventoryValue', '100.00');
        $response->assertDontSee('900.00');
    }

    public function test_other_users_data_never_leaks_into_the_report(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userB);
        $productB = $this->makeProduct($userB, ['name' => 'Other User Product', 'stock' => 99]);
        $customerB = Customer::create(['name' => 'Other User Customer', 'phone' => '0700000007']);
        $supplierB = Supplier::create(['name' => 'Other User Supplier', 'phone' => '0700000008']);
        $orderB = $this->makeOrder($userB, $customerB);
        $this->addOrderItem($orderB, $productB, 1, '9999.00');
        Payment::create(['order_id' => $orderB->id, 'amount' => '1.00', 'currency' => 'AFN']);
        $purchaseB = $this->makePurchase($userB, $supplierB);
        $this->addPurchaseItem($purchaseB, $productB, 99, '999.00');
        CashbookEntry::create(['type' => 'in', 'amount' => '777.00', 'currency' => 'AFN', 'entry_date' => '2026-08-10']);
        Expense::create(['category' => 'Utilities', 'amount' => '666.00', 'currency' => 'AFN', 'expense_date' => '2026-08-11']);
        $employeeB = Employee::create(['name' => 'Other User Staff', 'phone' => '0700000009']);
        SalaryPayment::create(['employee_id' => $employeeB->id, 'amount' => '555.00', 'currency' => 'AFN', 'for_month' => '2026-08-01']);

        $this->actingAs($userA);
        $this->makeProduct($userA, ['name' => 'My Product', 'stock' => 1]);

        $response = $this->get('/reports/balance-sheet');

        $response->assertOk();
        // The page is aggregate-only (no product names rendered), so the real leak
        // check is that userB's money never appears in userA's figures.
        $response->assertViewHas('cashBalance', '0.00');
        $response->assertViewHas('receivables', '0.00');
        $response->assertViewHas('payables', '0.00');
        $response->assertViewHas('inventoryValue', '0.00');
        $response->assertViewHas('retainedEarnings', '0.00');
        $response->assertViewHas('totalAssets', '0.00');
        $response->assertViewHas('totalLiabilitiesEquity', '0.00');
    }

    public function test_currency_filter_keeps_balances_separate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct($user, ['name' => 'Dual Currency Product', 'stock' => 0]);
        $customer = Customer::create(['name' => 'Dual Currency Customer', 'phone' => '0700000010']);
        $supplier = Supplier::create(['name' => 'Dual Currency Supplier', 'phone' => '0700000011']);

        $orderAfn = $this->makeOrder($user, $customer);
        $this->addOrderItem($orderAfn, $product, 1, '100.00');
        Payment::create(['order_id' => $orderAfn->id, 'amount' => '100.00', 'currency' => 'AFN']);

        $orderUsd = $this->makeOrder($user, $customer, ['currency' => 'USD']);
        $this->addOrderItem($orderUsd, $product, 1, '50.00');
        Payment::create(['order_id' => $orderUsd->id, 'amount' => '50.00', 'currency' => 'USD']);

        $purchaseUsd = $this->makePurchase($user, $supplier, ['currency' => 'USD']);
        $this->addPurchaseItem($purchaseUsd, $product, 1, '20.00');

        $afnResponse = $this->get('/reports/balance-sheet');
        $afnResponse->assertOk();
        $afnResponse->assertViewHas('cashBalance', '100.00');
        $afnResponse->assertViewHas('payables', '0.00');
        $afnResponse->assertSee('>100.00', false);
        $afnResponse->assertDontSee('>50.00');

        $usdResponse = $this->get('/reports/balance-sheet?currency=USD');
        $usdResponse->assertOk();
        $usdResponse->assertViewHas('cashBalance', '50.00');
        $usdResponse->assertViewHas('payables', '20.00');
        $usdResponse->assertViewHas('receivables', '0.00');
        $usdResponse->assertSee('>50.00', false);
        $usdResponse->assertSee('>20.00', false);
        $usdResponse->assertDontSee('>100.00');
    }
}
