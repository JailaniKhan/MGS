<?php

namespace Tests\Feature;

use App\Http\Controllers\CashbookController;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CashbookTest extends TestCase
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

    private function postEntry(array $overrides = []): TestResponse
    {
        return $this->post(route('cashbook.store'), array_merge([
            'type' => 'in',
            'amount' => '100.00',
            'currency' => 'AFN',
            'entry_date' => now()->toDateString(),
            'person' => '',
            'notes' => '',
        ], $overrides));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('cashbook.index'))->assertRedirect(route('login'));
        $this->get(route('cashbook.create'))->assertRedirect(route('login'));
        $this->get(route('cashbook.person', ['customer', 1]))->assertRedirect(route('login'));
        $this->post(route('cashbook.store'))->assertRedirect(route('login'));
    }

    public function test_store_records_cashbook_in_and_increases_cash_balance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postEntry(['type' => 'in', 'amount' => '250.00']);

        $response->assertRedirect(route('cashbook.index'));
        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $user->id,
            'source' => 'cashbook_in',
            'reference_type' => null,
            'reference_id' => null,
        ]);
        $this->assertDatabaseHas('ledger_entries', ['direction' => 'credit', 'amount' => '250.00']);

        $this->get(route('cashbook.index'))->assertSee('250.00', false);
    }

    public function test_store_records_cashbook_out_as_cash_debit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postEntry(['type' => 'out', 'amount' => '80.00']);

        $response->assertRedirect(route('cashbook.index'));
        $this->assertDatabaseHas('journal_entries', ['source' => 'cashbook_out']);
        $this->assertDatabaseHas('ledger_entries', ['direction' => 'debit', 'amount' => '80.00']);
    }

    public function test_store_links_entry_to_person_reference(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Referenced Customer');

        $response = $this->postEntry(['amount' => '50.00', 'person' => 'customer:'.$customer->id]);

        $response->assertRedirect(route('cashbook.index'));
        $this->assertDatabaseHas('journal_entries', [
            'source' => 'cashbook_in',
            'reference_type' => 'customer',
            'reference_id' => $customer->id,
        ]);
    }

    public function test_store_rejects_mismatched_person_reference(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Supplier Person');

        $response = $this->postEntry(['person' => 'customer:'.$supplier->id]);

        $response->assertSessionHasErrors('person');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_store_rejects_amount_below_minimum(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postEntry(['amount' => '0.00'])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_index_groups_entries_by_person_and_shows_net_per_currency(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $alpha = $this->makeCustomer('Alpha Cash');
        $beta = $this->makeSupplier('Beta Cash');

        $this->postEntry(['type' => 'in', 'amount' => '100.00', 'person' => 'customer:'.$alpha->id]);
        $this->postEntry(['type' => 'in', 'amount' => '50.00', 'person' => 'customer:'.$alpha->id]);
        $this->postEntry(['type' => 'out', 'amount' => '40.00', 'person' => 'customer:'.$alpha->id]);
        $this->postEntry(['type' => 'in', 'amount' => '200.00', 'currency' => 'USD', 'person' => 'supplier:'.$beta->id]);

        $response = $this->get(route('cashbook.index'));

        $response->assertOk();
        $response->assertSee('Alpha Cash');
        $response->assertSee('Beta Cash');
        $response->assertSee('110.00', false);
        $response->assertSee('200.00', false);
    }

    public function test_index_shows_closing_balances_per_person_and_currency(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Closing Balance Row');

        // AFN: cash in 100, order pending 500 -> closing = 100 − 500 = −400
        // (in side − out side; documents weigh on the out side).
        $this->postEntry(['type' => 'in', 'amount' => '100.00', 'person' => 'customer:'.$customer->id]);
        $afnOrder = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '500.00',
            'currency' => 'AFN',
        ]);
        $afnOrder->forceFill(['created_at' => now()->subDay()->setTime(9, 0)])->save();

        // USD: order pending 20 -> closing = 0 − 20 = −20.
        $usdOrder = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '20.00',
            'currency' => 'USD',
        ]);
        $usdOrder->forceFill(['created_at' => now()->subDay()->setTime(9, 0)])->save();

        $response = $this->get(route('cashbook.index'));

        $response->assertOk();
        $response->assertSee('Closing Balance Row');
        // Row shows each currency's closing figure, rendered as a link that
        // opens the statement in exactly that currency.
        $response->assertSee('href="'.route('cashbook.person', ['customer', $customer->id, 'currency' => 'AFN']).'"', false);
        $response->assertSee('href="'.route('cashbook.person', ['customer', $customer->id, 'currency' => 'USD']).'"', false);
        $response->assertSee('400.00', false);
        $response->assertSee('20.00', false);
        // The hero reports the summed closing balance of both currencies.
        $response->assertSee(__('messages.closing_balance'));
    }

    public function test_index_closing_balance_matches_statement_closing_bar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Parity Check');
        $supplier = $this->makeSupplier('Parity Supplier');

        // AFN: cash in 300, order pending 500, purchase pending 200
        // -> closing = 300 − (500 + 200) = −400 on BOTH pages (in side minus
        // out side; out side includes pending documents).
        $this->postEntry(['type' => 'in', 'amount' => '300.00', 'person' => 'customer:'.$customer->id]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '500.00',
            'currency' => 'AFN',
        ]);
        $order->forceFill(['created_at' => now()->subDays(2)->setTime(9, 0)])->save();

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '200.00',
            'currency' => 'AFN',
        ]);
        $purchase->forceFill(['created_at' => now()->subDay()->setTime(9, 0)])->save();

        // The purchase belongs to the supplier; re-point it to the customer so
        // all lines sit on one person's statement.
        DB::table('purchases')->where('id', $purchase->id)->update([
            'person_type' => 'customer',
            'person_id' => $customer->id,
        ]);

        $indexResponse = $this->get(route('cashbook.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('400.00', false);

        $statementResponse = $this->get(route('cashbook.person', ['customer', $customer->id]));
        $statementResponse->assertOk();
        $statementResponse->assertSee('data-closing="-400.00"', false);
    }

    public function test_create_shows_searchable_person_options(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->makeCustomer('Pickable Customer');

        $response = $this->get(route('cashbook.create'));

        $response->assertOk();
        $response->assertSee('Pickable Customer');
        $response->assertSee(__('messages.select_person'));
    }

    public function test_person_page_shows_cashbook_net_and_entry_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Person Detail');
        $this->postEntry(['type' => 'in', 'amount' => '100.00', 'person' => 'customer:'.$customer->id]);
        $this->postEntry(['type' => 'in', 'amount' => '50.00', 'person' => 'customer:'.$customer->id]);
        $this->postEntry(['type' => 'out', 'amount' => '20.00', 'person' => 'customer:'.$customer->id]);

        $response = $this->get(route('cashbook.person', ['customer', $customer->id]));

        $response->assertOk();
        $response->assertSee('Person Detail');
        // All lines render with their running balances ('in' adds, 'out'
        // subtracts): 100 -> 150 -> 130.
        $response->assertSee('data-balance="100.00"', false);
        $response->assertSee('data-balance="150.00"', false);
        $response->assertSee('data-balance="130.00"', false);
        // Closing bar equals the net of the three entries (no documents).
        $response->assertSee('data-closing="130.00"', false);
    }

    public function test_person_page_excludes_cancelled_documents_from_outstanding(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Cancelled Doc');
        $live = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '500.00',
            'currency' => 'AFN',
        ]);
        Payment::create(['order_id' => $live->id, 'amount' => '300.00', 'currency' => 'AFN']);

        Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'cancelled',
            'subtotal' => '0.00',
            'total_amount' => '900.00',
            'currency' => 'AFN',
        ]);

        $response = $this->get(route('cashbook.person', ['customer', $customer->id]));

        $response->assertOk();
        $response->assertSee('200.00', false);
        $response->assertDontSee('900.00');
    }

    public function test_person_page_shows_purchase_outstanding_for_supplier(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Supplier Docs');
        Purchase::create([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '700.00',
            'currency' => 'AFN',
        ]);

        $response = $this->get(route('cashbook.person', ['supplier', $supplier->id]));

        $response->assertOk();
        $response->assertSee('Supplier Docs');
        $response->assertSee('700.00', false);
    }

    public function test_person_statement_order_renders_as_out_but_keeps_cash_closing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $supplier = $this->makeSupplier('Flip Supplier');

        // Reported scenario (supplier statement): cash-in 1,200, purchase
        // pending 1,050, order pending 1,050. The order must render as an
        // 'out' line (red, minus sign) — never 'in'. The closing compares
        // the in side against the out side: 1,200 − (1,050 + 1,050) = −900,
        // negative because the out side outweighs the in side, so it renders
        // red with '-'. Only cashbook lines move the running balance, so the
        // cash-in line reads +1,200 and both document rows carry it forward.
        $this->postEntry([
            'type' => 'in',
            'amount' => '1200.00',
            'person' => 'supplier:'.$supplier->id,
            'entry_date' => now()->toDateString(),
        ]);

        Purchase::create([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'pending',
            'subtotal' => '0.00',
            'total_amount' => '1050.00',
            'currency' => 'AFN',
        ]);

        $order = Order::create([
            'customer_id' => null,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'pending',
            'subtotal' => '0.00',
            'total_amount' => '1050.00',
            'currency' => 'AFN',
        ]);

        $response = $this->get(route('cashbook.person', ['supplier', $supplier->id]));

        $response->assertOk();
        // Both documents render as outflows (minus sign), never inflows.
        $response->assertSee('-1,050.00', false);
        $response->assertDontSee('+1,050.00', false);
        // The order row keeps its link to the order page.
        $response->assertSee('href="'.route('orders.show', $order->id).'"', false);
        // The cash-in line adds to the running balance; document rows
        // carry the balance forward unchanged.
        $response->assertSee('data-balance="1200.00"', false);
        // Closing bar = in side − out side (documents weigh on the out side):
        // negative -> renders red with '-'.
        $response->assertSee('data-closing="-900.00"', false);
        $response->assertSee('text-danger-600 dark:text-danger-400', false);
    }

    public function test_person_statement_lines_carry_running_balance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Statement Runner');

        // Worked example: order pending 500 (context row) -> cash in 300 ->
        // cash out 100. Balances must read 0, then 300, then 200 — only
        // cashbook lines move the running balance ('in' adds, 'out'
        // subtracts); documents carry it forward. Closing = in side − out
        // side = 300 − (500 + 100) = −300.
        $order = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '500.00',
            'currency' => 'AFN',
        ]);
        $order->forceFill(['created_at' => now()->subDays(2)->setTime(10, 0)])->save();

        $this->postEntry([
            'type' => 'in',
            'amount' => '300.00',
            'person' => 'customer:'.$customer->id,
            'entry_date' => now()->subDay()->toDateString(),
        ]);

        $this->postEntry([
            'type' => 'out',
            'amount' => '100.00',
            'person' => 'customer:'.$customer->id,
            'entry_date' => now()->toDateString(),
        ]);

        $response = $this->get(route('cashbook.person', ['customer', $customer->id]));

        $response->assertOk();
        // Each row exposes its running balance: 0 -> 300 -> 200.
        $response->assertSee('data-balance="0.00"', false);
        $response->assertSee('data-balance="300.00"', false);
        $response->assertSee('data-balance="200.00"', false);
        // Closing balance bar = in side − out side (documents weigh on out).
        $response->assertSee(__('messages.closing_balance'));
        $response->assertSee('data-closing="-300.00"', false);
    }

    public function test_person_statement_currency_tabs_keep_balances_independent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = $this->makeCustomer('Currency Splitter');

        $afnOrder = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '500.00',
            'currency' => 'AFN',
        ]);
        $afnOrder->forceFill(['created_at' => now()->subDays(2)->setTime(9, 0)])->save();

        $usdOrder = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '20.00',
            'currency' => 'USD',
        ]);
        $usdOrder->forceFill(['created_at' => now()->subDays(1)->setTime(9, 0)])->save();

        $this->postEntry([
            'type' => 'in',
            'amount' => '100.00',
            'currency' => 'AFN',
            'person' => 'customer:'.$customer->id,
            'entry_date' => now()->subDay()->toDateString(),
        ]);

        // Default view (AFN): order context 0 -> cash in 100, no USD lines
        // polluting the statement.
        $afnResponse = $this->get(route('cashbook.person', ['customer', $customer->id]));
        $afnResponse->assertOk();
        $afnResponse->assertSee('data-balance="0.00"', false);
        $afnResponse->assertSee('data-balance="100.00"', false);

        // USD tab: only the 20.00 order line (context, balance 0.00), no AFN
        // balances.
        $usdResponse = $this->get(route('cashbook.person', ['customer', $customer->id, 'currency' => 'USD']));
        $usdResponse->assertOk();
        $usdResponse->assertSee('data-balance="0.00"', false);
        $usdResponse->assertDontSee('data-balance="500.00"', false);
        $usdResponse->assertDontSee('data-balance="100.00"', false);
    }

    /**
     * Regression: the closing-balance query must offset a document by its
     * same-currency payments even when a foreign-currency payment row exists
     * on the document. The currency match lives in the join ON clause — a
     * SELECT-level CASE lets a foreign-currency fan row feed SQLite's scalar
     * MAX(expr, 0) an arbitrary value, silently dropping the offset.
     */
    public function test_closing_ignores_foreign_currency_payment_but_keeps_same_currency_offset(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = $this->makeCustomer('Cross Currency');

        $order = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'pending',
            'subtotal' => '0.00',
            'total_amount' => '1000.00',
            'currency' => 'AFN',
        ]);

        Payment::create(['order_id' => $order->id, 'amount' => '50.00', 'currency' => 'USD']);
        Payment::create(['order_id' => $order->id, 'amount' => '400.00', 'currency' => 'AFN']);

        $controller = app(CashbookController::class);
        $method = new \ReflectionMethod($controller, 'applyDocumentClosing');

        $closing = ['customer-'.$customer->id => ['AFN' => '0.00', 'USD' => '0.00']];
        $method->invokeArgs($controller, [&$closing, 'order', 'orders', [$customer->id]]);

        // Remaining = 1000 − 400 (USD payment does not settle an AFN document).
        $this->assertSame('-600.00', $closing['customer-'.$customer->id]['AFN']);
        $this->assertSame('0.00', $closing['customer-'.$customer->id]['USD']);
    }
}
