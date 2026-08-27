<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgingReportTest extends TestCase
{
    use RefreshDatabase;

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

    private function age(Order $order, int $days): void
    {
        $order->forceFill(['created_at' => now()->subDays($days)->toDateTimeString()])->save();
    }

    private function makeCustomer(string $name): Customer
    {
        return Customer::create(['name' => $name, 'phone' => '07'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT)]);
    }

    private function makeSupplier(string $name): Supplier
    {
        return Supplier::create(['name' => $name, 'phone' => '07'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT)]);
    }

    private function amount(float|string $value): string
    {
        return '<bdi>'.number_format((float) $value, 2).'</bdi> '.__('messages.afn');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/reports/aging')->assertRedirect(route('login'));
    }

    public function test_outstanding_orders_are_bucketed_by_age(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Buckets Customer');

        $young = $this->makeOrder($customer, ['total_amount' => '100.00']);
        $this->age($young, 10);
        $middle = $this->makeOrder($customer, ['total_amount' => '200.00']);
        $this->age($middle, 45);
        $old = $this->makeOrder($customer, ['total_amount' => '300.00']);
        $this->age($old, 75);
        $ancient = $this->makeOrder($customer, ['total_amount' => '400.00']);
        $this->age($ancient, 120);

        $response = $this->get('/reports/aging');

        $response->assertOk();
        $response->assertSee('Buckets Customer');
        $response->assertSee($this->amount(1000), false);
        $response->assertSee('0-30: 100.00', false);
        $response->assertSee('31-60: 200.00', false);
        $response->assertSee('61-90: 300.00', false);
        $response->assertSee('90+: 400.00', false);
    }

    public function test_supplier_type_order_is_grouped_under_the_supplier_not_walk_in(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Party Supplier');

        Order::create([
            'customer_id' => null,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '500.00',
            'currency' => 'AFN',
        ]);

        $response = $this->get('/reports/aging');

        $response->assertOk();
        $response->assertSee('Party Supplier');
        $response->assertDontSee(__('messages.walk_in_customer'));
    }

    public function test_orders_without_a_party_group_under_walk_in(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Order::create([
            'customer_id' => null,
            'person_type' => 'customer',
            'person_id' => null,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '100.00',
            'currency' => 'AFN',
        ]);

        $response = $this->get('/reports/aging');

        $response->assertOk();
        $response->assertSee(__('messages.walk_in_customer'));
        $response->assertSee($this->amount(100), false);
    }

    public function test_foreign_currency_payment_does_not_reduce_outstanding(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Currency Customer');
        $order = $this->makeOrder($customer, ['total_amount' => '100.00']);

        Payment::create(['order_id' => $order->id, 'amount' => '50.00', 'currency' => 'USD']);

        $response = $this->get('/reports/aging');

        $response->assertOk();
        $response->assertSee('Currency Customer');
        $response->assertSee($this->amount(100), false);
        $response->assertDontSee($this->amount(50), false);
    }

    public function test_payment_in_the_order_currency_reduces_outstanding(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Paid Customer');
        $order = $this->makeOrder($customer, ['total_amount' => '100.00']);

        Payment::create(['order_id' => $order->id, 'amount' => '40.00', 'currency' => 'AFN']);

        $response = $this->get('/reports/aging');

        $response->assertOk();
        $response->assertSee($this->amount(60), false);
        $response->assertDontSee($this->amount(100), false);
    }

    public function test_foreign_currency_return_does_not_drop_the_order_from_the_report(): void
    {
        // Regression: the SQL pre-filter summed returns in any currency, so an AFN order with
        // a USD return was excluded even though it still carried an AFN balance.
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Return Customer');
        $order = $this->makeOrder($customer, ['total_amount' => '100.00']);

        OrderReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'return_date' => now()->toDateString(),
            'total_amount' => '100.00',
            'status' => 'completed',
            'currency' => 'USD',
        ]);

        $response = $this->get('/reports/aging');

        $response->assertOk();
        $response->assertSee('Return Customer');
        $response->assertSee($this->amount(100), false);
    }

    public function test_return_in_the_order_currency_reduces_outstanding(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Returned Customer');
        $order = $this->makeOrder($customer, ['total_amount' => '100.00']);

        OrderReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'return_date' => now()->toDateString(),
            'total_amount' => '40.00',
            'status' => 'completed',
            'currency' => 'AFN',
        ]);

        $response = $this->get('/reports/aging');

        $response->assertOk();
        $response->assertSee($this->amount(60), false);
        $response->assertDontSee($this->amount(100), false);
    }

    public function test_fully_paid_and_cancelled_orders_are_excluded(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Excluded Customer');

        $paid = $this->makeOrder($customer, ['total_amount' => '100.00']);
        Payment::create(['order_id' => $paid->id, 'amount' => '100.00', 'currency' => 'AFN']);
        $cancelled = $this->makeOrder($customer, ['total_amount' => '100.00']);
        $cancelled->update(['status' => 'cancelled']);

        $response = $this->get('/reports/aging');

        $response->assertOk();
        $response->assertDontSee('Excluded Customer');
        $response->assertSee(__('messages.no_debt_found'));
    }

    public function test_currency_filter_isolates_currencies(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Filter Customer');

        $this->makeOrder($customer, ['total_amount' => '100.00', 'currency' => 'AFN']);
        $this->makeOrder($customer, ['total_amount' => '50.00', 'currency' => 'USD']);

        $response = $this->get('/reports/aging?currency=USD');

        $response->assertOk();
        $response->assertSee('<bdi>50.00</bdi> $', false);
        $response->assertDontSee('100.00', false);
    }

    public function test_debtors_are_sorted_by_outstanding_descending(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $small = $this->makeCustomer('Small Debtor');
        $big = $this->makeCustomer('Big Debtor');
        $this->makeOrder($small, ['total_amount' => '10.00']);
        $this->makeOrder($big, ['total_amount' => '900.00']);

        $response = $this->get('/reports/aging');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertGreaterThan(
            strpos($content, 'Big Debtor'),
            strpos($content, 'Small Debtor'),
            'Largest debtor should be listed first.'
        );
    }

    public function test_outstanding_purchases_appear_under_supplier_payables(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Payable Supplier');

        $this->makePurchase($supplier, ['total_amount' => '250.00']);

        $response = $this->get('/reports/aging');

        $response->assertOk();
        $response->assertSee(__('messages.supplier_payables'));
        $response->assertSee('Payable Supplier');
        $response->assertSee($this->amount(250), false);
    }

    public function test_foreign_currency_purchase_payment_does_not_reduce_payable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Mixed Payment Supplier');
        $purchase = $this->makePurchase($supplier, ['total_amount' => '100.00']);

        PurchasePayment::create(['purchase_id' => $purchase->id, 'amount' => '50.00', 'currency' => 'USD']);

        $response = $this->get('/reports/aging');

        $response->assertOk();
        $response->assertSee($this->amount(100), false);
    }

    public function test_customer_type_purchase_is_not_reported_as_a_supplier_payable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Customer Party');

        Purchase::create([
            'supplier_id' => null,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '300.00',
            'currency' => 'AFN',
        ]);

        $response = $this->get('/reports/aging');

        $response->assertOk();
        $response->assertSee('Customer Party');
        $response->assertDontSee(__('messages.walk_in_supplier'));
    }

    public function test_payment_store_rejects_currency_mismatch(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Blocked Payment');
        $order = $this->makeOrder($customer, ['total_amount' => '100.00']);

        $response = $this->post(route('payments.store'), [
            'type' => 'order',
            'order_id' => $order->id,
            'amount' => '10.00',
            'currency' => 'USD',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('payments', ['order_id' => $order->id]);
    }

    public function test_customer_and_supplier_sharing_a_person_id_are_not_merged(): void
    {
        // Regression: grouping by person_id alone merged a customer order and a
        // supplier-type order whose person_ids collided, reporting both debts
        // under whichever party appeared first.
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Split Customer');
        $supplier = $this->makeSupplier('Split Supplier');

        $this->assertSame($customer->id, $supplier->id, 'Test requires matching person ids');

        $this->makeOrder($customer, ['total_amount' => '100.00']);
        Order::create([
            'customer_id' => null,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '200.00',
            'currency' => 'AFN',
        ]);

        $response = $this->get('/reports/aging');

        $response->assertOk();
        $response->assertSee('Split Customer');
        $response->assertSee('Split Supplier');
        $response->assertSee($this->amount(100), false);
        $response->assertSee($this->amount(200), false);
    }

    public function test_debtors_are_sorted_numerically_not_lexicographically(): void
    {
        // Regression: sortByDesc on the decimal-string total ranked "900.00"
        // above "1000.00" because string comparison is lexicographic.
        $user = User::factory()->create();
        $this->actingAs($user);

        $nineHundred = $this->makeCustomer('Nine Hundred Debtor');
        $thousand = $this->makeCustomer('Thousand Debtor');
        $this->makeOrder($nineHundred, ['total_amount' => '900.00']);
        $this->makeOrder($thousand, ['total_amount' => '1000.00']);

        $response = $this->get('/reports/aging');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertGreaterThan(
            strpos($content, 'Thousand Debtor'),
            strpos($content, 'Nine Hundred Debtor'),
            '1000.00 must sort above 900.00.'
        );
    }
}
