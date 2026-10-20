import type { APIRequestContext, APIResponse } from '@playwright/test';
import type { PublicOrder } from './types';

const jsonHeaders = { 'Content-Type': 'application/json', Accept: 'application/json' };

const unwrap = async <T>(promise: Promise<APIResponse>): Promise<T> => {
  const response = await promise;
  if (!response.ok()) {
    throw new Error(`Public API ${response.url()} → ${response.status()}: ${await response.text()}`);
  }
  const body = (await response.json()) as { data: T };
  return body.data;
};

export interface PublicOrderLine {
  product_id: number;
  quantities: { price_id: number; quantity: number; price?: number }[];
  event_occurrence_id?: number;
}

export interface QuestionAnswer {
  question_id: number;
  response: { answer: string | string[] };
}

export interface BuyerInfo {
  first_name: string;
  last_name: string;
  email: string;
}

export interface AttendeeEntry extends BuyerInfo {
  product_id: number;
  product_price_id: number;
  questions?: QuestionAnswer[];
}

export function createPublicOrder(
  request: APIRequestContext,
  eventId: number,
  opts: { products: PublicOrderLine[]; promoCode?: string; affiliateCode?: string },
): Promise<PublicOrder> {
  return unwrap<PublicOrder>(
    request.post(`public/events/${eventId}/order`, {
      headers: jsonHeaders,
      data: {
        products: opts.products,
        ...(opts.promoCode ? { promo_code: opts.promoCode } : {}),
        ...(opts.affiliateCode ? { affiliate_code: opts.affiliateCode } : {}),
      },
    }),
  );
}

export function completePublicOrder(
  request: APIRequestContext,
  eventId: number,
  orderShortId: string,
  sessionId: string,
  opts: { order: BuyerInfo & { questions?: QuestionAnswer[] }; attendees: AttendeeEntry[] },
): Promise<PublicOrder> {
  return unwrap<PublicOrder>(
    request.put(`public/events/${eventId}/order/${orderShortId}?session_identifier=${sessionId}`, {
      headers: jsonHeaders,
      data: {
        order: { ...opts.order, email_confirmation: opts.order.email },
        products: opts.attendees.map((attendee) => ({ ...attendee, email_confirmation: attendee.email })),
      },
    }),
  );
}

export function getPublicOrder(
  request: APIRequestContext,
  eventId: number,
  orderShortId: string,
  sessionId?: string,
): Promise<PublicOrder> {
  const query = sessionId ? `?session_identifier=${sessionId}` : '';
  return unwrap<PublicOrder>(
    request.get(`public/events/${eventId}/order/${orderShortId}${query}`, { headers: jsonHeaders }),
  );
}

export function awaitOfflinePayment(
  request: APIRequestContext,
  eventId: number,
  orderShortId: string,
  sessionId: string,
): Promise<PublicOrder> {
  return unwrap<PublicOrder>(
    request.post(
      `public/events/${eventId}/order/${orderShortId}/await-offline-payment?session_identifier=${sessionId}`,
      { headers: jsonHeaders },
    ),
  );
}

export async function joinWaitlist(
  request: APIRequestContext,
  eventId: number,
  payload: {
    product_price_id: number;
    email: string;
    first_name: string;
    last_name?: string;
    event_occurrence_id?: number;
  },
): Promise<void> {
  const response = await request.post(`public/events/${eventId}/waitlist`, { headers: jsonHeaders, data: payload });
  if (!response.ok()) {
    throw new Error(`join waitlist → ${response.status()}: ${await response.text()}`);
  }
}

export async function sendTicketLookupEmail(request: APIRequestContext, email: string): Promise<void> {
  const response = await request.post('public/ticket-lookup', { headers: jsonHeaders, data: { email } });
  if (!response.ok()) {
    throw new Error(`ticket lookup → ${response.status()}: ${await response.text()}`);
  }
}

export interface DoorOrder {
  id: number;
  short_id: string;
  public_id: string;
  status: string;
  total_gross: number;
  box_office_tender?: string | null;
}

export interface DoorSaleItem {
  product_id: number;
  product_price_id: number;
  quantity: number;
}

export async function startBoxOfficeSession(
  request: APIRequestContext,
  boxOfficeShortId: string,
  opts: { pin: string; operatorName: string; eventOccurrenceId?: number },
): Promise<string> {
  const session = await unwrap<{ token: string }>(
    request.post(`public/box-offices/${boxOfficeShortId}/sessions`, {
      headers: jsonHeaders,
      data: {
        operator_name: opts.operatorName,
        pin: opts.pin,
        ...(opts.eventOccurrenceId ? { event_occurrence_id: opts.eventOccurrenceId } : {}),
      },
    }),
  );
  return session.token;
}

export function createBoxOfficeOrder(
  request: APIRequestContext,
  boxOfficeShortId: string,
  token: string,
  items: DoorSaleItem[],
): Promise<DoorOrder> {
  return unwrap<DoorOrder>(
    request.post(`public/box-offices/${boxOfficeShortId}/orders`, {
      headers: { ...jsonHeaders, 'X-Box-Office-Session': token },
      data: { idempotency_key: crypto.randomUUID(), items },
    }),
  );
}

export function tenderBoxOfficeOrder(
  request: APIRequestContext,
  boxOfficeShortId: string,
  token: string,
  orderShortId: string,
  payload: { tender: 'CASH' | 'COMP' | 'OTHER'; amount_tendered?: number; reference?: string },
): Promise<DoorOrder> {
  return unwrap<DoorOrder>(
    request.post(`public/box-offices/${boxOfficeShortId}/orders/${orderShortId}/tender`, {
      headers: { ...jsonHeaders, 'X-Box-Office-Session': token },
      data: payload,
    }),
  );
}
