import { test, expect } from '../../fixtures';
import { AttendeePage } from '../../pages/attendee.page';
import { createDraftEventWithTicket } from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';

test.describe('emails for events that are not published', () => {
  test('an attendee added to a draft event gets a notice without any organizer content', { tag: '@smoke' }, async ({ authedPage, api, account, mailpit }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId, { title: 'Call 805 891 5965 now' });
    const email = uniqueEmail('draft-attendee');

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await attendees.createAttendee({ firstName: 'Draft', lastName: 'Guest', email, productTitle: event.productTitle });

    await expect(attendees.rowByText(email)).toBeVisible();

    const summary = await mailpit.waitForMessage(email, { subjectContains: 'An update about your booking' });
    const message = await mailpit.getMessage(summary.ID);
    expect(message.Subject).not.toContain(event.title);
    expect(message.Text).not.toContain('805 891 5965');
    expect(message.Text).toContain('has not published this event yet');
  });

  test('the organizer still receives the full ticket email when testing a draft event on their own address', async ({ authedPage, api, account, mailpit }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await attendees.createAttendee({ firstName: 'Owner', lastName: 'Test', email: account.email, productTitle: event.productTitle });

    const message = await mailpit.waitForMessage(account.email, { subjectContains: 'Your Ticket' });
    expect(message.Subject).toContain(event.title);
  });
});
