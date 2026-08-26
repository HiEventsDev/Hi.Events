import { test, expect } from '../../fixtures';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { uniqueShort } from '../../utils/unique';

const CONFIRMATION_SUBJECT = 'Your Order is Confirmed';

test.describe('order summary self-service', () => {
  test('a buyer edits their order details from the summary page', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);
    const newFirstName = uniqueShort('Edited');

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();

    await summary.editOrderFirstName(newFirstName);

    await expect(page.getByText(`${newFirstName} ${order.buyerLastName}`)).toBeVisible();
  });

  test('a buyer edits an attendee from the summary page', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event, {
      buyerFirstName: 'Original',
      buyerLastName: 'Guest',
    });
    const newFirstName = uniqueShort('Renamed');

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();

    await summary.editFirstAttendeeFirstName(newFirstName);

    await expect(page.getByText(`${newFirstName} Guest`)).toBeVisible();
  });

  test('a buyer resends their order confirmation email', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);
    await mailpit.waitForMessage(order.buyerEmail, { subjectContains: CONFIRMATION_SUBJECT });

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();

    await summary.resendConfirmation();

    await expect
      .poll(async () => {
        const messages = await mailpit.search(order.buyerEmail);
        return messages.filter((message) => message.Subject.includes(CONFIRMATION_SUBJECT)).length;
      })
      .toBe(2);
  });
});
