import { test, expect } from '../../fixtures';
import { AttendeePage } from '../../pages/attendee.page';
import { OrderPage } from '../../pages/order.page';
import { createCompletedOrder } from '../../api/factory';
import {
  canTakeStripePayments,
  getPaymentIntentRefundedAmount,
  listPaymentIntentRefunds,
  sendRefundUpdatedWebhooks,
  STRIPE_PAYMENTS_SKIP_REASON,
} from '../../api/stripe';
import { liftResaleThrottles } from '../../utils/resale';
import {
  createSoldOutEventWithListings,
  listingFor,
  orderRefundState,
  payReservedOrderByCard,
  RESALE_TICKET_PRICE,
  SETTLE_TIMEOUT,
  settleSellerRefunds,
  soldCount,
  waitForOrderStatus,
  waitForSellerRefund,
} from '../../utils/stripe-resale';
import { uniqueShort } from '../../utils/unique';

const ORGANIZER_REFUND = 30;

test.describe('an organizer changing the seller\'s order while a buyer is checking out', () => {
  test.skip(!canTakeStripePayments(), STRIPE_PAYMENTS_SKIP_REASON);
  test.beforeEach(() => liftResaleThrottles());

  test('cancelling the seller\'s ticket turns the buyer\'s payment into an ordinary sale with no resale refund', { tag: '@stripe' }, async ({ authedPage: page, api, account, publicApi, mailpit }) => {
    test.slow();
    const sellerLastName = uniqueShort('Seller').replace(' ', '');
    const { event, seller } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId, { sellerLastName });
    const sellerTicket = seller.attendees[0];
    const buyerOrder = await createCompletedOrder(publicApi, event, { buyerLastName: 'Holder' });
    expect(listingFor(sellerTicket.shortId).claimedByOrder).toBe(buyerOrder.orderShortId);

    const attendees = new AttendeePage(page);
    await attendees.goto(event.eventId);
    await attendees.cancelTicket(sellerLastName);
    await expect(page.getByText('Successfully cancelled attendee')).toBeVisible();
    await expect(attendees.rowByText(sellerLastName)).toContainText('Cancelled');

    expect(listingFor(sellerTicket.shortId)).toMatchObject({ status: 'CLOSED', closedReason: 'TICKET_CANCELLED' });
    await mailpit.waitForMessage(seller.buyerEmail, { subjectContains: 'Your ticket resale listing was closed' });

    const buyerPayment = await payReservedOrderByCard(publicApi, event.eventId, buyerOrder);
    await waitForOrderStatus(publicApi, event.eventId, buyerOrder);
    await mailpit.waitForMessage(buyerOrder.buyerEmail, { subjectContains: 'Your Order is Confirmed', timeoutMs: SETTLE_TIMEOUT });

    expect(listingFor(sellerTicket.shortId)).toMatchObject({ status: 'CLOSED', soldOrder: null, refundStatus: null, stripeRefundId: null });
    expect(await listPaymentIntentRefunds(publicApi, seller.paymentIntentId)).toEqual([]);
    expect(await getPaymentIntentRefundedAmount(publicApi, buyerPayment.paymentIntentId)).toBe(0);
    expect(await soldCount(api, event)).toBe(1);
    const active = (await api.listAttendees(event.eventId)).filter((attendee) => attendee.status === 'ACTIVE');
    expect(active.map((attendee) => attendee.last_name)).toEqual(['Holder']);
    expect((await mailpit.search(seller.buyerEmail)).filter((message) => message.Subject.includes('Your ticket sold'))).toEqual([]);
  });

  test('a part refund of the seller\'s order caps the resale refund at what the order has left', { tag: '@stripe' }, async ({ authedPage: page, api, account, publicApi, mailpit }) => {
    test.slow();
    const { event, seller } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId);
    const sellerTicket = seller.attendees[0];
    const buyerOrder = await createCompletedOrder(publicApi, event, { buyerLastName: 'Holder' });
    expect(listingFor(sellerTicket.shortId).claimedByOrder).toBe(buyerOrder.orderShortId);

    const orders = new OrderPage(page);
    await orders.goto(event.eventId);
    await orders.chooseRowAction(seller.buyerEmail, 'Refund order');
    await expect(page.getByRole('heading', { name: /^Refund Order/ }).first()).toBeVisible();
    await page.getByLabel(/^Refund amount/).fill(String(ORGANIZER_REFUND));
    await page.getByRole('button', { name: 'Process Refund' }).click();
    await expect(orders.rowByEmail(seller.buyerEmail).getByText(/Refund pending|Partially refunded/i)).toBeVisible();
    await expect.poll(() => getPaymentIntentRefundedAmount(publicApi, seller.paymentIntentId), { timeout: SETTLE_TIMEOUT }).toBe(ORGANIZER_REFUND * 100);
    await sendRefundUpdatedWebhooks(publicApi, seller.paymentIntentId);
    await expect
      .poll(() => orderRefundState(seller.orderShortId), { timeout: SETTLE_TIMEOUT })
      .toEqual({ refundStatus: 'PARTIALLY_REFUNDED', totalRefundedMinor: ORGANIZER_REFUND * 100 });
    expect(listingFor(sellerTicket.shortId)).toMatchObject({ status: 'LISTED', claimedByOrder: buyerOrder.orderShortId });

    await payReservedOrderByCard(publicApi, event.eventId, buyerOrder);
    await waitForOrderStatus(publicApi, event.eventId, buyerOrder);

    const leftToRefund = (RESALE_TICKET_PRICE - ORGANIZER_REFUND) * 100;
    const { listing, refund } = await waitForSellerRefund(publicApi, seller, sellerTicket.shortId);
    expect(listing).toMatchObject({ status: 'SOLD', soldOrder: buyerOrder.orderShortId });
    expect(refund.amount).toBe(leftToRefund);
    expect(refund.metadata.resale_listing_id).toBe(String(listing.id));
    expect(await getPaymentIntentRefundedAmount(publicApi, seller.paymentIntentId)).toBe(seller.amountPaidMinor);
    expect((await settleSellerRefunds(publicApi, seller, sellerTicket.shortId)).refundAmountMinor).toBe(leftToRefund);

    const active = (await api.listAttendees(event.eventId)).filter((attendee) => attendee.status === 'ACTIVE');
    expect(active.map((attendee) => attendee.last_name)).toEqual(['Holder']);
    expect(await soldCount(api, event)).toBe(1);
  });
});
