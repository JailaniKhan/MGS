# ADR-0002: Client-negotiated order and purchase unit prices are trusted

**Date:** 2026-08-23
**Status:** Accepted
**Tags:** sales, purchases, pricing, trust-boundary

## Context

`OrderController::store` and the purchase path accept `products.*.unit_price`
from the client (`required|numeric|min:0.01`) rather than resolving
`products.price` server-side. The order form fetches the catalog price for
convenience (`orders/create.blade.php` product options), but the field stays
editable before submission.

A static audit flagged this as a trust-boundary issue: a crafted request could
sell at an arbitrary price. Whether that is a defect depends on whether
negotiated pricing is a feature.

## Decision

Client-supplied unit prices are **an intended feature**, not a bug. Shop
owners negotiate per-deal prices with customers and suppliers; enforcing the
catalog price server-side would remove that capability.

The backend keeps its existing guardrails:

- price must be `numeric|min:0.01`;
- quantity is checked against locked stock;
- line totals are computed with BC math from whatever price was accepted, so
  arithmetic can never disagree with the recorded lines.

## Alternatives considered

**(a) Enforce `products.price` server-side.** Rejected — breaks negotiated
pricing, which real shops using MGS rely on daily.

**(b) Server-side price with an explicit per-line discount field.** More
auditable, but adds schema and UI surface; revisit only if abuse actually
appears in practice.

## Consequences

- Any client holding valid credentials can record sales at arbitrary prices.
  This is acceptable because every writer is an authenticated shop operator on
  a device-local install; there is no untrusted third party inside the trust
  boundary.
- If MGS ever exposes ordering to parties outside the shop (public API,
  customer self-service), this decision must be revisited — that scenario
  crosses the trust boundary and needs server-side pricing.
