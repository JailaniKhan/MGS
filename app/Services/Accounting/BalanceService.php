<?php

namespace App\Services\Accounting;

use App\Models\Account;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    /**
     * Party balance: credits minus debits (Udhaar minus Jama).
     * Positive = they owe you (customer) or you owe them (supplier).
     */
    public function balance(int $accountId): string
    {
        return $this->netBalance($accountId);
    }

    public function netBalance(int $accountId): string
    {
        $debits = $this->sumDirection($accountId, 'debit');
        $credits = $this->sumDirection($accountId, 'credit');

        return bcsub($credits, $debits, 2);
    }

    public function sumDirection(int $accountId, string $direction): string
    {
        $sum = DB::table('ledger_entries')
            ->where('account_id', $accountId)
            ->where('direction', $direction)
            ->sum('amount');

        return number_format((float) $sum, 2, '.', '');
    }

    public function cashBalance(int $userId, string $currency = 'AFN'): string
    {
        $account = Account::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('type', 'cash')
            ->where('currency', $currency)
            ->first();

        if (! $account) {
            return '0.00';
        }

        return $this->netBalance($account->id);
    }

    public function totalReceivable(int $userId, string $currency = 'AFN'): string
    {
        return $this->aggregatePartyBalance($userId, 'customer', $currency);
    }

    public function totalPayable(int $userId, string $currency = 'AFN'): string
    {
        return $this->aggregatePartyBalance($userId, 'supplier', $currency);
    }

    private function aggregatePartyBalance(int $userId, string $type, string $currency): string
    {
        $accounts = Account::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('currency', $currency)
            ->pluck('id');

        $total = '0.00';

        foreach ($accounts as $accountId) {
            $balance = $this->balance($accountId);
            if (bccomp($balance, '0', 2) === 1) {
                $total = bcadd($total, $balance, 2);
            }
        }

        return $total;
    }

    public function compare(string $a, string $b): int
    {
        return bccomp($a, $b, 2);
    }
}
