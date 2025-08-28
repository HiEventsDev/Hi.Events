# Hi.Events API Documentation

## Overview

Hi.Events is a comprehensive event management system with a RESTful API built on Laravel. This documentation covers all available endpoints, their parameters, and usage examples.

## Base URL

All API endpoints are prefixed with `/api/v1`

## Authentication

The API uses JWT authentication for protected routes. Include the Authorization header with Bearer token for authenticated requests:

```
Authorization: Bearer <your-jwt-token>
```

## Content Type

All requests should include the Content-Type header:

```
Content-Type: application/json
```

## Response Format

All responses are returned in JSON format with the following structure:

```json
{
  "data": {},
  "message": "Success message",
  "status": "success"
}
```

## Error Handling

Error responses follow this format:

```json
{
  "message": "Error message",
  "errors": {},
  "status": "error"
}
```

---

## Authentication Endpoints

### Login
- **Method:** `POST`
- **Endpoint:** `/auth/login`
- **Description:** Authenticate user and receive JWT token
- **Parameters:**
  - `email` (string, required): User's email address
  - `password` (string, required): User's password (min 8 characters)
  - `account_id` (integer, optional): Account ID to authenticate against

### Logout
- **Method:** `POST`
- **Endpoint:** `/auth/logout`
- **Description:** Invalidate the current JWT token
- **Authentication:** Required

### Register
- **Method:** `POST`
- **Endpoint:** `/auth/register`
- **Description:** Create a new user account
- **Parameters:** Same as account creation

### Forgot Password
- **Method:** `POST`
- **Endpoint:** `/auth/forgot-password`
- **Description:** Request password reset email

### Reset Password
- **Method:** `POST`
- **Endpoint:** `/auth/reset-password/{reset_token}`
- **Description:** Reset password using token

### Refresh Token
- **Method:** `POST`
- **Endpoint:** `/auth/refresh`
- **Description:** Refresh JWT token
- **Authentication:** Required

---

## User Management

### Get Current User
- **Method:** `GET`
- **Endpoint:** `/users/me`
- **Description:** Get current authenticated user's information
- **Authentication:** Required

### Update Current User
- **Method:** `PUT`
- **Endpoint:** `/users/me`
- **Description:** Update current user's information
- **Authentication:** Required

### Create User
- **Method:** `POST`
- **Endpoint:** `/users`
- **Description:** Create a new user
- **Authentication:** Required

### Get Users
- **Method:** `GET`
- **Endpoint:** `/users`
- **Description:** Get list of users
- **Authentication:** Required

### Get User
- **Method:** `GET`
- **Endpoint:** `/users/{user_id}`
- **Description:** Get specific user information
- **Authentication:** Required

### Update User
- **Method:** `PUT`
- **Endpoint:** `/users/{user_id}`
- **Description:** Update user information
- **Authentication:** Required

### User Email Management
- **Method:** `POST`
- **Endpoint:** `/users/{user_id}/email-change/{changeToken}`
- **Description:** Confirm email change
- **Authentication:** Required

- **Method:** `DELETE`
- **Endpoint:** `/users/{user_id}/email-change`
- **Description:** Cancel email change
- **Authentication:** Required

### User Invitations
- **Method:** `POST`
- **Endpoint:** `/users/{user_id}/invitation`
- **Description:** Resend invitation
- **Authentication:** Required

- **Method:** `DELETE`
- **Endpoint:** `/users/{user_id}/invitation`
- **Description:** Delete invitation
- **Authentication:** Required

---

## Account Management

### Get Account
- **Method:** `GET`
- **Endpoint:** `/accounts/{account_id?}`
- **Description:** Get account information
- **Authentication:** Required

### Update Account
- **Method:** `PUT`
- **Endpoint:** `/accounts/{account_id?}`
- **Description:** Update account information
- **Authentication:** Required

