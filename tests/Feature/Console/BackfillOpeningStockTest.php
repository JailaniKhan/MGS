<?php

namespace Tests\Feature\Console;

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
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackfillOpeningStockTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(string $name, string $price = '20.50', ?string $priceUsd = null): Product
    {
        $category = Category::create(['name' => 'General']);

        return Product::create([
            'name' => $name,
            'category_id' => $category->id,
            'price' => $price,
            'price_usd' => $priceUsd,
        ]);
    }

    private function sellProduct(User $user, Product $product, string $currency, string $unitPrice): void
    {
        $customer = Customer::create(['name' => 'Buyer '.$currency, 'phone' => '0700000'.random_int(10, 99)]);
        // user_id is not fillable and there is no auth context here, so set it
        // directly — the profit service scopes sales by orders.user_id.
        $order = new Order([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => bcmul($unitPrice, '1', 2),
            'total_amount' => bcmul($unitPrice, '1', 2),
            'currency' => $currency,
        ]);
        $order->user_id = $user->id;
        $order->save();

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'subtotal' => bcmul($unitPrice, '1', 2),
        ]);
    }

    public function test_creates_opening_purchase_for_sold_product_without_cost(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct('Gadget', '20.50', '20.50');

        // Sold in USD but never purchased anywhere -> zero cost basis.
        $this->sellProduct($user, $product, 'USD', '20.70');

        $this->artisan('purchases:backfill-opening-stock', ['--user-id' => $user->id, '--force' => true])
            ->assertSuccessful();

        // The product gets backfilled in BOTH currencies; assert the USD line.
        $item = PurchaseItem::where('product_id', $product->id)
            ->whereHas('purchase', fn ($q) => $q->where('currency', 'USD'))
            ->first();
        $this->assertNotNull($item);
        $this->assertSame('20.50', number_format((float) $item->unit_price, 2, '.', ''));
        // No lot on the product -> no placeholder lot on the backfilled line.
        $this->assertNull($item->lot_number);

        $purchase = $item->purchase;
        $this->assertSame($user->id, (int) $purchase->user_id);
        $this->assertSame('USD', $purchase->currency);
        $this->assertSame('completed', $purchase->status);
        // Explicit price_usd on the product; qty = max(stock=0, 1) = 1.
        $this->assertSame('20.50', number_format((float) $purchase->total_amount, 2, '.', ''));
    }

    public function test_backfilled_line_inherits_the_products_current_lot(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct('Lotted', '30.00');
        $product->update(['lot_number' => '7']);

        $this->sellProduct($user, $product, 'AFN', '40.00');

        $this->artisan('purchases:backfill-opening-stock', [
            '--user-id' => $user->id,
            '--currency' => ['AFN'],
            '--force' => true,
        ])->assertSuccessful();

        $item = PurchaseItem::where('product_id', $product->id)->first();
        $this->assertNotNull($item);
        $this->assertSame('7', $item->lot_number);
    }

    public function test_usd_line_is_skipped_when_only_an_afn_price_exists(): void
    {
        $user = User::factory()->create();
        // price_usd deliberately NULL: an AFN price must never become a USD cost.
        $product = $this->makeProduct('Afn Only', '15000.00');

        $this->sellProduct($user, $product, 'USD', '20.70');

        $this->artisan('purchases:backfill-opening-stock', ['--user-id' => $user->id, '--force' => true])
            ->expectsOutputToContain('no USD-specific price set')
            ->assertSuccessful();

        // The AFN side may legitimately get a line (price 15000 exists there);
        // what must NEVER happen is a USD line derived from the AFN price.
        $this->assertSame(0, PurchaseItem::where('product_id', $product->id)
            ->whereHas('purchase', fn ($q) => $q->where('currency', 'USD'))
            ->count());
    }

    public function test_product_id_filter_limits_the_backfill(): void
    {
        $user = User::factory()->create();
        $wanted = $this->makeProduct('Wanted', '10.00', '2.00');
        $ignored = $this->makeProduct('Ignored', '99.00', '9.00');

        $this->sellProduct($user, $wanted, 'USD', '12.00');
        $this->sellProduct($user, $ignored, 'USD', '50.00');

        $this->artisan('purchases:backfill-opening-stock', [
            '--user-id' => $user->id,
            '--currency' => ['USD'],
            '--product-id' => [$wanted->id],
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(1, PurchaseItem::where('product_id', $wanted->id)->count());
        $this->assertSame(0, PurchaseItem::where('product_id', $ignored->id)->count());
    }

    public function test_rejects_unknown_product_ids(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct('Real One', '5.00', '1.00');
        $this->sellProduct($user, $product, 'AFN', '6.00');

        $this->artisan('purchases:backfill-opening-stock', [
            '--user-id' => $user->id,
            '--product-id' => [424242],
            '--force' => true,
        ])->assertFailed();

        $this->assertSame(0, PurchaseItem::count());
    }

    public function test_backfilled_cost_feeds_profit_loss_page(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct('Margin Widget', '5.00');

        $this->sellProduct($user, $product, 'AFN', '200.00');

        $this->artisan('purchases:backfill-opening-stock', ['--user-id' => $user->id, '--force' => true])
            ->assertSuccessful();

        $response = $this->actingAs($user)->get('/reports/profit-loss');
        $response->assertOk();
        // Bought (proxy) at 5.00, sold at 200.00 -> profit +195.00, COGS row -5.00.
        $response->assertSee('>195.00', false);
        $response->assertSee('>-5.00', false);
    }

    public function test_skips_products_that_already_have_a_cost_basis(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct('Already Costed');
        $this->sellProduct($user, $product, 'AFN', '100.00');

        $supplier = new Supplier(['name' => 'Opening Stock', 'phone' => '']);
        $supplier->user_id = $user->id;
        $supplier->save();

        $existing = new Purchase([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '50.00',
            'total_amount' => '50.00',
            'currency' => 'AFN',
        ]);
        // No auth context in this test, so set the owner explicitly.
        $existing->user_id = $user->id;
        $existing->save();
        PurchaseItem::create([
            'purchase_id' => $existing->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => '5.00',
            'subtotal' => '50.00',
        ]);

        $before = PurchaseItem::count();

        Artisan::call('purchases:backfill-opening-stock', [
            '--user-id' => $user->id,
            '--currency' => ['AFN'],
            '--force' => true,
        ]);
        $output = Artisan::output();

        $this->assertStringContainsString('already has a cost basis', $output);
        $this->assertSame($before, PurchaseItem::count());
    }

    public function test_unit_cost_override_applies_to_every_line(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct('Override Me', '999.00');

        $this->sellProduct($user, $product, 'AFN', '1000.00');

        $this->artisan('purchases:backfill-opening-stock', [
            '--user-id' => $user->id,
            '--unit-cost' => '7.25',
            '--force' => true,
        ])->assertSuccessful();

        $item = PurchaseItem::where('product_id', $product->id)->first();
        $this->assertSame('7.25', number_format((float) $item->unit_price, 2, '.', ''));
    }

    public function test_skips_zero_price_products_without_override(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct('Freebie', '0.00');

        $this->sellProduct($user, $product, 'AFN', '100.00');

        $this->artisan('purchases:backfill-opening-stock', ['--user-id' => $user->id, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(0, PurchaseItem::where('product_id', $product->id)->count());
    }
}
