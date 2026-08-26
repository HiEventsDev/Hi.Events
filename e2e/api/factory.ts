import type { APIRequestContext } from '@playwright/test';
import type { ApiClient } from './api-client';
import type {
  AttendeeDetailsCollection,
  EventType,
  Occurrence,
  Organizer,
  ProductPriceType,
  PublicOrder,
  QuestionRecord,
} from './types';
import {
  awaitOfflinePayment,
  completePublicOrder,
  createPublicOrder,
  getPublicOrder,
  type QuestionAnswer,
} from './public-client';
import { uniqueEmail, uniqueName } from '../utils/unique';

export interface SeededEvent {
  eventId: number;
  slug: string;
  title: string;
  productId: number;
  productTitle: string;
  priceId: number;
}

interface SeedOptions {
  organizerId: number;
  price?: number;
  productType?: ProductPriceType;
  eventType?: EventType;
  category?: string;
  title?: string;
  productTitle?: string;
  quantityAvailable?: number;
  waitlistEnabled?: boolean;
  taxIds?: number[];
  prices?: { price: number; label?: string }[];
  attendeeDetails?: AttendeeDetailsCollection;
}

export const setAttendeeDetailsCollection = (
  api: ApiClient,
  eventId: number,
  method: AttendeeDetailsCollection,
): Promise<void> => api.updateEventSettings(eventId, { attendee_details_collection_method: method });

const futureStartDate = (): string => {
  const date = new Date();
  date.setDate(date.getDate() + 30);
  date.setHours(21, 0, 0, 0);
  return date.toISOString();
};

export async function createLiveEventWithProduct(api: ApiClient, opts: SeedOptions): Promise<SeededEvent> {
  const {
    organizerId,
    price = 0,
    productType = price > 0 ? 'PAID' : 'FREE',
    eventType = 'SINGLE',
    category = 'MUSIC',
    title = uniqueName('E2E Event'),
    productTitle = productType === 'FREE' ? 'Free Ticket' : 'Paid Ticket',
  } = opts;

  const event = await api.createEvent({
    title,
    type: eventType,
    organizer_id: organizerId,
    start_date: futureStartDate(),
    category,
    currency: 'USD',
    timezone: 'UTC',
  });

  if (eventType === 'SINGLE') {
    await setAttendeeDetailsCollection(api, event.id, opts.attendeeDetails ?? 'PER_TICKET');
  }

  const categories = await api.listProductCategories(event.id);
  const categoryId = categories[0].id;

  const created = await api.createProduct(event.id, {
    title: productTitle,
    product_type: 'TICKET',
    type: productType,
    product_category_id: categoryId,
    prices: (opts.prices ?? [{ price }]).map((priceEntry) => ({
      ...(opts.quantityAvailable !== undefined ? { initial_quantity_available: opts.quantityAvailable } : {}),
      ...priceEntry,
    })),
    ...(opts.waitlistEnabled !== undefined ? { waitlist_enabled: opts.waitlistEnabled } : {}),
    ...(opts.taxIds ? { tax_and_fee_ids: opts.taxIds } : {}),
  });

  const product = await api.getProduct(event.id, created.id);
  const priceId = product.prices?.[0]?.id;
  if (!priceId) {
    throw new Error(`Product ${created.id} has no prices in GET response`);
  }

  await api.publishEvent(event.id);

  return { eventId: event.id, slug: event.slug, title, productId: created.id, productTitle, priceId };
}

export interface SeededDraftEvent {
  eventId: number;
  slug: string;
  title: string;
}

export async function createDraftEvent(
  api: ApiClient,
  organizerId: number,
  opts: { title?: string; attendeeDetails?: AttendeeDetailsCollection } = {},
): Promise<SeededDraftEvent> {
  const title = opts.title ?? uniqueName('E2E Event');
  const event = await api.createEvent({
    title,
    type: 'SINGLE',
    organizer_id: organizerId,
    start_date: futureStartDate(),
    category: 'MUSIC',
    currency: 'USD',
    timezone: 'UTC',
  });
  await setAttendeeDetailsCollection(api, event.id, opts.attendeeDetails ?? 'PER_TICKET');
  return { eventId: event.id, slug: event.slug, title };
}

export async function createDraftEventWithTicket(
  api: ApiClient,
  organizerId: number,
  opts: { title?: string; productTitle?: string; price?: number } = {},
): Promise<SeededEvent & { categoryId: number }> {
  const { eventId, slug, title } = await createDraftEvent(api, organizerId, opts);
  const categories = await api.listProductCategories(eventId);
  const categoryId = categories[0].id;
  const price = opts.price ?? 0;
  const productTitle = opts.productTitle ?? 'General Admission';

  const created = await api.createProduct(eventId, {
    title: productTitle,
    product_type: 'TICKET',
    type: price > 0 ? 'PAID' : 'FREE',
    product_category_id: categoryId,
    prices: [{ price }],
  });

  const product = await api.getProduct(eventId, created.id);
  const priceId = product.prices?.[0]?.id;
  if (!priceId) {
    throw new Error(`Product ${created.id} has no prices in GET response`);
  }

  return { eventId, slug, title, productId: created.id, productTitle, categoryId, priceId };
}

