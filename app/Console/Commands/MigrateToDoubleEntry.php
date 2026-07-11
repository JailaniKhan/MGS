<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PartyPayment;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\BalanceService;
use App\Services\Accounting\ChartOfAccountsSeeder;
use App\Services\Accounting\TransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateToDoubleEntry extends Command
{
    protected $signature = 'accounting:migrate-to-double-entry {--user-id= : Migrate data for a specific user}';

    protected $description = 'Migrate legacy customers, suppliers, orders, and payments into double-entry journals';

    public function handle(
        ChartOfAccountsSeeder $chartOfAccounts,
        TransactionService $transactionService,
        BalanceService $balanceService,
    ): int {
        if (Setting::get('double_entry_migrated') === '1') {
            $this->warn('Migration already completed. Skipping.');

            return self::SUCCESS;
        }

        $user = $this->resolveUser();
        if (! $user) {
            $this->error('No user found. Create a user first or pass --user-id.');

            return self::FAILURE;
        }

        $this->info("Migrating data for user #{$user->id} ({$user->email})");

        DB::transaction(function () use ($user, $chartOfAccounts, $transactionService) {
            $chartOfAccounts->seedForUser($user);
            $this->assignUserIds($user);
            $this->migrateCustomers($user, $chartOfAccounts);
            $this->migrateSuppliers($user, $chartOfAccounts);
            $this->migrateOrders($user, $chartOfAccounts, $transactionService);
            $this->migratePurchases($user, $chartOfAccounts, $transactionService);
            $this->migrateOrderPayments($user, $chartOfAccounts, $transactionService);
            $this->migratePurchasePayments($user, $chartOfAccounts, $transactionService);
            $this->migratePartyPayments($user, $chartOfAccounts, $transactionService);
        });

        $this->verifyBalances($user, $balanceService);
        Setting::set('double_entry_migrated', '1');
        $this->info('Double-entry migration completed successfully.');

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        if ($this->option('user-id')) {
            return User::find($this->option('user-id'));
        }

        return User::first() ?? User::create([
            'name' => 'Default Shop',
            'email' => 'shop@mgs.local',
            'password' => 'password',
        ]);
    }

    private function assignUserIds(User $user): void
    {
        $tables = ['customers', 'suppliers', 'categories', 'units', 'products', 'orders', 'purchases', 'employees', 'cashbook_entries'];

        foreach ($tables as $table) {
            DB::table($table)->whereNull('user_id')->update(['user_id' => $user->id]);

            $rows = DB::table($table)->whereNull('uuid')->get();
            foreach ($rows as $row) {
                DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
            }
        }
    }

    private function migrateCustomers(User $user, ChartOfAccountsSeeder $chartOfAccounts): void
    {
        Customer::withoutGlobalScopes()->where('user_id', $user->id)->each(function (Customer $customer) use ($user, $chartOfAccounts) {
            foreach (['AFN', 'USD'] as $currency) {
                if ($chartOfAccounts->findPartyAccount($user, 'customer', $customer->id, $currency)) {
                    continue;
                }

                $chartOfAccounts->createPartyAccount(
                    $user,
                    'customer',
                    $customer->name,
                    $customer->phone,
                    $customer->address,
                    $currency,
                    $customer->id,
                );
            }
        });

        $this->info('Customers migrated to accounts.');
    }

    private function migrateSuppliers(User $user, ChartOfAccountsSeeder $chartOfAccounts): void
    {
        Supplier::withoutGlobalScopes()->where('user_id', $user->id)->each(function (Supplier $supplier) use ($user, $chartOfAccounts) {
            foreach (['AFN', 'USD'] as $currency) {
                if ($chartOfAccounts->findPartyAccount($user, 'supplier', $supplier->id, $currency)) {
                    continue;
                }

                $chartOfAccounts->createPartyAccount(
                    $user,
                    'supplier',
                    $supplier->name,
                    $supplier->phone,
                    $supplier->address,
                    $currency,
                    $supplier->id,
                );
            }
        });

        $this->info('Suppliers migrated to accounts.');
    }

    private function migrateOrders(User $user, ChartOfAccountsSeeder $chartOfAccounts, TransactionService $transactionService): void
    {
        Order::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereNull('journal_entry_id')
            ->where('status', '!=', 'cancelled')
            ->each(function (Order $order) use ($user, $chartOfAccounts, $transactionService) {
                $account = $chartOfAccounts->findPartyAccount($user, 'customer', $order->customer_id, $order->currency ?? 'AFN');
                if (! $account) {
                    return;
                }

                $journal = $transactionService->postCustomerCreditSale(
                    $user,
                    $account,
                    number_format((float) $order->total_amount, 2, '.', ''),
                    $order->currency ?? 'AFN',
                    [
                        'idempotency_key' => 'order-'.$order->id,
                        'transaction_date' => $order->created_at->toDateString(),
                        'description' => 'امر #'.$order->id,
                        'reference_type' => Order::class,
                        'reference_id' => $order->id,
                    ]
                );

                $order->update(['journal_entry_id' => $journal->id]);
            });

        $this->info('Orders migrated to journal entries.');
    }

    private function migratePurchases(User $user, ChartOfAccountsSeeder $chartOfAccounts, TransactionService $transactionService): void
    {
        Purchase::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereNull('journal_entry_id')
            ->where('status', '!=', 'cancelled')
            ->each(function (Purchase $purchase) use ($user, $chartOfAccounts, $transactionService) {
                $account = $chartOfAccounts->findPartyAccount($user, $purchase->person_type, $purchase->person_id, $purchase->currency ?? 'AFN');
                if (! $account) {
                    return;
                }

                $journal = $transactionService->postSupplierPurchase(
                    $user,
                    $account,
                    number_format((float) $purchase->total_amount, 2, '.', ''),
                    $purchase->currency ?? 'AFN',
                    [
                        'idempotency_key' => 'purchase-'.$purchase->id,
                        'transaction_date' => $purchase->created_at->toDateString(),
                        'description' => 'خرید #'.$purchase->id,
                        'reference_type' => Purchase::class,
                        'reference_id' => $purchase->id,
                    ]
                );

                $purchase->update(['journal_entry_id' => $journal->id]);
            });

        $this->info('Purchases migrated to journal entries.');
    }

    private function migrateOrderPayments(User $user, ChartOfAccountsSeeder $chartOfAccounts, TransactionService $transactionService): void
    {
        Payment::with('order')->each(function (Payment $payment) use ($user, $chartOfAccounts, $transactionService) {
            if (! $payment->order) {
                return;
            }

            $account = $chartOfAccounts->findPartyAccount(
                $user,
                'customer',
                $payment->order->customer_id,
                $payment->currency ?? 'AFN'
            );

            if (! $account) {
                return;
            }

            $transactionService->postCustomerPayment(
                $user,
                $account,
                number_format((float) $payment->amount, 2, '.', ''),
                $payment->currency ?? 'AFN',
                [
                    'idempotency_key' => 'payment-'.$payment->id,
                    'transaction_date' => $payment->created_at->toDateString(),
                    'description' => 'امر تادیه #'.$payment->order_id,
                    'notes' => $payment->notes,
                    'reference_type' => Payment::class,
                    'reference_id' => $payment->id,
                ]
            );
        });

        $this->info('Order payments migrated.');
    }

    private function migratePurchasePayments(User $user, ChartOfAccountsSeeder $chartOfAccounts, TransactionService $transactionService): void
    {
        PurchasePayment::with('purchase')->each(function (PurchasePayment $payment) use ($user, $chartOfAccounts, $transactionService) {
            if (! $payment->purchase) {
                return;
            }

            $account = $chartOfAccounts->findPartyAccount(
                $user,
                $payment->purchase->person_type,
                $payment->purchase->person_id,
                $payment->currency ?? 'AFN'
            );

            if (! $account) {
                return;
            }

            $transactionService->postSupplierPayment(
                $user,
                $account,
                number_format((float) $payment->amount, 2, '.', ''),
                $payment->currency ?? 'AFN',
                [
                    'idempotency_key' => 'purchase-payment-'.$payment->id,
                    'transaction_date' => $payment->created_at->toDateString(),
                    'description' => 'خرید تادیه #'.$payment->purchase_id,
                    'notes' => $payment->notes,
                    'reference_type' => PurchasePayment::class,
                    'reference_id' => $payment->id,
                ]
            );
        });

        $this->info('Purchase payments migrated.');
    }

    private function migratePartyPayments(User $user, ChartOfAccountsSeeder $chartOfAccounts, TransactionService $transactionService): void
    {
        PartyPayment::each(function (PartyPayment $payment) use ($user, $chartOfAccounts, $transactionService) {
            $type = $payment->person_type === 'customer' ? 'customer' : 'supplier';
            $account = $chartOfAccounts->findPartyAccount($user, $type, $payment->person_id, $payment->currency ?? 'AFN');

            if (! $account) {
                return;
            }

            if ($payment->type === 'payment_received') {
                $transactionService->postCustomerPayment(
                    $user,
                    $account,
                    number_format((float) $payment->amount, 2, '.', ''),
                    $payment->currency ?? 'AFN',
                    [
                        'idempotency_key' => 'party-payment-'.$payment->id,
                        'transaction_date' => $payment->created_at->toDateString(),
                        'description' => 'روزنامچه تادیه',
                        'notes' => $payment->notes,
                        'reference_type' => PartyPayment::class,
                        'reference_id' => $payment->id,
                    ]
                );
            } else {
                $transactionService->postSupplierPayment(
                    $user,
                    $account,
                    number_format((float) $payment->amount, 2, '.', ''),
                    $payment->currency ?? 'AFN',
                    [
                        'idempotency_key' => 'party-payment-'.$payment->id,
                        'transaction_date' => $payment->created_at->toDateString(),
                        'description' => 'روزنامچه تادیه',
                        'notes' => $payment->notes,
                        'reference_type' => PartyPayment::class,
                        'reference_id' => $payment->id,
                    ]
                );
            }
        });

        $this->info('Party payments migrated.');
    }

    private function verifyBalances(User $user, BalanceService $balanceService): void
    {
        foreach (['AFN', 'USD'] as $currency) {
            $incoming = Payment::where('currency', $currency)->sum('amount')
                + PartyPayment::where('currency', $currency)->where('type', 'payment_received')->sum('amount');
            $outgoing = PurchasePayment::where('currency', $currency)->sum('amount')
                + PartyPayment::where('currency', $currency)->where('type', 'payment_made')->sum('amount');
            $legacyWallet = $incoming - $outgoing;
            $newCash = $balanceService->cashBalance($user->id, $currency);

            $this->line("Wallet {$currency}: legacy={$legacyWallet}, double-entry cash={$newCash}");
        }
    }
}
