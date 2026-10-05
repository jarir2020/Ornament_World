# Phase 0 — Discovery and Technical Verification

**Date:** 2026-10-06
**Status:** In progress — local foundation verified; deployment and provider decisions remain.

## Scope

Phase 0 verifies the planned stack and records the decisions required before feature implementation. Initial stack validation was performed in isolated temporary directories under `/tmp`; the actual Nemesis and integrated Svelte foundations are now installed in this repository.

## Actual foundation now present

- `backend/` contains the Nemesis v7.2.0 Composer project and installed dependencies.
- `backend/resources/js/svelte` and `backend/resources/views/svelte` contain the integrated Svelte foundation.
- `backend/.env` is local-only and configured for MySQL. It is ignored and was not staged.
- An empty local MySQL database named `nemesis` was created because that is the configured Nemesis database name. No application data was added.
- The untouched Nemesis migrations ran successfully against MySQL.
- `backend/database/data/bangladesh_locations.json` contains the supplied public location dataset.

## Verification results

| Area | Evidence | Result |
|---|---|---|
| Repository | Published planning commit was clean before foundation work; current scaffold/report changes are local working-tree changes | Expected |
| PHP | PHP CLI 8.3.12 is available; Composer reports PHP 8.5.4 for dependency resolution | Passed, but runtime selection should be standardized in Phase 1 |
| Composer | Composer 2.9.5 is available | Passed |
| Nemesis package | `jarir/nemesis-framework` is available on Packagist; latest observed version is v7.2.0 and requires PHP `>=8.2` | Passed |
| Nemesis installation | Actual `backend/` created with `composer create-project`; 58 locked packages installed | Passed |
| Nemesis platform | `composer check-platform-reqs` passed for PHP and required extensions | Passed |
| Nemesis CLI | `key:generate`, `help`, `list`, and `db:list-connections` work after dependencies are installed | Passed with caveat |
| Integrated Svelte | Nemesis uses Svelte 5.57.1, Vite 8.3.2, and `@sveltejs/vite-plugin-svelte` in its root `backend/` pipeline | Passed |
| Tailwind CSS | Integrated build uses Tailwind CSS 4.3.3 with `@tailwindcss/vite` | Passed |
| Frontend validation | `npm run build --prefix backend` passed and generated the native `public/build/.vite/manifest.json` plus Svelte assets | Passed |
| Database | MySQL 8.4.11 accepted the supplied local credentials; empty `nemesis` database created and Nemesis migrations completed | Local development passed; production hosting pending |
| Bangladesh locations | Supplied JSON imported into `backend/database/data`; 1,369 records, 64 districts, and 479 district/subdistrict pairs validated | Passed |
| Pathao integration | Official Pathao material confirms merchant Developer API integration, system credentials, and webhooks | Provider capability confirmed; account details pending |
| Dependency security | Initial audit found 13 advisories; scoped updates moved Guzzle to 7.15.5, PSR-7 to 2.13.1, Flysystem to 3.36.0, and JMESPath to 2.9.2 | Audit now reports no advisories |
| Frontend dependency security | `npm audit` reports zero info, low, moderate, high, or critical vulnerabilities | Passed |

## Findings and caveats

### Nemesis

- The package is installable and its declared PHP requirement is compatible with the available runtime.
- The generated framework documentation contains version references that do not consistently match the observed Packagist v7.2.0 package. Phase 1 should pin the intended version and treat the installed package source/lock file as authoritative.
- Running the bare CLI without a command triggers a null-command `TypeError` in the tested package. Valid commands such as `help`, `list`, `key:generate`, and `db:list-connections` work. This should be reported upstream or guarded in the project’s developer workflow before relying on bare CLI output.
- The package supports SQLite, MySQL/MariaDB, and PostgreSQL. The actual local foundation uses MySQL and the empty `nemesis` database; existing scaffold migrations completed successfully.

### Integrated Svelte and Tailwind

- Nemesis’ integrated Svelte target uses `resources/js/svelte/app.js`, `resources/views/svelte`, and the native Vite manifest under `public/build/.vite/manifest.json`.
- The `/storefront` route renders a Nemesis Blade view, mounts the Svelte application, and serves its built JS/CSS assets successfully.
- The local npm policy requires `--ignore-scripts` for dependency installation in this environment. The backend package scripts are kept within the Nemesis root rather than creating a second frontend application.

