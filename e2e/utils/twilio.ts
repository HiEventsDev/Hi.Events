import { createHmac } from 'node:crypto';
import type { APIRequestContext, APIResponse } from '@playwright/test';
import { evaluatePhp } from './artisan';
import { runSql } from './db';
import { API_BASE_URL } from './env';

type TwilioWebhookKind = 'inbound' | 'status';

const PUBLIC_ID = /^[A-Za-z0-9_-]+$/;

const signTwilioRequest = (authToken: string, url: string, params: Record<string, string>): string =>
  createHmac('sha1', authToken)
    .update(url + Object.keys(params).sort().map((key) => key + params[key]).join(''))
    .digest('base64');

const webhookPath = (publicId: string, kind: TwilioWebhookKind): string => {
  if (!PUBLIC_ID.test(publicId)) {
    throw new Error(`Refusing to build a webhook address for an unexpected connection id: ${publicId}`);
  }
  return `public/webhooks/sms/twilio/${publicId}/${kind}`;
};

export async function postTwilioWebhook(
  publicApi: APIRequestContext,
  webhook: { publicId: string; kind: TwilioWebhookKind; authToken: string },
  params: Record<string, string>,
): Promise<APIResponse> {
  const url = `${API_BASE_URL}${webhookPath(webhook.publicId, webhook.kind)}`;
  return publicApi.post(url, {
    form: params,
    headers: { 'X-Twilio-Signature': signTwilioRequest(webhook.authToken, url, params) },
  });
}

export const FAKE_TWILIO_NUMBERS = {
  getsTheAccountRefused: '+12015550190',
  rejected: '+12015550191',
  optedOutAtTwilio: '+12015550192',
};

export interface SentText {
  messageId: string;
  body: string;
}

const PHONE = /^\+[0-9]{8,15}$/;
const ACCOUNT_SID = /^AC[0-9a-f]{32}$/;

export function sentTexts(organizerId: number, phone: string): SentText[] {
  if (!Number.isInteger(organizerId) || !PHONE.test(phone)) {
    throw new Error(`Refusing to read texts sent to an unexpected number ${phone} for organizer ${organizerId}`);
  }
  const accountSid = runSql(`SELECT account_sid FROM crm_sms_connections WHERE organizer_id = ${organizerId}`).trim();
  if (!ACCOUNT_SID.test(accountSid)) {
    throw new Error(`Organizer ${organizerId} has no fake Twilio connection`);
  }
  const sent = JSON.parse(evaluatePhp(
    `json_encode(app(\\HiEvents\\Enterprise\\Crm\\Services\\Domain\\Sms\\SmsProviderInterface::class)->sentTo('${accountSid}', '${phone}'))`,
  )) as { message_id: string; body: string }[];
  return sent.map((text) => ({ messageId: text.message_id, body: text.body }));
}

export function deliverWaitingText(recipientId: number, country: string): void {
  if (!Number.isInteger(recipientId) || !/^[A-Z]{2}$/.test(country)) {
    throw new Error(`Refusing to deliver an unexpected text ${recipientId} to ${country}`);
  }
  evaluatePhp(
    `(function () {
      \\Carbon\\Carbon::setTestNow(\\HiEvents\\DomainObjects\\Enums\\SmsCountry::from('${country}')->nextSendingTime(now()));
      app(\\HiEvents\\Enterprise\\Crm\\Services\\Domain\\Sms\\SmsCampaignDeliveryService::class)->deliver(${recipientId});
      return 'delivered';
    })()`,
  );
}