### Stripe Connect
- **Method:** `POST`
- **Endpoint:** `/accounts/{account_id}/stripe/connect`
- **Description:** Create Stripe Connect account
- **Authentication:** Required

---

## Event Management

### Create Event
- **Method:** `POST`
- **Endpoint:** `/events`
- **Description:** Create a new event
- **Authentication:** Required
- **Parameters:**
  - `title` (string, required): Event title (max 150 chars)
  - `description` (string, optional): Event description (max 50,000 chars)
  - `start_date` (date, required): Event start date
  - `end_date` (date, optional): Event end date
  - `timezone` (string, optional): Event timezone
  - `organizer_id` (integer, required): Organizer ID
  - `currency` (string, optional): Event currency code
  - `attributes` (array, optional): Custom event attributes
  - `location_details` (object, optional): Event location details

### Get Events
- **Method:** `GET`
- **Endpoint:** `/events`
- **Description:** Get list of events
- **Authentication:** Required

### Get Event
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}`
- **Description:** Get specific event details
- **Authentication:** Required

### Update Event
- **Method:** `PUT`
- **Endpoint:** `/events/{event_id}`
- **Description:** Update event information
- **Authentication:** Required

### Update Event Status
- **Method:** `PUT`
- **Endpoint:** `/events/{event_id}/status`
- **Description:** Update event status
- **Authentication:** Required

### Duplicate Event
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/duplicate`
- **Description:** Duplicate an existing event
- **Authentication:** Required

---

## Organizer Management

### Create Organizer
- **Method:** `POST`
- **Endpoint:** `/organizers`
- **Description:** Create a new organizer
- **Authentication:** Required

### Get Organizers
- **Method:** `GET`
- **Endpoint:** `/organizers`
- **Description:** Get list of organizers
- **Authentication:** Required

### Get Organizer
- **Method:** `GET`
- **Endpoint:** `/organizers/{organizer_id}`
- **Description:** Get specific organizer details
- **Authentication:** Required

### Update Organizer
- **Method:** `POST`
- **Endpoint:** `/organizers/{organizer_id}`
- **Description:** Update organizer information (POST used for file uploads)
- **Authentication:** Required

### Get Organizer Events
- **Method:** `GET`
- **Endpoint:** `/organizers/{organizer_id}/events`
- **Description:** Get events for specific organizer
- **Authentication:** Required

---

## Product Management

### Create Product
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/products`
- **Description:** Create a new product/ticket for an event
- **Authentication:** Required

### Get Products
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/products`
- **Description:** Get products for an event
- **Authentication:** Required

### Get Product
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/products/{ticket_id}`
- **Description:** Get specific product details
- **Authentication:** Required

### Update Product
- **Method:** `PUT`
- **Endpoint:** `/events/{event_id}/products/{ticket_id}`
- **Description:** Update product information
- **Authentication:** Required

### Delete Product
- **Method:** `DELETE`
- **Endpoint:** `/events/{event_id}/products/{ticket_id}`
- **Description:** Delete a product
- **Authentication:** Required

### Sort Products
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/products/sort`
- **Description:** Sort products order
- **Authentication:** Required

---

## Product Category Management

### Create Product Category
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/product-categories`
- **Description:** Create a new product category
- **Authentication:** Required

### Get Product Categories
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/product-categories`
- **Description:** Get product categories for an event
- **Authentication:** Required

### Get Product Category
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/product-categories/{category_id}`
- **Description:** Get specific product category
- **Authentication:** Required

### Update Product Category
- **Method:** `PUT`
- **Endpoint:** `/events/{event_id}/product-categories/{category_id}`
- **Description:** Update product category
- **Authentication:** Required

### Delete Product Category
- **Method:** `DELETE`
- **Endpoint:** `/events/{event_id}/product-categories/{category_id}`
- **Description:** Delete product category
- **Authentication:** Required

---

## Order Management

### Get Orders
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/orders`
- **Description:** Get orders for an event
- **Authentication:** Required

