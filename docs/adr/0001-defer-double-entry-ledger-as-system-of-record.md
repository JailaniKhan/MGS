# ADR-0001: Defer promoting the double-entry ledger to system of record for money

**Date:** 2026-08-10
**Status:** Deferred (active decision to *not* change yet)
**Tags:** accounting, ledger, returns, sales

## Context

MGS has a working double-entry ledger — `JournalEntry` (balanced, idempotent via
`idempotency_key`) and `LedgerEntry` (debit/credit lines per account), posted by
`App\Services\Accounting\TransactionService::post`. It has tests
(`tests/Feature/Accounting/TransactionServiceTest`).

It is **not** the system of record for money today:

- `OrderController::store` writes an `Order` + `OrderItem`s + `StockMovement`s
  but never calls `TransactionService::post` and never sets `journal_entry_id`
  on the stock movements.
- `OrderReturnController::store` and `PurchaseReturnController::store` do the
  same — they record `total_amount` and a `StockMovement` but post no journal
  entry.
- `DashboardController` (a comment in the code says so explicitly) computes money
  totals from six raw transaction tables (Payment, PartyPayment, SalaryPayment,
  Expense, CashbookEntry, and a `journal_entries` ⇄ `ledger_entries` join) —
  because the ledger isn't trusted yet.
- `ReportController::daybook` has a running-balance workaround with a code
  comment about a prior `ArgumentCountError` against `BalanceService`.

So real accounting arithmetic happens in two parallel worlds: the raw
transaction tables (used by dashboard/reports) and the journal/ledger (used by
`BalanceService` / `CashbookController`).

## Decision

We **defer** promoting the ledger to system of record. Concretely, for the
ReturnService work happening in parallel with this ADR:

- Returns **do not** post a journal entry. They record `total_amount` on the
  `OrderReturn` / `PurchaseReturn` row and a `StockMovement` per line, mirroring
  the sale path's current behavior.
- The sale path's missing journal entry is also left as-is for now; we are not
  fixing it inside the ReturnService ticket.
- `StockMovement.journal_entry_id` stays nullable and stays null on the
  sale/return paths.

## Alternatives considered

**(a) Promote the ledger now; Returns post a balanced entry (sales-returns
contra + inventory asset + refund-due).** Rejected because the sale itself
doesn't post — posting a return while the sale isn't journaled would unbalance
the books. Doing both in one ticket is too large a blast radius (sale path,
payment path, dashboard reads, report reads, sync API).

**(b) Promote the ledger later, as its own initiative.** Accepted — this is the
decision. It will need `/wayfinder` to chart the shared decisions (which tables
become feeds; what `BalanceService` becomes once the ledger is trusted; how the
Dashboard/Report controllers change; sync API implications).

## Consequences

- Returns stay non-ledgered; `Order::returned_amount` and the unified "remaining
  = total − paid − returned" formula stay the source of truth for "what does the
  customer owe."
- The Dashboard/Report duplicated-accounting-formula smell, the running-balance
  hack, and the stock-to-money-audit-trail gap are all **expected to remain**
  until the deferred decision is made and executed.
- A future "Return lifecycle: pending→completed gates the stock move"
  follow-up is compatible with this decision — it changes *when* the effect
  happens, not where it books.
- A future damaged-goods / phantom-return workflow can add its own
  `movement_type` without conflicting with this.
