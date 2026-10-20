import { test, expect } from '../../fixtures';
import { createSeatedEvent } from '../../api/factory';
import { checkoutModal, openEmbeddedWidget, resolveCheckoutFrame } from '../../pages/embedded-widget';

test.describe('embedded seat picker availability', () => {
  test.beforeEach(async ({ adminApi, account }) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('the seat picker shows a loader and then a message when the event cannot be loaded', async ({ page, api, account, baseURL }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const widgetFrame = await openEmbeddedWidget(page, baseURL!, event.eventId);
    await expect(widgetFrame.getByTestId('choose-seats-button')).toBeVisible();

    let releaseEventRequest: () => void = () => undefined;
    const eventRequestHeld = new Promise<void>((resolve) => {
      releaseEventRequest = resolve;
    });
    const publicEventPath = new RegExp(`/public/events/${event.eventId}$`);
    await page.route((url) => publicEventPath.test(url.pathname), async (route) => {
      await eventRequestHeld;
      await route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ message: 'Not found' }) });
    });

    await widgetFrame.getByTestId('choose-seats-button').click();
    await expect(checkoutModal(page)).toBeVisible();
    const seatFrame = await resolveCheckoutFrame(page);
    await seatFrame.waitForURL(/\/checkout\/\d+\/seats/);

    await expect(seatFrame.getByTestId('seat-checkout-loading')).toBeVisible();
    releaseEventRequest();

    await expect(seatFrame.getByText('This event is not available')).toBeVisible();
    await expect(seatFrame.getByText('It may have been unpublished or removed.', { exact: false })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Close checkout' })).toBeVisible();
  });
});
