import { test, expect } from '../../fixtures';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { TicketPage } from '../../pages/ticket.page';
import { TransferClaimPage } from '../../pages/transfer-claim.page';
import { VerifyEmailDialog } from '../../pages/verify-email.page';
import { createSeatedEvent, createSeatedOrder } from '../../api/factory';
import { liftSelfServiceThrottles } from '../../utils/throttle';
import { waitForClaimLink } from '../../utils/transfers';
import { uniqueEmail } from '../../utils/unique';

const SEAT = 'e2.0.9';

test.describe('transferring a seated ticket', () => {
  test.beforeEach(async ({ adminApi, account }) => {
    liftSelfServiceThrottles();
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('the seat stays with the ticket when it changes hands', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const order = await createSeatedOrder(publicApi, event, [SEAT]);
    const recipientEmail = uniqueEmail('seated');

    const ticket = new TicketPage(page);
    await ticket.goto(event.eventId, order.attendees[0].shortId);
    const seatLabel = (await page.locator('dt', { hasText: 'Seat' }).locator('xpath=following-sibling::dd[1]').innerText()).trim();
    expect(seatLabel).not.toBe('');

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.startTransfer(recipientEmail);
    await new VerifyEmailDialog(page).confirmWithEmailedCode(mailpit, order.buyerEmail);
    const claim = new TransferClaimPage(page);
    await claim.open((await waitForClaimLink(mailpit, recipientEmail)).href);
    await claim.accept('Seated', 'Recipient');

    await expect(page.getByText('Seated Recipient')).toBeVisible();
    await expect(page.locator('dt', { hasText: 'Seat' }).locator('xpath=following-sibling::dd[1]')).toHaveText(seatLabel);
  });
});
