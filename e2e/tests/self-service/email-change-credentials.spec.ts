import { test, expect } from '../../fixtures';
import { AttendeePage } from '../../pages/attendee.page';
import { CheckInPage } from '../../pages/check-in.page';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { uniqueEmail, uniqueName, uniqueShort } from '../../utils/unique';

test.describe('changing the email on a ticket', () => {
  test('an organizer changes a ticket email and the previous copy of the ticket stops working', async ({ authedPage, page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event, {
      buyerFirstName: 'Original',
      buyerLastName: uniqueShort('Holder').replace(' ', ''),
    });
    const previous = order.attendees[0];
    const list = await api.createCheckInList(event.eventId, { name: uniqueName('Main Door') });
    const newEmail = uniqueEmail('new-holder');

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await attendees.openRowAction(order.buyerEmail, 'Manage attendee');
    await attendees.changeEmail(newEmail);
    await expect(attendees.editButton()).toBeVisible();

    const ticketMail = await mailpit.waitForMessage(newEmail, { subjectContains: 'Your Ticket' });
    expect(ticketMail.Subject).toContain(event.title);
    await mailpit.waitForMessage(order.buyerEmail, { subjectContains: 'Your Ticket Details Have Been Changed' });

    await page.goto(`/product/${event.eventId}/${previous.shortId}`);
    await expect(page.getByText('Ticket Not Found')).toBeVisible();

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(order.buyerLastName);

    const row = page.getByRole('button', { name: /^View details for/ }).filter({ hasText: order.buyerLastName });
    await expect(row).toHaveCount(1);
    await expect(checkIn.attendeeRow(previous.publicId)).toHaveCount(0);

    await checkIn.search(previous.publicId);
    await expect(row).toHaveCount(0);

    await checkIn.search(order.buyerLastName);
    await row.getByRole('button', { name: /^Check in$/ }).click();
    await expect(row.getByRole('button', { name: /^Check out$/ })).toBeVisible();
  });
});
