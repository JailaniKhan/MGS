<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Models\LedgerEntry;
use App\Services\Accounting\BalanceService;
use App\Services\Accounting\ChartOfAccountsSeeder;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $query = Account::query()->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return AccountResource::collection($query->paginate(50));
    }

    public function store(Request $request, ChartOfAccountsSeeder $chartOfAccounts)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:customer,supplier,bank,cash,income,expense',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'currency' => 'required|in:AFN,USD',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:100',
        ]);

        $account = $chartOfAccounts->createPartyAccount(
            $request->user(),
            $validated['type'],
            $validated['name'],
            $validated['phone'] ?? null,
            $validated['address'] ?? null,
            $validated['currency'],
        );

        if ($validated['type'] === 'bank' && ! empty($validated['bank_name'])) {
            $account->bankDetails()->create([
                'bank_name' => $validated['bank_name'],
                'account_number' => $validated['account_number'] ?? null,
            ]);
        }

        return new AccountResource($account->load('bankDetails'));
    }

    public function show(Account $account)
    {
        return new AccountResource($account->load('bankDetails'));
    }

    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
        ]);

        $account->update($validated);

        return new AccountResource($account);
    }

    public function destroy(Account $account)
    {
        if (in_array($account->type, ['cash', 'income', 'expense'], true)) {
            return response()->json(['message' => 'System accounts cannot be deleted.'], 422);
        }

        $account->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function balance(Account $account, BalanceService $balanceService)
    {
        $entries = LedgerEntry::query()
            ->where('account_id', $account->id)
            ->with(['journalEntry', 'account'])
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'account' => new AccountResource($account),
            'balance' => $balanceService->balance($account->id),
            'recent_entries' => $entries,
        ]);
    }
}
