import { test, expect } from '../../fixtures';
import { AttendeePage } from '../../pages/attendee.page';
import { OrderPage } from '../../pages/order.page';
import { createCompletedOrder, createEventWithAttendee, createLiveEventWithFreeTicket } from '../../api/factory';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { TransferClaimPage } from '../../pages/transfer-claim.page';
import { VerifyEmailDialog } from '../../pages/verify-email.page';
import { invitedMember } from '../../utils/team';
import { liftSelfServiceThrottles } from '../../utils/throttle';
import { waitForClaimLink } from '../../utils/transfers';
import { uniqueEmail } from '../../utils/unique';

test.describe('ticket history', () => {
  test('an organizer sees an email change in the attendee and order history without the ticket link or QR value', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);
    const previous = order.attendees[0];
    const newEmail = uniqueEmail('history');

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await attendees.openRowAction(order.buyerEmail, 'Manage attendee');
    await attendees.changeEmail(newEmail);

    const attendeeHistory = attendees.history();
    await expect(attendeeHistory.getByText('Ticket details changed by an organizer')).toBeVisible();
    await expect(attendeeHistory.getByText(`Email: ${order.buyerEmail} → ${newEmail}`)).toBeVisible();
    await expect(attendeeHistory.getByText('QR code and ticket link replaced')).toBeVisible();
    await expect(attendeeHistory).not.toContainText(previous.shortId);
    await expect(attendeeHistory).not.toContainText(previous.publicId);

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);
    await orders.chooseRowAction(order.buyerEmail, 'Manage order');

    const orderHistory = orders.detailsDrawer().getByRole('list', { name: 'History' });
    await expect(orderHistory.getByText(`Email: ${order.buyerEmail} → ${newEmail}`)).toBeVisible();
    await expect(orderHistory).not.toContainText(previous.shortId);
  });

  test('an organizer sees who a ticket was transferred from and to', async ({ authedPage, page, api, account, publicApi, mailpit }) => {
    liftSelfServiceThrottles();
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);
    const recipientEmail = uniqueEmail('history-transfer');
    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.startTransfer(recipientEmail);
    await new VerifyEmailDialog(page).confirmWithEmailedCode(mailpit, order.buyerEmail);
    const claim = new TransferClaimPage(page);
    await claim.open((await waitForClaimLink(mailpit, recipientEmail)).href);
    await claim.accept('New', 'Holder');
    await expect(page.getByText('New Holder')).toBeVisible();

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await attendees.openRowAction(recipientEmail, 'Manage attendee');

    const history = attendees.history();
    await expect(history.getByText('Transferred to a new holder')).toBeVisible();
    await expect(history.getByText('Transfer started')).toBeVisible();
    await expect(history.getByText(`Email: ${order.buyerEmail} → ${recipientEmail}`).first()).toBeVisible();
    await expect(history.getByText('QR code and ticket link replaced')).toBeVisible();
    await expect(history).not.toContainText(order.attendees[0].publicId);
  });

  test('a team member without access to the event cannot reach its attendees or their history', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const granted = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const hidden = await createEventWithAttendee(freshAccount.api, freshAccount.organizerId);
    const member = await invitedMember(freshAccount, mailpit, publicApi, browser, {
      role: 'ORGANIZER',
      access_scope: 'RESTRICTED',
      event_ids: [granted.eventId],
    });

    await member.page.goto(`/manage/event/${hidden.eventId}/attendees`);
    await expect(member.page.getByRole('heading', { name: "You don't have access to this event" })).toBeVisible();

    const history = await member.page.request.get(`/api/events/${hidden.eventId}/attendees/${hidden.attendeeId}/history`, {
      headers: { Accept: 'application/json' },
    });
    expect(history.status()).toBe(403);
  });
});
