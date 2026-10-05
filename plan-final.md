# Ornaments World — Final Website Implementation Plan

## 1. Product vision

Ornaments World will be a premium men's jewellery e-commerce website built around the principle:

> **Luxury look, simple shopping.**

The first release will target customers in Bangladesh. It will provide a fast, mobile-first storefront, a short guest checkout, reliable customer-information validation, a practical admin panel, and a controlled Pathao shipment workflow.

The initial release will prioritize:

1. Premium black, gold, and white brand presentation.
2. Fast product discovery and clear product information.
3. Guest checkout with both **Order Now** and **Add to Cart** journeys.
4. Accurate Bangladesh delivery information and automatic delivery-charge calculation.
5. Fake-order reduction through validation, duplicate detection, and phone confirmation.
6. Admin-controlled order status and shipment management.
7. Safe Pathao automation with a visible manual fallback.
8. SEO, analytics, and conversion tracking readiness.

International shipping, multiple currencies, online payment gateways, and additional courier providers will be prepared for in the architecture but will not be activated in the first release.

## 2. Technology and system architecture

### 2.1 Application structure

The application will use one Nemesis application with an integrated Svelte presentation layer:

- **Backend:** `jarir/nemesis-framework` created through Composer.
- **Frontend:** Svelte components inside Nemesis at `resources/js/svelte` and `resources/views/svelte`.
- **Asset pipeline:** Vite with `@sveltejs/vite-plugin-svelte`, Tailwind CSS, and `@tailwindcss/vite`.
- **Database:** MySQL for the current project direction and production baseline. SQLite remains useful for isolated tests only.
- **Data delivery:** Nemesis controllers render the Svelte-compatible views and pass page data as props. JSON endpoints are added only where asynchronous browser interactions, admin actions, webhooks, or external integrations require them.
- **Authentication:** Secure admin authentication with server-side sessions or token-based authentication according to the framework's supported pattern. Customers do not need accounts for the first release.
- **Media:** Product images stored outside the database, with validated paths/URLs recorded in the database.
- **Configuration:** Environment variables for database, app URL, mail/SMS, Pathao, analytics, and other secrets. Secrets must never be committed to source control or displayed in logs.

### 2.2 Architectural boundaries

The backend will own:

- Product, category, variation, inventory, cart/order, customer, delivery, fraud, and shipment data.
- Price, discount, delivery-charge, stock, and order-status calculations.
- Checkout validation and order creation.
- Admin authorization and audit history.
- Pathao API communication, retries, idempotency, and failure handling.
- Analytics event payloads where server-side events are later enabled.

The integrated Svelte UI will own:

- Responsive storefront presentation.
- Product browsing, filtering, and product details.
- Cart state and checkout forms.
- Clear validation messages and loading/error states.
- Consent-aware client-side tracking.

The frontend must never be trusted for final prices, stock, delivery fees, customer validation, or order status. The backend will recalculate and verify all order-critical values before saving an order.

## 3. Brand and UX direction

### 3.1 Visual system

- Primary palette: black, gold, and white, with restrained neutral shades for borders and muted text.
- Strong product photography and generous spacing.
- Premium typography with readable body text and clear Bengali/English fallback support where required.
- Subtle hover, scroll, and transition effects only; avoid heavy animation and large client-side dependencies.
- Consistent buttons, form controls, badges, cards, price display, and empty/error states.
- Accessible contrast, keyboard navigation, visible focus states, useful labels, and touch-friendly controls.

### 3.2 Mobile-first behavior

The mobile experience is the primary design target. The site must be tested on narrow Android and iPhone widths before desktop polish is finalized.

On mobile:

- Product images, price, stock status, **Order Now**, and **Add to Cart** remain easy to find.
- Checkout uses a short, single-column layout.
- Buttons have adequate touch targets.
- Navigation and cart access remain visible without overwhelming the screen.
- Images are responsive and appropriately compressed.
- No important action depends on hover.

