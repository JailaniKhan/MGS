<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Sales\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The per-currency stock contract: goods bought with USD cash and goods
 * bought with AFN cash live in separate pools. A sale in one currency must
 * never touch the other pool, and a shortage in the selling currency blocks
 * the sale even when the other pool is full.
 */
class PerCurrencyStockTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(User $user, int $afn = 0, int $usd = 0, ?float $priceUsd = null): Product
    {
        $category = Category::create(['name' => 'Pools']);

        return Product::create([
            'user_id' => $user->id,
            'name' => 'Pool Widget',
            'category_id' => $category->id,
            // A product is priced in exactly one currency: passing a USD
            // price clears the AFN side (mirrors the product form contract).
            'price' => $priceUsd === null ? 100 : 0,
            'price_usd' => $priceUsd,
            'stock_afn' => $afn,
            'stock_usd' => $usd,
        ]);
    }

    private function sell(Product $product, string $currency, int $qty): TestResponse
    {
        $customer = Customer::create(['name' => 'Pool Buyer', 'phone' => '0799000001']);

        return $this->post(route('orders.store'), [
            'person' => 'customer:'.$customer->id,
            'currency' => $currency,
            'products' => [
                ['product_id' => $product->id, 'quantity' => $qty, 'unit_price' => $currency === 'USD' ? 2 : 100],
            ],
        ]);
    }

    private function purchaseRaw(User $user, Product $product, string $currency, int $qty): TestResponse
    {
        $supplier = Supplier::create(['name' => 'Pool Supplier', 'phone' => '0799000002']);

        return $this->post(route('purchases.store'), [
            'person' => 'supplier:'.$supplier->id,
            'currency' => $currency,
            'products' => [
                ['product_id' => $product->id, 'quantity' => $qty, 'unit_price' => $currency === 'USD' ? 1 : 50],
            ],
        ]);
    }

    private function purchase(User $user, Product $product, string $currency, int $qty): void
    {
        $this->purchaseRaw($user, $product, $currency, $qty)->assertRedirect(route('purchases.index'));
    }

    public function test_usd_sale_decrements_only_the_usd_pool(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct($user, afn: 10, usd: 5);

        $this->sell($product, 'USD', 3)->assertRedirect(route('orders.index'));

        $fresh = $product->fresh();
        $this->assertSame(2, $fresh->stock_usd);
        $this->assertSame(10, $fresh->stock_afn);
        // Legacy combined column stays in sync.
        $this->assertSame(12, (int) $fresh->stock);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity_change' => -3,
            'currency' => 'USD',
            'movement_type' => 'sale',
        ]);
    }

    public function test_afn_sale_decrements_only_the_afn_pool(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct($user, afn: 10, usd: 5);

        $this->sell($product, 'AFN', 4)->assertRedirect(route('orders.index'));

        $fresh = $product->fresh();
        $this->assertSame(6, $fresh->stock_afn);
        $this->assertSame(5, $fresh->stock_usd);
    }

    public function test_sale_is_blocked_when_its_pool_is_short_even_if_the_other_pool_is_full(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct($user, afn: 500, usd: 1);

        $this->sell($product, 'USD', 2)->assertSessionHasErrors('products');

        $fresh = $product->fresh();
        $this->assertSame(1, $fresh->stock_usd);
        $this->assertSame(500, $fresh->stock_afn);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $product->id]);
    }

    public function test_purchase_fills_the_pool_of_its_own_currency(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        // USD purchase requires a USD-priced product (currency-match rule).
        $product = $this->makeProduct($user, afn: 0, usd: 0, priceUsd: 2);

        $this->purchase($user, $product, 'USD', 7);

        $fresh = $product->fresh();
        $this->assertSame(7, $fresh->stock_usd);
        $this->assertSame(0, $fresh->stock_afn);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity_change' => 7,
            'currency' => 'USD',
            'movement_type' => 'purchase',
        ]);
    }

    public function test_purchase_is_blocked_when_currency_does_not_match_the_product_price(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $usdProduct = $this->makeProduct($user, afn: 0, usd: 5, priceUsd: 2);
        $afnProduct = $this->makeProduct($user, afn: 5, usd: 0);

        // AFN purchase of a $-priced product.
        $this->purchaseRaw($user, $usdProduct, 'AFN', 3)->assertSessionHasErrors('products');
        // $ purchase of an AFN-priced product.
        $this->purchaseRaw($user, $afnProduct, 'USD', 3)->assertSessionHasErrors('products');

        // Nothing was created and no pool moved — validation runs before
        // any stock movement, even with a valid line ahead of a bad one.
        $this->assertSame(5, $usdProduct->fresh()->stock_usd);
        $this->assertSame(0, $usdProduct->fresh()->stock_afn);
        $this->assertSame(5, $afnProduct->fresh()->stock_afn);
        $this->assertSame(0, $afnProduct->fresh()->stock_usd);
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseMissing('stock_movements', ['movement_type' => 'purchase']);
    }

    public function test_mixed_cart_is_rejected_before_any_stock_moves(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $afnProduct = $this->makeProduct($user, afn: 0, usd: 0);
        $usdProduct = $this->makeProduct($user, afn: 0, usd: 0, priceUsd: 2);
        $supplier = Supplier::create(['name' => 'Pool Supplier', 'phone' => '0799000002']);

        // First line matches the AFN document, second does not — the whole
        // purchase must fail without the first line touching the AFN pool.
        $this->post(route('purchases.store'), [
            'person' => 'supplier:'.$supplier->id,
            'currency' => 'AFN',
            'products' => [
                ['product_id' => $afnProduct->id, 'quantity' => 4, 'unit_price' => 50],
                ['product_id' => $usdProduct->id, 'quantity' => 2, 'unit_price' => 50],
            ],
        ])->assertSessionHasErrors('products');

        $this->assertSame(0, $afnProduct->fresh()->stock_afn);
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseMissing('stock_movements', ['movement_type' => 'purchase']);
    }

    public function test_unpriced_product_can_be_purchased_in_either_currency(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = Category::create(['name' => 'Unpriced']);
        $product = Product::create([
            'name' => 'No Price Yet',
            'category_id' => $category->id,
            'price' => 0,
        ]);

        $this->purchase($user, $product, 'AFN', 4);
        $this->purchase($user, $product, 'USD', 3);

        $fresh = $product->fresh();
        $this->assertSame(4, $fresh->stock_afn);
        $this->assertSame(3, $fresh->stock_usd);
    }

    public function test_sales_return_restocks_the_currency_it_was_sold_in(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct($user, afn: 10, usd: 10);

        $this->sell($product, 'USD', 4)->assertRedirect(route('orders.index'));
        $this->assertSame(6, $product->fresh()->stock_usd);

        $order = Order::latest('id')->first();
        app(ReturnService::class)->createReturn(
            direction: 'order',
            parent: $order,
            items: [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 2]],
            returnDate: now()->toDateString(),
        );

        $fresh = $product->fresh();
        $this->assertSame(8, $fresh->stock_usd);
        $this->assertSame(10, $fresh->stock_afn);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity_change' => 2,
            'currency' => 'USD',
            'movement_type' => 'return',
        ]);
    }

    public function test_purchase_return_removes_from_that_currencys_pool(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct($user, afn: 0, usd: 0);

        $this->purchase($user, $product, 'AFN', 9);
        $this->assertSame(9, $product->fresh()->stock_afn);

        $purchase = Purchase::latest('id')->first();
        $itemId = PurchaseItem::where('purchase_id', $purchase->id)->value('id');

        app(ReturnService::class)->createReturn(
            direction: 'purchase',
            parent: $purchase,
            items: [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 50]],
            returnDate: now()->toDateString(),
        );
        $this->assertSame($itemId, PurchaseItem::where('purchase_id', $purchase->id)->value('id'));

        $fresh = $product->fresh();
        $this->assertSame(6, $fresh->stock_afn);
        $this->assertSame(0, $fresh->stock_usd);
    }

    public function test_cancelling_an_order_restores_its_own_currency_pool(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct($user, afn: 10, usd: 10);

        $this->sell($product, 'AFN', 5)->assertRedirect(route('orders.index'));
        $this->assertSame(5, $product->fresh()->stock_afn);

        $order = Order::latest('id')->first();
        $this->post(route('orders.status', ['order' => $order, 'status' => 'cancelled']))
            ->assertRedirect();

        $fresh = $product->fresh();
        $this->assertSame(10, $fresh->stock_afn);
        $this->assertSame(10, $fresh->stock_usd);

        $this->assertSame(
            2,
            StockMovement::where('product_id', $product->id)->where('currency', 'AFN')->count()
        );
    }
}
