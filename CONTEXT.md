# CONTEXT.md

A glossary for the MGS domain. Implementation lives in code; this file is vocabulary
only. Resolved during the ReturnService grilling session.

## Order

A sale to a customer or a sale to a supplier (polymorphic `person_type` /
`person_id`). Carries `currency` (AFN or USD), `total_amount`, `status`
(`pending|processing|completed|cancelled`). Money is BC-arithmetic, scale 2.

## Purchase

A purchase from a supplier or a purchase from a customer (mirrors Order's
polymorphic party). Same currency, total, status shape as Order.

## Return

A reversal of some or all of an Order's (OrderReturn) or Purchase's
(PurchaseReturn) line items back into inventory. A Return:

- **moves stock**: `products.stock` is mutated (incremented for an OrderReturn,
  decremented for a PurchaseReturn) and a `StockMovement` row is written per line.
- **is money-informational only**: `total_amount` is recorded but **no journal
  entry is posted** — neither the sale nor the return has a ledger-side effect
  yet (see ADR-0001). The parent's `returned_amount` accessor reflects the
  effect for "remaining" calculations.
- **currency is inherited**: the Return's `currency` equals its parent order's
  or purchase's `currency`. It is never user-supplied.
- **qty must be ≤ sold − already-returned** for the same order/purchase and
  product. Phantom returns are rejected by the service layer.
- **has a `status` (`pending|processing|completed|cancelled`)** that today is
  cosmetic for the inventory effect — stock moves at creation, not on transition
  to `completed`. This is a known leak flagged for a follow-up lifecycle ticket,
  not the current contract.
- **is cancelled by flipping `status='cancelled'`, never by hard delete** in the
  service. Hard delete leaves orphaned `StockMovement` rows (the `reference_id`
  column has no FK — confirmed in its migration) and loses audit history. The
  `Order::returned_amount` accessor already filters `status != cancelled`, so a
  cancelled return stops affecting the parent's `remaining_amount` for free.
  The controller's existing `destroy` route stays for "delete mistaken draft
  returns" only (a `pending`-only guard is a small follow-up); `revertReturn`
  runs first so stock is restored, then `$return->delete()` purges the row and
  its items (which `cascadeOnDelete`).
- **`ReturnService` exposes `createReturn(...)` + `revertReturn($return)`** —
  mirroring `OrderController`'s `store`/`status` split but with both writes
  transactional and row-locked. The controller call sites shrink to one-liners.

## Stock

The current on-hand count of a product. **`products.stock` is the authoritative
source of truth** (a denormalized integer column mutated by the sale/return
controllers under row lock). `stock_movements` is the **append-only audit log**
of every delta — not a ledger to compute current stock from. Unifying these two
into a single stock ledger is deferred (see ADR-0001's companion decision,
future).

## StockMovement

An append-only row recording a `quantity_change`, `movement_type`
(`sale|return|purchase|purchase_return|order_cancelled|order_deleted|
return_cancelled|purchase_return_cancelled`), and a polymorphic
`reference_type`/`reference_id` back to the originating Order / Purchase /
OrderReturn / PurchaseReturn. Has an optional `journal_entry_id`Foreign Key
that today is left `null` on sale/return paths — the link point for the future
stock-to-money audit trail (deferred, see ADR-0001).

## IdempotencyKey

A UUID on `JournalEntry` that prevents double-posting the same transaction.
`TransactionService::post` locks the row `for update` and returns the existing
entry on a hit. Relevant context for any future Return-Posts-To-Ledger work.