## 4. Customer-facing scope

### 4.1 Storefront pages

The first release will include:

1. **Homepage**
   - Premium hero area with a concise brand message.
   - Shop Now / Explore Collection call to action.
   - Best sellers, new arrivals, featured collection, and category links.
   - Why Choose Ornaments World section.
   - Customer reviews and social-media section when content is available.
   - Footer with contact, delivery, order, policy, and social links.

2. **Category/product listing pages**
   - Categories such as Bracelets, Cuban Bracelets, Agate/Akik Stone Bracelets, Chains, Rings, Lockets, and Other Men's Jewellery.
   - Search, sorting, and lightweight filtering where useful.
   - Pagination or controlled loading to protect performance.
   - Category records managed by the admin rather than hard-coded in the frontend.

3. **Product detail page**
   - Multiple product images with mobile-friendly gallery behavior.
   - Product name, current price, previous price/discount, description, specifications, variations, stock, delivery information, reviews, and clear purchase actions.
   - Variation selection must affect the selected SKU, price, availability, and image when configured.
   - Invalid, inactive, or out-of-stock products must not be orderable.

4. **Cart**
   - Product/variation, quantity, unit price, discount, subtotal, delivery estimate, and total.
   - Quantity controls and removal.
   - Server revalidation before checkout.
   - Empty-cart state with a path back to shopping.

5. **Guest checkout / direct order form**
   - `Order Now` opens the same validated checkout flow with one selected product.
   - Cart checkout supports multiple products.
   - No mandatory registration or account creation.
   - Only necessary information is requested.

6. **Order success page**
   - Confirmation message, order reference, summary, delivery charge, total, and next step: the business will call to confirm the order.
   - Do not expose sensitive admin or internal shipment information.

### 4.2 Checkout fields and validation

Mandatory fields:

- Full name.
- Bangladesh mobile number.
- Complete delivery address.
- District selected from the controlled list of 64 Bangladesh districts.
- Area/thana/upazila, as a selected or entered field appropriate to the chosen district.

Optional field:

- Email address.

Validation rules:

- Trim all input and reject blank or whitespace-only values.
- Require a reasonable full-name length and allow Bengali/English letters, spaces, and normal name punctuation without pretending that software can prove whether a name is genuinely proper.
- Normalize Bangladesh phone formats and accept only the agreed valid local format. Store the normalized value consistently for duplicate detection.
- Reject unsupported characters, impossible lengths, and malformed email addresses.
- Require a meaningful complete address, not only a district or a short repeated string.
- Require a district from the server-controlled dataset; never trust a district label submitted by the browser.
- Display field-level errors beside the relevant field and prevent submission until required fields pass client and server validation.
- Revalidate all fields on the backend before creating the order.

OTP verification may be added after the basic release if fake-order rates justify its cost and customer-friction trade-off. The initial implementation should keep the phone-validation boundary ready for OTP without making OTP a hidden dependency.

### 4.3 Delivery-charge calculation

The initial Bangladesh rules will be:

- Inside Dhaka: **৳60**.
- Outside Dhaka: **৳120**.

The delivery-zone mapping and amounts must be stored in configurable backend data, not scattered through frontend components. The checkout response will show:

- Product subtotal.
- Discount, if applicable.
- Delivery charge.
- Final total.

The backend will recalculate the final total when the order is submitted. Admins must be able to change delivery rules later without a frontend redeploy, while every order retains a snapshot of the applied charge and location at order time.

## 5. Order lifecycle and business workflow

### 5.1 Customer order lifecycle

The initial order statuses will be:

`New Order → Pending Confirmation → Confirmed → Processing → Shipped → Delivered`

Alternative terminal or exceptional statuses:

- `Cancelled`
- `Rejected / Suspicious` where a separate business decision is needed
- `Pathao Pending / Manual Shipment Required` as a shipment state, not a replacement for the commercial order status

Order status and shipment status should be separate fields. This prevents a Pathao API failure from incorrectly changing a confirmed order into a cancelled order.