export const createLiveEventWithFreeTicket = (api: ApiClient, organizerId: number): Promise<SeededEvent> =>
  createLiveEventWithProduct(api, { organizerId, price: 0 });

export const createLiveEventWithPaidTicket = (
  api: ApiClient,
  organizerId: number,
  price = 25,
): Promise<SeededEvent> => createLiveEventWithProduct(api, { organizerId, price });

export interface SeededOrder {
  orderShortId: string;
  sessionId: string;
  buyerEmail: string;
  buyerFirstName: string;
  buyerLastName: string;
  attendees: { shortId: string; publicId: string }[];
}

export interface OrderSeedOptions {
  buyerEmail?: string;
  buyerFirstName?: string;
  buyerLastName?: string;
  quantity?: number;
  promoCode?: string;
  affiliateCode?: string;
  eventOccurrenceId?: number;
  orderQuestions?: QuestionAnswer[];
  attendeeQuestions?: QuestionAnswer[];
}

const mapAttendees = (order: PublicOrder): { shortId: string; publicId: string }[] =>
  (order.attendees ?? []).map((attendee) => ({ shortId: attendee.short_id, publicId: attendee.public_id }));

export async function createReservedOrder(
  publicApi: APIRequestContext,
  event: Pick<SeededEvent, 'eventId' | 'productId' | 'priceId'>,
  opts: OrderSeedOptions = {},
): Promise<{ orderShortId: string; sessionId: string }> {
  const order = await createPublicOrder(publicApi, event.eventId, {
    products: [
      {
        product_id: event.productId,
        quantities: [{ price_id: event.priceId, quantity: opts.quantity ?? 1 }],
        ...(opts.eventOccurrenceId ? { event_occurrence_id: opts.eventOccurrenceId } : {}),
      },
    ],
    promoCode: opts.promoCode,
    affiliateCode: opts.affiliateCode,
  });
  if (!order.session_identifier) {
    throw new Error(`Created order ${order.short_id} has no session_identifier in the response`);
  }
  return { orderShortId: order.short_id, sessionId: order.session_identifier };
}

export async function createCompletedOrder(
  publicApi: APIRequestContext,
  event: Pick<SeededEvent, 'eventId' | 'productId' | 'priceId'>,
  opts: OrderSeedOptions = {},
): Promise<SeededOrder> {
  const buyerEmail = opts.buyerEmail ?? uniqueEmail('buyer');
  const buyerFirstName = opts.buyerFirstName ?? 'Test';
  const buyerLastName = opts.buyerLastName ?? 'Buyer';
  const quantity = opts.quantity ?? 1;

  const { orderShortId, sessionId } = await createReservedOrder(publicApi, event, opts);

  await completePublicOrder(publicApi, event.eventId, orderShortId, sessionId, {
    order: {
      first_name: buyerFirstName,
      last_name: buyerLastName,
      email: buyerEmail,
      ...(opts.orderQuestions ? { questions: opts.orderQuestions } : {}),
    },
    attendees: Array.from({ length: quantity }, () => ({
      product_id: event.productId,
      product_price_id: event.priceId,
      first_name: buyerFirstName,
      last_name: buyerLastName,
      email: buyerEmail,
      ...(opts.attendeeQuestions ? { questions: opts.attendeeQuestions } : {}),
    })),
  });
  const completed = await getPublicOrder(publicApi, event.eventId, orderShortId, sessionId);

  return {
    orderShortId,
    sessionId,
    buyerEmail,
    buyerFirstName,
    buyerLastName,
    attendees: mapAttendees(completed),
  };
}

export const OFFLINE_PAYMENT_INSTRUCTIONS = 'Pay by bank transfer to account 12345678 within 5 days.';

export async function enableOfflinePayments(api: ApiClient, eventId: number): Promise<void> {
  await api.updateEventSettings(eventId, {
    payment_providers: ['OFFLINE'],
    offline_payment_instructions: OFFLINE_PAYMENT_INSTRUCTIONS,
  });
}

export async function createAwaitingOfflineOrder(
  api: ApiClient,
  publicApi: APIRequestContext,
  event: Pick<SeededEvent, 'eventId' | 'productId' | 'priceId'>,
  opts: OrderSeedOptions = {},
): Promise<SeededOrder & { orderId: number }> {
  await enableOfflinePayments(api, event.eventId);
  const seeded = await createCompletedOrder(publicApi, event, opts);
  await awaitOfflinePayment(publicApi, event.eventId, seeded.orderShortId, seeded.sessionId);
  const orderId = await api.findOrderIdByShortId(event.eventId, seeded.orderShortId);
  return { ...seeded, orderId };
}

