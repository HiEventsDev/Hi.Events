import type { APIRequestContext } from '@playwright/test';
import type {
  CreateEventPayload,
  CreateProductPayload,
  EventRecord,
  Me,
  Organizer,
  ProductCategory,
  ProductRecord,
  RegisterPayload,
} from './types';

const jsonHeaders = { 'Content-Type': 'application/json', Accept: 'application/json' };

const unwrap = async <T>(promise: Promise<import('@playwright/test').APIResponse>): Promise<T> => {
  const response = await promise;
  if (!response.ok()) {
    throw new Error(`API ${response.url()} → ${response.status()}: ${await response.text()}`);
  }
  const body = (await response.json()) as { data: T };
  return body.data;
};

export async function registerAccount(request: APIRequestContext, payload: RegisterPayload): Promise<void> {
  const response = await request.post('auth/register', { headers: jsonHeaders, data: payload });
  if (!response.ok()) {
    throw new Error(`register → ${response.status()}: ${await response.text()}`);
  }
}

export interface LoginResult {
  token: string;
  user: Me;
}

export async function login(request: APIRequestContext, email: string, password: string): Promise<LoginResult> {
  const response = await request.post('auth/login', { headers: jsonHeaders, data: { email, password } });
  if (!response.ok()) {
    throw new Error(`login → ${response.status()}: ${await response.text()}`);
  }
  const body = (await response.json()) as { token?: string; user?: Me };
  if (!body.token || !body.user) {
    throw new Error('login succeeded but response was missing token/user');
  }
  return { token: body.token, user: body.user };
}

export async function confirmEmailWithCode(
  request: APIRequestContext,
  token: string,
  userId: number,
  code: string,
): Promise<void> {
  const response = await request.post(`users/${userId}/confirm-email-with-code`, {
    headers: { ...jsonHeaders, Authorization: `Bearer ${token}` },
    data: { code },
  });
  if (!response.ok()) {
    throw new Error(`confirm-email-with-code → ${response.status()}: ${await response.text()}`);
  }
}

export class ApiClient {
  constructor(private readonly request: APIRequestContext) {}

  createOrganizer(name: string, opts: { email?: string; currency?: string; timezone?: string } = {}): Promise<Organizer> {
    return unwrap<Organizer>(
      this.request.post('organizers', {
        headers: jsonHeaders,
        data: {
          name,
          email: opts.email ?? 'organizer@hievents.test',
          currency: opts.currency ?? 'USD',
          timezone: opts.timezone ?? 'UTC',
        },
      }),
    );
  }

  createEvent(payload: CreateEventPayload): Promise<EventRecord> {
    return unwrap<EventRecord>(this.request.post('events', { headers: jsonHeaders, data: payload }));
  }

  listProductCategories(eventId: number): Promise<ProductCategory[]> {
    return unwrap<ProductCategory[]>(this.request.get(`events/${eventId}/product-categories`, { headers: jsonHeaders }));
  }

  createProduct(eventId: number, payload: CreateProductPayload): Promise<ProductRecord> {
    return unwrap<ProductRecord>(
      this.request.post(`events/${eventId}/products`, { headers: jsonHeaders, data: payload }),
    );
  }

  async publishEvent(eventId: number): Promise<void> {
    const response = await this.request.put(`events/${eventId}/status`, {
      headers: jsonHeaders,
      data: { status: 'LIVE' },
    });
    if (!response.ok()) {
      throw new Error(`publish event ${eventId} → ${response.status()}: ${await response.text()}`);
    }
  }
}
