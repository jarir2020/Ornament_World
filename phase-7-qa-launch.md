# Phase 7 — Quality Assurance and Launch Record

**Date:** 2026-10-06
**Status:** Local release gate in progress; deployment and live verification are pending.

## Implemented in this phase

- Added an app-level admin middleware that supports secure browser sessions and
  bearer clients while rechecking the live Nemesis RBAC assignment.
- Exposed the existing catalog update service through a usable admin edit form
  for product details, prices, stock, category, ordering, and active/featured
  state. Image upload and derivative management remain a separate follow-up.
- Updated browser login to establish a regenerated session and send admin staff
  to the protected admin page. Invalid HTML logins redirect without exposing
  credential details; JSON callers receive a 401.
- Added `AdminUserSeeder`, which provisions an admin only from
  `ADMIN_EMAIL`, `ADMIN_PASSWORD`, and optional `ADMIN_USERNAME` environment
  values. The previous plaintext sample-user seeder no longer ships passwords.
- Added checkout idempotency. The client keeps an opaque retry key in
  `sessionStorage`; only its SHA-256 digest is stored in MySQL under a unique
  constraint. A repeated submission returns the original order.
- Added `Phase7ReleaseGateTest.php` covering checkout validation, server-side
  totals and delivery, duplicate-submit recovery, status transitions, stock
  release, fraud signals, browser admin login/session protection, and cleanup.

## Local verification evidence

| Check | Result |
| --- | --- |
| `php tests/Phase5ShipmentServiceTest.php` | Passed: shipment idempotency, retry cap, manual fallback, cleanup |
| `php tests/Phase6SeoAnalyticsTest.php` | Passed: robots, sitemap, product schema, no-PII analytics boundary |
| `php tests/Phase7ReleaseGateTest.php` | Passed: validation, pricing/delivery, duplicate-submit, status/stock, fraud, admin session, cleanup |
| `npm run build` | Passed with Vite 8.3.2; approximately 33.34 kB gzip JavaScript and 7.24 kB gzip CSS |
| PHP syntax scan | Passed for 87 application, route, migration, seeder, view, and public PHP files |
| `git diff --check` | Passed |
| Local HTTP smoke | `/`, storefront, product, checkout, help, robots, and sitemap returned expected responses; missing page returned 404 |
| Protected admin HTTP smoke | `/admin` redirected to `/login?next=/admin`; JSON request returned 401 |
| Headless Chrome viewport smoke | Storefront mounted at 390×844, 768×1024, and 1440×1000; assets and favicon loaded; no page JavaScript errors were observed |
| Database migration | `2026_10_06_040000_add_checkout_idempotency.php` applied to local MySQL |

The local test identities and orders are disposable and were removed after the
release-gate run. No Pathao request or analytics-provider request was made.

## Manual QA matrix

The following still requires a real browser/device pass before launch sign-off:

- Android-sized, iPhone-sized, tablet, and desktop storefront-to-checkout flow.
- Keyboard-only navigation, focus visibility, labels, error announcements, and
  reduced-motion behavior.
- Empty bag, invalid location, out-of-stock, back-button, refresh-after-success,
  slow-network, image-loading, and repeated-submit behavior.
- Admin login, catalog editing, order transition, fraud review, shipment retry, and manual
  fallback using a provisioned non-production admin account.
- Mobile performance and Core Web Vitals on representative Android and iPhone
  devices.

## Launch blockers and external evidence boundary

- No production target, backup destination, deployment procedure, or rollback
  window was supplied in this workspace, so no production backup or deployment
  was attempted.
- Pathao credentials/store configuration are not present in tracked files and
  no live or sandbox provider request was made. Provider response evidence is
  therefore pending.
- The contact and privacy pages still contain business/legal handoff
  placeholders, and final brand/product image assets are pending.
- HTTPS, production secure-cookie settings, live admin provisioning, live
  checkout, live shipment behavior, and live SEO/performance checks remain
  unverified.

## Backup, deployment, and rollback runbook

Before an authorized release:

1. Put the site in maintenance mode or pause order intake according to the
   hosting procedure.
2. Take and verify a restorable MySQL backup, including catalog, orders,
   status history, fraud flags, shipments, and shipment attempts.
3. Record the current application commit and migration status.
4. Deploy the approved commit through the hosting process, run migrations, and
   provision the admin role with deployment-only environment variables.
5. Verify the migration status and run safe smoke checks before reopening order
   intake.
6. If rollback is required, stop intake, restore the database only with an
   approved recovery decision, deploy the last known-good commit, and verify
   the storefront, admin access, order state, and shipment queue.

Build/test success, a Git push, a provider response, and live verification are
separate release evidence and must be recorded separately.
