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
