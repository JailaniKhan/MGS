<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order lifecycle's stock contract, exercised through the HTTP
 * endpoints: store decrements once under lock, cancel restores once,
 * delete restores only what is still on the shelf.
 */
class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function acting(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function makeCategory(): Category
    {
        return Category::create(['name' => 'Lifecycle Cat']);
    }

    private function makeProduct(int $stock = 20): Product
    {
        return Product::create([
            'name' => 'Lifecycle Product',
            'category_id' => $this->makeCategory()->id,
            'price' => 100,
            'stock' => $stock,
        ]);
    }

    private function makeCustomer(): Customer
    {
        return Customer::create(['name' => 'Lifecycle Customer', 'phone' => null]);
    }

    private function storeOrder(Customer $customer, Product $product, int $quantity, string $unitPrice = '50.00')
    {
        return $this->post(route('orders.store'), [
            'person' => 'customer:'.$customer->id,
            'currency' => 'AFN',
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ],
            ],
        ]);
    }

    private function stockOf(Product $product): int
    {
        return (int) $product->fresh()->stock;
    }

    public function test_store_decrements_stock_and_records_sale_movements(): void
    {
        $this->acting();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(20);

        $this->storeOrder($customer, $product, 5)->assertRedirect(route('orders.index'));

        $order = Order::firstOrFail();
        $this->assertSame(15, $this->stockOf($product));
        $this->assertSame('250.00', (string) $order->total_amount);

        $movement = StockMovement::where('reference_type', 'order')->where('reference_id', $order->id)->sole();
        $this->assertSame(-5, (int) $movement->quantity_change);
        $this->assertSame('sale', $movement->movement_type);
    }

    public function test_store_rejects_quantity_above_stock_and_writes_nothing(): void
    {
        $this->acting();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(3);

        $this->from(route('orders.create'))
            ->storeOrder($customer, $product, 5)
            ->assertSessionHasErrors('products');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(3, $this->stockOf($product));
    }

    public function test_cancel_restores_stock_once_and_double_cancel_is_rejected(): void
    {
        $this->acting();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(10);
        $order = null;

        $this->storeOrder($customer, $product, 3)->assertRedirect(route('orders.index'));
        $order = Order::firstOrFail();
        $this->assertSame(7, $this->stockOf($product));

        $this->post(route('orders.status', [$order, 'cancelled']))
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame(10, $this->stockOf($product));
        $this->assertSame(
            1,
            StockMovement::where('movement_type', 'order_cancelled')
                ->where('reference_id', $order->id)
                ->count()
        );

        // Cancelling again is an idempotent no-op — stock must not be
        // restored a second time.
        $this->post(route('orders.status', [$order, 'cancelled']))
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('success');

        $this->assertSame(10, $this->stockOf($product));
        $this->assertSame(
            1,
            StockMovement::where('movement_type', 'order_cancelled')
                ->where('reference_id', $order->id)
                ->count()
        );
    }

    public function test_cancelled_order_cannot_return_to_a_live_status(): void
    {
        $this->acting();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(10);
        $this->storeOrder($customer, $product, 2)->assertRedirect(route('orders.index'));
        $order = Order::firstOrFail();

        $this->post(route('orders.status', [$order, 'cancelled']))->assertRedirect(route('orders.index'));

        $this->from(route('orders.edit', $order))
            ->post(route('orders.status', [$order, 'completed']))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_delete_restores_stock_for_live_order_but_not_twice_for_cancelled(): void
    {
        $this->acting();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(10);

        // Live order: delete restores its stock exactly once.
        $this->storeOrder($customer, $product, 4)->assertRedirect(route('orders.index'));
        $live = Order::firstOrFail();
        $this->assertSame(6, $this->stockOf($product));

        $this->delete(route('orders.destroy', $live))->assertRedirect(route('orders.index'));

        $this->assertSame(10, $this->stockOf($product));
        $deletedMovements = StockMovement::where('movement_type', 'order_deleted')->where('reference_id', $live->id)->count();
        $this->assertSame(1, $deletedMovements);

        // Cancelled order already gave its stock back — delete adds nothing.
        $this->storeOrder($customer, $product, 2)->assertRedirect(route('orders.index'));
        $cancelled = Order::orderByDesc('id')->firstOrFail();
        $this->post(route('orders.status', [$cancelled, 'cancelled']))->assertRedirect(route('orders.index'));
        $this->assertSame(10, $this->stockOf($product));
        $cancelMovementsBeforeDelete = StockMovement::where('reference_id', $cancelled->id)->count();

        $this->delete(route('orders.destroy', $cancelled))->assertRedirect(route('orders.index'));

        $this->assertSame(10, $this->stockOf($product));
        $this->assertSame(
            $cancelMovementsBeforeDelete,
            StockMovement::where('reference_id', $cancelled->id)->count(),
            'Deleting a cancelled order must not write further stock movements.'
        );
    }

    public function test_sequential_payments_settle_exactly_and_further_payment_is_rejected(): void
    {
        $user = $this->acting();
        $customer = Customer::create(['name' => 'Clamp Customer', 'phone' => null]);
        $order = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '100.00',
            'total_amount' => '100.00',
            'currency' => 'AFN',
        ]);

        $pay = fn (string $amount) => $this->post(route('payments.store'), [
            'type' => 'order',
            'order_id' => $order->id,
            'amount' => $amount,
            'currency' => 'AFN',
        ]);

        $pay('60.00')->assertRedirect(route('payments.index'));
        $pay('40.00')->assertRedirect(route('payments.index'));
        $this->assertSame(
            '100.00',
            number_format((float) Payment::where('order_id', $order->id)->sum('amount'), 2, '.', '')
        );

        // Document fully settled — one cent more must be refused even though
        // it would have passed against any single earlier balance snapshot.
        $this->from(route('payments.create'))
            ->post(route('payments.store'), [
                'type' => 'order',
                'order_id' => $order->id,
                'amount' => '0.01',
                'currency' => 'AFN',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(2, Payment::where('order_id', $order->id)->count());

        // Same clamp through the ledger path: nothing left to allocate, so
        // the whole amount stays on account instead of touching the order.
        $this->from(route('ledger.show', ['customer', $customer->id]))
            ->post(route('ledger.payment.store', ['customer', $customer->id]), [
                'amount' => '5.00',
                'currency' => 'AFN',
            ])
            ->assertRedirect(route('ledger.show', ['customer', $customer->id]));

        $this->assertSame(
            '100.00',
            number_format((float) Payment::where('order_id', $order->id)->sum('amount'), 2, '.', '')
        );
        $this->assertDatabaseHas('party_payments', [
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'amount' => '5.00',
            'currency' => 'AFN',
            'type' => 'payment_received',
        ]);

        $this->assertTrue(User::whereKey($user->id)->exists());
        $this->assertSame(0, PurchasePayment::count(), 'No purchase payments should exist in this scenario.');
        $this->assertSame(0, Purchase::count());
    }
}
