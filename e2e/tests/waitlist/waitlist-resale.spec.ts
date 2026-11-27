import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { PublicEventPage } from '../../pages/public-event.page';
import { joinWaitlist } from '../../api/public-client';
import {
  canTakeStripePayments,
  deliverPaymentIntentSucceededWebhook,
  getPaymentIntentFees,
  getPaymentIntentRefundedAmount,
  parsePaymentReturnUrl,
  sendChargeSucceededWebhook,
  STRIPE_PAYMENTS_SKIP_REASON,
} from '../../api/stripe';
import { liftResaleThrottles } from '../../utils/resale';
import {
  createCardPaidOrder,
  createResaleEvent,
  holdEndsByResaleCutoff,
  listingFor,
  listTicketForResale,
  moveEventStart,
  STRIPE_CARD_NUMBER_PAYING_INTO_AVAILABLE_BALANCE,
  startSellerSession,
  waitForOrderStatus,
  waitForSellerRefund,
  waitUntilResellable,
} from '../../utils/stripe-resale';
import { uniqueEmail } from '../../utils/unique';

const CUTOFF_MINUTES = 120;
const OFFER_TIMEOUT_MINUTES = 720;

test.describe('waitlist offers for listed tickets', () => {
  test.skip(!canTakeStripePayments(), STRIPE_PAYMENTS_SKIP_REASON);
  test.beforeEach(() => liftResaleThrottles());

  test('listing a sold-out ticket offers it to the next person waiting, who pays and the seller is refunded', { tag: '@stripe' }, async ({ page, api, account, publicApi, mailpit }) => {
    test.slow();
    const event = await createResaleEvent(api, account.organizerId, {
      quantityAvailable: 1,
      waitlistEnabled: true,
      settings: {
        waitlist_auto_process: true,
        waitlist_offer_timeout_minutes: OFFER_TIMEOUT_MINUTES,
        ticket_resale_cutoff_minutes: CUTOFF_MINUTES,
      },
    });
    const seller = await createCardPaidOrder(api, publicApi, event, { buyerLastName: 'Seller' });
    await waitUntilResellable(publicApi, event.eventId, seller);
    const sellerTicket = seller.attendees[0];
    const waiting = { firstName: 'Wanda', lastName: 'Waiting', email: uniqueEmail('waitlister') };
    await new PublicEventPage(page).expectTicketSoldOut(event);
    await joinWaitlist(publicApi, event.eventId, { product_price_id: event.priceId, email: waiting.email, first_name: waiting.firstName });
    await mailpit.waitForMessage(waiting.email, { subjectContains: "You're on the waitlist" });
    moveEventStart(event.eventId, CUTOFF_MINUTES + 180);

    const session = await startSellerSession(publicApi, mailpit, event.eventId, seller);
    await listTicketForResale(publicApi, event.eventId, seller.orderShortId, sellerTicket.shortId, session);

    const offerUrl = await mailpit.waitForLink(waiting.email, /\/checkout\/\d+/, { subjectContains: 'spot has opened up', timeoutMs: 60_000 });
    const offerOrderShortId = listingFor(sellerTicket.shortId).claimedByOrder;
    expect(offerOrderShortId).not.toBeNull();
    expect(offerUrl.pathname).toContain(offerOrderShortId!);
    expect(holdEndsByResaleCutoff(offerOrderShortId!, CUTOFF_MINUTES)).toBe(true);

    await page.goto(offerUrl.pathname + offerUrl.search);
    await page.waitForLoadState('networkidle');
    const checkout = new CheckoutPage(page);
    await checkout.fillBlankContactFields(waiting);
    await checkout.continueToPayment();
    await expect(page.getByText('This order includes a resold ticket, so it needs to be paid by card now.')).toBeVisible();
    await checkout.payWithStripeTestCard(STRIPE_CARD_NUMBER_PAYING_INTO_AVAILABLE_BALANCE);

    const offerOrder = parsePaymentReturnUrl(page.url());
    expect(offerOrder.orderShortId).toBe(offerOrderShortId);
    const buyerPaymentIntentId = await deliverPaymentIntentSucceededWebhook(publicApi, { eventId: event.eventId, ...offerOrder });
    await sendChargeSucceededWebhook(publicApi, buyerPaymentIntentId);
    await waitForOrderStatus(publicApi, event.eventId, offerOrder);

    const { listing, refund } = await waitForSellerRefund(publicApi, seller, sellerTicket.shortId);
    expect(listing).toMatchObject({ status: 'SOLD', soldOrder: offerOrderShortId });
    expect(refund.amount).toBe(seller.amountPaidMinor - (await getPaymentIntentFees(publicApi, buyerPaymentIntentId)));
    expect(refund.payment_intent).toBe(seller.paymentIntentId);
    expect(await getPaymentIntentRefundedAmount(publicApi, buyerPaymentIntentId)).toBe(0);
    await mailpit.waitForMessage(waiting.email, { subjectContains: 'Your Order is Confirmed' });
    await mailpit.waitForMessage(seller.buyerEmail, { subjectContains: 'Your ticket sold and your refund is on its way' });
  });
});
