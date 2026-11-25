import type { APIRequest, APIRequestContext, APIResponse, Locator, Page } from '@playwright/test';
import { test as base, expect, type FreshAccount } from '../../fixtures';
import type { AdminApiClient, ApiClient } from '../../api/api-client';
import { smsConnectionPublicId } from '../../utils/audience-db';
import { postTwilioWebhook } from '../../utils/twilio';
import { dataOf, fakeMessagingServiceOf, fakeTwilioAccount, ok, SMS_AUTH_TOKEN, withOrganizerApi, type SegmentRule, type SignupForm } from './audience.shared';

export const IE_MOBILE = '+353851234567';
export const AU_MOBILE = '+61412345678';

export type { SegmentRule };
export type SmsCountry = 'US' | 'GB' | 'IE' | 'AU';
export type AutomationTrigger = 'EVENT_PUBLISHED' | 'AFTER_EVENT' | 'BEFORE_EVENT' | 'ABANDONED_CHECKOUT' | 'SEGMENT_ENTERED' | 'WIN_BACK' | 'WELCOME';

export interface CampaignDraft {
  name: string;
  subject?: string | null;
  body: string;
  channel?: 'EMAIL' | 'SMS';
  segment_id?: number | null;
  preset?: string | null;
  event_id?: number | null;
  early_access_product_ids?: number[];
}

export interface Campaign {
  id: number;
  status: string;
  status_reason: string | null;
  scheduled_at: string | null;
  recipients_count: number;
  promo_code: string | null;
  stats: { sent: number; pending: number; delivered: number; skipped: number; failed: number };
}

export interface Automation {
  id: number;
  name: string;
  trigger: AutomationTrigger;
  is_enabled: boolean;
  sent_count: number;
  last_failure_reason: string | null;
}

export interface SmsCountryEstimate {
  country: SmsCountry;
  recipients: number;
  parts: number;
  is_sending_now: boolean;
  next_sending_at: string;
}

export class MessagingApi {
  constructor(private readonly request: APIRequestContext, private readonly organizerId: number) {}

  private path(section: string): string {
    return `organizers/${this.organizerId}/crm/${section}`;
  }

  createSegment(name: string, rules: SegmentRule[], match: 'all' | 'any' = 'all'): Promise<{ id: number; name: string }> {
    return dataOf(this.request.post(this.path('segments'), { data: { name, definition: { match, rules } } }));
  }

  createTag(name: string): Promise<{ id: number; name: string }> {
    return dataOf(this.request.post(this.path('tags'), { data: { name } }));
  }

  async tagContact(contactId: number, tagIds: number[]): Promise<void> {
    await ok(this.request.put(this.path(`contacts/${contactId}/tags`), { data: { tag_ids: tagIds } }));
  }

  async unsubscribeContact(contactId: number): Promise<void> {
    await ok(this.request.post(this.path(`contacts/${contactId}/unsubscribe`), { data: { channel: 'EMAIL' } }));
  }

  createCampaign(draft: CampaignDraft): Promise<Campaign> {
    return dataOf(this.request.post(this.path('campaigns'), {
      data: {
        subject: null,
        preheader: null,
        segment_id: null,
        preset: null,
        event_id: null,
        early_access_product_ids: [],
        channel: 'EMAIL',
        ...draft,
      },
    }));
  }

  sendCampaign(campaignId: number, scheduledAt: string | null = null): Promise<Campaign> {
    return dataOf(this.request.post(this.path(`campaigns/${campaignId}/send`), { data: { scheduled_at: scheduledAt } }));
  }

  async smsEstimate(campaign: { id: number; body: string }): Promise<SmsCountryEstimate[]> {
    const preview = await dataOf<{ estimate: { countries: SmsCountryEstimate[] } | null }>(
      this.request.post(this.path('campaigns/sms-preview'), {
        data: { body: campaign.body, event_id: null, early_access_product_ids: [], campaign_id: campaign.id },
      }),
    );
    return preview.estimate?.countries ?? [];
  }

  createTemplate(template: { name: string; subject: string; body: string }): Promise<{ id: number }> {
    return dataOf(this.request.post(this.path('templates'), { data: { preheader: null, ...template } }));
  }

  automatedCampaigns(): Promise<Campaign[]> {
    return dataOf(this.request.get(this.path('campaigns'), { params: { source: 'automation' } }));
  }

  automations(): Promise<Automation[]> {
    return dataOf(this.request.get(this.path('automations')));
  }

