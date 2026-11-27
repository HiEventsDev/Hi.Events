import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { TransferClaimPage } from '../../pages/transfer-claim.page';
import { VerifyEmailDialog } from '../../pages/verify-email.page';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { liftSelfServiceThrottles } from '../../utils/throttle';
import { waitForClaimLink } from '../../utils/transfers';
import { uniqueEmail } from '../../utils/unique';

const hydrationErrorsOn = (page: Page): string[] => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error') {
      errors.push(message.text());
    }
  });
  return errors;
};

test.describe('the transfer claim page', () => {
  test.beforeEach(() => liftSelfServiceThrottles());

  test('loads for a signed-out visitor without hydration errors or a trip to the login page', async ({ page, browser, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);
    const recipientEmail = uniqueEmail('ssr');
    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.startTransfer(recipientEmail);
    await new VerifyEmailDialog(page).confirmWithEmailedCode(mailpit, order.buyerEmail);
    const claimUrl = (await waitForClaimLink(mailpit, recipientEmail)).href;

    const visitor = await (await browser.newContext({ ignoreHTTPSErrors: true })).newPage();
    const errors = hydrationErrorsOn(visitor);
    const claim = new TransferClaimPage(visitor);
    await claim.open(claimUrl);

    await expect(claim.heading('A ticket is waiting for you')).toBeVisible();
    await expect(visitor).toHaveURL(/\/transfer\/tt_/);
    expect(errors.filter((error) => /hydrat|#418|#423|#425/i.test(error))).toEqual([]);
    await expect(claim.acceptButton()).toHaveCSS('cursor', 'pointer');

    await claim.open(claimUrl.replace(/tt_.*/, 'tt_doesnotexist'));
    await expect(claim.heading('Transfer not found')).toBeVisible();
  });
});
