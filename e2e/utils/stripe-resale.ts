import { expect, type APIRequestContext, type APIResponse, type Page } from '@playwright/test';
import type { ApiClient } from '../api/api-client';
import { createCompletedOrder, createLiveEventWithProduct, type OrderSeedOptions, type SeededEvent, type SeededOrder } from '../api/factory';
import { getPublicOrder } from '../api/public-client';
import {
  enableStripePayments,
  listPaymentIntentRefunds,
  payOrderWithTestCard,
  sendChargeSucceededWebhook,
  sendPaymentIntentSucceededWebhook,
  sendRefundUpdatedWebhooks,
  STRIPE_CARD_PAYING_INTO_AVAILABLE_BALANCE,
  type StripeRefund,
} from '../api/stripe';
import type { EventSettings } from '../api/types';
import { composeFile, runSql } from './db';
import type { MailpitClient } from './mailpit';
import { execFileSync } from 'node:child_process';

const SHORT_ID = /^[A-Za-z0-9_]+$/;
const jsonHeaders = { 'Content-Type': 'application/json', Accept: 'application/json' };

export const RESALE_TICKET_PRICE = 40;
export const STRIPE_CARD_NUMBER_PAYING_INTO_AVAILABLE_BALANCE = '4000000000000077';
export const SETTLE_TIMEOUT = 60_000;

type OrderLink = Pick<SeededOrder, 'orderShortId' | 'sessionId'>;
type Sellable = Pick<SeededEvent, 'eventId' | 'productId' | 'priceId'>;

export interface CardPaidOrder extends SeededOrder {
  orderId: number;
  paymentIntentId: string;
  amountPaidMinor: number;
}

export interface SellerSession {
  token: string;
  expires_at: string;
}

export interface ResaleState {
  can_list: boolean;
  status: 'LISTED' | 'SOLD' | null;
  held_until: string | null;
  withdrawal_requested: boolean;
  cutoff: string | null;
}

export interface ListingRow {
  id: number;
  status: string;
  closedReason: string | null;
  refundStatus: string | null;
  refundAmountMinor: number | null;
  stripeRefundId: string | null;
  claimedByOrder: string | null;
  soldOrder: string | null;
  withdrawalRequested: boolean;
}

const assertShortId = (shortId: string): void => {
  if (!SHORT_ID.test(shortId)) {
    throw new Error(`Unexpected short id: ${shortId}`);
  }
};

export async function createResaleEvent(
  api: ApiClient,
  organizerId: number,
  opts: Partial<Parameters<typeof createLiveEventWithProduct>[1]> & { settings?: Partial<EventSettings> } = {},
): Promise<SeededEvent> {
  const { settings, ...seed } = opts;
  const event = await createLiveEventWithProduct(api, {
    organizerId,
    price: RESALE_TICKET_PRICE,
    showQuantityRemaining: true,
    ...seed,
  });
  await api.updateEventSettings(event.eventId, { allow_ticket_resale: true, pass_platform_fee_to_buyer: false, ...settings });
  enableStripePayments(organizerId);
  return event;
}

async function deliverPaymentWebhooks(publicApi: APIRequestContext, paymentIntentId: string): Promise<void> {
  await sendPaymentIntentSucceededWebhook(publicApi, paymentIntentId);
  await sendChargeSucceededWebhook(publicApi, paymentIntentId);
}

export async function payReservedOrderByCard(
  publicApi: APIRequestContext,
  eventId: number,
  order: OrderLink,
): Promise<{ paymentIntentId: string; amount: number }> {
  const payment = await payOrderWithTestCard(publicApi, {
    eventId,
    orderShortId: order.orderShortId,
    sessionId: order.sessionId,
    paymentMethod: STRIPE_CARD_PAYING_INTO_AVAILABLE_BALANCE,
  });
  await deliverPaymentWebhooks(publicApi, payment.paymentIntentId);
  return payment;
}

export async function waitForOrderStatus(
  publicApi: APIRequestContext,
  eventId: number,
  order: OrderLink,
  status = 'COMPLETED',
): Promise<void> {
  await expect
    .poll(async () => (await getPublicOrder(publicApi, eventId, order.orderShortId, order.sessionId)).status, { timeout: SETTLE_TIMEOUT })
    .toBe(status);
}

