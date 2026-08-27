<?php

namespace Tests\Feature\Reports;

use App\Models\Account;
use App\Models\CashbookEntry;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DaybookTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(User $user, Customer $customer, array $overrides = []): Order
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

    private function makePurchase(User $user, Supplier $supplier, array $overrides = []): Purchase
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
        $this->get('/reports/daybook')->assertRedirect(route('login'));
    }

    public function test_default_range_renders_single_date_input_with_chips(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/reports/daybook');

        $response->assertOk();
        $response->assertSee('value="'.Carbon::now()->toDateString().'"', false);
        $response->assertDontSee('name="start_date"');
        $response->assertDontSee('name="end_date"');
        $response->assertSee(__('messages.last_7_days'));
        $response->assertSee(__('messages.last_30_days'));
        $response->assertSee(__('messages.last_90_days'));
        $response->assertSee(__('messages.this_month'));
        $response->assertSee(__('messages.no_transactions_found'));
    }

    public function test_cash_entry_in_range_renders_without_crashing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        CashbookEntry::create([
            'type' => 'in',
            'amount' => '200.00',
            'currency' => 'AFN',
            'notes' => 'Rent collected',
            'entry_date' => Carbon::now()->toDateString(),
        ]);

        $response = $this->get('/reports/daybook');

        $response->assertOk();
        $response->assertSee('Rent collected');
        $response->assertSee(__('messages.type_cash_in'));
        $response->assertSee('+200.00', false);
        $response->assertDontSee('00:00:00');
    }

    public function test_same_day_transactions_at_end_of_day_are_included(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'SameDay Customer', 'phone' => '0700000000']);
        $order = $this->makeOrder($user, $customer);
        $order->forceFill(['created_at' => '2026-08-12 23:59:00'])->save();

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => '100.00',
            'currency' => 'AFN',
        ]);
        $payment->forceFill(['created_at' => '2026-08-12 23:59:30'])->save();

        $response = $this->get('/reports/daybook?date_to=2026-08-12');

        $response->assertOk();
        $response->assertSee('SameDay Customer');
        $response->assertSee('+100.00', false);
    }

    public function test_period_seven_excludes_transactions_outside_the_window(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $recent = CashbookEntry::create([
            'type' => 'in',
            'amount' => '50.00',
            'currency' => 'AFN',
            'notes' => 'Recent deposit',
            'entry_date' => '2026-08-09',
        ]);
        $recent->forceFill(['created_at' => '2026-08-09 10:00:00'])->save();

        $old = CashbookEntry::create([
            'type' => 'in',
            'amount' => '60.00',
            'currency' => 'AFN',
            'notes' => 'Old deposit',
            'entry_date' => '2026-08-04',
        ]);
        $old->forceFill(['created_at' => '2026-08-04 10:00:00'])->save();

        $response = $this->get('/reports/daybook?period=7&date_to=2026-08-12');

        $response->assertOk();
        $response->assertSee('Recent deposit');
        $response->assertSee('+50.00', false);
        $response->assertDontSee('Old deposit');
        $response->assertDontSee('+60.00', false);
    }

    public function test_month_period_spans_the_whole_month_of_the_anchor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        CashbookEntry::create([
            'type' => 'in',
            'amount' => '30.00',
            'currency' => 'AFN',
            'entry_date' => '2026-08-01',
        ]);
        CashbookEntry::create([
            'type' => 'in',
            'amount' => '40.00',
            'currency' => 'AFN',
            'entry_date' => '2026-08-31',
        ]);

        $response = $this->get('/reports/daybook?period=month&date_to=2026-08-31');

        $response->assertOk();
        $response->assertSee('+30.00', false);
        $response->assertSee('+40.00', false);
        $response->assertSee('+70.00', false);
    }

    public function test_currency_filter_keeps_amounts_separate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $afnCustomer = Customer::create(['name' => 'AFN Buyer', 'phone' => '0700000001']);
        $afnOrder = $this->makeOrder($user, $afnCustomer);
        Payment::create(['order_id' => $afnOrder->id, 'amount' => '100.00', 'currency' => 'AFN']);

        $usdCustomer = Customer::create(['name' => 'USD Buyer', 'phone' => '0700000002']);
        $usdOrder = $this->makeOrder($user, $usdCustomer, ['currency' => 'USD']);
        Payment::create(['order_id' => $usdOrder->id, 'amount' => '50.00', 'currency' => 'USD']);

        $afnResponse = $this->get('/reports/daybook?date_to='.Carbon::now()->toDateString());
        $afnResponse->assertOk();
        $afnResponse->assertSee('AFN Buyer');
        $afnResponse->assertDontSee('USD Buyer');
        $afnResponse->assertSee('+100.00', false);
        $afnResponse->assertDontSee('+50.00', false);

        $usdResponse = $this->get('/reports/daybook?date_to='.Carbon::now()->toDateString().'&currency=USD');
        $usdResponse->assertOk();
        $usdResponse->assertSee('USD Buyer');
        $usdResponse->assertDontSee('AFN Buyer');
        $usdResponse->assertSee('+50.00', false);
        $usdResponse->assertDontSee('+100.00', false);
    }

    public function test_running_balance_includes_opening_balance_and_moves_cash(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $account = Account::create(['name' => 'Cash', 'type' => 'cash', 'currency' => 'AFN']);
        $journal = JournalEntry::create([
            'description' => 'Opening balance',
            'transaction_date' => Carbon::now()->toDateString(),
            'currency' => 'AFN',
        ]);
        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $account->id,
            'amount' => '1000.00',
            'direction' => 'credit',
        ]);

        CashbookEntry::create([
            'type' => 'in',
            'amount' => '200.00',
            'currency' => 'AFN',
            'entry_date' => Carbon::now()->toDateString(),
        ]);

        $supplier = Supplier::create(['name' => 'Fixture Supplier', 'phone' => '0700000003']);
        $purchase = $this->makePurchase($user, $supplier);
        PurchasePayment::create([
            'purchase_id' => $purchase->id,
            'amount' => '150.00',
            'currency' => 'AFN',
        ]);

        $response = $this->get('/reports/daybook?date_to='.Carbon::now()->toDateString());

        $response->assertOk();
        // Live cash = 1,000.00 (ledger credit). Period net = +50.00 (200 in − 150 out),
        // so opening is derived as 950.00 and the day closes at the live 1,000.00.
        $response->assertSee('950.00');
        $response->assertSee('1,000.00');
        $response->assertDontSee('1,050.00');
    }

    public function test_running_balance_accumulates_chronologically_so_newest_day_shows_closing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $account = Account::create(['name' => 'Cash', 'type' => 'cash', 'currency' => 'AFN']);
        $journal = JournalEntry::create([
            'description' => 'Opening balance',
            'transaction_date' => '2026-08-01',
            'currency' => 'AFN',
        ]);
        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $account->id,
            'amount' => '1000.00',
            'direction' => 'credit',
        ]);

        $day1 = CashbookEntry::create([
            'type' => 'in',
            'amount' => '200.00',
            'currency' => 'AFN',
            'entry_date' => '2026-08-11',
        ]);
        $day1->forceFill(['created_at' => '2026-08-11 10:00:00'])->save();

        $day2 = CashbookEntry::create([
            'type' => 'out',
            'amount' => '150.00',
            'currency' => 'AFN',
            'entry_date' => '2026-08-12',
        ]);
        $day2->forceFill(['created_at' => '2026-08-12 10:00:00'])->save();

        $response = $this->get('/reports/daybook?period=30&date_to=2026-08-12');

        $response->assertOk();
        // Opening = 1,000 − (200 − 150) = 950. Chronological accumulation: 11 Aug = 1,150.00,
        // 12 Aug = 1,000.00. The NEWEST (12 Aug) row carries the closing balance, so it must
        // render before the older (11 Aug) row's running balance.
        $response->assertSee('950.00');
        // Day labels are locale-rendered now, so order the ledger by the running-balance
        // badges themselves: 12 Aug (closing 1,000.00) must appear before 11 Aug (1,150.00).
        $content = $response->getContent();
        $this->assertTrue(
            strrpos($content, '1,000.00') < strrpos($content, '1,150.00'),
            'Newest day (12 Aug, closing 1,000.00) must render before 11 Aug (1,150.00).'
        );
    }

    public function test_sales_rows_are_reference_only_and_do_not_inflate_totals(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $account = Account::create(['name' => 'Cash', 'type' => 'cash', 'currency' => 'AFN']);
        $journal = JournalEntry::create([
            'description' => 'Opening balance',
            'transaction_date' => '2026-08-01',
            'currency' => 'AFN',
        ]);
        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $account->id,
            'amount' => '1000.00',
            'direction' => 'credit',
        ]);

        $customer = Customer::create(['name' => 'Sale Ref Customer', 'phone' => '0700000009']);
        $order = $this->makeOrder($user, $customer, [
            'status' => 'completed',
            'subtotal' => '1500.00',
            'total_amount' => '1500.00',
        ]);
        $order->forceFill(['created_at' => '2026-08-12 09:00:00'])->save();

        CashbookEntry::create([
            'type' => 'in',
            'amount' => '200.00',
            'currency' => 'AFN',
            'entry_date' => '2026-08-12',
        ]);

        $response = $this->get('/reports/daybook?date_to=2026-08-12');

        $response->assertOk();
        $response->assertSee('Sale Ref Customer');
        // Sale total shown as a muted reference amount...
        $response->assertSee('1,500.00');
        // ...but income totals 200.00, NOT 1,700.00 (the sale must not inflate cash).
        $response->assertSee('+200.00', false);
        $response->assertDontSee('1,700.00');
    }
}