### 5.2 Recommended status rules

- A newly submitted order is recorded as `New Order` and immediately enters the admin queue.
- Staff reviews the information and calls the customer.
- A successful phone confirmation changes the order to `Confirmed`.
- Only a confirmed order can be sent to Pathao.
- Processing, shipped, and delivered updates are controlled by the admin and may later be synchronized from Pathao if a reliable webhook/API capability is available.
- Cancellation records a reason and the actor who made the change.
- Every status transition is recorded in an order-history table with timestamp and actor.

## 6. Fake-order prevention and customer history

The system will reduce fake orders while keeping legitimate checkout short.

### 6.1 Suspicious-order signals

At order creation and in the admin panel, flag signals such as:

- Multiple orders from the same normalized phone number in a short period.
- Multiple orders from the same address.
- Repeated orders with unusual frequency.
- Previous cancellations, rejected orders, or failed confirmations for the same phone number.
- Missing or low-quality address information.
- Repeated browser/session signals where privacy and legal requirements allow their use.

These signals should create a review flag or risk score, not automatically block every matching customer. Admins need a clear reason for each flag and the ability to mark the result as reviewed.

### 6.2 Admin customer view

Each order will show:

- Customer name, normalized phone, and email.
- Full address, district, and area/thana/upazila.
- Order date and time.
- Current order and shipment statuses.
- Previous order count and relevant history for the same phone number.
- Suspicious/duplicate indicators and review notes.

Customer information must be access-controlled and must not be exposed in public APIs or analytics payloads unnecessarily.

## 7. Pathao integration

### 7.1 Target workflow

`Website Order → Phone Confirmation → Confirm Order → Send to Pathao → Shipment Created`

The admin order detail page will provide a **Send to Pathao / Create Shipment** action only when the order is eligible and contains the required shipment data.

### 7.2 Integration design

The Pathao adapter will:

- Keep credentials and environment selection in server-side environment variables.
- Authenticate through the official Pathao API flow supported by the account.
- Map the internal order to the required Pathao customer, address, area, order reference, item, and COD fields.
- Store the external shipment/consignment ID and request result.
- Use an internal idempotency key so retries do not create duplicate shipments.
- Record request time, response status, sanitized error details, and the admin actor.
- Use bounded retries for transient errors and avoid retrying known validation/authentication failures blindly.
- Prevent a second shipment creation for an order that already has a successful external ID unless an explicit recovery action is supported.

Before implementation, the exact Pathao API documentation, merchant account permissions, sandbox availability, required location identifiers, COD rules, and webhook capabilities must be verified. The adapter should isolate these provider-specific details from the order domain so another courier can be added later.

### 7.3 Failure and manual fallback

No order may disappear because Pathao is unavailable or rejects a request.

On failure:

- Keep the website order intact.
- Mark the shipment as `Pathao Pending / Manual Shipment Required`.
- Show the order in a dedicated admin queue.
- Display a concise operator-readable failure reason while keeping secrets and tokens out of the message.
- Allow the admin to correct missing data and retry safely.
- Allow a manual shipment reference/notes to be recorded when the shipment is created outside the integration.
- Keep an audit trail of every attempt and resolution.

## 8. Admin panel

### 8.1 Dashboard

The dashboard will show counts and useful shortcuts for:

- Total orders.
- New orders.
- Pending confirmation.
- Confirmed orders.
- Pathao pending/manual shipment required.
- Processing, shipped, delivered, and cancelled orders.
- Total sales based on a clearly defined reporting rule.
- Low-stock and out-of-stock products.

Dashboard figures must use consistent date, status, and refund/cancellation rules. The reporting definition should be documented before sales totals are displayed.

### 8.2 Product management

Admins will be able to:

- Create, edit, archive/deactivate, and remove products according to safe deletion rules.
- Manage names, slugs, descriptions, specifications, prices, discounts, images, stock, categories, and variations.
- Add sizes, colors, or other variation attributes.
- Set active/inactive and featured/new-arrival/best-seller presentation flags.
- Preview the public product page before activation.

