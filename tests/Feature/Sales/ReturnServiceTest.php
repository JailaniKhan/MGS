<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Sales\ReturnService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReturnServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderWithItems(User $user, Customer $customer, string $currency, array $lines): Order
    {
        $order = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '0.00',
            'currency' => $currency,
        ]);

        $subtotal = '0.00';
        foreach ($lines as $line) {
            $lineTotal = bcmul((string) $line['unit_price'], (string) $line['quantity'], 2);
            $subtotal = bcadd($subtotal, $lineTotal, 2);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $line['product']->id,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'subtotal' => $lineTotal,
            ]);
        }

        $order->update(['subtotal' => $subtotal, 'total_amount' => $subtotal]);

        return $order->fresh();
    }

    public function test_create_return_for_order_writes_return_items_and_moves_stock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'Test Customer', 'phone' => '0700000000']);

        $category = Category::create(['name' => 'Test Category']);

        $product = Product::create([
            'name' => 'Widget',
            'category_id' => $category->id,
            'price' => '100.00',
            'stock' => 5,
        ]);

        $order = $this->makeOrderWithItems($user, $customer, 'AFN', [
            ['product' => $product, 'unit_price' => '100.00', 'quantity' => 3],
        ]);

        $service = app(ReturnService::class);

        $return = $service->createReturn(
            direction: 'order',
            parent: $order,
            items: [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '100.00'],
            ],
            returnDate: '2026-08-10',
            reason: 'damaged in transit',
            status: 'completed',
        );

        $this->assertInstanceOf(OrderReturn::class, $return);
        $this->assertSame('AFN', $return->currency);
        $this->assertSame('200.00', $return->total_amount);
        $this->assertSame('damaged in transit', $return->reason);
        $this->assertSame('completed', $return->status);

        $this->assertDatabaseHas('order_return_items', [
            'order_return_id' => $return->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => '100.00',
            'subtotal' => '200.00',
        ]);

        $this->assertSame(7, (int) Product::find($product->id)->stock);

        $this->assertDatabaseHas('stock_movements', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity_change' => 2,
            'movement_type' => 'return',
            'reference_type' => 'order_return',
            'reference_id' => $return->id,
        ]);

        $movement = StockMovement::where('reference_id', $return->id)->first();
        $this->assertNull($movement->journal_entry_id);
    }

    public function test_create_return_for_order_rejects_phantom_returns_with_no_state_written(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'Phantom Customer', 'phone' => '0700000001']);
        $category = Category::create(['name' => 'Phantom Category']);
        $product = Product::create([
            'name' => 'Gadget',
            'category_id' => $category->id,
            'price' => '50.00',
            'stock' => 0,
        ]);

        $order = $this->makeOrderWithItems($user, $customer, 'AFN', [
            ['product' => $product, 'unit_price' => '50.00', 'quantity' => 3],
        ]);

        OrderReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'return_date' => '2026-08-09',
            'reason' => 'first return',
            'total_amount' => '100.00',
            'status' => 'completed',
            'currency' => 'AFN',
        ])->items()->createMany([
            ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '50.00', 'subtotal' => '100.00'],
        ]);

        $service = app(ReturnService::class);

        try {
            $service->createReturn(
                direction: 'order',
                parent: $order,
                items: [
                    ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '50.00'],
                ],
                returnDate: '2026-08-10',
            );
            $this->fail('Expected ValidationException for phantom return was not thrown.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Gadget', implode(' ', $e->validator->getMessageBag()->all()));
        }

        $this->assertSame(1, OrderReturn::where('order_id', $order->id)->count());
        $this->assertSame(1, OrderReturnItem::count());
        $this->assertSame(0, StockMovement::where('movement_type', 'return')->count());
    }

    public function test_create_return_uses_bcmath_scale_2_so_float_arithmetically_tricky_line_totals_are_exact(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'Math Customer', 'phone' => '0700000002']);
        $category = Category::create(['name' => 'Math Category']);
        $product = Product::create([
            'name' => 'Fractional Widget',
            'category_id' => $category->id,
            'price' => '0.10',
            'stock' => 0,
        ]);

        $order = $this->makeOrderWithItems($user, $customer, 'AFN', [
            ['product' => $product, 'unit_price' => '0.10', 'quantity' => 3],
        ]);

        $service = app(ReturnService::class);

        $service->createReturn(
            direction: 'order',
            parent: $order,
            items: [
                ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => '0.1'],
            ],
            returnDate: '2026-08-10',
        );

        $return = OrderReturn::where('order_id', $order->id)->first();
        $this->assertSame('0.30', $return->total_amount);
        $this->assertSame('0.30', $return->items->first()->subtotal);
        $this->assertSame('0.10', $return->items->first()->unit_price);
    }

    public function test_create_return_rolls_back_stock_and_items_when_a_later_write_throws(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'Rollback Customer', 'phone' => '0700000003']);
        $category = Category::create(['name' => 'Rollback Category']);
        $product = Product::create([
            'name' => 'Rollback Widget',
            'category_id' => $category->id,
            'price' => '25.00',
            'stock' => 10,
        ]);

        $order = $this->makeOrderWithItems($user, $customer, 'AFN', [
            ['product' => $product, 'unit_price' => '25.00', 'quantity' => 4],
        ]);

        OrderReturnItem::creating(function () {
            throw new \RuntimeException('simulated failure mid-transaction');
        });

        $service = app(ReturnService::class);

        try {
            $service->createReturn(
                direction: 'order',
                parent: $order,
                items: [
                    ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '25.00'],
                ],
                returnDate: '2026-08-10',
            );
            $this->fail('Expected the simulated failure to bubble out of createReturn.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated failure mid-transaction', $e->getMessage());
        }

        $this->assertSame(10, (int) Product::find($product->id)->stock, 'Stock was changed when the call should have rolled back');
        $this->assertSame(0, OrderReturn::where('order_id', $order->id)->count(), 'OrderReturn row should not exist after rollback');
        $this->assertSame(0, OrderReturnItem::count());
        $this->assertSame(0, StockMovement::where('movement_type', 'return')->count());
    }

    public function test_create_return_takes_lock_for_update_on_each_product_row(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'Lock Customer', 'phone' => '0700000004']);
        $category = Category::create(['name' => 'Lock Category']);
        $product = Product::create([
            'name' => 'Lockable Widget',
            'category_id' => $category->id,
            'price' => '75.00',
            'stock' => 20,
        ]);

        $order = $this->makeOrderWithItems($user, $customer, 'AFN', [
            ['product' => $product, 'unit_price' => '75.00', 'quantity' => 5],
        ]);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $service = app(ReturnService::class);
        $service->createReturn(
            direction: 'order',
            parent: $order,
            items: [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '75.00'],
            ],
            returnDate: '2026-08-10',
        );

        $hasLockIfSupported = false;
        foreach ($queries as $sql) {
            if (str_contains($sql, 'for update')) {
                $hasLockIfSupported = true;
                break;
            }
        }

        $this->assertTrue(
            $hasLockIfSupported || ! in_array(config('database.default'), ['mysql', 'pgsql'], true),
            'On locking-capable drivers (MySQL, PostgreSQL), createReturn must take row locks on products.'
        );
    }

    public function test_create_return_for_purchase_writes_return_items_and_moves_stock_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $supplier = Supplier::create(['name' => 'Test Supplier', 'phone' => '0700000010']);
        $category = Category::create(['name' => 'Purchase Category']);
        $product = Product::create([
            'name' => 'Supplied Widget',
            'category_id' => $category->id,
            'price' => '40.00',
            'stock' => 10,
        ]);

        $purchase = $this->makePurchaseWithItems($user, $supplier, 'USD', [
            ['product' => $product, 'unit_price' => '40.00', 'quantity' => 4],
        ]);

        $service = app(ReturnService::class);
        $return = $service->createReturn(
            direction: 'purchase',
            parent: $purchase,
            items: [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '40.00'],
            ],
            returnDate: '2026-08-10',
            reason: 'defective',
            status: 'completed',
        );

        $this->assertInstanceOf(PurchaseReturn::class, $return);
        $this->assertSame('USD', $return->currency);
        $this->assertSame('40.00', $return->total_amount);
        $this->assertSame('defective', $return->reason);
        $this->assertSame('completed', $return->status);
        $this->assertSame($supplier->id, $return->supplier_id);

        $this->assertDatabaseHas('purchase_return_items', [
            'purchase_return_id' => $return->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => '40.00',
            'subtotal' => '40.00',
        ]);

        $this->assertSame(9, (int) Product::find($product->id)->stock);

        $this->assertDatabaseHas('stock_movements', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity_change' => -1,
            'movement_type' => 'purchase_return',
            'reference_type' => 'purchase_return',
            'reference_id' => $return->id,
        ]);

        $movement = StockMovement::where('reference_id', $return->id)->first();
        $this->assertNull($movement->journal_entry_id);
    }

    public function test_create_return_for_purchase_rejects_phantom_returns_with_no_state_written(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $supplier = Supplier::create(['name' => 'Phantom Supplier', 'phone' => '0700000011']);
        $category = Category::create(['name' => 'Phantom Purchase Category']);
        $product = Product::create([
            'name' => 'Phantom Supplied Widget',
            'category_id' => $category->id,
            'price' => '30.00',
            'stock' => 0,
        ]);

        $purchase = $this->makePurchaseWithItems($user, $supplier, 'AFN', [
            ['product' => $product, 'unit_price' => '30.00', 'quantity' => 2],
        ]);

        PurchaseReturn::create([
            'purchase_id' => $purchase->id,
            'supplier_id' => $supplier->id,
            'return_date' => '2026-08-09',
            'reason' => 'first purchase return',
            'total_amount' => '60.00',
            'status' => 'completed',
            'currency' => 'AFN',
        ])->items()->createMany([
            ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '30.00', 'subtotal' => '60.00'],
        ]);

        $service = app(ReturnService::class);

        try {
            $service->createReturn(
                direction: 'purchase',
                parent: $purchase,
                items: [
                    ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '30.00'],
                ],
                returnDate: '2026-08-10',
            );
            $this->fail('Expected ValidationException for phantom purchase return was not thrown.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Phantom Supplied Widget', implode(' ', $e->validator->getMessageBag()->all()));
        }

        $this->assertSame(1, PurchaseReturn::where('purchase_id', $purchase->id)->count());
        $this->assertSame(1, PurchaseReturnItem::count());
        $this->assertSame(0, StockMovement::where('movement_type', 'purchase_return')->count());
    }

    private function makePurchaseWithItems(User $user, Supplier $supplier, string $currency, array $lines): Purchase
    {
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '0.00',
            'currency' => $currency,
        ]);

        $subtotal = '0.00';
        foreach ($lines as $line) {
            $lineTotal = bcmul((string) $line['unit_price'], (string) $line['quantity'], 2);
            $subtotal = bcadd($subtotal, $lineTotal, 2);

            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $line['product']->id,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'subtotal' => $lineTotal,
            ]);
        }

        $purchase->update(['subtotal' => $subtotal, 'total_amount' => $subtotal]);

        return $purchase->fresh();
    }
}
