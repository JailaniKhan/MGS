<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\User;
use App\Services\Accounting\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_push_creates_accounts_and_transactions(): void
    {
        $user = User::factory()->create();
        app(ChartOfAccountsSeeder::class)->seedForUser($user);

        $accountUuid = '11111111-1111-4111-8111-111111111111';
        $income = Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', 'income')
            ->where('currency', 'AFN')
            ->first();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/sync/push', [
            'accounts' => [[
                'uuid' => $accountUuid,
                'name' => 'Synced Customer',
                'type' => 'customer',
                'currency' => 'AFN',
            ]],
            'transactions' => [[
                'idempotency_key' => '22222222-2222-4222-8222-222222222222',
                'transaction_date' => '2026-06-29',
                'description' => 'sync test',
                'currency' => 'AFN',
                'lines' => [
                    ['account_uuid' => $accountUuid, 'direction' => 'credit', 'amount' => '25.00'],
                    ['account_uuid' => $income->uuid, 'direction' => 'debit', 'amount' => '25.00'],
                ],
            ]],
        ]);

        $response->assertOk()->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('accounts', ['uuid' => $accountUuid]);
        $this->assertDatabaseHas('journal_entries', ['idempotency_key' => '22222222-2222-4222-8222-222222222222']);
    }
}
