<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Billing\BillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderShowTest extends TestCase
{
    use RefreshDatabase;

    private function makeFractionalUsdOrder(): Order
    {
        $customer = Customer::create(['name' => 'Fractional Customer', 'phone' => '0700000011']);
        $category = Category::create(['name' => 'Fractional Category']);
        $product = Product::create(['name' => 'Fractional Product', 'category_id' => $category->id, 'stock' => 50]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '414.00',
            'total_amount' => '414.00',
            'currency' => 'USD',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'unit_price' => '20.70',
            'subtotal' => '414.00',
        ]);

        return $order;
    }

    private function makeFractionalUsdPurchase(): Purchase
    {
        $supplier = Supplier::create(['name' => 'Fractional Supplier', 'phone' => '0700000012']);
        $category = Category::create(['name' => 'Fractional Purchase Category']);
        $product = Product::create(['name' => 'Fractional Purchase Product', 'category_id' => $category->id, 'stock' => 50]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '414.00',
            'total_amount' => '414.00',
            'currency' => 'USD',
        ]);
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'unit_price' => '20.70',
            'subtotal' => '414.00',
        ]);

        return $purchase;
    }

    public function test_show_renders_fractional_unit_price_exactly(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $order = $this->makeFractionalUsdOrder();

        $response = $this->get(route('orders.show', $order))->assertOk();

        // 20.70 must survive rendering: no rounding to 21.
        $response->assertSee('20.70$', false);
        $response->assertDontSee('21$', false);
        // Whole subtotals stay clean (414, not 414.00).
        $response->assertSee('414$', false);
        $response->assertDontSee('414.00$', false);
    }

    public function test_print_renders_fractional_unit_price_exactly(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $order = $this->makeFractionalUsdOrder();

        $response = $this->get(route('orders.print', $order))->assertOk();

        $response->assertSee('20.70', false);
        $response->assertDontSee('21 $', false);
    }

    public function test_whatsapp_bill_text_renders_fractional_unit_price(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $order = $this->makeFractionalUsdOrder();

        $bill = app(BillService::class)->orderBill($order);

        $this->assertStringContainsString('20.70', $bill['message']);
        $this->assertStringNotContainsString('x 21', $bill['message']);
    }

    public function test_whatsapp_bill_wraps_amounts_in_bidi_isolates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $order = $this->makeFractionalUsdOrder();

        $bill = app(BillService::class)->orderBill($order);

        // LRI ... PDI around the equation keeps the money run in logical
        // order when the bill is displayed in an RTL locale.
        $this->assertStringContainsString(
            "\u{2066}20 x 20.70 = 414 $\u{2069}",
            $bill['message']
        );
        $this->assertStringContainsString(
            "\u{2066}414 $\u{2069}",
            $bill['message']
        );
    }

    public function test_purchase_show_renders_fractional_unit_price_exactly(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $purchase = $this->makeFractionalUsdPurchase();

        $response = $this->get(route('purchases.show', $purchase))->assertOk();

        $response->assertSee('20.70$', false);
        $response->assertDontSee('21$', false);
    }

    public function test_purchase_print_renders_fractional_unit_price_exactly(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $purchase = $this->makeFractionalUsdPurchase();

        $response = $this->get(route('purchases.print', $purchase))->assertOk();

        $response->assertSee('20.70', false);
    }

    public function test_purchase_whatsapp_bill_text_renders_fractional_unit_price(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $purchase = $this->makeFractionalUsdPurchase();

        $bill = app(BillService::class)->purchaseBill($purchase);

        $this->assertStringContainsString('20.70', $bill['message']);
        $this->assertStringNotContainsString('x 21', $bill['message']);
        $this->assertStringContainsString(
            "\u{2066}20 x 20.70 = 414 $\u{2069}",
            $bill['message']
        );
    }

    public function test_print_shows_paid_current_pending_and_total_pending(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $order = $this->makeFractionalUsdOrder();

        // 100 paid on this order; a second open order adds party-wide pending.
        Payment::create([
            'order_id' => $order->id,
            'amount' => '100.00',
            'currency' => 'USD',
            'notes' => 'deposit',
        ]);
        $other = Order::create([
            'customer_id' => $order->customer_id,
            'person_type' => 'customer',
            'person_id' => $order->person_id,
            'status' => 'completed',
            'subtotal' => '50.00',
            'total_amount' => '50.00',
            'currency' => 'USD',
        ]);

        $response = $this->get(route('orders.print', $order))->assertOk();

        $response->assertSee(__('messages.paid'));
        $response->assertSee(__('messages.current_pending'));
        $response->assertSee(__('messages.total_pending'));
        // 414 - 100 paid on this document = 314 current pending.
        $response->assertSee('314');
        // 314 + 50 from the second document = 364 total pending.
        $response->assertSee('364');
    }

    public function test_purchase_print_shows_paid_current_pending_and_total_pending(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $purchase = $this->makeFractionalUsdPurchase();

        PurchasePayment::create([
            'purchase_id' => $purchase->id,
            'amount' => '100.00',
            'currency' => 'USD',
            'notes' => 'deposit',
        ]);
        $other = Purchase::create([
            'supplier_id' => $purchase->supplier_id,
            'person_type' => 'supplier',
            'person_id' => $purchase->person_id,
            'status' => 'completed',
            'subtotal' => '50.00',
            'total_amount' => '50.00',
            'currency' => 'USD',
        ]);

        $response = $this->get(route('purchases.print', $purchase))->assertOk();

        $response->assertSee(__('messages.paid'));
        $response->assertSee(__('messages.current_pending'));
        $response->assertSee(__('messages.total_pending'));
        // 414 - 100 paid on this document = 314 current pending.
        $response->assertSee('314');
        // 314 + 50 from the second document = 364 total pending.
        $response->assertSee('364');
    }
}
