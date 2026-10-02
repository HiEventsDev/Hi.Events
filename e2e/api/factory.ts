import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
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
  createBoxOfficeOrder,
  createPublicOrder,
  getPublicOrder,
  startBoxOfficeSession,
  tenderBoxOfficeOrder,
  type DoorOrder,
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
  startDate?: string;
  price?: number;
  productType?: ProductPriceType;
  eventType?: EventType;
  category?: string;
  title?: string;
  productTitle?: string;
  productDescription?: string;
  quantityAvailable?: number;
  waitlistEnabled?: boolean;
  showQuantityRemaining?: boolean;
  taxIds?: number[];
  prices?: { price: number; label?: string; initial_quantity_available?: number }[];
  sequentialTierReleaseEnabled?: boolean;
  attendeeDetails?: AttendeeDetailsCollection;
  currency?: string;
}

export const setAttendeeDetailsCollection = (
  api: ApiClient,
  eventId: number,
  method: AttendeeDetailsCollection,
): Promise<void> => api.updateEventSettings(eventId, { attendee_details_collection_method: method });

export const futureStartDate = (): string => {
  const date = new Date();
  date.setDate(date.getDate() + 30);
  date.setHours(21, 0, 0, 0);
  return date.toISOString();
};

const pastStartDate = (): string => {
  const date = new Date();
  date.setDate(date.getDate() - 30);
  date.setHours(21, 0, 0, 0);
  return date.toISOString();
};

