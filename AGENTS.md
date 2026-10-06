# AGENTS.md

## Project purpose

Ornament World is a planned premium men's jewellery e-commerce platform for the Bangladesh market. The product principle is **Luxury look, simple shopping.**

The authoritative implementation direction is in [plan-final.md](plan-final.md). Read that file before making implementation decisions or changing the project scope.

## Current repository state

The repository now contains the approved planning documents, the initial Nemesis foundation, the Phase 2 catalog/admin path, the Phase 3 cart/guest-checkout path, the Phase 4 order-operations path, the Phase 5 shipment path, the Phase 6 SEO/analytics foundation, the Phase 7 local release gate, and the Phase 8 optional customer-account path inside `backend/`. Catalog browsing, protected catalog actions, guest order creation, optional customer registration/login/profile management, configurable delivery rules, server-side totals, checkout retry idempotency, status transitions, audit history, fraud review, customer order history, stock release on cancellation/rejection, shipment persistence, Pathao idempotency state, manual shipment recovery, public metadata, crawl files, branded 404 handling, provider-neutral event instrumentation, and browser-session admin protection are implemented and locally verified as applicable. Complete admin catalog image management, phone confirmation as an external business process, live Pathao account activation/provider verification, final brand-content handoff, deployment, and live QA are not complete; do not imply that those features exist until they are built and verified.

## Technology direction

- Backend: `jarir/nemesis-framework` created through Composer.
- Frontend: Svelte components inside Nemesis `resources/js/svelte` and `resources/views/svelte`.
- Styling/build: Tailwind CSS through the Nemesis Vite pipeline.
- Database: MySQL for the current local foundation and planned production direction.
- Data flow: Nemesis controller/view props by default; JSON endpoints only for asynchronous actions, webhooks, or external integrations that need them.
- Component communication: use Svelte props for parent-to-child data flow; props do not replace secure backend persistence or external-service communication.
- Initial market/currency: Bangladesh / BDT.

## Working rules

1. Read `plan-final.md` and the relevant phase before implementation.
2. Preserve unrelated user work and inspect existing files before editing them.
3. Keep the first release mobile-first, fast, accessible, and visually consistent with the black, gold, and white luxury theme.
4. Keep checkout guest-friendly. Customer accounts are optional and must never become a prerequisite for placing an order.
5. Treat the browser as untrusted. Recalculate price, discounts, delivery charge, stock, and order totals on the backend.
6. Keep order status and shipment status separate. Only confirmed orders may be sent to Pathao.
7. Pathao failures must leave a visible recoverable manual-shipment queue; never discard an order because an external request failed.
8. Use idempotency for shipment creation and preserve order/status/shipment audit history.
9. Validate Bangladesh phone numbers, complete addresses, districts, and required checkout data on both client and server.
10. Never commit credentials, `.env` files, tokens, private customer data, generated dependencies, or local tool state.
11. Add focused tests for pricing, validation, delivery rules, stock, status transitions, fraud signals, and Pathao retry/idempotency behavior.
12. Explain important non-obvious behavior with concise comments and update the relevant guides or README when workflow changes.
13. Keep the supplied Bangladesh location source at `backend/database/data/bangladesh_locations.json` traceable to its public source and validate its import before using it for checkout.

## Validation and handoff

Report these separately:

- Local formatting, linting, build, and test results.
- Deployment result.
- Git commit and push result.
- Provider/API response.
- Live smoke-test result.

A successful build or push is not proof that the live storefront or Pathao workflow works. For releases, verify the exact live customer checkout, admin flow, and shipment behavior using safe test data.
