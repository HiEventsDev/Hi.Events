import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { OrderPage } from '../../pages/order.page';
import { createLiveEventWithPaidTicket } from '../../api/factory';
import { deliverPaymentIntentSucceededWebhook, parsePaymentReturnUrl } from '../../api/stripe';
import { uniqueEmail } from '../../utils/unique';
import { STRIPE_PUBLIC_KEY } from '../../utils/env';
import { nonSaasOnly } from '../../utils/mode';

test.describe('order refunds', () => {
  test.skip(!STRIPE_PUBLIC_KEY, 'Requires STRIPE_PUBLIC_KEY (Stripe test mode) to be configured.');
  nonSaasOnly();

  test('an organizer partially refunds a Stripe order', { tag: '@stripe' }, async ({ page, authedPage, api, account, publicApi }) => {
    test.slow();

    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const buyer = { firstName: 'Refund', lastName: 'Buyer', email: uniqueEmail('refundbuyer') };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.continueToPayment();
    await checkout.payWithStripeTestCard();

    const { orderShortId, sessionId } = parsePaymentReturnUrl(page.url());
    await deliverPaymentIntentSucceededWebhook(publicApi, { eventId: event.eventId, orderShortId, sessionId });
    await page.waitForURL(/\/checkout\/\d+\/[^/]+\/summary/, { timeout: 30_000 });
    await page.reload();
    await page.waitForLoadState('networkidle');
    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);
    await orders.chooseRowAction(buyer.email, 'Refund order');

    await expect(authedPage.getByRole('heading', { name: /^Refund Order/ }).first()).toBeVisible();
    const amountInput = authedPage.getByLabel(/^Refund amount/);
    await expect(amountInput).toHaveValue(/25/);
    await amountInput.fill('12.50');
    await authedPage.getByRole('checkbox', { name: /Send refund notification email/ }).check();
    await authedPage.getByRole('button', { name: 'Process Refund' }).click();

    await expect(
      orders.rowByEmail(buyer.email).getByText(/Refund pending|Partially refunded/),
    ).toBeVisible();
  });
});
