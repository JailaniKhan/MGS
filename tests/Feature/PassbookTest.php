<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassbookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function createCashbookEntry(string $type, string $amount = '500.00', string $currency = 'AFN', array $overrides = []): JournalEntry
    {
        $account = Account::create(['name' => 'Cash', 'type' => 'cash', 'currency' => $currency]);

        $journal = JournalEntry::create(array_merge([
            'description' => 'Cash income',
            'transaction_date' => now()->toDateString(),
            'currency' => $currency,
            'source' => $type === 'in' ? 'cashbook_in' : 'cashbook_out',
        ], $overrides));

        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $account->id,
            'amount' => $amount,
            'direction' => 'debit',
        ]);

        return $journal;
    }

    private function createPayment(int $amount = 500, string $currency = 'AFN'): Payment
    {
        $order = Order::create([
            'customer_id' => null,
            'person_type' => 'customer',
            'person_id' => null,
            'status' => 'pending',
            'total_amount' => 1000,
            'currency' => $currency,
        ]);

        return Payment::create(['order_id' => $order->id, 'amount' => $amount, 'currency' => $currency]);
    }

    private function createPurchasePayment(int $amount = 200, string $currency = 'AFN'): PurchasePayment
    {
        $purchase = Purchase::create([
            'supplier_id' => null,
            'person_type' => 'supplier',
            'person_id' => null,
            'status' => 'pending',
            'total_amount' => 300,
            'currency' => $currency,
        ]);

        return PurchasePayment::create(['purchase_id' => $purchase->id, 'amount' => $amount, 'currency' => $currency]);
    }

    private function createExpense(int $amount = 100, string $currency = 'AFN'): Expense
    {
        return Expense::create([
            'category' => 'Rent',
            'amount' => $amount,
            'currency' => $currency,
            'expense_date' => now(),
        ]);
    }

    private function createSalaryEntry(int $amount = 150, string $currency = 'AFN'): JournalEntry
    {
        $account = Account::create(['name' => 'Cash', 'type' => 'cash', 'currency' => $currency]);

        $journal = JournalEntry::create([
            'description' => 'Salary - Ahmad',
            'transaction_date' => now()->toDateString(),
            'currency' => $currency,
            'source' => 'salary',
        ]);

        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $account->id,
            'amount' => $amount,
            'direction' => 'debit',
        ]);

        return $journal;
    }

    public function test_lists_all_cash_sources(): void
    {
        $cashIn = $this->createCashbookEntry('in');
        $cashOut = $this->createCashbookEntry('out');
        $payment = $this->createPayment();
        $purchasePayment = $this->createPurchasePayment();
        $expense = $this->createExpense();

        $response = $this->get('/passbook');

        $response->assertOk()
            ->assertSee('CB-'.$cashIn->id)
            ->assertSee('CB-'.$cashOut->id)
            ->assertSee('PAY-'.$payment->id)
            ->assertSee('PPAY-'.$purchasePayment->id)
            ->assertSee('EXP-'.$expense->id);
    }

    public function test_type_filter_limits_sources(): void
    {
        $this->createCashbookEntry('in');
        $payment = $this->createPayment();
        $expense = $this->createExpense();

        $response = $this->get('/passbook?filter=cash_in');

        $response->assertOk()
            ->assertSee('PAY-'.$payment->id)
            ->assertDontSee('EXP-'.$expense->id);

        $response = $this->get('/passbook?filter=expense');

        $response->assertOk()
            ->assertSee('EXP-'.$expense->id)
            ->assertDontSee('PAY-'.$payment->id);
    }

    public function test_cash_in_filter_only_returns_cashbook_in_entries(): void
    {
        $in = $this->createCashbookEntry('in');
        $out = $this->createCashbookEntry('out');

        $response = $this->get('/passbook?filter=cash_in');

        $response->assertOk()
            ->assertSee('CB-'.$in->id)
            ->assertDontSee('CB-'.$out->id);
    }

    public function test_date_range_filters_created_at(): void
    {
        $oldPayment = $this->createPayment(100);
        $oldPayment->forceFill(['created_at' => now()->subDays(10)])->save();

        $todayPayment = $this->createPayment(200);

        $response = $this->get('/passbook?date_from='.now()->toDateString().'&date_to='.now()->toDateString());

        $response->assertOk()
            ->assertSee('PAY-'.$todayPayment->id)
            ->assertDontSee('PAY-'.$oldPayment->id);
    }

    public function test_date_range_filters_cashbook_transaction_date(): void
    {
        $oldEntry = $this->createCashbookEntry('in', '100.00', 'AFN', [
            'transaction_date' => now()->subDays(10),
        ]);
        $todayEntry = $this->createCashbookEntry('in', '200.00');

        $response = $this->get('/passbook?date_from='.now()->toDateString().'&date_to='.now()->toDateString());

        $response->assertOk()
            ->assertSee('CB-'.$todayEntry->id)
            ->assertDontSee('CB-'.$oldEntry->id);
    }

    public function test_totals_are_cash_basis(): void
    {
        $this->createCashbookEntry('in', '400.00');
        $this->createPayment(500);
        $this->createCashbookEntry('out', '300.00');
        $this->createPurchasePayment(200);
        $this->createExpense(100);

        $response = $this->get('/passbook');

        $response->assertOk()
            ->assertViewHas('totalIn', 900)
            ->assertViewHas('totalOut', 600);
    }

    public function test_salary_payments_appear_as_cash_out(): void
    {
        $salary = $this->createSalaryEntry(150);

        $response = $this->get('/passbook');

        $response->assertOk()
            ->assertSee('SAL-'.$salary->id)
            ->assertViewHas('totalOut', 150);
    }

    public function test_cash_out_filter_includes_salary_payments(): void
    {
        $salary = $this->createSalaryEntry(150);
        $this->createCashbookEntry('in');

        $response = $this->get('/passbook?filter=cash_out');

        $response->assertOk()
            ->assertSee('SAL-'.$salary->id)
            ->assertViewHas('totalOut', 150);
    }

    public function test_filter_badges_preserve_date_params(): void
    {
        $this->createPayment();

        $response = $this->get('/passbook?date_from=2026-01-01&date_to=2026-01-31');

        $response->assertOk()
            ->assertSee('date_from=2026-01-01')
            ->assertSee('date_to=2026-01-31')
            ->assertSee('filter=cash_in');
    }
}
