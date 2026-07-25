import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { createLiveEventWithPaidTicket } from '../../api/factory';
import { uniqueCode, uniqueEmail } from '../../utils/unique';

test.describe('promo code checkout', () => {
  test(
    'a buyer completes a paid-ticket order for free with a 100% promo code',
    { tag: '@smoke' },
    async ({ page, api, account, mailpit }) => {
      const event = await createLiveEventWithPaidTicket(api, account.organizerId);
      const code = uniqueCode();
      await api.createPromoCode(event.eventId, { code, discount_type: 'PERCENTAGE', discount: 100 });
      const buyerEmail = uniqueEmail('buyer');
      const buyer = { firstName: 'Promo', lastName: 'Buyer', email: buyerEmail };

      const checkout = new CheckoutPage(page);
      await checkout.gotoPublicEvent(event.eventId, event.slug);
      await checkout.applyPromoCode(code);

      const productRow = page.locator('.hi-product-row').filter({ hasText: event.productTitle });
      await expect(productRow.getByText('Free')).toBeVisible();
      await expect(productRow.getByText('$25.00')).toBeVisible();

      await checkout.setQuantityForProduct(event.productTitle, 1);
      await checkout.continueToCheckout();
      await checkout.fillOrderDetails(buyer);
      await checkout.fillFirstAttendee(buyer);
      await checkout.completeFreeOrder();

      await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
      await mailpit.waitForMessage(buyerEmail, { subjectContains: 'Your Order is Confirmed' });
    },
  );

  test('applying an invalid promo code shows an error', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.applyPromoCode('BOGUS123');

    await expect(page.getByText('That promo code is invalid')).toBeVisible();
  });
});
