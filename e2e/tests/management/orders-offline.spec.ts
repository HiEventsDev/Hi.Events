import { test, expect } from '../../fixtures';
import { OrderPage } from '../../pages/order.page';
import { createAwaitingOfflineOrder, createCompletedPaidOrder, createLiveEventWithPaidTicket } from '../../api/factory';
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

  test('an organizer records a refund for a paid offline order', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const order = await createCompletedPaidOrder(api, publicApi, event, { buyerEmail: uniqueEmail() });

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);
    await orders.chooseRowAction(order.buyerEmail, 'Refund order');

    await expect(authedPage.getByRole('heading', { name: /^Refund Order/ }).first()).toBeVisible();
    await expect(authedPage.getByText('This order was paid offline', { exact: false })).toBeVisible();
    await authedPage.getByRole('button', { name: 'Record Refund' }).click();

    await expect(orders.rowByEmail(order.buyerEmail).getByText(/Refunded/)).toBeVisible();
  });

  test('an organizer cancels a paid offline order and refunds it in one step', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const order = await createCompletedPaidOrder(api, publicApi, event, { buyerEmail: uniqueEmail() });

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);
    await orders.chooseRowAction(order.buyerEmail, 'Cancel order');

    await expect(authedPage.getByRole('checkbox', { name: 'Also refund this order' })).toBeChecked();
    await orders.confirmCancelOrder();

    const row = orders.rowByEmail(order.buyerEmail);
    await expect(row.getByText('Cancelled')).toBeVisible();
    await expect(row.getByText(/Refunded/)).toBeVisible();
  });
});
