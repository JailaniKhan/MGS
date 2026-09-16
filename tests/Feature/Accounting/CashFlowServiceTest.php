<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\CashbookEntry;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Accounting\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Canonical cash-flow union: every page that shows "cash in / cash out /
 * balance" must agree, regardless of which flow recorded the money
 * (payments, purchase payments, ledger payments, cashbook entries —
 * journal generation, legacy cashbook_entries generation — expenses,
 * salaries).
 */
class CashFlowServiceTest extends TestCase
{
    use RefreshDatabase;

    private function postCashbook(string $type, string $amount, string $currency = 'AFN', string $date = '2026-09-01'): void
    {
        app(TransactionService::class)->postCashbookEntry(
            User::first(),
            $type,
            $amount,
            $currency,
            ['transaction_date' => $date],
        );
    }

    public function test_wallet_hero_includes_journal_cashbook_entries(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Live cashbook form (journal generation) — the flow the wallet page forgot.
        $this->postCashbook('in', '250.00');
        $this->postCashbook('out', '80.00', 'AFN');
        $this->postCashbook('in', '10.00', 'USD');

        $response = $this->get(route('payments.index'));

        $response->assertOk()
            ->assertViewHas('incomingAFN', 250.0)
            ->assertViewHas('outgoingAFN', 80.0)
            ->assertViewHas('incomingUSD', 10.0)
            ->assertViewHas('balanceAFN', 170.0)
            ->assertViewHas('balanceUSD', 10.0);
    }

    public function test_wallet_hero_includes_legacy_cashbook_entries(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        CashbookEntry::create(['type' => 'in', 'amount' => '100.00', 'currency' => 'AFN', 'entry_date' => '2026-08-10']);

        $this->get(route('payments.index'))
            ->assertOk()
            ->assertViewHas('incomingAFN', 100.0)
            ->assertViewHas('balanceAFN', 100.0);
    }

    public function test_wallet_feed_lists_cashbook_entries_from_both_generations(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $journal = JournalEntry::create([
            'user_id' => $user->id,
            'description' => 'Journal Cash Income',
            'transaction_date' => '2026-09-01',
            'currency' => 'AFN',
            'source' => 'cashbook_in',
        ]);
        LedgerEntry::create([
            'journal_entry_id' => $journal->id,
            'account_id' => Account::create(['name' => 'Cash', 'type' => 'cash', 'currency' => 'AFN'])->id,
            'amount' => '70.00',
            'direction' => 'credit',
        ]);

        CashbookEntry::create([
            'type' => 'out',
            'amount' => '20.00',
            'currency' => 'AFN',
            'entry_date' => '2026-09-02',
            'notes' => 'Legacy expense note',
        ]);

        $response = $this->get(route('payments.index'));

        $response->assertOk()
            // The journal cashbook entry renders in the feed...
            ->assertSee('Journal Cash Income')
            ->assertSee('+70.00', false)
            // ...and so does the legacy row.
            ->assertSee(__('messages.cash_expense'))
            ->assertSee('Legacy expense note')
            ->assertSee('-20.00', false);
    }

    public function test_balance_sheet_cash_includes_both_cashbook_generations(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Journal generation (the live cashbook form).
        $this->postCashbook('in', '70.00');
        // Legacy generation.
        CashbookEntry::create(['type' => 'in', 'amount' => '30.00', 'currency' => 'AFN', 'entry_date' => '2026-08-10']);

        $this->get('/reports/balance-sheet?currency=AFN')
            ->assertOk()
            ->assertViewHas('cashBalance', '100.00');
    }

    public function test_profit_and_loss_cash_expenses_include_both_cashbook_generations(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postCashbook('out', '40.00');
        CashbookEntry::create(['type' => 'out', 'amount' => '15.00', 'currency' => 'AFN', 'entry_date' => '2026-08-10']);

        $this->get('/reports/profit-loss?currency=AFN')
            ->assertOk()
            ->assertViewHas('cashExpenses', '55.00');
    }

    public function test_dashboard_totals_include_legacy_cashbook_entries(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Cache::flush();

        CashbookEntry::create(['type' => 'in', 'amount' => '90.00', 'currency' => 'AFN', 'entry_date' => '2026-08-10']);
        CashbookEntry::create(['type' => 'out', 'amount' => '20.00', 'currency' => 'AFN', 'entry_date' => '2026-08-11']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('totalRevenueAFN', 90.0)
            ->assertViewHas('totalExpenseAFN', 20.0);
    }

    public function test_cashbook_index_tiles_match_wallet_balance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'Tile Customer', 'phone' => '0700000001']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'person_type' => 'customer',
            'person_id' => $customer->id,
            'status' => 'completed',
            'subtotal' => '0.00',
            'total_amount' => '500.00',
            'currency' => 'AFN',
        ]);
        Payment::create(['order_id' => $order->id, 'amount' => '200.00', 'currency' => 'AFN']);
        $this->postCashbook('out', '50.00');

        // The cashbook page's Cash AFN tile and the wallet hero must agree.
        $wallet = $this->get(route('payments.index'));
        $cashbook = $this->get(route('cashbook.index'));

        $wallet->assertOk();
        $cashbook->assertOk();
        $this->assertSame((string) $wallet->viewData('balanceAFN'), (string) (float) $cashbook->viewData('cashAFN'));
    }
}