export async function createCardPaidOrder(
  api: ApiClient,
  publicApi: APIRequestContext,
  event: Sellable,
  opts: OrderSeedOptions = {},
): Promise<CardPaidOrder> {
  const order = await createCompletedOrder(publicApi, event, opts);
  const { paymentIntentId, amount } = await payReservedOrderByCard(publicApi, event.eventId, order);
  await waitForOrderStatus(publicApi, event.eventId, order);
  const completed = await getPublicOrder(publicApi, event.eventId, order.orderShortId, order.sessionId);
  return {
    ...order,
    attendees: (completed.attendees ?? []).map((attendee) => ({ shortId: attendee.short_id, publicId: attendee.public_id })),
    orderId: await api.findOrderIdByShortId(event.eventId, order.orderShortId),
    paymentIntentId,
    amountPaidMinor: amount,
  };
}

export async function resaleStates(publicApi: APIRequestContext, eventId: number, order: Pick<SeededOrder, 'orderShortId'>): Promise<(ResaleState | undefined)[]> {
  const response = await publicApi.get(`public/events/${eventId}/order/${order.orderShortId}?include=event`, { headers: jsonHeaders });
  if (!response.ok()) {
    throw new Error(`get order with resale state → ${response.status()}: ${await response.text()}`);
  }
  const { data } = (await response.json()) as { data: { attendees?: { resale?: ResaleState }[] } };
  return (data.attendees ?? []).map((attendee) => attendee.resale);
}

export async function waitUntilResellable(publicApi: APIRequestContext, eventId: number, order: OrderLink): Promise<void> {
  await expect
    .poll(async () => (await resaleStates(publicApi, eventId, order)).some((resale) => resale?.can_list), { timeout: SETTLE_TIMEOUT })
    .toBe(true);
}

export async function startSellerSession(
  publicApi: APIRequestContext,
  mailpit: MailpitClient,
  eventId: number,
  order: Pick<SeededOrder, 'orderShortId' | 'buyerEmail'>,
): Promise<SellerSession> {
  const base = `public/events/${eventId}/order/${order.orderShortId}/verification`;
  const requested = await publicApi.post(base, { headers: jsonHeaders });
  if (!requested.ok()) {
    throw new Error(`request verification → ${requested.status()}: ${await requested.text()}`);
  }
  const code = await mailpit.waitForSelfServiceCode(order.buyerEmail);
  const confirmed = await publicApi.post(`${base}/confirm`, { headers: jsonHeaders, data: { code } });
  if (!confirmed.ok()) {
    throw new Error(`confirm verification → ${confirmed.status()}: ${await confirmed.text()}`);
  }
  return ((await confirmed.json()) as { data: SellerSession }).data;
}

export async function adoptSellerSession(page: Page, eventId: number, orderShortId: string, session: SellerSession): Promise<void> {
  await page.addInitScript(
    ({ key, value }) => window.sessionStorage.setItem(key, value),
    { key: `hi_self_service_${eventId}_o_${orderShortId}`, value: JSON.stringify(session) },
  );
}

const resaleUrl = (eventId: number, orderShortId: string, attendeeShortId: string): string =>
  `public/events/${eventId}/order/${orderShortId}/attendees/${attendeeShortId}/resale`;

export async function listTicketForResale(
  publicApi: APIRequestContext,
  eventId: number,
  orderShortId: string,
  attendeeShortId: string,
  session: SellerSession,
): Promise<ResaleState> {
  const response = await publicApi.post(resaleUrl(eventId, orderShortId, attendeeShortId), {
    headers: { ...jsonHeaders, 'X-Self-Service-Token': session.token },
  });
  if (!response.ok()) {
    throw new Error(`list for resale → ${response.status()}: ${await response.text()}`);
  }
  return ((await response.json()) as { data: ResaleState }).data;
}

export async function withdrawListing(
  publicApi: APIRequestContext,
  eventId: number,
  orderShortId: string,
  attendeeShortId: string,
  session: SellerSession,
): Promise<ResaleState> {
  const response = await publicApi.delete(resaleUrl(eventId, orderShortId, attendeeShortId), {
    headers: { ...jsonHeaders, 'X-Self-Service-Token': session.token },
  });
  if (!response.ok()) {
    throw new Error(`withdraw listing → ${response.status()}: ${await response.text()}`);
  }
  return ((await response.json()) as { data: ResaleState }).data;
}

