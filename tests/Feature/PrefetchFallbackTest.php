<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chrome's prefetcher (sec-purpose: prefetch) re-requests the app's POST-only
 * document endpoints as GET straight from history. Each read-only endpoint
 * therefore has a GET fallback that lands on its GET-able page instead of a
 * 405; state-changing routes (status) deliberately keep none — a stray GET
 * must never mutate anything.
 */
class PrefetchFallbackTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'A', 'email' => 'a@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    public function test_get_pdf_endpoints_land_on_their_getable_page(): void
    {
        $customer = Customer::create(['name' => 'Karim', 'phone' => '0799000001']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => '100.00',
            'currency' => 'AFN',
        ]);

        foreach (['open', 'save', 'share', 'whatsapp'] as $action) {
            $this->get("/orders/{$order->id}/pdf/{$action}")
                ->assertRedirect(route('orders.print', $order));
        }
    }

    public function test_get_send_whatsapp_lands_on_the_show_page(): void
    {
        $customer = Customer::create(['name' => 'Karim', 'phone' => '0799000001']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => '100.00',
            'currency' => 'AFN',
        ]);

        $this->get("/orders/{$order->id}/send-whatsapp")
            ->assertRedirect(route('orders.show', $order));
    }

    public function test_purchase_get_fallbacks_land_on_their_getable_page(): void
    {
        $supplier = Supplier::create(['name' => 'Safi', 'phone' => '0799000002']);
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'pending',
            'total_amount' => '80.00',
            'currency' => 'USD',
        ]);

        foreach (['open', 'save', 'share', 'whatsapp'] as $action) {
            $this->get("/purchases/{$purchase->id}/pdf/{$action}")
                ->assertRedirect(route('purchases.print', $purchase));
        }
        $this->get("/purchases/{$purchase->id}/send-whatsapp")
            ->assertRedirect(route('purchases.show', $purchase));
    }

    public function test_status_routes_have_no_get_fallback(): void
    {
        $customer = Customer::create(['name' => 'Karim', 'phone' => '0799000001']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => '100.00',
            'currency' => 'AFN',
        ]);

        // A stray GET must be rejected outright, never mutate the status.
        $this->get("/orders/{$order->id}/status/completed")->assertStatus(405);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_cashbook_get_fallbacks_land_on_their_getable_page(): void
    {
        $customer = Customer::create(['name' => 'Zarghuna', 'phone' => '0799000003']);

        foreach (['open', 'save', 'share', 'whatsapp'] as $action) {
            $this->get("/cashbook/customer/{$customer->id}/pdf/{$action}")
                ->assertRedirect(route('cashbook.print', ['customer', $customer->id]));
        }
        $this->get("/cashbook/customer/{$customer->id}/send-statement")
            ->assertRedirect(route('cashbook.person', ['customer', $customer->id]));

        // The type constraint holds: a non-customer/supplier type is not a route.
        $this->get("/cashbook/widget/{$customer->id}/pdf/open")->assertNotFound();
    }
}
