import { test, expect } from '../../fixtures';
import { MessagePage } from '../../pages/message.page';
import { createEventWithAttendee } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

test.describe('message lifecycle', () => {
  test('a message to all attendees is delivered to the attendee inbox', { tag: '@smoke' }, async ({ authedPage, api, account, mailpit }) => {
    const event = await createEventWithAttendee(api, account.organizerId);
    const subject = uniqueName('Doors open early');

    const messages = new MessagePage(authedPage);
    await messages.goto(event.eventId);
    await messages.sendToAllAttendees(subject, 'Doors open at 6pm — see you there!');

    await expect(messages.listItem(subject)).toBeVisible();
    await mailpit.waitForMessage(event.attendeeEmail, { subjectContains: subject });
  });

  test('the recipients view of a sent message lists the attendee email', async ({ authedPage, api, account }) => {
    const event = await createEventWithAttendee(api, account.organizerId);
    const subject = uniqueName('Parking info');

    const messages = new MessagePage(authedPage);
    await messages.goto(event.eventId);
    await messages.sendToAllAttendees(subject, 'Parking is available on Level 2.');

    await expect(messages.listItem(subject).getByText('SENT', { exact: true })).toBeVisible();
    await messages.openSentMessageRecipients();

    await expect(authedPage.getByText(event.attendeeEmail)).toBeVisible();
  });

  test('a scheduled message shows as scheduled and can be cancelled', async ({ authedPage, api, account }) => {
    const event = await createEventWithAttendee(api, account.organizerId);
    const subject = uniqueName('Reminder');
    const scheduledAt = new Date(Date.now() + 60 * 60 * 1000).toISOString().slice(0, 16);

    const messages = new MessagePage(authedPage);
    await messages.goto(event.eventId);
    await messages.scheduleToAllAttendees(subject, 'The event starts soon!', scheduledAt);

    await expect(messages.listItem(subject).getByText('SCHEDULED', { exact: true })).toBeVisible();

    await messages.cancelScheduledMessage();

    await expect(messages.listItem(subject).getByText('CANCELLED', { exact: true })).toBeVisible();
  });
});
