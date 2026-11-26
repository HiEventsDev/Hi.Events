import { runSql } from './db';

const EMAIL = /^[A-Za-z0-9._+-]+@[A-Za-z0-9.-]+$/;
const SHORT_ID = /^[A-Za-z0-9_]+$/;

const assertId = (value: number, what: string): void => {
  if (!Number.isInteger(value) || value <= 0) {
    throw new Error(`Refusing to touch an unexpected ${what} id: ${value}`);
  }
};

const assertUpdated = (updated: string, what: string): void => {
  if (updated.trim() === '') {
    throw new Error(`No ${what} found to update`);
  }
};

export function expirePendingSubscription(email: string): void {
  if (!EMAIL.test(email)) {
    throw new Error(`Refusing to expire a signup for an unexpected address: ${email}`);
  }
  assertUpdated(
    runSql(`UPDATE crm_pending_subscriptions SET expires_at = NOW() - INTERVAL '1 hour' WHERE email = '${email.toLowerCase()}' RETURNING id`),
    `pending signup for ${email}`,
  );
}

export function moveScheduledCampaignIntoThePast(campaignId: number, minutesAgo: number): void {
  assertId(campaignId, 'campaign');
  assertId(minutesAgo, 'minutes');
  assertUpdated(
    runSql(`UPDATE crm_campaigns SET scheduled_at = NOW() - INTERVAL '${minutesAgo} minutes' WHERE id = ${campaignId} AND scheduled_at IS NOT NULL RETURNING id`),
    `scheduled campaign ${campaignId}`,
  );
}

export function smsConnectionPublicId(organizerId: number): string {
  assertId(organizerId, 'organizer');
  const publicId = runSql(`SELECT public_id FROM crm_sms_connections WHERE organizer_id = ${organizerId}`).trim();
  if (publicId === '') {
    throw new Error(`Organizer ${organizerId} has no Twilio connection`);
  }
  return publicId;
}

export interface TextRecipient {
  id: number;
  messageId: string | null;
  country: string;
}

export function textRecipients(campaignId: number): TextRecipient[] {
  assertId(campaignId, 'campaign');
  return runSql(`SELECT id, COALESCE(provider_message_id, ''), phone_country FROM crm_campaign_recipients WHERE campaign_id = ${campaignId} ORDER BY id`)
    .trim()
    .split('\n')
    .filter((line) => line !== '')
    .map((line) => {
      const [id, messageId, country] = line.split('|');
      return { id: Number(id), messageId: messageId || null, country };
    });
}

export function backdateAutomationSwitchOn(organizerId: number, hours: number): void {
  assertId(organizerId, 'organizer');
  assertId(hours, 'hours');
  assertUpdated(
    runSql(`UPDATE crm_automations SET enabled_at = NOW() - INTERVAL '${hours} hours' WHERE organizer_id = ${organizerId} AND is_enabled RETURNING id`),
    `switched-on automation for organizer ${organizerId}`,
  );
}

export function moveLastDailyRunToYesterday(organizerId: number): void {
  assertId(organizerId, 'organizer');
  runSql(`UPDATE crm_automations SET last_run_at = last_run_at - INTERVAL '1 day' WHERE organizer_id = ${organizerId} AND last_run_at IS NOT NULL`);
}

const AUTOMATION_FAILURE = /^[A-Z_]+$/;

export function recordAutomationFailure(organizerId: number, code: string): void {
  assertId(organizerId, 'organizer');
  if (!AUTOMATION_FAILURE.test(code)) {
    throw new Error(`Refusing to record an unexpected automation failure: ${code}`);
  }
  assertUpdated(
    runSql(`UPDATE crm_automations SET last_failure_reason = '{"code":"${code}","params":{}}' WHERE organizer_id = ${organizerId} AND is_enabled RETURNING id`),
    `switched-on automation for organizer ${organizerId}`,
  );
}

export function settlePublishedEvent(eventId: number): void {
  assertId(eventId, 'event');
  assertUpdated(
    runSql(`UPDATE events SET updated_at = NOW() - INTERVAL '45 minutes' WHERE id = ${eventId} RETURNING id`),
    `event ${eventId}`,
  );
}

export function endEventDatesAnHourAgo(eventId: number): void {
  assertId(eventId, 'event');
  assertUpdated(
    runSql(`UPDATE event_occurrences SET start_date = NOW() - INTERVAL '3 hours', end_date = NOW() - INTERVAL '1 hour' WHERE event_id = ${eventId} RETURNING id`),
    `date for event ${eventId}`,
  );
}

export function abandonCheckoutHoursAgo(orderShortId: string, hours: number): void {
  if (!SHORT_ID.test(orderShortId)) {
    throw new Error(`Refusing to abandon an order with an unexpected short id: ${orderShortId}`);
  }
  assertId(hours, 'hours');
  assertUpdated(
    runSql(`UPDATE orders SET reserved_until = NOW() - INTERVAL '${hours} hours' WHERE short_id = '${orderShortId}' RETURNING id`),
    `order ${orderShortId}`,
  );
}