export async function createCompletedPaidOrder(
  api: ApiClient,
  publicApi: APIRequestContext,
  event: Pick<SeededEvent, 'eventId' | 'productId' | 'priceId'>,
  opts: OrderSeedOptions = {},
): Promise<SeededOrder & { orderId: number; totalGross: number }> {
  const seeded = await createAwaitingOfflineOrder(api, publicApi, event, opts);
  await api.markOrderAsPaid(event.eventId, seeded.orderId);
  const completed = await getPublicOrder(publicApi, event.eventId, seeded.orderShortId, seeded.sessionId);
  return { ...seeded, attendees: mapAttendees(completed), totalGross: completed.total_gross };
}

export async function createSoldOutEvent(
  api: ApiClient,
  publicApi: APIRequestContext,
  organizerId: number,
  opts: { waitlist?: boolean; title?: string } = {},
): Promise<SeededEvent & { consumedOrder: SeededOrder }> {
  const event = await createLiveEventWithProduct(api, {
    organizerId,
    price: 0,
    title: opts.title,
    quantityAvailable: 1,
    waitlistEnabled: opts.waitlist ?? false,
  });
  const consumedOrder = await createCompletedOrder(publicApi, event);
  return { ...event, consumedOrder };
}

export async function createRecurringLiveEvent(
  api: ApiClient,
  organizerId: number,
  opts: { count?: number; price?: number; title?: string } = {},
): Promise<SeededEvent & { occurrences: Occurrence[] }> {
  const count = opts.count ?? 3;
  const price = opts.price ?? 0;
  const title = opts.title ?? uniqueName('E2E Recurring');

  const event = await api.createEvent({
    title,
    type: 'RECURRING',
    organizer_id: organizerId,
    start_date: futureStartDate(),
    category: 'MUSIC',
    currency: 'USD',
    timezone: 'UTC',
  });

  await api.generateOccurrences(event.id, {
    frequency: 'weekly',
    range: { type: 'count', count },
    days_of_week: ['friday'],
    times_of_day: ['19:00'],
    duration_minutes: 120,
  });
  const occurrences = await api.listOccurrences(event.id);

  const categories = await api.listProductCategories(event.id);
  const created = await api.createProduct(event.id, {
    title: price > 0 ? 'Paid Ticket' : 'Free Ticket',
    product_type: 'TICKET',
    type: price > 0 ? 'PAID' : 'FREE',
    product_category_id: categories[0].id,
    prices: [{ price }],
  });
  const product = await api.getProduct(event.id, created.id);
  const priceId = product.prices?.[0]?.id;
  if (!priceId) {
    throw new Error(`Product ${created.id} has no prices in GET response`);
  }

  await api.publishEvent(event.id);

  return {
    eventId: event.id,
    slug: event.slug,
    title,
    productId: created.id,
    productTitle: created.title,
    priceId,
    occurrences,
  };
}

export async function createEventWithQuestions(
  api: ApiClient,
  organizerId: number,
  opts: { orderQuestionTitle?: string; attendeeQuestionTitle?: string } = {},
): Promise<SeededEvent & { orderQuestion: QuestionRecord; attendeeQuestion: QuestionRecord }> {
  const event = await createLiveEventWithProduct(api, { organizerId, price: 0 });
  const orderQuestion = await api.createQuestion(event.eventId, {
    title: opts.orderQuestionTitle ?? 'How did you hear about us?',
    type: 'SINGLE_LINE_TEXT',
    belongs_to: 'ORDER',
    product_ids: [],
    required: true,
    is_hidden: false,
  });
  const attendeeQuestion = await api.createQuestion(event.eventId, {
    title: opts.attendeeQuestionTitle ?? 'Shirt size',
    type: 'RADIO',
    belongs_to: 'PRODUCT',
    product_ids: [event.productId],
    options: ['Small', 'Medium', 'Large'],
    required: true,
    is_hidden: false,
  });
  return { ...event, orderQuestion, attendeeQuestion };
}

export async function createEventWithAttendee(
  api: ApiClient,
  organizerId: number,
  opts: { attendeeEmail?: string; live?: boolean } = {},
): Promise<SeededEvent & { attendeeEmail: string; attendeeId: number }> {
  const event = await createLiveEventWithProduct(api, { organizerId, price: 0 });
  const attendeeEmail = opts.attendeeEmail ?? uniqueEmail('attendee');
  const attendee = await api.createAttendee(event.eventId, {
    product_id: event.productId,
    product_price_id: event.priceId,
    email: attendeeEmail,
    first_name: 'Seeded',
    last_name: 'Attendee',
    amount_paid: 0,
    send_confirmation_email: false,
    locale: 'en',
  });
  return { ...event, attendeeEmail, attendeeId: attendee.id };
}

export function createFreshOrganizer(api: ApiClient, name?: string): Promise<Organizer> {
  return api.createOrganizer(name ?? uniqueName('E2E Org'), { email: uniqueEmail('organizer') });
}
