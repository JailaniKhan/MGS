<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A purchase carries the newest paid price forward to the product master,
 * so the inventory list and the order picker show what was last paid —
 * not the price the product was created with.
 */
class PurchasePriceForwardTest extends TestCase
{
    use RefreshDatabase;

    private function buy(Product $product, string $currency, array $lines): TestResponse
    {
        $supplier = Supplier::create(['name' => 'Price Supplier', 'phone' => '0799000009']);

        return $this->post(route('purchases.store'), [
            'person' => 'supplier:'.$supplier->id,
            'currency' => $currency,
            'products' => $lines,
        ]);
    }

    public function test_afn_purchase_updates_the_products_afn_price(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = Product::create([
            'user_id' => $user->id,
            'name' => 'Cement Bag',
            'category_id' => Category::create(['name' => 'Build'])->id,
            'price' => 2200,
        ]);

        $this->buy($product, 'AFN', [
            ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 1000, 'lot_number' => 'LOT-2'],
        ])->assertRedirect(route('purchases.index'));

        $fresh = $product->fresh();
        $this->assertSame(1000.0, (float) $fresh->price);
        $this->assertNull($fresh->price_usd);
        $this->assertSame('LOT-2', $fresh->lot_number);
        $this->assertSame(5, $fresh->stock_afn);
    }

    public function test_last_line_for_a_product_wins_the_price(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = Product::create([
            'user_id' => $user->id,
            'name' => 'Rod',
            'category_id' => Category::create(['name' => 'Build'])->id,
            'price' => 2200,
        ]);

        $this->buy($product, 'AFN', [
            ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 900, 'lot_number' => 'LOT-A'],
            ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 1100, 'lot_number' => 'LOT-B'],
        ])->assertRedirect(route('purchases.index'));

        $fresh = $product->fresh();
        $this->assertSame(1100.0, (float) $fresh->price);
        $this->assertSame('LOT-B', $fresh->lot_number);
    }

    public function test_usd_purchase_updates_the_products_usd_price_only(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = Product::create([
            'user_id' => $user->id,
            'name' => 'Imported Tool',
            'category_id' => Category::create(['name' => 'Tools'])->id,
            'price' => 0,
            'price_usd' => 20,
        ]);

        $this->buy($product, 'USD', [
            ['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 15.5],
        ])->assertRedirect(route('purchases.index'));

        $fresh = $product->fresh();
        $this->assertSame(15.5, (float) $fresh->price_usd);
        $this->assertSame(0.0, (float) $fresh->price);
    }

    public function test_purchase_never_prices_an_unpriced_product(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = Product::create([
            'user_id' => $user->id,
            'name' => 'No Price Yet',
            'category_id' => Category::create(['name' => 'Loose'])->id,
            'price' => 0,
        ]);

        $this->buy($product, 'AFN', [
            ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 50],
        ])->assertRedirect(route('purchases.index'));

        $fresh = $product->fresh();
        $this->assertSame(0.0, (float) $fresh->price);
        $this->assertNull($fresh->price_usd);

        // Still unpriced, so still buyable in the other currency.
        $this->buy($fresh, 'USD', [
            ['product_id' => $fresh->id, 'quantity' => 2, 'unit_price' => 1],
        ])->assertRedirect(route('purchases.index'));
        $this->assertSame(2, $fresh->fresh()->stock_usd);
    }

    public function test_cancelled_purchase_leaves_the_forwarded_price_in_place(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = Product::create([
            'user_id' => $user->id,
            'name' => 'Wire Roll',
            'category_id' => Category::create(['name' => 'Electric'])->id,
            'price' => 2200,
        ]);

        $this->buy($product, 'AFN', [
            ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1000],
        ])->assertRedirect(route('purchases.index'));

        $purchase = Purchase::latest('id')->first();
        $this->post(route('purchases.status', ['purchase' => $purchase, 'status' => 'cancelled']))
            ->assertRedirect();

        // Forward-only: cancelling gives stock back but never rewrites price.
        $this->assertSame(1000.0, (float) $product->fresh()->price);
    }
}
