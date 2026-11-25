import { test, expect } from '../../fixtures';
import { AttendeePage } from '../../pages/attendee.page';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { TicketPage } from '../../pages/ticket.page';
import { TransferClaimPage } from '../../pages/transfer-claim.page';
import { VerifyEmailDialog } from '../../pages/verify-email.page';
import { createCompletedOrder, createEventWithQuestions } from '../../api/factory';
import { liftSelfServiceThrottles } from '../../utils/throttle';
import { waitForClaimLink } from '../../utils/transfers';
import { uniqueEmail } from '../../utils/unique';

test.describe('accepting a ticket that asks questions', () => {
  test.beforeEach(() => liftSelfServiceThrottles());

  test('the recipient must give a valid name and answer the ticket questions, and their answers replace the sender\'s', async ({ page, authedPage, api, account, publicApi, mailpit }) => {
    const event = await createEventWithQuestions(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event, {
      orderQuestions: [{ question_id: event.orderQuestion.id, response: { answer: 'A friend' } }],
      attendeeQuestions: [{ question_id: event.attendeeQuestion.id, response: { answer: 'Small' } }],
    });
    const recipientEmail = uniqueEmail('questions');

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.startTransfer(recipientEmail);
    await new VerifyEmailDialog(page).confirmWithEmailedCode(mailpit, order.buyerEmail);

    const claim = new TransferClaimPage(page);
    await claim.open((await waitForClaimLink(mailpit, recipientEmail)).href);
    await expect(page.getByText('Shirt size')).toBeVisible();

    await claim.accept('Grace', 'Hopper');
    await expect(claim.acceptButton()).toBeVisible();
    await expect(page.getByText(/required|select an option/i).first()).toBeVisible();

    await page.getByRole('radio', { name: 'Large' }).check();
    await claim.accept('See https://spam.example', 'Hopper');
    await expect(claim.acceptButton()).toBeVisible();
    await expect(page.getByLabel(/^First name/)).toHaveAttribute('aria-invalid', 'true');

    await claim.accept('Grace', 'Hopper');
    await expect(new TicketPage(page).heading(event.title)).toBeVisible();

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await attendees.openRowAction(recipientEmail, 'Manage attendee');
    await expect(authedPage.getByRole('dialog').getByText('Large')).toBeVisible();
    await expect(authedPage.getByRole('dialog').getByText('Small', { exact: true })).toHaveCount(0);
  });
});
