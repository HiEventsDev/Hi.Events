import { test, expect } from '../../fixtures';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { TicketPage } from '../../pages/ticket.page';
import { TransferClaimPage } from '../../pages/transfer-claim.page';
import { VerifyEmailDialog } from '../../pages/verify-email.page';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { liftSelfServiceThrottles } from '../../utils/throttle';
import { maskedEmail, waitForClaimLink } from '../../utils/transfers';
import { uniqueEmail } from '../../utils/unique';

test.describe('a named guest transferring their own ticket', () => {
  test.beforeEach(() => liftSelfServiceThrottles());

  test('the guest confirms with a code sent to their own address and the buyer is told when it is accepted', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const guestEmail = uniqueEmail('guest');
    const recipientEmail = uniqueEmail('friend');
    const order = await createCompletedOrder(publicApi, event, { attendeeEmail: guestEmail });

    const ticket = new TicketPage(page);
    await ticket.goto(event.eventId, order.attendees[0].shortId);
    await ticket.startTransfer(recipientEmail);

    const verify = new VerifyEmailDialog(page);
    await expect(verify.dialog().getByText(maskedEmail(guestEmail))).toBeVisible();
    await verify.confirmWithEmailedCode(mailpit, guestEmail);
    expect((await mailpit.search(order.buyerEmail)).filter((message) => message.Subject.includes('verification code'))).toHaveLength(0);
    await expect(ticket.pendingTransfer()).toContainText(maskedEmail(recipientEmail));

    const claim = new TransferClaimPage(page);
    await claim.open((await waitForClaimLink(mailpit, recipientEmail)).href);
    await claim.accept('Katherine', 'Johnson');
    await expect(ticket.heading(event.title)).toBeVisible();

    await mailpit.waitForMessage(guestEmail, { subjectContains: 'Your ticket transfer is complete' });
    await mailpit.waitForMessage(order.buyerEmail, { subjectContains: 'A ticket from your order was transferred' });

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(summary.transferredTickets()).toContainText(maskedEmail(recipientEmail));
    await expect(page.getByTestId('attendee-edit-button')).toHaveCount(0);
    await expect(page.getByTestId('resend-ticket-button')).toHaveCount(0);
    await expect(summary.transferButton()).toHaveCount(0);
  });
});
