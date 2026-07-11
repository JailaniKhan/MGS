<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Str;

class ChartOfAccountsSeeder
{
    public function seedForUser(User $user): void
    {
        foreach (['AFN', 'USD'] as $currency) {
            $this->firstOrCreateSystemAccount($user, 'cash', "نغد {$currency}", $currency);
            $this->firstOrCreateSystemAccount($user, 'income', "عمومي عاید {$currency}", $currency);
            $this->firstOrCreateSystemAccount($user, 'expense', "عمومي لګښت {$currency}", $currency);
        }
    }

    public function cashAccount(User $user, string $currency = 'AFN'): Account
    {
        return $this->firstOrCreateSystemAccount($user, 'cash', "نغد {$currency}", $currency);
    }

    public function incomeAccount(User $user, string $currency = 'AFN'): Account
    {
        return $this->firstOrCreateSystemAccount($user, 'income', "عمومي عاید {$currency}", $currency);
    }

    public function expenseAccount(User $user, string $currency = 'AFN'): Account
    {
        return $this->firstOrCreateSystemAccount($user, 'expense', "عمومي لګښت {$currency}", $currency);
    }

    public function findPartyAccount(User $user, string $type, int $legacyId, string $currency = 'AFN'): ?Account
    {
        return Account::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('legacy_type', $type)
            ->where('legacy_id', $legacyId)
            ->where('currency', $currency)
            ->first();
    }

    public function createPartyAccount(User $user, string $type, string $name, ?string $phone, ?string $address, string $currency, ?int $legacyId = null): Account
    {
        return Account::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'type' => $type,
            'phone' => $phone,
            'address' => $address,
            'currency' => $currency,
            'legacy_id' => $legacyId,
            'legacy_type' => $legacyId ? $type : null,
        ]);
    }

    private function firstOrCreateSystemAccount(User $user, string $type, string $name, string $currency): Account
    {
        return Account::withoutGlobalScopes()->firstOrCreate(
            [
                'user_id' => $user->id,
                'type' => $type,
                'name' => $name,
                'currency' => $currency,
            ],
            [
                'uuid' => (string) Str::uuid(),
            ]
        );
    }
}
