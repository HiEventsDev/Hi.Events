import { createHmac, randomUUID } from 'node:crypto';
import { request as playwrightRequest, type APIRequestContext } from '@playwright/test';
import { IS_SAAS_MODE, STRIPE_CONNECT_ACCOUNT_ID, STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET } from '../utils/env';
import { linkStripeConnectAccount } from '../utils/db';

const connectedAccountId = (): string | undefined => (IS_SAAS_MODE ? STRIPE_CONNECT_ACCOUNT_ID : undefined);

const stripeHeaders = (): Record<string, string> => {
  const accountId = connectedAccountId();
  return {
    Authorization: `Bearer ${STRIPE_SECRET_KEY}`,
    ...(accountId ? { 'Stripe-Account': accountId } : {}),
  };
};

export const canTakeStripePayments = (): boolean =>
  STRIPE_SECRET_KEY !== '' && (!IS_SAAS_MODE || STRIPE_CONNECT_ACCOUNT_ID !== '');

export const STRIPE_PAYMENTS_SKIP_REASON =
  'Requires STRIPE_SECRET_KEY (Stripe test mode), plus E2E_STRIPE_CONNECT_ACCOUNT_ID (an enabled connected test account) on a SaaS stack.';

export function enableStripePayments(organizerId: number): void {
  const accountId = connectedAccountId();
  if (accountId) {
    linkStripeConnectAccount(organizerId, accountId);
  }
}

export const STRIPE_CARD_PAYING_INTO_AVAILABLE_BALANCE = 'pm_card_bypassPending';

export async function payOrderWithTestCard(
  request: APIRequestContext,
  opts: { eventId: number; orderShortId: string; sessionId: string; paymentMethod?: string },
): Promise<{ paymentIntentId: string; amount: number }> {
  const created = await request.post(
    `public/events/${opts.eventId}/order/${opts.orderShortId}/stripe/payment_intent?session_identifier=${opts.sessionId}`,
    { headers: { Accept: 'application/json' } },
  );
  if (!created.ok()) {
    throw new Error(`create payment intent → ${created.status()}: ${await created.text()}`);
  }
  const { client_secret: clientSecret } = (await created.json()) as { client_secret: string };
  const paymentIntentId = clientSecret.split('_secret_')[0];

  const confirmed = await request.post(`https://api.stripe.com/v1/payment_intents/${paymentIntentId}/confirm`, {
    headers: stripeHeaders(),
    form: { payment_method: opts.paymentMethod ?? 'pm_card_visa', return_url: 'https://example.com/return' },
  });
  if (!confirmed.ok()) {
    throw new Error(`stripe confirm → ${confirmed.status()}: ${await confirmed.text()}`);
  }
  const paymentIntent = (await confirmed.json()) as { status: string; amount: number };
  if (paymentIntent.status !== 'succeeded') {
    throw new Error(`Payment intent ${paymentIntentId} is ${paymentIntent.status}, expected succeeded`);
  }
  return { paymentIntentId, amount: paymentIntent.amount };
}

export async function getPaymentIntentAmount(request: APIRequestContext, paymentIntentId: string): Promise<number> {
  const response = await request.get(`https://api.stripe.com/v1/payment_intents/${paymentIntentId}`, { headers: stripeHeaders() });
  if (!response.ok()) {
    throw new Error(`stripe payment intent fetch → ${response.status()}: ${await response.text()}`);
  }
  return ((await response.json()) as { amount: number }).amount;
}

export async function getPaymentIntentRefundedAmount(request: APIRequestContext, paymentIntentId: string): Promise<number> {
  const response = await request.get(`https://api.stripe.com/v1/refunds?payment_intent=${paymentIntentId}`, { headers: stripeHeaders() });
  if (!response.ok()) {
    throw new Error(`stripe refunds fetch → ${response.status()}: ${await response.text()}`);
  }
  const { data } = (await response.json()) as { data: { amount: number; status: string }[] };
  return data.filter((refund) => refund.status !== 'failed' && refund.status !== 'canceled')
    .reduce((total, refund) => total + refund.amount, 0);
}

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
): Promise<string> {
  const intentResponse = await request.get(
    `public/events/${opts.eventId}/order/${opts.orderShortId}/stripe/payment_intent?session_identifier=${opts.sessionId}`,
  );
  if (!intentResponse.ok()) {
    throw new Error(`get payment intent → ${intentResponse.status()}: ${await intentResponse.text()}`);
  }
  const { paymentIntentId } = (await intentResponse.json()) as { paymentIntentId: string };
  await sendPaymentIntentSucceededWebhook(request, paymentIntentId);
  return paymentIntentId;
}

