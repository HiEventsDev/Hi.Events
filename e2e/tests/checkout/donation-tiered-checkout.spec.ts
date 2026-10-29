import { test, expect } from '../../fixtures';
import { CheckoutPage, setWidgetQuantity } from '../../pages/checkout.page';
import { createLiveEventWithProduct, enableOfflinePayments } from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';

test.describe('donation and tiered checkout', () => {
  test('a buyer completes a free order on the standard tier of a tiered product', async ({ page, api, account }) => {
    const event = await createLiveEventWithProduct(api, {
      organizerId: account.organizerId,
      productType: 'TIERED',
      productTitle: 'Tiered Ticket',
      prices: [
        { price: 0, label: 'Standard' },
        { price: 20, label: 'VIP' },
      ],
    });
    const buyer = { firstName: 'Tier', lastName: 'Buyer', email: uniqueEmail('tierbuyer') };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const standardRow = page.locator('.hi-price-tier-row').filter({ hasText: 'Standard' });
    const vipRow = page.locator('.hi-price-tier-row').filter({ hasText: 'VIP' });
    await expect(standardRow.locator('.hi-price-tier-label')).toHaveText('Standard');
    await expect(standardRow.getByText('Free')).toBeVisible();
    await expect(vipRow.locator('.hi-price-tier-label')).toHaveText('VIP');
    await expect(vipRow.getByText('$20.00')).toBeVisible();

    await setWidgetQuantity(standardRow, 1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.completeFreeOrder();

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
    await page.getByRole('button', { name: /Order Summary/ }).click();
    await expect(page.getByText('Tiered Ticket - Standard').first()).toBeVisible();
  });

  test('a buyer completes a donation order with a custom amount', async ({ page, api, account }) => {
    const event = await createLiveEventWithProduct(api, {
      organizerId: account.organizerId,
      productType: 'DONATION',
      price: 5,
      productTitle: 'Donation Ticket',
    });
    await enableOfflinePayments(api, event.eventId);
    const buyer = { firstName: 'Donor', lastName: 'Buyer', email: uniqueEmail('donor') };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const amountInput = page.getByLabel(/^Amount/);
    await expect(amountInput).toBeVisible();
    await amountInput.fill('15');
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.continueToPayment();
    await checkout.chooseOfflinePayment();

    await expect(page.getByText('Your order is awaiting payment')).toBeVisible();
    await expect(page.getByText('$15.00').first()).toBeVisible();
  });
});
