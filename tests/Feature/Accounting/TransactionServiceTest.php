<?php

namespace Tests\Feature\Accounting;

use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\ChartOfAccountsSeeder;
use App\Services\Accounting\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_balanced_journal_is_created(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $chart = app(ChartOfAccountsSeeder::class);
        $chart->seedForUser($user);

        $customer = $chart->createPartyAccount($user, 'customer', 'Test Customer', null, null, 'AFN', 1);
        $service = app(TransactionService::class);

        $journal = $service->postCustomerCreditSale($user, $customer, '100.00', 'AFN');

        $this->assertDatabaseHas('journal_entries', ['id' => $journal->id]);
        $this->assertCount(2, $journal->ledgerEntries);
    }

    public function test_unbalanced_journal_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $chart = app(ChartOfAccountsSeeder::class);
        $chart->seedForUser($user);

        $cash = $chart->cashAccount($user, 'AFN');
        $service = app(TransactionService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->post([
            ['account_id' => $cash->id, 'direction' => 'debit', 'amount' => '100.00'],
            ['account_id' => $cash->id, 'direction' => 'credit', 'amount' => '50.00'],
        ], ['user_id' => $user->id, 'currency' => 'AFN']);
    }

    public function test_idempotency_prevents_duplicate_posting(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $chart = app(ChartOfAccountsSeeder::class);
        $chart->seedForUser($user);
        $customer = $chart->createPartyAccount($user, 'customer', 'Customer', null, null, 'AFN', 2);
        $service = app(TransactionService::class);

        $meta = ['idempotency_key' => '00000000-0000-4000-8000-000000000001'];

        $first = $service->postCustomerCreditSale($user, $customer, '50.00', 'AFN', $meta);
        $second = $service->postCustomerCreditSale($user, $customer, '50.00', 'AFN', $meta);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, JournalEntry::count());
    }
}
