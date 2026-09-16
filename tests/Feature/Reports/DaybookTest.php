<?php

namespace Tests\Feature\Reports;

use App\Models\Account;
use App\Models\CashbookEntry;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PartyPayment;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\SalaryPayment;
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

    public function test_single_day_page_renders_without_period_chips(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/reports/daybook');

        $response->assertOk();
        $response->assertSee('value="'.Carbon::now()->toDateString().'"', false);
        // The daybook is single-day: the period chips must be gone entirely.
        $response->assertDontSee(__('messages.last_7_days'));
        $response->assertDontSee(__('messages.last_30_days'));
        $response->assertDontSee(__('messages.last_90_days'));
        $response->assertDontSee(__('messages.this_month'));
        $response->assertDontSee('name="period"');
        $response->assertSee(__('messages.no_transactions_found'));
    }

    public function test_period_parameter_is_ignored(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // A stale period link (e.g. from history) must not error or widen the window.
        $response = $this->get('/reports/daybook?period=30&date_to=2026-08-12');

        $response->assertOk();
        $response->assertSee('value="2026-08-12"', false);
    }

    public function test_invalid_date_and_currency_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/reports/daybook?date_to=not-a-date')->assertRedirect();
        $this->get('/reports/daybook?currency=EUR')->assertRedirect();
    }

    public function test_cash_entry_on_the_day_renders_without_crashing(): void
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

    public function test_previous_day_transactions_are_excluded(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $recent = CashbookEntry::create([
            'type' => 'in',
            'amount' => '50.00',
            'currency' => 'AFN',
            'notes' => 'Recent deposit',
            'entry_date' => '2026-08-12',
        ]);
        $recent->forceFill(['created_at' => '2026-08-12 10:00:00'])->save();

        $old = CashbookEntry::create([
            'type' => 'in',
            'amount' => '60.00',
            'currency' => 'AFN',
            'notes' => 'Old deposit',
            'entry_date' => '2026-08-11',
        ]);
        $old->forceFill(['created_at' => '2026-08-11 10:00:00'])->save();

        $response = $this->get('/reports/daybook?date_to=2026-08-12');

        $response->assertOk();
        $response->assertSee('Recent deposit');
        $response->assertSee('+50.00', false);
        $response->assertDontSee('Old deposit');
        $response->assertDontSee('+60.00', false);
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

    public function test_closing_balance_matches_balance_sheet_cash_in_hand(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // History before the day: opening 1,000 via a legacy cashbook-in entry.
        $opening = CashbookEntry::create([
            'type' => 'in',
            'amount' => '1000.00',
            'currency' => 'AFN',
            'entry_date' => '2026-08-01',
        ]);
        $opening->forceFill(['created_at' => '2026-08-01 10:00:00'])->save();

        // Today (the reported day): +200 in, −150 out.
        $today = CashbookEntry::create([
            'type' => 'in',
            'amount' => '200.00',
            'currency' => 'AFN',
            'entry_date' => '2026-08-12',
        ]);
        $today->forceFill(['created_at' => '2026-08-12 09:00:00'])->save();

        $todayOut = CashbookEntry::create([
            'type' => 'out',
            'amount' => '150.00',
            'currency' => 'AFN',
            'entry_date' => '2026-08-12',
        ]);
        $todayOut->forceFill(['created_at' => '2026-08-12 10:00:00'])->save();

        $response = $this->get('/reports/daybook?date_to=2026-08-12');

        $response->assertOk();
        // Opening = 1,000 (history before 12 Aug only)...
        $response->assertSee('1,000.00');
        // ...closing = opening + net = 1,050.00, i.e. the balance sheet's
        // Cash in Hand for that moment (all history included).
        $response->assertSee('1,050.00');
        // The empty double-entry ledger must NOT zero these out.
        $response->assertDontSee('950.00');
    }

    public function test_salary_payments_render_as_cash_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $employee = Employee::create(['name' => 'Ahmad', 'position' => 'Worker', 'monthly_salary' => '0']);

        $salary = SalaryPayment::create([
            'employee_id' => $employee->id,
            'amount' => '120.00',
            'currency' => 'AFN',
            'for_month' => '2026-08-01',
        ]);
        $salary->forceFill(['created_at' => '2026-08-12 11:00:00'])->save();

        $response = $this->get('/reports/daybook?date_to=2026-08-12');

        $response->assertOk();
        $response->assertSee(__('messages.salary_dash').'Ahmad');
        $response->assertSee('-120.00', false);
    }

    public function test_unallocated_party_payment_renders_and_reconciles(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'OnAccount Customer', 'phone' => '0700000007']);

        // A party payment that was NOT allocated to any document (kept on
        // account) — it never appears in the payments table, so the daybook
        // must surface it via party_payments.
        $partyPayment = PartyPayment::create([
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'amount' => '75.00',
            'currency' => 'AFN',
            'type' => 'payment_received',
            'notes' => 'Kept on account',
        ]);
        $partyPayment->forceFill(['created_at' => '2026-08-12 12:00:00'])->save();

        $response = $this->get('/reports/daybook?date_to=2026-08-12');

        $response->assertOk();
        $response->assertSee('OnAccount Customer');
        $response->assertSee('+75.00', false);
        // It is real cash in: the day's net (and closing balance) must move by it.
        $response->assertSee('75.00');
    }

    public function test_journal_cashbook_entries_render_for_the_day(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // What CashbookController::store posts today (Fix C2): a journal
        // entry with source cashbook_in — not a legacy CashbookEntry row.
        $journal = JournalEntry::create([
            'user_id' => $user->id,
            'description' => 'Cash Income',
            'transaction_date' => '2026-08-12',
            'currency' => 'AFN',
            'source' => 'cashbook_in',
        ]);
        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'account_id' => Account::create(['name' => 'Cash', 'type' => 'cash', 'currency' => 'AFN'])->id,
            'amount' => '90.00',
            'direction' => 'credit',
        ]);
        $journal->forceFill(['created_at' => '2026-08-12 13:00:00'])->save();

        $response = $this->get('/reports/daybook?date_to=2026-08-12');

        $response->assertOk();
        $response->assertSee('Cash Income');
        $response->assertSee('+90.00', false);
    }

    public function test_sales_rows_are_reference_only_and_do_not_inflate_totals(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

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

    public function test_expense_rows_appear_as_cash_out_with_purchase_reference(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $supplier = Supplier::create(['name' => 'Daybook Freight Sup', 'phone' => '0700000099']);
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'person_type' => 'supplier',
            'person_id' => $supplier->id,
            'status' => 'completed',
            'subtotal' => '500.00',
            'total_amount' => '500.00',
            'currency' => 'AFN',
        ]);
        $purchase->forceFill(['created_at' => '2026-08-10 09:00:00'])->save();

        Expense::create([
            'user_id' => $user->id,
            'purchase_id' => $purchase->id,
            'category' => 'Freight',
            'amount' => '120.00',
            'currency' => 'AFN',
            'expense_date' => '2026-08-12',
        ]);

        Expense::create([
            'user_id' => $user->id,
            'category' => 'Rent',
            'amount' => '80.00',
            'currency' => 'AFN',
            'expense_date' => '2026-08-12',
        ]);

        $response = $this->get('/reports/daybook?date_to=2026-08-12');

        $response->assertOk();
        // Both expenses are cash-out rows...
        $response->assertSee('-120.00', false);
        $response->assertSee('-80.00', false);
        // ...the linked one carries its purchase reference.
        $response->assertSee('#'.$purchase->id, false);
        // Total out includes both.
        $response->assertSee('-200.00', false);
    }

    public function test_purchase_payment_uses_payment_to_label_with_party_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $supplier = Supplier::create(['name' => 'Label Check Sup', 'phone' => '0700000011']);
        $purchase = $this->makePurchase($user, $supplier);
        $purchase->forceFill(['created_at' => '2026-08-12 08:00:00'])->save();

        $payment = PurchasePayment::create([
            'purchase_id' => $purchase->id,
            'amount' => '140.00',
            'currency' => 'AFN',
        ]);
        $payment->forceFill(['created_at' => '2026-08-12 08:30:00'])->save();

        $response = $this->get('/reports/daybook?date_to=2026-08-12');

        $response->assertOk();
        // Correct "Payment to <party>" label, not "Pending Purchase -".
        $response->assertSee(__('messages.payment_to').$supplier->name);
        $response->assertDontSee(__('messages.pending_purchase_dash').$supplier->name);
    }

    public function test_customer_payment_uses_payment_from_label_with_party_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'Label Check Cust', 'phone' => '0700000012']);
        $order = $this->makeOrder($user, $customer);
        $order->forceFill(['created_at' => '2026-08-12 08:00:00'])->save();

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => '130.00',
            'currency' => 'AFN',
        ]);
        $payment->forceFill(['created_at' => '2026-08-12 08:30:00'])->save();

        $response = $this->get('/reports/daybook?date_to=2026-08-12');

        $response->assertOk();
        // Correct "Payment from <party>" label, not "Supplier Delivery -".
        $response->assertSee(__('messages.payment_from').$customer->name);
        $response->assertDontSee(__('messages.supplier_delivery_dash').$customer->name);
    }

    public function test_opening_balance_counts_history_from_all_cash_sources(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // History across every source the balance sheet counts, all BEFORE the day.
        $customer = Customer::create(['name' => 'History Cust', 'phone' => '0700000021']);
        $histOrder = $this->makeOrder($user, $customer);
        $histOrder->forceFill(['created_at' => '2026-08-01 09:00:00'])->save();
        $histPayment = Payment::create(['order_id' => $histOrder->id, 'amount' => '300.00', 'currency' => 'AFN']);
        $histPayment->forceFill(['created_at' => '2026-08-01 09:30:00'])->save();

        $journal = JournalEntry::create([
            'user_id' => $user->id,
            'description' => 'Legacy journal out',
            'transaction_date' => '2026-08-02',
            'currency' => 'AFN',
            'source' => 'cashbook_out',
        ]);
        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'account_id' => Account::create(['name' => 'Cash J', 'type' => 'cash', 'currency' => 'AFN'])->id,
            'amount' => '100.00',
            'direction' => 'debit',
        ]);

        Expense::create([
            'user_id' => $user->id,
            'category' => 'History Rent',
            'amount' => '50.00',
            'currency' => 'AFN',
            'expense_date' => '2026-08-03',
        ]);

        // The reported day itself is empty: closing must equal opening = 300 − 100 − 50 = 150.
        $response = $this->get('/reports/daybook?date_to=2026-08-12');

        $response->assertOk();
        $response->assertSee('150.00');
    }
}
