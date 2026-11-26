import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { PublicEventPage } from '../../pages/public-event.page';
import { createCompletedOrder, createReservedOrder, OFFLINE_PAYMENT_INSTRUCTIONS } from '../../api/factory';
import { canTakeStripePayments, getPaymentIntentRefundedAmount, parsePaymentReturnUrl, STRIPE_PAYMENTS_SKIP_REASON } from '../../api/stripe';
import { expireOrder } from '../../utils/db';
import { API_BASE_URL, BASE_URL } from '../../utils/env';
import { liftResaleThrottles } from '../../utils/resale';
import {
  adoptSellerSession,
  attemptOrder,
  createSoldOutEventWithListings,
  expectOrderRefusedAsSoldOut,
  listingFor,
  payReservedOrderByCard,
  resaleStates,
  runResaleMaintenance,
  scanTicket,
  waitForOrderStatus,
  waitForSellerRefund,
  withdrawListing,
} from '../../utils/stripe-resale';
import { uniqueEmail, uniqueName, uniqueShort } from '../../utils/unique';

const CARD_ONLY_NOTICE = 'This order includes a resold ticket, so it needs to be paid by card now.';

test.describe('a resale listing held by a checkout', () => {
  test.skip(!canTakeStripePayments(), STRIPE_PAYMENTS_SKIP_REASON);
  test.beforeEach(() => liftResaleThrottles());

  test('an abandoned checkout frees the listed ticket for the next buyer', { tag: '@stripe' }, async ({ page, api, account, publicApi, mailpit }) => {
    test.slow();
    const { event, seller } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId);
    const sellerTicket = seller.attendees[0];

    const abandoned = await createReservedOrder(publicApi, event);
    expect(listingFor(sellerTicket.shortId).claimedByOrder).toBe(abandoned.orderShortId);
    const publicPage = new PublicEventPage(page);
    await publicPage.expectTicketSoldOut(event);

    expireOrder(abandoned.orderShortId);

    await publicPage.expectTicketsAvailable(event, 1);
    const nextBuyer = { firstName: 'Nora', lastName: 'Next', email: uniqueEmail('nextbuyer') };
    const checkout = new CheckoutPage(page);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderAndAttendees(nextBuyer, 1);
    await checkout.continueToPayment();
    await expect(page.getByText(CARD_ONLY_NOTICE)).toBeVisible();

    const held = listingFor(sellerTicket.shortId);
    expect(held.status).toBe('LISTED');
    expect(held.claimedByOrder).toBe(parsePaymentReturnUrl(page.url()).orderShortId);
  });

  test('withdrawing while a buyer is checking out waits for their hold, keeps other buyers out, and gives the ticket back when the hold lapses', { tag: '@stripe' }, async ({ page, api, account, publicApi, mailpit }) => {
    test.slow();
    const sellerLastName = uniqueShort('Seller').replace(' ', '');
    const { event, seller, session } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId, { sellerLastName });
    const sellerTicket = seller.attendees[0];
    const door = await api.createCheckInList(event.eventId, { name: uniqueName('Main Door') });
    const holding = await createReservedOrder(publicApi, event);

    await adoptSellerSession(page, event.eventId, seller.orderShortId, session);
    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, seller.orderShortId);
    await expect(summary.listedForResale()).toContainText(/A buyer is checking out\. If they don't finish by .+, the ticket stays listed\./);
    await summary.withdrawListing();

    await expect(page.getByText("Withdrawal requested. A buyer is checking out, so the listing ends if they don't finish.")).toBeVisible();
    await expect(summary.listedForResale()).toContainText(/A buyer is checking out\. If they don't finish by .+, your listing will be withdrawn\./);
    await expect(summary.listedForResale()).toContainText('Withdrawal requested');
    await expect(page.getByTestId('ticket-resale-withdraw-button')).toHaveCount(0);
    const [state] = await resaleStates(publicApi, event.eventId, seller);
    expect(state).toMatchObject({ status: 'LISTED', withdrawal_requested: true });
    expect(state?.held_until).not.toBeNull();

    await expectOrderRefusedAsSoldOut(await attemptOrder(publicApi, event));
    await new PublicEventPage(page).expectTicketSoldOut(event);
    expect(listingFor(sellerTicket.shortId).claimedByOrder).toBe(holding.orderShortId);
    expect((await scanTicket(publicApi, door.short_id, sellerTicket.publicId)).errors.join(' ')).toMatch(/listed for resale/);

    expireOrder(holding.orderShortId);
    runResaleMaintenance();

    expect(listingFor(sellerTicket.shortId)).toMatchObject({ status: 'CLOSED', closedReason: 'WITHDRAWN' });
    await summary.goto(event.eventId, seller.orderShortId);
    await expect(summary.listedForResale()).toHaveCount(0);
    await expect(summary.guest(sellerLastName).getByRole('button', { name: 'View Ticket' })).toBeVisible();
    expect((await scanTicket(publicApi, door.short_id, sellerTicket.publicId)).checkedIn).toBe(true);
  });

  test('a buyer already checking out who pays after the seller asked to withdraw still gets the ticket and the seller is refunded', { tag: '@stripe' }, async ({ page, api, account, publicApi, mailpit }) => {
    test.slow();
    const sellerLastName = uniqueShort('Seller').replace(' ', '');
    const { event, seller, session } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId, { sellerLastName });
    const sellerTicket = seller.attendees[0];
    const buyerOrder = await createCompletedOrder(publicApi, event, { buyerLastName: 'Holder' });

    const withdrawal = await withdrawListing(publicApi, event.eventId, seller.orderShortId, sellerTicket.shortId, session);
    expect(withdrawal).toMatchObject({ status: 'LISTED', withdrawal_requested: true });
    expect(listingFor(sellerTicket.shortId)).toMatchObject({ withdrawalRequested: true, claimedByOrder: buyerOrder.orderShortId });

    const buyerPayment = await payReservedOrderByCard(publicApi, event.eventId, buyerOrder);
    await waitForOrderStatus(publicApi, event.eventId, buyerOrder);

    const { listing, refund } = await waitForSellerRefund(publicApi, seller, sellerTicket.shortId);
    expect(listing).toMatchObject({ status: 'SOLD', soldOrder: buyerOrder.orderShortId });
    expect(refund.amount).toBeGreaterThan(0);
    expect(refund.amount).toBe(listing.refundAmountMinor);
    expect(refund.payment_intent).toBe(seller.paymentIntentId);
    expect(await getPaymentIntentRefundedAmount(publicApi, buyerPayment.paymentIntentId)).toBe(0);

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, seller.orderShortId);
    await expect(summary.guest(sellerLastName)).toContainText('Resold');
    await summary.goto(event.eventId, buyerOrder.orderShortId);
    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
  });

  test('an order holding a listed ticket can only be paid by card now, while an ordinary order on the same event can pay later or send a payment link', { tag: '@stripe' }, async ({ page, browser, api, account, publicApi, mailpit }) => {
    test.slow();
    const { event } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId, {
      quantity: 2,
      unsold: 1,
      event: {
        settings: {
          payment_providers: ['STRIPE', 'OFFLINE'],
          offline_payment_instructions: OFFLINE_PAYMENT_INSTRUCTIONS,
          payment_links_enabled: true,
          payment_link_expiry_hours: 48,
        },
      },
    });
    const publicPage = new PublicEventPage(page);
    const checkout = new CheckoutPage(page);
    const payLater = page.getByRole('button', { name: 'Pay later' });
    const paymentLink = page.getByTestId('open-payment-link-modal');

    await publicPage.expectTicketsAvailable(event, 2);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderAndAttendees({ firstName: 'Olive', lastName: 'Ordinary', email: uniqueEmail('ordinary') }, 1);
    await checkout.continueToPayment();
    await expect(payLater).toBeVisible();
    await expect(paymentLink).toBeVisible();
    await expect(page.getByText(CARD_ONLY_NOTICE)).toHaveCount(0);

    const resaleContext = await browser.newContext({ baseURL: BASE_URL, ignoreHTTPSErrors: true });
    try {
      const resalePage = await resaleContext.newPage();
      const resaleCheckout = new CheckoutPage(resalePage);
      await new PublicEventPage(resalePage).expectTicketsAvailable(event, 1);
      await resaleCheckout.setFirstProductQuantity(1);
      await resaleCheckout.continueToCheckout();
      await resaleCheckout.fillOrderAndAttendees({ firstName: 'Rhea', lastName: 'Resale', email: uniqueEmail('resalebuyer') }, 1);
      await resaleCheckout.continueToPayment();
      await expect(resalePage.getByText(CARD_ONLY_NOTICE)).toBeVisible();
      await expect(resalePage.getByRole('button', { name: /^Pay \D*\d/ })).toBeVisible();
      await expect(resalePage.getByRole('button', { name: 'Pay later' })).toHaveCount(0);
      await expect(resalePage.getByTestId('open-payment-link-modal')).toHaveCount(0);

      const holding = parsePaymentReturnUrl(resalePage.url());
      const json = { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } };
      const orderUrl = `public/events/${event.eventId}/order/${holding.orderShortId}`;
      const offline = await resalePage.request.post(`${API_BASE_URL}${orderUrl}/await-offline-payment`, json);
      expect(offline.ok()).toBe(false);
      expect(await offline.text()).toMatch(/resold ticket|card/i);
      const link = await resalePage.request.post(`${API_BASE_URL}${orderUrl}/payment-link`, json);
      expect(link.ok()).toBe(false);
      expect(await link.text()).toMatch(/resold ticket|card/i);
    } finally {
      await resaleContext.close();
    }
  });
});
