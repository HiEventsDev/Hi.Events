import { test, expect } from '../../fixtures';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { VerifyEmailDialog } from '../../pages/verify-email.page';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { liftSelfServiceThrottles } from '../../utils/throttle';
import { uniqueEmail } from '../../utils/unique';

test.describe('changing the email on an order', () => {
  test.beforeEach(() => liftSelfServiceThrottles());

  test('someone holding only the order link cannot point the order at their own address', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);
    const intruderEmail = uniqueEmail('intruder');

    const refused = await publicApi.patch(`public/events/${event.eventId}/order/${order.orderShortId}`, {
      data: { email: intruderEmail },
    });
    expect(refused.status()).toBe(422);

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByText(order.buyerEmail).first()).toBeVisible();
    await summary.editOrderEmail(intruderEmail);

    const verify = new VerifyEmailDialog(page);
    await expect(verify.dialog()).toBeVisible();
    await mailpit.waitForSelfServiceCode(order.buyerEmail);
    expect(await mailpit.search(intruderEmail)).toHaveLength(0);
  });

  test('the buyer changes the order email after entering the code', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);
    const newEmail = uniqueEmail('new-buyer');

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.editOrderEmail(newEmail);
    await new VerifyEmailDialog(page).enterCode(await mailpit.waitForSelfServiceCode(order.buyerEmail));

    await expect(page.getByText('Order updated successfully')).toBeVisible();
    await expect(page).not.toHaveURL(new RegExp(order.orderShortId));
    await expect(page.getByText(newEmail).first()).toBeVisible();
  });
});