### Get Order
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/orders/{order_id}`
- **Description:** Get specific order details
- **Authentication:** Required

### Update Order
- **Method:** `PUT`
- **Endpoint:** `/events/{event_id}/orders/{order_id}`
- **Description:** Update order information
- **Authentication:** Required

### Cancel Order
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/orders/{order_id}/cancel`
- **Description:** Cancel an order
- **Authentication:** Required

### Mark Order as Paid
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/orders/{order_id}/mark-as-paid`
- **Description:** Mark order as paid
- **Authentication:** Required

### Refund Order
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/orders/{order_id}/refund`
- **Description:** Refund an order
- **Authentication:** Required

### Message Order
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/orders/{order_id}/message`
- **Description:** Send message to order customer
- **Authentication:** Required

### Resend Order Confirmation
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/orders/{order_id}/resend_confirmation`
- **Description:** Resend order confirmation email
- **Authentication:** Required

### Export Orders
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/orders/export`
- **Description:** Export orders to CSV/Excel
- **Authentication:** Required

### Download Order Invoice
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/orders/{order_id}/invoice`
- **Description:** Download order invoice
- **Authentication:** Required

---

## Attendee Management

### Create Attendee
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/attendees`
- **Description:** Create a new attendee
- **Authentication:** Required

### Get Attendees
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/attendees`
- **Description:** Get attendees for an event
- **Authentication:** Required

### Get Attendee
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/attendees/{attendee_id}`
- **Description:** Get specific attendee details
- **Authentication:** Required

### Update Attendee
- **Method:** `PUT`
- **Endpoint:** `/events/{event_id}/attendees/{attendee_id}`
- **Description:** Update attendee information
- **Authentication:** Required

### Partial Update Attendee
- **Method:** `PATCH`
- **Endpoint:** `/events/{event_id}/attendees/{attendee_id}`
- **Description:** Partially update attendee information
- **Authentication:** Required

### Export Attendees
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/attendees/export`
- **Description:** Export attendees to CSV/Excel
- **Authentication:** Required

### Resend Attendee Ticket
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/attendees/{attendee_public_id}/resend-ticket`
- **Description:** Resend ticket to attendee
- **Authentication:** Required

### Check-in Attendee
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/attendees/{attendee_public_id}/check_in`
- **Description:** Check-in an attendee
- **Authentication:** Required

---

## Question Management

### Create Question
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/questions`
- **Description:** Create a new question for an event
- **Authentication:** Required

### Get Questions
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/questions`
- **Description:** Get questions for an event
- **Authentication:** Required

### Get Question
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/questions/{question_id}`
- **Description:** Get specific question details
- **Authentication:** Required

### Update Question
- **Method:** `PUT`
- **Endpoint:** `/events/{event_id}/questions/{question_id}`
- **Description:** Update question information
- **Authentication:** Required

### Delete Question
- **Method:** `DELETE`
- **Endpoint:** `/events/{event_id}/questions/{question_id}`
- **Description:** Delete a question
- **Authentication:** Required

### Sort Questions
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/questions/sort`
- **Description:** Sort questions order
- **Authentication:** Required

### Update Question Answer
- **Method:** `PUT`
- **Endpoint:** `/events/{event_id}/questions/{question_id}/answers/{answer_id}`
- **Description:** Update question answer
- **Authentication:** Required

### Export Question Answers
- **Method:** `GET` or `POST`
- **Endpoint:** `/events/{event_id}/questions/answers/export`
- **Description:** Export question answers
- **Authentication:** Required

---

## Promo Code Management

### Create Promo Code
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/promo-codes`
- **Description:** Create a new promo code
- **Authentication:** Required

### Get Promo Codes
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/promo-codes`
- **Description:** Get promo codes for an event
- **Authentication:** Required

