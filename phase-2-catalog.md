# Phase 2 — Catalog and Storefront Progress

**Date:** 2026-10-06
**Status:** In progress — catalog read path verified locally; admin catalog management and full image workflow remain.

## Implemented slice

- Added the MySQL catalog schema:
  - `categories`
  - `products`
  - `product_variants`
  - `product_images`
  - `discounts`
- Added catalog models and a read-side `CatalogService`.
- Added repeatable local-only `CatalogSeeder` demo data in BDT. These are development records, not production inventory claims.
- Added server-side category and search filtering on `/storefront`.
- Added server-rendered product props and `/storefront/product/{slug}` detail pages.
- Added product variant display, stock state, discount presentation, image-gallery support, and empty/not-found states in the integrated Svelte application.

Normal catalog page data is provided through Nemesis controller/view props. No separate frontend application or catalog API is required for this read path.

## Local verification

- PHP syntax checks passed for the migration, seeder, models, service, controller, and routes.
- `npm run build` passed with Vite 8.3.2 and Svelte 5.57.1.
- `php nemesis migrate:run` applied `2026_10_06_000000_create_catalog_tables.php` to local MySQL.
- `php nemesis db:seed CatalogSeeder` completed successfully.
- `migrate:status` reports the catalog migration as `Ran`.
- `/storefront` returned HTTP 200 with the seeded catalog.
- `/storefront?category=bracelets` returned HTTP 200 with the bracelet result.
- `/storefront/product/onyx-signet-ring` returned HTTP 200.
- An unknown product slug returned HTTP 404.
- `/_health` returned HTTP 200 with database health `ok`.

## Remaining Phase 2 work

- Build protected admin catalog CRUD for categories, products, variants, prices, discounts, stock, and active state.
- Add an approved image upload/storage workflow and verify responsive galleries with real project assets.
- Add focused catalog tests for filtering, price/discount presentation, active state, and stock display.
- Complete the Phase 2 exit criterion: an admin can manage an active catalog and customers can browse it on mobile and desktop.
