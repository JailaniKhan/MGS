<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PartyPayment;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentsTest extends TestCase
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
        $this->get(route('payments.index'))->assertRedirect(route('login'));
        $this->get(route('payments.create'))->assertRedirect(route('login'));
        $this->get(route('payments.customers'))->assertRedirect(route('login'));
    }

    public function test_index_sums_structured_and_ledger_payments_per_currency(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Wallet Customer');
        $supplier = $this->makeSupplier('Wallet Supplier');

        $order = $this->makeOrder($customer, ['total_amount' => '100.00']);
        Payment::create(['order_id' => $order->id, 'amount' => '30.00', 'currency' => 'AFN']);
        Payment::create(['order_id' => $order->id, 'amount' => '50.00', 'currency' => 'USD']);

        $purchase = $this->makePurchase($supplier, ['total_amount' => '80.00']);
        PurchasePayment::create(['purchase_id' => $purchase->id, 'amount' => '20.00', 'currency' => 'AFN']);

        PartyPayment::create(['person_type' => 'customer', 'person_id' => $customer->id, 'type' => 'payment_received', 'amount' => '10.00', 'currency' => 'AFN']);
        PartyPayment::create(['person_type' => 'supplier', 'person_id' => $supplier->id, 'type' => 'payment_made', 'amount' => '5.00', 'currency' => 'AFN']);

        $response = $this->get(route('payments.index'))->assertOk();

        $this->assertSame(40, $response->viewData('incomingAFN'));
        $this->assertSame(50, $response->viewData('incomingUSD'));
        $this->assertSame(25, $response->viewData('outgoingAFN'));
        $this->assertSame(0, $response->viewData('outgoingUSD'));
        $this->assertSame(15, $response->viewData('balanceAFN'));
        $this->assertSame(50, $response->viewData('balanceUSD'));
    }

    public function test_index_lists_only_outstanding_non_cancelled_documents(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Outstanding Customer');
        $supplier = $this->makeSupplier('Outstanding Supplier');

        $open = $this->makeOrder($customer, ['total_amount' => '100.00']);
        Payment::create(['order_id' => $open->id, 'amount' => '30.00', 'currency' => 'AFN']);

        $paid = $this->makeOrder($customer, ['total_amount' => '50.00']);
        Payment::create(['order_id' => $paid->id, 'amount' => '50.00', 'currency' => 'AFN']);

        $cancelled = $this->makeOrder($customer, ['total_amount' => '60.00', 'status' => 'cancelled']);

        $purchaseOpen = $this->makePurchase($supplier, ['total_amount' => '200.00']);
        PurchasePayment::create(['purchase_id' => $purchaseOpen->id, 'amount' => '80.00', 'currency' => 'AFN']);

        $response = $this->get(route('payments.index'))->assertOk();

        $receivables = $response->viewData('receivablesAFN')->getCollection()->keyBy('id');
        $this->assertCount(1, $receivables);
        $this->assertSame('70.00', $receivables[$open->id]->remaining);
        $this->assertArrayNotHasKey($paid->id, $receivables);
        $this->assertArrayNotHasKey($cancelled->id, $receivables);

        $payables = $response->viewData('payablesAFN')->getCollection()->keyBy('id');
        $this->assertCount(1, $payables);
        $this->assertSame('120.00', $payables[$purchaseOpen->id]->remaining);
    }

    public function test_create_offers_only_unpaid_non_cancelled_documents(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Create Customer');
        $supplier = $this->makeSupplier('Create Supplier');

        $open = $this->makeOrder($customer, ['total_amount' => '100.00']);
        $paid = $this->makeOrder($customer, ['total_amount' => '50.00']);
        Payment::create(['order_id' => $paid->id, 'amount' => '50.00', 'currency' => 'AFN']);
        $cancelled = $this->makeOrder($customer, ['total_amount' => '60.00', 'status' => 'cancelled']);

        $purchaseOpen = $this->makePurchase($supplier, ['total_amount' => '200.00']);
        $purchasePaid = $this->makePurchase($supplier, ['total_amount' => '80.00']);
        PurchasePayment::create(['purchase_id' => $purchasePaid->id, 'amount' => '80.00', 'currency' => 'AFN']);

        $response = $this->get(route('payments.create'))->assertOk();

        $this->assertSame([$open->id], $response->viewData('orders')->pluck('id')->all());
        $this->assertSame([$purchaseOpen->id], $response->viewData('purchases')->pluck('id')->all());
        $this->assertSame('order', $response->viewData('selectedType'));
    }

    public function test_store_records_a_payment_and_redirects_to_wallet(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Store Customer');
        $order = $this->makeOrder($customer, ['total_amount' => '100.00']);

        $this->post(route('payments.store'), [
            'type' => 'order',
            'order_id' => $order->id,
            'amount' => '40.00',
            'currency' => 'AFN',
            'notes' => 'first installment',
        ])->assertRedirect(route('payments.index'));

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => '40.00',
            'currency' => 'AFN',
            'notes' => 'first installment',
        ]);
    }

    public function test_store_records_a_purchase_payment_and_redirects_to_wallet(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Purchase Store Supplier');
        $purchase = $this->makePurchase($supplier, ['total_amount' => '200.00']);

        $this->post(route('payments.store'), [
            'type' => 'purchase',
            'purchase_id' => $purchase->id,
            'amount' => '80.00',
            'currency' => 'AFN',
            'notes' => 'partial supplier payment',
        ])->assertRedirect(route('payments.index'));

        $this->assertDatabaseHas('purchase_payments', [
            'purchase_id' => $purchase->id,
            'amount' => '80.00',
            'currency' => 'AFN',
            'notes' => 'partial supplier payment',
        ]);
    }

    public function test_store_rejects_currency_mismatch(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Mismatch Customer');
        $order = $this->makeOrder($customer, ['total_amount' => '100.00', 'currency' => 'USD']);

        $this->post(route('payments.store'), [
            'type' => 'order',
            'order_id' => $order->id,
            'amount' => '40.00',
            'currency' => 'AFN',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_store_rejects_amount_above_remaining_balance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Overpay Customer');
        $order = $this->makeOrder($customer, ['total_amount' => '100.00']);
        Payment::create(['order_id' => $order->id, 'amount' => '30.00', 'currency' => 'AFN']);

        $this->post(route('payments.store'), [
            'type' => 'order',
            'order_id' => $order->id,
            'amount' => '70.01',
            'currency' => 'AFN',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_store_rejects_purchase_currency_mismatch(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Purchase Mismatch Supplier');
        $purchase = $this->makePurchase($supplier, ['total_amount' => '200.00', 'currency' => 'USD']);

        $this->post(route('payments.store'), [
            'type' => 'purchase',
            'purchase_id' => $purchase->id,
            'amount' => '80.00',
            'currency' => 'AFN',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('purchase_payments', 0);
    }

    public function test_store_rejects_purchase_amount_above_remaining_balance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Purchase Overpay Supplier');
        $purchase = $this->makePurchase($supplier, ['total_amount' => '200.00']);
        PurchasePayment::create(['purchase_id' => $purchase->id, 'amount' => '80.00', 'currency' => 'AFN']);

        $this->post(route('payments.store'), [
            'type' => 'purchase',
            'purchase_id' => $purchase->id,
            'amount' => '120.01',
            'currency' => 'AFN',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('purchase_payments', 1);
    }

    public function test_purchase_show_links_to_shared_payment_form(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Purchase Link Supplier');
        $purchase = $this->makePurchase($supplier, ['total_amount' => '200.00']);
        PurchasePayment::create(['purchase_id' => $purchase->id, 'amount' => '80.00', 'currency' => 'AFN']);

        $this->get(route('purchases.show', $purchase))
            ->assertOk()
            ->assertSee('type=purchase&purchase_id='.$purchase->id, false);

        // A fully paid purchase no longer offers the add-payment entry point.
        PurchasePayment::create(['purchase_id' => $purchase->id, 'amount' => '120.00', 'currency' => 'AFN']);

        $this->get(route('purchases.show', $purchase))
            ->assertOk()
            ->assertDontSee(__('messages.add_payment'));
    }

    public function test_destroy_deletes_a_payment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Destroy Customer');
        $order = $this->makeOrder($customer, ['total_amount' => '100.00']);
        $payment = Payment::create(['order_id' => $order->id, 'amount' => '40.00', 'currency' => 'AFN']);

        $this->delete(route('payments.destroy', $payment))->assertRedirect(route('payments.index'));

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }

    public function test_customer_payments_page_renders_the_wallet(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('payments.customers'))->assertOk();
    }

    public function test_create_renders_searchable_document_picker_with_outstanding_documents(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Picker Customer');
        $order = $this->makeOrder($customer, ['total_amount' => '150.00']);

        $response = $this->get(route('payments.create'))->assertOk();

        $response->assertSee('doc-search');
        $response->assertSee('doc-dropdown');
        $response->assertSee('order-id-input');
        $response->assertSee('purchase-id-input');
        $response->assertSee('type-input');
        $response->assertSee('Picker Customer', false);
    }

    public function test_store_ignores_stale_mismatched_document_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Stale Id Customer');
        $order = $this->makeOrder($customer, ['total_amount' => '100.00']);

        $this->from(route('payments.create'))->post(route('payments.store'), [
            'type' => 'order',
            'order_id' => $order->id,
            'purchase_id' => '999999',
            'amount' => '40.00',
            'currency' => 'AFN',
        ])->assertRedirect(route('payments.index'));

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => '40.00',
            'currency' => 'AFN',
        ]);
    }

    public function test_store_rejects_missing_type(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('No Type Customer');
        $order = $this->makeOrder($customer, ['total_amount' => '100.00']);

        $this->from(route('payments.create'))->post(route('payments.store'), [
            'order_id' => $order->id,
            'amount' => '40.00',
            'currency' => 'AFN',
        ])->assertSessionHasErrors('type');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_ledger_show_links_to_party_scoped_payment_form(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Ledger Link Customer');
        $this->makeOrder($customer, ['total_amount' => '100.00']);

        $this->get(route('ledger.show', ['customer', $customer->id]))
            ->assertOk()
            ->assertSee(route('payments.create', ['party_type' => 'customer', 'party_id' => $customer->id]))
            // The old inline ledger form is gone — payments go through the shared page.
            ->assertDontSee(__('messages.record_payment'));
    }

    public function test_ledger_show_hides_add_payment_when_fully_paid(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Ledger Settled Customer');
        $order = $this->makeOrder($customer, ['total_amount' => '100.00']);
        Payment::create(['order_id' => $order->id, 'amount' => '100.00', 'currency' => 'AFN']);

        $this->get(route('ledger.show', ['customer', $customer->id]))
            ->assertOk()
            ->assertDontSee(__('messages.add_payment'));
    }

    public function test_create_filters_documents_by_party(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $scoped = $this->makeCustomer('Scoped Customer');
        $other = $this->makeCustomer('Other Customer');
        $scopedOrder = $this->makeOrder($scoped, ['total_amount' => '100.00']);
        $otherOrder = $this->makeOrder($other, ['total_amount' => '50.00']);

        $response = $this->get(route('payments.create', ['party_type' => 'customer', 'party_id' => $scoped->id]))
            ->assertOk();

        $this->assertSame([$scopedOrder->id], $response->viewData('orders')->pluck('id')->all());
        $this->assertSame('Scoped Customer', $response->viewData('partyName'));
        $this->assertSame('customer', $response->viewData('partyType'));
        $this->assertNotContains($otherOrder->id, $response->viewData('orderPicker')->pluck('id')->all());
    }

    public function test_create_defaults_to_purchase_when_party_has_only_purchases(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Purchase Only Supplier');
        $purchase = $this->makePurchase($supplier, ['total_amount' => '200.00']);

        $response = $this->get(route('payments.create', ['party_type' => 'supplier', 'party_id' => $supplier->id]))
            ->assertOk();

        $this->assertSame('purchase', $response->viewData('selectedType'));
        $this->assertSame([$purchase->id], $response->viewData('purchases')->pluck('id')->all());
        $this->assertTrue($response->viewData('orderPicker')->isEmpty());
    }

    public function test_create_404s_for_another_shops_party(): void
    {
        User::factory()->create();
        $intruder = User::factory()->create();
        $this->actingAs($intruder);

        $foreign = Customer::create(['name' => 'Foreign Customer']);
        $foreign->user_id = User::first()->id;
        $foreign->save();

        $this->get(route('payments.create', ['party_type' => 'customer', 'party_id' => $foreign->id]))
            ->assertNotFound();
    }

    public function test_store_returns_to_ledger_when_return_to_is_ledger(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Ledger Return Customer');
        $order = $this->makeOrder($customer, ['total_amount' => '100.00']);

        $this->post(route('payments.store'), [
            'type' => 'order',
            'order_id' => $order->id,
            'amount' => '40.00',
            'currency' => 'AFN',
            'return_to' => 'ledger',
        ])->assertRedirect(route('ledger.show', ['customer', $customer->id]));

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => '40.00',
            'currency' => 'AFN',
        ]);
    }
}
