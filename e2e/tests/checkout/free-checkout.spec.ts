import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';

test.describe('free checkout', () => {
  test('a buyer completes a free-ticket order', { tag: '@smoke' }, async ({ page, api, account, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const buyerEmail = uniqueEmail('buyer');
    const buyer = { firstName: 'Buyer', lastName: 'One', email: buyerEmail };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.completeFreeOrder();

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
    await mailpit.waitForMessage(buyerEmail);
  });
});
