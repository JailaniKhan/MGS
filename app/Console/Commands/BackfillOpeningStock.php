<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Creates opening-balance purchases so every product has a purchase-cost
 * basis. Without at least one purchase line per product+currency the P&L
 * treats sales as pure margin ("No purchase cost recorded").
 *
 * Cost source per currency: --unit-cost override > product.price_usd (USD)
 * > product.price. These are PROXIES — review the generated rows and adjust
 * unit prices to the real buy-in values afterwards.
 */
class BackfillOpeningStock extends Command
{
    protected $signature = 'purchases:backfill-opening-stock
        {--user-id= : Owner of the generated purchases (defaults to the first user)}
        {--currency=* : Currencies to backfill (defaults to AFN and USD)}
        {--product-id=* : Only these product IDs}
        {--unit-cost= : Force this unit cost on every backfilled line}
        {--all-products : Include products never sold yet}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Create opening-balance purchases so products have a purchase-cost basis for profit reports';

    public function handle(): int
    {
        $user = $this->resolveUser();
        if (! $user) {
            $this->error('No user found. Create a user first or pass --user-id.');

            return self::FAILURE;
        }

        $currencies = $this->option('currency') ?: ['AFN', 'USD'];
        $forcedCost = $this->option('unit-cost');

        $productsQuery = Product::query();
        if ($productIds = array_map(intval(...), (array) $this->option('product-id'))) {
            $productsQuery->whereIn('id', $productIds);
        }
        if (! $this->option('all-products')) {
            $productsQuery->whereHas('orderItems');
        }
        $products = $productsQuery->orderBy('name')->get();

        if ($productIds && $products->count() !== count($productIds)) {
            $this->error('Some --product-id values do not match existing products.');

            return self::FAILURE;
        }

        if ($products->isEmpty()) {
            $this->info('Nothing to backfill.');

            return self::SUCCESS;
        }

        $supplier = Supplier::where('name', 'Opening Stock')->where('user_id', $user->id)->first();
        if (! $supplier) {
            // user_id is not fillable on Supplier, so assign it directly.
            $supplier = new Supplier(['name' => 'Opening Stock', 'phone' => '']);
            $supplier->user_id = $user->id;
            $supplier->save();
        }

        $plans = [];
        foreach ($currencies as $currency) {
            $existing = DB::table('purchase_items')
                ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
                ->where('purchases.user_id', $user->id)
                ->where('purchases.currency', $currency)
                ->whereIn('purchase_items.product_id', $products->pluck('id'))
                ->pluck('purchase_items.product_id');

            foreach ($products->whereNotIn('id', $existing) as $product) {
                if ($forcedCost !== null) {
                    $cost = $forcedCost;
                } else {
                    // Never fall back across currencies: using an AFN price as a
                    // USD cost produced absurd lines (a 15,000-AFN TV became
                    // "$15,000"). Each currency needs its own price.
                    $cost = $currency === 'USD' ? $product->price_usd : $product->price;
                }

                if ((float) $cost <= 0) {
                    $field = $currency === 'USD' ? 'USD price' : 'price';
                    $this->warn("Skipping {$product->name} [$currency]: no {$currency}-specific price set (fill the product's {$field} or pass --unit-cost).");

                    continue;
                }

                $plans[] = [
                    'product' => $product,
                    'currency' => $currency,
                    'qty' => max((int) $product->stock, 1),
                    'cost' => number_format((float) $cost, 2, '.', ''),
                ];
            }
        }

        if (empty($plans)) {
            $this->info('Every product already has a cost basis in the selected currencies.');

            return self::SUCCESS;
        }

        $this->table(
            ['Product', 'Currency', 'Qty', 'Unit Cost', 'Subtotal'],
            collect($plans)->map(fn ($plan) => [
                $plan['product']->name,
                $plan['currency'],
                $plan['qty'],
                $plan['cost'],
                bcmul($plan['cost'], (string) $plan['qty'], 2),
            ])->all()
        );

        if (! $this->option('force') && ! $this->confirm('Create these opening-stock purchases?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $created = DB::transaction(function () use ($plans, $user, $supplier) {
            $count = 0;
            foreach (collect($plans)->groupBy('currency') as $currency => $group) {
                $total = $group->reduce(
                    fn ($carry, $plan) => bcadd($carry, bcmul($plan['cost'], (string) $plan['qty'], 2), 2),
                    '0.00'
                );

                // user_id/uuid are not fillable on Purchase (the web flow relies
                // on the authenticated-user creating hook), so set directly here.
                $purchase = new Purchase([
                    'supplier_id' => $supplier->id,
                    'person_type' => 'supplier',
                    'person_id' => $supplier->id,
                    'status' => 'completed',
                    'currency' => $currency,
                    'subtotal' => $total,
                    'total_amount' => $total,
                ]);
                $purchase->user_id = $user->id;
                $purchase->save();

                foreach ($group as $plan) {
                    PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $plan['product']->id,
                        'quantity' => $plan['qty'],
                        'unit_price' => $plan['cost'],
                        'subtotal' => bcmul($plan['cost'], (string) $plan['qty'], 2),
                        // Inherit the product's current lot so the purchase page
                        // shows a real lot instead of a placeholder string.
                        'lot_number' => trim((string) $plan['product']->lot_number) !== ''
                            ? $plan['product']->lot_number
                            : null,
                    ]);
                    $count++;
                }
            }

            return $count;
        });

        $this->info("Created $created opening-stock line(s) under supplier '{$supplier->name}'.");
        $this->warn('Review the generated unit prices — they are proxies from product prices, not real buy-in costs.');

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        if ($id = $this->option('user-id')) {
            return User::find($id);
        }

        return User::orderBy('id')->first();
    }
}
