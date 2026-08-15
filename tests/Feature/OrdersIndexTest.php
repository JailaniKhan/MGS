<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersIndexTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $name): Customer
    {
        return Customer::create(['name' => $name, 'phone' => '07'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT)]);
    }

    private function makeSupplier(string $name): Supplier
    {
        return Supplier::create(['name' => $name, 'phone' => '07'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT)]);
    }

    private function makeOrder(Customer $customer, array $overrides = []): Order
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

    private function makePurchase(Supplier $supplier, array $overrides = []): Purchase
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

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('orders.index'))->assertRedirect(route('login'));
        $this->get(route('purchases.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_remaining_after_payments_and_returns(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Balance Customer');

        $order = $this->makeOrder($customer, ['total_amount' => '100.00']);
        Payment::create(['order_id' => $order->id, 'amount' => '30.00', 'currency' => 'AFN']);

        $returned = $this->makeOrder($customer, ['total_amount' => '50.00']);
        OrderReturn::create([
            'order_id' => $returned->id,
            'customer_id' => $customer->id,
            'return_date' => now()->toDateString(),
            'total_amount' => '50.00',
        ]);

        $foreign = $this->makeOrder($customer, ['total_amount' => '200.00']);
        Payment::create(['order_id' => $foreign->id, 'amount' => '200.00', 'currency' => 'USD']);

        $response = $this->get(route('orders.index'))->assertOk();

        $orders = $response->viewData('orders');
        $byId = $orders->getCollection()->keyBy('id');

        $this->assertSame('70.00', $byId[$order->id]->remaining);
        $this->assertSame('0.00', $byId[$returned->id]->remaining);
        $this->assertSame('paid', $byId[$returned->id]->list_status);
        $this->assertSame('200.00', $byId[$foreign->id]->remaining);
    }

    public function test_cancelled_orders_show_cancelled_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Cancelled Customer');
        $order = $this->makeOrder($customer, ['total_amount' => '100.00', 'status' => 'cancelled']);

        $response = $this->get(route('orders.index'))->assertOk();

        $row = $response->viewData('orders')->getCollection()->firstWhere('id', $order->id);
        $this->assertSame('cancelled', $row->list_status);
    }

    public function test_search_filters_orders_and_purchases_by_party_name_and_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $ahmad = $this->makeCustomer('Ahmad Zia');
        $karim = $this->makeCustomer('Karim Gul');
        $zabih = $this->makeSupplier('Zabihullah Traders');

        $orderA = $this->makeOrder($ahmad);
        $orderK = $this->makeOrder($karim);
        $purchase = $this->makePurchase($zabih);

        // By name: only Karim's order, purchases list empty.
        $response = $this->get(route('orders.index', ['search' => 'Karim']))->assertOk();
        $orders = $response->viewData('orders')->getCollection()->pluck('id');
        $purchases = $response->viewData('purchases')->getCollection()->pluck('id');
        $this->assertSame([$orderK->id], $orders->all());
        $this->assertSame([], $purchases->all());

        // By supplier name: purchase found, orders empty.
        $response = $this->get(route('orders.index', ['search' => 'Zabihullah']))->assertOk();
        $purchases = $response->viewData('purchases')->getCollection()->pluck('id');
        $this->assertSame([$purchase->id], $purchases->all());
        $this->assertSame([], $response->viewData('orders')->getCollection()->pluck('id')->all());

        // By document id.
        $response = $this->get(route('orders.index', ['search' => (string) $orderA->id]))->assertOk();
        $orders = $response->viewData('orders')->getCollection()->pluck('id');
        $this->assertSame([$orderA->id], $orders->all());
    }

    public function test_purchases_index_uses_same_remaining_logic(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Balance Supplier');
        $purchase = $this->makePurchase($supplier, ['total_amount' => '100.00']);

        PurchasePayment::create(['purchase_id' => $purchase->id, 'amount' => '25.00', 'currency' => 'AFN']);

        $response = $this->get(route('purchases.index'))->assertOk();
        $row = $response->viewData('purchases')->getCollection()->firstWhere('id', $purchase->id);
        $this->assertSame('75.00', $row->remaining);
    }

    public function test_destroy_does_not_restore_stock_twice_for_cancelled_orders(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Destroy Customer');
        $category = Category::create(['name' => 'Destroy Category']);
        $product = Product::create(['name' => 'Destroy Product', 'category_id' => $category->id, 'stock' => 10]);

        $order = $this->makeOrder($customer, ['status' => 'cancelled']);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => '10.00',
            'subtotal' => '30.00',
        ]);

        // Cancelling earlier already restored the stock; deleting must not
        // restore it again.
        $this->delete(route('orders.destroy', $order));

        $this->assertSame(10, (int) Product::find($product->id)->stock);
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_update_cannot_un_cancel_an_order(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Update Customer');
        $order = $this->makeOrder($customer, ['status' => 'cancelled']);

        $this->put(route('orders.update', $order), [
            'person' => 'customer:'.$customer->id,
            'status' => 'pending',
        ])->assertSessionHas('error');

        $this->assertSame('cancelled', Order::find($order->id)->status);
    }
}
