# Phase 3 — Cart and Checkout Progress

**Date:** 2026-10-06
**Status:** In progress — guest cart/order creation and the protected new-order queue are locally verified; full order operations remain.

## Implemented slice

- Added the MySQL checkout schema:
  - `bangladesh_locations`
  - `delivery_zones` and `delivery_zone_districts`
  - `orders` and immutable `order_items` snapshots
  - `order_status_history`
  - `fraud_flags`
- Imported the supplied [BangladeshLocations source](https://github.com/jarir-in-atl/BangladeshLocations/blob/main/bangladesh_locations.json) through the migration. The source has 1,369 raw records; duplicate rows are tolerated by the unique key and 1,330 unique rows covering all 64 districts are stored locally.
- Seeded configurable delivery rules in backend data: Inside Dhaka ৳60 and Outside Dhaka ৳120.
- Added client-side `Add to bag`, quantity controls, removal, direct `Order now`, and session-backed cart state in the integrated Svelte app.
- Added a short guest checkout form with name, Bangladesh phone, optional email, complete address, controlled district, area/thana/upazila, and optional post office.
- Added server validation for names, phone normalization including Bengali digits and `+880` formats, email, address length, controlled locations, item quantities, active products, active variants, and stock.
- Added transactional server recalculation of current catalog price, product discounts, stock, delivery charge, and final total. The browser’s submitted prices are ignored.
- Added order references, initial `new_order` status, separate `not_ready` shipment status, status history, and duplicate-phone/address review flags.
- Added order-success rendering that exposes only the public order summary, not customer phone or address data.
- Added a protected admin new-order queue showing customer contact, delivery area, total, and review flags.

Normal checkout page data is provided through Nemesis controller/view props. The JSON endpoint is limited to the asynchronous order-submit action and is protected by the framework’s browser CSRF middleware.

## Local verification

- PHP syntax checks passed for the migration, checkout service/controller, frontend controller, and routes.
- `npm run build` passed with Vite 8.3.2 and Svelte 5.57.1.
- `php nemesis migrate:run` applied `2026_10_06_010000_create_checkout_tables.php` to local MySQL.
- `migrate:status` reports all catalog and checkout migrations as `Ran`.
- The imported database contains 1,330 unique location rows across 64 districts and two delivery zones.
- `/checkout` returned HTTP 200.
- `/storefront` returned HTTP 200 after the cart changes.
- An unknown `/checkout/success/{reference}` returned HTTP 404.
- `POST /checkout/orders` without a server CSRF token returned HTTP 419.
- One valid local checkout transaction calculated the expected Dhaka delivery charge and total, then its explicitly-created smoke-test order was removed and stock restored; the database contained zero orders afterward.

## Remaining Phase 3 work

- Add focused automated tests for phone normalization, location validation, delivery rules, discount math, stock races, duplicate flags, and transactional rollback.
- Complete browser-level checkout testing across narrow mobile and desktop layouts, including refresh/back-button and repeated-submit behavior.
- Complete the browser admin authentication/session flow.

Phase 4 has since added the protected order detail, status, fraud-review, history, filtering, and stock-release operations; the Phase 4 progress document records that work separately.