### Get Promo Code
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/promo-codes/{promo_code_id}`
- **Description:** Get specific promo code details
- **Authentication:** Required

### Update Promo Code
- **Method:** `PUT`
- **Endpoint:** `/events/{event_id}/promo-codes/{promo_code_id}`
- **Description:** Update promo code information
- **Authentication:** Required

### Delete Promo Code
- **Method:** `DELETE`
- **Endpoint:** `/events/{event_id}/promo-codes/{promo_code_id}`
- **Description:** Delete a promo code
- **Authentication:** Required

---

## Event Settings

### Get Event Settings
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/settings`
- **Description:** Get event settings
- **Authentication:** Required

### Update Event Settings
- **Method:** `PUT`
- **Endpoint:** `/events/{event_id}/settings`
- **Description:** Update event settings
- **Authentication:** Required

### Partial Update Event Settings
- **Method:** `PATCH`
- **Endpoint:** `/events/{event_id}/settings`
- **Description:** Partially update event settings
- **Authentication:** Required

---

## Capacity Assignment Management

### Create Capacity Assignment
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/capacity-assignments`
- **Description:** Create a new capacity assignment
- **Authentication:** Required

### Get Capacity Assignments
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/capacity-assignments`
- **Description:** Get capacity assignments for an event
- **Authentication:** Required

### Get Capacity Assignment
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/capacity-assignments/{capacity_assignment_id}`
- **Description:** Get specific capacity assignment
- **Authentication:** Required

### Update Capacity Assignment
- **Method:** `PUT`
- **Endpoint:** `/events/{event_id}/capacity-assignments/{capacity_assignment_id}`
- **Description:** Update capacity assignment
- **Authentication:** Required

### Delete Capacity Assignment
- **Method:** `DELETE`
- **Endpoint:** `/events/{event_id}/capacity-assignments/{capacity_assignment_id}`
- **Description:** Delete capacity assignment
- **Authentication:** Required

---

## Check-in List Management

### Create Check-in List
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/check-in-lists`
- **Description:** Create a new check-in list
- **Authentication:** Required

### Get Check-in Lists
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/check-in-lists`
- **Description:** Get check-in lists for an event
- **Authentication:** Required

### Get Check-in List
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/check-in-lists/{check_in_list_id}`
- **Description:** Get specific check-in list
- **Authentication:** Required

### Update Check-in List
- **Method:** `PUT`
- **Endpoint:** `/events/{event_id}/check-in-lists/{check_in_list_id}`
- **Description:** Update check-in list
- **Authentication:** Required

### Delete Check-in List
- **Method:** `DELETE`
- **Endpoint:** `/events/{event_id}/check-in-lists/{check_in_list_id}`
- **Description:** Delete check-in list
- **Authentication:** Required

---

## Webhook Management

### Create Webhook
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/webhooks`
- **Description:** Create a new webhook
- **Authentication:** Required

### Get Webhooks
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/webhooks`
- **Description:** Get webhooks for an event
- **Authentication:** Required

### Get Webhook
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/webhooks/{webhook_id}`
- **Description:** Get specific webhook details
- **Authentication:** Required

### Update Webhook
- **Method:** `PUT`
- **Endpoint:** `/events/{event_id}/webhooks/{webhook_id}`
- **Description:** Update webhook information
- **Authentication:** Required

### Delete Webhook
- **Method:** `DELETE`
- **Endpoint:** `/events/{event_id}/webhooks/{webhook_id}`
- **Description:** Delete a webhook
- **Authentication:** Required

### Get Webhook Logs
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/webhooks/{webhook_id}/logs`
- **Description:** Get webhook execution logs
- **Authentication:** Required

---

## Message Management

### Send Message
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/messages`
- **Description:** Send message to event attendees
- **Authentication:** Required

### Get Messages
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/messages`
- **Description:** Get messages for an event
- **Authentication:** Required

---

## Image Management

### Upload Event Image
- **Method:** `POST`
- **Endpoint:** `/events/{event_id}/images`
- **Description:** Upload image for an event
- **Authentication:** Required

