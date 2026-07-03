# Deploying Hi.Events with Razorpay Integration

This guide provides instructions on how to deploy or update your Hi.Events installation to include the new Razorpay payment integration alongside Stripe.

## Prerequisites

Before proceeding, ensure you have:
1. An active Razorpay account.
2. Your Razorpay **Key ID** and **Key Secret**.
3. A configured Razorpay Webhook with a **Webhook Secret**. 

### Razorpay Webhook Configuration
In your Razorpay Dashboard, set up a webhook pointing to your application:
- **Webhook URL**: `https://your-domain.com/api/webhooks/razorpay`
- **Active Events**:
  - `payment.captured`
  - `payment.failed`
  - `refund.processed`
- **Secret**: Generate a strong secret and save it for the `.env` configuration.

---

## Deployment Steps

### 1. Update Environment Variables

Update your `.env` files (both `backend/.env` and `docker/all-in-one/.env` if you are using Docker) to include the new Razorpay configuration variables:

```env
RAZORPAY_KEY_ID=your_razorpay_key_id
RAZORPAY_KEY_SECRET=your_razorpay_key_secret
RAZORPAY_WEBHOOK_SECRET=your_razorpay_webhook_secret
```

### 2. Install PHP Dependencies

The new integration relies on the official `razorpay/razorpay` PHP SDK. You must update your composer dependencies.

**If running directly on a server:**
```bash
cd backend
composer install --no-interaction --prefer-dist --optimize-autoloader
```

**If running via Docker (Development):**
```bash
docker compose -f docker-compose.dev.yml exec backend composer install --no-interaction
```

**If running via Docker (All-In-One):**
The Dockerfile will automatically install the dependencies when you rebuild the image.

### 3. Run Database Migrations

The Razorpay integration requires a new `razorpay_orders` table to track order statuses, payment IDs, and signatures.

**If running directly on a server:**
```bash
cd backend
php artisan migrate --force
```

**If running via Docker:**
```bash
docker compose -f docker-compose.dev.yml exec backend php artisan migrate --force
```

### 4. Rebuild and Restart Containers (Docker Users)

If you are deploying using Docker, it is highly recommended to rebuild your containers so the frontend can compile the new Razorpay components and the backend can package the new SDK and configurations.

```bash
# Navigate to your docker directory
cd docker/all-in-one

# Rebuild and start the containers in the background
docker compose up -d --build
```

### 5. Enable Razorpay for Events

Once the deployment is complete, Razorpay is officially supported by the platform. To use it for a specific event:
1. Log in to the Organizer Dashboard.
2. Navigate to your Event -> **Settings** -> **Payment Settings**.
3. Select **Razorpay** as an enabled payment provider.
4. Save settings. Attendees will now see Razorpay dynamically loaded on the checkout screen.

---

## Troubleshooting

- **Razorpay SDK Not Loading**: Ensure the frontend can reach `https://checkout.razorpay.com/v1/checkout.js`.
- **Payment Success but Order Not Updating**: Check your webhook logs. Verify that `RAZORPAY_WEBHOOK_SECRET` exactly matches the secret configured in the Razorpay Dashboard. Webhooks are the primary mechanism for finalizing orders.
- **Class Not Found Error**: Run `composer dump-autoload` or ensure `composer install` was executed successfully to load the Razorpay PHP SDK.
