import { test, expect } from '../../fixtures';
import { CheckInPage } from '../../pages/check-in.page';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { TicketPage } from '../../pages/ticket.page';
import { TransferClaimPage } from '../../pages/transfer-claim.page';
import { VerifyEmailDialog } from '../../pages/verify-email.page';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { liftSelfServiceThrottles } from '../../utils/throttle';
import { markProductNotTransferable, seedCancelledTransfers, waitForClaimLink } from '../../utils/transfers';
import { uniqueEmail, uniqueName, uniqueShort } from '../../utils/unique';

test.describe('when a ticket cannot be transferred', () => {
  test.beforeEach(() => liftSelfServiceThrottles());

  test('no transfer is offered when the organizer has turned transfers off', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);
    await api.updateEventSettings(event.eventId, { allow_ticket_transfer: false });

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByTestId('attendee-edit-button')).toBeVisible();
    await expect(summary.transferButton()).toHaveCount(0);

    const ticket = new TicketPage(page);
    await ticket.goto(event.eventId, order.attendees[0].shortId);
    await expect(ticket.heading(event.title)).toBeVisible();
    await expect(ticket.transferButton()).toHaveCount(0);
  });

  test('no transfer is offered once transfers have closed before the event', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);
    await api.updateEventSettings(event.eventId, { ticket_transfer_cutoff_minutes: 525600 });

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByTestId('attendee-edit-button')).toBeVisible();
    await expect(summary.transferButton()).toHaveCount(0);
  });

  test('a ticket type marked as not transferable offers no transfer', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);
    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(summary.transferButton()).toBeVisible();

    markProductNotTransferable(event.productId);

    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByTestId('attendee-edit-button')).toBeVisible();
    await expect(summary.transferButton()).toHaveCount(0);
  });

  test('a checked-in ticket offers no transfer', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event, { buyerLastName: uniqueShort('Door').replace(' ', '') });
    const list = await api.createCheckInList(event.eventId, { name: uniqueName('Main Door') });
    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(order.buyerLastName);
    await checkIn.checkInButton(order.attendees[0].publicId).click();
    await expect(checkIn.checkOutButton(order.attendees[0].publicId)).toBeVisible();

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByTestId('attendee-edit-button')).toBeVisible();
    await expect(summary.transferButton()).toHaveCount(0);
  });

  test('a transfer to the address already on the ticket is refused', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.startTransfer(order.buyerEmail);
    await new VerifyEmailDialog(page).confirmWithEmailedCode(mailpit, order.buyerEmail);

    await expect(page.getByRole('dialog', { name: 'Transfer this ticket' }).getByText("This ticket is already in that person's name.")).toBeVisible();
    await expect(summary.pendingTransfer()).toHaveCount(0);
  });

  test('a ticket that has reached the organizer\'s transfer limit cannot be transferred again', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.updateEventSettings(event.eventId, { max_transfers_per_ticket: 1 });
    const order = await createCompletedOrder(publicApi, event);
    const recipientEmail = uniqueEmail('limit');

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.startTransfer(recipientEmail);
    await new VerifyEmailDialog(page).confirmWithEmailedCode(mailpit, order.buyerEmail);
    const claim = new TransferClaimPage(page);
    await claim.open((await waitForClaimLink(mailpit, recipientEmail)).href);
    await claim.accept('Second', 'Holder');

    const ticket = new TicketPage(page);
    await expect(ticket.heading(event.title)).toBeVisible();
    await expect(page.getByText('Second Holder')).toBeVisible();
    await expect(ticket.transferButton()).toHaveCount(0);
  });

  test('a sixth transfer of the same ticket in a day is not offered', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);
    const summary = new OrderSummaryPage(page);
    seedCancelledTransfers(order.attendees[0].shortId, 4);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(summary.transferButton()).toBeVisible();

    seedCancelledTransfers(order.attendees[0].shortId, 1);

    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByTestId('attendee-edit-button')).toBeVisible();
    await expect(summary.transferButton()).toHaveCount(0);
  });
});
