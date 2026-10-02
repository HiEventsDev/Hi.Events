import { test, expect } from '../../fixtures';
import { OrderPage } from '../../pages/order.page';
import { createDoorSale, createLiveEventWithPaidTicket, defaultBoxOffice } from '../../api/factory';

test.describe('box office reporting', () => {
  test('door sales are labelled in the manage orders table and filter by operator', async ({ authedPage, api, publicApi, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);
    await createDoorSale(publicApi, boxOffice, event, { operatorName: 'Robin' });

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);

    const row = authedPage.getByRole('row').filter({ hasText: 'Box office sale' });
    await expect(row).toContainText('Box office · Sold by Robin');
    await expect(row).toContainText('Cash');
    await expect(row).toContainText('No email provided');

    await authedPage.getByRole('button', { name: /Filters/ }).click();
    await authedPage.getByRole('dialog').getByLabel('Sold by').fill('Robin');
    await authedPage.getByRole('button', { name: 'Apply' }).click();
    await expect(row).toBeVisible();

    await authedPage.getByRole('button', { name: /Filters/ }).click();
    await authedPage.getByRole('dialog').getByLabel('Sold by').fill('Nobody');
    await authedPage.getByRole('button', { name: 'Apply' }).click();
    await expect(authedPage.getByText('No orders to show')).toBeVisible();
  });
});
