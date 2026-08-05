import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { createLiveEventWithFreeTicket, createReservedOrder } from '../../api/factory';

test.describe('order lifecycle', () => {
  test('a reserved order shows a countdown timer in the checkout header', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const { orderShortId, sessionId } = await createReservedOrder(publicApi, event);

    await page.goto(`/checkout/${event.eventId}/${orderShortId}/details?session_identifier=${sessionId}`);
    await page.waitForLoadState('networkidle');

    const timerLabel = page.getByText('Time left:');
    await expect(timerLabel).toBeVisible();
    await expect(timerLabel.locator('..')).toContainText(/\d+:\d{2}/);
  });

  test('abandoning a checkout returns to the event page and cancels the order', async ({ page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    const detailsUrl = page.url();

    await page.getByRole('button', { name: 'Event Homepage' }).click();
    await expect(page.getByRole('heading', { name: 'Are you sure you want to leave?' })).toBeVisible();
    await page.getByRole('button', { name: 'Yes, cancel my order' }).click();

    await page.waitForURL(/\/event\/\d+\//);
    await expect(page.getByRole('heading', { name: event.title })).toBeVisible();

    await page.goto(detailsUrl);
    await page.waitForLoadState('networkidle');
    await expect(page.getByRole('heading', { name: 'Order was cancelled' })).toBeVisible();
    await expect(page.getByText('This order was abandoned. You can start a new order anytime.')).toBeVisible();
    await expect(page.getByLabel(/^First Name/)).toHaveCount(0);
  });
});
