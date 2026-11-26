import { createHash, randomBytes } from 'node:crypto';
import type { APIRequest, APIRequestContext, APIResponse } from '@playwright/test';
import { expect, type FreshAccount } from '../../fixtures';
import type { ApiClient } from '../../api/api-client';
import { createReservedOrder, type SeededEvent } from '../../api/factory';
import { completePublicOrder } from '../../api/public-client';
import { API_BASE_URL } from '../../utils/env';

export interface Buyer {
  email: string;
  firstName: string;
  lastName: string;
  phone?: string;
  agreesToTexts?: boolean;
}

export const US_MOBILE = '+12015550123';
export const UK_MOBILE = '+447400123456';
export const fakeTwilioAccount = (): string => `AC${randomBytes(16).toString('hex')}`;
export const fakeMessagingServiceOf = (accountSid: string): string => `MG${createHash('md5').update(accountSid).digest('hex')}`;

export const askForMobileAtCheckout = (api: ApiClient, eventId: number, collection: 'OPTIONAL' | 'REQUIRED' = 'OPTIONAL'): Promise<void> =>
  api.updateEventSettings(eventId, { phone_collection: collection, show_sms_opt_in: true });

export const buildAudience = async (api: ApiClient, organizerId: number): Promise<void> => {
  await api.startAudienceSync(organizerId);
  await expect.poll(async () => (await api.getAudienceSync(organizerId)).status, { timeout: 30_000 }).toBe('READY');
};

export const waitForContact = (api: ApiClient, organizerId: number, email: string) =>
  expect.poll(async () => (await api.findAudienceContacts(organizerId, email)).length, { timeout: 30_000 }).toBeGreaterThan(0);

export const marketingStatusOf = async (api: ApiClient, organizerId: number, email: string): Promise<string | undefined> =>
  (await api.findAudienceContacts(organizerId, email))[0]?.email_marketing_status;

export const createOptedInOrder = async (publicApi: APIRequestContext, event: SeededEvent, buyer: Buyer): Promise<void> => {
  const { orderShortId, sessionId } = await createReservedOrder(publicApi, event);
  await completePublicOrder(publicApi, event.eventId, orderShortId, sessionId, {
    order: {
      first_name: buyer.firstName,
      last_name: buyer.lastName,
      email: buyer.email,
      opted_into_marketing: true,
      ...(buyer.phone ? { phone: buyer.phone, sms_opted_in: buyer.agreesToTexts ?? false } : {}),
    } as never,
    attendees: [{
      product_id: event.productId,
      product_price_id: event.priceId,
      first_name: buyer.firstName,
      last_name: buyer.lastName,
      email: buyer.email,
    }],
  });
};

export const ok = async (pending: Promise<APIResponse>): Promise<APIResponse> => {
  const response = await pending;
  if (!response.ok()) {
    throw new Error(`API ${response.url()} → ${response.status()}: ${await response.text()}`);
  }
  return response;
};

export const dataOf = async <T>(pending: Promise<APIResponse>): Promise<T> => ((await (await ok(pending)).json()) as { data: T }).data;

export interface SignupForm {
  id: number;
  public_id: string;
}

export interface SegmentRule {
  field: string;
  operator: string;
  value: number | string | number[] | string[] | null;
}

export const SMS_AUTH_TOKEN = 'e2e-token';

export class AudienceApi {
  constructor(private readonly request: APIRequestContext, private readonly organizerId: number) {}

  private path(section: string): string {
    return `organizers/${this.organizerId}/crm/${section}`;
  }

  async createContact(contact: { email: string; first_name: string; last_name: string }): Promise<void> {
    await ok(this.request.post(this.path('contacts'), { data: contact }));
  }

  async importSuppressions(fileName: string, csv: string): Promise<void> {
    await ok(this.request.post(this.path('imports'), {
      multipart: { kind: 'SUPPRESSIONS', file: { name: fileName, mimeType: 'text/csv', buffer: Buffer.from(csv) } },
    }));
  }

  async smsProviderIsFaked(): Promise<boolean> {
    const accountSid = fakeTwilioAccount();
    const response = await this.request.post(this.path('sms-connection/senders'), {
      data: { account_sid: accountSid, auth_token: SMS_AUTH_TOKEN },
    });
    if (!response.ok()) {
      return false;
    }
    const senders = ((await response.json()) as { data: { sid: string }[] }).data;
    return senders.some((sender) => sender.sid === fakeMessagingServiceOf(accountSid));
  }
}

export const withOrganizerApi = async <T>(
  playwright: { request: APIRequest },
  account: FreshAccount,
  run: (request: APIRequestContext) => Promise<T>,
): Promise<T> => {
  const context = await playwright.request.newContext({
    baseURL: API_BASE_URL,
    ignoreHTTPSErrors: true,
    extraHTTPHeaders: { Accept: 'application/json', Authorization: `Bearer ${account.token}` },
  });
  try {
    return await run(context);
  } finally {
    await context.dispose();
  }
};

export const withAudienceApi = <T>(
  playwright: { request: APIRequest },
  account: FreshAccount,
  run: (audienceApi: AudienceApi) => Promise<T>,
): Promise<T> => withOrganizerApi(playwright, account, (request) => run(new AudienceApi(request, account.organizerId)));

export const submitSignupForm = async (publicApi: APIRequestContext, publicId: string, email: string): Promise<void> => {
  await ok(publicApi.post(`public/crm/forms/${publicId}/submissions`, { data: { email } }));
};
