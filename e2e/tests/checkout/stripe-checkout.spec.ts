import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { createLiveEventWithPaidTicket } from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';
import { STRIPE_PUBLIC_KEY } from '../../utils/env';
import { nonSaasOnly } from '../../utils/mode';

test.describe('stripe checkout', () => {
  test.skip(!STRIPE_PUBLIC_KEY, 'Requires STRIPE_PUBLIC_KEY (Stripe test mode) to be configured.');
  nonSaasOnly();

  test('a buyer completes a paid order with a Stripe test card', { tag: '@smoke' }, async ({ page, api, account }) => {
    test.slow();

    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const buyer = { firstName: 'Buyer', lastName: 'Paid', email: uniqueEmail('paidbuyer') };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.continueToPayment();
    await checkout.payWithStripeTestCard();

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
  });
});
