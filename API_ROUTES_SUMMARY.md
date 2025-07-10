# Hi.Events API Routes Summary

## Authentication Routes
- `POST /auth/login` - User login
- `POST /auth/logout` - User logout
- `POST /auth/register` - User registration
- `POST /auth/forgot-password` - Request password reset
- `POST /auth/reset-password/{token}` - Reset password
- `POST /auth/refresh` - Refresh JWT token
- `GET /auth/invitation/{token}` - Get invitation details
- `POST /auth/invitation/{token}` - Accept invitation

## User Management
- `GET /users/me` - Get current user
- `PUT /users/me` - Update current user
- `POST /users` - Create user
- `GET /users` - List users
- `GET /users/{id}` - Get user
- `PUT /users/{id}` - Update user
- `POST /users/{id}/invitation` - Resend invitation
- `DELETE /users/{id}/invitation` - Delete invitation

## Account Management
- `GET /accounts/{id?}` - Get account
- `PUT /accounts/{id?}` - Update account
- `POST /accounts/{id}/stripe/connect` - Stripe connect

## Event Management
- `POST /events` - Create event
- `GET /events` - List events
- `GET /events/{id}` - Get event
- `PUT /events/{id}` - Update event
- `PUT /events/{id}/status` - Update event status
- `POST /events/{id}/duplicate` - Duplicate event

## Organizer Management
- `POST /organizers` - Create organizer
- `GET /organizers` - List organizers
- `GET /organizers/{id}` - Get organizer
- `POST /organizers/{id}` - Update organizer
- `GET /organizers/{id}/events` - Get organizer events

## Product Management
- `POST /events/{event_id}/products` - Create product
- `GET /events/{event_id}/products` - List products
- `GET /events/{event_id}/products/{id}` - Get product
- `PUT /events/{event_id}/products/{id}` - Update product
- `DELETE /events/{event_id}/products/{id}` - Delete product
- `POST /events/{event_id}/products/sort` - Sort products

## Product Category Management
- `POST /events/{event_id}/product-categories` - Create category
- `GET /events/{event_id}/product-categories` - List categories
- `GET /events/{event_id}/product-categories/{id}` - Get category
- `PUT /events/{event_id}/product-categories/{id}` - Update category
- `DELETE /events/{event_id}/product-categories/{id}` - Delete category

## Order Management
- `GET /events/{event_id}/orders` - List orders
- `GET /events/{event_id}/orders/{id}` - Get order
- `PUT /events/{event_id}/orders/{id}` - Update order
- `POST /events/{event_id}/orders/{id}/cancel` - Cancel order
- `POST /events/{event_id}/orders/{id}/mark-as-paid` - Mark as paid
- `POST /events/{event_id}/orders/{id}/refund` - Refund order
- `POST /events/{event_id}/orders/{id}/message` - Message order
- `POST /events/{event_id}/orders/{id}/resend_confirmation` - Resend confirmation
- `POST /events/{event_id}/orders/export` - Export orders
- `GET /events/{event_id}/orders/{id}/invoice` - Download invoice

## Attendee Management
- `POST /events/{event_id}/attendees` - Create attendee
- `GET /events/{event_id}/attendees` - List attendees
- `GET /events/{event_id}/attendees/{id}` - Get attendee
- `PUT /events/{event_id}/attendees/{id}` - Update attendee
- `PATCH /events/{event_id}/attendees/{id}` - Partial update attendee
- `POST /events/{event_id}/attendees/export` - Export attendees
- `POST /events/{event_id}/attendees/{id}/resend-ticket` - Resend ticket
- `POST /events/{event_id}/attendees/{id}/check_in` - Check-in attendee

## Question Management
- `POST /events/{event_id}/questions` - Create question
- `GET /events/{event_id}/questions` - List questions
- `GET /events/{event_id}/questions/{id}` - Get question
- `PUT /events/{event_id}/questions/{id}` - Update question
- `DELETE /events/{event_id}/questions/{id}` - Delete question
- `POST /events/{event_id}/questions/sort` - Sort questions
- `PUT /events/{event_id}/questions/{id}/answers/{answer_id}` - Update answer
- `GET|POST /events/{event_id}/questions/answers/export` - Export answers

## Promo Code Management
- `POST /events/{event_id}/promo-codes` - Create promo code
- `GET /events/{event_id}/promo-codes` - List promo codes
- `GET /events/{event_id}/promo-codes/{id}` - Get promo code
- `PUT /events/{event_id}/promo-codes/{id}` - Update promo code
- `DELETE /events/{event_id}/promo-codes/{id}` - Delete promo code

## Event Settings
- `GET /events/{event_id}/settings` - Get settings
- `PUT /events/{event_id}/settings` - Update settings
- `PATCH /events/{event_id}/settings` - Partial update settings