async function stripeGet<T>(request: APIRequestContext, path: string): Promise<T> {
  const response = await request.get(`https://api.stripe.com/v1/${path}`, { headers: stripeHeaders() });
  if (!response.ok()) {
    throw new Error(`stripe GET ${path} → ${response.status()}: ${await response.text()}`);
  }
  return (await response.json()) as T;
}

let settlementCurrency: Promise<string> | undefined;

export const stripeSettlementCurrency = (): Promise<string> => {
  settlementCurrency ??= (async () => {
    const context = await playwrightRequest.newContext();
    try {
      return (await stripeGet<{ default_currency: string }>(context, 'account')).default_currency.toUpperCase();
    } finally {
      await context.dispose();
    }
  })();
  return settlementCurrency;
};

async function postSignedStripeEvent(
  request: APIRequestContext,
  type: string,
  object: unknown,
  delivery: { tolerateRejection?: boolean } = {},
): Promise<void> {
  const timestamp = Math.floor(Date.now() / 1000);
  const payload = JSON.stringify({
    id: `evt_e2e_${randomUUID().replace(/-/g, '')}`,
    object: 'event',
    api_version: '2024-06-20',
    created: timestamp,
    type,
    data: { object },
    livemode: false,
    ...(connectedAccountId() ? { account: connectedAccountId() } : {}),
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
  if (!webhookResponse.ok() && !delivery.tolerateRejection) {
    throw new Error(`stripe ${type} webhook delivery → ${webhookResponse.status()}: ${await webhookResponse.text()}`);
  }
}

export async function sendPaymentIntentSucceededWebhook(
  request: APIRequestContext,
  paymentIntentId: string,
  delivery: { tolerateRejection?: boolean } = {},
): Promise<void> {
  const paymentIntent = await stripeGet(request, `payment_intents/${paymentIntentId}?expand[]=latest_charge`);
  await postSignedStripeEvent(request, 'payment_intent.succeeded', paymentIntent, delivery);
}

interface ChargeWithBalanceTransaction {
  balance_transaction: { fee: number } | null;
}

async function chargeOnceFeesAreKnown(request: APIRequestContext, paymentIntentId: string): Promise<ChargeWithBalanceTransaction> {
  const deadline = Date.now() + 30_000;
  for (;;) {
    const { latest_charge: charge } = await stripeGet<{ latest_charge: ChargeWithBalanceTransaction }>(
      request,
      `payment_intents/${paymentIntentId}?expand[]=latest_charge.balance_transaction`,
    );
    if (charge.balance_transaction) {
      return charge;
    }
    if (Date.now() > deadline) {
      throw new Error(`Stripe has no balance transaction for the charge on ${paymentIntentId}`);
    }
    await new Promise((resolve) => setTimeout(resolve, 500));
  }
}

export async function sendChargeSucceededWebhook(request: APIRequestContext, paymentIntentId: string): Promise<void> {
  await postSignedStripeEvent(request, 'charge.succeeded', await chargeOnceFeesAreKnown(request, paymentIntentId));
}

export interface StripeRefund {
  id: string;
  amount: number;
  currency: string;
  status: string;
  payment_intent: string;
  metadata: Record<string, string>;
}

export async function listPaymentIntentRefunds(request: APIRequestContext, paymentIntentId: string): Promise<StripeRefund[]> {
  const { data } = await stripeGet<{ data: StripeRefund[] }>(request, `refunds?payment_intent=${paymentIntentId}&limit=100`);
  return data;
}

export async function getPaymentIntentFees(request: APIRequestContext, paymentIntentId: string): Promise<number> {
  return (await chargeOnceFeesAreKnown(request, paymentIntentId)).balance_transaction?.fee ?? 0;
}

export async function sendRefundUpdatedWebhooks(request: APIRequestContext, paymentIntentId: string): Promise<void> {
  for (const refund of await listPaymentIntentRefunds(request, paymentIntentId)) {
    await postSignedStripeEvent(request, 'refund.updated', refund);
  }
}
