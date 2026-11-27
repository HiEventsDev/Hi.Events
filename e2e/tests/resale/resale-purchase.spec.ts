import { test, expect } from '../../fixtures';
import { CheckInPage } from '../../pages/check-in.page';
import { CheckoutPage } from '../../pages/checkout.page';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { PublicEventPage } from '../../pages/public-event.page';
import { TicketPage } from '../../pages/ticket.page';
import { getPublicOrder } from '../../api/public-client';
import {
  canTakeStripePayments,
  deliverPaymentIntentSucceededWebhook,
  getPaymentIntentFees,
  getPaymentIntentRefundedAmount,
  listPaymentIntentRefunds,
  parsePaymentReturnUrl,
  sendChargeSucceededWebhook,
  STRIPE_PAYMENTS_SKIP_REASON,
} from '../../api/stripe';
import { liftResaleThrottles } from '../../utils/resale';
import {
  createSoldOutEventWithListings,
  formatMoney,
  RESALE_TICKET_PRICE,
  scanTicket,
  settleSellerRefunds,
  soldCount,
  STRIPE_CARD_NUMBER_PAYING_INTO_AVAILABLE_BALANCE,
  waitForOrderStatus,
  waitForSellerRefund,
} from '../../utils/stripe-resale';
import { uniqueEmail, uniqueName, uniqueShort } from '../../utils/unique';

test.describe('buying a resold ticket', () => {
  test.skip(!canTakeStripePayments(), STRIPE_PAYMENTS_SKIP_REASON);
  test.beforeEach(() => liftResaleThrottles());

  test('a sold-out ticket listed for resale is bought by card and the seller is refunded what they paid minus the new sale fees', { tag: ['@stripe', '@smoke'] }, async ({ page, api, account, publicApi, mailpit }) => {
    test.slow();
    const sellerLastName = uniqueShort('Seller').replace(' ', '');
    const { event, seller } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId, {
      quantity: 2,
      sellerLastName,
    });
    const sellerTicket = seller.attendees[0];
    const door = await api.createCheckInList(event.eventId, { name: uniqueName('Main Door') });
    const soldBeforeResale = await soldCount(api, event);
    expect(soldBeforeResale).toBe(2);

    const buyer = { firstName: 'Bianca', lastName: uniqueShort('Buyer').replace(' ', ''), email: uniqueEmail('rebuyer') };
    const checkout = new CheckoutPage(page);
    const publicPage = new PublicEventPage(page);
    await publicPage.expectTicketsAvailable(event, 1);
    await expect(publicPage.ticketRow(event.productTitle)).not.toHaveAttribute('data-availability', 'sold-out');

    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderAndAttendees(buyer, 1);
    await checkout.continueToPayment();
    await expect(page.getByText('This order includes a resold ticket, so it needs to be paid by card now.')).toBeVisible();
    await checkout.payWithStripeTestCard(STRIPE_CARD_NUMBER_PAYING_INTO_AVAILABLE_BALANCE);

    const buyerOrder = parsePaymentReturnUrl(page.url());
    const buyerPaymentIntentId = await deliverPaymentIntentSucceededWebhook(publicApi, { eventId: event.eventId, ...buyerOrder });
    await sendChargeSucceededWebhook(publicApi, buyerPaymentIntentId);
    await waitForOrderStatus(publicApi, event.eventId, buyerOrder);

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, buyerOrder.orderShortId);
    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
    await mailpit.waitForMessage(buyer.email, { subjectContains: 'Your Order is Confirmed' });
    const [buyerTicket] = (await getPublicOrder(publicApi, event.eventId, buyerOrder.orderShortId, buyerOrder.sessionId)).attendees ?? [];
    const ticketPage = new TicketPage(page);
    await ticketPage.goto(event.eventId, buyerTicket.short_id);
    await expect(ticketPage.heading(event.title)).toBeVisible();
    await expect(ticketPage.qrCode()).toBeVisible();
    await expect(ticketPage.unusableNotice()).toHaveCount(0);

    const { listing, refund } = await waitForSellerRefund(publicApi, seller, sellerTicket.shortId);
    const buyerFees = await getPaymentIntentFees(publicApi, buyerPaymentIntentId);
    expect(listing.status).toBe('SOLD');
    expect(listing.soldOrder).toBe(buyerOrder.orderShortId);
    expect(refund.amount).toBe(listing.refundAmountMinor);
    expect(refund.amount).toBe((RESALE_TICKET_PRICE * 100) - buyerFees);
    expect(refund.amount).toBeLessThan(seller.amountPaidMinor);
    expect(refund.payment_intent).toBe(seller.paymentIntentId);
    expect(refund.metadata.resale_listing_id).toBe(String(listing.id));
    expect(await listPaymentIntentRefunds(publicApi, seller.paymentIntentId)).toHaveLength(1);
    expect(await getPaymentIntentRefundedAmount(publicApi, buyerPaymentIntentId)).toBe(0);
    expect(refund.status).toBe('succeeded');
    expect((await settleSellerRefunds(publicApi, seller, sellerTicket.shortId)).refundAmountMinor).toBe(refund.amount);

    const soldEmail = await mailpit.waitForMessage(seller.buyerEmail, { subjectContains: 'Your ticket sold and your refund is on its way' });
    expect((await mailpit.getMessage(soldEmail.ID)).Text).toContain(`**${formatMoney(refund.amount, event.currency)}** is on its way back to the card you paid with`);

    await summary.goto(event.eventId, seller.orderShortId);
    await expect(summary.guest(sellerLastName)).toContainText('Resold');
    await expect(summary.sellButton()).toHaveCount(0);
    await ticketPage.goto(event.eventId, sellerTicket.shortId);
    await expect(ticketPage.unusableNotice()).toContainText('This ticket was resold and is no longer valid');
    await expect(ticketPage.qrCode()).toHaveCount(0);

    const refused = await scanTicket(publicApi, door.short_id, sellerTicket.publicId);
    expect(refused.checkedIn).toBe(false);
    expect(refused.errors.join(' ')).toMatch(/ticket is cancelled/);

    const checkIn = new CheckInPage(page);
    await checkIn.goto(door.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(buyer.lastName);
    await checkIn.checkInButton(buyerTicket.public_id).click();
    await expect(checkIn.checkOutButton(buyerTicket.public_id)).toBeVisible();

    expect(await soldCount(api, event)).toBe(soldBeforeResale);
  });
});
