import { test, expect } from '../../fixtures';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { TicketPage } from '../../pages/ticket.page';
import { TransferClaimPage } from '../../pages/transfer-claim.page';
import { VerifyEmailDialog } from '../../pages/verify-email.page';
import {
  createCompletedOrder,
  createCompletedPaidOrder,
  createLiveEventWithFreeTicket,
  createLiveEventWithPaidTicket,
} from '../../api/factory';
import {
  createEventWithResale,
  createResellableOrder,
  createResellableOrderWithAddOn,
  createStripePaidOrder,
  liftResaleThrottles,
} from '../../utils/resale';
import { maskedEmail, waitForClaimLink } from '../../utils/transfers';
import { uniqueEmail } from '../../utils/unique';

test.describe('when a ticket cannot be resold', () => {
  test.beforeEach(() => liftResaleThrottles());

  test('a free ticket offers no sell action', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.updateEventSettings(event.eventId, { allow_ticket_resale: true });
    const order = await createCompletedOrder(publicApi, event);

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByTestId('attendee-edit-button')).toBeVisible();
    await expect(summary.sellButton()).toHaveCount(0);
  });

  test('an order that was not paid by card online offers no sell action', async ({ page, api, account, publicApi }) => {
    const event = await createEventWithResale(api, account.organizerId);
    const order = await createCompletedPaidOrder(api, publicApi, event);

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByTestId('attendee-edit-button')).toBeVisible();
    await expect(summary.sellButton()).toHaveCount(0);
  });

  test('a ticket type the organizer marked as not resellable offers no sell action', async ({ page, api, account, publicApi }) => {
    const event = await createEventWithResale(api, account.organizerId, { isResellable: false });
    const order = await createStripePaidOrder(api, publicApi, event);

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByTestId('attendee-edit-button')).toBeVisible();
    await expect(summary.sellButton()).toHaveCount(0);
  });

  test('an event without resale turned on offers no sell action', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    const order = await createStripePaidOrder(api, publicApi, event);

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByTestId('attendee-edit-button')).toBeVisible();
    await expect(summary.sellButton()).toHaveCount(0);
  });

  test('a ticket that was transferred to someone else can no longer be sold by the buyer', async ({ page, api, account, publicApi, mailpit }) => {
    const { event, order } = await createResellableOrder(api, publicApi, account.organizerId, { quantity: 2 });
    const recipientEmail = uniqueEmail('recipient');

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(summary.sellButton()).toHaveCount(2);
    await summary.startTransfer(recipientEmail);
    await new VerifyEmailDialog(page).confirmWithEmailedCode(mailpit, order.buyerEmail);
    await expect(summary.pendingTransfer()).toBeVisible();
    await expect(summary.sellButton()).toHaveCount(1);

    const claim = new TransferClaimPage(page);
    await claim.open((await waitForClaimLink(mailpit, recipientEmail)).href);
    await claim.accept('Grace', 'Hopper');
    const ticket = new TicketPage(page);
    await expect(ticket.heading(event.title)).toBeVisible();
    await expect(summary.sellButton()).toHaveCount(0);

    await summary.goto(event.eventId, order.orderShortId);
    await expect(summary.transferredTickets()).toContainText(maskedEmail(recipientEmail));
    await expect(summary.sellButton()).toHaveCount(1);
  });

  test('a named guest opening their own ticket link sees no sell action', async ({ page, api, account, publicApi }) => {
    const { event, order } = await createResellableOrder(api, publicApi, account.organizerId, {
      attendeeEmail: uniqueEmail('guest'),
    });

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(summary.sellButton()).toBeVisible();

    const ticket = new TicketPage(page);
    await ticket.goto(event.eventId, order.attendees[0].shortId);
    await expect(ticket.heading(event.title)).toBeVisible();
    await expect(ticket.qrCode()).toBeVisible();
    await expect(summary.sellButton()).toHaveCount(0);
    await expect(page.getByRole('button', { name: /sell/i })).toHaveCount(0);
  });

  test('add-ons on the order are named as not included before the ticket is listed', async ({ page, api, account, publicApi, mailpit }) => {
    const { event, order } = await createResellableOrderWithAddOn(api, publicApi, account.organizerId, 'Parking Pass');

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.sellButton().click();
    await new VerifyEmailDialog(page).confirmWithEmailedCode(mailpit, order.buyerEmail);

    await expect(summary.sellDialog().getByTestId('ticket-resale-add-ons')).toHaveText(
      'Not included: Parking Pass. Add-ons stay with you and are not refunded.',
    );
  });
});
