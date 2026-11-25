import type { MailpitClient } from './mailpit';
import { runSql } from './db';

const EMAIL = /^[A-Za-z0-9._+-]+@[A-Za-z0-9.-]+$/;

export const maskedEmail = (email: string): string => `${email[0]}•••@${email.split('@')[1]}`;

export const waitForClaimLink = (mailpit: MailpitClient, recipientEmail: string): Promise<URL> =>
  mailpit.waitForLink(recipientEmail, /\/transfer\/tt_/, { subjectContains: 'You have been sent a ticket' });

export function expirePendingTransferTo(recipientEmail: string): void {
  if (!EMAIL.test(recipientEmail)) {
    throw new Error(`Refusing to expire a transfer for an unexpected address: ${recipientEmail}`);
  }
  const updated = runSql(
    `UPDATE ticket_transfers SET expires_at = NOW() - INTERVAL '1 minute' WHERE recipient_email = '${recipientEmail.toLowerCase()}' AND status = 'PENDING' RETURNING id`,
  ).trim();
  if (updated === '') {
    throw new Error(`No pending transfer found for ${recipientEmail}`);
  }
}

const SHORT_ID = /^[A-Za-z0-9_]+$/;

export function seedCancelledTransfers(attendeeShortId: string, count: number): void {
  if (!SHORT_ID.test(attendeeShortId) || !Number.isInteger(count) || count < 1 || count > 20) {
    throw new Error(`Refusing to seed transfers for ${attendeeShortId} x ${count}`);
  }
  runSql(
    `INSERT INTO ticket_transfers (event_id, order_id, attendee_id, sender_email, recipient_email, token_hash, status, expires_at, created_at, updated_at)
     SELECT a.event_id, a.order_id, a.id, 'seed@hievents.test', 'seeded-' || n || '@hievents.test', md5(random()::text || n) || md5(random()::text), 'CANCELLED', NOW() + INTERVAL '1 day', NOW() - INTERVAL '1 hour', NOW()
     FROM attendees a CROSS JOIN generate_series(1, ${count}) AS n WHERE a.short_id = '${attendeeShortId}'`,
  );
}

export function markProductNotTransferable(productId: number): void {
  if (!Number.isInteger(productId)) {
    throw new Error(`Refusing to update an unexpected product id: ${productId}`);
  }
  runSql(`UPDATE products SET is_transferable = false WHERE id = ${productId}`);
}
