import { test, expect } from '../../fixtures';
import { CheckInPage } from '../../pages/check-in.page';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { TicketPage } from '../../pages/ticket.page';
import { VerifyEmailDialog } from '../../pages/verify-email.page';
import { createResellableOrder, liftResaleThrottles } from '../../utils/resale';
import { uniqueName, uniqueShort } from '../../utils/unique';

const amount = (text: string | null): number => Number((text ?? '').replace(/[^0-9.]/g, ''));

test.describe('reselling a ticket', () => {
  test.beforeEach(() => liftResaleThrottles());

  test('a buyer lists a ticket for resale, cannot use it while it is listed, and gets it back by withdrawing', { tag: '@smoke' }, async ({ page, api, account, publicApi, mailpit }) => {
    const { event, order } = await createResellableOrder(api, publicApi, account.organizerId, {
      buyerLastName: uniqueShort('Seller').replace(' ', ''),
    });
    const ticket = order.attendees[0];
    const door = await api.createCheckInList(event.eventId, { name: uniqueName('Main Door') });

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.sellButton().click();
    await new VerifyEmailDialog(page).confirmWithEmailedCode(mailpit, order.buyerEmail);

    const sell = summary.sellDialog();
    await expect(sell.getByText('You paid', { exact: true })).toBeVisible();
    await expect(sell.getByTestId('ticket-resale-amount-paid')).toHaveText(`$${order.totalGross.toFixed(2)}`);
    await expect(sell.getByText('Estimated fees on the new sale')).toBeVisible();
    await expect(sell.getByText("You'll get back about")).toBeVisible();
    await expect(sell.getByText('This amount is an estimate.')).toBeVisible();
    const fees = amount(await sell.getByTestId('ticket-resale-estimated-fees').textContent());
    const refund = amount(await sell.getByTestId('ticket-resale-estimated-refund').textContent());
    expect(fees).toBeGreaterThan(0);
    expect(refund).toBeCloseTo(order.totalGross - fees, 2);
    await expect(sell.getByText(/^Resale closes /)).toBeVisible();
    await summary.confirmSell();

    await mailpit.waitForMessage(order.buyerEmail, { subjectContains: 'Your ticket is listed for resale' });
    const guest = summary.guest(order.buyerLastName);
    await expect(guest).toContainText('Listed for resale');
    await expect(summary.listedForResale()).toContainText('Resale closes');
    await expect(guest.getByRole('button', { name: 'View Ticket' })).toHaveCount(0);
    await expect(guest.getByRole('button', { name: 'Print Ticket' })).toHaveCount(0);
    await expect(summary.sellButton()).toHaveCount(0);
    await expect(summary.transferButton()).toHaveCount(0);
    await expect(page.getByTestId('attendee-edit-button')).toHaveCount(0);

    const ticketPage = new TicketPage(page);
    await page.goto(`/order/${event.eventId}/${order.orderShortId}/print`);
    await expect(ticketPage.unusableNotice()).toContainText('Listed for resale');
    await expect(ticketPage.qrCode()).toHaveCount(0);

    await ticketPage.goto(event.eventId, ticket.shortId);
    await expect(ticketPage.heading(event.title)).toBeVisible();
    await expect(ticketPage.unusableNotice()).toContainText("This ticket can't be used while it's listed");
    await expect(ticketPage.qrCode()).toHaveCount(0);
    await expect(ticketPage.transferButton()).toHaveCount(0);

    const checkIn = new CheckInPage(page);
    await checkIn.goto(door.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(order.buyerLastName);
    await checkIn.checkInButton(ticket.publicId).click();
    await expect(page.getByText(/ticket is listed for resale/)).toBeVisible();
    await expect(checkIn.checkOutButton(ticket.publicId)).toHaveCount(0);

    await summary.goto(event.eventId, order.orderShortId);
    await summary.withdrawListing();

    await expect(page.getByText('Listing withdrawn. Your ticket works again.')).toBeVisible();
    await expect(summary.listedForResale()).toHaveCount(0);
    await expect(guest.getByRole('button', { name: 'View Ticket' })).toBeVisible();
    await expect(summary.sellButton()).toBeVisible();
    await expect(page.getByTestId('attendee-edit-button')).toBeVisible();

    await ticketPage.goto(event.eventId, ticket.shortId);
    await expect(ticketPage.qrCode()).toBeVisible();
    await expect(ticketPage.unusableNotice()).toHaveCount(0);

    await checkIn.goto(door.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(order.buyerLastName);
    await checkIn.checkInButton(ticket.publicId).click();
    await expect(checkIn.checkOutButton(ticket.publicId)).toBeVisible();
  });
});
