import type { APIRequestContext } from '@playwright/test';
import type { ApiClient } from '../api/api-client';
import {
  createCompletedPaidOrder,
  createLiveEventWithProduct,
  enableOfflinePayments,
  type OrderSeedOptions,
  type SeededEvent,
  type SeededOrder,
} from '../api/factory';
import { awaitOfflinePayment, completePublicOrder, createPublicOrder, getPublicOrder } from '../api/public-client';
import { runSql } from './db';
import { liftSelfServiceThrottles, liftThrottle } from './throttle';
import { uniqueEmail } from './unique';

const SHORT_ID = /^[A-Za-z0-9_]+$/;

export const TICKET_PRICE = 40;
export const CARD_FEE = 1.6;

export function liftResaleThrottles(): void {
  liftSelfServiceThrottles();
  ['self-service-resale-ip', 'self-service-resale-estimate'].forEach(liftThrottle);
}

export function markOrderPaidThroughStripe(orderShortId: string, cardFee = CARD_FEE): void {
  if (!SHORT_ID.test(orderShortId) || !Number.isFinite(cardFee)) {
    throw new Error(`Refusing to mark an unexpected order as paid through Stripe: ${orderShortId}`);
  }
  const updated = runSql(
    `WITH paid AS (
       UPDATE orders SET payment_provider = 'STRIPE' WHERE short_id = '${orderShortId}' AND status = 'COMPLETED' RETURNING id, event_id, currency
     ), payment AS (
       INSERT INTO stripe_payments (order_id, payment_intent_id, charge_id, connected_account_id, currency, created_at, updated_at)
       SELECT paid.id, 'pi_e2e_' || md5(random()::text), 'ch_e2e_' || md5(random()::text),
              (SELECT platform.stripe_account_id FROM organizer_stripe_platforms platform
                 JOIN events ON events.organizer_id = platform.organizer_id
                WHERE events.id = paid.event_id AND platform.deleted_at IS NULL AND platform.stripe_setup_completed_at IS NOT NULL
                ORDER BY platform.created_at DESC LIMIT 1),
              paid.currency, NOW(), NOW()
       FROM paid
     )
     INSERT INTO order_payment_platform_fees (order_id, payment_platform, payment_platform_fee_amount, currency, created_at, updated_at)
     SELECT paid.id, 'stripe', ${cardFee}, paid.currency, NOW(), NOW() FROM paid RETURNING order_id`,
  ).trim();
  if (updated === '') {
    throw new Error(`No completed order found with short id ${orderShortId}`);
  }
}

export interface ResellableOrder {
  event: SeededEvent;
  order: SeededOrder & { totalGross: number };
}

export async function createEventWithResale(
  api: ApiClient,
  organizerId: number,
  opts: { isResellable?: boolean } = {},
): Promise<SeededEvent> {
  const event = await createLiveEventWithProduct(api, { organizerId, price: TICKET_PRICE, isResellable: opts.isResellable });
  await api.updateEventSettings(event.eventId, { allow_ticket_resale: true });
  return event;
}

export async function createStripePaidOrder(
  api: ApiClient,
  publicApi: APIRequestContext,
  event: SeededEvent,
  opts: OrderSeedOptions = {},
): Promise<SeededOrder & { totalGross: number }> {
  const order = await createCompletedPaidOrder(api, publicApi, event, opts);
  markOrderPaidThroughStripe(order.orderShortId);
  return order;
}

export async function createResellableOrder(
  api: ApiClient,
  publicApi: APIRequestContext,
  organizerId: number,
  opts: OrderSeedOptions = {},
): Promise<ResellableOrder> {
  const event = await createEventWithResale(api, organizerId);
  return { event, order: await createStripePaidOrder(api, publicApi, event, opts) };
}

export async function createResellableOrderWithAddOn(
  api: ApiClient,
  publicApi: APIRequestContext,
  organizerId: number,
  addOnTitle: string,
): Promise<ResellableOrder> {
  const event = await createEventWithResale(api, organizerId);
  const [category] = await api.listProductCategories(event.eventId);
  const addOn = await api.createProduct(event.eventId, {
    title: addOnTitle,
    product_type: 'GENERAL',
    type: 'PAID',
    product_category_id: category.id,
    prices: [{ price: 10 }],
  });
  const addOnPriceId = (await api.getProduct(event.eventId, addOn.id)).prices?.[0]?.id;
  if (!addOnPriceId) {
    throw new Error(`Add-on ${addOn.id} has no price`);
  }
  await enableOfflinePayments(api, event.eventId);

  const buyerEmail = uniqueEmail('buyer');
  const reserved = await createPublicOrder(publicApi, event.eventId, {
    products: [
      { product_id: event.productId, quantities: [{ price_id: event.priceId, quantity: 1 }] },
      { product_id: addOn.id, quantities: [{ price_id: addOnPriceId, quantity: 1 }] },
    ],
  });
  const sessionId = reserved.session_identifier as string;
  const buyer = { first_name: 'Test', last_name: 'Buyer', email: buyerEmail };
  await completePublicOrder(publicApi, event.eventId, reserved.short_id, sessionId, {
    order: buyer,
    attendees: [{ ...buyer, product_id: event.productId, product_price_id: event.priceId }],
  });
  await awaitOfflinePayment(publicApi, event.eventId, reserved.short_id, sessionId);
  await api.markOrderAsPaid(event.eventId, await api.findOrderIdByShortId(event.eventId, reserved.short_id));
  markOrderPaidThroughStripe(reserved.short_id);
  const completed = await getPublicOrder(publicApi, event.eventId, reserved.short_id, sessionId);

  return {
    event,
    order: {
      orderShortId: reserved.short_id,
      sessionId,
      buyerEmail,
      buyerFirstName: buyer.first_name,
      buyerLastName: buyer.last_name,
      attendees: (completed.attendees ?? []).map((attendee) => ({ shortId: attendee.short_id, publicId: attendee.public_id })),
      totalGross: completed.total_gross,
    },
  };
}