Products referenced by existing orders should be archived or deactivated rather than physically deleted. Order lines must retain snapshots of product name, SKU/variation, unit price, discount, and quantity.

### 8.3 Order management

Admins will be able to:

- Search and filter by order ID, phone number, status, date, district, and Pathao state.
- Open a complete order detail view.
- Change order status according to allowed transitions.
- Add internal notes and confirmation outcome.
- Confirm/cancel orders with a reason.
- Send eligible confirmed orders to Pathao.
- Review failed shipment attempts and manual-shipment queue.
- View order history and audit events.

At least one protected admin role is required for launch. A future role/permission model should allow separate staff capabilities without rewriting the order rules.

## 9. Data model

The initial relational model should include, at minimum:

- `admins`, `admin_sessions` or the framework's equivalent.
- `categories`.
- `products`.
- `product_variants` / SKUs.
- `product_images`.
- `inventory_movements` or an equivalent stock ledger.
- `districts` and delivery-zone configuration.
- `carts` and `cart_items` if server-side cart persistence is used.
- `orders`.
- `order_items` with immutable purchase snapshots.
- `order_status_history`.
- `customers` or normalized customer references for repeat-order lookup.
- `fraud_flags` / review records.
- `shipment_attempts` and shipment provider references.
- `reviews` if reviews are enabled in the first release.
- `site_settings` / configurable storefront content where appropriate.
- `analytics_event_log` only if a server-side event log is actually needed; do not store unnecessary personal data.

Important constraints:

- Unique slugs and stable product/SKU identifiers.
- Decimal-safe monetary values with an explicit currency code, initially `BDT`.
- Foreign keys and indexes for order phone lookup, status/date filtering, shipment state, product slug, and active catalog queries.
- Transactional order creation so stock, order lines, totals, and audit records cannot be partially saved.
- A clear stock policy for checkout and cancellation to prevent overselling.

## 10. Analytics, SEO, and performance

### 10.1 Tracking readiness

The frontend will define a consistent event layer for:

- `view_item` / View Content.
- `add_to_cart`.
- `begin_checkout` / Initiate Checkout.
- `purchase`.

The event layer will be provider-neutral so Meta Pixel, Meta Conversion API, Google Analytics, and Google Ads can be connected without coupling product components to one vendor. Purchase events must be deduplicated if browser and server-side events are both enabled. Consent and applicable privacy requirements must be respected.

### 10.2 SEO

The first release will include:

- Stable, readable product and category URLs.
- Per-page title and meta description support.
- Canonical URLs where needed.
- Open Graph/social sharing metadata.
- Product structured data with accurate price and availability.
- XML sitemap.
- Robots configuration.
- Crawlable server-rendered Nemesis views with Svelte-enhanced interactions.
- Custom 404 and useful error pages.

### 10.3 Performance

- Responsive image sizes, modern formats where supported, compression, and lazy loading below the fold.
- Small, focused client-side bundles.
- Paginated catalog queries.
- Backend indexes and controlled API payloads.
- Caching for stable catalog/configuration data with safe invalidation after admin changes.
- Production HTTPS, secure headers, rate limiting for checkout/admin endpoints, and appropriate CSRF/session protections.
- No secrets, private customer data, or raw provider responses in browser bundles or public logs.

## 11. Security and operational safeguards

- Validate and authorize every admin endpoint server-side.
- Hash admin passwords using the framework's secure mechanism and protect login attempts with rate limiting.
- Use secure, HTTP-only cookies where session authentication is selected.
- Validate uploaded image type, size, dimensions, and storage name; never execute uploaded files.
- Escape rendered customer/product content and protect against XSS, SQL injection, CSRF, and unsafe redirects.
- Keep Pathao, mail, SMS, analytics, and database credentials in the active environment only.
- Add privacy-conscious retention and access rules for phone numbers, addresses, and order history.
- Back up the production database before major releases and test restoration before relying on backups.
- Add structured application logs and error monitoring without logging full phone numbers, addresses, tokens, or secrets.

