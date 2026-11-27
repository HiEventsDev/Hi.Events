import { runSql } from './db';

const SHORT_ID = /^[A-Za-z0-9_]+$/;

export interface ResaleListingSeed {
  status: 'LISTED' | 'SOLD' | 'CLOSED';
  estimatedRefund?: number;
  soldOrderShortId?: string;
  refundStatus?: 'PENDING' | 'SUCCEEDED' | 'FAILED';
  refundAmount?: number;
  closedReason?: 'WITHDRAWN' | 'CUTOFF_PASSED' | 'CLOSED_BY_ORGANIZER';
}

const sqlText = (value: string | undefined): string => (value === undefined ? 'NULL' : `'${value}'`);

const sqlNumber = (value: number | undefined): string => {
  if (value === undefined) {
    return 'NULL';
  }
  if (!Number.isFinite(value)) {
    throw new Error(`Refusing to seed a resale listing with an unexpected amount: ${value}`);
  }
  return String(value);
};

export function seedResaleListing(attendeeShortId: string, listing: ResaleListingSeed): number {
  if (!SHORT_ID.test(attendeeShortId) || (listing.soldOrderShortId !== undefined && !SHORT_ID.test(listing.soldOrderShortId))) {
    throw new Error(`Refusing to seed a resale listing for an unexpected ticket: ${attendeeShortId}`);
  }
  const soldOrderId = listing.soldOrderShortId === undefined
    ? 'NULL'
    : `(SELECT id FROM orders WHERE short_id = '${listing.soldOrderShortId}')`;
  const inserted = runSql(
    `WITH seller AS (
       UPDATE attendees SET status = ${listing.status === 'SOLD' ? "'CANCELLED'" : 'status'}
       WHERE short_id = '${attendeeShortId}'
       RETURNING id, event_id, order_id, product_price_id, event_occurrence_id
     )
     INSERT INTO ticket_resale_listings (
       event_id, order_id, attendee_id, product_price_id, event_occurrence_id, status, min_unit_price, estimated_refund,
       sold_order_id, refund_amount, refund_status, closed_reason, created_at, updated_at
     )
     SELECT seller.event_id, seller.order_id, seller.id, seller.product_price_id, seller.event_occurrence_id,
            '${listing.status}', ${sqlNumber(listing.estimatedRefund ?? 38.4)}, ${sqlNumber(listing.estimatedRefund ?? 38.4)},
            ${soldOrderId}, ${sqlNumber(listing.refundAmount)}, ${sqlText(listing.refundStatus)}, ${sqlText(listing.closedReason)},
            NOW(), NOW()
     FROM seller RETURNING id`,
  ).trim();
  if (inserted === '') {
    throw new Error(`No ticket found with short id ${attendeeShortId}`);
  }
  return Number(inserted);
}

export function resaleRefundStatus(listingId: number): string {
  if (!Number.isInteger(listingId)) {
    throw new Error(`Refusing to read an unexpected resale listing: ${listingId}`);
  }
  return runSql(`SELECT refund_status FROM ticket_resale_listings WHERE id = ${listingId}`).trim();
}

export function settleResaleRefund(listingId: number): void {
  if (!Number.isInteger(listingId)) {
    throw new Error(`Refusing to settle an unexpected resale listing: ${listingId}`);
  }
  runSql(`UPDATE ticket_resale_listings SET refund_status = 'SUCCEEDED' WHERE id = ${listingId}`);
}
