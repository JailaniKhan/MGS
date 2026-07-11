<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TransactionService
{
    public function __construct(
        private ChartOfAccountsSeeder $chartOfAccounts,
    ) {}

    /**
     * @param  array<int, array{account_id: int, direction: string, amount: string|float, notes?: string|null}>  $lines
     * @param  array<string, mixed>  $meta
     */
    public function post(array $lines, array $meta): JournalEntry
    {
        $this->validateLines($lines);

        $userId = $meta['user_id'] ?? Auth::id();
        if (! $userId) {
            throw new InvalidArgumentException('user_id is required to post a transaction.');
        }

        $idempotencyKey = $meta['idempotency_key'] ?? (string) Str::uuid();

        return DB::transaction(function () use ($lines, $meta, $userId, $idempotencyKey) {
            $existing = JournalEntry::withoutGlobalScopes()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing->load('ledgerEntries.account');
            }

            $journal = JournalEntry::withoutGlobalScopes()->create([
                'user_id' => $userId,
                'uuid' => $meta['uuid'] ?? (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'description' => $meta['description'] ?? null,
                'transaction_date' => $meta['transaction_date'] ?? now()->toDateString(),
                'currency' => $meta['currency'] ?? 'AFN',
                'source' => $meta['source'] ?? 'manual',
                'reference_type' => $meta['reference_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
            ]);

            foreach ($lines as $line) {
                LedgerEntry::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $line['account_id'],
                    'amount' => number_format((float) $line['amount'], 2, '.', ''),
                    'direction' => $line['direction'],
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            AuditLog::create([
                'user_id' => $userId,
                'journal_entry_id' => $journal->id,
                'action' => $meta['audit_action'] ?? 'transaction.posted',
                'ip_address' => $meta['ip_address'] ?? request()?->ip(),
                'user_agent' => $meta['user_agent'] ?? request()?->userAgent(),
                'metadata' => $meta['metadata'] ?? null,
            ]);

            return $journal->load('ledgerEntries.account');
        });
    }

    public function postCustomerCreditSale(User $user, Account $customerAccount, string $amount, string $currency, array $meta = []): JournalEntry
    {
        $income = $this->chartOfAccounts->incomeAccount($user, $currency);

        return $this->post([
            ['account_id' => $customerAccount->id, 'direction' => 'credit', 'amount' => $amount, 'notes' => $meta['notes'] ?? null],
            ['account_id' => $income->id, 'direction' => 'debit', 'amount' => $amount],
        ], array_merge($meta, [
            'user_id' => $user->id,
            'currency' => $currency,
            'source' => 'sale',
            'audit_action' => 'sale.posted',
        ]));
    }

    public function postCustomerPayment(User $user, Account $customerAccount, string $amount, string $currency, array $meta = []): JournalEntry
    {
        $cash = $this->chartOfAccounts->cashAccount($user, $currency);

        return $this->post([
            ['account_id' => $customerAccount->id, 'direction' => 'debit', 'amount' => $amount, 'notes' => $meta['notes'] ?? null],
            ['account_id' => $cash->id, 'direction' => 'credit', 'amount' => $amount],
        ], array_merge($meta, [
            'user_id' => $user->id,
            'currency' => $currency,
            'source' => 'customer_payment',
            'audit_action' => 'customer_payment.posted',
        ]));
    }

    public function postCustomerGiveCredit(User $user, Account $customerAccount, string $amount, string $currency, array $meta = []): JournalEntry
    {
        $expense = $this->chartOfAccounts->expenseAccount($user, $currency);

        return $this->post([
            ['account_id' => $customerAccount->id, 'direction' => 'credit', 'amount' => $amount, 'notes' => $meta['notes'] ?? null],
            ['account_id' => $expense->id, 'direction' => 'debit', 'amount' => $amount],
        ], array_merge($meta, [
            'user_id' => $user->id,
            'currency' => $currency,
            'source' => 'customer_credit',
            'audit_action' => 'customer_credit.posted',
        ]));
    }

    public function postSupplierPurchase(User $user, Account $supplierAccount, string $amount, string $currency, array $meta = []): JournalEntry
    {
        $expense = $this->chartOfAccounts->expenseAccount($user, $currency);

        return $this->post([
            ['account_id' => $expense->id, 'direction' => 'debit', 'amount' => $amount],
            ['account_id' => $supplierAccount->id, 'direction' => 'credit', 'amount' => $amount, 'notes' => $meta['notes'] ?? null],
        ], array_merge($meta, [
            'user_id' => $user->id,
            'currency' => $currency,
            'source' => 'purchase',
            'audit_action' => 'purchase.posted',
        ]));
    }

    public function postSupplierPayment(User $user, Account $supplierAccount, string $amount, string $currency, array $meta = []): JournalEntry
    {
        $cash = $this->chartOfAccounts->cashAccount($user, $currency);

        // A payment REDUCES what we owe the supplier, so the supplier account must be DEBITED.
        // netBalance = credits - debits, positive = amount owed. (Fix C5)
        return $this->post([
            ['account_id' => $cash->id, 'direction' => 'credit', 'amount' => $amount],
            ['account_id' => $supplierAccount->id, 'direction' => 'debit', 'amount' => $amount, 'notes' => $meta['notes'] ?? null],
        ], array_merge($meta, [
            'user_id' => $user->id,
            'currency' => $currency,
            'source' => 'supplier_payment',
            'audit_action' => 'supplier_payment.posted',
        ]));
    }

    public function postCashbookEntry(User $user, string $type, string $amount, string $currency, array $meta = []): JournalEntry
    {
        $cash = $this->chartOfAccounts->cashAccount($user, $currency);

        if ($type === 'in') {
            $income = $this->chartOfAccounts->incomeAccount($user, $currency);

            return $this->post([
                ['account_id' => $income->id, 'direction' => 'debit', 'amount' => $amount],
                ['account_id' => $cash->id, 'direction' => 'credit', 'amount' => $amount, 'notes' => $meta['notes'] ?? null],
            ], array_merge($meta, [
                'user_id' => $user->id,
                'currency' => $currency,
                'source' => 'cashbook_in',
                'audit_action' => 'cashbook.in',
            ]));
        }

        $expense = $this->chartOfAccounts->expenseAccount($user, $currency);

        return $this->post([
            ['account_id' => $cash->id, 'direction' => 'debit', 'amount' => $amount, 'notes' => $meta['notes'] ?? null],
            ['account_id' => $expense->id, 'direction' => 'credit', 'amount' => $amount],
        ], array_merge($meta, [
            'user_id' => $user->id,
            'currency' => $currency,
            'source' => 'cashbook_out',
            'audit_action' => 'cashbook.out',
        ]));
    }

    public function postSalaryPayment(User $user, string $amount, string $currency, array $meta = []): JournalEntry
    {
        $expense = $this->chartOfAccounts->expenseAccount($user, $currency);
        $cash = $this->chartOfAccounts->cashAccount($user, $currency);

        return $this->post([
            ['account_id' => $cash->id, 'direction' => 'debit', 'amount' => $amount, 'notes' => $meta['notes'] ?? null],
            ['account_id' => $expense->id, 'direction' => 'credit', 'amount' => $amount],
        ], array_merge($meta, [
            'user_id' => $user->id,
            'currency' => $currency,
            'source' => 'salary',
            'audit_action' => 'salary.posted',
        ]));
    }

    public function postBankTransfer(User $user, Account $fromAccount, Account $toAccount, string $amount, array $meta = []): JournalEntry
    {
        if ($fromAccount->currency !== $toAccount->currency) {
            throw new InvalidArgumentException('Transfer accounts must share the same currency.');
        }

        return $this->post([
            ['account_id' => $toAccount->id, 'direction' => 'debit', 'amount' => $amount, 'notes' => $meta['notes'] ?? null],
            ['account_id' => $fromAccount->id, 'direction' => 'credit', 'amount' => $amount],
        ], array_merge($meta, [
            'user_id' => $user->id,
            'currency' => $fromAccount->currency,
            'source' => 'bank_transfer',
            'audit_action' => 'bank.transfer',
        ]));
    }

    /**
     * @param  array<int, array{account_uuid: string, direction: string, amount: string|float, notes?: string|null}>  $lines
     */
    public function postFromSync(User $user, array $lines, array $meta): JournalEntry
    {
        $resolvedLines = [];

        foreach ($lines as $line) {
            $account = Account::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('uuid', $line['account_uuid'])
                ->firstOrFail();

            $resolvedLines[] = [
                'account_id' => $account->id,
                'direction' => $line['direction'],
                'amount' => $line['amount'],
                'notes' => $line['notes'] ?? null,
            ];
        }

        return $this->post($resolvedLines, array_merge($meta, ['user_id' => $user->id]));
    }

    private function validateLines(array $lines): void
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal entry requires at least two lines.');
        }

        $totalDebits = '0.00';
        $totalCredits = '0.00';

        foreach ($lines as $line) {
            if (! in_array($line['direction'], ['debit', 'credit'], true)) {
                throw new InvalidArgumentException('Invalid ledger direction.');
            }

            $amount = number_format((float) $line['amount'], 2, '.', '');

            if (bccomp($amount, '0', 2) !== 1) {
                throw new InvalidArgumentException('Amount must be greater than zero.');
            }

            if ($line['direction'] === 'debit') {
                $totalDebits = bcadd($totalDebits, $amount, 2);
            } else {
                $totalCredits = bcadd($totalCredits, $amount, 2);
            }
        }

        if (bccomp($totalDebits, $totalCredits, 2) !== 0) {
            throw new InvalidArgumentException('Debits and credits must balance.');
        }
    }
}