## 12. Phased delivery plan

### Phase 0 — Discovery and technical verification

- Confirm Nemesis framework installation, supported runtime, database, authentication, file storage, and API conventions.
- Confirm Nemesis hosting/runtime and the integrated Vite/Svelte asset-serving requirements.
- Verify the official Pathao API contract, merchant access, sandbox/live credentials, location data, and COD requirements.
- Confirm initial payment policy, assumed to be cash on delivery unless the business specifies otherwise.
- Confirm hosting, domain, email/SMS provider, image storage, backup, and analytics accounts.
- Convert this plan into accepted data, API, and UI contracts.

**Exit criteria:** technology choices and external dependencies are verified; no provider-specific assumption remains undocumented.

### Phase 1 — Foundation

- Create the backend project with `composer create-project jarir/nemesis-framework`.
- Initialize the integrated Vite/Svelte/Tailwind foundation inside the Nemesis project.
- Establish environment configuration, API versioning, database migrations, formatting, linting, and basic CI checks.
- Implement the shared design tokens, responsive shell, navigation, cart affordance, and error/loading states.
- Add secure admin authentication foundation.

**Exit criteria:** clean development environment, integrated Nemesis/Svelte application, protected admin route, and reproducible setup documentation.

### Phase 2 — Catalog and storefront

- Implement categories, products, variants, images, prices, discounts, stock, slugs, and catalog APIs.
- Build homepage, category listings, product detail pages, responsive image galleries, and basic search/filtering.
- Build the admin product-management screens.
- Add seed/demo data without using fake production claims.

**Exit criteria:** an admin can manage an active catalog and a customer can browse the catalog successfully on mobile and desktop.

### Phase 3 — Cart and checkout

- Implement cart and direct `Order Now` flow.
- Implement checkout form, district dataset, area/thana/upazila handling, phone normalization, address validation, and delivery calculation.
- Add backend price/stock revalidation and transactional order creation.
- Add order-success page and admin new-order queue.
- Add validation and duplicate/suspicious-order flags.

**Exit criteria:** valid orders can be placed through both journeys; invalid/incomplete orders cannot be saved; totals and delivery fees are correct on the server.

### Phase 4 — Order operations and fraud review

- Implement order detail pages, allowed status transitions, confirmation notes, status history, search/filtering, and customer previous-order history.
- Implement dashboard counts and reporting definitions.
- Implement fraud review queue and admin review outcomes.
- Add stock reservation/release behavior and cancellation handling.

**Exit criteria:** staff can process an order from submission through phone confirmation and operational status updates without database or audit gaps.

### Phase 5 — Pathao integration

- Implement the isolated Pathao provider adapter.
- Add eligibility checks, request mapping, authentication, idempotency, sanitized logging, retry policy, and shipment persistence.
- Add Send to Pathao action, success state, failure state, retry path, and manual-shipment queue.
- Validate with Pathao sandbox/test credentials before live credentials.

**Exit criteria:** a confirmed test order creates one shipment; repeated clicks do not duplicate it; failed requests remain visible and recoverable.

### Phase 6 — SEO, analytics, performance, and content

- Add metadata, structured data, sitemap, robots, social preview, and 404 pages.
- Implement the provider-neutral conversion event layer.
- Optimize images, queries, bundle size, caching, and Core Web Vitals.
- Add policy/contact/help content and real brand assets.

**Exit criteria:** public pages are crawlable, tracking events are verifiable without leaking personal data, and performance meets agreed thresholds on mobile.

### Phase 7 — Quality assurance and launch

