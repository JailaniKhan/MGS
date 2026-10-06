<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function actingUser(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'inventory@test.dev'],
            ['name' => 'I', 'password' => bcrypt('x')]
        );
        $this->actingAs($user);

        return $user;
    }

    protected function category(): Category
    {
        return Category::create(['name' => 'Grocery']);
    }

    public function test_index_renders_stock_value_summary_and_tabs(): void
    {
        $user = $this->actingUser();
        $category = $this->category();
        Product::create([
            'name' => 'Rice 25kg',
            'category_id' => $category->id,
            'price' => 1500,
            'stock_afn' => 20,
        ]);

        $res = $this->actingAs($user)->get('/inventory');
        $res->assertOk();
        $res->assertSee('Rice 25kg');
        $res->assertSee('product-list');
        // AFN tile values the AFN pool (20 × 1,500).
        $res->assertSee('30,000.00');
    }

    public function test_index_shows_per_currency_pool_pills_and_hides_empty_prices(): void
    {
        $user = $this->actingUser();
        $category = $this->category();

        // USD-only product: no AFN price row may render.
        Product::create([
            'name' => 'Usd Only Widget',
            'category_id' => $category->id,
            'price' => 0,
            'price_usd' => 20.50,
            'stock_afn' => 0,
            'stock_usd' => 80,
        ]);

        // Two-pool product: each pill keeps its currency tag.
        Product::create([
            'name' => 'Dual Pool Widget',
            'category_id' => $category->id,
            'price' => 100,
            'price_usd' => 1.5,
            'stock_afn' => 7,
            'stock_usd' => 5,
        ]);

        $res = $this->actingAs($user)->get('/inventory');
        $res->assertOk();
        $res->assertSee('20.50');
        // Single-pool product: the pill drops the currency prefix — the price
        // chip above already carries it.
        $res->assertDontSee('USD 80');
        // Two-pool products keep tagged pills.
        $res->assertSee('AFN 7');
        $res->assertSee('USD 5');
        // Empty pools disappear instead of rendering a "0" row.
        $res->assertDontSee('AFN 0');
    }

    public function test_lot_numbers_render_as_visible_chips(): void
    {
        $user = $this->actingUser();
        $category = $this->category();
        $product = Product::create([
            'name' => 'Lotted Goods',
            'category_id' => $category->id,
            'price' => 100,
            'stock_afn' => 5,
            'lot_number' => 'LOT-A-4711',
        ]);

        // The synthetic "OPENING" backfill marker never surfaces as the shelf lot.
        DB::table('purchase_items')->insert([
            'purchase_id' => DB::table('purchases')->insertGetId([
                'user_id' => $user->id,
                'person_type' => 'supplier',
                'status' => 'completed',
                'total_amount' => 500,
                'currency' => 'AFN',
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100,
            'subtotal' => 500,
            'lot_number' => 'OPENING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->actingAs($user)->get('/inventory');
        $res->assertOk();
        $res->assertSee('LOT-A-4711', false);
        $res->assertDontSee('OPENING');

        // A later purchase carrying a REAL lot becomes the on-shelf lot for
        // the pool, replacing the master field on the list.
        DB::table('purchase_items')->insert([
            'purchase_id' => DB::table('purchases')->insertGetId([
                'user_id' => $user->id,
                'person_type' => 'supplier',
                'status' => 'completed',
                'total_amount' => 300,
                'currency' => 'AFN',
                'created_at' => now()->addHour(),
                'updated_at' => now()->addHour(),
            ]),
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 100,
            'subtotal' => 300,
            'lot_number' => 'LOT-B-2024',
            'created_at' => now()->addHour(),
            'updated_at' => now()->addHour(),
        ]);

        $res = $this->actingAs($user)->get('/inventory');
        $res->assertOk();
        $res->assertSee('LOT-B-2024', false);
        $res->assertDontSee('LOT-A-4711', false);
    }

    public function test_zero_stock_product_still_shows_its_registered_lot(): void
    {
        $this->actingUser();
        Product::create([
            'name' => 'Empty Shelf Goods',
            'category_id' => $this->category()->id,
            'price' => 50,
            'stock_afn' => 0,
            'stock_usd' => 0,
            'lot_number' => 'REG-77',
        ]);

        $res = $this->actingAs($this->actingUser())->get('/inventory');
        $res->assertOk();
        // With nothing on any shelf, the registered master lot still shows.
        $res->assertSee('REG-77', false);
    }

    public function test_product_rows_link_to_the_details_page(): void
    {
        $user = $this->actingUser();
        $product = Product::create([
            'name' => 'Clickable Goods',
            'category_id' => $this->category()->id,
            'price' => 10,
            'stock_afn' => 4,
        ]);

        $res = $this->actingAs($user)->get('/inventory');
        $res->assertOk();
        $res->assertSee(route('products.show', $product), false);
        $res->assertDontSee(route('products.edit', $product), false);
    }

    public function test_product_store_auto_generates_sequential_lot_numbers(): void
    {
        $this->actingUser();
        $category = $this->category();

        foreach (['First Lot', 'Second Lot'] as $name) {
            $this->post('/products', [
                'name' => $name,
                'category_id' => $category->id,
                'price_currency' => 'AFN',
                'price' => '10',
                'stock_afn' => '1',
                'stock_usd' => '0',
            ])->assertRedirect(route('inventory.index'));
        }

        // Lots are plain numbers starting from 1.
        $this->assertSame('1', Product::where('name', 'First Lot')->value('lot_number'));
        $this->assertSame('2', Product::where('name', 'Second Lot')->value('lot_number'));
    }

    public function test_usd_value_tile_hidden_when_usd_pool_empty(): void
    {
        $user = $this->actingUser();
        $category = $this->category();

        // USD price exists but there is no USD stock: no "0.00 USD" headline tile.
        Product::create([
            'name' => 'Afn Stock Only',
            'category_id' => $category->id,
            'price' => 123,
            'price_usd' => 20.50,
            'stock_afn' => 2,
            'stock_usd' => 0,
        ]);

        $res = $this->actingAs($user)->get('/inventory');
        $res->assertOk();
        // AFN tile values the AFN pool (2 × 123).
        $res->assertSee('246.00');
        // The USD price row still renders on the product card.
        $res->assertSee('20.50');
        // No currency tile may render a zero value.
        $res->assertDontSee('0.00');
    }

    public function test_usd_value_tile_shown_when_usd_pool_has_stock(): void
    {
        $user = $this->actingUser();
        $category = $this->category();

        Product::create([
            'name' => 'Usd Stock Widget',
            'category_id' => $category->id,
            'price' => 0,
            'price_usd' => 20.50,
            'stock_afn' => 0,
            'stock_usd' => 3,
        ]);

        $res = $this->actingAs($user)->get('/inventory');
        $res->assertOk();
        // USD tile values the USD pool (3 × 20.50).
        $res->assertSee('61.50');
    }

    public function test_product_create_renders_with_price_currency_picker_and_back_to_inventory(): void
    {
        $this->actingUser();
        $this->category();

        $res = $this->get('/products/create');
        $res->assertOk();
        // A product is priced in ONE currency: a picker, not two price fields.
        $res->assertSee('name="price_currency"', false);
        $res->assertDontSee('name="price_usd"', false);
        $res->assertSee(route('inventory.index'));
    }

    public function test_product_store_persists_usd_price_and_returns_to_inventory(): void
    {
        $user = $this->actingUser();
        $category = $this->category();

        // A USD-priced product opens its stock in the USD pool only.
        $res = $this->post('/products', [
            'name' => 'Cooking Oil 5L',
            'category_id' => $category->id,
            'price_currency' => 'USD',
            'price' => '12.50',
            'stock_afn' => '0',
            'stock_usd' => '15',
        ]);

        $res->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('products', [
            'user_id' => $user->id,
            'name' => 'Cooking Oil 5L',
            'price_usd' => '12.50',
            'price' => 0,
            'stock_afn' => 0,
            'stock_usd' => 15,
            'stock' => 15,
        ]);
    }

    public function test_product_store_rejects_stock_in_the_other_currencys_pool(): void
    {
        $this->actingUser();
        $category = $this->category();

        $res = $this->post('/products', [
            'name' => 'Cross Pool',
            'category_id' => $category->id,
            'price_currency' => 'USD',
            'price' => '5',
            'stock_afn' => '10',
            'stock_usd' => '0',
        ]);

        $res->assertSessionHasErrors('stock_afn');
        $this->assertDatabaseMissing('products', ['name' => 'Cross Pool']);
    }

    public function test_product_update_rejects_growing_the_other_currencys_pool(): void
    {
        $this->actingUser();
        $product = Product::create([
            'name' => 'Usd Priced',
            'category_id' => $this->category()->id,
            'price' => 0,
            'price_usd' => 5,
            'stock_usd' => 10,
        ]);

        $res = $this->put("/products/{$product->id}", [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'price_currency' => 'USD',
            'price' => '5',
            'stock_afn' => '7',
            'stock_usd' => '10',
        ]);

        $res->assertSessionHasErrors('stock_afn');
        $fresh = $product->fresh();
        $this->assertSame(0, $fresh->stock_afn);
        $this->assertSame(10, $fresh->stock_usd);
    }

    public function test_product_update_moves_stock_when_unpriced_product_picks_a_currency(): void
    {
        $this->actingUser();
        // Unpriced products may be bought in either currency, so this one
        // holds a USD pool before it ever gets a price.
        $product = Product::create([
            'name' => 'Switcher',
            'category_id' => $this->category()->id,
            'price' => 0,
            'stock_usd' => 80,
        ]);

        // Pricing it in AFN carries the 80 units USD -> AFN and zeroes the
        // USD pool (the form's currency switch submits it that way).
        $res = $this->put("/products/{$product->id}", [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'price_currency' => 'AFN',
            'price' => '500',
            'stock_afn' => '80',
            'stock_usd' => '0',
        ]);

        $res->assertRedirect(route('inventory.index'));
        $fresh = $product->fresh();
        $this->assertSame(80, $fresh->stock_afn);
        $this->assertSame(0, $fresh->stock_usd);
        $this->assertSame(80, (int) $fresh->stock);
        // The move is audited as one adjustment movement per pool.
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity_change' => 80,
            'currency' => 'AFN',
            'movement_type' => 'adjustment',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity_change' => -80,
            'currency' => 'USD',
            'movement_type' => 'adjustment',
        ]);
    }

    public function test_product_store_accepts_missing_usd_price(): void
    {
        $this->actingUser();
        $category = $this->category();

        $res = $this->post('/products', [
            'name' => 'Sugar 1kg',
            'category_id' => $category->id,
            'price_currency' => 'AFN',
            'price' => '60',
            'stock_afn' => '10',
            'stock_usd' => '0',
        ]);

        $res->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('products', ['name' => 'Sugar 1kg', 'price_usd' => null, 'price' => 60]);
    }

    public function test_product_store_accepts_missing_afn_price(): void
    {
        $this->actingUser();
        $category = $this->category();

        $res = $this->post('/products', [
            'name' => 'Dish Soap',
            'category_id' => $category->id,
            'price_currency' => 'USD',
            'price' => '2.50',
            'stock_afn' => '0',
            'stock_usd' => '4',
        ]);

        $res->assertRedirect(route('inventory.index'));
        $product = Product::where('name', 'Dish Soap')->first();
        $this->assertNotNull($product);
        $this->assertSame(0.0, (float) $product->price);
        $this->assertEqualsWithDelta(2.50, (float) $product->price_usd, 0.001);
    }

    public function test_product_update_keeps_price_when_left_empty(): void
    {
        $this->actingUser();
        $product = Product::create([
            'name' => 'Detergent',
            'category_id' => $this->category()->id,
            'price' => 120,
            'stock' => 8,
        ]);

        $res = $this->put("/products/{$product->id}", [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'price_currency' => 'AFN',
            'price' => '',
            'stock_afn' => (string) $product->stock_afn,
            'stock_usd' => (string) $product->stock_usd,
        ]);

        $res->assertRedirect(route('inventory.index'));
        $fresh = $product->fresh();
        $this->assertEqualsWithDelta(120, (float) $fresh->price, 0.001);
        $this->assertNull($fresh->price_usd);
    }

    public function test_product_update_persists_usd_price(): void
    {
        $this->actingUser();
        $product = Product::create([
            'name' => 'Tea',
            'category_id' => $this->category()->id,
            'price' => 0,
            'price_usd' => 3,
            'stock_usd' => 5,
        ]);

        $res = $this->put("/products/{$product->id}", [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'price_currency' => 'USD',
            'price' => '4.25',
            'stock_afn' => (string) $product->stock_afn,
            'stock_usd' => (string) $product->stock_usd,
        ]);

        $res->assertRedirect(route('inventory.index'));
        $fresh = $product->fresh();
        $this->assertEqualsWithDelta(4.25, (float) $fresh->price_usd, 0.001);
        // A USD-priced product keeps the AFN side empty.
        $this->assertSame(0.0, (float) $fresh->price);
    }

    public function test_product_update_rejects_switching_a_priced_products_currency(): void
    {
        $this->actingUser();
        $product = Product::create([
            'name' => 'Locked Currency',
            'category_id' => $this->category()->id,
            'price' => 300,
            'stock_afn' => 5,
        ]);

        $res = $this->put("/products/{$product->id}", [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'price_currency' => 'USD',
            'price' => '4.25',
            'stock_afn' => '0',
            'stock_usd' => '5',
        ]);

        $res->assertSessionHasErrors('price_currency');
        $fresh = $product->fresh();
        $this->assertSame(300.0, (float) $fresh->price);
        $this->assertNull($fresh->price_usd);
        $this->assertSame(5, $fresh->stock_afn);
    }

    public function test_category_and_unit_create_back_buttons_target_inventory(): void
    {
        $this->actingUser();

        $this->get('/categories/create')
            ->assertOk()
            ->assertSee(route('inventory.index'));

        $this->get('/units/create')
            ->assertOk()
            ->assertSee(route('inventory.index'));
    }

    public function test_category_and_unit_store_redirect_to_inventory(): void
    {
        $this->actingUser();

        $this->post('/categories', ['name' => 'Beverages'])
            ->assertRedirect(route('inventory.index'));

        $this->post('/units', ['name' => 'Carton', 'short_name' => 'ct'])
            ->assertRedirect(route('inventory.index'));
    }

    public function test_edit_pages_back_buttons_target_inventory(): void
    {
        $this->actingUser();
        $category = $this->category();
        $unit = Unit::create(['name' => 'Kg', 'short_name' => 'kg']);
        $product = Product::create([
            'name' => 'Flour',
            'category_id' => $category->id,
            'price' => 100,
            'stock' => 1,
        ]);

        $this->get("/products/{$product->id}/edit")
            ->assertOk()->assertSee(route('inventory.index'));
        $this->get("/categories/{$category->id}/edit")
            ->assertOk()->assertSee(route('inventory.index'));
        $this->get("/units/{$unit->id}/edit")
            ->assertOk()->assertSee(route('inventory.index'));
    }

    public function test_guests_are_redirected_from_product_show(): void
    {
        $product = Product::create([
            'name' => 'Guest Bait',
            'category_id' => $this->category()->id,
            'price' => 10,
            'stock' => 1,
        ]);

        $this->get("/products/{$product->id}")->assertRedirect(route('login'));
    }

    public function test_product_show_renders_pools_lots_and_movements(): void
    {
        $user = $this->actingUser();
        $unit = Unit::create(['name' => 'Kg', 'short_name' => 'kg']);
        $product = Product::create([
            'name' => 'Shown Goods',
            'category_id' => $this->category()->id,
            'unit_id' => $unit->id,
            'price' => 100,
            'price_usd' => 2,
            'stock_afn' => 7,
            'stock_usd' => 3,
            'lot_number' => 'MASTER-LOT',
        ]);

        // A real purchase gives the AFN pool its on-shelf lot.
        $purchaseId = DB::table('purchases')->insertGetId([
            'user_id' => $user->id,
            'person_type' => 'supplier',
            'status' => 'completed',
            'total_amount' => 700,
            'currency' => 'AFN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('purchase_items')->insert([
            'purchase_id' => $purchaseId,
            'product_id' => $product->id,
            'quantity' => 7,
            'unit_price' => 100,
            'subtotal' => 700,
            'lot_number' => 'SHELF-LOT-9',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        StockMovement::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity_change' => 7,
            'currency' => 'AFN',
            'movement_type' => 'purchase',
            'reference_type' => 'purchase',
            'reference_id' => $purchaseId,
        ]);
        StockMovement::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity_change' => -2,
            'currency' => 'AFN',
            'movement_type' => 'adjustment',
            'reference_type' => 'product',
            'reference_id' => $product->id,
        ]);

        $res = $this->actingAs($user)->get("/products/{$product->id}");

        $res->assertOk();
        // Pools with units + low-stock tinting data.
        $res->assertSee('Shown Goods');
        $res->assertSee(__('messages.current_stock'));
        // The USD pool renders even though it has no purchase history.
        $res->assertSee('3');
        // Per-pool lots: real purchase lot for AFN, master fallback for USD.
        $res->assertSee('SHELF-LOT-9', false);
        $res->assertSee('MASTER-LOT', false);
        // Prices.
        $res->assertSee('100.00');
        $res->assertSee('2.00');
        // Movements: signed quantities and a deep link to the source document;
        // the adjustment (product reference) must not link anywhere.
        $res->assertSee('+7', false);
        $res->assertSee('-2', false);
        $res->assertSee(route('purchases.show', $purchaseId), false);
    }
}
