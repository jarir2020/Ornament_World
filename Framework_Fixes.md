# Framework Fixes

Project-specific fixes made inside the Nemesis framework source tree (`backend/src`).

## 2026-10-06 — Vite CSS entry emitted as a JavaScript module

- **File:** `backend/src/Assets/ViteManifest.php`
- **Symptom:** The browser rejected the CSS entry with: `Expected a JavaScript-or-Wasm module script but the server responded with a MIME type of "text/css"`.
- **Cause:** `ViteManifest::tags()` always emitted `<script type="module">`, including when the requested Vite entry was CSS.
- **Fix:** CSS entries now emit `<link rel="stylesheet">`; JavaScript entries continue to emit module scripts. The same behavior is handled for Vite hot mode.
- **Verification:** The storefront now returns `text/css` for the stylesheet, `application/javascript` for the JavaScript bundle, and the page loads without the module MIME error.
- **Commit:** `3f869ae` (`Fix Vite CSS MIME type and favicon`)

Future fixes inside `backend/src` should be added here with the affected file, symptom, cause, fix, verification, and commit.

## 2026-10-06 — PHP development server forwards extension routes

- **Files:** `backend/bin/nemesis`, `backend/public/router.php` (development-server bridge; outside `backend/src`).
- **Symptom:** `php nemesis serve` served `/robots.txt` and `/sitemap.xml` as missing static files instead of dispatching them to application routes. Extensionless storefront routes worked, which made the local behavior inconsistent with Apache/Nginx front-controller routing.
- **Cause:** PHP's built-in server treats a request with a file extension as a static-file lookup unless a router script is supplied.
- **Fix:** `nemesis serve` now supplies the public router script. Existing files still pass through unchanged; missing paths, including dynamic crawl files, reach Nemesis.
- **Verification:** Phase 6 local HTTP smoke checks cover `/robots.txt`, `/sitemap.xml`, public pages, and the branded fallback 404.