## Capacity Assignment Management
- `POST /events/{event_id}/capacity-assignments` - Create assignment
- `GET /events/{event_id}/capacity-assignments` - List assignments
- `GET /events/{event_id}/capacity-assignments/{id}` - Get assignment
- `PUT /events/{event_id}/capacity-assignments/{id}` - Update assignment
- `DELETE /events/{event_id}/capacity-assignments/{id}` - Delete assignment

## Check-in List Management
- `POST /events/{event_id}/check-in-lists` - Create check-in list
- `GET /events/{event_id}/check-in-lists` - List check-in lists
- `GET /events/{event_id}/check-in-lists/{id}` - Get check-in list
- `PUT /events/{event_id}/check-in-lists/{id}` - Update check-in list
- `DELETE /events/{event_id}/check-in-lists/{id}` - Delete check-in list

## Webhook Management
- `POST /events/{event_id}/webhooks` - Create webhook
- `GET /events/{event_id}/webhooks` - List webhooks
- `GET /events/{event_id}/webhooks/{id}` - Get webhook
- `PUT /events/{event_id}/webhooks/{id}` - Update webhook
- `DELETE /events/{event_id}/webhooks/{id}` - Delete webhook
- `GET /events/{event_id}/webhooks/{id}/logs` - Get webhook logs

## Message Management
- `POST /events/{event_id}/messages` - Send message
- `GET /events/{event_id}/messages` - List messages

## Image Management
- `POST /events/{event_id}/images` - Upload event image
- `GET /events/{event_id}/images` - List event images
- `DELETE /events/{event_id}/images/{id}` - Delete event image
- `POST /images` - Upload general image

## Statistics & Reports
- `GET /events/{event_id}/stats` - Get event stats
- `GET /events/{event_id}/check_in_stats` - Get check-in stats
- `GET /events/{event_id}/reports/{type}` - Get report

## Taxes and Fees
- `POST /accounts/{account_id}/taxes-and-fees` - Create tax/fee
- `GET /accounts/{account_id}/taxes-and-fees` - List taxes/fees
- `PUT /accounts/{account_id}/taxes-and-fees/{id}` - Update tax/fee
- `DELETE /accounts/{account_id}/taxes-and-fees/{id}` - Delete tax/fee

## Public API Routes (No Authentication Required)

### Public Event & Organizer
- `GET /public/events/{id}` - Get public event
- `GET /public/organizers/{id}` - Get public organizer
- `GET /public/organizers/{id}/events` - Get public organizer events

### Public Order Management
- `POST /public/events/{event_id}/order` - Create public order
- `PUT /public/events/{event_id}/order/{order_short_id}` - Complete order
- `GET /public/events/{event_id}/order/{order_short_id}` - Get public order
- `POST /public/events/{event_id}/order/{order_short_id}/await-offline-payment` - Offline payment
- `GET /public/events/{event_id}/order/{order_short_id}/invoice` - Download invoice

### Public Attendee & Content
- `GET /public/events/{event_id}/attendees/{attendee_short_id}` - Get public attendee
- `GET /public/events/{event_id}/promo-codes/{code}` - Get public promo code
- `GET /public/events/{event_id}/questions` - Get public questions

### Public Payment (Stripe)
- `POST /public/events/{event_id}/order/{order_short_id}/stripe/payment_intent` - Create payment intent
- `GET /public/events/{event_id}/order/{order_short_id}/stripe/payment_intent` - Get payment intent

### Public Check-in
- `GET /public/check-in-lists/{check_in_list_short_id}` - Get public check-in list
- `GET /public/check-in-lists/{check_in_list_short_id}/attendees` - Get check-in attendees
- `GET /public/check-in-lists/{check_in_list_short_id}/attendees/{attendee_public_id}` - Get check-in attendee
- `POST /public/check-in-lists/{check_in_list_short_id}/check-ins` - Create check-in
- `DELETE /public/check-in-lists/{check_in_list_short_id}/check-ins/{check_in_short_id}` - Delete check-in

### Webhooks
- `POST /public/webhooks/stripe` - Stripe webhook

## Utility Routes
- `GET /csrf-cookie` - Get CSRF cookie
- `GET /mail-test` - Mail testing endpoint (development)

---

## Authentication
Most routes require JWT authentication via `Authorization: Bearer <token>` header.

## Response Format
All responses return JSON with standard structure:
```json
{
  "data": {},
  "message": "Success",
  "status": "success"
}
```

## Total Routes Count
- **Authenticated Routes:** ~120 endpoints
- **Public Routes:** ~20 endpoints
- **Total:** ~140 API endpoints 