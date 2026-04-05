# Hi.Events Enhancement Summary

> **Branch:** `feature/enhancements-batch`  
> **Date:** April 5, 2026  
> **Total Issues Implemented:** 8 (of 9 requested — #191 skipped as maintainer is actively working on it)

---

## Completed Enhancements

### #714 — Automatic Sales Report Emails

Organizers can now receive periodic email summaries of their event's sales performance.

**What's new:**
- Configurable frequency: Daily, Weekly, or Monthly
- Multiple recipient email addresses per event
- Reports include: orders, tickets sold, attendees, gross revenue, tax, fees, refunds, abandoned orders, page views
- Daily breakdown table for granular insight
- Scheduled job runs every 15 minutes, sends at the start of each period window (midnight UTC)

**Files:**
- `Mail/Organizer/SalesReportMail.php` — Queued mail class
- `Jobs/Event/SendSalesReportsJob.php` — Scheduler job with frequency logic
- `Enums/SalesReportFrequency.php` — DAILY / WEEKLY / MONTHLY
- `views/emails/organizer/sales-report.blade.php` — Markdown email template
- Migration `000025` — `sales_report_frequency`, `sales_report_recipient_emails` on `event_settings`

---

### #712 — Site-wide Voucher Codes

Account-level promo codes that work across all events, not just one.

**What's new:**
- Vouchers are promo codes with `event_id = null` and `account_id` set
- Full CRUD via `/vouchers` API endpoints (account-scoped, no event required)
- `isSiteWide()` helper method on `PromoCodeDomainObject`
- Repository methods: `findByAccountId()`, `findSiteWideByCode()`

**Files:**
- `Actions/Vouchers/` — Create, Get, Delete actions
- `Services/Domain/PromoCode/CreateSiteWideVoucherService.php`
- Migration `000026` — adds `account_id` to `promo_codes`, makes `event_id` nullable
- Frontend: `voucher.client.ts` API client, `PromoCode` type extended with `account_id`

---

### #330 — Certificate of Attendance

Attendees who have checked in can download a PDF certificate of attendance.

**What's new:**
- Per-event certificate settings: enable/disable, custom title, body template with variable placeholders, signatory name & title
- PDF generation via `barryvdh/laravel-dompdf` — A4 landscape with decorative border
- Template variables: `{{ attendee_name }}`, `{{ event_title }}`, `{{ event_date }}`, `{{ certificate_id }}`
- Public download endpoint (no auth required, validates check-in status)

**Files:**
- `Actions/Certificates/DownloadCertificateAction.php`
- `Services/Domain/Certificate/GenerateCertificateService.php`
- `views/certificates/attendance.blade.php` — HTML/CSS certificate template
- Migration `000027` — certificate settings on `event_settings`
- Frontend: `certificate.client.ts` API client

---

### #698 — Custom Document Templates

A Liquid-based template system for certificates, receipts, badges, and custom documents.

**What's new:**
- Account-scoped template library with optional event association
- Template types: CERTIFICATE, RECEIPT, BADGE, CUSTOM
- Full CRUD API endpoints at `/document-templates`
- Liquid template content with JSON settings

**Files:**
- `Actions/DocumentTemplates/` — Create, GetAll, GetOne, Update, Delete actions
- `Repository/` — `DocumentTemplateRepository` + interface
- `Resources/DocumentTemplate/DocumentTemplateResource.php`
- `Models/DocumentTemplate.php`, `DomainObjects/DocumentTemplateDomainObject.php`
- Migration `000028` — `document_templates` table
- Frontend: `document-template.client.ts` API client, `DocumentTemplate` type

---

### #361 — Ticket Packages / Bundles

Group multiple products into discounted bundles for sale.

**What's new:**
- Bundles with: name, description, price, currency, quantity limits, sale date windows, active toggle, sort order
- Bundle items link to products and specific price tiers with quantities
- Organizer CRUD at `/events/{id}/bundles`
- Public read endpoint for active bundles on the event widget

**Files:**
- `Actions/ProductBundles/` — Create, GetAll, Update, Delete, GetPublic actions
- `Repository/` — `ProductBundleRepository` + interface
- `Resources/ProductBundle/ProductBundleResource.php`
- `Models/ProductBundle.php`, `ProductBundleItem.php`
- `DomainObjects/ProductBundleDomainObject.php`
- Migration `000029` — `product_bundles` + `product_bundle_items` tables
- Frontend: `product-bundle.client.ts` API client, `ProductBundle` / `ProductBundleItem` types

---

### #234 — Upselling Capabilities

Flag products as upsells and show them contextually during checkout based on cart contents.

**What's new:**
- Three new fields on products: `is_upsell`, `upsell_for_product_ids` (JSON), `upsell_display_text`
- Public endpoint `/events/{id}/products/upsells?product_ids=1,2,3` filters upsells by selected products
- Upsells with no target restriction show for all product selections
- Validation rules and DTO support for creating/editing upsell products
- Product resource includes upsell fields in API responses

**Files:**
- `Actions/Products/GetUpsellProductsPublicAction.php`
- Modified: `UpsertProductDTO`, `UpsertProductRequest`, `ProductResource`, `Product` model (casts)
- Migration `000030` — `is_upsell`, `upsell_for_product_ids`, `upsell_display_text` on `products`
- Frontend: `upsell.client.ts` API client, `Product` type extended with upsell fields

---

### #335 — Provisional Reservation / Book Now, Pay Later

High-value orders can be provisionally reserved with an extended deadline before payment is required.

**What's new:**
- New `PROVISIONAL` order status added to the order lifecycle
- Per-event settings: enable/disable, price threshold (orders above this get provisional status), deadline in hours, custom message
- Service layer: `markAsProvisional()`, `confirmProvisionalOrder()`, `cancelExpiredProvisionalOrders()`
- Organizer endpoints to list and confirm provisional orders

**Files:**
- `Services/Domain/Order/ProvisionalReservationService.php`
- `Actions/Orders/GetProvisionalOrdersAction.php`, `ConfirmProvisionalOrderAction.php`
- Modified: `OrderStatus.php` — added `PROVISIONAL` case
- Migration `000031` — provisional booking settings on `event_settings`
- Frontend: `Order` status type extended with `'PROVISIONAL'`

---

### #747 — Multi-step Checkout Wizard

Break the checkout flow into configurable steps instead of a single long page.

**What's new:**
- Per-event toggle: `multi_step_checkout_enabled`
- JSON-based step configuration: each step has an ID, label, type (products/details/payment/confirmation), and optional product category filter
- Public endpoint `/events/{id}/checkout-config` for the widget to fetch step configuration
- Full validation rules and DTO support for saving step config via event settings

**Files:**
- `Actions/EventSettings/GetCheckoutConfigPublicAction.php`
- Migration `000032` — `multi_step_checkout_enabled`, `checkout_steps_config` on `event_settings`
- Frontend: `checkout-config.client.ts` API client, `CheckoutStepConfig` type

---

## Infrastructure Changes

| Area | Details |
|---|---|
| **Migrations** | 8 new migrations (`000025`–`000032`), all ran successfully |
| **Repositories** | 2 new repos registered in `RepositoryServiceProvider` (ProductBundle, DocumentTemplate) |
| **Scheduler** | `SendSalesReportsJob` added to `Kernel.php` (every 15 minutes) |
| **Models** | 3 new models (DocumentTemplate, ProductBundle, ProductBundleItem) |
| **Domain Objects** | 4 new domain objects + 3 existing abstracts modified |
| **Routes** | 15+ new API routes (authenticated + public) |
| **Event Settings** | 13 new settings fields across 4 features |

## Frontend Changes

| Area | Details |
|---|---|
| **Types** | `EventSettings` +13 fields, `Product` +3 fields, `PromoCode` +1 field, `Order` status +1 value, 5 new interfaces |
| **API Clients** | 6 new client files: voucher, product-bundle, document-template, certificate, upsell, checkout-config |

## Bug Fix

- Fixed `GetSiteWideVouchersAction` calling non-existent `paginatedResourceResponse()` — changed to `resourceResponse()`

---

## Skipped

| Issue | Reason |
|---|---|
| **#191 — Repeating/Recurring Events** | Maintainer `daveearley` commented 5 days ago that this is actively in development. Skipped to avoid merge conflicts. |

---

## Session 10 — Security & Authentication Enhancements

> **Date:** April 6, 2026  
> **Issues:** 4 implemented (#874, #338, #33, #32)  
> **Commit:** `408c3220`

### #874 — Rate Limiting & Brute-Force Protection

Enhanced API security with tiered rate limiting across all endpoints.

**What's new:**
- Tiered rate limits: auth (5/min), public write (10/min), public read (60/min), authenticated (120/min)
- Custom `RateLimitServiceProvider` with named limiters
- Rate limit headers in responses

### #338 — Two-Factor Authentication (TOTP)

TOTP-based 2FA for organizer accounts.

**What's new:**
- TOTP setup flow: generate secret → QR code → verify → enable
- Recovery codes for account recovery
- 2FA enforcement middleware for sensitive operations
- Encrypted secret storage

### #33 — Account Lockout After Failed Attempts

Automatic account lockout after excessive failed login attempts.

**What's new:**
- Configurable threshold (default 5 attempts)
- Progressive lockout duration (15min → 30min → 1hr)
- Lockout tracking in database with automatic expiry
- Admin can manually unlock accounts

### #32 — Password Policy Enforcement

Strong password requirements for all user accounts.

**What's new:**
- Minimum 8 characters, requires uppercase + lowercase + number + special character
- Password history prevention (last 5 passwords)
- Password strength meter guidance in validation messages

---

## Session 11 — Reports, Mobile, PWA & Advanced Features

> **Date:** April 6, 2026  
> **Issues:** 12 implemented (#744, #716, #244, #236, #189, #186, #1000, #998, #772, #759, #39, #589)

### #744 — Attendees by Ticket Type Report

SQL-based report showing attendee breakdown per product/ticket type with check-in status.

**What's new:**
- CTE-based SQL query joining attendees, orders, products, product_prices
- Includes check-in status via subquery on `attendee_check_ins`
- Registered as `ATTENDEES_BY_PRODUCT` in `ReportTypes` enum

**Files:**
- `Services/Domain/Report/Reports/AttendeesByProductReport.php`
- Modified: `ReportTypes.php`, `ReportServiceFactory.php`

---

### #716 — View Generated Invoices

Paginated listing of all generated invoices for an event.

**What's new:**
- Expanded `InvoiceResource` with nested order data (id, short_id, names, email, status)
- New repository method `findByEventId()` with eager-loaded order
- Paginated API endpoint: `GET /events/{event_id}/invoices`

**Files:**
- `Http/Resources/InvoiceResource.php` (expanded)
- `Repository/Interfaces/InvoiceRepositoryInterface.php` (added method)
- `Repository/Eloquent/InvoiceRepository.php` (implemented)
- `Http/Actions/Orders/GetEventInvoicesAction.php`
- `routes/api.php` (new route)

---

### #244 — Progressive Web App (PWA) Support

Full PWA support with service worker, offline fallback, and installability.

**What's new:**
- `vite-plugin-pwa` integration with Workbox runtime caching
- NetworkFirst for API calls, CacheFirst for images/fonts
- Offline fallback page with brand styling
- App shortcuts: "My Tickets" and "Manage Events"
- Auto-update service worker with hourly refresh
- Install prompt support

**Files:**
- `frontend/vite.config.ts` (VitePWA plugin)
- `frontend/public/site.webmanifest` (shortcuts)
- `frontend/public/offline.html` (new)
- `frontend/package.json` (`vite-plugin-pwa` dep)
- `frontend/src/utilites/pwa.ts` (new)
- `frontend/src/entry.client.tsx` (init PWA)
- `frontend/tsconfig.json` (types)

---

### #236 — Flutter Mobile App Scaffold

Full Flutter mobile app for event check-in with QR scanning.

**What's new:**
- Dio-based API client with auth interceptors
- Provider state management (Auth, Event, CheckIn)
- MobileScanner QR code scanning with overlay
- Material 3 dark theme matching Hi.Events brand (#CD58DD)
- Login, Events list, Scanner screens
- Secure token storage via flutter_secure_storage

**Files:**
- `mobile/` directory (11 files): `main.dart`, `models/models.dart`, `services/api_service.dart`, `providers/auth_provider.dart`, `providers/event_provider.dart`, `providers/checkin_provider.dart`, `screens/login_screen.dart`, `screens/events_screen.dart`, `screens/scanner_screen.dart`, `pubspec.yaml`, `README.md`

---

### #189 — Mobile Wallet Integration

Apple Wallet & Google Wallet pass generation for attendee tickets.

**What's new:**
- Apple Wallet: eventTicket pass type, QR barcode, location geofencing, relevant date
- Google Wallet: save URL with JWT, eventTicketClass/Object, venue info
- Platform-selectable endpoint: `?platform=apple|google`

**Files:**
- `Services/Domain/Wallet/AppleWalletPassService.php`
- `Services/Domain/Wallet/GoogleWalletPassService.php`
- `Http/Actions/Attendees/GetAttendeeWalletPassAction.php`
- `routes/api.php` (new route)

---

### #186 — QR Code Printing

Generate printable PDF sheets of attendee QR codes as labels.

**What's new:**
- Configurable label size (default 30x20mm) and columns (default 3)
- Optional attendee ID filtering
- DomPDF-generated PDF with table-cell grid layout
- QR images via qrserver.com API

**Files:**
- `Http/Actions/Attendees/PrintAttendeeQrCodesAction.php`
- `resources/views/qr-codes/attendee-labels.blade.php`
- `routes/api.php` (new route)

---

### #1000 — Subscribable ICS Calendar Feed

Per-event ICS calendar feed with auto-refresh support.

**What's new:**
- Public endpoint: `GET /events/{event_id}/event.ics`
- 60-minute refresh interval via `X-WR-REFRESH`
- Confirmed event status, productIdentifier
- No-cache headers for subscription clients

**Files:**
- `Http/Actions/Events/GetEventIcsAction.php`
- `routes/api.php` (public route)

---

### #998 — Federation / ActivityPub Support

ActivityPub discovery for organizers and event outboxes.

**What's new:**
- Organizer → ActivityPub Actor (Organization type, inbox/outbox/followers)
- Events → ActivityPub Event objects with location/Place
- Create activity wrapping for outbox
- WebFinger discovery at `/.well-known/webfinger`
- JSON-LD with `application/activity+json` content type

**Files:**
- `Services/Domain/Federation/ActivityPubTransformer.php`
- `Http/Actions/Federation/GetFederatedOrganizerAction.php`
- `Http/Actions/Federation/GetFederatedOrganizerOutboxAction.php`
- `Http/Actions/Federation/WebFingerAction.php`
- `routes/api.php` (public + root routes)

---

### #772 — Subscribe to Future Events

Email subscription system for organizer's future events.

**What's new:**
- `event_subscribers` table with organizer scoping, email, token, confirmation tracking
- Public subscribe endpoint with rate limiting (10/min)
- Token-based unsubscribe
- Authenticated subscriber listing with pagination
- Duplicate email prevention per organizer

**Files:**
- Migration `000037` — `event_subscribers` table
- `Models/EventSubscriber.php`
- `DomainObjects/EventSubscriberDomainObject.php` + Generated abstract
- `Repository/` — `EventSubscriberRepository` + interface
- `Http/Actions/Subscribers/SubscribeToOrganizerPublicAction.php`
- `Http/Actions/Subscribers/UnsubscribeAction.php`
- `Http/Actions/Subscribers/GetOrganizerSubscribersAction.php`
- `Providers/RepositoryServiceProvider.php` (binding)
- `routes/api.php` (3 new routes)

---

### #759 — Abandoned Checkout Recovery

Automated recovery emails for abandoned orders with optional promo code incentive.

**What's new:**
- `abandoned_order_recoveries` tracking table
- Event settings: enable flag, delay minutes (default 60), max emails (default 2)
- Queued recovery email with CTA "Complete Your Order" button
- Scheduled job: finds abandoned orders past delay, sends recovery emails with 24h cooldown
- Upsert-based tracking (no duplicate recovery records)

**Files:**
- Migration `000038` — `abandoned_order_recoveries` table + event_settings columns
- `Mail/Order/AbandonedCheckoutRecoveryMail.php`
- `resources/views/emails/orders/abandoned-checkout-recovery.blade.php`
- `Jobs/SendAbandonedCheckoutRecoveryEmailsJob.php`

---

### #39 — Seating Charts

Full venue seating chart system with interactive seat management.

**What's new:**
- Three-table schema: `seating_charts` → `seating_sections` → `seats`
- Section shapes: rectangle, arc, circle with JSON position data
- Seat statuses: available, reserved, held, sold, disabled
- Auto-generated seat labels (A1, A2, B1, B2...) from row/column configuration
- Accessibility flags and aisle seat identification
- Per-seat price overrides and product association
- Public seat availability endpoint for interactive seat maps
- Atomic seat assignment with optimistic locking (status check in WHERE clause)

**Files:**
- Migration `000039` — 3 tables with foreign keys, composite unique constraints
- `Models/SeatingChart.php`, `SeatingSection.php`, `Seat.php`
- `DomainObjects/` — 3 domain objects + 3 Generated abstracts
- `Repository/` — `SeatingChartRepository`, `SeatRepository` + interfaces
- `Http/Actions/SeatingCharts/` — CreateSeatingChartAction, GetSeatingChartsAction, GetSeatingChartAction, AssignSeatAction, GetSeatAvailabilityPublicAction
- `Providers/RepositoryServiceProvider.php` (2 new bindings)
- `routes/api.php` (4 auth + 1 public route)

---

### #589 — Better Hybrid Events

Per-product attendance mode with distinct connection details for in-person, online, and hybrid tickets.

**What's new:**
- Products get `attendance_mode` (IN_PERSON / ONLINE / HYBRID), `online_meeting_url`, `venue_instructions`
- Event settings get `hybrid_stream_url`, `hybrid_venue_instructions` as defaults
- `HybridEventService` resolves correct connection details per product mode with fallback chain
- Public endpoint for attendees to get their ticket-type-specific connection details

**Files:**
- Migration `000040` — adds columns to `products` and `event_settings`
- `Services/Domain/Event/HybridEventService.php`
- `Http/Actions/Events/GetEventConnectionDetailsPublicAction.php`
- Modified: `ProductDomainObjectAbstract.php` (+3 constants, +3 properties, +3 getter/setters)
- Modified: `EventSettingDomainObjectAbstract.php` (+2 constants, +2 properties, +2 getter/setters)
- `routes/api.php` (new public route)

---

## Session 11 Infrastructure Changes

| Area | Details |
|---|---|
| **Migrations** | 4 new migrations (`000037`–`000040`) |
| **Repositories** | 3 new repos registered (EventSubscriber, SeatingChart, Seat) |
| **Models** | 4 new models (EventSubscriber, SeatingChart, SeatingSection, Seat) |
| **Domain Objects** | 6 new domain objects + 3 Generated abstracts + 2 existing abstracts modified |
| **Routes** | 20+ new API routes (authenticated + public + root) |
| **Actions** | 15+ new action classes across 6 feature areas |
| **Services** | 4 new services (AppleWallet, GoogleWallet, HybridEvent, ActivityPubTransformer) |
| **Frontend/Mobile** | PWA support (7 files), Flutter app scaffold (11 files) |
| **Jobs** | 1 new scheduled job (SendAbandonedCheckoutRecoveryEmailsJob) |
| **Mail** | 1 new mail class (AbandonedCheckoutRecoveryMail) |

---

## Session 12 — Tier 2 High Priority Features (Competitive Advantage)

> **Date:** April 6, 2026  
> **Features:** 6 implemented, 1 verified existing (#7 Seating Chart Builder, #8 Gift Cards, #9 Memberships, #10 POS, #11 Bulk Import, #12 Google Maps, #13 Social Sharing already existed)

### #7 — Visual Seating Chart Builder (Frontend)

Interactive canvas-based seating chart builder for the existing backend seating chart system (Session 11, #39).

**What's new:**
- List view showing existing seating charts as cards with name, status badge, seat count
- Full canvas-based builder with drag-and-drop section placement
- Pan/zoom controls (0.3x–2.5x) with grid background
- Section sidebar with property editor (name, color, rows, seats/row, shape)
- "Add Section" modal with color picker (10 swatches), row/seat configuration, shape selection
- Live seat preview rendering (rows A–Z with numbered seats, tooltips)
- Total seats/sections summary badges
- Canvas interactions: mouse handlers for panning and section dragging

**Files:**
- `frontend/src/api/seating-chart.client.ts` — API client with types and CRUD methods
- `frontend/src/queries/useGetSeatingCharts.ts` — React Query hooks for listing/getting charts
- `frontend/src/mutations/useCreateSeatingChart.ts` — Mutation hook for creating charts
- `frontend/src/components/routes/event/SeatingCharts/SeatingCharts.module.scss` — ~280 line SCSS module
- `frontend/src/components/routes/event/SeatingCharts/index.tsx` — ~470 line builder component
- Modified: `frontend/src/router.tsx` — Added `seating-charts` route
- Modified: `frontend/src/components/layouts/Event/index.tsx` — Added nav item with `IconArmchair`

---

### #8 — Gift Cards / Gift Vouchers

Full gift card system with batch creation, redemption, and balance tracking.

**What's new:**
- Gift cards with unique codes, initial/remaining balance, currency, expiry dates
- Batch creation: specify quantity and all cards are generated with UUID codes
- Atomic redemption with balance validation (uses DB transaction)
- Usage tracking: every redemption logged with order association
- Public endpoints for redeeming and checking balance by code

**Files:**
- Migration `000041` — `gift_cards` + `gift_card_usages` tables
- `Models/GiftCard.php`, `GiftCardUsage.php`
- `DomainObjects/GiftCardDomainObject.php` + Generated abstract
- `DomainObjects/GiftCardUsageDomainObject.php` + Generated abstract
- `Repository/Eloquent/GiftCardRepository.php` + Interface
- `Actions/GiftCards/` — CreateGiftCardAction (batch support), GetGiftCardAction, GetGiftCardsAction, UpdateGiftCardAction, RedeemGiftCardAction, CheckGiftCardBalanceAction
- Routes: 4 authenticated (CRUD) + 2 public (redeem, check-balance)

---

### #9 — Memberships & Season Passes

Membership plans with recurring access to events and attendee membership management.

**What's new:**
- Membership plans: name, description, price, currency, duration, renewal type (manual/auto), max events, benefits list
- Individual memberships linked to plans with status tracking (active/expired/cancelled/suspended)
- Event access control: plans can specify which events members can access
- Membership validation endpoint for checking access to specific events
- Full lifecycle management: activate, cancel, expire, renew

**Files:**
- Migration `000042` — `membership_plans` + `memberships` + `membership_event_access` tables
- `Models/MembershipPlan.php`, `Membership.php`, `MembershipEventAccess.php`
- `DomainObjects/MembershipPlanDomainObject.php` + Generated abstract
- `DomainObjects/MembershipDomainObject.php` + Generated abstract
- `Repository/Eloquent/MembershipPlanRepository.php` + Interface
- `Repository/Eloquent/MembershipRepository.php` + Interface
- `Actions/Memberships/` — CreateMembershipPlanAction, GetMembershipPlansAction, UpdateMembershipPlanAction, CreateMembershipAction, GetMembershipsAction, CancelMembershipAction, ValidateMembershipPublicAction
- Routes: 6 authenticated (CRUD plans + memberships) + 1 public (validate)

---

### #10 — In-Person Sales / Point of Sale (POS)

POS session management for on-site ticket and merchandise sales with Stripe Terminal support.

**What's new:**
- POS sessions: open/close with automatic total calculation
- Transactions within sessions: product, quantity, unit price, payment method (cash/card/terminal)
- Atomic total updates on session close via DB transaction
- Stripe Terminal connection token generation for card reader integration
- Session-level reporting with transaction counts and totals

**Files:**
- Migration `000043` — `pos_sessions` + `pos_transactions` tables
- `Models/PosSession.php`, `PosTransaction.php`
- `DomainObjects/PosSessionDomainObject.php` + Generated abstract
- `DomainObjects/PosTransactionDomainObject.php` + Generated abstract
- `Repository/Eloquent/PosSessionRepository.php` + Interface
- `Actions/POS/` — OpenPosSessionAction, ClosePosSessionAction, GetPosSessionsAction, CreatePosTransactionAction, GetStripeTerminalTokenAction
- Routes: 5 authenticated (open, close, list, create transaction, terminal token)

---

### #11 — Bulk Attendee Import

CSV-based bulk import of attendees with validation and deduplication.

**What's new:**
- CSV parsing: supports file upload or inline CSV data
- Column mapping: first_name, last_name, email, product_id, product_price_id
- Row-level validation with error collection (missing fields, invalid emails, product not found)
- Duplicate email detection within import batch
- Returns success count, error count, and detailed error messages per row

**Files:**
- `Services/Domain/Attendee/BulkAttendeeImportService.php` — CSV parsing, validation, dedup
- `Actions/Attendees/BulkImportAttendeesAction.php` — File upload or inline CSV handling
- Routes: 1 authenticated (POST bulk-import)

---

### #12 — Google Maps on Event Pages

Embedded Google Maps on event pages when venue coordinates are configured.

**What's new:**
- Venue latitude/longitude fields on event settings (decimal 10,7 precision)
- Toggle to enable/disable map display on public event page
- Google Maps embed using free URL format (no API key required)
- Admin UI: NumberInputs for coordinates with bounds validation, Switch toggle
- Frontend: conditional rendering — Google Maps iframe when coordinates set, SVG placeholder fallback

**Files:**
- Migration `000044` — `venue_latitude`, `venue_longitude`, `show_map_on_event_page`, `maps_embed_type` on `event_settings`
- Modified: `DomainObjects/Generated/EventSettingDomainObjectAbstract.php` — 4 new constants, properties, toArray entries, getter/setters
- Modified: `frontend/src/types.ts` — 4 new fields on `EventSettings` interface
- Modified: `frontend/src/components/layouts/EventHomepage/index.tsx` — Google Maps iframe embed
- Modified: `frontend/src/components/routes/event/Settings/Sections/LocationSettings/index.tsx` — Admin form fields

---

### #13 — Social Sharing Tools + WhatsApp (Already Existed)

Verified that the existing `ShareModal` component already supports WhatsApp, Facebook, X (Twitter), LinkedIn, Telegram, Reddit, Pinterest, Email sharing with QR code generation and native Web Share API integration. No changes needed.

**Existing files:**
- `frontend/src/components/modals/ShareModal/index.tsx`
- `frontend/src/components/modals/ShareModal/ShareModal.module.scss`

---

## Session 12 Infrastructure Changes

| Area | Details |
|---|---|
| **Migrations** | 4 new migrations (`000041`–`000044`) |
| **Repositories** | 4 new repos registered in `RepositoryServiceProvider` (GiftCard, MembershipPlan, Membership, PosSession) |
| **Models** | 7 new models (GiftCard, GiftCardUsage, MembershipPlan, Membership, MembershipEventAccess, PosSession, PosTransaction) |
| **Domain Objects** | 6 new domain objects + 4 Generated abstracts + 1 existing abstract modified |
| **Routes** | ~20 new API routes (16 authenticated + 3 public) |
| **Actions** | 19 new action classes across 5 feature areas |
| **Services** | 1 new service (BulkAttendeeImportService) |
| **Frontend** | Seating chart builder (5 new files), Google Maps embed (3 files modified), types extended |
