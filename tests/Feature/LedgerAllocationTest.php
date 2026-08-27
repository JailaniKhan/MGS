<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\PartyPayment;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Billing\PartyBalanceService;
use App\Services\Billing\PaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerAllocationTest extends TestCase
{
    use RefreshDatabase;

    private function acting(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'ledger-alloc@test.dev'],
            ['name' => 'L', 'password' => bcrypt('x')]
        );
        $this->actingAs($user);

        return $user;
    }

    private function makeCustomer(string $name = 'Alloc Customer'): Customer
    {
        return Customer::create(['name' => $name, 'phone' => null]);
    }

    private function makeSupplier(string $name = 'Alloc Supplier'): Supplier
    {
        return Supplier::create(['name' => $name, 'phone' => null]);
    }

    private function makeOrder(Customer $customer, string $total, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'pending',
            'subtotal' => $total,
            'total_amount' => $total,
            'currency' => 'AFN',
        ], $overrides));
    }

    private function makePurchase(Supplier $supplier, string $total, array $overrides = []): Purchase
    {
        return Purchase::create(array_merge([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'pending',
            'subtotal' => $total,
            'total_amount' => $total,
            'currency' => 'AFN',
        ], $overrides));
    }

    public function test_ledger_payment_allocates_to_open_orders_oldest_first(): void
    {
        $this->acting();
        $customer = $this->makeCustomer('Oldest First');

        $newer = $this->makeOrder($customer, '60.00');
        $older = $this->makeOrder($customer, '100.00');
        $older->forceFill(['created_at' => $older->created_at->subDay()])->save();

        $this->post(route('ledger.payment.store', ['customer', $customer->id]), [
            'amount' => '120.00',
            'currency' => 'AFN',
        ])->assertRedirect(route('ledger.show', ['customer', $customer->id]));

        $olderPayments = Payment::where('order_id', $older->id)->get();
        $newerPayments = Payment::where('order_id', $newer->id)->get();

        // Oldest order fully settled first, overflow goes to the newer one.
        $this->assertSame('100.00', number_format((float) $olderPayments->sum('amount'), 2, '.', ''));
        $this->assertSame('20.00', number_format((float) $newerPayments->sum('amount'), 2, '.', ''));
        $this->assertDatabaseCount('party_payments', 0);

        $older->refresh();
        $newer->refresh();
        $this->assertSame('paid', $older->display_status);
        $this->assertSame('40.00', $newer->remaining_amount);
    }

    public function test_ledger_payment_over_allocation_keeps_remainder_on_account(): void
    {
        $this->acting();
        $customer = $this->makeCustomer('Over Alloc');
        $order = $this->makeOrder($customer, '100.00');

        $this->post(route('ledger.payment.store', ['customer', $customer->id]), [
            'amount' => '140.00',
            'currency' => 'AFN',
        ]);

        $this->assertSame('100.00', number_format((float) Payment::where('order_id', $order->id)->sum('amount'), 2, '.', ''));
        $this->assertDatabaseHas('party_payments', [
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'amount' => '40.00',
            'currency' => 'AFN',
            'type' => 'payment_received',
        ]);
    }

    public function test_ledger_payment_ignores_foreign_currency_and_cancelled_documents(): void
    {
        $this->acting();
        $customer = $this->makeCustomer('Currency Guard');

        $usdOrder = $this->makeOrder($customer, '100.00', ['currency' => 'USD']);
        $cancelled = $this->makeOrder($customer, '80.00', ['status' => 'cancelled']);

        $this->post(route('ledger.payment.store', ['customer', $customer->id]), [
            'amount' => '150.00',
            'currency' => 'AFN',
        ]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('party_payments', [
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'amount' => '150.00',
            'currency' => 'AFN',
        ]);
        $this->assertSame('100.00', $usdOrder->fresh()->remaining_amount);
        $this->assertSame('cancelled', $cancelled->fresh()->display_status);
    }

    public function test_ledger_payment_allocates_to_supplier_purchases(): void
    {
        $this->acting();
        $supplier = $this->makeSupplier('Supplier Alloc');
        $purchase = $this->makePurchase($supplier, '200.00');

        $this->post(route('ledger.payment.store', ['supplier', $supplier->id]), [
            'amount' => '75.00',
            'currency' => 'AFN',
        ]);

        $this->assertDatabaseHas('purchase_payments', [
            'purchase_id' => $purchase->id,
            'amount' => '75.00',
            'currency' => 'AFN',
        ]);
        $this->assertSame('125.00', $purchase->fresh()->remaining_amount);
    }

    public function test_ledger_show_agrees_with_orders_page_after_ledger_payment(): void
    {
        $this->acting();
        $customer = $this->makeCustomer('Consistent');
        $order = $this->makeOrder($customer, '100.00');

        $this->post(route('ledger.payment.store', ['customer', $customer->id]), [
            'amount' => '100.00',
            'currency' => 'AFN',
        ]);

        // Orders page: fully paid via the list-status math.
        $ordersPage = $this->get(route('orders.index'))->assertOk();
        $listed = collect($ordersPage->viewData('orders')->getCollection())->firstWhere('id', $order->id);
        $this->assertSame('paid', $listed->list_status);
        $this->assertSame('0.00', $listed->remaining);

        // Ledger page: same remaining (0) the orders page reports.
        $ledger = $this->get(route('ledger.show', ['customer', $customer->id]))->assertOk();
        $this->assertSame('0.00', $ledger->viewData('summary')['remaining_afn']);
    }

    public function test_other_users_party_payment_is_rejected(): void
    {
        $this->acting();
        $intruder = User::create(['name' => 'X', 'email' => 'ledger-intruder@test.dev', 'password' => bcrypt('x')]);
        $customer = Customer::create(['name' => 'Hidden Ledger Customer']);
        $customer->user_id = $intruder->id;
        $customer->save();

        $this->post(route('ledger.payment.store', ['customer', $customer->id]), [
            'amount' => '50.00',
            'currency' => 'AFN',
        ])->assertNotFound();

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('party_payments', 0);
    }

    public function test_allocation_service_sums_without_allocating_to_unsettled_party_payments(): void
    {
        $this->acting();
        $customer = $this->makeCustomer('Service Bound');
        $order = $this->makeOrder($customer, '100.00');

        $service = app(PaymentAllocationService::class);
        $result = $service->allocate('customer', $customer->id, '100.00', 'AFN', 'cash');

        $this->assertSame('100.00', $result['allocated']);
        $this->assertSame('0.00', $result['unallocated']);
        $this->assertCount(1, $result['documents']);
        $this->assertSame('order', $result['documents'][0]['type']);
        $this->assertSame($order->id, $result['documents'][0]['id']);
    }

    public function test_party_balance_service_matches_ledger_summary(): void
    {
        $this->acting();
        $customer = $this->makeCustomer('Summary');
        $order = $this->makeOrder($customer, '100.00');
        Payment::create(['order_id' => $order->id, 'amount' => '30.00', 'currency' => 'AFN']);

        $summary = app(PartyBalanceService::class)->partySummary('customer', $customer->id);

        $this->assertSame('100.00', $summary['total_amount_afn']);
        $this->assertSame('30.00', $summary['paid_afn']);
        $this->assertSame('70.00', $summary['remaining_afn']);
        $this->assertSame('0.00', $summary['total_amount_usd']);
    }

    public function test_return_reduces_remaining_and_appears_on_ledger_page(): void
    {
        $this->acting();
        $customer = $this->makeCustomer('Return Visible');
        $order = $this->makeOrder($customer, '100.00');
        OrderReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'return_date' => now()->toDateString(),
            'total_amount' => '40.00',
            'currency' => 'AFN',
            'status' => 'completed',
            'reason' => 'damaged',
        ]);

        $summary = app(PartyBalanceService::class)->partySummary('customer', $customer->id);
        $this->assertSame('40.00', $summary['returned_afn']);
        $this->assertSame('60.00', $summary['remaining_afn']);

        $this->get(route('ledger.show', ['customer', $customer->id]))
            ->assertOk()
            ->assertSee(__('messages.returned'))
            ->assertSee(__('messages.returned').' — '.__('messages.order').' #'.$order->id);
    }

    public function test_purchase_return_reduces_supplier_payable(): void
    {
        $this->acting();
        $supplier = $this->makeSupplier('Return Supplier');
        $purchase = $this->makePurchase($supplier, '200.00');
        PurchaseReturn::create([
            'purchase_id' => $purchase->id,
            'supplier_id' => $supplier->id,
            'return_date' => now()->toDateString(),
            'total_amount' => '50.00',
            'currency' => 'AFN',
            'status' => 'completed',
        ]);

        $summary = app(PartyBalanceService::class)->partySummary('supplier', $supplier->id);
        $this->assertSame('50.00', $summary['returned_afn']);
        $this->assertSame('150.00', $summary['remaining_afn']);

        $this->get(route('ledger.show', ['supplier', $supplier->id]))
            ->assertOk()
            ->assertSee(__('messages.returned').' — '.__('messages.purchase').' #'.$purchase->id);
    }

    public function test_overpayment_creates_visible_credit(): void
    {
        $this->acting();
        $customer = $this->makeCustomer('Credit Customer');
        $this->makeOrder($customer, '100.00');

        PartyPayment::create([
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'amount' => '150.00',
            'currency' => 'AFN',
            'type' => 'payment_received',
        ]);

        $summary = app(PartyBalanceService::class)->partySummary('customer', $customer->id);
        $this->assertSame('0.00', $summary['remaining_afn']);
        $this->assertSame('50.00', $summary['credit_afn']);
        $this->assertSame('150.00', $summary['paid_afn']);

        $this->get(route('ledger.show', ['customer', $customer->id]))
            ->assertOk()
            ->assertSee(__('messages.credit'));

        $pdf = $this->get(route('ledger.pdf', ['customer', $customer->id]))->assertOk();
        $this->assertSame(-50.0, $pdf->viewData('closingBalanceAFN'));
        $this->assertSame(50.0, $pdf->viewData('creditAFN'));
    }

    public function test_outstanding_totals_do_not_offset_other_parties_with_credit(): void
    {
        $this->acting();
        $debtor = $this->makeCustomer('Debtor');
        $this->makeOrder($debtor, '100.00');

        $prepaid = $this->makeCustomer('Prepaid');
        PartyPayment::create([
            'person_type' => 'customer',
            'person_id' => $prepaid->id,
            'amount' => '200.00',
            'currency' => 'AFN',
            'type' => 'payment_received',
        ]);

        $totals = app(PartyBalanceService::class)->outstandingByPartyType();
        $this->assertSame('100.00', $totals['customer']['AFN']);
    }

    public function test_outstanding_totals_ignore_other_shops_party_payments(): void
    {
        $this->acting();
        $debtor = $this->makeCustomer('Shop Debtor');
        $this->makeOrder($debtor, '100.00');

        $intruder = User::create(['name' => 'X', 'email' => 'ledger-scope@test.dev', 'password' => bcrypt('x')]);
        $foreign = Customer::create(['name' => 'Foreign Prepaid']);
        $foreign->user_id = $intruder->id;
        $foreign->save();

        PartyPayment::create([
            'person_type' => 'customer',
            'person_id' => $foreign->id,
            'amount' => '500.00',
            'currency' => 'AFN',
            'type' => 'payment_received',
        ]);

        $totals = app(PartyBalanceService::class)->outstandingByPartyType();
        $this->assertSame('100.00', $totals['customer']['AFN']);
    }
}
