import { test, expect } from '../../fixtures';
import { CheckInPage } from '../../pages/check-in.page';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { TicketPage } from '../../pages/ticket.page';
import { TransferClaimPage } from '../../pages/transfer-claim.page';
import { VerifyEmailDialog } from '../../pages/verify-email.page';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { liftSelfServiceThrottles } from '../../utils/throttle';
import { maskedEmail, waitForClaimLink } from '../../utils/transfers';
import { uniqueEmail, uniqueName, uniqueShort } from '../../utils/unique';

test.describe('transferring a ticket', () => {
  test.beforeEach(() => liftSelfServiceThrottles());

  test('a buyer transfers a ticket and the recipient ends up with the only working copy', { tag: '@smoke' }, async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event, {
      buyerFirstName: 'Original',
      buyerLastName: uniqueShort('Buyer').replace(' ', ''),
    });
    const previous = order.attendees[0];
    const list = await api.createCheckInList(event.eventId, { name: uniqueName('Main Door') });
    const recipientEmail = uniqueEmail('recipient');
    const recipientLastName = uniqueShort('Hopper').replace(' ', '');

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.startTransfer(recipientEmail);
    await new VerifyEmailDialog(page).confirmWithEmailedCode(mailpit, order.buyerEmail);

    await expect(summary.pendingTransfer()).toContainText(maskedEmail(recipientEmail));
    await expect(summary.pendingTransfer()).toContainText('Your ticket still works until then');
    await expect(summary.transferButton()).toHaveCount(0);

    const ticket = new TicketPage(page);
    await ticket.goto(event.eventId, previous.shortId);
    await expect(ticket.heading(event.title)).toBeVisible();

    const claim = new TransferClaimPage(page);
    await claim.open((await waitForClaimLink(mailpit, recipientEmail)).href);
    await expect(claim.heading('A ticket is waiting for you')).toBeVisible();
    await expect(page.getByText(maskedEmail(order.buyerEmail)).first()).toBeVisible();
    await expect(page.getByText(order.buyerEmail)).toHaveCount(0);
    await claim.accept('Grace', recipientLastName);

    await expect(ticket.heading(event.title)).toBeVisible();
    await expect(page.getByText(`Grace ${recipientLastName}`)).toBeVisible();
    await expect(page).not.toHaveURL(new RegExp(previous.shortId));
    await mailpit.waitForMessage(recipientEmail, { subjectContains: 'Your Ticket' });
    await mailpit.waitForMessage(order.buyerEmail, { subjectContains: 'Your ticket transfer is complete' });

    await summary.goto(event.eventId, order.orderShortId);
    await expect(summary.transferredTickets()).toContainText(maskedEmail(recipientEmail));
    await expect(page.getByTestId('attendee-edit-button')).toHaveCount(0);
    await expect(page.getByText(`Grace ${recipientLastName}`)).toHaveCount(0);

    await page.goto(`/order/${event.eventId}/${order.orderShortId}/print`);
    await page.waitForLoadState('networkidle');
    await expect(page.getByText(`Grace ${recipientLastName}`)).toHaveCount(0);
    await expect(page.getByText(previous.publicId)).toHaveCount(0);

    await ticket.goto(event.eventId, previous.shortId);
    await expect(page.getByText('Ticket Not Found')).toBeVisible();

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(previous.publicId);
    await expect(page.getByRole('button', { name: /^View details for/ })).toHaveCount(0);
    await checkIn.search(recipientLastName);
    const row = page.getByRole('button', { name: /^View details for/ }).filter({ hasText: recipientLastName });
    await row.getByRole('button', { name: /^Check in$/ }).click();
    await expect(row.getByRole('button', { name: /^Check out$/ })).toBeVisible();
  });
});
