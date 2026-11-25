import { test, expect } from '../../fixtures';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { TicketPage } from '../../pages/ticket.page';
import { TransferClaimPage } from '../../pages/transfer-claim.page';
import { VerifyEmailDialog } from '../../pages/verify-email.page';
import { createCompletedOrder, createRecurringLiveEvent } from '../../api/factory';
import { liftSelfServiceThrottles } from '../../utils/throttle';
import { waitForClaimLink } from '../../utils/transfers';
import { uniqueEmail } from '../../utils/unique';

test.describe('transferring a ticket for one date of a recurring event', () => {
  test.beforeEach(() => liftSelfServiceThrottles());

  test('the ticket keeps its date when it changes hands', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });
    const order = await createCompletedOrder(publicApi, event, { eventOccurrenceId: event.occurrences[1].id });
    const recipientEmail = uniqueEmail('recurring');
    const dateOnTicket = page.locator('dt', { hasText: 'Date & Time' }).locator('xpath=following-sibling::dd[1]');

    const ticket = new TicketPage(page);
    await ticket.goto(event.eventId, order.attendees[0].shortId);
    const originalDate = (await dateOnTicket.innerText()).trim();
    expect(originalDate).not.toBe('');

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.startTransfer(recipientEmail);
    await new VerifyEmailDialog(page).confirmWithEmailedCode(mailpit, order.buyerEmail);
    const claim = new TransferClaimPage(page);
    await claim.open((await waitForClaimLink(mailpit, recipientEmail)).href);
    await claim.accept('Later', 'Date');

    await expect(page.getByText('Later Date')).toBeVisible();
    await expect(dateOnTicket).toHaveText(originalDate);
  });
});