### Pathao

Official references:

- [Pathao: website integration through the Merchant Developer API](https://help.pathao.com/integrate-pathao-panel-with-website/)
- [Pathao: Developer API, credentials, webhooks, and shipment workflow](https://pathao.com/blog/pathao-commerce-instant-delivery/)

The public documentation confirms that integration is available through the merchant panel and that current Pathao Commerce integration can use Client ID, Client Secret, Access Tokens, and webhook configuration. It does not provide this project’s account-specific endpoint access, location identifiers, sandbox status, COD settings, or webhook secret. Those must be verified from the Ornaments World merchant account or Pathao support before implementing the adapter.

No Pathao credentials were present in the local environment, and none were requested or stored.

### Bangladesh location source

The imported dataset is from [jarir-in-atl/BangladeshLocations](https://github.com/jarir-in-atl/BangladeshLocations/blob/main/bangladesh_locations.json). It currently provides `district`, `subdistrict`, `postoffice`, and `postcode` records. The checkout model should preserve the source spelling while allowing a future curated alias layer if courier or customer-facing naming needs differ.

The data is stored in `backend/database/data/bangladesh_locations.json` and is not yet connected to a checkout form or database seeder.

## Phase 0 blockers and decisions

The following items prevent Phase 0 from being marked complete:

1. **Hosting/deployment target:** choose the Nemesis PHP runtime/web server and the production Vite asset-serving process. No separate frontend runtime or adapter is required.
2. **Production database hosting:** MySQL is selected for the project; choose the production MySQL/MariaDB host, database name, backup strategy, and least-privilege application user. The local database name currently defaults to `nemesis`.
3. **Pathao merchant access:** provide or verify merchant Developer API access, credential generation, sandbox/test capability, required location IDs, COD rules, shipment creation fields, retry behavior, and webhook support.
4. **Initial payment policy:** confirm whether launch is COD-only. The current plan assumes COD unless the business specifies an online gateway.
5. **OTP policy:** confirm whether phone OTP is required at launch or deferred until fake-order rates justify the added cost and friction.
6. **Location operations:** the supplied 64-district dataset is selected; confirm the update process and whether Pathao requires a separate area/location-ID mapping.
7. **Production services:** choose Nemesis PHP hosting/web server, image storage, monitoring, email/SMS provider, and analytics accounts.

## Phase 0 exit criteria

Phase 0 can be closed after the decisions above are recorded and these confirmations are available:

- The intended PHP binary and deployment runtime are fixed.
- MySQL is fixed for the project; the production host, database name, application user, and backup strategy are fixed.
- The integrated Nemesis Svelte/Vite asset pipeline is fixed; production PHP/web-server hosting and asset deployment are still to be selected.
- Pathao merchant API access and a safe test path are verified.
- Initial COD/OTP/location-data decisions are fixed.
- Phase 1 can create the actual backend/frontend projects using the verified stack without guessing at infrastructure or provider contracts.

## Next authorized phase

Once the open decisions are resolved, Phase 1 will continue inside the Nemesis project, establish environment templates and CI checks, and expand the shared responsive Svelte application shell. No production credentials should be committed during that work.

## Phase 1 started locally

The first Phase 1 foundation slice is now implemented inside `backend/`:

- The storefront receives its initial content as server-rendered props from `FrontendController`; no catalog API is needed for this static shell.
- The integrated Svelte app now provides the responsive Ornaments World navigation, hero, collection links, story section, bag affordance, and footer.
- The root route enters `/storefront`, while the `/admin` route is protected by Nemesis `auth:admin` middleware.
- Local smoke tests passed for the storefront response, root redirect, built Vite assets, health endpoint, and unauthenticated admin rejection.

Phase 1 remains in progress until the setup/CI contract and the complete admin authentication flow are documented and verified.

## Phase 2 started locally

The first catalog slice is now implemented inside `backend/`; see [phase-2-catalog.md](phase-2-catalog.md) for the scoped details and evidence. Phase 2 is in progress, not complete.
