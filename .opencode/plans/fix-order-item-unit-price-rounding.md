# Fix: Order show renders unit price 20.70$ as "21$"

## Root cause (verified)

Order 11 data is correct: `quantity=20, unit_price=20.70 USD, subtotal=414.00`.
The display rounds it: `<x-money>` defaults to `decimals => 0`
(resources/views/components/money.blade.php:4), so `number_format(20.70, 0)` → "21$".

Same 0-decimal rounding on 6 surfaces:
1. resources/views/orders/show.blade.php:120 — reported page
2. resources/views/purchases/show.blade.php:110
3. resources/views/orders/returns/show.blade.php:45 + purchases/returns/show.blade.php:45
4. resources/views/orders/print.blade.php:79-80 + purchases/print.blade.php:80 (`number_format($item->unit_price)`)
5. app/Services/Billing/BillService.php:49-50, 100-101 — WhatsApp bill text (`x 21 = 414 $`)
6. totals in print + BillService lines 58-62/109-113

## User decisions

- Scope: **all 6 surfaces**
- Format: **smart** — fractional shows exact (`20.70$`), whole stays clean (`414$`)

## Blast radius (verified safe)

- Reports/inventory/dashboard/expenses/cashbook views use raw `number_format(..., 2)`
  or explicit `decimals="2"` — untouched by this change.
- Every `assertSee('.00')` in the suite (Cashbook, Daybook, P/L, StockReport,
  Aging, BalanceSheet, LandedCost, SpendBreakdown, UnitProfit, Inventory,
  Backfill) binds to those surfaces — checked one by one.
- Default-decimals `x-money` surfaces (orders/purchases/returns show, transactions
  index, dashboard recent, staff) have NO fractional-amount assertions; whole
  amounts render identically before/after (`15,000`, `8,000` stay).

## Changes

### 1. `resources/views/components/money.blade.php`
Change default `'decimals' => 0` → `'decimals' => null` (null = auto):
```blade
@php($dec = $decimals === null ? ((int) round(abs($num) * 100) % 100 === 0 ? 0 : 2) : (int) $decimals)
```
then `number_format(..., (int) $dec)`. Explicit `decimals="2"` call sites
(spend-breakdown) unchanged.

### 2. `app/Support/helpers.php`
Add guarded `money_format($amount): string` — same smart rule — for non-Blade
surfaces (follows existing `local_date` pattern).

### 3. Print views (orders/print.blade.php:79-80,90 + purchases/print.blade.php:80)
Replace 0-decimal `number_format` with `money_format` on unit_price, subtotal,
total.

### 4. `app/Services/Billing/BillService.php`
Lines 49-50, 58-62, 100-101, 109-113: `number_format(...)` → `money_format(...)`.
Leave the already-explicit-2-decimal statement lines (151-162) alone.

### 5. Auto-fixed for free (no call-site edits)
orders/show:120, purchases/show:110, both returns show views, transactions
index, dashboard recent, staff — component default change covers them.

## Regression tests (write FIRST, watch red, then fix)

New `tests/Feature/OrderShowTest.php` (mirrors OrdersIndexTest helpers):
- `test_show_renders_fractional_unit_price_exactly` — USD order, item
  qty 20 / unit_price `20.70` / subtotal `414.00`;
  `GET route('orders.show')` → `assertSee('20.70$', false)` + `assertSee('414$', false)`
- `test_print_renders_fractional_unit_price_exactly` —
  `GET route('orders.print')` → `assertSee('20.70')`
- `test_whatsapp_bill_text_renders_fractional_unit_price` —
  `app(BillService::class)->orderBill($order)['message']` contains `20.70`
  (exercises the WhatsApp text seam without sending)
- purchase-side mirror test (same shape, `purchases.show`)

## Execution order (diagnosing-bugs skill)

1. `git status` — confirm clean tree
2. Write OrderShowTest → run → RED (`21$` rendered, `20.70$` absent)
3. Apply changes 1-4
4. Re-run OrderShowTest → GREEN
5. Full suite `php artisan test` — zero regressions expected
6. Cleanup check: no debug instrumentation to remove
7. Manual confirm on `http://127.0.0.1:8000/orders/11/show` (auth-protected;
   user verifies visually): line reads `20 کارتن × 20.70$`, subtotal `414$`

## Correct hypothesis for commit message (when asked)
`x-money` default `decimals=0` rounded 20.70 → 21; smart default (2 decimals
iff cents ≠ 0) restores exact scale-2 rendering per domain money contract.

## Not committed unless requested. No post-verify doc changes planned.
