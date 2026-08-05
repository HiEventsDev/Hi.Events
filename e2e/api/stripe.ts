import { createHmac, randomUUID } from 'node:crypto';
import type { APIRequestContext } from '@playwright/test';
import { STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET } from '../utils/env';

export function parsePaymentReturnUrl(pageUrl: string): { orderShortId: string; sessionId: string } {
  const url = new URL(pageUrl);
  const match = url.pathname.match(/^\/checkout\/\d+\/([^/]+)\//);
  if (!match) {
    throw new Error(`Not a checkout URL: ${pageUrl}`);
  }
  return { orderShortId: match[1], sessionId: url.searchParams.get('session_identifier') ?? '' };
}

export async function deliverPaymentIntentSucceededWebhook(
  request: APIRequestContext,
  opts: { eventId: number; orderShortId: string; sessionId: string },
): Promise<void> {
  const intentResponse = await request.get(
    `public/events/${opts.eventId}/order/${opts.orderShortId}/stripe/payment_intent?session_identifier=${opts.sessionId}`,
  );
  if (!intentResponse.ok()) {
    throw new Error(`get payment intent → ${intentResponse.status()}: ${await intentResponse.text()}`);
  }
  const { paymentIntentId } = (await intentResponse.json()) as { paymentIntentId: string };

  const stripeResponse = await request.get(
    `https://api.stripe.com/v1/payment_intents/${paymentIntentId}?expand[]=latest_charge`,
    { headers: { Authorization: `Bearer ${STRIPE_SECRET_KEY}` } },
  );
  if (!stripeResponse.ok()) {
    throw new Error(`stripe payment intent fetch → ${stripeResponse.status()}: ${await stripeResponse.text()}`);
  }
  const paymentIntent = await stripeResponse.json();

  const timestamp = Math.floor(Date.now() / 1000);
  const payload = JSON.stringify({
    id: `evt_e2e_${randomUUID().replace(/-/g, '')}`,
    object: 'event',
    api_version: '2024-06-20',
    created: timestamp,
    type: 'payment_intent.succeeded',
    data: { object: paymentIntent },
    livemode: false,
    pending_webhooks: 1,
    request: { id: null, idempotency_key: null },
  });
  const signature = createHmac('sha256', STRIPE_WEBHOOK_SECRET)
    .update(`${timestamp}.${payload}`)
    .digest('hex');

  const webhookResponse = await request.post('public/webhooks/stripe', {
    headers: {
      'Content-Type': 'application/json',
      'Stripe-Signature': `t=${timestamp},v1=${signature}`,
    },
    data: payload,
  });
  if (!webhookResponse.ok()) {
    throw new Error(`stripe webhook delivery → ${webhookResponse.status()}: ${await webhookResponse.text()}`);
  }
}
