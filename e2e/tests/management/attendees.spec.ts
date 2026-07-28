import fs from 'node:fs';
import { test, expect } from '../../fixtures';
import { AttendeePage } from '../../pages/attendee.page';
import { createEventWithAttendee, createLiveEventWithFreeTicket } from '../../api/factory';
import { uniqueEmail, uniqueShort } from '../../utils/unique';

test.describe('attendees', () => {
  test('an organizer manually creates an attendee and the ticket email is sent', { tag: '@smoke' }, async ({ authedPage, api, account, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const email = uniqueEmail('manual-attendee');

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await attendees.createAttendee({
      firstName: 'Manual',
      lastName: 'Entry',
      email,
      productTitle: event.productTitle,
    });

    await expect(attendees.rowByText(email)).toBeVisible();

    const message = await mailpit.waitForMessage(email, { subjectContains: 'Your Ticket' });
    expect(message.Subject).toContain(event.title);
  });

  test('an organizer renames an attendee from the manage drawer', async ({ authedPage, api, account }) => {
    const event = await createEventWithAttendee(api, account.organizerId);
    const newFirstName = uniqueShort('Renamed');

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await attendees.openRowAction(event.attendeeEmail, 'Manage attendee');
    await attendees.renameFirstName(newFirstName);

    await expect(attendees.editButton()).toBeVisible();
    await attendees.closeDrawer();
    await expect(attendees.rowByText(newFirstName)).toBeVisible();
  });

  test('an organizer resends a ticket email to an attendee', async ({ authedPage, api, account, mailpit }) => {
    const event = await createEventWithAttendee(api, account.organizerId);

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await attendees.openRowAction(event.attendeeEmail, 'Resend ticket email');

    const message = await mailpit.waitForMessage(event.attendeeEmail, { subjectContains: 'Your Ticket' });
    expect(message.Subject).toContain(event.title);
  });

  test('an organizer exports attendees to a spreadsheet', async ({ authedPage, api, account }) => {
    const event = await createEventWithAttendee(api, account.organizerId);

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);

    const [download] = await Promise.all([
      authedPage.waitForEvent('download'),
      attendees.clickExport(),
    ]);

    expect(download.suggestedFilename()).toBe('attendees.xlsx');
    const downloadPath = await download.path();
    expect(fs.statSync(downloadPath).size).toBeGreaterThan(0);
  });
});
