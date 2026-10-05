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

This repository currently contains the approved project plan and publication documentation. Application implementation will proceed phase by phase according to [plan-final.md](plan-final.md).

## Documentation

- [Final implementation plan](plan-final.md)
- [Original requirements](plan.md)
- [Agent and contributor guidance](AGENTS.md)
- [LLM project context](llms.txt)

## Planned stack

- Backend: `jarir/nemesis-framework`
- Frontend: SvelteKit
- CSS: Tailwind CSS
- Initial currency: BDT
- Initial market: Bangladesh

Svelte props will be used for parent-to-child component data flow. Secure backend communication, persistence, order validation, and Pathao integration will use the backend boundary and SvelteKit server-side loading/form actions where appropriate.

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

Implementation setup and commands will be documented when the backend and frontend projects are created. Before adding features, read `AGENTS.md` and the relevant phase in `plan-final.md`.

## License

This project is released under the [MIT License](LICENSE).
