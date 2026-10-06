<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Http\Resources\JournalEntryResource;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\Accounting\TransactionService;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function push(Request $request, TransactionService $transactionService)
    {
        $validated = $request->validate([
            'accounts' => 'nullable|array',
            'accounts.*.uuid' => 'required|uuid',
            'accounts.*.name' => 'required|string|max:255',
            'accounts.*.type' => 'required|in:customer,supplier,cash,income,expense',
            'accounts.*.phone' => 'nullable|string|max:50',
            'accounts.*.address' => 'nullable|string|max:500',
            'accounts.*.currency' => 'required|in:AFN,USD',
            'transactions' => 'nullable|array',
            'transactions.*.idempotency_key' => 'required|uuid',
            'transactions.*.transaction_date' => 'required|date',
            'transactions.*.description' => 'nullable|string|max:500',
            'transactions.*.currency' => 'required|in:AFN,USD',
            'transactions.*.lines' => 'required|array|min:2',
            'transactions.*.lines.*.account_uuid' => 'required|uuid',
            'transactions.*.lines.*.direction' => 'required|in:debit,credit',
            'transactions.*.lines.*.amount' => 'required|numeric|min:0.01',
        ]);

        $syncedAccounts = [];
        $syncedTransactions = [];

        foreach ($validated['accounts'] ?? [] as $accountData) {
            $account = Account::withoutGlobalScopes()->updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'uuid' => $accountData['uuid'],
                ],
                [
                    'name' => $accountData['name'],
                    'type' => $accountData['type'],
                    'phone' => $accountData['phone'] ?? null,
                    'address' => $accountData['address'] ?? null,
                    'currency' => $accountData['currency'],
                ]
            );
            $syncedAccounts[] = $account->uuid;
        }

        foreach ($validated['transactions'] ?? [] as $transactionData) {
            $journal = $transactionService->postFromSync($request->user(), $transactionData['lines'], [
                'idempotency_key' => $transactionData['idempotency_key'],
                'description' => $transactionData['description'] ?? null,
                'transaction_date' => $transactionData['transaction_date'],
                'currency' => $transactionData['currency'],
                'source' => 'sync',
            ]);
            $syncedTransactions[] = $journal->uuid;
        }

        return response()->json([
            'status' => 'success',
            'message' => __('messages.data_synced'),
            'synced' => [
                'accounts' => $syncedAccounts,
                'transactions' => $syncedTransactions,
            ],
            'conflicts' => [],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function pull(Request $request)
    {
        $since = $request->query('since');

        $accountsQuery = Account::query();
        $journalsQuery = JournalEntry::query()->with('ledgerEntries.account');

        if ($since) {
            $accountsQuery->where('updated_at', '>', $since);
            $journalsQuery->where('updated_at', '>', $since);
        }

        return response()->json([
            'accounts' => AccountResource::collection($accountsQuery->get()),
            'transactions' => JournalEntryResource::collection($journalsQuery->get()),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
