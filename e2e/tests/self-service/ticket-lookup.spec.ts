import { test, expect } from '../../fixtures';
import { MyTicketsPage } from '../../pages/my-tickets.page';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { VerifyEmailDialog } from '../../pages/verify-email.page';
import { sendTicketLookupEmail } from '../../api/public-client';
import { uniqueEmail } from '../../utils/unique';

test.describe('ticket lookup', () => {
  test('a buyer requests a lookup link and views their tickets', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const buyerEmail = uniqueEmail('lookup');
    await createCompletedOrder(publicApi, event, { buyerEmail });

    await sendTicketLookupEmail(publicApi, buyerEmail);
    const lookupUrl = await mailpit.waitForLink(buyerEmail, /my-tickets/, { subjectContains: 'Your Tickets' });

    const myTickets = new MyTicketsPage(page);
    await myTickets.open(lookupUrl.href);

    await expect(page.getByRole('heading', { name: 'My Tickets' })).toBeVisible();
    await expect(myTickets.orderCardHeading(event.title)).toBeVisible();

    await myTickets.viewOrder();

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
  });

  test('a guest named on someone else\'s order finds only their own ticket', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const guestEmail = uniqueEmail('lookup-guest');
    const order = await createCompletedOrder(publicApi, event, { attendeeEmail: guestEmail, buyerFirstName: 'Named', buyerLastName: 'Guest' });

    await sendTicketLookupEmail(publicApi, guestEmail);
    const lookupUrl = await mailpit.waitForLink(guestEmail, /my-tickets/, { subjectContains: 'Your Tickets' });

    const myTickets = new MyTicketsPage(page);
    await myTickets.open(lookupUrl.href);

    await expect(myTickets.orderCardHeading(event.title)).toBeVisible();
    await expect(page.getByRole('link', { name: 'View Order' })).toHaveCount(0);
    await expect(page.getByText(order.buyerEmail)).toHaveCount(0);

    await page.getByRole('link', { name: 'View Ticket' }).click();
    await expect(page.getByRole('heading', { name: `Your ticket for ${event.title}` })).toBeVisible();
    await expect(page).toHaveURL(new RegExp(order.attendees[0].shortId));
  });

  test('a buyer arriving from their lookup link changes a ticket email without being asked for a code', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const buyerEmail = uniqueEmail('lookup-verified');
    await createCompletedOrder(publicApi, event, { buyerEmail });
    const newEmail = uniqueEmail('lookup-changed');

    await sendTicketLookupEmail(publicApi, buyerEmail);
    const lookupUrl = await mailpit.waitForLink(buyerEmail, /my-tickets/, { subjectContains: 'Your Tickets' });

    const myTickets = new MyTicketsPage(page);
    await myTickets.open(lookupUrl.href);
    await myTickets.viewOrder();
    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();

    await new OrderSummaryPage(page).editFirstAttendeeEmail(newEmail);

    await expect(page.getByText('Attendee updated successfully')).toBeVisible();
    await expect(new VerifyEmailDialog(page).dialog()).toHaveCount(0);
    expect(await mailpit.search(buyerEmail).then((messages) => messages.filter((m) => m.Subject.includes('verification code')))).toHaveLength(0);
  });
});
