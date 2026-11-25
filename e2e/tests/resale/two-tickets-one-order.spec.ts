import { test, expect } from '../../fixtures';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { createCompletedOrder } from '../../api/factory';
import {
  canTakeStripePayments,
  getPaymentIntentFees,
  listPaymentIntentRefunds,
  sendRefundUpdatedWebhooks,
  STRIPE_PAYMENTS_SKIP_REASON,
} from '../../api/stripe';
import { liftResaleThrottles } from '../../utils/resale';
import {
  adoptSellerSession,
  createSoldOutEventWithListings,
  listingFor,
  orderRefundState,
  payReservedOrderByCard,
  RESALE_TICKET_PRICE,
  waitForOrderStatus,
} from '../../utils/stripe-resale';

const REFUND_TIMEOUT = 150_000;

test.describe('reselling several tickets from one order', () => {
  test.skip(!canTakeStripePayments(), STRIPE_PAYMENTS_SKIP_REASON);
  test.beforeEach(() => liftResaleThrottles());

  test('two tickets resold in one new order are refunded separately and the seller can still list the third', { tag: '@stripe' }, async ({ page, api, account, publicApi, mailpit }) => {
    test.setTimeout(300_000);
    const { event, seller, session } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId, {
      sellerTickets: 3,
      listed: 2,
    });
    const listedTickets = seller.attendees.slice(0, 2).map((ticket) => ticket.shortId);
    const refundIds = (): string[] => listedTickets.map((ticket) => listingFor(ticket).stripeRefundId).filter((id): id is string => id !== null);

    const buyerOrder = await createCompletedOrder(publicApi, event, { quantity: 2, buyerLastName: 'Pair' });
    expect(listedTickets.map((ticket) => listingFor(ticket).claimedByOrder)).toEqual([buyerOrder.orderShortId, buyerOrder.orderShortId]);
    const buyerPayment = await payReservedOrderByCard(publicApi, event.eventId, buyerOrder);
    await waitForOrderStatus(publicApi, event.eventId, buyerOrder);

    await expect.poll(() => refundIds().length, { timeout: REFUND_TIMEOUT }).toBeGreaterThanOrEqual(1);
    await sendRefundUpdatedWebhooks(publicApi, seller.paymentIntentId);
    await expect.poll(() => refundIds().length, { timeout: REFUND_TIMEOUT }).toBe(2);
    await sendRefundUpdatedWebhooks(publicApi, seller.paymentIntentId);
    await expect
      .poll(() => listedTickets.map((ticket) => listingFor(ticket).refundStatus), { timeout: REFUND_TIMEOUT })
      .toEqual(['SUCCEEDED', 'SUCCEEDED']);

    const listings = listedTickets.map(listingFor);
    const refunds = await listPaymentIntentRefunds(publicApi, seller.paymentIntentId);
    const feesPerTicket = (await getPaymentIntentFees(publicApi, buyerPayment.paymentIntentId)) / 2;
    expect(refunds).toHaveLength(2);
    expect(refunds.map((refund) => refund.status)).toEqual(['succeeded', 'succeeded']);
    expect(refunds.map((refund) => refund.id).sort()).toEqual(listings.map((listing) => listing.stripeRefundId).sort());
    expect(refunds.map((refund) => refund.metadata.resale_listing_id).sort()).toEqual(listings.map((listing) => String(listing.id)).sort());
    const refundedTotal = refunds.reduce((total, refund) => total + refund.amount, 0);
    expect(refundedTotal).toBe(listings.reduce((total, listing) => total + (listing.refundAmountMinor ?? 0), 0));
    for (const refund of refunds) {
      expect(Math.abs(refund.amount - ((RESALE_TICKET_PRICE * 100) - feesPerTicket))).toBeLessThanOrEqual(1);
    }
    expect(orderRefundState(seller.orderShortId)).toEqual({ refundStatus: 'PARTIALLY_REFUNDED', totalRefundedMinor: refundedTotal });

    await adoptSellerSession(page, event.eventId, seller.orderShortId, session);
    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, seller.orderShortId);
    await expect(page.getByTestId('order-guest').filter({ hasText: 'Resold' })).toHaveCount(2);
    await expect(summary.sellButton()).toHaveCount(1);
    await summary.sellButton().click();
    await expect(summary.sellDialog().getByText("You'll get back about")).toBeVisible();
    await summary.confirmSell();

    await expect(summary.listedForResale()).toHaveCount(1);
    expect(listingFor(seller.attendees[2].shortId).status).toBe('LISTED');
    await expect
      .poll(async () => (await mailpit.search(seller.buyerEmail)).filter((message) => message.Subject.includes('Your ticket is listed for resale')).length)
      .toBe(3);
  });
});
