import { test, expect } from '../../fixtures';
import { OrderPage } from '../../pages/order.page';
import { createAwaitingOfflineOrder, createLiveEventWithPaidTicket } from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';

test.describe('offline orders', () => {
  test('an organizer marks an awaiting-offline-payment order as paid', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    const order = await createAwaitingOfflineOrder(api, publicApi, event, { buyerEmail: uniqueEmail() });

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);

    const row = orders.rowByEmail(order.buyerEmail);
    await expect(row).toBeVisible();
    await expect(row.getByText('Awaiting Payment')).toBeVisible();

    await orders.chooseRowAction(order.buyerEmail, 'Mark as paid');

    await expect(row.getByText('Completed')).toBeVisible();
  });
});
