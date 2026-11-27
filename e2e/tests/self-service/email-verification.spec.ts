import { test, expect } from '../../fixtures';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { VerifyEmailDialog } from '../../pages/verify-email.page';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { liftSelfServiceThrottles } from '../../utils/throttle';
import { uniqueEmail, uniqueShort } from '../../utils/unique';

const maskedAddress = (email: string): string => `${email[0]}•••@${email.split('@')[1]}`;
const wrongCodeFor = (code: string): string => (code === '000000' ? '111111' : '000000');

test.describe('confirming it is you before changing an email', () => {
  test.beforeEach(() => liftSelfServiceThrottles());

  test('a buyer enters the emailed code to change a ticket email', { tag: '@smoke' }, async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);
    const newEmail = uniqueEmail('verified-change');

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.editFirstAttendeeEmail(newEmail);

    const verify = new VerifyEmailDialog(page);
    await expect(verify.dialog().getByText(maskedAddress(order.buyerEmail))).toBeVisible();
    await expect(verify.dialog()).not.toContainText(order.buyerEmail);
    await expect(verify.resendButton()).toBeDisabled();

    await verify.enterCode(await mailpit.waitForSelfServiceCode(order.buyerEmail));

    await expect(page.getByText('Attendee updated successfully')).toBeVisible();
    await expect(page.getByText(newEmail)).toBeVisible();
    await mailpit.waitForMessage(newEmail, { subjectContains: 'Your Ticket' });
  });

  test('a wrong code is refused and the right one still works', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);
    const newEmail = uniqueEmail('wrong-then-right');

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.editFirstAttendeeEmail(newEmail);

    const verify = new VerifyEmailDialog(page);
    const code = await mailpit.waitForSelfServiceCode(order.buyerEmail);
    await verify.enterCode(wrongCodeFor(code));
    await expect(verify.error()).toContainText('That code is not correct');

    await verify.enterCode(code);
    await expect(page.getByText('Attendee updated successfully')).toBeVisible();
  });

  test('five wrong codes lock the code and a new one has to be requested', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.editFirstAttendeeEmail(uniqueEmail('locked'));

    const verify = new VerifyEmailDialog(page);
    const code = await mailpit.waitForSelfServiceCode(order.buyerEmail);
    for (let attempt = 1; attempt <= 4; attempt += 1) {
      await verify.enterCode(wrongCodeFor(code));
      await expect(verify.error()).toContainText('That code is not correct');
    }
    await verify.enterCode(wrongCodeFor(code));
    await expect(verify.error()).toContainText('Request a new code');

    await verify.enterCode(code);
    await expect(verify.error()).toContainText('Request a new code');
  });

  test('changing only a name needs no code', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event, { buyerFirstName: 'Original', buyerLastName: 'Guest' });
    const newFirstName = uniqueShort('Renamed');

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.editFirstAttendeeFirstName(newFirstName);

    await expect(page.getByText(`${newFirstName} Guest`)).toBeVisible();
    await expect(new VerifyEmailDialog(page).dialog()).toHaveCount(0);
  });
});
