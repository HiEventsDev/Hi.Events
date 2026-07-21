import type { ApiClient } from './api-client';
import type { EventType, ProductPriceType } from './types';
import { uniqueName } from '../utils/unique';

export interface SeededEvent {
  eventId: number;
  slug: string;
  title: string;
  productId: number;
  productTitle: string;
}

interface SeedOptions {
  organizerId: number;
  price?: number;
  productType?: ProductPriceType;
  eventType?: EventType;
  category?: string;
  title?: string;
  productTitle?: string;
}

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

  const categories = await api.listProductCategories(event.id);
  const categoryId = categories[0].id;

  const product = await api.createProduct(event.id, {
    title: productTitle,
    product_type: 'TICKET',
    type: productType,
    product_category_id: categoryId,
    prices: [{ price }],
  });

  await api.publishEvent(event.id);

  return { eventId: event.id, slug: event.slug, title, productId: product.id, productTitle };
}

export interface SeededDraftEvent {
  eventId: number;
  slug: string;
  title: string;
}

export async function createDraftEvent(
  api: ApiClient,
  organizerId: number,
  opts: { title?: string } = {},
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

  const product = await api.createProduct(eventId, {
    title: productTitle,
    product_type: 'TICKET',
    type: price > 0 ? 'PAID' : 'FREE',
    product_category_id: categoryId,
    prices: [{ price }],
  });

  return { eventId, slug, title, productId: product.id, productTitle, categoryId };
}

export const createLiveEventWithFreeTicket = (api: ApiClient, organizerId: number): Promise<SeededEvent> =>
  createLiveEventWithProduct(api, { organizerId, price: 0 });

export const createLiveEventWithPaidTicket = (
  api: ApiClient,
  organizerId: number,
  price = 25,
): Promise<SeededEvent> => createLiveEventWithProduct(api, { organizerId, price });
