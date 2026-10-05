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