### Get Event Images
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/images`
- **Description:** Get images for an event
- **Authentication:** Required

### Delete Event Image
- **Method:** `DELETE`
- **Endpoint:** `/events/{event_id}/images/{image_id}`
- **Description:** Delete an event image
- **Authentication:** Required

### Upload General Image
- **Method:** `POST`
- **Endpoint:** `/images`
- **Description:** Upload a general image
- **Authentication:** Required

---

## Statistics & Reports

### Get Event Stats
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/stats`
- **Description:** Get event statistics
- **Authentication:** Required

### Get Check-in Stats
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/check_in_stats`
- **Description:** Get check-in statistics
- **Authentication:** Required

### Get Report
- **Method:** `GET`
- **Endpoint:** `/events/{event_id}/reports/{report_type}`
- **Description:** Get specific report type
- **Authentication:** Required

---

## Taxes and Fees

### Create Tax or Fee
- **Method:** `POST`
- **Endpoint:** `/accounts/{account_id}/taxes-and-fees`
- **Description:** Create a new tax or fee
- **Authentication:** Required

### Get Taxes and Fees
- **Method:** `GET`
- **Endpoint:** `/accounts/{account_id}/taxes-and-fees`
- **Description:** Get taxes and fees for an account
- **Authentication:** Required

### Update Tax or Fee
- **Method:** `PUT`
- **Endpoint:** `/accounts/{account_id}/taxes-and-fees/{tax_or_fee_id}`
- **Description:** Update tax or fee
- **Authentication:** Required

### Delete Tax or Fee
- **Method:** `DELETE`
- **Endpoint:** `/accounts/{account_id}/taxes-and-fees/{tax_or_fee_id}`
- **Description:** Delete tax or fee
- **Authentication:** Required

---

## Public API Endpoints

These endpoints are accessible without authentication and are used for public event pages and ticket purchasing.

### Get Public Event
- **Method:** `GET`
- **Endpoint:** `/public/events/{event_id}`
- **Description:** Get public event information

### Get Public Organizer
- **Method:** `GET`
- **Endpoint:** `/public/organizers/{organizer_id}`
- **Description:** Get public organizer information

### Get Public Organizer Events
- **Method:** `GET`
- **Endpoint:** `/public/organizers/{organizer_id}/events`
- **Description:** Get public events for an organizer

### Create Public Order
- **Method:** `POST`
- **Endpoint:** `/public/events/{event_id}/order`
- **Description:** Create a new order (public ticket purchase)

### Complete Public Order
- **Method:** `PUT`
- **Endpoint:** `/public/events/{event_id}/order/{order_short_id}`
- **Description:** Complete an order

### Get Public Order
- **Method:** `GET`
- **Endpoint:** `/public/events/{event_id}/order/{order_short_id}`
- **Description:** Get public order details

### Transition to Offline Payment
- **Method:** `POST`
- **Endpoint:** `/public/events/{event_id}/order/{order_short_id}/await-offline-payment`
- **Description:** Transition order to offline payment

### Download Public Order Invoice
- **Method:** `GET`
- **Endpoint:** `/public/events/{event_id}/order/{order_short_id}/invoice`
- **Description:** Download order invoice (public)

### Get Public Attendee
- **Method:** `GET`
- **Endpoint:** `/public/events/{event_id}/attendees/{attendee_short_id}`
- **Description:** Get public attendee information

### Get Public Promo Code
- **Method:** `GET`
- **Endpoint:** `/public/events/{event_id}/promo-codes/{promo_code}`
- **Description:** Get public promo code information

### Get Public Questions
- **Method:** `GET`
- **Endpoint:** `/public/events/{event_id}/questions`
- **Description:** Get public questions for an event

### Stripe Payment Intent (Public)
- **Method:** `POST`
- **Endpoint:** `/public/events/{event_id}/order/{order_short_id}/stripe/payment_intent`
- **Description:** Create Stripe payment intent

- **Method:** `GET`
- **Endpoint:** `/public/events/{event_id}/order/{order_short_id}/stripe/payment_intent`
- **Description:** Get Stripe payment intent

### Public Check-in Endpoints
- **Method:** `GET`
- **Endpoint:** `/public/check-in-lists/{check_in_list_short_id}`
- **Description:** Get public check-in list

- **Method:** `GET`
- **Endpoint:** `/public/check-in-lists/{check_in_list_short_id}/attendees`
- **Description:** Get attendees for public check-in list

- **Method:** `GET`
- **Endpoint:** `/public/check-in-lists/{check_in_list_short_id}/attendees/{attendee_public_id}`
- **Description:** Get specific attendee for public check-in

- **Method:** `POST`
- **Endpoint:** `/public/check-in-lists/{check_in_list_short_id}/check-ins`
- **Description:** Create attendee check-in (public)

- **Method:** `DELETE`
- **Endpoint:** `/public/check-in-lists/{check_in_list_short_id}/check-ins/{check_in_short_id}`
- **Description:** Delete attendee check-in (public)

---

## Webhook Endpoints

### Stripe Webhook
- **Method:** `POST`
- **Endpoint:** `/public/webhooks/stripe`
- **Description:** Stripe webhook endpoint for payment notifications

---

## Utility Endpoints

### CSRF Cookie
- **Method:** `GET`
- **Endpoint:** `/csrf-cookie`
- **Description:** Get CSRF cookie for form submissions

---

## API Keys Management

### Create API Key
- **Method:** `POST`
- **Endpoint:** `/auth/api-keys`
- **Description:** Create a new API key
- **Authentication:** Required

### Get API Keys
- **Method:** `GET`
- **Endpoint:** `/auth/api-keys`
- **Description:** Get list of API keys
- **Authentication:** Required

### Revoke API Key
- **Method:** `DELETE`
- **Endpoint:** `/auth/api-keys/{api_key_id}`
- **Description:** Revoke an API key
- **Authentication:** Required

---

## Invitation Management

### Get User Invitation
- **Method:** `GET`
- **Endpoint:** `/auth/invitation/{invite_token}`
- **Description:** Get invitation details

### Accept Invitation
- **Method:** `POST`
- **Endpoint:** `/auth/invitation/{invite_token}`
- **Description:** Accept user invitation

---

## Rate Limiting

The API implements rate limiting to prevent abuse. Standard limits apply:
- 60 requests per minute for authenticated users
- 30 requests per minute for unauthenticated users

## Error Codes

| HTTP Status | Description |
|-------------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Internal Server Error |

## Examples

### Creating an Event

```bash
curl -X POST \
  'https://your-domain.com/api/v1/events' \
  -H 'Authorization: Bearer YOUR_JWT_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "title": "My Awesome Event",
    "description": "This is a great event you should attend!",
    "start_date": "2024-12-01T10:00:00Z",
    "end_date": "2024-12-01T18:00:00Z",
    "timezone": "America/New_York",
    "organizer_id": 1,
    "currency": "USD",
    "location_details": {
      "venue_name": "Convention Center",
      "address_line_1": "123 Main St",
      "city": "New York",
      "state_or_region": "NY",
      "zip_or_postal_code": "10001",
      "country": "US"
    }
  }'
```

### Getting Events

```bash
curl -X GET \
  'https://your-domain.com/api/v1/events' \
  -H 'Authorization: Bearer YOUR_JWT_TOKEN'
```

### Creating a Public Order

```bash
curl -X POST \
  'https://your-domain.com/api/v1/public/events/123/order' \
  -H 'Content-Type: application/json' \
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "order_items": [
      {
        "ticket_id": 1,
        "quantity": 2
      }
    ]
  }'
```

---

## Support

For additional support or questions about the API, please refer to the project documentation or create an issue in the repository. 