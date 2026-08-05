import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { uniqueEmail, uniqueShort } from '../../utils/unique';

test.describe('multi-ticket checkout', () => {
  test('a buyer completes an order with two products and three attendees', async ({ page, api, account, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const secondProductTitle = uniqueShort('VIP');
    const categories = await api.listProductCategories(event.eventId);
    await api.createProduct(event.eventId, {
      title: secondProductTitle,
      product_type: 'TICKET',
      type: 'FREE',
      product_category_id: categories[0].id,
      prices: [{ price: 0 }],
    });

    const buyerEmail = uniqueEmail('buyer');
    const attendees = [
      { firstName: 'AttendeeOne', lastName: 'Guest', email: uniqueEmail('attendee') },
      { firstName: 'AttendeeTwo', lastName: 'Guest', email: uniqueEmail('attendee') },
      { firstName: 'AttendeeThree', lastName: 'Guest', email: uniqueEmail('attendee') },
    ];

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setQuantityForProduct(event.productTitle, 2);
    await checkout.setQuantityForProduct(secondProductTitle, 1);
    await checkout.continueToCheckout();

    await checkout.fillOrderDetails({ firstName: 'Multi', lastName: 'Buyer', email: buyerEmail });
    for (const [index, attendee] of attendees.entries()) {
      const blockIndex = index + 1;
      await page.getByLabel(/^First Name/).nth(blockIndex).fill(attendee.firstName);
      await page.getByLabel(/^Last Name/).nth(blockIndex).fill(attendee.lastName);
      await page.getByLabel(/^Email Address/).nth(blockIndex).fill(attendee.email);
      await page.getByLabel(/^Confirm Email Address/).nth(blockIndex).fill(attendee.email);
    }
    await checkout.completeFreeOrder();

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
    for (const attendee of attendees) {
      await expect(page.getByText(attendee.email)).toBeVisible();
    }
    await expect(page.getByText(secondProductTitle)).toHaveCount(2);

    await mailpit.waitForMessage(buyerEmail, { subjectContains: 'Your Order is Confirmed' });
    expect(await mailpit.search(buyerEmail)).toHaveLength(1);
  });
});