export function attemptOrder(
  publicApi: APIRequestContext,
  target: Sellable,
  opts: { quantity?: number; eventOccurrenceId?: number; promoCode?: string } = {},
): Promise<APIResponse> {
  return publicApi.post(`public/events/${target.eventId}/order`, {
    headers: jsonHeaders,
    data: {
      products: [{
        product_id: target.productId,
        quantities: [{ price_id: target.priceId, quantity: opts.quantity ?? 1 }],
        ...(opts.eventOccurrenceId ? { event_occurrence_id: opts.eventOccurrenceId } : {}),
      }],
      ...(opts.promoCode ? { promo_code: opts.promoCode } : {}),
    },
  });
}

export async function expectOrderRefusedAsSoldOut(response: APIResponse): Promise<void> {
  expect(response.ok(), await response.text()).toBe(false);
  expect(await response.text()).toMatch(/sold out|available/i);
}

export interface SoldOutResaleEvent {
  event: SeededEvent;
  seller: CardPaidOrder;
  session: SellerSession;
}

export async function createSoldOutEventWithListings(
  api: ApiClient,
  publicApi: APIRequestContext,
  mailpit: MailpitClient,
  organizerId: number,
  opts: {
    quantity?: number;
    sellerTickets?: number;
    listed?: number;
    unsold?: number;
    sellerLastName?: string;
    event?: Parameters<typeof createResaleEvent>[2];
  } = {},
): Promise<SoldOutResaleEvent> {
  const sellerTickets = opts.sellerTickets ?? 1;
  const quantity = opts.quantity ?? sellerTickets;
  const otherTickets = quantity - sellerTickets - (opts.unsold ?? 0);
  const event = await createResaleEvent(api, organizerId, { quantityAvailable: quantity, ...opts.event });

  const seller = await createCardPaidOrder(api, publicApi, event, {
    quantity: sellerTickets,
    buyerFirstName: 'Sally',
    buyerLastName: opts.sellerLastName ?? 'Seller',
  });
  if (otherTickets > 0) {
    await createCardPaidOrder(api, publicApi, event, { quantity: otherTickets, buyerFirstName: 'Otto', buyerLastName: 'Other' });
  }
  await waitUntilResellable(publicApi, event.eventId, seller);

  const session = await startSellerSession(publicApi, mailpit, event.eventId, seller);
  for (const ticket of seller.attendees.slice(0, opts.listed ?? 1)) {
    await listTicketForResale(publicApi, event.eventId, seller.orderShortId, ticket.shortId, session);
  }
  return { event, seller, session };
}

export function listingFor(attendeeShortId: string): ListingRow {
  assertShortId(attendeeShortId);
  const row = runSql(
    `SELECT row_to_json(found) FROM (
       SELECT listing.id, listing.status, listing.closed_reason, listing.refund_status,
              ROUND(listing.refund_amount * 100)::int AS refund_minor, listing.stripe_refund_id,
              (SELECT short_id FROM orders WHERE id = listing.claimed_by_order_id) AS claimed_by,
              (SELECT short_id FROM orders WHERE id = listing.sold_order_id) AS sold_to,
              listing.withdrawal_requested_at IS NOT NULL AS withdrawal_requested
         FROM ticket_resale_listings listing
         JOIN attendees ON attendees.id = listing.attendee_id
        WHERE attendees.short_id = '${attendeeShortId}'
        ORDER BY listing.id DESC LIMIT 1
     ) found`,
  ).trim();
  if (row === '') {
    throw new Error(`No resale listing found for attendee ${attendeeShortId}`);
  }
  const found = JSON.parse(row) as {
    id: number; status: string; closed_reason: string | null; refund_status: string | null; refund_minor: number | null;
    stripe_refund_id: string | null; claimed_by: string | null; sold_to: string | null; withdrawal_requested: boolean;
  };
  return {
    id: found.id,
    status: found.status,
    closedReason: found.closed_reason,
    refundStatus: found.refund_status,
    refundAmountMinor: found.refund_minor,
    stripeRefundId: found.stripe_refund_id,
    claimedByOrder: found.claimed_by,
    soldOrder: found.sold_to,
    withdrawalRequested: found.withdrawal_requested,
  };
}

