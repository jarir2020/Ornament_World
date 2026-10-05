# AGENTS.md

## Project purpose

Ornament World is a planned premium men's jewellery e-commerce platform for the Bangladesh market. The product principle is **Luxury look, simple shopping.**

The authoritative implementation direction is in [plan-final.md](plan-final.md). Read that file before making implementation decisions or changing the project scope.

## Current repository state

This repository currently contains the approved planning documents and project metadata. The application has not yet been implemented. Do not imply that storefront, checkout, admin, or Pathao functionality exists until it is actually built and verified.

## Technology direction

- Backend: `jarir/nemesis-framework` created through Composer.
- Frontend: SvelteKit.
- Styling: Tailwind CSS.
- Backend/frontend boundary: versioned backend JSON endpoints and/or secure SvelteKit server-side load/form actions as appropriate.
- Component communication: use Svelte props for parent-to-child data flow; props do not replace secure backend persistence or external-service communication.
- Initial market/currency: Bangladesh / BDT.

## Working rules

1. Read `plan-final.md` and the relevant phase before implementation.
2. Preserve unrelated user work and inspect existing files before editing them.
3. Keep the first release mobile-first, fast, accessible, and visually consistent with the black, gold, and white luxury theme.
4. Keep checkout guest-friendly. Do not add mandatory customer registration unless the plan is explicitly revised.
5. Treat the browser as untrusted. Recalculate price, discounts, delivery charge, stock, and order totals on the backend.
6. Keep order status and shipment status separate. Only confirmed orders may be sent to Pathao.
7. Pathao failures must leave a visible recoverable manual-shipment queue; never discard an order because an external request failed.
8. Use idempotency for shipment creation and preserve order/status/shipment audit history.
9. Validate Bangladesh phone numbers, complete addresses, districts, and required checkout data on both client and server.
10. Never commit credentials, `.env` files, tokens, private customer data, generated dependencies, or local tool state.
11. Add focused tests for pricing, validation, delivery rules, stock, status transitions, fraud signals, and Pathao retry/idempotency behavior.
12. Explain important non-obvious behavior with concise comments and update the relevant guides or README when workflow changes.

## Validation and handoff

Report these separately:

- Local formatting, linting, build, and test results.
- Deployment result.
- Git commit and push result.
- Provider/API response.
- Live smoke-test result.

A successful build or push is not proof that the live storefront or Pathao workflow works. For releases, verify the exact live customer checkout, admin flow, and shipment behavior using safe test data.
