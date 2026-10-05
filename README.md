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

The repository now contains the approved project plan, Phase 0 discovery report, and the initial Nemesis-integrated Svelte foundation. Feature implementation will proceed phase by phase according to [plan-final.md](plan-final.md).

## Documentation

- [Final implementation plan](plan-final.md)
- [Original requirements](plan.md)
- [Phase 0 discovery report](phase-0-discovery.md)
- [Agent and contributor guidance](AGENTS.md)
- [LLM project context](llms.txt)

## Repository layout

- `backend/` — Nemesis application, integrated Svelte/Vite/Tailwind assets, Composer dependencies, migrations, and data packs.
- `backend/database/data/bangladesh_locations.json` — supplied Bangladesh district/subdistrict/post-office dataset.

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

The initial Nemesis and integrated Svelte foundations are now created. Before adding features, read `AGENTS.md` and the relevant phase in `plan-final.md`.

Backend setup:

```bash
cd backend
composer install
php bin/nemesis migrate:status
```

Integrated Svelte/Vite setup:

```bash
cd backend
npm install
npm run dev
```

Local credentials belong in the ignored `backend/.env`; never commit them. The tracked `backend/.env.example` contains only safe configuration placeholders.

## License

This project is released under the [MIT License](LICENSE).