export function orderRefundState(orderShortId: string): { refundStatus: string | null; totalRefundedMinor: number } {
  assertShortId(orderShortId);
  const [refundStatus, totalRefunded] = runSql(
    `SELECT COALESCE(refund_status, ''), ROUND(total_refunded * 100)::int FROM orders WHERE short_id = '${orderShortId}'`,
  ).trim().split('|');
  return { refundStatus: refundStatus === '' ? null : refundStatus, totalRefundedMinor: Number(totalRefunded) };
}

export async function waitForSellerRefund(
  publicApi: APIRequestContext,
  seller: Pick<CardPaidOrder, 'paymentIntentId'>,
  attendeeShortId: string,
): Promise<{ listing: ListingRow; refund: StripeRefund }> {
  await expect.poll(() => listingFor(attendeeShortId).stripeRefundId, { timeout: SETTLE_TIMEOUT }).not.toBeNull();
  const listing = listingFor(attendeeShortId);
  const refund = (await listPaymentIntentRefunds(publicApi, seller.paymentIntentId)).find((candidate) => candidate.id === listing.stripeRefundId);
  if (!refund) {
    throw new Error(`Stripe has no refund ${listing.stripeRefundId} on the seller's payment ${seller.paymentIntentId}`);
  }
  return { listing, refund };
}

export async function settleSellerRefunds(
  publicApi: APIRequestContext,
  seller: Pick<CardPaidOrder, 'paymentIntentId'>,
  attendeeShortId: string,
): Promise<ListingRow> {
  await sendRefundUpdatedWebhooks(publicApi, seller.paymentIntentId);
  await expect.poll(() => listingFor(attendeeShortId).refundStatus, { timeout: SETTLE_TIMEOUT }).toBe('SUCCEEDED');
  return listingFor(attendeeShortId);
}

export function runResaleMaintenance(): string {
  return execFileSync(
    'docker',
    ['compose', '-f', composeFile(), 'exec', '-T', 'backend', 'php', 'artisan', 'ticket-resale:maintain'],
    { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] },
  );
}

export function moveEventStart(eventId: number, startsInMinutes: number): void {
  if (!Number.isInteger(eventId) || !Number.isInteger(startsInMinutes)) {
    throw new Error(`Refusing to move event ${eventId} to start in ${startsInMinutes} minutes`);
  }
  runSql(
    `UPDATE event_occurrences
        SET start_date = NOW() + INTERVAL '${startsInMinutes} minutes', end_date = NOW() + INTERVAL '${startsInMinutes + 120} minutes'
      WHERE event_id = ${eventId}`,
  );
}

export function holdEndsByResaleCutoff(orderShortId: string, cutoffMinutes: number): boolean {
  assertShortId(orderShortId);
  if (!Number.isInteger(cutoffMinutes)) {
    throw new Error(`Unexpected cutoff: ${cutoffMinutes}`);
  }
  return runSql(
    `SELECT orders.reserved_until > NOW() AND orders.reserved_until <= MIN(dates.start_date) - INTERVAL '${cutoffMinutes} minutes'
       FROM orders JOIN event_occurrences dates ON dates.event_id = orders.event_id
      WHERE orders.short_id = '${orderShortId}' GROUP BY orders.id`,
  ).trim() === 't';
}

export async function scanTicket(
  publicApi: APIRequestContext,
  checkInListShortId: string,
  attendeePublicId: string,
): Promise<{ checkedIn: boolean; errors: string[] }> {
  const response = await publicApi.post(`public/check-in-lists/${checkInListShortId}/check-ins`, {
    headers: jsonHeaders,
    data: { attendees: [{ public_id: attendeePublicId, action: 'check-in' }] },
  });
  const body = (await response.json()) as { data?: unknown[]; errors?: Record<string, string>; message?: string };
  return {
    checkedIn: response.ok() && (body.data ?? []).length > 0,
    errors: [...Object.values(body.errors ?? {}), ...(body.message ? [body.message] : [])],
  };
}

export async function soldCount(api: ApiClient, event: Pick<SeededEvent, 'eventId' | 'productId' | 'priceId'>): Promise<number | undefined> {
  const product = await api.getProduct(event.eventId, event.productId);
  return product.prices?.find((price) => price.id === event.priceId)?.quantity_sold;
}
