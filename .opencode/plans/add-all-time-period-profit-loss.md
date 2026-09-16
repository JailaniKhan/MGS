# Add "All Time" period to the Profit & Loss report

## Goal
On `http://127.0.0.1:8000/reports/profit-loss`, add an **All Time** pill after
"This Year" so the report aggregates from the user's earliest record to the
anchor date (end of day).

## User decisions (confirmed)
- Start boundary: **earliest record** (MIN across aggregated sources), not a fixed epoch
- UI: **pill appended to the existing filter row** (pill row is horizontally scrollable)

## Current behavior
- `ReportController::profitLoss` (app/Http/Controllers/ReportController.php:35-120)
  matches `period` ∈ {month, 30, quarter, year} → start datetime; unknown values
  fall to `default` (start of month). End = anchor end-of-day.
- View `resources/views/reports/profit_loss.blade.php:5-10` maps the same 4 keys
  to labels; the pill row (line 38-43) and header subtitle (line 29) are driven by
  `$periodLabels`, so a new key flows through automatically.
- Translation key `messages.all_time` already exists in all 3 languages
  (en/fa line 781, ps) — no lang edits needed.

## Changes

### 1. `app/Http/Controllers/ReportController.php`
- Add match arm (after `'year'`):
  ```php
  'all' => $this->earliestRecordStart($anchor),
  ```
- New private method `earliestRecordStart(Carbon $anchor): Carbon` —
  MIN over exactly the sources this report aggregates, all user-scoped the
  same way the aggregate queries are (BelongsToUser global scope applies to
  Order/OrderReturn/CashbookEntry/Expense; SalaryPayment scopes via
  `whereHas('employee', user_id)`):
  - `orders.created_at`
  - `order_returns.return_date`
  - `cashbook_entries.created_at`
  - `expenses.expense_date`
  - `salary_payments.created_at` (employee-scoped)
  
  Take the earliest non-null, `Carbon::parse(...)->startOfDay()`; if all are
  null (fresh install) fall back to `$anchor->copy()->startOfDay()` (empty
  report, same as other periods on day one).
  
  No currency/status filters on the MIN probes — a foreign-currency or
  cancelled record starting the window earlier changes no numbers; the
  window is only a container.

### 2. `resources/views/reports/profit_loss.blade.php`
- Add to `$periodLabels` (line 5-10): `'all' => __('messages.all_time'),`
  Pill row, active-state highlight, and header subtitle all key off this map
  and need no other edits. Date ("end date") input stays — the anchor still
  bounds the window's end, consistent with the other periods.

## Regression tests (ProfitLossTest, mirrors `test_year_period_spans_the_whole_year_of_the_anchor`)
1. `test_all_time_period_includes_orders_older_than_the_current_year` —
   seed orders at `2025-06-15` (100.00) + `2026-12-31 23:59:59` (300.00),
   `GET /reports/profit-loss?period=all&date_to=2026-12-31`
   → `assertSee('>400.00')` (proves the window reaches before the year start,
   where period=year would show only 300).
2. `test_all_time_period_counts_early_expense_in_operating_expenses` —
   seed an unlinked Expense (expense_date `2026-01-05`, 50.00 AFN) + an order
   in the anchor month; `GET ...?period=all&date_to=2026-12-31` → the expense
   appears (`>-50.00`).
   (If both prove heavy, test 1 alone is the load-bearing one — it pins the
   match arm; test 2 pins the date-only-column path via the shared MIN probe.)

## Verification
1. `php artisan test tests/Feature/Reports/ProfitLossTest.php` — new tests red
   before the controller change (period=all currently falls to startOfMonth),
   green after.
2. Full `php artisan test` — the 4 existing boundary tests
   (month/30/quarter/year) must stay green; no other test uses `period=all`.
3. Manual: `/reports/profit-loss?period=all` — pill highlights, header reads
   "All time · <anchor> · AFN", numbers span all history.

## Out of scope (optional follow-ups)
- Same "all" arm for the Daybook report (identical pill structure,
  ReportController.php:326-329) — not requested.
- Hiding the end-date input under period=all.

## Risk
Low: one match arm + one private helper + one array entry; unknown periods
previously degraded silently to "month", so no legacy `period=all` links exist.