  async createAutomation(trigger: AutomationTrigger, overrides: Record<string, unknown> = {}): Promise<Automation> {
    const templates = await dataOf<Record<string, unknown>[]>(this.request.get(this.path('automations/templates')));
    const template = templates.find((candidate) => candidate.trigger === trigger);
    if (!template) {
      throw new Error(`No automation template for ${trigger}`);
    }
    return dataOf(this.request.post(this.path('automations'), {
      data: { preheader: null, segment_id: null, ...template, ...overrides },
    }));
  }

  async switchOnAutomation(automationId: number): Promise<void> {
    await ok(this.request.post(this.path(`automations/${automationId}/enable`)));
  }

  async automationNamed(name: string): Promise<Automation> {
    const automation = (await this.automations()).find((candidate) => candidate.name === name);
    if (!automation) {
      throw new Error(`No automation named ${name}`);
    }
    return automation;
  }

  async connectSms(countries: SmsCountry[]): Promise<void> {
    const accountSid = fakeTwilioAccount();
    await ok(this.request.put(this.path('sms-connection'), {
      data: {
        account_sid: accountSid,
        auth_token: SMS_AUTH_TOKEN,
        messaging_service_sid: fakeMessagingServiceOf(accountSid),
        enabled_countries: countries,
      },
    }));
  }

  async disconnectSms(): Promise<void> {
    await ok(this.request.delete(this.path('sms-connection')));
  }

  async createSignupForm(form: { name: string; title: string; show_on_organizer_page?: boolean; status?: 'ACTIVE' | 'PAUSED' }): Promise<SignupForm> {
    return dataOf(this.request.post(this.path('forms'), {
      data: {
        status: 'ACTIVE',
        description: null,
        button_label: 'Subscribe',
        success_message: null,
        asks_for_name: false,
        privacy_url: null,
        tag_ids: [],
        show_on_organizer_page: false,
        ...form,
      },
    }));
  }
}

export const withMessagingApi = <T>(
  playwright: { request: APIRequest },
  account: FreshAccount,
  run: (messaging: MessagingApi) => Promise<T>,
): Promise<T> => withOrganizerApi(playwright, account, (request) => run(new MessagingApi(request, account.organizerId)));

export const test = base.extend<{ messaging: MessagingApi }>({
  messaging: async ({ playwright, freshAccount }, use) => {
    await withMessagingApi(playwright, freshAccount, use);
  },
});

export const approveIfHeldForReview = async (adminApi: AdminApiClient, api: ApiClient, organizerId: number, campaignId: number): Promise<void> => {
  if ((await api.getAudienceCampaign(organizerId, campaignId)).status === 'PENDING_REVIEW') {
    await adminApi.approveAudienceCampaign(campaignId);
  }
};

export const waitForCampaignStatus = (api: ApiClient, organizerId: number, campaignId: number, status: RegExp): Promise<void> =>
  expect.poll(async () => (await api.getAudienceCampaign(organizerId, campaignId)).status, { timeout: 45_000 }).toMatch(status);

export const contactIdOf = async (api: ApiClient, organizerId: number, email: string): Promise<number> => {
  await expect.poll(async () => (await api.findAudienceContacts(organizerId, email)).length, { timeout: 30_000 }).toBeGreaterThan(0);
  return (await api.findAudienceContacts(organizerId, email))[0].id;
};

export const replyToTexts = (
  publicApi: APIRequestContext,
  organizerId: number,
  reply: { from: string; body: string; authToken?: string },
): Promise<APIResponse> =>
  postTwilioWebhook(
    publicApi,
    { publicId: smsConnectionPublicId(organizerId), kind: 'inbound', authToken: reply.authToken ?? SMS_AUTH_TOKEN },
    { From: reply.from, Body: reply.body },
  );

export const reportTextDelivery = (
  publicApi: APIRequestContext,
  organizerId: number,
  report: { messageId: string; status: 'delivered' | 'undelivered' | 'failed'; authToken?: string },
): Promise<APIResponse> =>
  postTwilioWebhook(
    publicApi,
    { publicId: smsConnectionPublicId(organizerId), kind: 'status', authToken: report.authToken ?? SMS_AUTH_TOKEN },
    { MessageSid: report.messageId, MessageStatus: report.status },
  );

export const automationCard = (page: Page, name: string): Locator => page.getByTestId('automation-card').filter({ hasText: name });

export const automationSwitch = (page: Page, name: string): Locator => automationCard(page, name).getByRole('switch', { name, exact: true });

export const tomorrow = (): string => new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' ');
