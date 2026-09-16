# Fix: P&L shows 100% margin for products added with opening stock (no purchases)

## Root cause (verified against live data)

- Product 25 (غوړي "10 لیټره"): created today via the inventory form with
  opening stock 120 @ 1050 AFN — `products` row only, **zero `purchase_items`**
- Order 12: sold 50 @ 1100 AFN → revenue 55,000
- `LandedCostService::weightedAverageCost` (app/Services/Reporting/LandedCostService.php:47)
  builds the cost basis **only from `purchase_items`** → no lines = no basis
- `UnitProfitService` then treats the sale as "missing cost data": COGS = 0,
  so Gross profit = Net profit = 55,000 (should be 55,000 − 50×1050 = **2,500**)

**Why still wrong after the earlier fixes:** those were display-scope fixes
(all-time window, tiles, decimals). The cost-basis gap is independent: stock
that enters through the *product form* (or any non-purchase path) never gets a
purchase line, and the repo's own `purchases:backfill-opening-stock` command
exists precisely because of this — it creates "Opening Stock" purchases at the
**product's own price as a proxy cost** (with a warning that it's a proxy).

Same flaw for USD (a USD-priced product with opening stock also shows 100%
margin). No currency mixing involved — the basis is per-currency already.

## User decisions (confirmed)
- Cost basis for unpurchased products: **product's own pool price** (price for
  AFN, price_usd for USD) — same convention as the backfill command
- Scope: **all three reports** that share the basis (P&L, stock report,
  balance sheet) — keeps the documented single-basis design and the sheet
  balanced (assets and retained earnings both move by the same Δ)

## Change (single file + tests)

### `app/Services/Reporting/LandedCostService.php`
In `weightedAverageCost()`, after the existing purchase-rows loop, add a
**per-currency fallback pass**:

```php
// Products with NO purchase lines in a bucket fall back to their own pool
// price (opening stock entered via the product form has no purchase history).
// Purchases always win — never blend. Mirrors what the backfill command
// would create, landed multiplier included, so both paths agree.
$proxies = DB::table('products')
    ->where('user_id', $userId)
    ->get(['id', 'price', 'price_usd']);

foreach ($proxies as $product) {
    foreach ($currency !== null ? [$currency] : ['AFN', 'USD'] as $bucket) {
        if (isset($cost[$product->id][$bucket])) {
            continue; // real purchase basis exists — keep it
        }
        $proxy = $bucket === 'USD' ? $product->price_usd : $product->price;
        if ($proxy === null || (float) $proxy <= 0) {
            continue; // no pool price → stays "no cost data"
        }
        $linked = (float) ($poolMultiplier[$bucket] ?? 0);
        $value  = (float) ($poolValue[$bucket] ?? 0);
        $multiplier = $value > 0 ? 1 + $linked / $value : 1;
        $cost[$product->id][$bucket] = number_format((float) $proxy * $multiplier, 2, '.', '');
    }
}
```

Rules encoded:
1. **Purchases win** — a product with any purchase line in a currency keeps
   the weighted-average purchase basis; the fallback only fills empty buckets
2. **Per-currency, never converted** — AFN pool price only for AFN lookups,
   `price_usd` only for USD (matches the "never fall back across currencies"
   rule the backfill command documents)
3. **Zero price = no basis** — the flag ("no cost data") behavior stays for
   genuinely unpriced products
4. **Landed multiplier applied** — identical to what a backfilled purchase line
   would absorb, so running the command vs not running it yields the same basis
5. Adds ONE user-scoped query — `ReportQueryCountTest`'s O(1)-in-products
   bounds still hold (both runs include it; ≤32/<30 caps have headroom)

One extra query on products; the docblock gains a note that the basis now
includes pool-price proxies for unpurchased stock.

### Blast radius (verified safe — every fixture checked)
- All no-cost flag tests (ProfitLossTest "Ghost Item"/"No Cost Return",
  UnitProfitTest "Cross Currency"/"Never Purchased") use products with
  `price = 0.00` and no `price_usd` → guard skips → still flagged
- Weighted/landed/cancelled-purchase tests all seed real purchases → win
- ReportQueryCountTest: products fixture has purchases → same numbers, +1 flat query
- BackfillOpeningStockTest `test_backfilled_cost_feeds_profit_loss_page`:
  backfill runs first, purchase exists, assertions (195/−5) unchanged
- InventoryTest POST /products tests: no P&L assertions
- Live data: only product 25 (AFN) gains a basis for user 7; 23/24 have
  purchases; user 2's 13/14 unaffected by user scoping

## Regression tests (write first, watch RED, then fix)

`ProfitLossTest` (has `orderAt`/`orderItem`/`makeOrder` helpers):
1. `test_opening_stock_product_is_costed_at_its_afn_pool_price` — product
   `price 1050.00, stock_afn 120` (no purchases), order 50 @ 1100 AFN →
   `GET /reports/profit-loss` asserts revenue `>55,000.00`, COGS row
   `>-52,500.00`, gross profit hero `>2,500.00`, net badge `+2,500.00`,
   and `assertDontSee(__('messages.no_cost_data'))` (the user's exact scenario)
2. `test_opening_stock_product_is_costed_at_its_usd_pool_price` — product
   `price_usd 20.50, stock_usd 120` (price stays 0), order 50 @ 21 USD →
   COGS `>-1,025.00`... keep it small: 2 @ 21 USD → revenue `>42.00`,
   COGS `>-41.00`, GP `>1.00`, net `+1.00` (proves both currencies)
3. `test_real_purchases_beat_the_product_price_proxy` — product `price 10.00`
   BUT purchased at 5.00 (10 units), sold 1 @ 12.00 → COGS row `>-5.00`
   (not −10.00), GP `>7.00` (pins "purchases win, no blending")

`StockReportTest`:
4. `test_opening_stock_product_values_inventory_at_its_pool_price` —
   product `price 100.00, stock_afn 5`, no purchases → `/reports/stock`
   asserts avg purchase `100.00` and stock value `>500.00`

`BalanceSheetTest`:
5. `test_opening_stock_product_counts_in_inventory_value_and_keeps_balance` —
   same product shape → `/reports/balance-sheet` asserts inventory `>500.00`
   and `assertDontSee('≠')` (the balance indicator)

`UnitProfitTest` (spend-breakdown surface):
6. `test_opening_stock_product_is_costed_at_its_pool_price` — product
   `price_usd 5.00` sold in USD at 9.00 → per-sale line `+4.00$` (covers
   UnitProfitService reading the same service output)

## Verification
1. New tests RED (COGS renders 0.00 / flag shows today)
2. Apply LandedCostService change
3. New tests GREEN; full `php artisan test` — zero regressions
4. Manual: `/reports/profit-loss` (AFN) — غوړي line now shows COGS −52,500,
   Gross/Net profit drop from 55,000 to 2,500; USD view unchanged where real
   purchases exist

## Follow-up noted (not in scope)
- The backfill command stays useful for multi-currency backfills and for
  overriding bad proxies with real buy-in costs; its warning text stays true
- CONTEXT.md / ADR note could record "pool price is the documented proxy basis
  for unpurchased stock" — optional documentation pass after the fix lands
