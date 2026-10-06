# Phase 8 — Optional customer accounts

## Status

Implemented and locally verified on 2026-10-06. This is an approved scope extension to the guest-first plan; account creation is optional and does not replace guest checkout.

## Delivered

- Customer registration with a 12-character minimum password, duplicate-email protection, and optional Bangladesh mobile validation.
- Customer login using the Nemesis session plus a bearer-token response for API clients.
- Protected `/profile` page with editable name and optional mobile number.
- Account-linked order history for orders placed while signed in.
- Checkout keeps working for guests and attaches a `customer_id` only when a valid customer session is present.
- Sign out from the storefront header or profile page.
- Server-side customer middleware that revalidates the account and role on each protected request.
- MySQL migration `2026_10_06_050000_add_customer_accounts.php` for profile fields and nullable order ownership.

## Routes

- `GET /login` and `POST /login`
- `GET /register` and `POST /register`
- `GET /profile`
- `PUT /profile` for the asynchronous profile update
- `POST /logout`

Normal page data is passed from Nemesis controllers to Svelte as props. JSON is used only for the profile update, logout, and other asynchronous or integration-oriented actions.

## Validation

The focused integration test is `backend/tests/Phase8CustomerAccountsTest.php`. It covers registration, authentication, protected profile access, profile update, customer-linked checkout, profile order history, and cleanup. The existing Phase 5, Phase 6, and Phase 7 tests remain the regression suite for shipment behavior, SEO/analytics, and the release gate.

## Remaining boundaries

Password reset still follows the existing legacy user-controller flow and needs a separate hardening pass. Email verification, OTP, account deletion, saved addresses, and live deployment/QA are not part of this phase.
