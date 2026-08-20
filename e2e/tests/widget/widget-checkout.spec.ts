import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { setWidgetQuantity } from '../../pages/checkout.page';

test.describe('widget checkout', () => {
  test('a buyer starts checkout from the standalone product widget', async ({ page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);

    await page.goto(`/widget/${event.eventId}`);
    await page.waitForLoadState('networkidle');
    await expect(page.getByRole('heading', { name: event.productTitle })).toBeVisible();

    await setWidgetQuantity(page, 1);

    const [checkoutPage] = await Promise.all([
      page.context().waitForEvent('page'),
      page.getByTestId('checkout-continue-button').click(),
    ]);

    await checkoutPage.waitForURL(new RegExp(`/checkout/${event.eventId}/[^/]+/details`));
    await expect(checkoutPage.getByRole('heading', { name: 'Your Details' })).toBeVisible();
    await expect(checkoutPage.getByLabel(/^First Name/).first()).toBeVisible();
  });
});