- Run unit tests for validation, pricing, delivery rules, status transitions, fraud signals, stock, and Pathao mapping.
- Run API/integration tests for checkout, admin authorization, order lifecycle, and shipment retry/idempotency.
- Run responsive browser checks across mobile, tablet, and desktop breakpoints.
- Test empty, invalid, out-of-stock, duplicate-click, API-failure, and network-retry cases.
- Perform accessibility, security, SEO, and performance checks.
- Back up production, deploy through the approved process, smoke-test the live site, and verify the exact live checkout and shipment behavior.

**Exit criteria:** all release acceptance criteria pass, rollback/backup steps are documented, and live verification is recorded separately from local build/test results.

## 13. Testing strategy

### Unit tests

- Bangladesh phone normalization and validation.
- Name, email, and complete-address validation.
- District and delivery-zone mapping.
- Discount, subtotal, delivery charge, and final-total calculations.
- Order-status transition rules.
- Suspicious-order signal calculation.
- Stock reservation and release rules.
- Pathao payload mapping and idempotency-key generation.

### Integration/API tests

- Product and category administration.
- Guest direct order and cart checkout.
- Server-side total and stock revalidation.
- Admin authorization and audit history.
- Duplicate/suspicious-order lookup.
- Pathao success, validation failure, authentication failure, timeout, retry, and duplicate-submit scenarios.

### Browser and manual QA

- Homepage-to-order journey on Android-sized, iPhone-sized, tablet, and desktop viewports.
- Keyboard navigation and visible validation errors.
- Slow network and image-loading behavior.
- Out-of-stock and inactive product behavior.
- Cart refresh, back-button, repeated-submit, and refresh-after-success behavior.
- Admin workflow from new order to confirmed shipment and manual fallback.

## 14. Release acceptance criteria

The first release is ready only when:

- The site presents a consistent premium black/gold/white brand on mobile and desktop.
- Customers can browse products, use `Order Now`, or use cart checkout without registering.
- Required name, phone, complete address, district, and area/thana/upazila information is validated on both client and server.
- Invalid or incomplete requests cannot create orders.
- Inside-Dhaka and outside-Dhaka charges are calculated correctly and stored with the order.
- Prices, discounts, stock, and totals are recalculated server-side.
- New orders appear in the admin panel with complete customer and order details.
- Staff can record phone confirmation and control order status through the defined lifecycle.
- Repeated or suspicious orders are visible for review with useful reasons.
- Only confirmed orders can be sent to Pathao.
- A successful Pathao request is idempotent and stores the external shipment reference.
- A failed Pathao request remains in a visible manual-shipment queue and can be safely retried or completed manually.
- Product management is usable without developer assistance.
- SEO basics, conversion events, HTTPS, secure admin access, and image performance checks pass.
- Automated tests, manual QA, deployment checks, and live smoke tests are all recorded as separate evidence.

## 15. Decisions to confirm before implementation

These items are not fully specified in the source brief and must be confirmed during Phase 0:

1. Whether the initial release is COD-only or also requires an online payment gateway.
2. Exact Nemesis hosting/runtime and integrated Vite asset-serving arrangement.
3. Production database engine, image storage, backup destination, and monitoring provider.
4. Official Pathao account/API access, sandbox availability, and shipment-status synchronization scope.
5. Whether OTP verification is required at launch or added after observing fake-order rates.
6. Exact area/thana/upazila dataset and whether the user should select from a structured hierarchy or enter the area manually.
7. Admin users, roles, and who may confirm orders, cancel orders, or create shipments.
8. Brand assets, product photography, reviews, social links, contact information, and policy content.
9. Sales-reporting rules for cancelled orders, discounts, delivery fees, and returned shipments.
10. Launch domain, email/SMS notification policy, and customer-facing order confirmation channels.

## Final scope statement

The project will deliver a fast, premium, mobile-first Bangladesh jewellery storefront with a guest-first checkout, reliable order validation, simple admin operations, and recoverable Pathao automation. The architecture will preserve room for international shipping, multiple currencies, online payments, additional courier providers, OTP verification, and richer customer accounts without allowing those future features to complicate the first release.
