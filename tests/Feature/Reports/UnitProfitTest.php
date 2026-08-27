<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per-unit profit/loss on /spend-breakdown: cost is the weighted average
 * purchase price per product PER CURRENCY, sales are matched only against
 * cost in their own currency, and lines without cost data are flagged.
 */
class UnitProfitTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(string $name): Product
    {
        $category = Category::create(['name' => 'PL Category']);

        return Product::create([
            'name' => $name,
            'category_id' => $category->id,
            'price' => '0.00',
            'stock' => 100,
        ]);
    }

    private function makePurchase(string $currency = 'USD', string $status = 'completed'): Purchase
    {
        $supplier = Supplier::create(['name' => 'PL Supplier', 'phone' => '0700000010']);

        return Purchase::create([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => $status,
            'subtotal' => '0.00',
            'total_amount' => '0.00',
            'currency' => $currency,
        ]);
    }

    private function addPurchaseItem(Purchase $purchase, Product $product, int $quantity, string $unitPrice): void
    {
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => bcmul((string) $quantity, $unitPrice, 2),
        ]);
    }

    private function makeOrder(string $currency = 'USD', string $status = 'completed'): Order
    {
        $customer = Customer::create(['name' => 'PL Customer', 'phone' => null]);

        return Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => $status,
            'subtotal' => '0.00',
            'total_amount' => '0.00',
            'currency' => $currency,
        ]);
    }

    private function addOrderItem(Order $order, Product $product, int $quantity, string $unitPrice): void
    {
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => bcmul((string) $quantity, $unitPrice, 2),
        ]);
    }

    public function test_profit_and_loss_per_unit_against_weighted_average_cost(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Bought at 20.5, sold once at 20.7 (+0.2) and once at 20 (-0.5).
        $product = $this->makeProduct('Scenario Product');
        $this->addPurchaseItem($this->makePurchase('USD'), $product, 10, '20.50');
        $this->addOrderItem($this->makeOrder('USD'), $product, 1, '20.70');
        $this->addOrderItem($this->makeOrder('USD'), $product, 1, '20.00');

        $response = $this->get('/spend-breakdown?period=month&date_from='.now()->toDateString());

        $response->assertOk();
        $response->assertSee('+0.20$', false);
        $response->assertSee('-0.50$', false);
        // Net: +0.20 - 0.50 = -0.30 on revenue 40.70 vs cost 41.00.
        $response->assertSee('-0.30$', false);
        $response->assertSee('40.70');
        $response->assertSee('41.00');
    }

    public function test_cost_is_quantity_weighted_not_simple_average(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct('Weighted PL Product');
        // Weighted avg = (1×100 + 99×10) / 100 = 10.90. A plain AVG would be 55.
        $this->addPurchaseItem($this->makePurchase('USD'), $product, 1, '100.00');
        $this->addPurchaseItem($this->makePurchase('USD'), $product, 99, '10.00');
        $this->addOrderItem($this->makeOrder('USD'), $product, 1, '11.00');

        $response = $this->get('/spend-breakdown?period=month&date_from='.now()->toDateString());

        $response->assertOk();
        // Profit = 11.00 - 10.90 = +0.10 (would be -44.00 with a plain AVG).
        $response->assertSee('+0.10$', false);
        $response->assertDontSee('-44.00', false);
    }

    public function test_currencies_are_never_mixed_or_converted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Product bought ONLY in AFN but sold in USD: no comparable cost,
        // the USD line must be flagged, not matched against the AFN cost.
        $product = $this->makeProduct('Cross Currency Product');
        $this->addPurchaseItem($this->makePurchase('AFN'), $product, 10, '700.00');
        $this->addOrderItem($this->makeOrder('USD'), $product, 1, '10.00');

        $response = $this->get('/spend-breakdown?period=month&date_from='.now()->toDateString());

        $response->assertOk();
        $response->assertSee(__('messages.no_cost_data'));
        // No USD profit figure may be derived from the AFN cost.
        $response->assertDontSee('+10.00$', false);
    }

    public function test_sale_without_any_purchase_history_is_flagged_and_excluded(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct('Never Purchased Product');
        $this->addOrderItem($this->makeOrder('USD'), $product, 2, '15.00');

        $response = $this->get('/spend-breakdown?period=month&date_from='.now()->toDateString());

        $response->assertOk();
        $response->assertSee(__('messages.no_cost_data'));
        // Revenue still counts, but no cost/profit is invented.
        $response->assertSee('30.00');
        $response->assertDontSee('+30.00$', false);
    }

    public function test_cancelled_orders_and_purchases_are_excluded(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->makeProduct('Cancelled PL Product');
        // Cancelled purchase at a wild price must not skew the average.
        $this->addPurchaseItem($this->makePurchase('USD', 'cancelled'), $product, 1, '999.00');
        $this->addPurchaseItem($this->makePurchase('USD'), $product, 1, '10.00');
        // Cancelled sale must not appear at all.
        $this->addOrderItem($this->makeOrder('USD', 'cancelled'), $product, 1, '50.00');
        $this->addOrderItem($this->makeOrder('USD'), $product, 1, '12.00');

        $response = $this->get('/spend-breakdown?period=month&date_from='.now()->toDateString());

        $response->assertOk();
        // Only the live sale: 12.00 - 10.00 = +2.00.
        $response->assertSee('+2.00$', false);
        $response->assertDontSee('999.00');
        $response->assertDontSee('+40.00$', false);
    }

    public function test_other_users_sales_do_not_leak_into_the_report(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userB);
        $productB = $this->makeProduct('Other User PL Product');
        $this->addPurchaseItem($this->makePurchase('USD'), $productB, 1, '5.00');
        $this->addOrderItem($this->makeOrder('USD'), $productB, 1, '9.00');

        $this->actingAs($userA);
        $response = $this->get('/spend-breakdown?period=month&date_from='.now()->toDateString());

        $response->assertOk();
        $response->assertDontSee('Other User PL Product');
        $response->assertDontSee('+4.00$', false);
    }

    public function test_section_is_hidden_when_there_are_no_sales(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/spend-breakdown');

        $response->assertOk();
        $response->assertDontSee(__('messages.unit_profit_loss'));
    }
}
