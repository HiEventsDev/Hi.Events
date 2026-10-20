import { test, expect } from '../../fixtures';
import { AttendeePage } from '../../pages/attendee.page';
import { CheckoutPage } from '../../pages/checkout.page';
import { createLiveEventWithProduct } from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';

test.describe('per-order attendee details checkout', () => {
  test('a buyer books two tickets with one set of contact details', async ({ page, authedPage, api, account, mailpit }) => {
    const event = await createLiveEventWithProduct(api, {
      organizerId: account.organizerId,
      attendeeDetails: 'PER_ORDER',
    });
    const buyerEmail = uniqueEmail('perorderbuyer');
    const buyer = { firstName: 'Per', lastName: 'Order', email: buyerEmail };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(2);
    await checkout.continueToCheckout();

    await expect(page.getByLabel(/^First Name/)).toHaveCount(1);

    await checkout.fillOrderDetails(buyer);
    await checkout.completeFreeOrder();

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
    const ticketEmail = await mailpit.waitForMessage(buyerEmail, { subjectContains: 'Your Tickets for' });
    await mailpit.waitForMessage(buyerEmail, { subjectContains: 'Your Order is Confirmed' });
    expect(await mailpit.search(buyerEmail)).toHaveLength(2);

    const ticketLinks = (await mailpit.getLinks(ticketEmail.ID))
      .filter((link) => new RegExp(`/product/${event.eventId}/[^/]+$`).test(link));
    expect(new Set(ticketLinks).size).toBe(2);

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await expect(attendees.rowByText(buyerEmail)).toHaveCount(2);
    await expect(attendees.rowByText('Per Order')).toHaveCount(2);
  });
});
