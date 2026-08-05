import fs from 'node:fs';
import { test, expect } from '../../fixtures';
import { OrderPage } from '../../pages/order.page';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { uniqueEmail, uniqueName } from '../../utils/unique';

const CONFIRMATION_SUBJECT = 'Your Order is Confirmed';

test.describe('orders management', () => {
  test('an organizer sees an order in the list and views its details', { tag: '@smoke' }, async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail() });

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);

    await expect(orders.rowByEmail(order.buyerEmail)).toBeVisible();

    await orders.chooseRowAction(order.buyerEmail, 'Manage order');

    const drawer = orders.detailsDrawer();
    await expect(drawer.getByText(`${order.buyerFirstName} ${order.buyerLastName}`).first()).toBeVisible();
    await expect(drawer.getByText('Items')).toBeVisible();
    await expect(drawer.getByText('Order Summary')).toBeVisible();
    await expect(drawer.getByText('$0.00').first()).toBeVisible();
  });

  test('an organizer resends the order confirmation email', async ({ authedPage, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail() });
    await mailpit.waitForMessage(order.buyerEmail, { subjectContains: CONFIRMATION_SUBJECT });

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);

    await orders.chooseRowAction(order.buyerEmail, 'Resend order email');

    await expect
      .poll(
        async () => {
          const messages = await mailpit.search(order.buyerEmail);
          return messages.filter((m) => m.Subject.includes(CONFIRMATION_SUBJECT)).length;
        },
        { timeout: 15_000 },
      )
      .toBe(2);
  });

  test('an organizer messages the order buyer', async ({ authedPage, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail() });
    const subject = uniqueName('Parking info');

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);

    await orders.chooseRowAction(order.buyerEmail, 'Message buyer');
    await orders.sendMessageToBuyer(subject, 'Please use the north entrance car park.');

    const message = await mailpit.waitForMessage(order.buyerEmail, { subjectContains: subject });
    expect(message.Subject).toContain(subject);
  });

  test('an organizer cancels an order', async ({ authedPage, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail() });

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);

    await orders.chooseRowAction(order.buyerEmail, 'Cancel order');
    await orders.confirmCancelOrder();

    await expect(orders.rowByEmail(order.buyerEmail).getByText('Cancelled')).toBeVisible();
    const cancellation = await mailpit.waitForMessage(order.buyerEmail, { subjectContains: 'cancelled' });
    expect(cancellation.Subject).toContain('cancelled');
  });

  test('an organizer exports orders to a spreadsheet', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail() });

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);

    const [download] = await Promise.all([
      authedPage.waitForEvent('download'),
      orders.exportButton().click(),
    ]);

    expect(download.suggestedFilename()).toMatch(/\.(csv|xlsx)$/);
    const downloadPath = await download.path();
    const { size } = await fs.promises.stat(downloadPath);
    expect(size).toBeGreaterThan(0);
  });
});
