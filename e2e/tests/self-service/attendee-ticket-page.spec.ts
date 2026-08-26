import { test, expect } from '../../fixtures';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';

test.describe('attendee ticket page', () => {
  test('an attendee views their ticket page', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event, {
      buyerFirstName: 'Ticketed',
      buyerLastName: 'Guest',
    });

    await page.goto(`/product/${event.eventId}/${order.attendees[0].shortId}`);
    await page.waitForLoadState('networkidle');

    await expect(page.getByRole('heading', { name: `Your ticket for ${event.title}` })).toBeVisible();
    await expect(page.getByText('Ticketed Guest')).toBeVisible();
  });

  test('an attendee views the printable ticket page', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event, {
      buyerFirstName: 'Printable',
      buyerLastName: 'Guest',
    });

    await page.goto(`/product/${event.eventId}/${order.attendees[0].shortId}/print`);
    await page.waitForLoadState('networkidle');

    await expect(page.getByRole('heading', { name: `Ticket for ${event.title}` })).toBeVisible();
    await expect(page.getByText('Printable Guest')).toBeVisible();
  });
});
