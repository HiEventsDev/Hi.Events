import type { APIRequestContext, APIResponse } from '@playwright/test';
import type { AdminApiClient } from '../../api/api-client';
import { expect } from '../../fixtures';
import { runAutomations, runScheduledJob } from '../../utils/artisan';
import { runSql } from '../../utils/db';
import { API_BASE_URL, MAILPIT_URL } from '../../utils/env';
import type { MailpitClient } from '../../utils/mailpit';
import type { MessagingApi } from './messaging.shared';

export const emailsAbout = async (mailpit: MailpitClient, to: string, subject: string): Promise<number> =>
  (await mailpit.search(to)).filter((message) => message.Subject.includes(subject)).length;

export interface AutomatedEmail {
  messaging: MessagingApi;
  adminApi: AdminApiClient;
  mailpit: MailpitClient;
  to: string;
  subject: string;
}

export const runAutomationsAndApproveRuns = async (messaging: MessagingApi, adminApi: AdminApiClient): Promise<void> => {
  runAutomations();
  for (const campaign of await messaging.automatedCampaigns()) {
    if (campaign.status === 'PENDING_REVIEW') {
      await adminApi.approveAudienceCampaign(campaign.id);
    }
  }
};

export const expectOneAutomatedEmail = async ({ messaging, adminApi, mailpit, to, subject }: AutomatedEmail): Promise<void> => {
  await expect(async () => {
    await runAutomationsAndApproveRuns(messaging, adminApi);
    expect(await emailsAbout(mailpit, to, subject)).toBe(1);
  }).toPass({ timeout: 45_000, intervals: [2_000, 4_000] });
};

export const expectStillOneAfterAnotherRun = async ({ messaging, adminApi, mailpit, to, subject }: AutomatedEmail): Promise<void> => {
  await runAutomationsAndApproveRuns(messaging, adminApi);
  await expect.poll(async () => (await messaging.automatedCampaigns()).every((campaign) => campaign.status === 'SENT'), { timeout: 45_000 }).toBe(true);
  expect(await emailsAbout(mailpit, to, subject)).toBe(1);
};

const EMAIL = /^[A-Za-z0-9._+-]+@[A-Za-z0-9.-]+$/;

const assertId = (value: number, what: string): void => {
  if (!Number.isInteger(value) || value <= 0) {
    throw new Error(`Refusing to touch an unexpected ${what} id: ${value}`);
  }
};

const EMAIL_DELIVERY_WEBHOOK_URL = process.env.E2E_EMAIL_DELIVERY_WEBHOOK_URL ?? `${API_BASE_URL}public/webhooks/email-delivery/postmark`;
export const EMAIL_DELIVERY_WEBHOOK_SECRET = process.env.E2E_POSTMARK_WEBHOOK_SECRET ?? '';

export type PostmarkRecord =
  | { RecordType: 'Bounce'; MessageID: string; Type: 'HardBounce'; Inactive: true; BouncedAt: string }
  | { RecordType: 'SpamComplaint'; MessageID: string; BouncedAt: string };

export const postEmailDeliveryWebhook = (publicApi: APIRequestContext, secret: string, record: PostmarkRecord | Record<string, never>): Promise<APIResponse> =>
  publicApi.post(EMAIL_DELIVERY_WEBHOOK_URL, {
    data: record,
    headers: { Authorization: `Basic ${Buffer.from(`postmark:${secret}`).toString('base64')}` },
  });

export const emailDeliveryReportsAreReceived = async (publicApi: APIRequestContext): Promise<boolean> =>
  (await postEmailDeliveryWebhook(publicApi, 'not-the-secret', {})).status() !== 404;

export function emailMessageIdOf(campaignId: number, email: string): string {
  assertId(campaignId, 'campaign');
  if (!EMAIL.test(email)) {
    throw new Error(`Refusing to look up a recipient with an unexpected address: ${email}`);
  }
  const messageId = runSql(`SELECT provider_message_id FROM crm_campaign_recipients WHERE campaign_id = ${campaignId} AND email = '${email.toLowerCase()}'`).trim();
  if (messageId === '') {
    throw new Error(`Campaign ${campaignId} has no sent email to ${email}`);
  }
  return messageId;
}

export const recordCampaignFeedback = (): void => runScheduledJob('HiEvents\\Enterprise\\Crm\\Jobs\\RecordCampaignFeedbackJob');

const ZONES_ROUND_THE_CLOCK = [
  'Pacific/Kiritimati', 'Pacific/Tongatapu', 'Pacific/Auckland', 'Asia/Magadan', 'Australia/Brisbane', 'Asia/Tokyo', 'Asia/Singapore',
  'Asia/Bangkok', 'Asia/Dhaka', 'Asia/Karachi', 'Asia/Dubai', 'Europe/Moscow', 'Africa/Johannesburg', 'Africa/Lagos', 'UTC',
  'Atlantic/Cape_Verde', 'America/Noronha', 'America/Sao_Paulo', 'America/Halifax', 'America/New_York', 'America/Chicago',
  'America/Denver', 'America/Los_Angeles', 'America/Anchorage', 'Pacific/Honolulu', 'Pacific/Pago_Pago',
];

const hourIn = (timezone: string): number =>
  Number(new Intl.DateTimeFormat('en-GB', { timeZone: timezone, hour: '2-digit', hourCycle: 'h23' }).format(new Date()));

export function timezoneWhereItIsMidday(): string {
  const timezone = ZONES_ROUND_THE_CLOCK.find((zone) => hourIn(zone) === 12);
  if (!timezone) {
    throw new Error('No timezone is at midday right now');
  }
  return timezone;
}

const TIMEZONE = /^[A-Za-z_]+(\/[A-Za-z_]+)?$/;

export function startEventDatesTomorrowEveningIn(eventId: number, timezone: string): void {
  assertId(eventId, 'event');
  if (!TIMEZONE.test(timezone)) {
    throw new Error(`Refusing to use an unexpected timezone: ${timezone}`);
  }
  const startsAt = `((date_trunc('day', NOW() AT TIME ZONE '${timezone}') + INTERVAL '1 day 19 hours') AT TIME ZONE '${timezone}') AT TIME ZONE 'UTC'`;
  const updated = runSql(`
    WITH zoned AS (UPDATE events SET timezone = '${timezone}' WHERE id = ${eventId} RETURNING id)
    UPDATE event_occurrences
    SET start_date = ${startsAt}, end_date = ${startsAt} + INTERVAL '3 hours', created_at = NOW() - INTERVAL '2 days'
    WHERE event_id IN (SELECT id FROM zoned) RETURNING id`);
  if (updated.trim() === '') {
    throw new Error(`No date found for event ${eventId}`);
  }
}

export async function oneClickUnsubscribeUrlOf(request: APIRequestContext, mailpit: MailpitClient, to: string, subject: string): Promise<string> {
  const message = await mailpit.waitForMessage(to, { subjectContains: subject, timeoutMs: 45_000 });
  const response = await request.get(`${MAILPIT_URL}/api/v1/message/${message.ID}/headers`);
  expect(response.ok()).toBe(true);
  const headers = (await response.json()) as Record<string, string[]>;
  expect(headers['List-Unsubscribe-Post']).toEqual(['List-Unsubscribe=One-Click']);
  const url = /^<(https?:\/\/[^>]+)>$/.exec(headers['List-Unsubscribe']?.[0] ?? '')?.[1];
  if (!url) {
    throw new Error(`The email "${message.Subject}" to ${to} has no one-click unsubscribe address`);
  }
  return url;
}
