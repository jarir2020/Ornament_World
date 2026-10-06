# Phase 5 — Pathao Shipment Progress

**Date:** 2026-10-06
**Status:** In progress — the provider boundary, shipment state, protected admin actions, and local fake-provider checks are complete for this slice; Pathao account activation and live verification remain pending.

## Scope and provider boundary

The order domain owns eligibility and shipment state. The Pathao-specific client is isolated behind `PathaoProviderInterface`, so a provider contract change or another courier can be added without moving courier logic into checkout or Svelte components.

The normal admin page is still rendered with Nemesis controller/view props. Only the buttons that change shipment state use protected JSON actions, because they need an asynchronous server action and provider I/O.

## Implemented slice

- Added `shipments` with one idempotency row per order and `shipment_attempts` with sanitized audit outcomes.
- Kept `orders.status` separate from `orders.shipment_status`.
- Allowed automated creation only for confirmed, non-blocked orders. Suspicious orders must be reviewed before shipment creation.
- Added an environment-driven server-only `PathaoClient`; credentials, tokens, request bodies, and raw provider responses are not logged or passed to the browser.
- Added bounded retry state for retryable failures, stale pending-claim recovery, and a manual fallback reference/note path.
- Added protected `Send to Pathao / create shipment`, retry, and manual shipment actions to the admin order detail page.
- Added a dashboard manual-shipment queue so provider failures remain visible and recoverable.
- Added `FakePathaoProvider` for deterministic local idempotency and failure tests without network access.

## Provider verification boundary

Pathao’s official help identifies the Developer API option in the merchant panel: [Integrate Pathao panel with website](https://help.pathao.com/integrate-pathao-panel-with-website/). Pathao’s published commerce workflow also describes orders entering the merchant panel and being accepted/marked ready to ship: [Pathao Commerce workflow](https://pathao.com/blog/pathao-commerce-online-store-creation/).

Those public pages do not establish this project’s merchant credentials, store/location identifiers, sandbox access, COD policy, webhook permissions, or the exact account API contract. The environment-driven endpoint paths and payload mapping must be checked against the merchant account’s current official Developer API documentation before production activation. No Pathao credentials have been added and no live provider request has been made in this phase.

## Local verification

- `php nemesis migrate:run` applied `2026_10_06_030000_create_shipments.php` to local MySQL, and `migrate:status` reports all project migrations as `Ran`.
- PHP syntax checks passed for the Phase 5 provider, service, controller, migration, routes, and modified admin service.
- `npm run build` passed with Vite 8.3.2 and Svelte 5.57.1.
- A disposable confirmed-order test created one fake shipment; repeating the create action made exactly one fake-provider call.
- Three retryable fake failures stopped at the configured attempt limit and remained `manual_required`.
- A manual shipment reference was persisted without changing the commercial order status. The test restored stock and removed its orders; the local database retained zero test orders and zero shipment rows afterward.
- The reproducible local check is `php tests/Phase5ShipmentServiceTest.php` from `backend/`.
- The suspicious-order eligibility guard rejected an unresolved test flag before the test resolved it and continued, confirming that review is required before shipment creation.
- `/storefront` and `/checkout` returned HTTP 200; `/admin`, `/admin/orders/OW-NOT-FOUND`, and the shipment action returned HTTP 401 without admin authentication.
- No live Pathao request was made.

## Remaining Phase 5 work

- Verify the exact Pathao merchant API contract, credentials, store/location IDs, COD behavior, sandbox/test capability, and webhook/status-sync options with the merchant account.
- Add live-safe provider contract tests only after the account test boundary is confirmed.
- Complete browser-level testing with a real admin authentication/session flow and safe test shipments.
