<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\Accounting\BalanceService;
use App\Services\Accounting\ChartOfAccountsSeeder;
use App\Services\Accounting\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BankController extends Controller
{
    public function index(BalanceService $balanceService)
    {
        $banks = Account::where('type', 'bank')->orderBy('name')->get();
        $cashAfn = $balanceService->cashBalance(Auth::id(), 'AFN');
        $cashUsd = $balanceService->cashBalance(Auth::id(), 'USD');

        return view('banks.index', compact('banks', 'cashAfn', 'cashUsd'));
    }

    public function create()
    {
        return view('banks.create');
    }

    public function store(Request $request, ChartOfAccountsSeeder $chartOfAccounts)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'currency' => 'required|in:AFN,USD',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:100',
        ]);

        $account = $chartOfAccounts->createPartyAccount(
            Auth::user(),
            'bank',
            $validated['name'],
            null,
            null,
            $validated['currency'],
        );

        $account->bankDetails()->create([
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'] ?? null,
        ]);

        return redirect()->route('banks.index')->with('success', __('messages.bank_account_added'));
    }

    public function transfer(Request $request, TransactionService $transactionService, ChartOfAccountsSeeder $chartOfAccounts)
    {
        $validated = $request->validate([
            'from_type' => 'required|in:cash,bank',
            'to_type' => 'required|in:cash,bank',
            'from_account_id' => 'nullable|exists:accounts,id',
            'to_account_id' => 'nullable|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:AFN,USD',
            'notes' => 'nullable|string|max:500',
        ]);

        $from = $validated['from_type'] === 'cash'
            ? $chartOfAccounts->cashAccount(Auth::user(), $validated['currency'])
            : Account::findOrFail($validated['from_account_id']);

        $to = $validated['to_type'] === 'cash'
            ? $chartOfAccounts->cashAccount(Auth::user(), $validated['currency'])
            : Account::findOrFail($validated['to_account_id']);

        $transactionService->postBankTransfer(
            Auth::user(),
            $from,
            $to,
            number_format((float) $validated['amount'], 2, '.', ''),
            [
                'description' => __('messages.bank_transfer'),
                'transaction_date' => now()->toDateString(),
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return redirect()->route('banks.index')->with('success', __('messages.transfer_recorded'));
    }
}
