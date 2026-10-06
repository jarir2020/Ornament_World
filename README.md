# Ornament_World

Premium men's jewellery e-commerce platform for Bangladesh.

## Project direction

Ornaments World is being planned around:

- Premium black, gold, and white branding.
- Mobile-first product browsing.
- Simple guest checkout through **Order Now** or **Add to Cart**.
- Bangladesh district and address validation.
- Configurable inside-Dhaka and outside-Dhaka delivery charges.
- Admin-managed products, inventory, orders, and customer review flags.
- Phone confirmation before shipment.
- Safe Pathao shipment automation with a manual fallback queue.
- SEO, analytics, and conversion-tracking readiness.

The guiding principle is **Luxury look, simple shopping.**

## Status

The repository now contains the approved project plan, Phase 0 discovery report, the Nemesis-integrated Svelte foundation, the Phase 2 catalog/admin slice, the Phase 3 cart/guest-checkout slice, the Phase 4 order-operations slice, the Phase 5 shipment slice, and the in-progress Phase 6 SEO/analytics slice. Feature implementation proceeds phase by phase according to [plan-final.md](plan-final.md).

## Documentation

- [Final implementation plan](plan-final.md)
- [Original requirements](plan.md)
- [Phase 0 discovery report](phase-0-discovery.md)
- [Phase 2 catalog progress](phase-2-catalog.md)
- [Phase 3 checkout progress](phase-3-checkout.md)
- [Phase 4 order operations progress](phase-4-order-operations.md)
- [Phase 5 Pathao shipment progress](phase-5-pathao.md)
- [Phase 6 SEO, analytics, and performance progress](phase-6-seo-analytics.md)
- [Framework fixes](Framework_Fixes.md)
- [Agent and contributor guidance](AGENTS.md)
- [LLM project context](llms.txt)

## Repository layout

- `backend/` — Nemesis application, integrated Svelte/Vite/Tailwind assets, Composer dependencies, migrations, and data packs.
- `backend/database/data/bangladesh_locations.json` — supplied Bangladesh district/subdistrict/post-office dataset.
- `backend/database/migrations/2026_10_06_000000_create_catalog_tables.php` — Phase 2 catalog schema.
- `backend/database/migrations/2026_10_06_010000_create_checkout_tables.php` — Phase 3 locations, delivery, orders, history, and fraud schema.
- `backend/database/migrations/2026_10_06_020000_create_order_operations.php` — Phase 4 status, review, and stock-release fields.
- `backend/database/migrations/2026_10_06_030000_create_shipments.php` — Phase 5 shipment and sanitized-attempt audit tables.
- `backend/public/router.php` — local PHP-server bridge so extension routes reach Nemesis while real assets remain static.
- `backend/database/seeders/CatalogSeeder.php` — repeatable local demo catalog seed.

## Planned stack

- Backend: `jarir/nemesis-framework`
- Frontend: Svelte inside Nemesis
- CSS/build: Tailwind CSS + Vite
- Database: MySQL
- Initial currency: BDT
- Initial market: Bangladesh

Svelte props will be used for parent-to-child component data flow. Nemesis controllers will provide page data to the integrated Svelte views; JSON endpoints are reserved for asynchronous actions, webhooks, and external integrations.

## Planned first-release capabilities

1. Catalog, categories, variants, images, discounts, and stock.
2. Responsive homepage, listing pages, and product detail pages.
3. Cart and direct-order checkout without mandatory registration.
4. Server-side price, stock, customer, district, address, and delivery validation.
5. Admin order lifecycle from new order through delivery or cancellation.
6. Suspicious/duplicate order review and previous-order history.
7. Confirmed-order Pathao creation with idempotency and manual recovery.
8. SEO metadata, structured product data, sitemap, and conversion events.

## Development

The initial Nemesis and integrated Svelte foundations are now created, and Phase 6 SEO/analytics work is in progress. Before adding features, read `AGENTS.md` and the relevant phase in `plan-final.md`.

Backend setup:

```bash
cd backend
composer install
php bin/nemesis migrate:status
php bin/nemesis db:seed CatalogSeeder
```

Integrated Svelte/Vite setup:

```bash
cd backend
npm install
npm run build
php bin/nemesis serve 127.0.0.1:8098
```

The Nemesis development server supplies the project router, so `/robots.txt`, `/sitemap.xml`, and the branded fallback 404 work locally alongside static Vite assets.

Local credentials belong in the ignored `backend/.env`; never commit them. The tracked `backend/.env.example` contains only safe configuration placeholders.

## License

This project is released under the [MIT License](LICENSE).