const coverImage = (): { name: string; mimeType: string; buffer: Buffer } => ({
  name: 'event-cover.png',
  mimeType: 'image/png',
  buffer: readFileSync(fileURLToPath(new URL('../fixtures/assets/event-cover.png', import.meta.url))),
});

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
    start_date: opts.startDate ?? futureStartDate(),
    category,
    currency: opts.currency ?? 'USD',
    timezone: 'UTC',
  });

  if (eventType === 'SINGLE') {
    await setAttendeeDetailsCollection(api, event.id, opts.attendeeDetails ?? 'PER_TICKET');
  }

  const categories = await api.listProductCategories(event.id);
  const categoryId = categories[0].id;

  const created = await api.createProduct(event.id, {
    title: productTitle,
    ...(opts.productDescription !== undefined ? { description: opts.productDescription } : {}),
    product_type: 'TICKET',
    type: productType,
    product_category_id: categoryId,
    prices: (opts.prices ?? [{ price }]).map((priceEntry) => ({
      ...(opts.quantityAvailable !== undefined ? { initial_quantity_available: opts.quantityAvailable } : {}),
      ...priceEntry,
    })),
    ...(opts.waitlistEnabled !== undefined ? { waitlist_enabled: opts.waitlistEnabled } : {}),
    ...(opts.showQuantityRemaining !== undefined ? { show_quantity_remaining: opts.showQuantityRemaining } : {}),
    ...(opts.sequentialTierReleaseEnabled !== undefined ? { sequential_tier_release_enabled: opts.sequentialTierReleaseEnabled } : {}),
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
  opts: { title?: string; attendeeDetails?: AttendeeDetailsCollection; currency?: string } = {},
): Promise<SeededDraftEvent> {
  const title = opts.title ?? uniqueName('E2E Event');
  const event = await api.createEvent({
    title,
    type: 'SINGLE',
    organizer_id: organizerId,
    start_date: futureStartDate(),
    category: 'MUSIC',
    currency: opts.currency ?? 'USD',
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
  seatUids?: string[];
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
        quantities: [{
          price_id: event.priceId,
          quantity: opts.seatUids?.length ?? opts.quantity ?? 1,
          ...(opts.seatUids ? { seat_uids: opts.seatUids } : {}),
        }],
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
  const quantity = opts.seatUids?.length ?? opts.quantity ?? 1;

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

export async function createPastEventWithCoverImage(
  api: ApiClient,
  organizerId: number,
  opts: { title?: string; eventType?: EventType } = {},
): Promise<SeededEvent> {
  const event = await createLiveEventWithProduct(api, {
    organizerId,
    startDate: pastStartDate(),
    title: opts.title,
    eventType: opts.eventType,
  });

  if (opts.eventType === 'RECURRING') {
    await api.createOccurrence(event.eventId, { start_date: pastStartDate() });
  }

  await api.uploadEventImage(event.eventId, coverImage());

  return event;
}

export async function createRecurringLiveEvent(
  api: ApiClient,
  organizerId: number,
  opts: {
    count?: number;
    price?: number;
    title?: string;
    quantityAvailable?: number;
    quantityAppliesTo?: 'OCCURRENCE' | 'EVENT';
    waitlistEnabled?: boolean;
    showQuantityRemaining?: boolean;
  } = {},
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
    prices: [{
      price,
      ...(opts.quantityAvailable !== undefined ? { initial_quantity_available: opts.quantityAvailable } : {}),
      ...(opts.quantityAppliesTo ? { quantity_applies_to: opts.quantityAppliesTo } : {}),
    }],
    ...(opts.waitlistEnabled !== undefined ? { waitlist_enabled: opts.waitlistEnabled } : {}),
    ...(opts.showQuantityRemaining !== undefined ? { show_quantity_remaining: opts.showQuantityRemaining } : {}),
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

export interface SeededBoxOffice {
  id: number;
  short_id: string;
  name: string;
  is_system_default: boolean;
  has_pin: boolean;
  pin: string;
}

export async function defaultBoxOffice(api: ApiClient, eventId: number): Promise<SeededBoxOffice> {
  const boxOffices = await api.listBoxOffices(eventId);
  const systemDefault = boxOffices.find((boxOffice) => boxOffice.is_system_default);
  if (!systemDefault) {
    throw new Error(`Event ${eventId} has no system default box office`);
  }
  return api.resetBoxOfficePin(eventId, systemDefault.id);
}

export async function createDoorSale(
  request: APIRequestContext,
  boxOffice: SeededBoxOffice,
  event: SeededEvent,
  opts: { operatorName?: string; quantity?: number; tender?: 'CASH' | 'COMP' | 'OTHER'; amountTendered?: number } = {},
): Promise<DoorOrder> {
  const token = await startBoxOfficeSession(request, boxOffice.short_id, {
    pin: boxOffice.pin,
    operatorName: opts.operatorName ?? 'Sam',
  });
  const order = await createBoxOfficeOrder(request, boxOffice.short_id, token, [
    { product_id: event.productId, product_price_id: event.priceId, quantity: opts.quantity ?? 1 },
  ]);
  const tender = opts.tender ?? 'CASH';
  return tenderBoxOfficeOrder(request, boxOffice.short_id, token, order.short_id, {
    tender,
    ...(tender === 'CASH' ? { amount_tendered: opts.amountTendered ?? order.total_gross } : {}),
    ...(tender === 'OTHER' ? { reference: 'Paid on SumUp' } : {}),
  });
}

export interface SeatedEvent {
  eventId: number;
  slug: string;
  productId: number;
  priceId: number;
  occurrenceId: number;
}

export type SeatMapFixture = 'theatre' | 'club' | 'empty';

const seatMapFixture = (name: SeatMapFixture) => JSON.parse(
  readFileSync(fileURLToPath(new URL(`../../backend/tests/Fixtures/seating/${name}.json`, import.meta.url)), 'utf8'),
);

export async function createFixtureSeatMap(api: ApiClient, organizerId: number, layout: SeatMapFixture, name = 'Main auditorium'): Promise<number> {
  return (await api.createSeatMap(organizerId, name, seatMapFixture(layout))).id;
}

export async function attachFixtureSeatMap(
  api: ApiClient,
  organizerId: number,
  eventId: number,
  bandProducts: { band_key: string; products: { product_id: number; price_adjustment?: number }[] }[],
  layout: 'theatre' | 'club' = 'theatre',
): Promise<void> {
  await api.attachSeatMap(eventId, await createFixtureSeatMap(api, organizerId, layout));
  await api.linkSeatMapBands(eventId, bandProducts);
}

export async function createSeatedEvent(
  api: ApiClient,
  organizerId: number,
  opts: { layout?: 'theatre' | 'club'; bandKey?: string; price?: number; recurringCount?: number; currency?: string } = {},
): Promise<SeatedEvent & { occurrences: Occurrence[] }> {
  const price = opts.price ?? 0;
  const event = opts.recurringCount
    ? await createDraftRecurringEvent(api, organizerId, opts.recurringCount)
    : await createDraftEvent(api, organizerId, { currency: opts.currency });
  const [category] = await api.listProductCategories(event.eventId);
  const product = await api.createProduct(event.eventId, {
    title: 'Premium Seat',
    product_type: 'TICKET',
    type: price > 0 ? 'PAID' : 'FREE',
    product_category_id: category.id,
    prices: [{ price }],
  });

  await attachFixtureSeatMap(api, organizerId, event.eventId, [{ band_key: opts.bandKey ?? 'b_premium', products: [{ product_id: product.id }] }], opts.layout);
  await api.publishEvent(event.eventId);
  const occurrences = [...await api.listOccurrences(event.eventId)].sort((a, b) => a.start_date.localeCompare(b.start_date));

  return {
    eventId: event.eventId,
    slug: event.slug,
    productId: product.id,
    priceId: product.prices![0].id!,
    occurrenceId: occurrences[0].id,
    occurrences,
  };
}

async function createDraftRecurringEvent(api: ApiClient, organizerId: number, count: number): Promise<{ eventId: number; slug: string }> {
  const event = await api.createEvent({
    title: uniqueName('E2E Seated Recurring'),
    type: 'RECURRING',
    organizer_id: organizerId,
    start_date: futureStartDate(),
    category: 'MUSIC',
    currency: 'USD',
    timezone: 'UTC',
  });
  await setAttendeeDetailsCollection(api, event.id, 'PER_TICKET');
  await api.generateOccurrences(event.id, {
    frequency: 'weekly',
    range: { type: 'count', count },
    days_of_week: ['friday'],
    times_of_day: ['19:00'],
    duration_minutes: 120,
  });
  return { eventId: event.id, slug: event.slug };
}

export const createSeatedOrder = (
  publicApi: APIRequestContext,
  event: SeatedEvent,
  seatUids: string[],
  opts: Omit<OrderSeedOptions, 'seatUids' | 'quantity'> = {},
): Promise<SeededOrder> => createCompletedOrder(publicApi, event, {
  eventOccurrenceId: event.occurrenceId,
  ...opts,
  seatUids,
});
