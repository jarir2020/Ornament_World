# Phase 4 — Order Operations and Fraud Review Progress

**Date:** 2026-10-06
**Status:** In progress — protected order operations and reversible stock release are locally verified; browser authentication and full release workflow remain.

## Implemented slice

- Added the Phase 4 schema fields for confirmation/cancellation timestamps, cancellation reasons, per-line stock release tracking, and fraud-review notes/actors.
- Added constrained order transitions:
  - `new_order → pending_confirmation | confirmed | cancelled | rejected_suspicious`
  - `pending_confirmation → confirmed | cancelled | rejected_suspicious`
  - `confirmed → processing | cancelled`
  - `processing → shipped | cancelled`
  - `shipped → delivered`
- Added protected order detail pages with customer contact/address, immutable order-line snapshots, separate shipment status, totals, status history, fraud flags, and previous orders for the same normalized phone.
- Added protected dashboard counts, search by reference/name/phone/district, and status filtering.
- Added internal notes and required notes for confirmation, cancellation, and suspicious rejection.
- Added fraud-review outcomes: legitimate/confirmed, dismissed, and blocked, with review notes and timestamps.
- Added transactional stock release on cancellation or suspicious rejection. Each order line is marked as released so repeated actions cannot restore stock twice.
- Kept order status separate from shipment status; no Phase 4 action creates or sends a Pathao shipment.

## Local verification

- PHP syntax checks passed for the migration, order service/controller, frontend controller, and routes.
- `npm run build` passed with Vite 8.3.2 and Svelte 5.57.1.
- `php nemesis migrate:run` applied `2026_10_06_020000_create_order_operations.php` to local MySQL.
- A local two-order operation test detected duplicate phone/address signals, reviewed a fraud flag, recorded lifecycle history, cancelled one order, rejected the other, confirmed stock was restored exactly once, and cleaned up both smoke-test orders.
- The public `/storefront` and `/checkout` routes returned HTTP 200.
- `/admin` and `/admin/orders/{reference}` returned HTTP 401 without admin authentication.
- The local database retained zero smoke-test orders after cleanup.

## Remaining Phase 4 work

- Complete focused automated tests for transition rules, audit history, fraud-review outcomes, concurrent stock behavior, and rollback paths.
- Complete browser-level testing of the protected dashboard/detail actions with a real admin authentication/session flow.
- Add richer reporting definitions and operational filters such as date range and shipment state.
- Complete the full catalog edit/image workflow and phone-confirmation operating procedure.
- Pathao shipment creation, idempotency, retry handling, and manual fallback remain Phase 5.
