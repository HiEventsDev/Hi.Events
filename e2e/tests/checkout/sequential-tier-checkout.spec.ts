import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { createCompletedOrder, createLiveEventWithProduct } from '../../api/factory';

test.describe('sequential tier release', () => {
  test('a later tier stays locked until the earlier tier sells out', { tag: '@smoke' }, async ({ page, api, publicApi, account }) => {
    const event = await createLiveEventWithProduct(api, {
      organizerId: account.organizerId,
      productType: 'TIERED',
      productTitle: 'Sequential Ticket',
      sequentialTierReleaseEnabled: true,
      prices: [
        { price: 0, label: 'Early bird', initial_quantity_available: 1 },
        { price: 0, label: 'General', initial_quantity_available: 1 },
      ],
    });
    const product = await api.getProduct(event.eventId, event.productId);
    const generalPriceId = product.prices?.[1]?.id;
    if (!generalPriceId) {
      throw new Error('General tier has no price id');
    }

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const productRow = page.locator('.hi-product-row').filter({ hasText: 'Sequential Ticket' });
    const earlyBirdRow = page.locator('.hi-price-tier-row').filter({ hasText: 'Early bird' });
    const generalRow = page.locator('.hi-price-tier-row').filter({ hasText: 'General' });
    await expect(earlyBirdRow.getByRole('button', { name: 'Increase quantity' })).toBeVisible();
    await expect(generalRow).toHaveAttribute('data-unavailable', 'locked');
    await expect(generalRow.getByText('Not yet on sale')).toBeVisible();

    await createCompletedOrder(publicApi, event);
    await checkout.gotoPublicEventAfterAvailabilityCacheExpires(event.eventId, event.slug);

    await expect(earlyBirdRow.getByText('Sold out')).toBeVisible();
    await expect(generalRow).not.toHaveAttribute('data-unavailable');
    await expect(generalRow.getByRole('button', { name: 'Increase quantity' })).toBeVisible();

    await createCompletedOrder(publicApi, { ...event, priceId: generalPriceId });
    await checkout.gotoPublicEventAfterAvailabilityCacheExpires(event.eventId, event.slug);

    await expect(productRow.locator('.hi-product-availability:visible')).toHaveCount(2);
    await productRow.locator('.hi-product-title').click();
    await expect(productRow.locator('.hi-product-availability:visible')).toHaveCount(1);
    await expect(productRow.locator('.hi-product-title')).toContainText('Sold out');
  });
});
